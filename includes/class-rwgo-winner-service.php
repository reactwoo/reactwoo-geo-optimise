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
		$allowed = array();
		foreach ( $slugs as $slug ) {
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
		$raw = RWGO_Event_Store::count_breakdown_by_variant_goal_handler( $key );
		$acc = array();
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

		$breakdown = $conversion_mode && '' !== $key
			? self::goal_breakdown_rows( $key, $config, $slugs )
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

		return array(
			'assignment_only'     => $assignment_only,
			'conversion_mode'     => $conversion_mode,
			'has_measurement_pairs'=> $has_pairs,
			'primary_goal_id'     => $primary_gid,
			'primary_goal_label'  => $primary_label,
			'metric_label'        => __( 'Total conversions', 'reactwoo-geo-optimise' ),
			'metric_description'  => __( 'Sum of mapped success goals (goal + handler) per variant.', 'reactwoo-geo-optimise' ),
			'variants'            => $rows,
			'leading_variant'     => $lead_slug,
			'best_rate'           => $best_rate >= 0 ? $best_rate : null,
			'goal_breakdown'      => $breakdown,
			'insight_line'        => $insight_line,
		);
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
