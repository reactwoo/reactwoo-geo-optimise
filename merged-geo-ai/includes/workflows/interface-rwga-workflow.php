<?php
/**
 * Bounded workflow contract.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Workflow interface for Geo AI.
 */
interface RWGA_Workflow_Interface {

	/**
	 * @return string
	 */
	public function get_key();

	/**
	 * @return string
	 */
	public function get_label();

	/**
	 * @return string
	 */
	public function get_agent_key();

	/**
	 * @param array<string, mixed> $input Raw input.
	 * @return true|\WP_Error
	 */
	public function validate_input( array $input );

	/**
	 * @param array<string, mixed> $input Sanitised input.
	 * @return array<string, mixed>
	 */
	public function build_request_payload( array $input );

	/**
	 * Run workflow (local adapter or remote).
	 *
	 * @param array<string, mixed> $input Sanitised input.
	 * @return array<string, mixed>|\WP_Error Normalised payload or error.
	 */
	public function execute( array $input );

	/**
	 * @param array<string, mixed> $response Raw engine response.
	 * @return array<string, mixed>
	 */
	public function normalise_response( array $response );

	/**
	 * @param array<string, mixed> $input    Input used.
	 * @param array<string, mixed> $result   Normalised result.
	 * @return array<string, mixed> Persisted ids and record summary.
	 */
	public function persist( array $input, array $result );
}
