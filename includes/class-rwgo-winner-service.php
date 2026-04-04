<?php
/**
 * Winner = best primary goal conversion rate (completions / assignments per variant).
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
		// Include stats keys not in config (legacy).
		foreach ( $assignments as $vk => $cnt ) {
			$vk = sanitize_key( (string) $vk );
			if ( ! isset( $rows[ $vk ] ) ) {
				$rows[ $vk ] = array(
					'variant_id'  => $vk,
					'assignments' => (int) $cnt,
					'completions' => 0,
					'rate'        => 0.0,
				);
			}
		}

		if ( ! $assignment_only && '' !== $primary_gid && class_exists( 'RWGO_Event_Store', false ) ) {
			$by_variant = RWGO_Event_Store::count_goal_completions_by_variant( $key, $primary_gid );
			foreach ( $rows as $slug => &$r ) {
				$c = isset( $by_variant[ $slug ] ) ? (int) $by_variant[ $slug ] : 0;
				$r['completions'] = $c;
				$den                = max( 1, (int) $r['assignments'] );
				$r['rate']          = $c / $den;
			}
			unset( $r );
		}

		$lead_slug = null;
		$best_rate = -1.0;
		if ( ! $assignment_only && '' !== $primary_gid ) {
			foreach ( $rows as $slug => $r ) {
				if ( (float) $r['rate'] > $best_rate ) {
					$best_rate = (float) $r['rate'];
					$lead_slug = $slug;
				}
			}
		}

		return array(
			'assignment_only'   => $assignment_only,
			'primary_goal_id'   => $primary_gid,
			'primary_goal_label'=> $primary_label,
			'variants'          => $rows,
			'leading_variant'   => $lead_slug,
			'best_rate'         => $best_rate >= 0 ? $best_rate : null,
		);
	}
}
