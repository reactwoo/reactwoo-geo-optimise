<?php
/**
 * Geo Optimise — wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for ReactWoo Geo Optimise.
 */
class RWGO_Admin {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 30 );
		add_action( 'admin_post_rwgo_reset_counts', array( __CLASS__, 'handle_reset_counts' ) );
		add_action( 'admin_post_rwgo_export_stats', array( __CLASS__, 'handle_export_stats' ) );
		add_filter( 'rwgc_inner_nav_items', array( __CLASS__, 'filter_inner_nav_items' ), 11, 1 );
	}

	/**
	 * Add Geo Optimise to the shared Geo Core inner nav.
	 *
	 * @param array $items Page slug => label.
	 * @return array
	 */
	public static function filter_inner_nav_items( $items ) {
		if ( ! is_array( $items ) ) {
			return $items;
		}
		return array_merge(
			$items,
			array(
				'rwgo-dashboard' => __( 'Geo Optimise', 'reactwoo-geo-optimise' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		add_submenu_page(
			'rwgc-dashboard',
			__( 'Geo Optimise', 'reactwoo-geo-optimise' ),
			__( 'Geo Optimise', 'reactwoo-geo-optimise' ),
			'manage_options',
			'rwgo-dashboard',
			array( __CLASS__, 'render_dashboard' )
		);
	}

	/**
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$snapshot = class_exists( 'RWGO_Stats', false ) ? RWGO_Stats::get_snapshot() : array();
		$geo_events = isset( $snapshot['geo_event_count'] ) ? (int) $snapshot['geo_event_count'] : 0;
		$route_hits = isset( $snapshot['route_resolved_count'] ) ? (int) $snapshot['route_resolved_count'] : 0;
		$assign_n   = isset( $snapshot['assignment_count'] ) ? (int) $snapshot['assignment_count'] : 0;
		$exp_dist   = isset( $snapshot['experiment_variant_counts'] ) && is_array( $snapshot['experiment_variant_counts'] ) ? $snapshot['experiment_variant_counts'] : array();
		$csv_export_count   = isset( $snapshot['csv_export_count'] ) ? (int) $snapshot['csv_export_count'] : 0;
		$last_csv_export_gmt = isset( $snapshot['last_csv_export_gmt'] ) ? (string) $snapshot['last_csv_export_gmt'] : '';
		$assign_per_route    = isset( $snapshot['assignment_per_route_resolved'] ) ? $snapshot['assignment_per_route_resolved'] : '';
		$capabilities_url = function_exists( 'rwgc_get_rest_capabilities_url' ) ? rwgc_get_rest_capabilities_url() : '';
		$rwgc_nav_current = 'rwgo-dashboard';
		include RWGO_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * @return void
	 */
	public static function handle_reset_counts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_reset_counts' );
		delete_option( 'rwgo_geo_event_count' );
		delete_option( 'rwgo_route_resolved_count' );
		delete_option( 'rwgo_assignment_count' );
		delete_option( 'rwgo_experiment_variant_counts' );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgo-dashboard&reset=1' ) );
		exit;
	}

	/**
	 * Download CSV of current snapshot (UTF-8 BOM for Excel).
	 *
	 * @return void
	 */
	public static function handle_export_stats() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_export_stats' );
		if ( ! class_exists( 'RWGO_Stats', false ) ) {
			wp_die( esc_html__( 'Stats unavailable.', 'reactwoo-geo-optimise' ) );
		}
		$prev_exports = (int) get_option( 'rwgo_csv_export_count', 0 );
		update_option( 'rwgo_csv_export_count', $prev_exports + 1, false );
		update_option( 'rwgo_last_csv_export_gmt', gmdate( 'c' ), false );

		$snapshot = RWGO_Stats::get_snapshot();
		if ( ! is_array( $snapshot ) ) {
			$snapshot = array();
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			$host = 'site';
		} else {
			$host = sanitize_file_name( str_replace( ':', '-', $host ) );
		}
		$filename = 'geo-optimise-stats-' . $host . '-' . gmdate( 'Y-m-d' ) . '.csv';
		/**
		 * Filters the CSV export filename (Geo Optimise → Export CSV).
		 *
		 * @param string $filename Default filename (ASCII-safe).
		 * @param array  $snapshot Snapshot passed to flatten (same as dashboard).
		 */
		$filename = sanitize_file_name( basename( (string) apply_filters( 'rwgo_export_csv_filename', $filename, $snapshot ) ) );
		if ( '' === $filename ) {
			$filename = 'geo-optimise-stats-' . $host . '-' . gmdate( 'Y-m-d' ) . '.csv';
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Could not write export.', 'reactwoo-geo-optimise' ) );
		}
		// UTF-8 BOM for Excel.
		fprintf( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'key', 'value' ) );
		$flat = RWGO_Stats::flatten_for_csv( $snapshot );
		foreach ( $flat as $key => $cell ) {
			fputcsv( $out, array( $key, $cell ) );
		}
		fclose( $out );
		exit;
	}
}
