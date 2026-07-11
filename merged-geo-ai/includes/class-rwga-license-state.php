<?php
/**
 * Single canonical persisted snapshot for Geo AI license + assistant usage (connect / refresh / disconnect).
 *
 * Previous sources (collapsed here):
 * - License key: `rwga_settings` option (RWGA_Settings::OPTION_KEY) — still authoritative for the key string.
 * - JWT cache: transient `rwga_rw_jwt_cache` (RWGA_Platform_Client).
 * - Usage/tier/package UI: this option (`rwga_license_state`), replacing `rwga_assistant_usage_cache`.
 * - Updates check diag: `rwga_updates_check_last` (RWGA_Updates_Diagnostics).
 * - No browser localStorage for license state.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns option `rwga_license_state` and legacy cleanup for `rwga_assistant_usage_cache`.
 */
class RWGA_License_State {

	const OPTION_KEY = 'rwga_license_state';

	const SCHEMA_VERSION = 1;

	/** Tier when API omits label and limit does not imply a paid band — never invent "free". */
	const TIER_UNKNOWN = 'unknown';

	/**
	 * @return void
	 */
	public static function log_debug( $event, array $context = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}
		$line = '[RWGA License] ' . (string) $event;
		if ( array() !== $context ) {
			$line .= ' ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Default empty snapshot (disconnected / nothing loaded).
	 *
	 * @return array<string, mixed>
	 */
	public static function default_snapshot() {
		return array(
			'schema_version'    => self::SCHEMA_VERSION,
			'license_status'    => 'disconnected',
			'connected'         => false,
			'license_key'       => '',
			'package_name'      => null,
			'assistant_tier'    => null,
			'tier_source'       => null,
			'token_limit'       => null,
			'tokens_used'       => null,
			'tokens_remaining'  => null,
			'usage_period'      => null,
			'last_checked_at'   => null,
			'http_code_last'    => null,
			'api_license_tier_raw' => null,
			'plan_limits'       => array(),
			'product_context'   => array(),
			'over_limit'        => false,
			'jwt_domain'        => '',
			'jwt_product_slug'  => '',
			'jwt_catalog_slug'  => '',
			'jwt_package_type'  => '',
			'jwt_plan_code'     => '',
			'jwt_tier'          => '',
			'jwt_package_label' => '',
		);
	}

	/**
	 * Full snapshot for debugging (no license key material).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_snapshot() {
		$raw = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $raw ) || empty( $raw['schema_version'] ) ) {
			self::maybe_migrate_legacy_usage_option();
			$raw = get_option( self::OPTION_KEY, null );
		}
		if ( ! is_array( $raw ) ) {
			return self::default_snapshot();
		}
		return array_merge( self::default_snapshot(), $raw );
	}

	/**
	 * Persist API usage + JWT hints; replaces any prior snapshot for this site.
	 *
	 * @param array<string, mixed>|null $body     JSON body.
	 * @param int                       $http_code HTTP status.
	 * @return bool True if stored.
	 */
	public static function ingest_usage_api_body( $body, $http_code ) {
		$norm = self::normalize_api_body( $body );
		if ( null === $norm ) {
			self::log_debug( 'ingest_skipped_invalid_body', array( 'http' => (int) $http_code ) );
			return false;
		}

		$claims   = null;
		$jwt_hint = '';
		if ( class_exists( 'RWGA_License_Introspection', false ) ) {
			$claims = RWGA_License_Introspection::get_bearer_claims();
			if ( is_array( $claims ) ) {
				$jwt_hint = self::jwt_tier_hint_from_claims( $claims );
			}
		}

		$tier_info = self::resolve_assistant_tier( $norm['api_license_tier_raw'], (int) $norm['limit'], $jwt_hint );

		$key_present = class_exists( 'RWGA_Settings', false ) && '' !== trim( (string) RWGA_Settings::get_saved_license_key() );

		$pkg = '';
		if ( is_array( $claims ) && class_exists( 'RWGA_License_Introspection', false ) ) {
			$pkg = RWGA_License_Introspection::format_package_summary( $claims );
		}

		$snapshot = array_merge(
			self::default_snapshot(),
			array(
				'schema_version'       => self::SCHEMA_VERSION,
				'license_status'       => $key_present ? 'connected' : 'disconnected',
				'connected'            => $key_present,
				'license_key'          => '',
				'package_name'         => '' !== $pkg ? $pkg : null,
				'assistant_tier'       => $tier_info['tier'],
				'tier_source'          => $tier_info['source'],
				'token_limit'          => (int) $norm['limit'],
				'tokens_used'          => (int) $norm['used'],
				'tokens_remaining'     => (int) $norm['remaining'],
				'usage_period'         => (string) $norm['period'],
				'last_checked_at'      => gmdate( 'c' ),
				'http_code_last'       => (int) $http_code,
				'api_license_tier_raw' => (string) $norm['api_license_tier_raw'],
				'plan_limits'          => $norm['plan_limits'],
				'product_context'      => $norm['product_context'],
				'over_limit'           => (bool) $norm['over_limit'],
			)
		);

		// Legacy field used across views — same value as assistant_tier for BC.
		$snapshot['license_tier'] = $tier_info['tier'];

		// Mirror old cache field names for filters / templates.
		$snapshot['refreshed_at_gmt'] = $snapshot['last_checked_at'];
		$snapshot['used']             = $snapshot['tokens_used'];
		$snapshot['limit']             = $snapshot['token_limit'];
		$snapshot['remaining']         = $snapshot['tokens_remaining'];
		$snapshot['period']            = $snapshot['usage_period'];

		if ( is_array( $claims ) && class_exists( 'RWGA_License_Introspection', false ) ) {
			$snapshot['jwt_domain']         = isset( $claims['domain'] ) ? sanitize_text_field( (string) $claims['domain'] ) : '';
			$snapshot['jwt_product_slug']  = isset( $claims['product_slug'] ) ? sanitize_key( (string) $claims['product_slug'] ) : '';
			$snapshot['jwt_catalog_slug']  = isset( $claims['catalog_slug'] ) ? sanitize_key( (string) $claims['catalog_slug'] ) : '';
			$snapshot['jwt_package_type']  = isset( $claims['packageType'] ) ? sanitize_text_field( (string) $claims['packageType'] ) : '';
			$snapshot['jwt_plan_code']     = isset( $claims['plan_code'] ) ? sanitize_text_field( (string) $claims['plan_code'] ) : '';
			$snapshot['jwt_tier']          = RWGA_License_Introspection::tier_from_claims( $claims );
			$snapshot['jwt_package_label'] = RWGA_License_Introspection::format_package_summary( $claims );
		}

		update_option( self::OPTION_KEY, $snapshot, false );
		delete_option( 'rwga_assistant_usage_cache' );

		self::log_debug(
			'ingest_saved',
			array(
				'http'   => (int) $http_code,
				'tier'   => $tier_info['tier'],
				'source' => $tier_info['source'],
				'limit'  => (int) $norm['limit'],
			)
		);

		return true;
	}

	/**
	 * Clear all persisted license/usage data and related caches (disconnect / invalid key).
	 *
	 * @param string $reason Reason for logs.
	 * @return void
	 */
	public static function clear_all( $reason = 'disconnect' ) {
		$before = self::get_snapshot();
		self::log_debug( 'clear_before', array( 'reason' => $reason, 'snapshot' => $before ) );

		delete_option( self::OPTION_KEY );
		delete_option( 'rwga_assistant_usage_cache' );
		wp_cache_delete( self::OPTION_KEY, 'options' );
		wp_cache_delete( 'rwga_assistant_usage_cache', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		self::log_debug( 'clear_after', array( 'reason' => $reason, 'snapshot' => self::get_snapshot() ) );
	}

	/**
	 * @param array<string, mixed>|null $body API JSON.
	 * @return array<string, mixed>|null
	 */
	private static function normalize_api_body( $body ) {
		if ( ! is_array( $body ) ) {
			return null;
		}
		$inner = null;
		if ( isset( $body['data'] ) && is_array( $body['data'] ) ) {
			$inner = $body['data'];
		} elseif ( isset( $body['usage'] ) && is_array( $body['usage'] ) ) {
			$inner = $body;
		}
		if ( null === $inner || ! is_array( $inner ) ) {
			return null;
		}
		if ( isset( $inner['data'] ) && is_array( $inner['data'] ) && isset( $inner['data']['usage'] ) && is_array( $inner['data']['usage'] ) ) {
			$inner = $inner['data'];
		}
		$usage = isset( $inner['usage'] ) && is_array( $inner['usage'] ) ? $inner['usage'] : null;
		if ( null === $usage ) {
			return null;
		}
		$plan_limits = array();
		if ( isset( $inner['planLimits'] ) && is_array( $inner['planLimits'] ) ) {
			$plan_limits = $inner['planLimits'];
		} elseif ( isset( $inner['plan_limits'] ) && is_array( $inner['plan_limits'] ) ) {
			$plan_limits = $inner['plan_limits'];
		}
		$tier_raw = '';
		if ( isset( $inner['licenseTier'] ) ) {
			$tier_raw = sanitize_key( (string) $inner['licenseTier'] );
		} elseif ( isset( $inner['license_tier'] ) ) {
			$tier_raw = sanitize_key( (string) $inner['license_tier'] );
		}
		if ( '' === $tier_raw && is_array( $usage ) ) {
			if ( isset( $usage['licenseTier'] ) ) {
				$tier_raw = sanitize_key( (string) $usage['licenseTier'] );
			} elseif ( isset( $usage['license_tier'] ) ) {
				$tier_raw = sanitize_key( (string) $usage['license_tier'] );
			}
		}
		if ( '' === $tier_raw && is_array( $body ) ) {
			if ( isset( $body['licenseTier'] ) ) {
				$tier_raw = sanitize_key( (string) $body['licenseTier'] );
			} elseif ( isset( $body['license_tier'] ) ) {
				$tier_raw = sanitize_key( (string) $body['license_tier'] );
			}
		}

		$used      = isset( $usage['used'] ) ? (int) $usage['used'] : ( isset( $usage['used_tokens'] ) ? (int) $usage['used_tokens'] : 0 );
		$limit     = isset( $usage['limit'] ) ? (int) $usage['limit'] : 0;
		$remaining = isset( $usage['remaining'] ) ? (int) $usage['remaining'] : 0;
		$period    = '';
		if ( isset( $usage['period'] ) ) {
			$period = sanitize_text_field( (string) $usage['period'] );
		} elseif ( isset( $usage['billing_period'] ) ) {
			$period = sanitize_text_field( (string) $usage['billing_period'] );
		}
		$over = ! empty( $usage['overLimit'] ) || ! empty( $usage['over_limit'] );

		$product_context = array();
		if ( isset( $inner['product_context'] ) && is_array( $inner['product_context'] ) ) {
			$product_context = $inner['product_context'];
		}

		return array(
			'api_license_tier_raw' => $tier_raw,
			'period'               => $period,
			'used'                 => $used,
			'limit'                => $limit,
			'remaining'            => $remaining,
			'over_limit'           => $over,
			'plan_limits'          => $plan_limits,
			'product_context'      => $product_context,
		);
	}

	/**
	 * Tier for UI: JWT and token limits can override a stale usage API `free` label.
	 *
	 * @param string $api_raw   Sanitized tier from usage API.
	 * @param int    $limit     Token cap for the period.
	 * @param string $jwt_hint  free|pro|enterprise from JWT tier fields or monthly_ai_tokens heuristic.
	 * @return array{tier: string, source: string}
	 */
	private static function resolve_assistant_tier( $api_raw, $limit, $jwt_hint = '' ) {
		$api_raw   = is_string( $api_raw ) ? sanitize_key( $api_raw ) : '';
		$jwt_hint  = is_string( $jwt_hint ) ? sanitize_key( $jwt_hint ) : '';
		$lim       = (int) $limit;

		// 1) License JWT says paid — prefer over usage API when it still reports free/empty (billing lag).
		if ( '' !== $jwt_hint && in_array( $jwt_hint, array( 'pro', 'enterprise' ), true ) ) {
			if ( 'free' === $api_raw || '' === $api_raw ) {
				return array(
					'tier'   => $jwt_hint,
					'source' => 'jwt_overrides_usage_api',
				);
			}
		}

		// 2) Usage API says free but allowance is clearly paid-scale (stale tier label).
		if ( 'free' === $api_raw && $lim > 100_000 ) {
			if ( $lim >= 9_000_000 ) {
				return array( 'tier' => 'enterprise', 'source' => 'limit_overrides_stale_free' );
			}
			return array( 'tier' => 'pro', 'source' => 'limit_overrides_stale_free' );
		}

		// 3) Explicit usage API tier.
		if ( '' !== $api_raw && in_array( $api_raw, array( 'free', 'pro', 'enterprise' ), true ) ) {
			return array(
				'tier'   => $api_raw,
				'source' => 'api',
			);
		}

		if ( $lim <= 0 ) {
			return array(
				'tier'   => self::TIER_UNKNOWN,
				'source' => 'not_available',
			);
		}
		if ( $lim >= 9_000_000 ) {
			return array( 'tier' => 'enterprise', 'source' => 'limit_inferred' );
		}
		if ( $lim >= 1_000_000 ) {
			return array( 'tier' => 'pro', 'source' => 'limit_inferred' );
		}
		if ( $lim > 100_000 ) {
			return array( 'tier' => 'pro', 'source' => 'limit_inferred' );
		}

		return array(
			'tier'   => self::TIER_UNKNOWN,
			'source' => 'unlabeled',
		);
	}

	/**
	 * Pro/enterprise hint from JWT tier claims or monthly_ai_tokens when tier strings are empty.
	 *
	 * @param array<string, mixed> $claims JWT payload.
	 * @return string free|pro|enterprise or ''
	 */
	private static function jwt_tier_hint_from_claims( $claims ) {
		if ( ! is_array( $claims ) || ! class_exists( 'RWGA_License_Introspection', false ) ) {
			return '';
		}
		$t = RWGA_License_Introspection::tier_from_claims( $claims );
		if ( '' !== $t ) {
			return $t;
		}
		$mt = 0;
		if ( isset( $claims['monthly_ai_tokens'] ) ) {
			$mt = (int) $claims['monthly_ai_tokens'];
		}
		if ( $mt <= 0 ) {
			return '';
		}
		if ( $mt >= 9_000_000 ) {
			return 'enterprise';
		}
		if ( $mt >= 1_000_000 || $mt > 100_000 ) {
			return 'pro';
		}
		return '';
	}

	/**
	 * Copy legacy option into canonical snapshot once.
	 *
	 * @return void
	 */
	private static function maybe_migrate_legacy_usage_option() {
		$legacy = get_option( 'rwga_assistant_usage_cache', null );
		if ( ! is_array( $legacy ) || isset( $legacy['schema_version'] ) ) {
			return;
		}
		$migrated = $legacy;
		if ( empty( $migrated['schema_version'] ) ) {
			$migrated['schema_version'] = self::SCHEMA_VERSION;
		}
		if ( empty( $migrated['license_status'] ) ) {
			$migrated['license_status'] = class_exists( 'RWGA_Settings', false ) && RWGA_Settings::is_license_configured_for_geo_ai_ui() ? 'connected' : 'disconnected';
		}
		update_option( self::OPTION_KEY, $migrated, false );
		delete_option( 'rwga_assistant_usage_cache' );
		self::log_debug( 'migrated_legacy_usage_option', array() );
	}
}
