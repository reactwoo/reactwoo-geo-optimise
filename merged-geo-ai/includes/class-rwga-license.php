<?php
/**
 * License and product availability for Geo AI workflows.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin checks: key on file means workflows may run (API still validates server-side).
 */
class RWGA_License {

	/**
	 * Whether a ReactWoo license key is configured for this plugin.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		if ( ! class_exists( 'RWGA_Settings', false ) ) {
			return false;
		}
		return RWGA_Settings::is_license_configured_for_geo_ai_ui();
	}

	/**
	 * Workflows require a configured license (per product rules).
	 *
	 * @return bool
	 */
	public static function can_run_workflows() {
		return self::is_configured();
	}
}
