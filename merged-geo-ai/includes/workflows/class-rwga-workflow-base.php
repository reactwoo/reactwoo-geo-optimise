<?php
/**
 * Shared workflow behaviour.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for bounded workflows.
 */
abstract class RWGA_Workflow_Base implements RWGA_Workflow_Interface {

	/**
	 * Default schema version for normalised payloads.
	 */
	const DEFAULT_SCHEMA_VERSION = '1.0.0';

	/**
	 * Whether the current user may run this workflow.
	 *
	 * @return true|\WP_Error
	 */
	protected function gate_capabilities() {
		if ( ! RWGA_Capabilities::current_user_can_run_ai() ) {
			return new WP_Error( 'rwga_forbidden', __( 'You do not have permission to run Geo AI workflows.', 'reactwoo-geo-ai' ) );
		}
		if ( ! RWGA_License::can_run_workflows() ) {
			return new WP_Error( 'rwga_unlicensed', __( 'Add a Geo AI license key to run workflows.', 'reactwoo-geo-ai' ) );
		}
		return true;
	}

	/**
	 * @param array<string, mixed> $input Raw.
	 * @return array<string, mixed>
	 */
	protected function sanitise_common( array $input ) {
		$page_id = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		$geo     = isset( $input['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $input['geo_target'] ), 0, 2 ) ) : '';
		$device  = isset( $input['device_type'] ) ? sanitize_key( (string) $input['device_type'] ) : 'desktop';
		$ptype   = isset( $input['page_type'] ) ? sanitize_key( (string) $input['page_type'] ) : 'page';

		$url = '';
		if ( $page_id > 0 ) {
			$url = get_permalink( $page_id );
			if ( ! is_string( $url ) ) {
				$url = '';
			}
		} elseif ( ! empty( $input['page_url'] ) ) {
			$url = esc_url_raw( (string) $input['page_url'] );
		}

		return array(
			'page_id'     => $page_id > 0 ? $page_id : 0,
			'page_url'    => $url,
			'page_type'   => $ptype,
			'geo_target'  => '' !== $geo ? $geo : null,
			'device_type' => '' !== $device ? $device : 'desktop',
		);
	}
}
