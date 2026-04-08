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
			$cfg = RWGO_Experiment_Repository::normalize_page_bindings(
				RWGO_Experiment_Repository::get_config( $post->ID ),
				$post->ID,
				false
			);
			if ( empty( $cfg['status'] ) || 'active' !== $cfg['status'] || empty( $cfg['experiment_key'] ) ) {
				continue;
			}
			if ( ! RWGO_Experiment_Repository::config_touches_page_id( $cfg, $queried_post_id ) ) {
				continue;
			}
			$source = (int) ( $cfg['source_page_id'] ?? 0 );
			$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
			if ( class_exists( 'RWGO_Goal_Mapping', false ) && RWGO_Goal_Mapping::is_active( $cfg ) ) {
				$goals = self::merge_mapping_physical_pairs_into_goals( $goals, $cfg );
			}
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
				? RWGO_Experiment_Service::resolve_variant_for_context( $cfg, $queried_post_id, $post->ID )
				: '';

			$variant_labels = class_exists( 'RWGO_GTM_Handoff', false )
				? RWGO_GTM_Handoff::variant_labels_map( $cfg )
				: array();

			$mapping_active = class_exists( 'RWGO_Goal_Mapping', false ) && RWGO_Goal_Mapping::is_active( $cfg );

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
				'mappingActive'        => $mapping_active,
			);
		}

		$strict = class_exists( 'RWGO_Settings', false ) && RWGO_Settings::strict_binding_mode_enabled();

		if ( self::should_log_frontend_config_summary() ) {
			$dbg = array();
			foreach ( $experiments as $ex ) {
				if ( ! is_array( $ex ) ) {
					continue;
				}
				$goals = isset( $ex['goals'] ) && is_array( $ex['goals'] ) ? $ex['goals'] : array();
				$pairs = self::list_goal_handler_pairs_for_debug( $goals );
				$dbg[] = array(
					'experimentPostId'     => isset( $ex['experimentId'] ) ? (int) $ex['experimentId'] : 0,
					'experimentKey'        => isset( $ex['experimentKey'] ) ? (string) $ex['experimentKey'] : '',
					'resolvedVariant'      => isset( $ex['resolvedVariant'] ) ? (string) $ex['resolvedVariant'] : '',
					'goalCount'            => count( $goals ),
					'logicalPrimaryGoalId' => isset( $ex['logicalPrimaryGoalId'] ) ? (string) $ex['logicalPrimaryGoalId'] : '',
					'mappingActive'        => ! empty( $ex['mappingActive'] ),
					'goalHandlerPairs'     => $pairs,
				);
			}
			error_log( '[RWGO frontend config] page=' . (string) $queried_post_id . ' ' . wp_json_encode( $dbg ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- gated debug.
		}

		return array(
			'pageContextId'  => $queried_post_id,
			'experiments'    => $experiments,
			'strictBinding'  => $strict,
		);
	}

	/**
	 * Log {@see build_frontend_config()} summary to error_log (pairs for stamping / REST).
	 *
	 * @return bool
	 */
	private static function should_log_frontend_config_summary() {
		if ( defined( 'RWGO_TRACKING_DEBUG' ) && RWGO_TRACKING_DEBUG ) {
			return true;
		}
		if ( defined( 'RWGO_FRONTEND_CONFIG_LOG' ) && RWGO_FRONTEND_CONFIG_LOG ) {
			return true;
		}
		/**
		 * @param bool $log Default false.
		 */
		return (bool) apply_filters( 'rwgo_log_frontend_goal_config', false );
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
	/**
	 * Ensure localized goals include every physical (goal_id, handler_id) from `defined_goal_mapping.targets`
	 * so rwgo-tracking.js can stamp `data-rwgo-experiment-key` on Control and Variant B DOM.
	 *
	 * @param list<array<string, mixed>> $goals Goals from meta (may omit a variant row).
	 * @param array<string, mixed>       $cfg   Experiment config.
	 * @return list<array<string, mixed>>
	 */
	private static function merge_mapping_physical_pairs_into_goals( array $goals, array $cfg ) {
		if ( ! class_exists( 'RWGO_Goal_Mapping', false ) || ! RWGO_Goal_Mapping::is_active( $cfg ) ) {
			return $goals;
		}
		$m       = isset( $cfg['defined_goal_mapping'] ) && is_array( $cfg['defined_goal_mapping'] ) ? $cfg['defined_goal_mapping'] : array();
		$logical = RWGO_Goal_Mapping::logical_goal_id( $cfg );
		$targets = isset( $m['targets'] ) && is_array( $m['targets'] ) ? $m['targets'] : array();

		$have = array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			$gid = sanitize_key( (string) $g['goal_id'] );
			$hs  = isset( $g['handlers'] ) && is_array( $g['handlers'] ) ? $g['handlers'] : array();
			foreach ( $hs as $h ) {
				if ( ! is_array( $h ) || empty( $h['handler_id'] ) ) {
					continue;
				}
				$have[ $gid . '|' . sanitize_key( (string) $h['handler_id'] ) ] = true;
			}
		}

		$out = $goals;
		foreach ( array( 'control', 'var_b' ) as $vkey ) {
			$pairs = isset( $targets[ $vkey ] ) && is_array( $targets[ $vkey ] ) ? $targets[ $vkey ] : array();
			foreach ( $pairs as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				$gid = sanitize_key( (string) ( $p['goal_id'] ?? '' ) );
				$hid = sanitize_key( (string) ( $p['handler_id'] ?? '' ) );
				if ( '' === $gid || '' === $hid ) {
					continue;
				}
				$pk = $gid . '|' . $hid;
				if ( isset( $have[ $pk ] ) ) {
					continue;
				}
				$template = self::template_goal_for_mapping_slot( $out, $logical, $vkey );
				if ( null === $template ) {
					$template = array(
						'goal_type'   => 'click',
						'label'       => __( 'Conversion goal', 'reactwoo-geo-optimise' ),
						'is_defined'  => true,
						'builder'     => 'elementor',
						'ui_goal_type'=> 'cta_click',
						'handlers'    => array(
							array(
								'handler_id'   => $hid,
								'handler_type' => 'click',
								'label'        => __( 'Conversion goal', 'reactwoo-geo-optimise' ),
								'selector'     => '',
								'dedupe'       => 'allow_multiple',
								'event_name'   => 'rwgo_goal_fired',
							),
						),
					);
				}
				$out[]    = self::clone_goal_for_physical_pair( $template, $gid, $hid, $logical, $vkey );
				$have[ $pk ] = true;
			}
		}

		return $out;
	}

	/**
	 * @param list<array<string, mixed>> $goals  Current goals list.
	 * @param string                     $logical Logical goal id from mapping.
	 * @param string                     $vkey   control|var_b.
	 * @return array<string, mixed>|null
	 */
	private static function template_goal_for_mapping_slot( array $goals, $logical, $vkey ) {
		$logical = sanitize_key( (string) $logical );
		$vkey    = sanitize_key( (string) $vkey );
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) ) {
				continue;
			}
			if ( isset( $g['mapping_variant'] ) && sanitize_key( (string) $g['mapping_variant'] ) === $vkey ) {
				return $g;
			}
		}
		if ( '' !== $logical ) {
			foreach ( $goals as $g ) {
				if ( ! is_array( $g ) ) {
					continue;
				}
				if ( isset( $g['logical_goal_id'] ) && sanitize_key( (string) $g['logical_goal_id'] ) === $logical ) {
					return $g;
				}
			}
		}
		foreach ( $goals as $g ) {
			if ( is_array( $g ) && ! empty( $g['is_defined'] ) && ! empty( $g['handlers'][0] ) ) {
				return $g;
			}
		}
		foreach ( $goals as $g ) {
			if ( is_array( $g ) && ! empty( $g['handlers'][0] ) ) {
				return $g;
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $template From an existing goal row.
	 * @param string               $gid      Physical goal id.
	 * @param string               $hid      Physical handler id.
	 * @param string               $logical  Logical primary id.
	 * @param string               $vkey     control|var_b.
	 * @return array<string, mixed>
	 */
	private static function clone_goal_for_physical_pair( array $template, $gid, $hid, $logical, $vkey ) {
		$clone                 = $template;
		$clone['goal_id']      = sanitize_key( (string) $gid );
		$clone['is_primary']   = false;
		$clone['mapping_variant'] = sanitize_key( (string) $vkey );
		if ( '' !== (string) $logical ) {
			$clone['logical_goal_id'] = sanitize_key( (string) $logical );
		}
		$gt = isset( $clone['goal_type'] ) ? sanitize_key( (string) $clone['goal_type'] ) : 'click';
		if ( 'page_view' === $gt ) {
			$clone['goal_type'] = 'click';
		}
		if ( ! isset( $clone['handlers'] ) || ! is_array( $clone['handlers'] ) ) {
			$clone['handlers'] = array();
		}
		if ( isset( $clone['handlers'][0] ) && is_array( $clone['handlers'][0] ) ) {
			$clone['handlers'][0]['handler_id'] = sanitize_key( (string) $hid );
			if ( empty( $clone['handlers'][0]['handler_type'] ) ) {
				$clone['handlers'][0]['handler_type'] = ( 'form_submit' === $clone['goal_type'] ) ? 'form_submit' : 'click';
			}
		} else {
			$clone['handlers'] = array(
				array(
					'handler_id'   => sanitize_key( (string) $hid ),
					'handler_type' => ( 'form_submit' === $clone['goal_type'] ) ? 'form_submit' : 'click',
					'label'        => isset( $clone['label'] ) ? (string) $clone['label'] : '',
					'selector'     => '',
					'dedupe'       => 'allow_multiple',
					'event_name'   => 'rwgo_goal_fired',
				),
			);
		}
		return $clone;
	}

	/**
	 * @param list<array<string, mixed>> $goals Goals for one experiment.
	 * @return list<array{goal_id: string, handler_id: string}>
	 */
	private static function list_goal_handler_pairs_for_debug( array $goals ) {
		$out = array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			$gid = sanitize_key( (string) $g['goal_id'] );
			$hs  = isset( $g['handlers'] ) && is_array( $g['handlers'] ) ? $g['handlers'] : array();
			foreach ( $hs as $h ) {
				if ( ! is_array( $h ) || empty( $h['handler_id'] ) ) {
					continue;
				}
				$out[] = array(
					'goal_id'    => $gid,
					'handler_id' => sanitize_key( (string) $h['handler_id'] ),
				);
			}
		}
		return $out;
	}

	/**
	 * Older automatic builder goals may only retain a generic click/form shell with no builder metadata.
	 *
	 * @param array<string, mixed> $goal Saved goal row.
	 * @return bool
	 */
	private static function is_legacy_automatic_builder_goal( array $goal ) {
		if ( ! empty( $goal['builder'] ) || ! empty( $goal['ui_goal_type'] ) || ! empty( $goal['source_type'] ) || ! empty( $goal['elementor_id'] ) ) {
			return false;
		}
		$gt = isset( $goal['goal_type'] ) ? sanitize_key( (string) $goal['goal_type'] ) : '';
		return in_array( $gt, array( 'click', 'form_submit' ), true );
	}

	/**
	 * If a legacy automatic builder goal has exactly one stable live key across the touched pages,
	 * expand against that key as a backwards-compatible fallback.
	 *
	 * @param list<array<string, mixed>> $discovered Live discovered rows.
	 * @param string                     $builder    Effective builder slug.
	 * @param string                     $goal_type  Goal type.
	 * @return array{key: string, rows: list<array<string, mixed>>}
	 */
	private static function legacy_auto_match_group( array $discovered, $builder, $goal_type ) {
		$builder = sanitize_key( (string) $builder );
		$goal_type = sanitize_key( (string) $goal_type );
		$groups = array();
		foreach ( $discovered as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( sanitize_key( (string) ( $row['builder'] ?? '' ) ) !== $builder ) {
				continue;
			}
			if ( sanitize_key( (string) ( $row['goal_type'] ?? '' ) ) !== $goal_type ) {
				continue;
			}
			$key = class_exists( 'RWGO_Defined_Goal_Service', false )
				? RWGO_Defined_Goal_Service::preferred_physical_goal_match_key( $row, false )
				: '';
			if ( '' === $key ) {
				continue;
			}
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array();
			}
			$groups[ $key ][] = $row;
		}
		if ( 1 !== count( $groups ) ) {
			return array(
				'key'  => '',
				'rows' => array(),
			);
		}
		$key = (string) array_key_first( $groups );
		return array(
			'key'  => $key,
			'rows' => $groups[ $key ],
		);
	}

	/**
	 * Map a runtime-expanded physical goal/handler pair back to the canonical stored pair from config.
	 *
	 * This lets the REST endpoint accept recovered live Elementor/Gutenberg pairs while still storing
	 * the stable config pair that reports aggregate against.
	 *
	 * @param array<string, mixed> $cfg        Experiment config.
	 * @param string               $goal_id    Runtime physical goal id.
	 * @param string               $handler_id Runtime physical handler id.
	 * @return array{goal_id: string, handler_id: string}|null
	 */
	public static function canonical_stored_pair_for_runtime_pair( array $cfg, $goal_id, $handler_id ) {
		$goal_id    = sanitize_key( (string) $goal_id );
		$handler_id = sanitize_key( (string) $handler_id );
		if ( '' === $goal_id || '' === $handler_id ) {
			return null;
		}
		if ( class_exists( 'RWGO_Goal_Mapping', false ) && RWGO_Goal_Mapping::is_active( $cfg ) ) {
			return null;
		}
		$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
		if ( empty( $goals ) ) {
			return null;
		}
		$post_ids = array( (int) ( $cfg['source_page_id'] ?? 0 ) );
		foreach ( isset( $cfg['variants'] ) && is_array( $cfg['variants'] ) ? $cfg['variants'] : array() as $row ) {
			if ( is_array( $row ) && ! empty( $row['page_id'] ) ) {
				$post_ids[] = (int) $row['page_id'];
			}
		}
		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
		if ( empty( $post_ids ) || ! class_exists( 'RWGO_Defined_Goal_Service', false ) ) {
			return null;
		}
		$discovered = RWGO_Defined_Goal_Service::collect_for_posts( $post_ids );
		$by_match_key = array();
		foreach ( $discovered as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = RWGO_Defined_Goal_Service::preferred_physical_goal_match_key( $row, false );
			if ( '' === $key ) {
				continue;
			}
			if ( ! isset( $by_match_key[ $key ] ) ) {
				$by_match_key[ $key ] = array();
			}
			$by_match_key[ $key ][] = $row;
		}
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			if ( isset( $g['source_type'] ) && 'page_destination' === sanitize_key( (string) $g['source_type'] ) ) {
				continue;
			}
			$canonical_h0  = isset( $g['handlers'][0] ) && is_array( $g['handlers'][0] ) ? $g['handlers'][0] : array();
			$canonical_hid = isset( $canonical_h0['handler_id'] ) ? sanitize_key( (string) $canonical_h0['handler_id'] ) : '';
			$canonical_gid = sanitize_key( (string) $g['goal_id'] );
			if ( '' === $canonical_gid || '' === $canonical_hid ) {
				continue;
			}
			$b = isset( $g['builder'] ) ? sanitize_key( (string) $g['builder'] ) : sanitize_key( (string) ( $cfg['builder_type'] ?? '' ) );
			if ( 'elementor' !== $b && 'gutenberg' !== $b ) {
				continue;
			}
			$gt = isset( $g['goal_type'] ) ? sanitize_key( (string) $g['goal_type'] ) : '';
			if ( ! in_array( $gt, array( 'click', 'form_submit' ), true ) ) {
				continue;
			}
			$g = RWGO_Defined_Goal_Service::enrich_saved_goal_with_live_identity( $g, $discovered );
			$matched_rows = array();
			$key          = RWGO_Defined_Goal_Service::preferred_physical_goal_match_key( $g, true );
			if ( '' !== $key && isset( $by_match_key[ $key ] ) ) {
				$matched_rows = $by_match_key[ $key ];
			} else {
				$best_row   = null;
				$best_score = -1;
				foreach ( $discovered as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$score = RWGO_Defined_Goal_Service::loose_live_match_score( $g, $row );
					if ( $score > $best_score ) {
						$best_row   = $row;
						$best_score = $score;
					}
				}
				if ( $best_score > 0 && is_array( $best_row ) ) {
					$best_key = RWGO_Defined_Goal_Service::preferred_physical_goal_match_key( $best_row, false );
					if ( '' !== $best_key && isset( $by_match_key[ $best_key ] ) ) {
						$matched_rows = $by_match_key[ $best_key ];
					} else {
						$matched_rows[] = $best_row;
					}
				}
			}
			if ( empty( $matched_rows ) && self::is_legacy_automatic_builder_goal( $g ) ) {
				$legacy_group = self::legacy_auto_match_group( $discovered, $b, $gt );
				if ( ! empty( $legacy_group['rows'] ) ) {
					$matched_rows = $legacy_group['rows'];
				}
			}
			foreach ( $matched_rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$gid = sanitize_key( (string) ( $row['goal_id'] ?? '' ) );
				$hid = sanitize_key( (string) ( $row['handler_id'] ?? '' ) );
				if ( $gid === $goal_id && $hid === $handler_id ) {
					return array(
						'goal_id'    => $canonical_gid,
						'handler_id' => $canonical_hid,
					);
				}
			}
		}
		return null;
	}

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
			$key = class_exists( 'RWGO_Defined_Goal_Service', false )
				? RWGO_Defined_Goal_Service::preferred_physical_goal_match_key( $row, false )
				: '';
			if ( '' === $key ) {
				continue;
			}
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

		$out             = $goals;
		$pairs_to_remove = array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) ) {
				continue;
			}
			if ( isset( $g['source_type'] ) && 'page_destination' === sanitize_key( (string) $g['source_type'] ) ) {
				continue;
			}
			$b = isset( $g['builder'] ) ? sanitize_key( (string) $g['builder'] ) : sanitize_key( (string) ( $cfg['builder_type'] ?? '' ) );
			if ( 'elementor' !== $b && 'gutenberg' !== $b ) {
				continue;
			}
			$gt = isset( $g['goal_type'] ) ? sanitize_key( (string) $g['goal_type'] ) : '';
			if ( ! in_array( $gt, array( 'click', 'form_submit' ), true ) ) {
				continue;
			}
			if ( class_exists( 'RWGO_Defined_Goal_Service', false ) ) {
				$g = RWGO_Defined_Goal_Service::enrich_saved_goal_with_live_identity( $g, $discovered );
			}
			$matched_rows = array();
			$best_score   = -1;
			$best_key     = '';
			$key          = class_exists( 'RWGO_Defined_Goal_Service', false )
				? RWGO_Defined_Goal_Service::preferred_physical_goal_match_key( $g, true )
				: '';
			if ( '' !== $key && isset( $by_match_key[ $key ] ) ) {
				$matched_rows = $by_match_key[ $key ];
			} elseif ( class_exists( 'RWGO_Defined_Goal_Service', false ) ) {
				$best_row   = null;
				$best_score = -1;
				foreach ( $discovered as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$score = RWGO_Defined_Goal_Service::loose_live_match_score( $g, $row );
					if ( $score > $best_score ) {
						$best_row   = $row;
						$best_score = $score;
					}
				}
				if ( $best_score > 0 && is_array( $best_row ) ) {
					$best_key = RWGO_Defined_Goal_Service::preferred_physical_goal_match_key( $best_row, false );
					if ( '' !== $best_key && isset( $by_match_key[ $best_key ] ) ) {
						$matched_rows = $by_match_key[ $best_key ];
					} else {
						$matched_rows[] = $best_row;
					}
				}
			}
			if ( empty( $matched_rows ) && self::is_legacy_automatic_builder_goal( $g ) ) {
				$legacy_group = self::legacy_auto_match_group( $discovered, $b, $gt );
				if ( ! empty( $legacy_group['rows'] ) ) {
					$matched_rows = $legacy_group['rows'];
					$orig_h0      = isset( $g['handlers'][0] ) && is_array( $g['handlers'][0] ) ? $g['handlers'][0] : array();
					$orig_hid     = isset( $orig_h0['handler_id'] ) ? sanitize_key( (string) $orig_h0['handler_id'] ) : '';
					$orig_gid     = isset( $g['goal_id'] ) ? sanitize_key( (string) $g['goal_id'] ) : '';
					$orig_pk      = $orig_gid . '|' . $orig_hid;
					if ( '' !== $orig_gid && '' !== $orig_hid ) {
						$pairs_to_remove[ $orig_pk ] = true;
						unset( $pairs_present[ $orig_pk ] );
					}
				}
			}
			if ( empty( $matched_rows ) ) {
				continue;
			}
			$first_clone = true;
			foreach ( $matched_rows as $row ) {
				$gid = isset( $row['goal_id'] ) ? sanitize_key( (string) $row['goal_id'] ) : '';
				$hid = isset( $row['handler_id'] ) ? sanitize_key( (string) $row['handler_id'] ) : '';
				if ( '' === $gid || '' === $hid ) {
					continue;
				}
				$pk = $gid . '|' . $hid;
				if ( isset( $pairs_present[ $pk ] ) ) {
					continue;
				}
				$clone = class_exists( 'RWGO_Defined_Goal_Service', false )
					? RWGO_Defined_Goal_Service::rebuild_saved_defined_goal_from_live( $g, $row )
					: null;
				if ( ! is_array( $clone ) ) {
					$clone = $g;
					$clone['goal_id']  = $gid;
					$clone['handlers'] = isset( $clone['handlers'] ) && is_array( $clone['handlers'] ) ? $clone['handlers'] : array();
					if ( ! empty( $clone['handlers'][0] ) && is_array( $clone['handlers'][0] ) ) {
						$clone['handlers'][0]['handler_id'] = $hid;
					} else {
						$clone['handlers'] = array(
							array(
								'handler_id'   => $hid,
								'handler_type' => 'form_submit' === $gt ? 'form_submit' : 'click',
								'label'        => isset( $clone['label'] ) ? (string) $clone['label'] : '',
								'selector'     => '',
								'dedupe'       => 'allow_multiple',
								'event_name'   => 'rwgo_goal_fired',
							),
						);
					}
				}
				$clone['is_primary'] = $first_clone ? ! empty( $g['is_primary'] ) : false;
				$out[]                 = $clone;
				$pairs_present[ $pk ] = true;
				$first_clone          = false;
			}
		}
		if ( ! empty( $pairs_to_remove ) ) {
			$out = array_values(
				array_filter(
					$out,
					static function ( $goal ) use ( $pairs_to_remove ) {
						if ( ! is_array( $goal ) || empty( $goal['goal_id'] ) ) {
							return true;
						}
						$h0  = isset( $goal['handlers'][0] ) && is_array( $goal['handlers'][0] ) ? $goal['handlers'][0] : array();
						$hid = isset( $h0['handler_id'] ) ? sanitize_key( (string) $h0['handler_id'] ) : '';
						$pk  = sanitize_key( (string) $goal['goal_id'] ) . '|' . $hid;
						return ! isset( $pairs_to_remove[ $pk ] );
					}
				)
			);
		}

		return $out;
	}
}
