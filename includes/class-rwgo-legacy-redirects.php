<?php
/**
 * Legacy Geo AI admin URLs → Optimise hub tabs (merged product).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirects standalone Geo AI menu slugs when AI is served from Optimise.
 */
class RWGO_Legacy_Redirects {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ), 1 );
	}

	/**
	 * @return bool
	 */
	public static function should_redirect() {
		if ( ! is_admin() || wp_doing_ajax() ) {
			return false;
		}
		if ( ! class_exists( 'RWGO_AI_Module', false ) ) {
			return false;
		}
		return RWGO_AI_Module::uses_optimise_hub();
	}

	/**
	 * @return array<string, string> Legacy page slug => hub tab id.
	 */
	public static function legacy_page_tab_map() {
		$map = array(
			'rwga-ux-opportunity-review' => 'ai-review',
			'rwga-intelligence-actions'  => 'recommendations',
			'rwga-implementation-drafts' => 'drafts',
			'rwga-analyses'              => 'history',
			'rwga-recommendations'       => 'recommendations',
			'rwga-intelligence-wizard'   => 'settings',
			'rwga-license'               => 'settings',
			'rwga-dashboard'             => 'ai-review',
			'rwga-intelligence-cloud'    => 'settings',
			'rwga-advanced'              => 'settings',
			'rwgc-insights-ai'           => 'ai-review',
		);

		/**
		 * Legacy Geo AI admin page → Optimise hub tab map.
		 *
		 * @param array<string, string> $map Page slug => tab id.
		 */
		return apply_filters( 'rwgo_legacy_geo_ai_page_tab_map', $map );
	}

	/**
	 * @return void
	 */
	public static function maybe_redirect() {
		if ( ! self::should_redirect() || ! class_exists( 'RWGO_Optimise_Hub', false ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin screen routing only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( '' === $page ) {
			return;
		}

		$map = self::legacy_page_tab_map();
		if ( ! isset( $map[ $page ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$query = wp_unslash( $_GET );
		unset( $query['page'] );
		$tab = (string) $map[ $page ];
		unset( $query['tab'] );

		$target = RWGO_Optimise_Hub::tab_url( $tab, $query );
		wp_safe_redirect( $target );
		exit;
	}
}
