<?php
/**
 * Read-only connection summary for Geo AI admin (credentials live in Geo AI → License).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connection summary for Geo AI admin (no secrets exposed).
 */
class RWGA_Connection {

	/**
	 * @return array{core_ready: bool, api_base: string, license_configured: bool, rest_enabled: bool}
	 */
	public static function get_summary() {
		if ( ! class_exists( 'RWGC_Settings', false ) ) {
			return array(
				'core_ready'           => false,
				'api_base'             => '',
				'license_configured'   => false,
				'rest_enabled'         => false,
			);
		}

		$api_base = 'https://api.reactwoo.com';
		if ( class_exists( 'RWGA_Platform_Client', false ) ) {
			$api_base = RWGA_Platform_Client::get_api_base();
		}

		$lic_ok = false;
		if ( class_exists( 'RWGA_Settings', false ) ) {
			$lic_ok = RWGA_Settings::is_license_configured_for_geo_ai_ui();
		}

		return array(
			'core_ready'         => true,
			'api_base'           => $api_base,
			'license_configured' => $lic_ok,
			'rest_enabled'       => (bool) RWGC_Settings::get( 'rest_enabled', 1 ),
		);
	}
}
