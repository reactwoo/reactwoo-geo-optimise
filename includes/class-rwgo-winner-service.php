<?php
/**
 * Winner = best conversion rate where completions = sum of mapped goal fires per variant.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregates assignments + stored goal events for reporting.
 */
class RWGO_Winner_Service {

	/**
	 * Filtered breakdown: one row per (goal_id, handler_id) with counts per variant (display labels only).
	 *
	 * @param string               $experiment_key Key.
	 * @param array<string, mixed> $config         Experiment config.
	 * @param list<string>         $variant_slugs  Variants to show columns for.
	 * @return list<array{pair_key: string, label: string, counts: array<string, int>}>
	 */
	public static function goal_breakdown_rows( $experiment_key, array $config, array $variant_slugs ) {
		if ( ! class_exists( 'RWGO_Event_Store', false ) || ! class_exists( 'RWGO_Experiment_Measurements', false ) ) {
			return array();
		}
		$key = sanitize_text_field( (string) $experiment_key );
		if ( '' === $key ) {
			return array();
		}
		$slugs = array();
		foreach ( $variant_slugs as $s ) {
			$k = sanitize_key( (string) $s );
			if ( '' !== $k ) {
				$slugs[] = $k;
			}
		}
		if ( empty( $slugs ) ) {
			return array();
		}
		$allowed = self::allowed_pairs_by_variant( $config, $slugs );
		// Seed rows from configured pairs so the report lists measured CTAs even when counts are still zero.
		$acc = array();
		foreach ( $slugs as $slug ) {
			if ( empty( $allowed[ $slug ] ) || ! is_array( $allowed[ $slug ] ) ) {
				continue;
			}
			foreach ( array_keys( $allowed[ $slug ] ) as $pk ) {
				if ( isset( $acc[ $pk ] ) ) {
					continue;
				}
				$parts = explode( '|', $pk, 2 );
				$g     = isset( $parts[0] ) ? $parts[0] : '';
				$h     = isset( $parts[1] ) ? $parts[1] : '';
				$acc[ $pk ] = array(
					'pair_key' => $pk,
					'label'    => RWGO_Experiment_Measurements::label_for_pair( $config, $g, $h ),
					'counts'   => array_fill_keys( $slugs, 0 ),
				);
			}
		}

		$raw = RWGO_Event_Store::count_breakdown_by_variant_goal_handler( $key );
		foreach ( $raw as $row ) {
			$v = $row['variant_id'];
			if ( ! isset( $allowed[ $v ] ) ) {
				continue;
			}
			$pk = $row['goal_id'] . '|' . $row['handler_id'];
			if ( empty( $allowed[ $v ][ $pk ] ) ) {
				continue;
			}
			if ( ! isset( $acc[ $pk ] ) ) {
				$acc[ $pk ] = array(
					'pair_key' => $pk,
					'label'    => RWGO_Experiment_Measurements::label_for_pair( $config, $row['goal_id'], $row['handler_id'] ),
					'counts'   => array_fill_keys( $slugs, 0 ),
				);
			}
			$acc[ $pk ]['counts'][ $v ] = (int) $row['c'];
		}
		$vals = array_values( $acc );
		usort(
			$vals,
			static function ( $a, $b ) {
				$la = isset( $a['label'] ) ? (string) $a['label'] : '';
				$lb = isset( $b['label'] ) ? (string) $b['label'] : '';
				return strcasecmp( $la, $lb );
			}
		);
		return $vals;
	}

	/**
	 * Event-level breakdown using the actual stored fired label/type, grouped per variant.
	 *
	 * @param string               $experiment_key Key.
	 * @param array<string, mixed> $config         Experiment config.
	 * @param list<string>         $variant_slugs  Variants to show columns for.
	 * @return list<array{bucket_key: string, label: string, goal_type: string, fingerprint: string, counts: array<string, int>}>
	 */
	public static function fired_touchpoint_rows( $experiment_key, array $config, array $variant_slugs ) {
		if ( ! class_exists( 'RWGO_Event_Store', false ) || ! class_exists( 'RWGO_Experiment_Measurements', false ) ) {
			return array();
		}
		$key = sanitize_text_field( (string) $experiment_key );
		if ( '' === $key ) {
			return array();
		}
		$slugs = array();
		foreach ( $variant_slugs as $s ) {
			$k = sanitize_key( (string) $s );
			if ( '' !== $k ) {
				$slugs[] = $k;
			}
		}
		if ( empty( $slugs ) ) {
			return array();
		}
		$allowed = self::allowed_pairs_by_variant( $config, $slugs );
		$raw     = RWGO_Event_Store::list_goal_event_rows( $key );
		$acc     = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$variant = sanitize_key( (string) ( $row['variant_id'] ?? '' ) );
			if ( '' === $variant || ! isset( $allowed[ $variant ] ) ) {
				continue;
			}
			$goal_id    = sanitize_key( (string) ( $row['goal_id'] ?? '' ) );
			$handler_id = sanitize_key( (string) ( $row['handler_id'] ?? '' ) );
			$pair_key   = $goal_id . '|' . $handler_id;
			if ( '' === $goal_id || '' === $handler_id || empty( $allowed[ $variant ][ $pair_key ] ) ) {
				continue;
			}
			$meta        = isset( $row['meta'] ) && is_array( $row['meta'] ) ? $row['meta'] : array();
			$fired_label = isset( $meta['client_goal_label'] ) ? trim( (string) $meta['client_goal_label'] ) : '';
			if ( '' === $fired_label && isset( $meta['goal_label'] ) ) {
				$fired_label = trim( (string) $meta['goal_label'] );
			}
			if ( '' === $fired_label ) {
				$fired_label = RWGO_Experiment_Measurements::label_for_pair( $config, $goal_id, $handler_id );
			}
			$fingerprint = isset( $meta['element_fingerprint'] ) ? trim( (string) $meta['element_fingerprint'] ) : '';
			$goal_type   = self::normalize_goal_type_label( isset( $meta['goal_type'] ) ? (string) $meta['goal_type'] : '' );
			$bucket_key  = strtolower( $fired_label ) . '|' . strtolower( $goal_type ) . '|' . strtolower( $fingerprint );
			if ( ! isset( $acc[ $bucket_key ] ) ) {
				$acc[ $bucket_key ] = array(
					'bucket_key'  => $bucket_key,
					'label'       => $fired_label,
					'goal_type'   => $goal_type,
					'fingerprint' => $fingerprint,
					'counts'      => array_fill_keys( $slugs, 0 ),
				);
			}
			$acc[ $bucket_key ]['counts'][ $variant ]++;
		}
		$vals = array_values( $acc );
		usort(
			$vals,
			static function ( $a, $b ) use ( $slugs ) {
				$total_a = 0;
				$total_b = 0;
				foreach ( $slugs as $slug ) {
					$total_a += isset( $a['counts'][ $slug ] ) ? (int) $a['counts'][ $slug ] : 0;
					$total_b += isset( $b['counts'][ $slug ] ) ? (int) $b['counts'][ $slug ] : 0;
				}
				if ( $total_a !== $total_b ) {
					return $total_b <=> $total_a;
				}
				$la = isset( $a['label'] ) ? (string) $a['label'] : '';
				$lb = isset( $b['label'] ) ? (string) $b['label'] : '';
				return strcasecmp( $la, $lb );
			}
		);
		return $vals;
	}

	/**
	 * Top contributing goal label for a variant (by raw count in breakdown).
	 *
	 * @param list<array{label: string, counts: array<string, int>}> $rows Rows from goal_breakdown_rows.
	 * @param string                                                   $variant_slug Variant.
	 * @return string
	 */
	public static function top_contributing_goal_label( array $rows, $variant_slug ) {
		$variant_slug = sanitize_key( (string) $variant_slug );
		$best         = -1;
		$label        = '';
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['counts'] ) || ! is_array( $row['counts'] ) ) {
				continue;
			}
			$n = isset( $row['counts'][ $variant_slug ] ) ? (int) $row['counts'][ $variant_slug ] : 0;
			if ( $n > $best ) {
				$best  = $n;
				$label = isset( $row['label'] ) ? (string) $row['label'] : '';
			}
		}
		return ( $best > 0 && '' !== $label ) ? $label : '';
	}

	/**
	 * Top stored fired touchpoint for a specific variant, or overall if no variant is provided.
	 *
	 * @param list<array{label: string, goal_type: string, fingerprint: string, counts: array<string, int>}> $rows Touchpoint rows.
	 * @param string                                                                                            $variant_slug Optional variant id.
	 * @return array{label: string, goal_type: string, fingerprint: string, count: int}|null
	 */
	public static function top_fired_touchpoint( array $rows, $variant_slug = '' ) {
		$variant_slug = sanitize_key( (string) $variant_slug );
		$best         = null;
		$best_count   = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['counts'] ) || ! is_array( $row['counts'] ) ) {
				continue;
			}
			$count = 0;
			if ( '' !== $variant_slug ) {
				$count = isset( $row['counts'][ $variant_slug ] ) ? (int) $row['counts'][ $variant_slug ] : 0;
			} else {
				foreach ( $row['counts'] as $n ) {
					$count += (int) $n;
				}
			}
			if ( $count <= $best_count ) {
				continue;
			}
			$best_count = $count;
			$best       = array(
				'label'       => isset( $row['label'] ) ? (string) $row['label'] : '',
				'goal_type'   => isset( $row['goal_type'] ) ? (string) $row['goal_type'] : '',
				'fingerprint' => isset( $row['fingerprint'] ) ? (string) $row['fingerprint'] : '',
				'count'       => $count,
			);
		}
		return $best_count > 0 ? $best : null;
	}

	/**
	 * Full analysis for Reports and Tests list.
	 *
	 * @param string               $experiment_key Experiment key.
	 * @param array<string, mixed> $config         Experiment config.
	 * @param array<string, mixed> $exp_dist       Snapshot experiment_variant_counts[ key ].
	 * @return array<string, mixed>
	 */
	public static function analyze( $experiment_key, array $config, array $exp_dist ) {
		$key = sanitize_text_field( (string) $experiment_key );

		$variants_cfg = isset( $config['variants'] ) && is_array( $config['variants'] ) ? $config['variants'] : array();

		$assignment_only = RWGO_Goal_Service::is_assignment_only( $config );
		$primary_gid     = RWGO_Goal_Service::get_primary_goal_id( $config );
		$primary_label   = RWGO_Goal_Service::get_primary_goal_label( $config );

		$has_pairs = class_exists( 'RWGO_Experiment_Measurements', false )
			? self::config_has_measurement_pairs( $config )
			: false;

		$assignments = isset( $exp_dist[ $key ] ) && is_array( $exp_dist[ $key ] ) ? $exp_dist[ $key ] : array();

		$rows   = array();
		$slugs  = array();
		foreach ( $variants_cfg as $row ) {
			if ( ! is_array( $row ) || empty( $row['variant_id'] ) ) {
				continue;
			}
			$slug           = sanitize_key( (string) $row['variant_id'] );
			$slugs[]        = $slug;
			$assign_n       = isset( $assignments[ $slug ] ) ? (int) $assignments[ $slug ] : 0;
			$rows[ $slug ] = array(
				'variant_id'   => $slug,
				'assignments'  => $assign_n,
				'completions'  => 0,
				'rate'         => 0.0,
			);
		}
		foreach ( $assignments as $vk => $cnt ) {
			$vk = sanitize_key( (string) $vk );
			if ( ! isset( $rows[ $vk ] ) ) {
				$rows[ $vk ] = array(
					'variant_id'  => $vk,
					'assignments' => (int) $cnt,
					'completions' => 0,
					'rate'        => 0.0,
				);
				$slugs[]     = $vk;
			}
		}
		$slugs = array_values( array_unique( $slugs ) );

		$conversion_mode = ! $assignment_only && $has_pairs && class_exists( 'RWGO_Event_Store', false );

		if ( $conversion_mode ) {
			$by_variant = RWGO_Event_Store::count_total_conversions_by_variant( $key, $config, array_keys( $rows ) );
			foreach ( $rows as $slug => &$r ) {
				$c                = isset( $by_variant[ $slug ] ) ? (int) $by_variant[ $slug ] : 0;
				$r['completions'] = $c;
				$den              = max( 1, (int) $r['assignments'] );
				$r['rate']        = $c / $den;
			}
			unset( $r );
		}

		// Prefer exposure denominators for rates when available (before leader pick).
		$denom_source = 'assignments';
		$policy_settings = class_exists( 'RWGO_Winner_Policy', false ) ? RWGO_Winner_Policy::settings( $config ) : array( 'use_exposures' => true );
		if ( $conversion_mode && ! empty( $policy_settings['use_exposures'] ) && class_exists( 'RWGO_Event_Store', false ) && '' !== $key ) {
			$exp_counts = RWGO_Event_Store::count_exposures_by_variant( $key );
			$e_c        = isset( $exp_counts['control'] ) ? (int) $exp_counts['control'] : 0;
			$e_b        = isset( $exp_counts['var_b'] ) ? (int) $exp_counts['var_b'] : 0;
			if ( $e_c > 0 && $e_b > 0 ) {
				$denom_source = 'exposures';
				foreach ( array( 'control', 'var_b' ) as $slug ) {
					if ( ! isset( $rows[ $slug ] ) ) {
						continue;
					}
					$n = isset( $exp_counts[ $slug ] ) ? (int) $exp_counts[ $slug ] : 0;
					$rows[ $slug ]['exposures'] = $n;
					$rows[ $slug ]['rate']      = $n > 0 ? ( (int) $rows[ $slug ]['completions'] / $n ) : 0.0;
				}
			}
		}

		$lead_slug = null;
		$best_rate = -1.0;
		if ( $conversion_mode ) {
			foreach ( $rows as $slug => $r ) {
				if ( (float) $r['rate'] > $best_rate ) {
					$best_rate = (float) $r['rate'];
					$lead_slug = $slug;
				}
			}
		}

		// With zero completions every variant has rate 0; do not pick an arbitrary "leader".
		$total_completions = 0;
		foreach ( $rows as $r ) {
			$total_completions += isset( $r['completions'] ) ? (int) $r['completions'] : 0;
		}
		if ( $conversion_mode && 0 === $total_completions ) {
			$lead_slug = null;
		}

		$breakdown = $conversion_mode && '' !== $key
			? self::goal_breakdown_rows( $key, $config, $slugs )
			: array();
		$fired_touchpoints = $conversion_mode && '' !== $key
			? self::fired_touchpoint_rows( $key, $config, $slugs )
			: array();

		$insight_line = '';
		if ( $conversion_mode && $lead_slug && ! empty( $breakdown ) ) {
			$lead_total = isset( $rows[ $lead_slug ]['completions'] ) ? (int) $rows[ $lead_slug ]['completions'] : 0;
			$top_goal   = $lead_total > 0 ? self::top_contributing_goal_label( $breakdown, $lead_slug ) : '';
			if ( '' !== $top_goal ) {
				/* translators: 1: leading variant label, 2: goal/CTA label */
				$insight_line = sprintf(
					__( '%1$s is leading, driven primarily by %2$s.', 'reactwoo-geo-optimise' ),
					self::variant_label_from_config( $config, $lead_slug ),
					$top_goal
				);
			}
		}
		$top_touchpoint = ! empty( $fired_touchpoints )
			? self::top_fired_touchpoint( $fired_touchpoints, $lead_slug ? $lead_slug : '' )
			: null;

		$out = array(
			'assignment_only'      => $assignment_only,
			'conversion_mode'      => $conversion_mode,
			'has_measurement_pairs'=> $has_pairs,
			'primary_goal_id'      => $primary_gid,
			'primary_goal_label'   => $primary_label,
			'metric_label'         => __( 'Total conversions', 'reactwoo-geo-optimise' ),
			'metric_description'   => 'exposures' === $denom_source
				? __( 'Sum of mapped success goals per variant ÷ exposures.', 'reactwoo-geo-optimise' )
				: __( 'Sum of mapped success goals (goal + handler) per variant ÷ assignments.', 'reactwoo-geo-optimise' ),
			'denominator_source'   => $denom_source,
			'variants'             => $rows,
			'leading_variant'      => $lead_slug,
			'best_rate'            => $best_rate >= 0 ? $best_rate : null,
			'goal_breakdown'       => $breakdown,
			'fired_touchpoints'    => $fired_touchpoints,
			'top_fired_touchpoint' => $top_touchpoint,
			'insight_line'         => $insight_line,
		);

		if ( class_exists( 'RWGO_Winner_Policy', false ) ) {
			$out['winner_policy'] = RWGO_Winner_Policy::evaluate( $out, $config, $key );
			$exp_id = self::find_experiment_post_id_by_key( $key );
			if ( $exp_id > 0 ) {
				/**
				 * After winner policy is evaluated for an experiment.
				 *
				 * @param int                  $exp_id Experiment CPT id.
				 * @param array<string, mixed> $out    Analysis.
				 * @param array<string, mixed> $config Config.
				 */
				do_action( 'rwgo_winner_policy_evaluated', $exp_id, $out, $config );
			}
		}

		return $out;
	}

	/**
	 * @param string $experiment_key Key.
	 * @return int
	 */
	private static function find_experiment_post_id_by_key( $experiment_key ) {
		$key = sanitize_text_field( (string) $experiment_key );
		if ( '' === $key || ! class_exists( 'RWGO_Experiment_Repository', false ) ) {
			return 0;
		}
		foreach ( RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 500 ) ) as $post ) {
			if ( ! ( $post instanceof \WP_Post ) ) {
				continue;
			}
			$cfg = RWGO_Experiment_Repository::get_config( (int) $post->ID );
			if ( is_array( $cfg ) && isset( $cfg['experiment_key'] ) && (string) $cfg['experiment_key'] === $key ) {
				return (int) $post->ID;
			}
		}
		return 0;
	}

	/**
	 * @param array<string, mixed> $config Experiment config.
	 * @return bool
	 */
	private static function config_has_measurement_pairs( array $config ) {
		if ( class_exists( 'RWGO_Goal_Mapping', false ) && RWGO_Goal_Mapping::is_active( $config ) ) {
			$m = isset( $config['defined_goal_mapping'] ) && is_array( $config['defined_goal_mapping'] ) ? $config['defined_goal_mapping'] : array();
			$targets = isset( $m['targets'] ) && is_array( $m['targets'] ) ? $m['targets'] : array();
			foreach ( $targets as $tlist ) {
				if ( ! is_array( $tlist ) ) {
					continue;
				}
				foreach ( $tlist as $p ) {
					if ( is_array( $p ) && ! empty( $p['handler_id'] ) ) {
						return true;
					}
				}
			}
			return false;
		}
		$pairs = RWGO_Experiment_Measurements::stored_pairs_all_goals( $config );
		return ! empty( $pairs );
	}

	/**
	 * @param array<string, mixed> $config Experiment config.
	 * @param list<string>         $variant_slugs Variants to show.
	 * @return array<string, array<string, bool>>
	 */
	private static function allowed_pairs_by_variant( array $config, array $variant_slugs ) {
		$allowed = array();
		foreach ( $variant_slugs as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' === $slug ) {
				continue;
			}
			$pairs = RWGO_Experiment_Measurements::stored_pairs_for_variant( $config, $slug );
			if ( empty( $pairs ) ) {
				$pairs = RWGO_Experiment_Measurements::stored_pairs_all_goals( $config );
			}
			$allowed[ $slug ] = array();
			foreach ( $pairs as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				$g = sanitize_key( (string) ( $p['goal_id'] ?? '' ) );
				$h = sanitize_key( (string) ( $p['handler_id'] ?? '' ) );
				if ( '' === $g || '' === $h ) {
					continue;
				}
				$allowed[ $slug ][ $g . '|' . $h ] = true;
			}
		}
		return $allowed;
	}

	/**
	 * @param string $goal_type Stored goal type.
	 * @return string
	 */
	private static function normalize_goal_type_label( $goal_type ) {
		$goal_type = sanitize_key( (string) $goal_type );
		if ( '' === $goal_type ) {
			return __( 'Unknown', 'reactwoo-geo-optimise' );
		}
		if ( 'cta_click' === $goal_type ) {
			return __( 'CTA click', 'reactwoo-geo-optimise' );
		}
		if ( 'form_submit' === $goal_type ) {
			return __( 'Form submit', 'reactwoo-geo-optimise' );
		}
		if ( 'click' === $goal_type ) {
			return __( 'Click', 'reactwoo-geo-optimise' );
		}
		return ucwords( str_replace( '_', ' ', $goal_type ) );
	}

	/**
	 * @param array<string, mixed> $config Experiment config.
	 * @param string               $variant_slug Variant id.
	 * @return string
	 */
	private static function variant_label_from_config( array $config, $variant_slug ) {
		$slug = sanitize_key( (string) $variant_slug );
		if ( isset( $config['variants'] ) && is_array( $config['variants'] ) ) {
			foreach ( $config['variants'] as $row ) {
				if ( ! is_array( $row ) || empty( $row['variant_id'] ) ) {
					continue;
				}
				if ( sanitize_key( (string) $row['variant_id'] ) === $slug ) {
					return isset( $row['variant_label'] ) ? (string) $row['variant_label'] : $slug;
				}
			}
		}
		if ( 'control' === $slug ) {
			return __( 'Control', 'reactwoo-geo-optimise' );
		}
		if ( 'var_b' === $slug ) {
			return __( 'Variant B', 'reactwoo-geo-optimise' );
		}
		return $slug;
	}
}
