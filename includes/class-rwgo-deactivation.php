<?php
/**
 * Clears cron events this plugin registered.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivation cleanup for scheduled hooks.
 */
class RWGO_Deactivation {

	/**
	 * Files that declare the cron hook constants.
	 *
	 * @return string[]
	 */
	public static function hook_files() {
		$root = dirname( __DIR__ );
		return array(
			$root . '/merged-geo-ai/includes/class-rwga-cron.php',
			$root . '/merged-geo-ai/includes/services/class-rwga-weather-catalog-audit.php',
		);
	}

	/**
	 * Hook names passed to wp_schedule_event / wp_schedule_single_event.
	 *
	 * @return string[]
	 */
	public static function cron_hooks() {
		foreach ( self::hook_files() as $file ) {
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
		$hooks = array();
		if ( class_exists( 'RWGA_Cron', false ) ) {
			$hooks[] = RWGA_Cron::HOOK;
		}
		if ( class_exists( 'RWGA_Weather_Catalog_Audit', false ) ) {
			$hooks[] = RWGA_Weather_Catalog_Audit::HOOK;
		}
		return $hooks;
	}

	/**
	 * Drop every scheduled event for each hook this plugin registers.
	 *
	 * @return void
	 */
	public static function clear_scheduled_crons() {
		if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
			return;
		}
		foreach ( self::cron_hooks() as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}
}
