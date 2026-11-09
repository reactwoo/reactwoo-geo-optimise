<?php
/**
 * Update check used when Geo Core does not provide one.
 *
 * The WordPress.org Geo Core package loads a stub whose register() ignores
 * this plugin. Support Connector checks the catalog itself. Optimise does too,
 * only in that case, so a ReactWoo-channel Geo Core does not check twice.
 *
 * The catalog returns 401 for reactwoo-geo-optimise unless a license token is
 * sent. The token must cover Geo Optimise. A Geo Core Pro token is not used.
 * This class does not publish a download URL of its own.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fallback plugin update check.
 */
class RWGO_Self_Updater {

	const CATALOG_SLUG = 'reactwoo-geo-optimise';

	const CHECK_URL = 'https://api.reactwoo.com/api/v5/updates/check';

	const PACKAGE_PREFIX = 'https://api.reactwoo.com/rwgo-update-placeholder/';

	/**
	 * @param bool $core_filter_hooked Geo Core's real updater filter is already registered.
	 * @return bool
	 */
	public static function should_install_fallback( $core_filter_hooked ) {
		return ! $core_filter_hooked;
	}

	/**
	 * @return void
	 */
	public static function register_fallback() {
		$hooked = false;
		if ( function_exists( 'has_filter' ) && class_exists( 'RWGC_Satellite_Updater', false ) ) {
			$hooked = false !== has_filter( 'pre_set_site_transient_update_plugins', array( 'RWGC_Satellite_Updater', 'filter_update_transient' ) );
		}
		if ( ! self::should_install_fallback( $hooked ) || ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'filter_update_transient' ), 50, 1 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'filter_upgrader_pre_download' ), 10, 4 );
	}

	/**
	 * @param object $stored   Previously saved transient.
	 * @param object $incoming Transient about to be saved.
	 * @return bool
	 */
	public static function is_last_checked_only( $stored, $incoming ) {
		if ( ! is_object( $stored ) || ! is_object( $incoming ) ) {
			return false;
		}
		if ( $stored === $incoming ) {
			return true;
		}
		$left  = clone $stored;
		$right = clone $incoming;
		unset( $left->last_checked, $right->last_checked );
		return $left == $right;
	}

	/**
	 * Decide whether the check response is a newer installable offer.
	 *
	 * @param string $current   Installed version.
	 * @param int    $http_code HTTP status.
	 * @param mixed  $data      Decoded JSON.
	 * @return array{version:string,package:string}|null
	 */
	public static function offer_from_response( $current, $http_code, $data ) {
		if ( 200 !== (int) $http_code || ! is_array( $data ) ) {
			return null;
		}
		if ( empty( $data['update'] ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			return null;
		}
		$version = (string) $data['version'];
		$package = (string) $data['download_url'];
		if ( ! self::package_is_https( $package ) ) {
			return null;
		}
		if ( version_compare( (string) $current, $version, '>=' ) ) {
			return null;
		}
		return array(
			'version' => $version,
			'package' => $package,
		);
	}

	/**
	 * @param object               $transient Update transient.
	 * @param string               $basename  Plugin basename.
	 * @param array<string,string> $offer     Version and package URL.
	 * @return object
	 */
	public static function apply_offer( $transient, $basename, array $offer ) {
		if ( ! is_object( $transient ) || '' === (string) $basename ) {
			return $transient;
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}
		$info              = new stdClass();
		$info->slug        = self::CATALOG_SLUG;
		$info->plugin      = (string) $basename;
		$info->new_version = (string) $offer['version'];
		$info->package     = self::PACKAGE_PREFIX . rawurlencode( self::CATALOG_SLUG )
			. '?version=' . rawurlencode( (string) $offer['version'] )
			. '&current=' . rawurlencode( isset( $offer['current'] ) ? (string) $offer['current'] : '' );
		$transient->response[ (string) $basename ] = $info;
		return $transient;
	}

	/**
	 * @param object $transient Update transient.
	 * @return object
	 */
	public static function filter_update_transient( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}
		if ( function_exists( 'get_site_transient' ) && self::is_last_checked_only( get_site_transient( 'update_plugins' ), $transient ) ) {
			return $transient;
		}
		$basename = defined( 'RWGO_FILE' ) ? plugin_basename( RWGO_FILE ) : 'reactwoo-geo-optimise/reactwoo-geo-optimise.php';
		if ( ! isset( $transient->checked[ $basename ] ) ) {
			return $transient;
		}
		$current = (string) $transient->checked[ $basename ];
		$offer   = self::request_offer( $current );
		if ( null === $offer ) {
			return $transient;
		}
		$offer['current'] = $current;
		return self::apply_offer( $transient, $basename, $offer );
	}

	/**
	 * @param mixed  $reply   Short-circuit result.
	 * @param string $package Package URL from the transient.
	 * @return mixed
	 */
	public static function filter_upgrader_pre_download( $reply, $package ) {
		if ( false !== $reply || ! is_string( $package ) || 0 !== strpos( $package, self::PACKAGE_PREFIX ) ) {
			return $reply;
		}
		$current = '';
		$parts   = explode( '?', $package, 2 );
		if ( isset( $parts[1] ) ) {
			$query = array();
			parse_str( (string) $parts[1], $query );
			if ( isset( $query['current'] ) && is_scalar( $query['current'] ) ) {
				$current = (string) $query['current'];
			}
		}
		$offer = self::request_offer( $current );
		if ( null === $offer || ! self::package_is_https( $offer['package'] ) || ! function_exists( 'download_url' ) ) {
			return $reply;
		}
		return download_url( $offer['package'], 300 );
	}

	/**
	 * Signed catalog URLs must be https and must not carry the license token.
	 *
	 * @param string $package Download URL.
	 * @return bool
	 */
	public static function package_is_https( $package ) {
		if ( ! is_string( $package ) || ! preg_match( '#^https://#i', $package ) ) {
			return false;
		}
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $package ) : parse_url( $package );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return false;
		}
		$haystack = strtolower( $package );
		if ( false !== strpos( $haystack, 'bearer ' ) || false !== strpos( $haystack, 'license_key' ) || false !== strpos( $haystack, 'access_token' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $current Installed version.
	 * @return array{version:string,package:string}|null
	 */
	public static function request_offer( $current ) {
		$bearer = self::bearer();
		if ( '' === $bearer ) {
			return null;
		}
		$body = array(
			'slug'            => self::CATALOG_SLUG,
			'current_version' => (string) $current,
			'channel'         => 'stable',
			'site_host'       => self::site_host(),
		);
		$forced = apply_filters( 'rwgo_self_updater_http', null, $body );
		if ( is_array( $forced ) && isset( $forced['code'] ) ) {
			return self::offer_from_response( $current, (int) $forced['code'], isset( $forced['data'] ) ? $forced['data'] : null );
		}
		if ( ! function_exists( 'wp_remote_post' ) ) {
			return null;
		}
		$response = wp_remote_post(
			self::CHECK_URL,
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $bearer,
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return self::offer_from_response( $current, $code, $data );
	}

	/**
	 * @return string
	 */
	private static function bearer() {
		$forced = apply_filters( 'rwgo_self_updater_bearer', null );
		if ( is_string( $forced ) ) {
			return self::entitled_bearer( $forced );
		}
		if ( ! class_exists( 'RWGO_Platform_Client', false ) ) {
			return '';
		}
		$token = RWGO_Platform_Client::get_bearer_for_updates();
		return self::entitled_bearer( is_string( $token ) ? $token : '' );
	}

	/**
	 * Update checks run only with a token whose package or product_slugs covers Geo Optimise.
	 *
	 * @param string $token Bearer.
	 * @return string
	 */
	private static function entitled_bearer( $token ) {
		if ( ! is_string( $token ) || '' === $token || ! class_exists( 'RWGO_Platform_Client', false ) ) {
			return '';
		}
		if ( ! RWGO_Platform_Client::token_covers_optimise( $token ) ) {
			return '';
		}
		return $token;
	}

	/**
	 * @return string
	 */
	private static function site_host() {
		if ( ! function_exists( 'home_url' ) ) {
			return 'localhost';
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return is_string( $host ) && '' !== $host ? $host : 'localhost';
	}
}
