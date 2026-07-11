<?php
/**
 * Cloud API client for intelligence run history and relationship graph.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET intelligence runs / graph from api.reactwoo.com (Phase 8 API contract).
 */
class RWGA_Intelligence_Cloud_Client {

	/**
	 * Resolve cloud site id from sync status.
	 *
	 * @return string
	 */
	public static function get_cloud_site_id() {
		if ( ! class_exists( 'RWGA_Site_Intelligence_Sync', false ) ) {
			return '';
		}
		$status = RWGA_Site_Intelligence_Sync::get_status();
		return is_array( $status ) && ! empty( $status['cloud_site_id'] )
			? sanitize_text_field( (string) $status['cloud_site_id'] )
			: '';
	}

	/**
	 * @param string $site_id Cloud site id.
	 * @param int    $limit   Max runs.
	 * @return array{runs:array<int,array<string,mixed>>}|\WP_Error
	 */
	public static function list_runs( $site_id, $limit = 20 ) {
		$site_id = sanitize_text_field( (string) $site_id );
		if ( '' === $site_id ) {
			return new WP_Error( 'rwga_no_site', __( 'No cloud site id. Sync site intelligence first.', 'reactwoo-geo-ai' ) );
		}

		$limit = max( 1, min( 50, (int) $limit ) );
		$path  = sprintf(
			'/api/v5/geo-ai/sites/%s/intelligence/runs?limit=%d',
			rawurlencode( $site_id ),
			$limit
		);
		$path = apply_filters( 'rwga_intelligence_cloud_runs_path', $path, $site_id, $limit );

		return self::unwrap_data( self::get( $path ), __( 'Could not load cloud intelligence runs.', 'reactwoo-geo-ai' ) );
	}

	/**
	 * @param string $site_id Cloud site id.
	 * @param string $run_id  Run uuid.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_run( $site_id, $run_id ) {
		$site_id = sanitize_text_field( (string) $site_id );
		$run_id  = sanitize_text_field( (string) $run_id );
		if ( '' === $site_id || '' === $run_id ) {
			return new WP_Error( 'rwga_bad_run', __( 'Invalid run request.', 'reactwoo-geo-ai' ) );
		}

		$path = sprintf(
			'/api/v5/geo-ai/sites/%s/intelligence/runs/%s',
			rawurlencode( $site_id ),
			rawurlencode( $run_id )
		);
		$path = apply_filters( 'rwga_intelligence_cloud_run_path', $path, $site_id, $run_id );

		$result = self::unwrap_data( self::get( $path ), __( 'Intelligence run not found.', 'reactwoo-geo-ai' ) );
		return $result;
	}

	/**
	 * @param string $site_id Cloud site id.
	 * @return array{graph:array<string,mixed>}|array<string,mixed>|\WP_Error
	 */
	public static function get_graph( $site_id ) {
		$site_id = sanitize_text_field( (string) $site_id );
		if ( '' === $site_id ) {
			return new WP_Error( 'rwga_no_site', __( 'No cloud site id. Sync site intelligence first.', 'reactwoo-geo-ai' ) );
		}

		$path = sprintf(
			'/api/v5/geo-ai/sites/%s/intelligence/graph',
			rawurlencode( $site_id )
		);
		$path = apply_filters( 'rwga_intelligence_cloud_graph_path', $path, $site_id );

		return self::unwrap_data( self::get( $path ), __( 'Could not load site relationship graph.', 'reactwoo-geo-ai' ) );
	}

	/**
	 * @param string $path API path.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function get( $path ) {
		if ( ! class_exists( 'RWGA_Platform_Client', false ) ) {
			return new WP_Error( 'rwga_no_platform', __( 'Platform client unavailable.', 'reactwoo-geo-ai' ) );
		}

		$result = RWGA_Platform_Client::request( 'GET', $path, null, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : null;

		if ( $code < 200 || $code >= 300 ) {
			$msg = __( 'Cloud intelligence request failed.', 'reactwoo-geo-ai' );
			if ( is_array( $data ) && ! empty( $data['message'] ) ) {
				$msg = (string) $data['message'];
			}
			return new WP_Error( 'rwga_cloud_failed', $msg, array( 'status' => $code, 'data' => $data ) );
		}

		return is_array( $data ) ? $data : array();
	}

	/**
	 * @param array<string, mixed>|\WP_Error $response API response.
	 * @param string                         $fallback Error message.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function unwrap_data( $response, $fallback ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			return $response['data'];
		}
		if ( ! empty( $response ) ) {
			return $response;
		}
		return new WP_Error( 'rwga_cloud_empty', $fallback );
	}
}
