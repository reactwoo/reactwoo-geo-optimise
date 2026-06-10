<?php
/**
 * Suite feature gates for Geo Optimise (license + plugin presence).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central capability checks for Create Test wizard options.
 */
class RWGO_Suite_Features {

	/**
	 * Whether Variant B can be created with Geo AI copy adaptation.
	 *
	 * @return bool
	 */
	public static function can_use_ai_adapt_variant() {
		$allowed = class_exists( 'RWGA_Plugin', false )
			&& class_exists( 'RWGA_License', false )
			&& RWGA_License::can_run_workflows();
		return (bool) apply_filters( 'rwgo_can_use_ai_adapt_variant', $allowed );
	}

	/**
	 * Whether Geo AI is installed but not licensed (for upsell copy).
	 *
	 * @return bool
	 */
	public static function geo_ai_installed_without_license() {
		return class_exists( 'RWGA_Plugin', false )
			&& class_exists( 'RWGA_License', false )
			&& ! RWGA_License::can_run_workflows();
	}

	/**
	 * Whether saved portable targeting rules can gate test entry.
	 *
	 * @return bool
	 */
	public static function can_use_saved_rule_targeting() {
		$allowed = ( function_exists( 'rwgc_is_ready' ) && rwgc_is_ready() )
			&& class_exists( 'RWGC_Visibility_Rule_Repository', false );
		return (bool) apply_filters( 'rwgo_can_use_saved_rule_targeting', $allowed );
	}

	/**
	 * Whether the create-rule handoff is shown in the wizard.
	 *
	 * @return bool
	 */
	public static function can_use_create_rule_targeting() {
		$allowed = self::can_use_saved_rule_targeting();
		return (bool) apply_filters( 'rwgo_can_use_create_rule_targeting', $allowed );
	}

	/**
	 * Whether advanced portable conditions (device, campaign, weather, etc.) are licensed.
	 *
	 * @return bool
	 */
	public static function has_advanced_targeting_license() {
		return function_exists( 'rwgc_advanced_targeting_enabled' ) && rwgc_advanced_targeting_enabled();
	}

	/**
	 * @return array<int, array{id:int,title:string,summary:string}>
	 */
	public static function get_library_rule_options() {
		if ( ! self::can_use_saved_rule_targeting() || ! class_exists( 'RWGC_Experience_Workflow', false ) ) {
			return array();
		}
		return RWGC_Experience_Workflow::get_library_rule_options();
	}

	/**
	 * Rule editor URL that returns to Create Test after save.
	 *
	 * @return string
	 */
	public static function get_create_rule_url() {
		return add_query_arg(
			array(
				'page'        => 'rwgc-visibility-rules',
				'rwgc_edit'   => 'new',
				'rwgc_return' => rawurlencode( admin_url( 'admin.php?page=rwgo-create-test' ) ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Geo AI license settings URL.
	 *
	 * @return string
	 */
	public static function get_geo_ai_license_url() {
		return admin_url( 'admin.php?page=rwga-license' );
	}

	/**
	 * GeoCore Pro setup URL (advanced targeting).
	 *
	 * @return string
	 */
	public static function get_geocore_pro_url() {
		return admin_url( 'admin.php?page=rwgcp-geocore-pro' );
	}
}
