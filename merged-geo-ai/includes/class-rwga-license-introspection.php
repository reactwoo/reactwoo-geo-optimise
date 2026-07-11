<?php
/**
 * Decode cached plugin JWT and compare with site / product expectations (read-only, admin).
 *
 * Mirrors reactwoo-api `getLicenseJwtPayload` / tier-related claims used by `checkLicenseTier`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License token + API usage introspection for Settings.
 */
class RWGA_License_Introspection {

	/**
	 * Decode JWT payload segment (no signature verification — display-only, same as API base64 decode of Bearer).
	 *
	 * @param string $jwt Full JWT.
	 * @return array<string, mixed>|null
	 */
	public static function decode_jwt_payload( $jwt ) {
		if ( ! is_string( $jwt ) || '' === $jwt ) {
			return null;
		}
		$parts = explode( '.', $jwt );
		if ( count( $parts ) !== 3 ) {
			return null;
		}
		$payload = $parts[1];
		$payload .= str_repeat( '=', ( 4 - strlen( $payload ) % 4 ) % 4 );
		$decoded = base64_decode( strtr( $payload, '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded ) {
			return null;
		}
		$data = json_decode( $decoded, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Claims from the cached access token (after login or refresh that obtained a Bearer).
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get_bearer_claims() {
		if ( ! class_exists( 'RWGA_Platform_Client', false ) ) {
			return null;
		}
		$jwt = RWGA_Platform_Client::get_cached_access_token_string();
		if ( null === $jwt || '' === $jwt ) {
			return null;
		}
		return self::decode_jwt_payload( $jwt );
	}

	/**
	 * Explicit tier from JWT if present (free|pro|enterprise).
	 *
	 * @param array<string, mixed> $claims JWT payload.
	 * @return string
	 */
	public static function tier_from_claims( $claims ) {
		if ( ! is_array( $claims ) ) {
			return '';
		}
		$t = $claims['tier'] ?? $claims['license_tier'] ?? $claims['assistant_tier'] ?? null;
		if ( null === $t ) {
			return '';
		}
		$t = sanitize_key( strtolower( trim( (string) $t ) ) );
		return in_array( $t, array( 'free', 'pro', 'enterprise' ), true ) ? $t : '';
	}

	/**
	 * Whether JWT domain matches this site host (same rules as API licenseAuth: exact, subdomain, or reversed subdomain).
	 *
	 * @param string $site_host Host from home_url, e.g. www.example.com.
	 * @param string $token_domain Domain claim from JWT.
	 * @return bool
	 */
	public static function domain_matches_token( $site_host, $token_domain ) {
		$site_host    = strtolower( trim( (string) $site_host ) );
		$token_domain = strtolower( trim( (string) $token_domain ) );
		if ( '' === $site_host || '' === $token_domain ) {
			return false;
		}
		if ( $site_host === $token_domain ) {
			return true;
		}
		$ld = strlen( $token_domain );
		$lh = strlen( $site_host );
		if ( $lh > $ld && substr( $site_host, -$ld - 1 ) === '.' . $token_domain ) {
			return true;
		}
		if ( $ld > $lh && substr( $token_domain, -$lh - 1 ) === '.' . $site_host ) {
			return true;
		}
		return false;
	}

	/**
	 * Human-readable package / plan line from JWT claims (license server fields).
	 *
	 * @param array<string, mixed> $claims JWT payload.
	 * @return string
	 */
	public static function format_package_summary( $claims ) {
		if ( ! is_array( $claims ) ) {
			return '';
		}
		$parts = array();
		$pkg   = isset( $claims['packageType'] ) ? trim( (string) $claims['packageType'] ) : '';
		if ( '' !== $pkg ) {
			$parts[] = $pkg;
		}
		foreach ( array( 'plan_code', 'plan_key', 'type_key', 'package_slug', 'packageSlug' ) as $k ) {
			if ( ! empty( $claims[ $k ] ) ) {
				$parts[] = trim( (string) $claims[ $k ] );
				break;
			}
		}
		$parts = array_filter( array_unique( $parts ) );
		return ! empty( $parts ) ? implode( ' — ', $parts ) : '';
	}
}
