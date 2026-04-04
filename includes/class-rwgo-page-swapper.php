<?php
/**
 * Request-time routing: send visitors on the control URL to variant B when assigned.
 *
 * Uses sticky {@see RWGO_Assignment} and a safe redirect (no theme edits).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page-level experiment runtime (redirect-based v1).
 */
class RWGO_Page_Swapper {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 5 );
	}

	/**
	 * @return void
	 */
	public static function maybe_redirect() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( ! is_singular() ) {
			return;
		}
		$pid = (int) get_queried_object_id();
		if ( $pid <= 0 ) {
			return;
		}

		$items = RWGO_Experiment_Repository::get_active_for_source_page( $pid );
		if ( empty( $items ) ) {
			return;
		}

		/**
		 * Multiple concurrent tests on one control URL: process in stable order; each uses a distinct experiment_key in the cookie map.
		 *
		 * @param array<int, array<string, mixed>> $items Rows from repository.
		 * @param int                              $pid   Current post ID (control).
		 */
		$items = apply_filters( 'rwgo_page_swapper_experiments_for_control', $items, $pid );

		foreach ( $items as $row ) {
			$cfg = isset( $row['config'] ) && is_array( $row['config'] ) ? $row['config'] : array();
			if ( empty( $cfg['experiment_key'] ) ) {
				continue;
			}
			if ( ! RWGO_Targeting::passes( isset( $cfg['targeting'] ) ? $cfg['targeting'] : array() ) ) {
				continue;
			}
			$key     = sanitize_key( (string) $cfg['experiment_key'] );
			$slugs   = RWGO_Experiment_Service::assignment_variant_slugs( $cfg );
			$weights = RWGO_Experiment_Service::assignment_weights( $cfg );
			$pick    = RWGO_Assignment::get_variant( $key, $slugs, $weights );
			if ( 'control' === $pick || '' === $pick ) {
				continue;
			}
			$target_page = RWGO_Experiment_Service::page_id_for_variant( $cfg, $pick );
			if ( $target_page <= 0 || $target_page === $pid ) {
				continue;
			}
			$url = get_permalink( $target_page );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			// Admin / preview: avoid redirect.
			if ( is_user_logged_in() && isset( $_GET['preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return;
			}
			/**
			 * Filters the variant redirect URL for a page-level test.
			 *
			 * @param string               $url    Variant permalink.
			 * @param array<string, mixed> $cfg    Experiment config.
			 * @param string               $pick   Assigned variant id.
			 * @param int                  $pid    Control post ID.
			 */
			$url = apply_filters( 'rwgo_variant_redirect_url', $url, $cfg, $pick, $pid );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			wp_safe_redirect( $url );
			exit;
		}
	}
}
