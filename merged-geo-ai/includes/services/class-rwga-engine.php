<?php
/**
 * Workflow execution / generation mode.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves {@see RWGA_Settings} workflow_engine for workflows and the generation router.
 *
 * Public modes: automatic | wordpress_ai | managed | local
 * Legacy stored values: remote → managed, remote_fallback → managed-then-local (no WordPress AI).
 */
class RWGA_Engine {

	/**
	 * All accepted stored values (public + legacy).
	 *
	 * @return string[]
	 */
	public static function allowed_modes() {
		return array(
			'automatic',
			'wordpress_ai',
			'managed',
			'local',
			'remote',
			'remote_fallback',
		);
	}

	/**
	 * Modes shown in Advanced settings.
	 *
	 * @return string[]
	 */
	public static function public_modes() {
		return array( 'automatic', 'wordpress_ai', 'managed', 'local' );
	}

	/**
	 * Stored workflow_engine value (including legacy).
	 *
	 * @return string
	 */
	public static function get_mode() {
		$s = RWGA_Settings::get_settings();
		$m = isset( $s['workflow_engine'] ) ? sanitize_key( (string) $s['workflow_engine'] ) : 'automatic';
		if ( ! in_array( $m, self::allowed_modes(), true ) ) {
			$m = 'automatic';
		}
		/**
		 * Override resolved workflow engine mode (diagnostics / tests).
		 *
		 * @param string $mode Stored or default mode.
		 */
		$filtered = apply_filters( 'rwga_workflow_engine_mode', $m );
		$filtered = sanitize_key( (string) $filtered );
		return in_array( $filtered, self::allowed_modes(), true ) ? $filtered : $m;
	}

	/**
	 * Public-facing mode label (maps remote → managed).
	 *
	 * @return string
	 */
	public static function get_public_mode() {
		$m = self::get_mode();
		if ( 'remote' === $m ) {
			return 'managed';
		}
		if ( 'remote_fallback' === $m ) {
			return 'remote_fallback';
		}
		return in_array( $m, self::public_modes(), true ) ? $m : 'automatic';
	}

	/**
	 * Whether non-router workflows should POST to the ReactWoo managed API.
	 *
	 * @return bool
	 */
	public static function should_try_remote() {
		$m = self::get_mode();
		return in_array( $m, array( 'remote', 'remote_fallback', 'managed', 'automatic' ), true );
	}

	/**
	 * Whether the mode uses the generation router (migrated workflows).
	 *
	 * @return bool
	 */
	public static function uses_generation_router() {
		return true;
	}
}
