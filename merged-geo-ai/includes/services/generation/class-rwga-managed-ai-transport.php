<?php
/**
 * ReactWoo managed AI transport (platform JWT + quota).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wraps {@see RWGA_Remote_Client} with managed-auth and quota preflight.
 */
class RWGA_Managed_AI_Transport implements RWGA_Generation_Transport {

	/**
	 * @return string
	 */
	public function get_key() {
		return 'managed';
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Request.
	 * @return bool
	 */
	public function supports( $workflow_key, array $request ) {
		unset( $request );
		return '' !== sanitize_key( (string) $workflow_key );
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Request.
	 * @return true|\WP_Error
	 */
	public function availability( $workflow_key, array $request ) {
		unset( $request );
		if ( ! $this->supports( $workflow_key, array() ) ) {
			return new WP_Error(
				'rwga_transport_unsupported',
				__( 'Managed AI requires a valid workflow key.', 'reactwoo-geo-ai' )
			);
		}

		if ( class_exists( 'RWGA_AI_Usage_Guard', false ) ) {
			$gate = RWGA_AI_Usage_Guard::can_run_managed_generation( $workflow_key );
			if ( empty( $gate['allowed'] ) ) {
				$reason = isset( $gate['reason'] ) ? (string) $gate['reason'] : __( 'ReactWoo managed AI is not available.', 'reactwoo-geo-ai' );
				return new WP_Error( 'rwga_transport_unavailable', $reason );
			}
		}

		if ( ! class_exists( 'RWGA_Remote_Client', false ) ) {
			return new WP_Error(
				'rwga_transport_unavailable',
				__( 'ReactWoo managed AI client is not loaded.', 'reactwoo-geo-ai' )
			);
		}

		return true;
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function dispatch( $workflow_key, array $request ) {
		$avail = $this->availability( $workflow_key, $request );
		if ( is_wp_error( $avail ) ) {
			return $avail;
		}

		$payload = isset( $request['payload'] ) && is_array( $request['payload'] ) ? $request['payload'] : $request;
		unset( $payload['local_callback'] );

		$remote = RWGA_Remote_Client::dispatch( $workflow_key, $payload );
		if ( is_wp_error( $remote ) ) {
			return new WP_Error(
				'rwga_generation_failed',
				$remote->get_error_message(),
				$remote->get_error_data()
			);
		}
		if ( ! is_array( $remote ) || empty( $remote['engine_response'] ) || ! is_array( $remote['engine_response'] ) ) {
			return new WP_Error(
				'rwga_generation_invalid_response',
				__( 'Managed AI returned an unusable response.', 'reactwoo-geo-ai' )
			);
		}

		$telemetry = RWGA_Remote_Client::telemetry_meta(
			isset( $remote['usage'] ) && is_array( $remote['usage'] ) ? $remote['usage'] : array()
		);

		$rid = isset( $remote['remote_run_id'] ) ? trim( (string) $remote['remote_run_id'] ) : '';

		return array(
			'transport'       => 'managed',
			'engine_response' => $remote['engine_response'],
			'remote_run_id'   => '' !== $rid ? $rid : null,
			'usage'           => isset( $remote['usage'] ) && is_array( $remote['usage'] ) ? $remote['usage'] : array(),
			'meta'            => array_merge(
				array(
					'engine_source' => 'managed',
					'cache_hit'     => ! empty( $remote['cache_hit'] ),
				),
				$telemetry
			),
		);
	}
}
