<?php
/**
 * Front-end runtime bootstrap (page swapper, tracking enqueue).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runtime coordinator.
 */
class RWGO_Runtime {

	/**
	 * @return void
	 */
	public static function init() {
		RWGO_Page_Swapper::init();
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracking' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_record_variant_served' ), 99 );
	}

	/**
	 * Count **served** impressions per variant (page actually rendered) for split validation.
	 * Does not change sticky assignment cookies.
	 *
	 * @return void
	 */
	public static function maybe_record_variant_served() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! class_exists( 'RWGO_Experiment_Repository', false ) || ! class_exists( 'RWGO_Stats', false ) || ! class_exists( 'RWGO_Experiment_Service', false ) ) {
			return;
		}
		$pid = self::resolve_frontend_context_post_id();
		if ( $pid <= 0 ) {
			return;
		}
		foreach ( RWGO_Experiment_Repository::get_active_touching_page( $pid ) as $row ) {
			if ( ! isset( $row['config'] ) || ! is_array( $row['config'] ) ) {
				continue;
			}
			$cfg = $row['config'];
			if ( empty( $cfg['experiment_key'] ) ) {
				continue;
			}
			$variant = RWGO_Experiment_Service::resolve_variant_for_context( $cfg, $pid );
			if ( '' === $variant ) {
				continue;
			}
			RWGO_Stats::record_variant_served( (string) $cfg['experiment_key'], $variant );
		}
	}

	/**
	 * Post ID for the page being viewed (singular, WooCommerce shop, or filtered).
	 *
	 * @return int
	 */
	public static function resolve_frontend_context_post_id() {
		if ( is_singular() ) {
			$pid = (int) get_queried_object_id();
			if ( $pid > 0 ) {
				return $pid;
			}
		}
		if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) ) {
			$sid = (int) wc_get_page_id( 'shop' );
			if ( $sid > 0 ) {
				return $sid;
			}
		}
		/**
		 * @param int $post_id Default 0.
		 */
		return (int) apply_filters( 'rwgo_tracking_context_post_id', 0 );
	}

	/**
	 * Localize experiment + goal config for rwgo-tracking.js on singular pages.
	 *
	 * @return void
	 */
	public static function enqueue_tracking() {
		if ( is_admin() ) {
			return;
		}
		$pid = self::resolve_frontend_context_post_id();
		if ( $pid <= 0 ) {
			return;
		}
		$config = RWGO_Goal_Registry::build_frontend_config( $pid );
		if ( empty( $config['experiments'] ) ) {
			return;
		}

		$config['restUrl']      = rest_url( 'rwgo/v1/goal' );
		$config['restNonceUrl'] = rest_url( 'rwgo/v1/tracking-nonce' );
		$config['nonce']        = wp_create_nonce( RWGO_REST_Tracking::NONCE_ACTION );
		/**
		 * Persist browser goal events via REST into wp_rwgo_events (in addition to dataLayer).
		 *
		 * @param bool $persist Default true.
		 */
		$config['persistClientGoals'] = (bool) apply_filters( 'rwgo_persist_client_goals', true );

		wp_register_script(
			'rwgo-tracking',
			RWGO_URL . 'assets/js/rwgo-tracking.js',
			array(),
			RWGO_VERSION,
			true
		);
		wp_enqueue_script( 'rwgo-tracking' );
		wp_localize_script(
			'rwgo-tracking',
			'rwgoTracking',
			$config
		);
	}
}
