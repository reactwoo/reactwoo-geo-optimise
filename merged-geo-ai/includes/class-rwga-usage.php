<?php
/**
 * Cached assistant usage (delegates persistence to {@see RWGA_License_State}).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RWGA_License_State', false ) && defined( 'RWGA_PATH' ) ) {
	require_once RWGA_PATH . 'includes/class-rwga-license-state.php';
}

/**
 * Facade for assistant token usage (GET /api/v5/ai/assistant/usage).
 */
class RWGA_Usage {

	/**
	 * Legacy option key — data now lives in {@see RWGA_License_State::OPTION_KEY}.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'rwga_assistant_usage_cache';

	/**
	 * @param array<string, mixed>|null $body     JSON body from {@see RWGA_Platform_Client::get_usage()}.
	 * @param int                       $http_code HTTP status.
	 * @return bool True if cache was updated.
	 */
	public static function save_from_api_body( $body, $http_code ) {
		if ( ! class_exists( 'RWGA_License_State', false ) ) {
			return false;
		}
		return RWGA_License_State::ingest_usage_api_body( $body, $http_code );
	}

	/**
	 * @return array<string, mixed>|null Snapshot compatible with previous `rwga_assistant_usage_cache` consumers.
	 */
	public static function get_cache() {
		if ( class_exists( 'RWGA_Settings', false ) && ! RWGA_Settings::is_license_configured_for_geo_ai_ui() ) {
			if ( class_exists( 'RWGA_License_State', false ) && false !== get_option( RWGA_License_State::OPTION_KEY, false ) ) {
				delete_option( RWGA_License_State::OPTION_KEY );
			}
			if ( false !== get_option( self::OPTION_KEY, false ) ) {
				delete_option( self::OPTION_KEY );
			}
			return null;
		}
		if ( ! class_exists( 'RWGA_License_State', false ) ) {
			return null;
		}
		$s = RWGA_License_State::get_snapshot();
		if ( ! is_array( $s ) ) {
			return null;
		}
		$ts = isset( $s['refreshed_at_gmt'] ) ? (string) $s['refreshed_at_gmt'] : ( isset( $s['last_checked_at'] ) ? (string) $s['last_checked_at'] : '' );
		if ( '' === $ts || ! isset( $s['used'], $s['limit'] ) ) {
			return null;
		}
		return $s;
	}

	/**
	 * Human-readable plan line (never invents a “Free” product for Geo AI when tier is unknown).
	 *
	 * @param array<string, mixed>|null $cache From {@see get_cache()}.
	 * @return string
	 */
	public static function format_plan_label( $cache ) {
		if ( ! is_array( $cache ) ) {
			return '';
		}
		if ( class_exists( 'RWGA_Settings', false ) && ! RWGA_Settings::is_license_configured_for_geo_ai_ui() ) {
			return '';
		}
		$tier  = isset( $cache['assistant_tier'] ) ? sanitize_key( (string) $cache['assistant_tier'] ) : '';
		if ( '' === $tier && isset( $cache['license_tier'] ) ) {
			$tier = sanitize_key( (string) $cache['license_tier'] );
		}
		$limit = isset( $cache['limit'] ) ? (int) $cache['limit'] : ( isset( $cache['token_limit'] ) ? (int) $cache['token_limit'] : 0 );

		$tier_source = isset( $cache['tier_source'] ) ? sanitize_key( (string) $cache['tier_source'] ) : '';

		if ( 'unknown' === $tier || '' === $tier ) {
			if ( $limit > 0 ) {
				return sprintf(
					/* translators: %s: formatted token limit */
					__( '%s assistant tokens this billing period (tier not labeled by API)', 'reactwoo-geo-ai' ),
					number_format_i18n( $limit )
				);
			}
			return __( 'Package information unavailable — save your license and use Refresh usage.', 'reactwoo-geo-ai' );
		}

		// Do not present “Free — …” as a product name when the only signal is a stale usage API label.
		if ( 'free' === $tier && 'api' === $tier_source && $limit > 0 ) {
			return sprintf(
				/* translators: %s: formatted token limit */
				__( '%s assistant tokens this billing period (usage API reports free — confirm your ReactWoo plan if this should be higher)', 'reactwoo-geo-ai' ),
				number_format_i18n( $limit )
			);
		}

		$names = array(
			'free'         => __( 'Free', 'reactwoo-geo-ai' ),
			'pro'          => __( 'Pro', 'reactwoo-geo-ai' ),
			'enterprise'   => __( 'Enterprise', 'reactwoo-geo-ai' ),
		);
		$name = isset( $names[ $tier ] ) ? $names[ $tier ] : ucfirst( $tier );
		if ( $limit > 0 ) {
			return sprintf(
				/* translators: 1: plan name, 2: formatted token limit */
				__( '%1$s — %2$s assistant tokens this billing period (API quota)', 'reactwoo-geo-ai' ),
				$name,
				number_format_i18n( $limit )
			);
		}
		return $name;
	}

	/**
	 * Rows for a widefat table (label => display value).
	 *
	 * @return array<string, string>
	 */
	public static function get_display_rows() {
		$c = self::get_cache();
		if ( null === $c ) {
			return array();
		}

		$rows = array(
			__( 'Last refreshed (GMT)', 'reactwoo-geo-ai' ) => (string) ( $c['refreshed_at_gmt'] ?? $c['last_checked_at'] ?? '' ),
			__( 'License tier', 'reactwoo-geo-ai' )        => (string) ( $c['assistant_tier'] ?? $c['license_tier'] ?? '' ),
			__( 'Billing period', 'reactwoo-geo-ai' )       => (string) ( $c['period'] ?? $c['usage_period'] ?? '' ),
			__( 'Tokens used', 'reactwoo-geo-ai' )          => (string) (int) ( $c['used'] ?? $c['tokens_used'] ?? 0 ),
			__( 'Plan limit', 'reactwoo-geo-ai' )            => (string) (int) ( $c['limit'] ?? $c['token_limit'] ?? 0 ),
			__( 'Remaining', 'reactwoo-geo-ai' )             => (string) (int) ( $c['remaining'] ?? $c['tokens_remaining'] ?? 0 ),
			__( 'Over limit', 'reactwoo-geo-ai' )            => ! empty( $c['over_limit'] ) ? __( 'Yes', 'reactwoo-geo-ai' ) : __( 'No', 'reactwoo-geo-ai' ),
		);

		if ( ! empty( $c['plan_limits'] ) && is_array( $c['plan_limits'] ) ) {
			$enc = wp_json_encode( $c['plan_limits'], JSON_UNESCAPED_SLASHES );
			if ( is_string( $enc ) ) {
				$rows[ __( 'Plan limits (all tiers)', 'reactwoo-geo-ai' ) ] = $enc;
			}
		}

		return apply_filters( 'rwga_usage_display_rows', $rows, $c );
	}
}
