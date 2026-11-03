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
$GLOBALS['rw_login_mode'] = 'token';

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
	function apply_filters( $hook, $value ) { // phpcs:ignore
		unset( $hook );
		return $value;
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
		if ( 'rwga_settings' === $key ) {
			return array(
				'reactwoo_license_key' => 'test-license',
			);
		}
		return $default;
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
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => (string) wp_json_encode(
				array(
					'access_token' => 'jwt-from-login',
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
$direct_post = rwgo_login_last_post();
rwgo_login_assert(
	'API calls outside the update path still use the 30 second login timeout',
	is_wp_error( $direct ) && rwgo_login_posts() === $before + 1 && is_array( $direct_post ) && 30 === (int) $direct_post['args']['timeout']
);
rwgo_login_assert(
	'an API login failure does not clear the update-path cache',
	900 === rwgo_login_ttl( RWGA_Platform_Client::UPDATE_LOGIN_FAIL_TRANSIENT )
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
	'jwt-from-login' === $token && rwgo_login_posts() === $before + 1 && is_array( $ok ) && 10 === (int) $ok['args']['timeout']
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
	'Optimise API calls outside the update path keep the 30 second timeout',
	'jwt-from-login' === $direct && rwgo_login_posts() === $before + 1 && is_array( $direct_post ) && 30 === (int) $direct_post['args']['timeout']
);

if ( 0 !== $GLOBALS['rw_failed'] ) {
	fwrite( STDERR, $GLOBALS['rw_failed'] . " failed\n" );
	exit( 1 );
}
echo "update login checks passed\n";
exit( 0 );
