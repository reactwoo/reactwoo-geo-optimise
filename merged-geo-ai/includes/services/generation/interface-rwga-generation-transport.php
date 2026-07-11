<?php
/**
 * Generation transport contract.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bounded AI generation transport (WordPress AI, managed, or local).
 */
interface RWGA_Generation_Transport {

	/**
	 * Stable transport key: wordpress_ai|managed|local.
	 *
	 * @return string
	 */
	public function get_key();

	/**
	 * Whether this transport can handle the workflow (before availability).
	 *
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Generation request.
	 * @return bool
	 */
	public function supports( $workflow_key, array $request );

	/**
	 * Preflight availability. Do not start generation here.
	 *
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Generation request.
	 * @return true|\WP_Error
	 */
	public function availability( $workflow_key, array $request );

	/**
	 * Run generation. Failures must surface as WP_Error (no silent fallback).
	 *
	 * Success envelope:
	 * - transport (string)
	 * - engine_response (array)
	 * - remote_run_id (string|null)
	 * - usage (array)
	 * - meta (array)
	 *
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $request      Generation request.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function dispatch( $workflow_key, array $request );
}
