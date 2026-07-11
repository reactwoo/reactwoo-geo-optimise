<?php
/**
 * Periodic catalog audit — suggest weather facets for untagged products.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Weekly WP-Cron batch scan with admin report for Geo Commerce merchandising.
 */
class RWGA_Weather_Catalog_Audit {

	const HOOK   = 'rwga_weather_catalog_audit_tick';
	const OPTION = 'rwga_weather_catalog_audit_report';

	const BATCH_SIZE = 40;

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! class_exists( 'RWGCM_Weather_Tagging', false ) ) {
			return;
		}
		add_action( 'rwga_loaded', array( __CLASS__, 'maybe_schedule' ), 45 );
		add_action( self::HOOK, array( __CLASS__, 'run_batch' ) );
		add_action( 'admin_post_rwga_run_weather_catalog_audit', array( __CLASS__, 'handle_manual_run' ) );
	}

	/**
	 * @return void
	 */
	public static function maybe_schedule() {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::HOOK );
	}

	/**
	 * @return void
	 */
	public static function deactivate() {
		$ts = wp_next_scheduled( self::HOOK );
		while ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
			$ts = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * @return void
	 */
	public static function handle_manual_run() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'reactwoo-geo-ai' ) );
		}
		check_admin_referer( 'rwga_weather_catalog_audit' );
		self::reset_report();
		self::run_batch();
		$redirect = wp_get_referer();
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url( 'admin.php?page=rwgcm-merchandising' );
		}
		wp_safe_redirect( add_query_arg( 'rwga_audit_started', '1', $redirect ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function reset_report() {
		update_option(
			self::OPTION,
			array(
				'updated_at'  => time(),
				'scanned'     => 0,
				'suggestions' => array(),
				'offset'      => 0,
				'complete'    => false,
			),
			false
		);
	}

	/**
	 * @return void
	 */
	public static function run_batch() {
		if ( ! class_exists( 'RWGA_Weather_Facet_Suggester', false ) || ! class_exists( 'RWGCM_Weather_Tagging', false ) ) {
			return;
		}

		$report = self::get_report();
		$offset = isset( $report['offset'] ) ? (int) $report['offset'] : 0;

		$query = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => self::BATCH_SIZE,
				'offset'         => $offset,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'     => RWGCM_Weather_Affinity::META_KEY,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$ids = is_array( $query->posts ) ? array_map( 'intval', $query->posts ) : array();
		if ( empty( $ids ) ) {
			$report['complete']   = true;
			$report['updated_at'] = time();
			update_option( self::OPTION, $report, false );
			return;
		}

		$suggestions = isset( $report['suggestions'] ) && is_array( $report['suggestions'] ) ? $report['suggestions'] : array();
		foreach ( $ids as $pid ) {
			$result = RWGA_Weather_Facet_Suggester::suggest_for_product( $pid );
			$facets = is_array( $result ) && ! empty( $result['facets'] ) && is_array( $result['facets'] ) ? $result['facets'] : array();
			if ( empty( $facets ) ) {
				continue;
			}
			$suggestions[] = array(
				'product_id' => $pid,
				'title'      => get_the_title( $pid ),
				'facets'     => $facets,
				'source'     => isset( $result['source'] ) ? sanitize_key( (string) $result['source'] ) : 'keywords',
				'edit_url'   => get_edit_post_link( $pid, 'raw' ),
			);
		}

		$report['suggestions'] = array_slice( $suggestions, -200 );
		$report['scanned']     = (int) ( $report['scanned'] ?? 0 ) + count( $ids );
		$report['offset']      = $offset + count( $ids );
		$report['updated_at']  = time();
		$report['complete']    = count( $ids ) < self::BATCH_SIZE;
		update_option( self::OPTION, $report, false );

		if ( ! $report['complete'] ) {
			wp_schedule_single_event( time() + 60, self::HOOK );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_report() {
		$report = get_option( self::OPTION, array() );
		return is_array( $report ) ? $report : array();
	}

	/**
	 * @return bool
	 */
	public static function has_actionable_report() {
		$report = self::get_report();
		return ! empty( $report['suggestions'] ) && is_array( $report['suggestions'] );
	}
}
