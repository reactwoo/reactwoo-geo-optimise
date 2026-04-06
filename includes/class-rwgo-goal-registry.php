<?php
/**
 * Resolves which experiments / goals apply to the current front request.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Goal + handler registry for front-end localization.
 */
class RWGO_Goal_Registry {

	/**
	 * Build rwgoTracking localisation: experiments active on this page (control or variant URL).
	 *
	 * @param int $queried_post_id Current singular post ID.
	 * @return array<string, mixed>
	 */
	public static function build_frontend_config( $queried_post_id ) {
		$queried_post_id = (int) $queried_post_id;
		$experiments     = array();

		foreach ( RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 200 ) ) as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$cfg = RWGO_Experiment_Repository::get_config( $post->ID );
			if ( empty( $cfg['status'] ) || 'active' !== $cfg['status'] || empty( $cfg['experiment_key'] ) ) {
				continue;
			}
			$source = (int) ( $cfg['source_page_id'] ?? 0 );
			$match  = false;
			if ( $source === $queried_post_id ) {
				$match = true;
			} else {
				foreach ( isset( $cfg['variants'] ) && is_array( $cfg['variants'] ) ? $cfg['variants'] : array() as $row ) {
					if ( is_array( $row ) && (int) ( $row['page_id'] ?? 0 ) === $queried_post_id ) {
						$match = true;
						break;
					}
				}
			}
			if ( ! $match ) {
				continue;
			}
			$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
			$goals = self::expand_defined_elementor_goals_across_pages( $goals, $cfg );
			$logical_primary = class_exists( 'RWGO_Goal_Mapping', false ) && RWGO_Goal_Mapping::is_active( $cfg )
				? RWGO_Goal_Mapping::logical_goal_id( $cfg )
				: '';
			/**
			 * Goals passed to rwgo-tracking.js after cross-page Elementor expansion.
			 *
			 * @param list<array<string, mixed>> $goals Goals for this experiment on this request.
			 * @param array<string, mixed>       $cfg   Full experiment config.
			 * @param int                        $queried_post_id Current singular post ID.
			 */
			$goals = apply_filters( 'rwgo_frontend_experiment_goals', $goals, $cfg, $queried_post_id );
			if ( empty( $goals ) ) {
				$goals = array(
					array(
						'goal_id'   => 'goal_default_pageview',
						'goal_type' => 'page_view',
						'label'     => __( 'Page view', 'reactwoo-geo-optimise' ),
						'handlers'  => array(),
					),
				);
			}
			$resolved_variant = class_exists( 'RWGO_Experiment_Service', false )
				? RWGO_Experiment_Service::resolve_variant_for_context( $cfg, $queried_post_id )
				: '';

			$variant_labels = class_exists( 'RWGO_GTM_Handoff', false )
				? RWGO_GTM_Handoff::variant_labels_map( $cfg )
				: array();

			$experiments[] = array(
				'experimentId'         => (int) $post->ID,
				'experimentKey'        => (string) $cfg['experiment_key'],
				'testName'             => get_the_title( $post ),
				'builder'              => class_exists( 'RWGO_GTM_Handoff', false )
					? RWGO_GTM_Handoff::builder_slug_for_datalayer( $cfg )
					: ( isset( $cfg['builder_type'] ) ? sanitize_key( (string) $cfg['builder_type'] ) : '' ),
				'variantLabels'        => $variant_labels,
				'sourcePageId'         => $source,
				'goals'                => $goals,
				'resolvedVariant'      => (string) $resolved_variant,
				'logicalPrimaryGoalId' => (string) $logical_primary,
			);
		}

		$strict = class_exists( 'RWGO_Settings', false ) && RWGO_Settings::strict_binding_mode_enabled();

		return array(
			'pageContextId'  => $queried_post_id,
			'experiments'    => $experiments,
			'strictBinding'  => $strict,
		);
	}

	/**
	 * Elementor (and Gutenberg) defined goals use per-post hashed goal_id/handler_id values.
	 * The experiment config usually stores the pair from the page where the user picked the goal
	 * (often Control). The same CTA on Variant B has different IDs, so rwgo-tracking.js would not
	 * stamp data-rwgo-experiment-key there and clicks would not count. Re-scan all experiment
	 * pages and add matching goals (same label + ui_goal_type) so every page’s stamps match.
	 *
	 * @param list<array<string, mixed>> $goals Goals from experiment meta.
	 * @param array<string, mixed>       $cfg   Experiment config.
	 * @return list<array<string, mixed>>
	 */
	private static function expand_defined_elementor_goals_across_pages( array $goals, array $cfg ) {
		if ( class_exists( 'RWGO_Goal_Mapping', false ) && RWGO_Goal_Mapping::is_active( $cfg ) ) {
			return $goals;
		}
		if ( empty( $goals ) ) {
			return $goals;
		}
		$post_ids = array( (int) ( $cfg['source_page_id'] ?? 0 ) );
		foreach ( isset( $cfg['variants'] ) && is_array( $cfg['variants'] ) ? $cfg['variants'] : array() as $row ) {
			if ( is_array( $row ) && ! empty( $row['page_id'] ) ) {
				$post_ids[] = (int) $row['page_id'];
			}
		}
		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
		if ( count( $post_ids ) < 1 ) {
			return $goals;
		}

		$discovered = class_exists( 'RWGO_Defined_Goal_Service', false )
			? RWGO_Defined_Goal_Service::collect_for_posts( $post_ids )
			: array();

		$by_match_key = array();
		foreach ( $discovered as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$builder = isset( $row['builder'] ) ? sanitize_key( (string) $row['builder'] ) : '';
			$st      = isset( $row['source_type'] ) ? sanitize_key( (string) $row['source_type'] ) : '';
			$prefix  = '';
			if ( 'elementor' === $builder && 'elementor_widget' === $st ) {
				$prefix = 'el:';
			} elseif ( 'gutenberg' === $builder && 'gutenberg_block' === $st ) {
				$prefix = 'gb:';
			} else {
				continue;
			}
			$label = isset( $row['goal_label'] ) ? sanitize_text_field( (string) $row['goal_label'] ) : '';
			$ui    = isset( $row['ui_goal_type'] ) ? sanitize_key( (string) $row['ui_goal_type'] ) : '';
			$key   = $prefix . $label . "\x1e" . $ui;
			if ( ! isset( $by_match_key[ $key ] ) ) {
				$by_match_key[ $key ] = array();
			}
			$by_match_key[ $key ][] = $row;
		}

		$pairs_present = array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			$h0 = isset( $g['handlers'][0] ) && is_array( $g['handlers'][0] ) ? $g['handlers'][0] : array();
			$hid = isset( $h0['handler_id'] ) ? sanitize_key( (string) $h0['handler_id'] ) : '';
			$pairs_present[ sanitize_key( (string) $g['goal_id'] ) . '|' . $hid ] = true;
		}

		$out = $goals;
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['is_defined'] ) ) {
				continue;
			}
			$b = isset( $g['builder'] ) ? sanitize_key( (string) $g['builder'] ) : '';
			if ( 'elementor' !== $b && 'gutenberg' !== $b ) {
				continue;
			}
			$gt = isset( $g['goal_type'] ) ? sanitize_key( (string) $g['goal_type'] ) : '';
			if ( ! in_array( $gt, array( 'click', 'form_submit' ), true ) ) {
				continue;
			}
			$label = isset( $g['label'] ) ? sanitize_text_field( (string) $g['label'] ) : '';
			$ui    = isset( $g['ui_goal_type'] ) ? sanitize_key( (string) $g['ui_goal_type'] ) : '';
			$key   = ( 'gutenberg' === $b ? 'gb:' : 'el:' ) . $label . "\x1e" . $ui;
			if ( ! isset( $by_match_key[ $key ] ) ) {
				continue;
			}
			foreach ( $by_match_key[ $key ] as $row ) {
				$gid = isset( $row['goal_id'] ) ? sanitize_key( (string) $row['goal_id'] ) : '';
				$hid = isset( $row['handler_id'] ) ? sanitize_key( (string) $row['handler_id'] ) : '';
				if ( '' === $gid || '' === $hid ) {
					continue;
				}
				$pk = $gid . '|' . $hid;
				if ( isset( $pairs_present[ $pk ] ) ) {
					continue;
				}
				$clone = $g;
				$clone['goal_id']     = $gid;
				$clone['is_primary']  = false;
				$clone['handlers']    = isset( $clone['handlers'] ) && is_array( $clone['handlers'] ) ? $clone['handlers'] : array();
				if ( ! empty( $clone['handlers'][0] ) && is_array( $clone['handlers'][0] ) ) {
					$clone['handlers'][0]['handler_id'] = $hid;
				} else {
					$clone['handlers'] = array(
						array(
							'handler_id'   => $hid,
							'handler_type' => 'form_submit' === $gt ? 'form_submit' : 'click',
							'label'        => $label,
							'selector'     => '',
							'dedupe'       => 'allow_multiple',
							'event_name'   => 'rwgo_goal_fired',
						),
					);
				}
				$out[]                 = $clone;
				$pairs_present[ $pk ] = true;
			}
		}

		return $out;
	}
}
