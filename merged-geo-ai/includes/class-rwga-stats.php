<?php
/**
 * Lightweight snapshot for integrations and exports (Geo AI).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta snapshot — no secrets.
 */
class RWGA_Stats {

	/**
	 * @return array<string, scalar|string>
	 */
	public static function get_snapshot() {
		$data = array(
			'plugin_version'  => defined( 'RWGA_VERSION' ) ? RWGA_VERSION : '',
			'site_url'        => home_url( '/' ),
			'exported_at_gmt'   => gmdate( 'c' ),
		);

		/**
		 * Filter Geo AI stats snapshot (CSV-friendly keys).
		 *
		 * @param array<string, scalar|string> $data Data.
		 */
		return apply_filters( 'rwga_stats_snapshot', $data );
	}
}
