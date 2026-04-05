<?php
/**
 * Plugin Name: ReactWoo Geo Optimise
 * Description: Experiments, CRO, and analytics on top of ReactWoo Geo Core. Requires Geo Core.
 * Version: 0.3.8
 * Author: ReactWoo
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: reactwoo-geo-optimise
 * Requires Plugins: reactwoo-geocore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RWGO_VERSION' ) ) {
	define( 'RWGO_VERSION', '0.3.8' );
}
if ( ! defined( 'RWGO_FILE' ) ) {
	define( 'RWGO_FILE', __FILE__ );
}
if ( ! defined( 'RWGO_PATH' ) ) {
	define( 'RWGO_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'RWGO_URL' ) ) {
	define( 'RWGO_URL', plugin_dir_url( __FILE__ ) );
}

require_once RWGO_PATH . 'includes/class-rwgo-plugin.php';

/**
 * Activation: goal events table + rewrite rules.
 *
 * @return void
 */
function rwgo_activate() {
	require_once RWGO_PATH . 'includes/class-rwgo-event-store.php';
	require_once RWGO_PATH . 'includes/class-rwgo-db-schema.php';
	RWGO_Event_Store::activate();
	RWGO_DB_Schema::activate();
	flush_rewrite_rules();
}

register_activation_hook( RWGO_FILE, 'rwgo_activate' );

/**
 * Bootstrap after Geo Core.
 *
 * @return void
 */
function rwgo_boot() {
	RWGO_Plugin::instance()->boot();
}

add_action( 'plugins_loaded', 'rwgo_boot', 20 );
