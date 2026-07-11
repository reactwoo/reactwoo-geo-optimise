<?php
/**
 * Payload strategy enforcement — strip forbidden data before remote AI calls.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central gate for what may leave WordPress toward api.reactwoo.com.
 */
class RWGA_Payload_Guard {

	/**
	 * Keys never sent to remote models (top-level or nested).
	 *
	 * @var array<int, string>
	 */
	private static $forbidden_keys = array(
		'_elementor_data',
		'elementor_data',
		'post_content',
		'raw_html',
		'html',
		'controls',
		'ai_page_context',
		'license_key',
		'api_key',
		'visitor_ip',
		'email',
	);

	/**
	 * Sanitize a workflow payload recursively.
	 *
	 * @param array<string, mixed> $payload Raw payload.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $payload ) {
		$clean = self::sanitize_value( $payload );

		/**
		 * @param array<string, mixed> $clean   Sanitized payload.
		 * @param array<string, mixed> $payload Original payload.
		 */
		$clean = apply_filters( 'rwga_payload_guard_sanitize', is_array( $clean ) ? $clean : array(), $payload );
		return is_array( $clean ) ? $clean : array();
	}

	/**
	 * List top-level keys removed or replaced during sanitization.
	 *
	 * @param array<string, mixed> $before Original payload.
	 * @param array<string, mixed> $after  Sanitized payload.
	 * @return array<int, string>
	 */
	public static function audit_exclusions( array $before, array $after ) {
		$removed = array();
		foreach ( array_keys( $before ) as $key ) {
			if ( self::is_forbidden_key( (string) $key ) ) {
				$removed[] = (string) $key;
				continue;
			}
			if ( ! array_key_exists( $key, $after ) ) {
				$removed[] = (string) $key;
			}
		}
		return array_values( array_unique( $removed ) );
	}

	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function sanitize_value( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		$out = array();
		foreach ( $value as $key => $child ) {
			$key = (string) $key;
			if ( self::is_forbidden_key( $key ) ) {
				continue;
			}
			if ( is_array( $child ) ) {
				$out[ $key ] = self::sanitize_value( $child );
			} else {
				$out[ $key ] = $child;
			}
		}
		return $out;
	}

	/**
	 * @param string $key Key name.
	 * @return bool
	 */
	private static function is_forbidden_key( $key ) {
		$key = strtolower( (string) $key );
		return in_array( $key, self::$forbidden_keys, true );
	}
}
