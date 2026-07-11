<?php
/**
 * Activation and schema upgrades.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Install Geo AI tables and capabilities.
 */
class RWGA_Install {

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once RWGA_PATH . 'includes/class-rwga-settings.php';
		RWGA_Settings::maybe_migrate_from_geo_core();

		require_once RWGA_PATH . 'includes/class-rwga-capabilities.php';
		RWGA_Capabilities::install();

		require_once RWGA_PATH . 'includes/db/class-rwga-db.php';
		RWGA_DB::install();

		if ( function_exists( 'rwga_get_site_uuid' ) ) {
			rwga_get_site_uuid();
		} else {
			require_once RWGA_PATH . 'includes/helpers/rwga-site.php';
			rwga_get_site_uuid();
		}

		require_once RWGA_PATH . 'includes/class-rwga-cron.php';
		RWGA_Cron::activate();
	}

	/**
	 * Run pending DB upgrades on load.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$ver = get_option( RWGA_DB::VERSION_OPTION, '' );
		if ( RWGA_DB::SCHEMA_VERSION !== $ver || ! RWGA_DB::tables_ready() ) {
			require_once RWGA_PATH . 'includes/db/class-rwga-db.php';
			RWGA_DB::install();
		}
	}
}
