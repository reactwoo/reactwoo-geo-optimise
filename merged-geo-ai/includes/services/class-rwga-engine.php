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
 * Legacy stored values stay in the option. Without a Decision Cloud entitlement,
 * managed, remote, and remote_fallback behave as automatic (WordPress AI, then local).
 * remote_fallback never selects hosted AI.
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
	 * Mode the generation router should run.
	 *
	 * Saved managed, remote, and remote_fallback values are kept. Without a Decision Cloud
	 * entitlement they behave as automatic. remote_fallback never selects hosted AI.
	 *
	 * @return string
	 */
	public static function get_effective_mode() {
		$stored = self::get_mode();
		if ( in_array( $stored, array( 'managed', 'remote', 'remote_fallback' ), true ) && ! self::hosted_ai_allowed() ) {
			return 'automatic';
		}
		if ( 'remote' === $stored && self::hosted_ai_allowed() ) {
			return 'managed';
		}
		if ( 'remote_fallback' === $stored ) {
			return 'automatic';
		}
		return $stored;
	}

	/**
	 * Active Decision Cloud Growth or Scale entitlement that includes Geo Optimise.
	 *
	 * @return bool
	 */
	public static function hosted_ai_allowed() {
		self::load_decision_cloud();
		if ( class_exists( 'RWGO_Decision_Cloud', false ) ) {
			return RWGO_Decision_Cloud::hosted_ai_allowed();
		}
		return (bool) apply_filters( 'rwgo_decision_cloud_hosted_ai', false );
	}

	/**
	 * @return void
	 */
	private static function load_decision_cloud() {
		if ( class_exists( 'RWGO_Decision_Cloud', false ) ) {
			return;
		}
		$candidates = array();
		if ( defined( 'RWGO_PATH' ) ) {
			$candidates[] = RWGO_PATH . 'includes/class-rwgo-decision-cloud.php';
		}
		$candidates[] = dirname( __DIR__, 3 ) . '/includes/class-rwgo-decision-cloud.php';
		foreach ( $candidates as $file ) {
			if ( is_string( $file ) && is_readable( $file ) ) {
				require_once $file;
				return;
			}
		}
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
		if ( ! self::hosted_ai_allowed() ) {
			return false;
		}
		$m = self::get_effective_mode();
		return in_array( $m, array( 'remote', 'managed' ), true );
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
