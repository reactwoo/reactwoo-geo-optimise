<?php
/**
 * Geo Optimise-owned ReactWoo API client and JWT cache.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGO_Platform_Client {

	const TOKEN_TRANSIENT  = 'rwgo_rw_jwt_cache';
	const LOGIN_PATH       = '/api/v5/auth/login';
	const DEFAULT_API_BASE = 'https://api.reactwoo.com';
	const PRODUCT_SLUG     = 'reactwoo-geo-optimise';

	/**
	 * Login timeout on the plugins-screen update path. Other API calls keep 30 seconds.
	 */
	const UPDATE_LOGIN_TIMEOUT = 10;

	/**
	 * How long a failed update-path login is remembered so the plugins screen does not retry it.
	 */
	const UPDATE_LOGIN_FAIL_TTL = 900;

	/**
	 * Failed login. The value is the unix time when the window ends. The transient TTL is also 900 seconds.
	 */
	const UPDATE_LOGIN_FAIL_TRANSIENT = 'rwgo_updates_login_failed';

	/**
	 * Same window as {@see UPDATE_LOGIN_FAIL_TRANSIENT}, stored as an option so a short object-cache TTL cannot drop it.
	 */
	const UPDATE_LOGIN_FAIL_OPTION = 'rwgo_updates_login_failed_until';

	/**
	 * @return string
	 */
	public static function get_api_base() {
		if ( defined( 'RWGO_REACTWOO_API_BASE' ) && is_string( RWGO_REACTWOO_API_BASE ) ) {
			$configured = trim( (string) RWGO_REACTWOO_API_BASE );
			if ( '' !== $configured && wp_http_validate_url( $configured ) ) {
				return untrailingslashit( esc_url_raw( $configured ) );
			}
		}

		$via_filter = apply_filters( 'rwgo_reactwoo_api_base', null );
		if ( is_string( $via_filter ) ) {
			$filtered = esc_url_raw( trim( $via_filter ) );
			if ( $filtered && wp_http_validate_url( $filtered ) ) {
				return untrailingslashit( $filtered );
			}
		}

		if ( class_exists( 'RWGO_Settings', false ) ) {
			$settings = RWGO_Settings::get_settings();
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
	public static function get_license_key() {
		if ( ! class_exists( 'RWGO_Settings', false ) ) {
			return '';
		}
		$settings = RWGO_Settings::get_settings();
		if ( ! is_array( $settings ) || empty( $settings['reactwoo_license_key'] ) ) {
			return '';
		}
		return trim( (string) $settings['reactwoo_license_key'] );
	}

	/**
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== self::get_license_key();
	}

	/**
	 * @return void
	 */
	public static function clear_token_cache() {
		delete_transient( self::TOKEN_TRANSIENT );
		self::forget_login_failure();
	}

	/**
	 * Bearer for Geo Core's satellite updater.
	 *
	 * Geo Core passes the update-path login timeout when this callback accepts a parameter.
	 *
	 * @param int $login_timeout Login HTTP timeout in seconds.
	 * @return string|null
	 */
	public static function get_bearer_for_updates( $login_timeout = self::UPDATE_LOGIN_TIMEOUT ) {
		$token = self::login_for_updates( $login_timeout );
		if ( is_wp_error( $token ) || ! is_string( $token ) || '' === $token ) {
			return null;
		}
		return $token;
	}

	/**
	 * Whether this request may start a new license login.
	 *
	 * Ordinary front-end views and WP-CLI commands may reuse a saved token, but they do not log in.
	 *
	 * @return bool
	 */
	public static function request_may_login() {
		return ! self::request_skips_new_login();
	}

	/**
	 * Bearer JWT for Authorization header, refreshed via login when needed.
	 *
	 * Every caller shares the saved token and the 900-second failed-login window.
	 * Outside an admin-triggered action the HTTP timeout is 10 seconds.
	 *
	 * @param int $timeout Login HTTP timeout in seconds. Admin-post and admin-ajax may pass 30.
	 * @return string|\WP_Error
	 */
	public static function get_access_token( $timeout = 30 ) {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_array( $cached ) && ! empty( $cached['token'] ) && isset( $cached['expires'] ) && (int) $cached['expires'] > time() + 120 ) {
			self::forget_login_failure();
			return (string) $cached['token'];
		}

		if ( self::login_recently_failed() ) {
			return new WP_Error(
				'rwgo_login_cached_failure',
				__( 'License login failed recently. It will be retried in a few minutes.', 'reactwoo-geo-optimise' )
			);
		}

		$license = self::get_license_key();
		if ( '' === $license ) {
			return new WP_Error( 'rwgo_no_license', __( 'Save a Geo Optimise license key before validating or updating this plugin.', 'reactwoo-geo-optimise' ) );
		}

		$domain = self::get_site_domain();
		if ( '' === $domain ) {
			return new WP_Error( 'rwgo_no_domain', __( 'Could not determine this site domain for license login.', 'reactwoo-geo-optimise' ) );
		}

		if ( self::request_skips_new_login() ) {
			return new WP_Error(
				'rwgo_login_skipped',
				__( 'License login does not run on this request.', 'reactwoo-geo-optimise' )
			);
		}

		$timeout = self::resolve_login_timeout( $timeout );

		$body = array(
			'license_key'  => $license,
			'domain'       => $domain,
			'product_slug' => self::PRODUCT_SLUG,
			'catalog_slug' => self::PRODUCT_SLUG,
		);
		$filtered = apply_filters( 'rwgc_auth_login_body', $body, $license, $domain );
		$body     = is_array( $filtered ) ? $filtered : $body;

		$response = wp_remote_post(
			self::get_api_base() . self::LOGIN_PATH,
			array(
				'timeout' => $timeout,
				'headers' => self::base_headers(),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			self::remember_login_failure();
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg = isset( $data['message'] ) ? (string) $data['message'] : __( 'License login failed.', 'reactwoo-geo-optimise' );
			self::remember_login_failure();
			return new WP_Error( 'rwgo_login_failed', $msg, array( 'status' => $code ) );
		}

		$token = isset( $data['access_token'] ) ? (string) $data['access_token'] : '';
		if ( '' === $token ) {
			self::remember_login_failure();
			return new WP_Error( 'rwgo_login_no_token', __( 'License login response did not include a token.', 'reactwoo-geo-optimise' ) );
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
		self::forget_login_failure();
		return $token;
	}

	/**
	 * License login for plugin update checks. Uses the same caches as {@see get_access_token()}.
	 *
	 * @param int $timeout Login HTTP timeout in seconds.
	 * @return string|\WP_Error
	 */
	private static function login_for_updates( $timeout ) {
		return self::get_access_token( (int) $timeout );
	}

	/**
	 * @return bool
	 */
	private static function request_is_cli() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		return (bool) apply_filters( 'rwgo_platform_login_is_cli', false );
	}

	/**
	 * @return bool
	 */
	private static function request_skips_new_login() {
		if ( self::request_is_cli() ) {
			return true;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return false;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}
		return true;
	}

	/**
	 * Admin-post and admin-ajax are the actions a person started. Other logins stay at 10 seconds.
	 *
	 * @return bool
	 */
	private static function is_admin_triggered_action() {
		if ( self::request_is_cli() || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) ) {
			return false;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}
		$raw = isset( $_SERVER['PHP_SELF'] ) ? (string) $_SERVER['PHP_SELF'] : '';
		if ( function_exists( 'wp_unslash' ) ) {
			$raw = (string) wp_unslash( $raw );
		}
		$script = strtolower( basename( $raw ) );
		return in_array( $script, array( 'admin-post.php', 'admin-ajax.php' ), true );
	}

	/**
	 * @param int $timeout Requested timeout.
	 * @return int
	 */
	private static function resolve_login_timeout( $timeout ) {
		$timeout = (int) $timeout;
		if ( $timeout < 1 ) {
			$timeout = 30;
		}
		if ( ! self::is_admin_triggered_action() && $timeout > self::UPDATE_LOGIN_TIMEOUT ) {
			return self::UPDATE_LOGIN_TIMEOUT;
		}
		return $timeout;
	}

	/**
	 * @return bool
	 */
	private static function login_recently_failed() {
		return self::login_failure_until() > time();
	}

	/**
	 * @return int Unix time when the failure window ends, or 0.
	 */
	private static function login_failure_until() {
		$until = 0;
		if ( function_exists( 'get_option' ) ) {
			$opt = get_option( self::UPDATE_LOGIN_FAIL_OPTION, 0 );
			if ( is_numeric( $opt ) ) {
				$until = (int) $opt;
			}
		}
		if ( function_exists( 'get_transient' ) ) {
			$cached = get_transient( self::UPDATE_LOGIN_FAIL_TRANSIENT );
			if ( is_numeric( $cached ) ) {
				$until = max( $until, (int) $cached );
			} elseif ( false !== $cached && '' !== (string) $cached ) {
				$until = max( $until, time() + 1 );
			}
		}
		return $until;
	}

	/**
	 * @return void
	 */
	private static function remember_login_failure() {
		if ( self::login_failure_until() > time() ) {
			return;
		}
		$until = time() + self::UPDATE_LOGIN_FAIL_TTL;
		set_transient( self::UPDATE_LOGIN_FAIL_TRANSIENT, (string) $until, self::UPDATE_LOGIN_FAIL_TTL );
		if ( function_exists( 'update_option' ) ) {
			update_option( self::UPDATE_LOGIN_FAIL_OPTION, $until, false );
		}
	}

	/**
	 * @return void
	 */
	private static function forget_login_failure() {
		delete_transient( self::UPDATE_LOGIN_FAIL_TRANSIENT );
		if ( function_exists( 'delete_option' ) ) {
			delete_option( self::UPDATE_LOGIN_FAIL_OPTION );
		}
	}

	/**
	 * @return string
	 */
	private static function get_site_domain() {
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
