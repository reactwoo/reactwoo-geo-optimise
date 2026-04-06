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

	const SERVED_OPTION = 'rwgo_experiment_variant_served';

	/**
	 * Increment per-variant **served** impressions (page views where that variant’s experience was rendered).
	 * Sticky assignment is unchanged; this only measures delivery for split validation.
	 *
	 * @param string $experiment_key Sanitized experiment key.
	 * @param string $variant_slug   Variant slug (e.g. control, var_b).
	 * @return void
	 */
	public static function record_variant_served( $experiment_key, $variant_slug ) {
		$experiment_key = sanitize_key( (string) $experiment_key );
		$variant_slug   = is_string( $variant_slug ) ? $variant_slug : (string) $variant_slug;
		if ( '' === $experiment_key || '' === $variant_slug ) {
			return;
		}
		$opt = get_option( self::SERVED_OPTION, array() );
		if ( ! is_array( $opt ) ) {
			$opt = array();
		}
		if ( ! isset( $opt[ $experiment_key ] ) || ! is_array( $opt[ $experiment_key ] ) ) {
			$opt[ $experiment_key ] = array();
		}
		$cur = isset( $opt[ $experiment_key ][ $variant_slug ] ) ? (int) $opt[ $experiment_key ][ $variant_slug ] : 0;
		$opt[ $experiment_key ][ $variant_slug ] = $cur + 1;
		update_option( self::SERVED_OPTION, $opt, false );
	}

	/**
	 * @return array<string, array<string, int>>
	 */
	public static function get_served_distribution() {
		$opt = get_option( self::SERVED_OPTION, array() );
		return is_array( $opt ) ? $opt : array();
	}

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
		$served = get_option( self::SERVED_OPTION, array() );
		if ( ! is_array( $served ) ) {
			$served = array();
		}

		$data = array(
			'plugin_version'               => defined( 'RWGO_VERSION' ) ? RWGO_VERSION : '',
			'geo_event_count'              => (int) get_option( 'rwgo_geo_event_count', 0 ),
			'route_resolved_count'         => (int) get_option( 'rwgo_route_resolved_count', 0 ),
			'assignment_count'             => (int) get_option( 'rwgo_assignment_count', 0 ),
			'experiment_variant_counts'      => $dist,
			'experiment_variant_served'      => $served,
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
			if ( ( 'experiment_variant_counts' === $key || 'experiment_variant_served' === $key ) && is_array( $value ) ) {
				foreach ( $value as $exp => $vars ) {
					if ( ! is_string( $exp ) || ! is_array( $vars ) ) {
						continue;
					}
					$prefix = 'experiment_variant_counts' === $key ? 'experiment_variant' : 'experiment_variant_served';
					foreach ( $vars as $vk => $cnt ) {
						$vk = is_string( $vk ) || is_numeric( $vk ) ? (string) $vk : '';
						if ( '' === $vk ) {
							continue;
						}
						$subkey          = $prefix . '.' . $exp . '.' . $vk;
						$rows[ $subkey ] = (string) (int) $cnt;
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
