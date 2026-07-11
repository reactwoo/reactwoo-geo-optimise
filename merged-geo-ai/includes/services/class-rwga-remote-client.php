<?php
/**
 * Remote workflow dispatch via Geo AI-owned platform JWT.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST workflow payload to api.reactwoo.com (or filtered base).
 */
class RWGA_Remote_Client {

	/**
	 * Default API path (appended to {@see RWGA_Platform_Client::get_api_base()}).
	 */
	const DEFAULT_PATH = '/api/v5/geo-ai/workflow';

	/**
	 * @var array<string, mixed>|null
	 */
	private static $last_dispatch_meta = null;

	/**
	 * Execute a workflow on the remote engine.
	 *
	 * On success returns array with keys: remote_run_id (string, may be empty), engine_response (array).
	 *
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $payload      Sanitised workflow input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function dispatch( $workflow_key, array $payload ) {
		$workflow_key = sanitize_key( (string) $workflow_key );
		if ( '' === $workflow_key ) {
			return new WP_Error( 'rwga_bad_workflow', __( 'Invalid workflow key.', 'reactwoo-geo-ai' ), array( 'status' => 400 ) );
		}

		self::$last_dispatch_meta = null;
		$route                  = class_exists( 'RWGA_Model_Router', false ) ? RWGA_Model_Router::resolve( $workflow_key ) : array();

		if ( class_exists( 'RWGA_Insight_Memory', false ) ) {
			$cached = RWGA_Insight_Memory::lookup( $workflow_key, $payload, $route );
			if ( is_array( $cached ) ) {
				self::$last_dispatch_meta = array(
					'cache_hit'   => true,
					'model_route' => isset( $cached['model_route'] ) && is_array( $cached['model_route'] ) ? $cached['model_route'] : $route,
				);
				return $cached;
			}
		}

		if ( ! class_exists( 'RWGA_Platform_Client', false ) ) {
			return new WP_Error(
				'rwga_no_platform',
				__( 'Geo AI platform client is required for remote workflows.', 'reactwoo-geo-ai' ),
				array( 'status' => 500 )
			);
		}

		$path = apply_filters( 'rwga_remote_workflow_path', self::DEFAULT_PATH, $workflow_key, $payload );
		$path = is_string( $path ) ? trim( $path ) : self::DEFAULT_PATH;
		if ( '' === $path || '/' !== $path[0] ) {
			return new WP_Error( 'rwga_bad_path', __( 'Invalid remote workflow path.', 'reactwoo-geo-ai' ), array( 'status' => 500 ) );
		}

		$site_uuid = function_exists( 'rwga_get_site_uuid' ) ? (string) rwga_get_site_uuid() : '';
		$cloud_site_id = '';
		if ( class_exists( 'RWGA_Site_Intelligence_Sync', false ) ) {
			$sync_status = RWGA_Site_Intelligence_Sync::get_status();
			if ( is_array( $sync_status ) && ! empty( $sync_status['cloud_site_id'] ) ) {
				$cloud_site_id = sanitize_text_field( (string) $sync_status['cloud_site_id'] );
			}
		}

		$payload_for_api = $payload;
		if ( class_exists( 'RWGA_Model_Router', false ) && array() !== $route ) {
			$payload_for_api['model_routing'] = RWGA_Model_Router::for_api( $route );
		}

		$body = array(
			'workflow_key' => $workflow_key,
			'payload'      => $payload_for_api,
			'site'         => array(
				'uuid'    => $site_uuid,
				'url'     => home_url( '/' ),
				'site_id' => $cloud_site_id,
			),
		);

		/**
		 * Filter JSON body for POST /api/v5/geo-ai/workflow (or filtered path).
		 *
		 * @param array<string, mixed> $body         Request body.
		 * @param string               $workflow_key Workflow key.
		 * @param array<string, mixed> $payload      Original payload.
		 */
		$body = apply_filters( 'rwga_remote_workflow_body', $body, $workflow_key, $payload );
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		$result = RWGA_Platform_Client::request( 'POST', $path, $body, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : null;

		if ( $code < 200 || $code >= 300 || null === $data ) {
			$msg = is_array( $data ) && isset( $data['message'] ) ? (string) $data['message'] : __( 'Remote workflow request failed.', 'reactwoo-geo-ai' );
			return new WP_Error( 'rwga_remote_failed', $msg, array( 'status' => $code ) );
		}

		$remote_run_id = '';
		if ( isset( $data['remote_run_id'] ) ) {
			$remote_run_id = (string) $data['remote_run_id'];
		} elseif ( isset( $data['run_id'] ) ) {
			$remote_run_id = (string) $data['run_id'];
		}

		$engine_response = null;
		if ( isset( $data['result'] ) && is_array( $data['result'] ) ) {
			$engine_response = $data['result'];
		} elseif ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$engine_response = $data['data'];
		} else {
			$engine_response = $data;
		}

		$parsed = array(
			'remote_run_id'   => $remote_run_id,
			'engine_response' => is_array( $engine_response ) ? $engine_response : array(),
		);

		/**
		 * Normalised remote workflow response before the workflow consumes it.
		 *
		 * @param array<string, mixed> $parsed       Keys: remote_run_id, engine_response.
		 * @param array<string, mixed> $data         Raw API JSON.
		 * @param string               $workflow_key Workflow key.
		 */
		$parsed = apply_filters( 'rwga_remote_workflow_response', $parsed, $data, $workflow_key );
		if ( ! is_array( $parsed ) || empty( $parsed['engine_response'] ) || ! is_array( $parsed['engine_response'] ) ) {
			return new WP_Error( 'rwga_remote_shape', __( 'Remote response did not include a usable result.', 'reactwoo-geo-ai' ), array( 'status' => 502 ) );
		}

		if ( class_exists( 'RWGA_Insight_Memory', false ) ) {
			RWGA_Insight_Memory::store( $workflow_key, $payload, $route, $parsed );
		}

		$parsed['cache_hit']   = false;
		$parsed['model_route'] = $route;
		self::$last_dispatch_meta = array(
			'cache_hit'   => false,
			'model_route' => $route,
		);

		return $parsed;
	}

	/**
	 * Metadata from the most recent dispatch (cache hit, model route).
	 *
	 * @return array<string, mixed>
	 */
	public static function last_dispatch_meta() {
		return is_array( self::$last_dispatch_meta ) ? self::$last_dispatch_meta : array();
	}

	/**
	 * Telemetry fields for rwga_workflow_persisted.
	 *
	 * @param array<string, mixed> $usage Optional usage block from engine response.
	 * @return array<string, mixed>
	 */
	public static function telemetry_meta( array $usage = array() ) {
		$meta  = self::last_dispatch_meta();
		$route = isset( $meta['model_route'] ) && is_array( $meta['model_route'] ) ? $meta['model_route'] : array();

		return array(
			'cache_hit'      => ! empty( $meta['cache_hit'] ) || ! empty( $usage['cache_hit'] ),
			'model'            => ! empty( $usage['model'] )
				? sanitize_text_field( (string) $usage['model'] )
				: ( isset( $route['model_hint'] ) ? sanitize_text_field( (string) $route['model_hint'] ) : '' ),
			'provider'         => ! empty( $usage['provider'] )
				? sanitize_key( (string) $usage['provider'] )
				: ( isset( $route['provider_hint'] ) ? sanitize_key( (string) $route['provider_hint'] ) : '' ),
			'prompt_version'   => isset( $route['prompt_version'] )
				? sanitize_text_field( (string) $route['prompt_version'] )
				: ( class_exists( 'RWGA_Local_Intelligence', false ) ? RWGA_Local_Intelligence::PROMPT_VERSION : '1.0.0' ),
		);
	}
}
