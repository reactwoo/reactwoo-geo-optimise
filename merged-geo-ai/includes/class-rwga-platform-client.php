<?php
/**
 * Geo AI-owned ReactWoo API client and JWT cache.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Platform_Client {

	const TOKEN_TRANSIENT  = 'rwga_rw_jwt_cache';

	/** Last {@see get_bearer_for_updates()} failure for Settings → Plugin updates diagnostics. */
	const BEARER_ERROR_TRANSIENT = 'rwga_updates_bearer_last_error';

	const LOGIN_PATH           = '/api/v5/auth/login';
	const DEFAULT_API_BASE     = 'https://api.reactwoo.com';
	const DEFAULT_LICENSE_BASE = 'https://license.reactwoo.com';
	const PRODUCT_SLUG     = 'reactwoo-geo-ai';

	/**
	 * Per-request memo for {@see get_license_key()} (cleared in {@see clear_token_cache()}).
	 *
	 * @var string|null
	 */
	private static $license_key_request_memo = null;

	/**
	 * @return string
	 */
	public static function get_api_base() {
		if ( defined( 'RWGA_REACTWOO_API_BASE' ) && is_string( RWGA_REACTWOO_API_BASE ) ) {
			$configured = trim( (string) RWGA_REACTWOO_API_BASE );
			if ( '' !== $configured && wp_http_validate_url( $configured ) ) {
				return untrailingslashit( esc_url_raw( $configured ) );
			}
		}

		$via_filter = apply_filters( 'rwga_reactwoo_api_base', null );
		if ( is_string( $via_filter ) ) {
			$filtered = esc_url_raw( trim( $via_filter ) );
			if ( $filtered && wp_http_validate_url( $filtered ) ) {
				return untrailingslashit( $filtered );
			}
		}

		if ( class_exists( 'RWGA_Settings', false ) ) {
			$settings = RWGA_Settings::get_settings();
			if ( is_array( $settings ) && ! empty( $settings['reactwoo_api_base'] ) ) {
				$saved = esc_url_raw( trim( (string) $settings['reactwoo_api_base'] ) );
				if ( $saved && wp_http_validate_url( $saved ) ) {
					return untrailingslashit( $saved );
				}
			}
		}

		return self::DEFAULT_API_BASE;
	}


	/**
	 * @return string
	 */
	public static function get_license_base() {
		if ( defined( 'RWGA_REACTWOO_LICENSE_BASE' ) && is_string( RWGA_REACTWOO_LICENSE_BASE ) ) {
			$configured = trim( (string) RWGA_REACTWOO_LICENSE_BASE );
			if ( '' !== $configured && wp_http_validate_url( $configured ) ) {
				return untrailingslashit( esc_url_raw( $configured ) );
			}
		}

		$via_filter = apply_filters( 'rwga_reactwoo_license_base', null );
		if ( is_string( $via_filter ) ) {
			$filtered = esc_url_raw( trim( $via_filter ) );
			if ( $filtered && wp_http_validate_url( $filtered ) ) {
				return untrailingslashit( $filtered );
			}
		}

		return self::DEFAULT_LICENSE_BASE;
	}

	/**
	 * Whether to log login/usage API traces (no secrets — tier/HTTP/tier from API only).
	 * Enable: `define( 'RWGA_LICENSE_API_TRACE', true );` in wp-config.php, or filter `rwga_license_api_trace`.
	 *
	 * @return bool
	 */
	public static function should_log_license_api_trace() {
		if ( defined( 'RWGA_LICENSE_API_TRACE' ) && RWGA_LICENSE_API_TRACE ) {
			return true;
		}
		return (bool) apply_filters( 'rwga_license_api_trace', ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) );
	}

	/**
	 * @param string               $event   Short event name.
	 * @param array<string, mixed> $context Context (no raw license keys or JWTs).
	 * @return void
	 */
	public static function log_license_api_trace( $event, array $context = array() ) {
		if ( ! self::should_log_license_api_trace() ) {
			return;
		}
		$line = '[RWGA License API] ' . (string) $event;
		if ( array() !== $context ) {
			$line .= ' ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * @param string $jwt Full JWT.
	 * @return array<string, mixed>|null
	 */
	private static function decode_jwt_payload_for_trace( $jwt ) {
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
	 * @param array<string, mixed>|null $claims JWT payload.
	 * @return array<string, mixed>
	 */
	private static function summarize_jwt_claims_for_trace( $claims ) {
		if ( ! is_array( $claims ) ) {
			return array();
		}
		$keys = array(
			'tier',
			'license_tier',
			'assistant_tier',
			'packageType',
			'package_type',
			'package_slug',
			'product_slug',
			'catalog_slug',
			'plan_code',
			'monthly_ai_tokens',
			'domain',
		);
		$out = array();
		foreach ( $keys as $k ) {
			if ( isset( $claims[ $k ] ) && '' !== (string) $claims[ $k ] ) {
				$out[ $k ] = $claims[ $k ];
			}
		}
		return $out;
	}

	/**
	 * Read `reactwoo_license_key` from the `rwga_settings` option row (direct SQL; bypasses Settings memo + object-cache quirks).
	 *
	 * @return string
	 */
	private static function read_license_key_from_db() {
		global $wpdb;
		if ( ! isset( $wpdb->options ) ) {
			return '';
		}
		$name = class_exists( 'RWGA_Settings', false ) ? RWGA_Settings::OPTION_KEY : 'rwga_settings';
		$raw  = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( null === $raw || '' === $raw ) {
			return '';
		}
		$data = maybe_unserialize( $raw );
		if ( ! is_array( $data ) ) {
			return '';
		}
		return isset( $data['reactwoo_license_key'] ) ? trim( (string) $data['reactwoo_license_key'] ) : '';
	}

	/**
	 * @return string
	 */
	public static function get_license_key() {
		if ( null !== self::$license_key_request_memo ) {
			return self::$license_key_request_memo;
		}
		if (
			class_exists( 'RWGA_Settings', false ) &&
			method_exists( 'RWGA_Settings', 'is_explicitly_disconnected' ) &&
			RWGA_Settings::is_explicitly_disconnected()
		) {
			self::$license_key_request_memo = '';
			return '';
		}
		$k = self::read_license_key_from_db();
		if ( '' === $k && class_exists( 'RWGA_Settings', false ) ) {
			$k = RWGA_Settings::get_saved_license_key();
		}
		if ( '' === $k ) {
			$opt = get_option( 'rwga_settings', array() );
			if ( is_array( $opt ) && ! empty( $opt['reactwoo_license_key'] ) ) {
				$k = trim( (string) $opt['reactwoo_license_key'] );
			}
		}
		self::$license_key_request_memo = $k;
		return $k;
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		if (
			class_exists( 'RWGA_Settings', false ) &&
			method_exists( 'RWGA_Settings', 'is_explicitly_disconnected' ) &&
			RWGA_Settings::is_explicitly_disconnected()
		) {
			return false;
		}
		return '' !== self::get_license_key();
	}

	/**
	 * @return string
	 */
	public static function get_site_domain() {
		$home = home_url( '/' );
		$host = wp_parse_url( $home, PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host ) {
			return $host;
		}
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		}
		return '';
	}

	/**
	 * @return void
	 */
	public static function clear_token_cache() {
		delete_transient( self::TOKEN_TRANSIENT );
		delete_transient( self::BEARER_ERROR_TRANSIENT );
		self::$license_key_request_memo = null;
	}

	/**
	 * Whether we already attempted a proactive JWT fetch this request (avoid duplicate logins).
	 *
	 * @var bool
	 */
	private static $jwt_warm_for_updates_done = false;

	/**
	 * Register hooks that run before WordPress builds `update_plugins` so a bearer exists for /updates/check.
	 *
	 * @return void
	 */
	public static function register_update_check_warm_hooks() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;
		add_action( 'load-plugins.php', array( __CLASS__, 'maybe_warm_access_token_for_updates' ), 1 );
		add_action( 'load-update.php', array( __CLASS__, 'maybe_warm_access_token_for_updates' ), 1 );
		add_action( 'load-update-core.php', array( __CLASS__, 'maybe_warm_access_token_for_updates' ), 1 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_warm_access_token_for_updates' ), 1 );
		add_action( 'wp_update_plugins', array( __CLASS__, 'maybe_warm_access_token_for_updates' ), 1 );
		/*
		 * Runs immediately before {@see RWGC_Satellite_Updater} (priority 10) mutates the transient.
		 * Catches code paths where admin_init / load-plugins hooks did not run (e.g. some cron timings)
		 * so {@see get_access_token()} still runs once before /api/v5/updates/check.
		 */
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'prime_jwt_before_plugins_transient' ), 5, 3 );
	}

	/**
	 * Prime JWT cache right before WordPress persists `update_plugins` (same request as satellite updater).
	 *
	 * @param mixed $value      Transient value about to be saved.
	 * @param int   $expiration TTL (unused).
	 * @param string $transient Name (unused).
	 * @return mixed
	 */
	public static function prime_jwt_before_plugins_transient( $value, $expiration = 0, $transient = '' ) {
		unset( $expiration, $transient );
		if ( self::is_configured() ) {
			self::get_access_token();
		}
		return $value;
	}

	/**
	 * Prime the license JWT cache before {@see pre_set_site_transient_update_plugins} (same login as usage API).
	 *
	 * @return void
	 */
	public static function maybe_warm_access_token_for_updates() {
		if ( self::$jwt_warm_for_updates_done ) {
			return;
		}
		if ( ! self::is_configured() ) {
			return;
		}
		self::$jwt_warm_for_updates_done = true;
		self::get_access_token();
	}

	/**
	 * Cached access token string for admin introspection (decoded payload only in UI; not for transport).
	 *
	 * @return string|null
	 */
	public static function get_cached_access_token_string() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( ! is_array( $cached ) || empty( $cached['token'] ) ) {
			return null;
		}
		return (string) $cached['token'];
	}

	/**
	 * @return string|null
	 */
	public static function get_bearer_for_updates() {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			set_transient(
				self::BEARER_ERROR_TRANSIENT,
				array(
					'code'    => $token->get_error_code(),
					'message' => $token->get_error_message(),
				),
				15 * MINUTE_IN_SECONDS
			);
			return null;
		}
		if ( ! is_string( $token ) || '' === $token ) {
			set_transient(
				self::BEARER_ERROR_TRANSIENT,
				array(
					'code'    => 'rwga_empty_token',
					'message' => __( 'License login returned an empty token.', 'reactwoo-geo-ai' ),
				),
				15 * MINUTE_IN_SECONDS
			);
			return null;
		}
		delete_transient( self::BEARER_ERROR_TRANSIENT );
		return $token;
	}

	/**
	 * @return string|\WP_Error
	 */
	public static function get_access_token() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['token'] ) && isset( $cached['expires'] ) && (int) $cached['expires'] > time() + 120 ) {
			return (string) $cached['token'];
		}

		$license = self::get_license_key();
		if ( '' === $license ) {
			return new WP_Error( 'rwga_no_license', __( 'Save a Geo AI license key before using ReactWoo API features.', 'reactwoo-geo-ai' ) );
		}

		$domain = self::get_site_domain();
		if ( '' === $domain ) {
			return new WP_Error( 'rwga_no_domain', __( 'Could not determine this site domain for license login.', 'reactwoo-geo-ai' ) );
		}

		$body = array(
			'license_key'  => $license,
			'domain'       => $domain,
			'product_slug' => self::PRODUCT_SLUG,
			'catalog_slug' => self::PRODUCT_SLUG,
		);
		/**
		 * Same hook as Geo Core {@see RWGC_Platform_Client::get_access_token()} so login JSON
		 * matches multi-product expectations (license server / api.reactwoo.com proxy).
		 *
		 * @param array<string, string> $body    Login JSON body.
		 * @param string                $license License key used for this login.
		 * @param string                $domain  Site host.
		 */
		$filtered = apply_filters( 'rwgc_auth_login_body', $body, $license, $domain );
		$body     = is_array( $filtered ) ? $filtered : $body;

		// POST /api/v5/auth/login is implemented on the ReactWoo API app (api.reactwoo.com), not on license.reactwoo.com.
		$login_url = untrailingslashit( self::get_api_base() ) . self::LOGIN_PATH;
		self::log_license_api_trace(
			'login_request',
			array(
				'url'          => $login_url,
				'domain'       => $domain,
				'product_slug' => isset( $body['product_slug'] ) ? (string) $body['product_slug'] : '',
			)
		);

		$response = wp_remote_post(
			$login_url,
			array(
				'timeout' => 30,
				'headers' => self::base_headers(),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			self::log_license_api_trace(
				'login_transport_error',
				array( 'message' => $response->get_error_message() )
			);
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg = isset( $data['message'] ) ? (string) $data['message'] : __( 'License login failed.', 'reactwoo-geo-ai' );
			self::log_license_api_trace(
				'login_http_error',
				array(
					'http'    => $code,
					'message' => $msg,
					'body_snip' => is_string( $raw ) ? substr( $raw, 0, 240 ) : '',
				)
			);
			return new WP_Error( 'rwga_login_failed', $msg, array( 'status' => $code ) );
		}

		$token = isset( $data['access_token'] ) ? (string) $data['access_token'] : '';
		if ( '' === $token ) {
			self::log_license_api_trace( 'login_no_access_token', array( 'http' => $code ) );
			return new WP_Error( 'rwga_login_no_token', __( 'License login response did not include a token.', 'reactwoo-geo-ai' ) );
		}

		$ttl = min( self::parse_expires_in( isset( $data['expires_in'] ) ? $data['expires_in'] : null ), 23 * HOUR_IN_SECONDS );
		set_transient(
			self::TOKEN_TRANSIENT,
			array(
				'token'   => $token,
				'expires' => time() + $ttl,
			),
			$ttl
		);

		$claims = self::decode_jwt_payload_for_trace( $token );
		$trace  = array(
			'http' => $code,
			'jwt'  => self::summarize_jwt_claims_for_trace( $claims ),
		);
		if ( isset( $data['token_source'] ) ) {
			$trace['token_source'] = (string) $data['token_source'];
		}
		if ( isset( $data['token_source_detail'] ) ) {
			$trace['token_source_detail'] = (string) $data['token_source_detail'];
		}
		if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
			$trace['login_message'] = $data['message'];
		}
		self::log_license_api_trace( 'login_ok', $trace );

		return $token;
	}

	/**
	 * @param string                    $method HTTP method.
	 * @param string                    $path   Absolute path starting with /.
	 * @param array<string, mixed>|null $body   Request body.
	 * @param bool                      $auth   Whether to send Authorization.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function request( $method, $path, $body = null, $auth = true ) {
		$path = is_string( $path ) ? $path : '';
		if ( '' === $path || '/' !== $path[0] ) {
			return new WP_Error( 'rwga_bad_path', __( 'Invalid API path.', 'reactwoo-geo-ai' ) );
		}

		$headers = self::base_headers();
		if ( $auth ) {
			$token = self::get_access_token();
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$args = array(
			'method'  => strtoupper( $method ),
			'timeout' => 45,
			'headers' => $headers,
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::get_api_base() . $path, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( 401 === $code && $auth ) {
			self::clear_token_cache();
		}

		return array(
			'code'     => $code,
			'data'     => is_array( $data ) ? $data : null,
			'body_raw' => $raw,
		);
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function ai_health() {
		$response = wp_remote_post(
			self::get_api_base() . '/ai/health',
			array(
				'timeout' => 15,
				'headers' => self::base_headers(),
				'body'    => '{}',
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return array(
			'code' => $code,
			'data' => is_array( $data ) ? $data : null,
		);
	}

	/**
	 * GET /api/v5/ai/assistant/usage — success JSON shape:
	 * `{ status: 'success', data: { usage, planLimits, licenseTier } }` (reactwoo-api `assistant.ts`).
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_usage() {
		$result = self::request( 'GET', '/api/v5/ai/assistant/usage', null, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : null;
		if ( $code < 200 || $code >= 300 || null === $data ) {
			$msg = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : __( 'ReactWoo API request failed.', 'reactwoo-geo-ai' );
			self::log_license_api_trace(
				'usage_http_error',
				array(
					'http'    => $code,
					'message' => $msg,
				)
			);
			return new WP_Error( 'rwga_api_error', $msg, array( 'status' => $code, 'data' => $data ) );
		}

		$inner = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : $data;
		$lt    = null;
		if ( is_array( $inner ) ) {
			if ( isset( $inner['licenseTier'] ) ) {
				$lt = $inner['licenseTier'];
			} elseif ( isset( $inner['license_tier'] ) ) {
				$lt = $inner['license_tier'];
			}
		}
		self::log_license_api_trace(
			'usage_ok',
			array(
				'http'         => $code,
				'api_status'   => isset( $data['status'] ) ? $data['status'] : null,
				'licenseTier'  => $lt,
				'tokens_limit' => is_array( $inner ) && isset( $inner['usage'] ) && is_array( $inner['usage'] ) && isset( $inner['usage']['limit'] )
					? $inner['usage']['limit']
					: null,
			)
		);

		return array(
			'success'   => true,
			'http_code' => $code,
			'body'      => $data,
		);
	}

	/**
	 * @param mixed $raw Expiry from API.
	 * @return int
	 */
	private static function parse_expires_in( $raw ) {
		if ( is_numeric( $raw ) ) {
			return max( 300, (int) $raw );
		}
		if ( is_string( $raw ) && preg_match( '/^(\d+)h$/i', $raw, $m ) ) {
			return max( 300, (int) $m[1] * HOUR_IN_SECONDS );
		}
		return DAY_IN_SECONDS;
	}

	/**
	 * @return array<string, string>
	 */
	private static function base_headers() {
		return array(
			'Content-Type'     => 'application/json',
			'X-Requested-With' => 'XMLHttpRequest',
		);
	}
}
