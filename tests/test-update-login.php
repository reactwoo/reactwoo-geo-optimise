<?php
/**
 * Plugins-screen logins must not run on a last_checked bump, must time out at 10 seconds,
 * and must remember a failure for 15 minutes.
 *
 * Run: php tests/test-update-login.php
 *
 * @package ReactWooGeoOptimise
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

$GLOBALS['rw_failed']     = 0;
$GLOBALS['rw_posts']      = array();
$GLOBALS['rw_transients'] = array();
$GLOBALS['rw_site']       = false;
$GLOBALS['rw_login_mode']   = 'token';
$GLOBALS['rw_token_claims'] = array(
	'packageType'  => 'reactwoo-geo-optimise',
	'product_slug' => 'reactwoo-geo-optimise',
);
$GLOBALS['rw_is_admin']   = true;
$GLOBALS['rw_is_cli']     = false;
$GLOBALS['rw_doing_cron'] = false;
$GLOBALS['rw_doing_ajax'] = false;
$GLOBALS['rw_options']    = array(
	'rwga_settings' => array(
		'reactwoo_license_key' => 'test-license',
	),
);
$_SERVER['PHP_SELF'] = '/wp-admin/plugins.php';

if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * Minimal WP_Error.
	 */
	class WP_Error {
		/**
		 * @var string
		 */
		public $code = '';

		/**
		 * @var string
		 */
		public $message = '';

		/**
		 * @param string $code Code.
		 * @param string $message Message.
		 * @param mixed  $data Data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			unset( $data );
		}

		/**
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		unset( $domain );
		return $text;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) { // phpcs:ignore
		unset( $args );
		if ( 'rwga_platform_login_is_cli' === $hook || 'rwgo_platform_login_is_cli' === $hook ) {
			return ! empty( $GLOBALS['rw_is_cli'] );
		}
		return $value;
	}
}
if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * @return bool
	 */
	function is_admin() {
		return ! empty( $GLOBALS['rw_is_admin'] );
	}
}
if ( ! function_exists( 'wp_doing_cron' ) ) {
	/**
	 * @return bool
	 */
	function wp_doing_cron() {
		return ! empty( $GLOBALS['rw_doing_cron'] );
	}
}
if ( ! function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * @return bool
	 */
	function wp_doing_ajax() {
		return ! empty( $GLOBALS['rw_doing_ajax'] );
	}
}
if ( ! function_exists( 'get_locale' ) ) {
	/**
	 * @return string
	 */
	function get_locale() {
		return 'en_US';
	}
}
if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * @param array<string, mixed>|string $args Args.
	 * @param string                      $url URL.
	 * @return string
	 */
	function add_query_arg( $args, $url = '' ) {
		if ( ! is_array( $args ) ) {
			return (string) $url;
		}
		return (string) $url . '?' . http_build_query( $args );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string|false
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}
if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url URL.
	 * @param int    $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}
if ( ! function_exists( 'home_url' ) ) {
	/**
	 * @return string
	 */
	function home_url() {
		return 'https://shop.example/';
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * @param string $value Value.
	 * @return string
	 */
	function untrailingslashit( $value ) {
		return rtrim( (string) $value, "/\\" );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, $GLOBALS['rw_options'] ) ? $GLOBALS['rw_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	function update_option( $key, $value, $autoload = null ) {
		unset( $autoload );
		$GLOBALS['rw_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_option( $key ) {
		unset( $GLOBALS['rw_options'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return array_key_exists( $key, $GLOBALS['rw_transients'] ) ? $GLOBALS['rw_transients'][ $key ]['value'] : false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl TTL.
	 * @return bool
	 */
	function set_transient( $key, $value, $ttl = 0 ) {
		$GLOBALS['rw_transients'][ $key ] = array(
			'value' => $value,
			'ttl'   => (int) $ttl,
		);
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_transient( $key ) {
		unset( $GLOBALS['rw_transients'][ $key ] );
		return true;
	}
}
if ( ! function_exists( 'get_site_transient' ) ) {
	/**
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_site_transient( $key ) {
		if ( 'update_plugins' !== $key ) {
			return false;
		}
		return $GLOBALS['rw_site'];
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * @param mixed $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) {
		return ( is_array( $response ) && isset( $response['response']['code'] ) ) ? (int) $response['response']['code'] : 0;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * @param mixed $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) {
		return ( is_array( $response ) && isset( $response['body'] ) ) ? (string) $response['body'] : '';
	}
}
/**
 * Unsigned JWT for entitlement checks. The signature is not verified.
 *
 * @param array<string, mixed> $claims Claims.
 * @return string
 */
function rwgo_login_jwt( $claims ) {
	$segment = static function ( $data ) {
		return rtrim( strtr( base64_encode( (string) wp_json_encode( $data ) ), '+/', '-_' ), '=' );
	};
	return $segment( array( 'alg' => 'none', 'typ' => 'JWT' ) ) . '.' . $segment( $claims ) . '.x';
}

/**
 * @return string
 */
function rwgo_login_token() {
	$claims = isset( $GLOBALS['rw_token_claims'] ) && is_array( $GLOBALS['rw_token_claims'] )
		? $GLOBALS['rw_token_claims']
		: array( 'packageType' => 'reactwoo-geo-optimise' );
	return rwgo_login_jwt( $claims );
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * @param string $url URL.
	 * @param array  $args Args.
	 * @return array<string, mixed>|\WP_Error
	 */
	function wp_remote_post( $url, $args = array() ) {
		$GLOBALS['rw_posts'][] = array(
			'url'  => $url,
			'args' => $args,
		);
		if ( false !== strpos( $url, '/auth/login' ) && 'fail' === $GLOBALS['rw_login_mode'] ) {
			return new WP_Error( 'http_request_failed', 'login timed out' );
		}
		if ( false !== strpos( $url, '/auth/login' ) && 'http-error' === $GLOBALS['rw_login_mode'] ) {
			return array(
				'response' => array( 'code' => 403 ),
				'body'     => (string) wp_json_encode(
					array(
						'message' => isset( $GLOBALS['rw_login_http_message'] ) ? (string) $GLOBALS['rw_login_http_message'] : 'package mismatch',
					)
				),
			);
		}
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => (string) wp_json_encode(
				array(
					'access_token' => rwgo_login_token(),
					'expires_in'   => 3600,
				)
			),
		);
	}
}

/**
 * Settings stub so the Optimise client can read a license key.
 */
class RWGO_Settings {
	/**
	 * @return array<string, string>
	 */
	public static function get_settings() {
		return array(
			'reactwoo_license_key' => 'optimise-license',
		);
	}
}

$wpdb          = new stdClass();
$GLOBALS['wpdb'] = $wpdb;

require_once dirname( __DIR__ ) . '/merged-geo-ai/includes/class-rwga-platform-client.php';
require_once dirname( __DIR__ ) . '/includes/class-rwgo-platform-client.php';

/**
 * @param string $label Label.
 * @param bool   $ok Condition.
 * @return void
 */
function rwgo_login_assert( $label, $ok ) {
	if ( $ok ) {
		echo "ok  - {$label}\n";
		return;
	}
	echo "FAIL - {$label}\n";
	++$GLOBALS['rw_failed'];
}

/**
 * @return int
 */
function rwgo_login_posts() {
	return count( $GLOBALS['rw_posts'] );
}

/**
 * @return array<string, mixed>|null
 */
function rwgo_login_last_post() {
	if ( array() === $GLOBALS['rw_posts'] ) {
		return null;
	}
	return $GLOBALS['rw_posts'][ count( $GLOBALS['rw_posts'] ) - 1 ];
}

/**
 * @param string $key Transient key.
 * @return int|null
 */
function rwgo_login_ttl( $key ) {
	if ( ! isset( $GLOBALS['rw_transients'][ $key ] ) ) {
		return null;
	}
	return (int) $GLOBALS['rw_transients'][ $key ]['ttl'];
}

rwgo_login_assert(
	'Geo AI bearer callback accepts the timeout Geo Core passes',
	1 === ( new ReflectionMethod( 'RWGA_Platform_Client', 'get_bearer_for_updates' ) )->getNumberOfParameters()
);
rwgo_login_assert(
	'Geo Optimise bearer callback accepts the timeout Geo Core passes',
	1 === ( new ReflectionMethod( 'RWGO_Platform_Client', 'get_bearer_for_updates' ) )->getNumberOfParameters()
);

$stored                 = new stdClass();
$stored->last_checked   = 10;
$stored->checked        = array( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' => '0.5.1' );
$stored->response       = array( 'keep' => 'yes' );
$incoming               = new stdClass();
$incoming->last_checked = 99;
$incoming->checked      = array( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' => '0.5.1' );
$incoming->response     = array( 'keep' => 'yes' );
$GLOBALS['rw_site']     = $stored;
$before                 = rwgo_login_posts();
$skipped                = RWGA_Platform_Client::prime_jwt_before_plugins_transient( $incoming );
rwgo_login_assert( 'a save that only changes last_checked makes no login call', rwgo_login_posts() === $before );
rwgo_login_assert( 'that save returns the transient unchanged', $skipped === $incoming );

$same               = new stdClass();
$same->last_checked = 1;
$same->checked      = array( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' => '0.5.1' );
$same->response     = array();
$GLOBALS['rw_site'] = $same;
$same->last_checked = 2;
$before             = rwgo_login_posts();
RWGA_Platform_Client::prime_jwt_before_plugins_transient( $same );
rwgo_login_assert( 'WordPress mutating last_checked on the stored object makes no login call', rwgo_login_posts() === $before );

$changed               = new stdClass();
$changed->last_checked = 500;
$changed->checked      = array( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' => '0.5.1' );
$changed->response     = array( 'fresh' => 'yes' );
$before                = rwgo_login_posts();
$primed                = RWGA_Platform_Client::prime_jwt_before_plugins_transient( $changed );
$last                  = rwgo_login_last_post();
rwgo_login_assert( 'a real plugins transient save logs in once', $primed === $changed && rwgo_login_posts() === $before + 1 );
rwgo_login_assert(
	'that login times out at 10 seconds',
	is_array( $last ) && isset( $last['args']['timeout'] ) && 10 === (int) $last['args']['timeout']
);
$before = rwgo_login_posts();
RWGA_Platform_Client::prime_jwt_before_plugins_transient( $changed );
rwgo_login_assert( 'a cached JWT is reused for the next save', rwgo_login_posts() === $before );

delete_transient( RWGA_Platform_Client::TOKEN_TRANSIENT );
$before = rwgo_login_posts();
RWGA_Platform_Client::maybe_warm_access_token_for_updates();
$warm = rwgo_login_last_post();
rwgo_login_assert(
	'the plugins-screen warm-up logs in with a 10 second timeout',
	rwgo_login_posts() === $before + 1 && is_array( $warm ) && 10 === (int) $warm['args']['timeout']
);
delete_transient( RWGA_Platform_Client::TOKEN_TRANSIENT );
$before = rwgo_login_posts();
RWGA_Platform_Client::maybe_warm_access_token_for_updates();
rwgo_login_assert( 'the warm-up runs once per request', rwgo_login_posts() === $before );

delete_transient( RWGA_Platform_Client::TOKEN_TRANSIENT );
$GLOBALS['rw_login_mode'] = 'fail';
$before                   = rwgo_login_posts();
RWGA_Platform_Client::prime_jwt_before_plugins_transient( $changed );
$failed                   = rwgo_login_last_post();
rwgo_login_assert(
	'a failed plugins-screen login uses a 10 second timeout',
	rwgo_login_posts() === $before + 1 && is_array( $failed ) && 10 === (int) $failed['args']['timeout']
);
rwgo_login_assert(
	'a failed login is cached for 15 minutes',
	900 === rwgo_login_ttl( RWGA_Platform_Client::UPDATE_LOGIN_FAIL_TRANSIENT )
);
$before = rwgo_login_posts();
RWGA_Platform_Client::prime_jwt_before_plugins_transient( $changed );
$bearer = RWGA_Platform_Client::get_bearer_for_updates( 10 );
rwgo_login_assert( 'the cached login failure is not attempted again', rwgo_login_posts() === $before && null === $bearer );
rwgo_login_assert(
	'the diagnostics transient records the cached failure',
	isset( $GLOBALS['rw_transients'][ RWGA_Platform_Client::BEARER_ERROR_TRANSIENT ]['value']['code'] )
	&& 'rwga_login_cached_failure' === $GLOBALS['rw_transients'][ RWGA_Platform_Client::BEARER_ERROR_TRANSIENT ]['value']['code']
);

$before = rwgo_login_posts();
$direct = RWGA_Platform_Client::get_access_token();
$via_request = RWGA_Platform_Client::request( 'GET', '/api/v5/ai/assistant/usage', null, true );
rwgo_login_assert(
	'API callers share the failed-login cache',
	is_wp_error( $direct ) && is_wp_error( $via_request ) && rwgo_login_posts() === $before
);
$fail_until = (int) get_option( RWGA_Platform_Client::UPDATE_LOGIN_FAIL_OPTION, 0 );
rwgo_login_assert(
	'the failed-login option lasts 900 seconds',
	$fail_until >= time() + 890 && $fail_until <= time() + 900
);
rwgo_login_assert(
	'the diagnostics failure flag also lasts 900 seconds',
	900 === rwgo_login_ttl( RWGA_Platform_Client::BEARER_ERROR_TRANSIENT )
);

RWGA_Platform_Client::clear_token_cache();
$before = rwgo_login_posts();
RWGA_Platform_Client::get_bearer_for_updates( 10 );
rwgo_login_assert( 'clearing the token cache allows the update path to log in again', rwgo_login_posts() === $before + 1 );
rwgo_login_assert(
	'that retry is cached for 15 minutes again',
	900 === rwgo_login_ttl( RWGA_Platform_Client::UPDATE_LOGIN_FAIL_TRANSIENT )
);

$GLOBALS['rw_login_mode'] = 'token';
RWGA_Platform_Client::clear_token_cache();
$before = rwgo_login_posts();
$token  = RWGA_Platform_Client::get_bearer_for_updates( 10 );
$ok     = rwgo_login_last_post();
rwgo_login_assert(
	'a bearer callback receives the 10 second login timeout',
	rwgo_login_token() === $token && rwgo_login_posts() === $before + 1 && is_array( $ok ) && 10 === (int) $ok['args']['timeout']
);
rwgo_login_assert(
	'a successful update login clears the failure cache',
	null === rwgo_login_ttl( RWGA_Platform_Client::UPDATE_LOGIN_FAIL_TRANSIENT )
);

delete_transient( RWGO_Platform_Client::TOKEN_TRANSIENT );
$GLOBALS['rw_login_mode'] = 'fail';
$before                   = rwgo_login_posts();
$optimise                 = RWGO_Platform_Client::get_bearer_for_updates( 10 );
$optimise_post            = rwgo_login_last_post();
rwgo_login_assert(
	'the Optimise bearer callback uses a 10 second timeout',
	null === $optimise && rwgo_login_posts() === $before + 1 && is_array( $optimise_post ) && 10 === (int) $optimise_post['args']['timeout']
);
rwgo_login_assert(
	'the Optimise bearer caches a failed login for 15 minutes',
	900 === rwgo_login_ttl( RWGO_Platform_Client::UPDATE_LOGIN_FAIL_TRANSIENT )
);
$before = rwgo_login_posts();
RWGO_Platform_Client::get_bearer_for_updates();
rwgo_login_assert( 'the Optimise bearer does not retry a cached failure', rwgo_login_posts() === $before );

$GLOBALS['rw_login_mode'] = 'token';
RWGO_Platform_Client::clear_token_cache();
$before = rwgo_login_posts();
$direct = RWGO_Platform_Client::get_access_token();
$direct_post = rwgo_login_last_post();
rwgo_login_assert(
	'Optimise API calls outside an admin action use the 10 second timeout',
	rwgo_login_token() === $direct && rwgo_login_posts() === $before + 1 && is_array( $direct_post ) && 10 === (int) $direct_post['args']['timeout']
);
$direct_body = array();
if ( is_array( $direct_post ) && isset( $direct_post['args']['body'] ) ) {
	$decoded = json_decode( (string) $direct_post['args']['body'], true );
	$direct_body = is_array( $decoded ) ? $decoded : array();
}
rwgo_login_assert(
	'Optimise login sends package_type with the product slug',
	isset( $direct_body['package_type'], $direct_body['product_slug'], $direct_body['catalog_slug'] )
	&& 'reactwoo-geo-optimise' === $direct_body['package_type']
	&& 'reactwoo-geo-optimise' === $direct_body['product_slug']
	&& 'reactwoo-geo-optimise' === $direct_body['catalog_slug']
);

$_SERVER['PHP_SELF'] = '/wp-admin/admin-post.php';
RWGA_Platform_Client::clear_token_cache();
$GLOBALS['rw_login_mode'] = 'fail';
$before = rwgo_login_posts();
$posted = RWGA_Platform_Client::get_access_token( 30 );
$posted_call = rwgo_login_last_post();
rwgo_login_assert(
	'an admin-post action may use the 30 second login timeout',
	is_wp_error( $posted ) && rwgo_login_posts() === $before + 1 && is_array( $posted_call ) && 30 === (int) $posted_call['args']['timeout']
);
$_SERVER['PHP_SELF'] = '/wp-admin/plugins.php';

$GLOBALS['rw_is_admin']   = false;
$GLOBALS['rw_doing_cron'] = true;
$GLOBALS['rw_is_cli']     = false;
RWGA_Platform_Client::clear_token_cache();
$before = rwgo_login_posts();
$croned = RWGA_Platform_Client::get_access_token();
$cron_call = rwgo_login_last_post();
rwgo_login_assert(
	'a cron login times out at 10 seconds',
	is_wp_error( $croned ) && rwgo_login_posts() === $before + 1 && is_array( $cron_call ) && 10 === (int) $cron_call['args']['timeout']
);
$GLOBALS['rw_doing_cron'] = false;
$GLOBALS['rw_is_admin']   = true;

if ( ! defined( 'RWGA_VERSION' ) ) {
	define( 'RWGA_VERSION', '0.4.68' );
}
if ( ! defined( 'RWGA_PATH' ) ) {
	define( 'RWGA_PATH', dirname( __DIR__ ) . '/merged-geo-ai/' );
}
require_once dirname( __DIR__ ) . '/merged-geo-ai/includes/services/class-rwga-intelligence-sync-service.php';

RWGA_Platform_Client::clear_token_cache();
delete_option( RWGA_Intelligence_Sync_Service::OPTION_LAST_SYNC );
$GLOBALS['rw_login_mode'] = 'fail';
$GLOBALS['rw_is_admin']   = true;
$GLOBALS['rw_is_cli']     = false;
$before = rwgo_login_posts();
RWGA_Intelligence_Sync_Service::maybe_auto_sync();
RWGA_Intelligence_Sync_Service::maybe_auto_sync();
rwgo_login_assert( 'two runs in a row with a bad key make at most one login', rwgo_login_posts() === $before + 1 );
rwgo_login_assert(
	'that shared failure is stored for 900 seconds',
	900 === rwgo_login_ttl( RWGA_Platform_Client::UPDATE_LOGIN_FAIL_TRANSIENT )
);
$kept_until = (int) get_option( RWGA_Platform_Client::UPDATE_LOGIN_FAIL_OPTION, 0 );
delete_transient( RWGA_Platform_Client::UPDATE_LOGIN_FAIL_TRANSIENT );
$before = rwgo_login_posts();
RWGA_Platform_Client::get_access_token();
RWGA_Platform_Client::request( 'GET', '/api/v1/intelligence/geocore/bundle', null, true );
rwgo_login_assert(
	'a failure flag that leaves the transient early still blocks the next login',
	rwgo_login_posts() === $before && $kept_until > time()
);

$GLOBALS['rw_is_cli']   = true;
$GLOBALS['rw_is_admin'] = true;
RWGA_Platform_Client::clear_token_cache();
RWGO_Platform_Client::clear_token_cache();
$before = rwgo_login_posts();
RWGA_Intelligence_Sync_Service::maybe_auto_sync();
RWGA_Platform_Client::get_access_token();
RWGA_Platform_Client::maybe_warm_access_token_for_updates();
RWGO_Platform_Client::get_access_token();
RWGA_Intelligence_Sync_Service::maybe_auto_sync();
RWGA_Platform_Client::get_access_token();
rwgo_login_assert( 'WP-CLI runs do not log in', rwgo_login_posts() === $before && ! RWGA_Platform_Client::request_may_login() );

$GLOBALS['rw_is_cli']   = false;
$GLOBALS['rw_is_admin'] = false;
RWGA_Platform_Client::clear_token_cache();
$before = rwgo_login_posts();
RWGA_Intelligence_Sync_Service::maybe_auto_sync();
RWGA_Platform_Client::get_access_token();
RWGO_Platform_Client::get_access_token();
rwgo_login_assert( 'ordinary front-end requests do not log in', rwgo_login_posts() === $before && ! RWGO_Platform_Client::request_may_login() );

$GLOBALS['rw_is_admin']     = true;
$GLOBALS['rw_is_cli']       = false;
$GLOBALS['rw_login_mode']   = 'token';
$GLOBALS['rw_token_claims'] = array(
	'packageType'  => 'reactwoo-geocore-pro',
	'product_slug' => 'reactwoo-geo-optimise',
);
RWGO_Platform_Client::clear_token_cache();
$before = rwgo_login_posts();
$pro    = RWGO_Platform_Client::get_access_token();
rwgo_login_assert(
	'a Geo Core Pro token is not stored or treated as an Optimise licence',
	is_wp_error( $pro )
	&& 'rwgo_license_wrong_product' === $pro->get_error_code()
	&& false === get_transient( RWGO_Platform_Client::TOKEN_TRANSIENT )
	&& rwgo_login_posts() === $before + 1
);
rwgo_login_assert(
	'the License screen records the mismatched-key message',
	isset( $GLOBALS['rw_options']['rwgo_license_last_check']['code'] )
	&& 'rwgo_license_wrong_product' === $GLOBALS['rw_options']['rwgo_license_last_check']['code']
	&& false !== strpos( (string) $GLOBALS['rw_options']['rwgo_license_last_check']['error'], 'Geo Core Pro' )
);
$before = rwgo_login_posts();
RWGO_Platform_Client::get_bearer_for_updates();
rwgo_login_assert( 'the updater does not retry a rejected Pro key', rwgo_login_posts() === $before );

$GLOBALS['rw_token_claims'] = array(
	'packageType'   => 'reactwoo-decision-cloud',
	'plan_code'     => 'growth',
	'product_slugs' => array( 'reactwoo-geocore-pro', 'reactwoo-geo-commerce', 'reactwoo-geo-optimise' ),
);
RWGO_Platform_Client::clear_token_cache();
$growth = RWGO_Platform_Client::get_access_token();
rwgo_login_assert(
	'Decision Cloud Growth is accepted when product_slugs includes Geo Optimise',
	is_string( $growth ) && '' !== $growth && RWGO_Platform_Client::token_covers_optimise( $growth )
);

$GLOBALS['rw_token_claims'] = array(
	'packageType'   => 'reactwoo-decision-cloud',
	'plan_code'     => 'starter',
	'product_slugs' => array( 'reactwoo-geocore-pro' ),
);
RWGO_Platform_Client::clear_token_cache();
$starter = RWGO_Platform_Client::get_access_token();
rwgo_login_assert(
	'Decision Cloud Starter is rejected because it does not list Geo Optimise',
	is_wp_error( $starter ) && 'rwgo_license_wrong_product' === $starter->get_error_code()
);

$GLOBALS['rw_login_mode']         = 'http-error';
$GLOBALS['rw_login_http_message'] = 'package type does not match';
RWGO_Platform_Client::clear_token_cache();
$http = RWGO_Platform_Client::get_access_token();
rwgo_login_assert(
	'a server package mismatch uses the same admin message',
	is_wp_error( $http ) && 'rwgo_license_wrong_product' === $http->get_error_code()
);

if ( 0 !== $GLOBALS['rw_failed'] ) {
	fwrite( STDERR, $GLOBALS['rw_failed'] . " failed\n" );
	exit( 1 );
}
echo "update login checks passed\n";
exit( 0 );
