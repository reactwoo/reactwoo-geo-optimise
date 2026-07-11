<?php
/**
 * Deterministic local generation transport.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invokes a workflow-provided local callback.
 */
class RWGA_Local_AI_Transport implements RWGA_Generation_Transport {

	/**
	 * @return string
	 */
	public function get_key() {
		return 'local';
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Request.
	 * @return bool
	 */
	public function supports( $workflow_key, array $request ) {
		unset( $workflow_key );
		return isset( $request['local_callback'] ) && is_callable( $request['local_callback'] );
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Request.
	 * @return true|\WP_Error
	 */
	public function availability( $workflow_key, array $request ) {
		if ( ! $this->supports( $workflow_key, $request ) ) {
			return new WP_Error(
				'rwga_transport_unavailable',
				__( 'No local deterministic implementation is available for this workflow.', 'reactwoo-geo-ai' )
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

		try {
			$result = call_user_func( $request['local_callback'], $workflow_key, $request );
		} catch ( Exception $e ) {
			return new WP_Error(
				'rwga_generation_failed',
				__( 'Local generation failed.', 'reactwoo-geo-ai' )
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! is_array( $result ) ) {
			return new WP_Error(
				'rwga_generation_invalid_response',
				__( 'Local generation returned an invalid response.', 'reactwoo-geo-ai' )
			);
		}

		$engine = isset( $result['engine_response'] ) && is_array( $result['engine_response'] )
			? $result['engine_response']
			: $result;

		return array(
			'transport'       => 'local',
			'engine_response' => $engine,
			'remote_run_id'   => null,
			'usage'           => array(),
			'meta'            => array(
				'engine_source'  => 'local',
				'provider'       => 'local',
				'model'          => '',
				'prompt_version' => '',
				'cache_hit'      => false,
			),
		);
	}
}
