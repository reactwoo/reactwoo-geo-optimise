<?php
/**
 * Workflow execution mode (local stub vs remote API).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves {@see RWGA_Settings} workflow_engine for workflows.
 */
class RWGA_Engine {

	/**
	 * @return string One of local, remote, remote_fallback.
	 */
	public static function get_mode() {
		$s = RWGA_Settings::get_settings();
		$m = isset( $s['workflow_engine'] ) ? sanitize_key( (string) $s['workflow_engine'] ) : 'local';
		$allowed = array( 'local', 'remote', 'remote_fallback' );
		return in_array( $m, $allowed, true ) ? $m : 'local';
	}

	/**
	 * Whether workflows should POST to the ReactWoo API workflow route.
	 *
	 * @return bool
	 */
	public static function should_try_remote() {
		$m = self::get_mode();
		return 'remote' === $m || 'remote_fallback' === $m;
	}
}
