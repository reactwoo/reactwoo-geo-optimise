<?php
/**
 * REST: list builder-defined goals for test pages.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GET /rwgo/v1/defined-goals
 */
class RWGO_REST_Defined_Goals {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'rwgo/v1',
			'/defined-goals',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_get' ),
				'permission_callback' => array( __CLASS__, 'can_manage_goals' ),
				'args'                => array(
					'post_ids' => array(
						'description' => __( 'Comma-separated post IDs (control + variant pages).', 'reactwoo-geo-optimise' ),
						'type'        => 'string',
						'required'    => true,
					),
				),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function can_manage_goals() {
		return class_exists( 'RWGO_Admin', false ) && RWGO_Admin::can_manage();
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_get( $request ) {
		$raw = $request->get_param( 'post_ids' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return new \WP_Error(
				'rwgo_bad_request',
				__( 'Missing post_ids.', 'reactwoo-geo-optimise' ),
				array( 'status' => 400 )
			);
		}
		$parts = array_filter( array_map( 'intval', explode( ',', $raw ) ) );
		$ids   = array();
		foreach ( $parts as $pid ) {
			if ( $pid > 0 && current_user_can( 'edit_post', $pid ) ) {
				$ids[] = $pid;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		if ( empty( $ids ) ) {
			return new \WP_Error(
				'rwgo_forbidden',
				__( 'No valid posts.', 'reactwoo-geo-optimise' ),
				array( 'status' => 403 )
			);
		}
		$goals = RWGO_Defined_Goal_Service::collect_for_posts( $ids );
		return rest_ensure_response(
			array(
				'goals' => $goals,
			)
		);
	}
}
