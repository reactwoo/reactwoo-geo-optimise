<?php
/**
 * Thin React Cloud client for `/geo-api/v1/google/*` (GTM live push).
 * Tokens stay on cloud; WordPress only sends license JWT + packs.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Geo Cloud HTTP client.
 */
class RWGO_Cloud_Client {

	const DEFAULT_GEO_SERVICE_BASE = 'https://cloud.reactwoo.com';

	/**
	 * @return string
	 */
	public static function get_geo_service_base() {
		if ( defined( 'RWGO_CLOUD_BASE' ) && is_string( RWGO_CLOUD_BASE ) ) {
			$base = trim( (string) RWGO_CLOUD_BASE );
			if ( '' !== $base && function_exists( 'wp_http_validate_url' ) && wp_http_validate_url( $base ) ) {
				return untrailingslashit( esc_url_raw( $base ) );
			}
		}
		$filtered = apply_filters( 'rwgo_cloud_base', self::DEFAULT_GEO_SERVICE_BASE );
		$filtered = is_string( $filtered ) ? trim( $filtered ) : self::DEFAULT_GEO_SERVICE_BASE;
		if ( '' !== $filtered && function_exists( 'wp_http_validate_url' ) && wp_http_validate_url( $filtered ) ) {
			return untrailingslashit( esc_url_raw( $filtered ) );
		}
		return self::DEFAULT_GEO_SERVICE_BASE;
	}

	/**
	 * @param string               $method  HTTP method.
	 * @param string               $path    Path under cloud origin (e.g. /geo-api/v1/google/gtm/status).
	 * @param array<string, mixed> $payload Query (GET) or JSON body (POST).
	 * @param int                  $timeout Seconds.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function request( $method, $path, array $payload = array(), $timeout = 45 ) {
		if ( ! class_exists( 'RWGO_Platform_Client', false ) ) {
			return new WP_Error( 'rwgo_cloud_no_platform', __( 'License client unavailable.', 'reactwoo-geo-optimise' ) );
		}
		$token = RWGO_Platform_Client::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		if ( ! is_string( $token ) || '' === $token ) {
			return new WP_Error( 'rwgo_cloud_no_token', __( 'Could not obtain a license token for React Cloud.', 'reactwoo-geo-optimise' ) );
		}

		$norm_path = '/' . ltrim( (string) $path, '/' );
		$url       = untrailingslashit( self::get_geo_service_base() ) . $norm_path;
		$method_u  = strtoupper( (string) $method );
		$timeout   = max( 15, (int) $timeout );
		$args      = array(
			'timeout' => $timeout,
			'headers' => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $token,
			),
		);

		if ( 'GET' === $method_u ) {
			$url            = add_query_arg( $payload, $url );
			$args['method'] = 'GET';
			$raw            = wp_remote_request( $url, $args );
		} else {
			$args['method']                    = 'POST';
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                      = wp_json_encode( $payload );
			$raw                               = wp_remote_request( $url, $args );
		}

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$code = (int) wp_remote_retrieve_response_code( $raw );
		$body = (string) wp_remote_retrieve_body( $raw );
		$data = json_decode( $body, true );
		if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
			return $data;
		}

		$msg = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : __( 'React Cloud request failed.', 'reactwoo-geo-optimise' );
		return new WP_Error( 'rwgo_cloud_http', $msg, array( 'status' => $code, 'body' => $data ) );
	}

	/**
	 * @param array<string, mixed> $payload Auth URL payload.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function google_auth_url( array $payload ) {
		return self::request( 'POST', '/geo-api/v1/google/auth-url', $payload );
	}

	/**
	 * Finalize OAuth after cloud redirect (no code on WP).
	 *
	 * @param array<string, mixed> $payload { state }.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function google_finalize_token( array $payload ) {
		return self::request( 'POST', '/geo-api/v1/google/token', $payload );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function gtm_status() {
		return self::request( 'GET', '/geo-api/v1/google/gtm/status' );
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function gtm_accounts() {
		return self::request( 'GET', '/geo-api/v1/google/gtm/accounts' );
	}

	/**
	 * @param string $account_id Account id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function gtm_containers( $account_id ) {
		return self::request(
			'GET',
			'/geo-api/v1/google/gtm/containers',
			array( 'account_id' => (string) $account_id )
		);
	}

	/**
	 * @param string $account_id   Account id.
	 * @param string $container_id Container id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function gtm_workspaces( $account_id, $container_id ) {
		return self::request(
			'GET',
			'/geo-api/v1/google/gtm/workspaces',
			array(
				'account_id'   => (string) $account_id,
				'container_id' => (string) $container_id,
			)
		);
	}

	/**
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function gtm_provision( array $payload ) {
		// Sequential GTM API creates often exceed the default 45s HTTP timeout.
		return self::request( 'POST', '/geo-api/v1/google/gtm/provision', $payload, 180 );
	}

	/**
	 * GA4 web-stream measurement IDs from React Cloud (same OAuth as GeoCore Pro Targeting).
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function ga_measurement_ids() {
		return self::request( 'GET', '/geo-api/v1/google/analytics/measurement-ids', array(), 60 );
	}
}
