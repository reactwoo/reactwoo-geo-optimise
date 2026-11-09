<?php
/**
 * Decision Cloud entitlement for ReactWoo hosted AI.
 *
 * Hosted generation is allowed only when a cached licence token is an active
 * Decision Cloud Growth or Scale plan that lists Geo Optimise. Detection reads
 * the JWT already stored by licence login. It does not call the API.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gates ReactWoo hosted AI. WordPress AI (BYOK) and local generation are unaffected.
 */
class RWGO_Decision_Cloud {

	const PACKAGE_SLUG = 'reactwoo-decision-cloud';

	const NOTICE = 'Available with ReactWoo Decision Cloud';

	/**
	 * Written once. Saved workflow_engine values are not rewritten.
	 */
	const MIGRATION_OPTION = 'rwgo_byok_engine_migration';

	const MIGRATION_VERSION = '0.5.9';

	/**
	 * @return void
	 */
	public static function migrate_saved_engine() {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}
		if ( self::MIGRATION_VERSION === (string) get_option( self::MIGRATION_OPTION, '' ) ) {
			return;
		}
		/*
		 * Sites that stored managed, remote, or remote_fallback keep that value.
		 * Without this entitlement, RWGA_Engine::get_effective_mode() treats it as automatic.
		 * A later Decision Cloud login can honour an explicit hosted choice without another migration.
		 */
		update_option( self::MIGRATION_OPTION, self::MIGRATION_VERSION, false );
	}

	/**
	 * @return bool
	 */
	public static function hosted_ai_allowed() {
		$detected = self::detect_from_cached_tokens();
		/**
		 * Override hosted-AI entitlement detection. Default is the cached Decision Cloud token.
		 *
		 * @param bool $detected Whether a cached token allows hosted AI.
		 */
		return (bool) apply_filters( 'rwgo_decision_cloud_hosted_ai', $detected );
	}

	/**
	 * @return string
	 */
	public static function notice() {
		return __( self::NOTICE, 'reactwoo-geo-optimise' );
	}

	/**
	 * @return \WP_Error
	 */
	public static function unavailable_error() {
		return new WP_Error( 'rwgo_decision_cloud_required', self::notice() );
	}

	/**
	 * @return void
	 */
	public static function render_admin_notice() {
		if ( self::hosted_ai_allowed() ) {
			return;
		}
		echo '<div class="notice notice-info"><p>' . esc_html( self::notice() ) . '</p></div>';
	}

	/**
	 * Hosted generation, usage, snapshot, and cloud-intelligence routes.
	 *
	 * Licence login and the package update check are not AI routes.
	 *
	 * @param string $path Absolute API path.
	 * @return bool
	 */
	public static function is_ai_api_path( $path ) {
		$path = strtolower( (string) $path );
		$query = strpos( $path, '?' );
		if ( false !== $query ) {
			$path = substr( $path, 0, $query );
		}
		foreach ( array( '/ai/', '/geo-ai/', '/geo-optimise/' ) as $needle ) {
			if ( false !== strpos( $path, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Block a hosted AI request before any HTTP call when the site has no entitlement.
	 *
	 * @param string $path Absolute API path.
	 * @return \WP_Error|null Null when the call may proceed.
	 */
	public static function block_ai_request( $path ) {
		if ( ! self::is_ai_api_path( $path ) || self::hosted_ai_allowed() ) {
			return null;
		}
		return self::unavailable_error();
	}

	/**
	 * @param array<string, mixed>|null $claims JWT payload.
	 * @return bool
	 */
	public static function claims_allow_hosted_ai( $claims ) {
		if ( ! is_array( $claims ) ) {
			return false;
		}
		if ( isset( $claims['exp'] ) && is_numeric( $claims['exp'] ) && (int) $claims['exp'] <= time() ) {
			return false;
		}
		if ( self::PACKAGE_SLUG !== self::package_slug( $claims ) ) {
			return false;
		}
		if ( ! in_array( self::plan_code( $claims ), array( 'growth', 'scale' ), true ) ) {
			return false;
		}
		$wanted = array( 'reactwoo-geo-optimise', 'reactwoo-geo-ai' );
		foreach ( array( 'product_slugs', 'productSlugs' ) as $key ) {
			if ( ! isset( $claims[ $key ] ) ) {
				continue;
			}
			foreach ( self::claim_slug_list( $claims[ $key ] ) as $slug ) {
				if ( in_array( $slug, $wanted, true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @return bool
	 */
	private static function detect_from_cached_tokens() {
		foreach ( self::cached_tokens() as $token ) {
			$claims = self::decode_jwt_payload( $token );
			if ( self::claims_allow_hosted_ai( $claims ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Tokens already cached by licence login. This does not log in.
	 *
	 * @return string[]
	 */
	private static function cached_tokens() {
		if ( ! function_exists( 'get_transient' ) ) {
			return array();
		}
		$keys = array( 'rwgo_rw_jwt_cache', 'rwga_rw_jwt_cache' );
		if ( class_exists( 'RWGO_Platform_Client', false ) ) {
			$keys[] = RWGO_Platform_Client::TOKEN_TRANSIENT;
		}
		if ( class_exists( 'RWGA_Platform_Client', false ) ) {
			$keys[] = RWGA_Platform_Client::TOKEN_TRANSIENT;
		}
		$tokens = array();
		foreach ( array_unique( $keys ) as $key ) {
			$cached = get_transient( $key );
			if ( ! is_array( $cached ) || empty( $cached['token'] ) || ! is_string( $cached['token'] ) ) {
				continue;
			}
			if ( isset( $cached['expires'] ) && is_numeric( $cached['expires'] ) && (int) $cached['expires'] <= time() + 120 ) {
				continue;
			}
			$tokens[] = $cached['token'];
		}
		return $tokens;
	}

	/**
	 * @param array<string, mixed> $claims JWT payload.
	 * @return string
	 */
	private static function package_slug( array $claims ) {
		foreach ( array( 'packageType', 'package_type', 'package_slug', 'packageSlug', 'package' ) as $key ) {
			if ( ! isset( $claims[ $key ] ) || is_array( $claims[ $key ] ) ) {
				continue;
			}
			$slug = self::normalize_slug( $claims[ $key ] );
			if ( '' !== $slug ) {
				return $slug;
			}
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $claims JWT payload.
	 * @return string
	 */
	private static function plan_code( array $claims ) {
		foreach ( array( 'plan_code', 'plan_key', 'plan', 'type_key' ) as $key ) {
			if ( ! isset( $claims[ $key ] ) || is_array( $claims[ $key ] ) ) {
				continue;
			}
			$plan = self::normalize_slug( $claims[ $key ] );
			if ( '' !== $plan ) {
				return $plan;
			}
		}
		return '';
	}

	/**
	 * @param mixed $value Raw claim.
	 * @return string
	 */
	private static function normalize_slug( $value ) {
		$slug = strtolower( trim( (string) $value ) );
		$slug = preg_replace( '/[^a-z0-9_\-]/', '', $slug );
		return is_string( $slug ) ? $slug : '';
	}

	/**
	 * @param mixed $value product_slugs claim.
	 * @return string[]
	 */
	private static function claim_slug_list( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\s,]+/', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $item ) {
			if ( is_string( $item ) || is_numeric( $item ) ) {
				$slug = self::normalize_slug( $item );
				if ( '' !== $slug ) {
					$out[] = $slug;
				}
				continue;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			foreach ( array( 'slug', 'product_slug', 'package_slug', 'packageType', 'package_type' ) as $key ) {
				if ( ! isset( $item[ $key ] ) || is_array( $item[ $key ] ) ) {
					continue;
				}
				$slug = self::normalize_slug( $item[ $key ] );
				if ( '' !== $slug ) {
					$out[] = $slug;
				}
			}
		}
		return $out;
	}

	/**
	 * @param string $jwt JWT.
	 * @return array<string, mixed>|null
	 */
	private static function decode_jwt_payload( $jwt ) {
		if ( class_exists( 'RWGO_Platform_Client', false ) ) {
			return RWGO_Platform_Client::decode_jwt_payload( $jwt );
		}
		if ( ! is_string( $jwt ) || '' === $jwt ) {
			return null;
		}
		$parts = explode( '.', $jwt );
		if ( count( $parts ) < 2 ) {
			return null;
		}
		$payload  = $parts[1];
		$payload .= str_repeat( '=', ( 4 - strlen( $payload ) % 4 ) % 4 );
		$decoded  = base64_decode( strtr( $payload, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded ) {
			return null;
		}
		$data = json_decode( $decoded, true );
		return is_array( $data ) ? $data : null;
	}
}
