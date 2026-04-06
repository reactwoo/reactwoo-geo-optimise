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
