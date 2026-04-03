<?php
/**
 * Diagnostic counters and export snapshot (Geo Optimise).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregated stats for dashboard and CSV export.
 */
class RWGO_Stats {

	/**
	 * Snapshot for admin display and downloads. Extend via `rwgo_stats_snapshot`.
	 *
	 * @return array<string, mixed> Includes `experiment_variant_counts` (nested array) when assignments occurred; `csv_export_count`, `last_csv_export_gmt` after exports; `assignment_per_route_resolved` when route events exist.
	 */
	public static function get_snapshot() {
		$dist = get_option( 'rwgo_experiment_variant_counts', array() );
		if ( ! is_array( $dist ) ) {
			$dist = array();
		}

		$data = array(
			'plugin_version'               => defined( 'RWGO_VERSION' ) ? RWGO_VERSION : '',
			'geo_event_count'              => (int) get_option( 'rwgo_geo_event_count', 0 ),
			'route_resolved_count'         => (int) get_option( 'rwgo_route_resolved_count', 0 ),
			'assignment_count'             => (int) get_option( 'rwgo_assignment_count', 0 ),
			'experiment_variant_counts'      => $dist,
			'csv_export_count'             => (int) get_option( 'rwgo_csv_export_count', 0 ),
			'last_csv_export_gmt'          => (string) get_option( 'rwgo_last_csv_export_gmt', '' ),
			'site_url'                     => home_url( '/' ),
			'exported_at_gmt'              => gmdate( 'c' ),
		);

		$rr = (int) $data['route_resolved_count'];
		$ac = (int) $data['assignment_count'];
		$data['assignment_per_route_resolved'] = $rr > 0 ? round( $ac / $rr, 6 ) : '';

		/**
		 * Filter stats snapshot before dashboard render or CSV export.
		 *
		 * @param array<string, mixed> $data Keys should be string-safe for CSV (non-scalars JSON-encoded on export).
		 */
		return apply_filters( 'rwgo_stats_snapshot', $data );
	}

	/**
	 * Flatten snapshot for CSV: `experiment_variant_counts` becomes `experiment_variant.{slug}.{variant}` keys.
	 *
	 * @param array<string, mixed> $snapshot From {@see get_snapshot()}.
	 * @return array<string, string>
	 */
	public static function flatten_for_csv( array $snapshot ) {
		$rows = array();
		foreach ( $snapshot as $key => $value ) {
			$key = is_string( $key ) ? $key : (string) $key;
			if ( 'experiment_variant_counts' === $key && is_array( $value ) ) {
				foreach ( $value as $exp => $vars ) {
					if ( ! is_string( $exp ) || ! is_array( $vars ) ) {
						continue;
					}
					foreach ( $vars as $vk => $cnt ) {
						$vk = is_string( $vk ) || is_numeric( $vk ) ? (string) $vk : '';
						if ( '' === $vk ) {
							continue;
						}
						$subkey                        = 'experiment_variant.' . $exp . '.' . $vk;
						$rows[ $subkey ]               = (string) (int) $cnt;
					}
				}
				continue;
			}
			if ( is_scalar( $value ) || null === $value ) {
				$rows[ $key ] = null === $value ? '' : (string) $value;
			} else {
				$enc = wp_json_encode( $value );
				$rows[ $key ] = is_string( $enc ) ? $enc : '';
			}
		}
		ksort( $rows, SORT_STRING );
		return $rows;
	}
}
