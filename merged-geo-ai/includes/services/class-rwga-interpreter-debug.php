<?php
/**
 * Optional interpreter debug logging (WP_DEBUG_LOG / option / constant).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs structured Geo AI interpreter traces when debug mode is on.
 */
class RWGA_Interpreter_Debug {

	/**
	 * @return bool
	 */
	public static function is_enabled() {
		if ( defined( 'RWGA_DEBUG_INTERPRETER' ) && RWGA_DEBUG_INTERPRETER ) {
			return true;
		}
		return '1' === (string) get_option( 'rwga_debug_interpreter', '' );
	}

	/**
	 * @param string              $message Log message.
	 * @param array<string,mixed> $context Structured context.
	 * @return void
	 */
	public static function log( $message, array $context = array() ) {
		if ( ! self::is_enabled() ) {
			return;
		}
		$payload = wp_json_encode( $context );
		if ( ! is_string( $payload ) ) {
			$payload = '{}';
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[RWGA AI Interpreter] ' . (string) $message . ' ' . $payload );
	}
}
