<?php
/**
 * Cloud API client for Geo AI site registration and intelligence snapshots.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST site intelligence payloads to api.reactwoo.com (Phase 5 API contract).
 */
class RWGA_Site_Snapshot_Client {

	const REGISTER_PATH_TEMPLATE = '/api/v5/geo-ai/sites/register';
	const SNAPSHOT_PATH_TEMPLATE = '/api/v5/geo-ai/sites/%s/snapshot';

	/**
	 * Register or resolve a cloud site id for this WordPress install.
	 *
	 * @return array{site_id:string}|\WP_Error
	 */
	public static function register_site() {
		if ( ! class_exists( 'RWGA_Platform_Client', false ) ) {
			return new WP_Error( 'rwga_no_platform', __( 'Platform client unavailable.', 'reactwoo-geo-ai' ) );
		}

		$site_uuid = function_exists( 'rwga_get_site_uuid' ) ? (string) rwga_get_site_uuid() : '';

		$body = array(
			'site' => array(
				'uuid' => $site_uuid,
				'url'  => home_url( '/' ),
				'name' => get_bloginfo( 'name' ),
			),
			'product_slug' => RWGA_Platform_Client::PRODUCT_SLUG,
		);

		$path = apply_filters( 'rwga_site_snapshot_register_path', self::REGISTER_PATH_TEMPLATE );
		$path = is_string( $path ) ? trim( $path ) : self::REGISTER_PATH_TEMPLATE;

		/**
		 * @param array<string, mixed> $body Register request JSON.
		 */
		$body = apply_filters( 'rwga_site_snapshot_register_body', $body );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$result = RWGA_Platform_Client::request( 'POST', $path, $body, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : null;

		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			$msg = self::extract_api_message( $data, __( 'Site registration failed.', 'reactwoo-geo-ai' ) );
			return new WP_Error( 'rwga_register_failed', $msg, array( 'status' => $code, 'data' => $data ) );
		}

		$inner = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : $data;
		$site_id = '';
		if ( isset( $inner['site_id'] ) ) {
			$site_id = sanitize_text_field( (string) $inner['site_id'] );
		} elseif ( isset( $inner['id'] ) ) {
			$site_id = sanitize_text_field( (string) $inner['id'] );
		} elseif ( '' !== $site_uuid ) {
			$site_id = $site_uuid;
		}

		if ( '' === $site_id ) {
			return new WP_Error( 'rwga_register_shape', __( 'Registration response did not include a site id.', 'reactwoo-geo-ai' ) );
		}

		/**
		 * @param array{site_id:string} $parsed Parsed registration response.
		 * @param array<string, mixed>  $data   Raw API JSON.
		 */
		$parsed = apply_filters(
			'rwga_site_snapshot_register_response',
			array( 'site_id' => $site_id ),
			$data
		);

		return is_array( $parsed ) && ! empty( $parsed['site_id'] )
			? array( 'site_id' => (string) $parsed['site_id'] )
			: array( 'site_id' => $site_id );
	}

	/**
	 * Upload a normalized snapshot payload.
	 *
	 * @param string               $site_id  Cloud site id.
	 * @param array<string, mixed> $snapshot Snapshot from {@see rwgc_build_ai_snapshot()}.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function upload_snapshot( $site_id, array $snapshot ) {
		$site_id = sanitize_text_field( (string) $site_id );
		if ( '' === $site_id ) {
			return new WP_Error( 'rwga_bad_site', __( 'Invalid cloud site id.', 'reactwoo-geo-ai' ) );
		}

		if ( ! class_exists( 'RWGA_Platform_Client', false ) ) {
			return new WP_Error( 'rwga_no_platform', __( 'Platform client unavailable.', 'reactwoo-geo-ai' ) );
		}

		$path = sprintf( self::SNAPSHOT_PATH_TEMPLATE, rawurlencode( $site_id ) );
		$path = apply_filters( 'rwga_site_snapshot_upload_path', $path, $site_id, $snapshot );
		$path = is_string( $path ) ? $path : sprintf( self::SNAPSHOT_PATH_TEMPLATE, rawurlencode( $site_id ) );

		$body = array(
			'site_id'        => $site_id,
			'snapshot'       => $snapshot,
			'snapshot_hash'  => isset( $snapshot['snapshot_hash'] ) ? (string) $snapshot['snapshot_hash'] : '',
			'schema_version' => isset( $snapshot['schema_version'] ) ? (int) $snapshot['schema_version'] : 1,
		);

		/**
		 * @param array<string, mixed> $body     Upload request JSON.
		 * @param string               $site_id  Cloud site id.
		 * @param array<string, mixed> $snapshot Snapshot payload.
		 */
		$body = apply_filters( 'rwga_site_snapshot_upload_body', $body, $site_id, $snapshot );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$result = RWGA_Platform_Client::request( 'POST', $path, $body, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : null;

		if ( $code < 200 || $code >= 300 ) {
			$msg  = self::extract_api_message( is_array( $data ) ? $data : null, __( 'Snapshot upload failed.', 'reactwoo-geo-ai' ) );
			$meta = array( 'status' => $code, 'data' => $data );
			if ( is_array( $data ) ) {
				$api_code = isset( $data['code'] ) ? sanitize_key( (string) $data['code'] ) : '';
				if ( 'SNAPSHOT_QUOTA_EXCEEDED' === $api_code ) {
					$quota = isset( $data['quota'] ) && is_array( $data['quota'] ) ? $data['quota'] : null;
					if ( is_array( $quota ) ) {
						$meta['quota'] = $quota;
						$meta['code']  = $api_code;
					}
					return new WP_Error( 'rwga_snapshot_quota_exceeded', $msg, $meta );
				}
			}
			return new WP_Error( 'rwga_upload_failed', $msg, $meta );
		}

		$parsed = array(
			'http_code'      => $code,
			'site_id'        => $site_id,
			'snapshot_hash'  => isset( $snapshot['snapshot_hash'] ) ? (string) $snapshot['snapshot_hash'] : '',
			'response'       => is_array( $data ) ? $data : array(),
		);

		/**
		 * @param array<string, mixed> $parsed   Normalised upload response.
		 * @param array<string, mixed> $data     Raw API JSON.
		 * @param string               $site_id  Cloud site id.
		 */
		$parsed = apply_filters( 'rwga_site_snapshot_upload_response', $parsed, $data, $site_id );
		return is_array( $parsed ) ? $parsed : array();
	}

	/**
	 * @param array<string, mixed>|null $data API JSON body.
	 * @param string                    $fallback Default message.
	 * @return string
	 */
	private static function extract_api_message( $data, $fallback ) {
		if ( ! is_array( $data ) ) {
			return $fallback;
		}
		if ( ! empty( $data['message'] ) && is_string( $data['message'] ) ) {
			return (string) $data['message'];
		}
		if ( isset( $data['data']['message'] ) && is_string( $data['data']['message'] ) ) {
			return (string) $data['data']['message'];
		}
		$code = isset( $data['code'] ) ? sanitize_key( (string) $data['code'] ) : '';
		if ( 'SNAPSHOT_QUOTA_EXCEEDED' === $code ) {
			$quota = isset( $data['quota'] ) && is_array( $data['quota'] ) ? $data['quota'] : null;
			return self::format_snapshot_quota_message( $quota );
		}
		if ( 'RATE_LIMIT_EXCEEDED' === $code ) {
			return __( 'Site intelligence sync rate limit reached. Try again later.', 'reactwoo-geo-ai' );
		}
		if ( 'TIER_REQUIRED' === $code ) {
			return __( 'Site intelligence sync requires a Pro plan or higher.', 'reactwoo-geo-ai' );
		}
		return $fallback;
	}

	/**
	 * @param array<string, mixed>|null $quota API quota payload.
	 * @return string
	 */
	public static function format_snapshot_quota_message( $quota ) {
		if ( ! is_array( $quota ) ) {
			return __( 'Monthly site intelligence upload limit reached for this license.', 'reactwoo-geo-ai' );
		}
		$used  = isset( $quota['used'] ) ? (int) $quota['used'] : 0;
		$limit = isset( $quota['limit'] ) ? (int) $quota['limit'] : 0;
		if ( $limit <= 0 ) {
			return __( 'Monthly site intelligence upload limit is not available on this plan.', 'reactwoo-geo-ai' );
		}
		return sprintf(
			/* translators: 1: uploads used, 2: monthly limit */
			__( 'Monthly site intelligence upload limit reached (%1$d of %2$d used this month).', 'reactwoo-geo-ai' ),
			$used,
			$limit
		);
	}
}
