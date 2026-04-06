<?php
/**
 * REST API: persist browser-originated goal events (validated against saved experiments).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers POST /rwgo/v1/goal.
 */
class RWGO_REST_Tracking {

	const NONCE_ACTION = 'rwgo_goal_track';

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
			'/goal',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_goal' ),
				'permission_callback' => '__return_true',
				'args'                => array(),
			)
		);
		register_rest_route(
			'rwgo/v1',
			'/tracking-nonce',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'handle_tracking_nonce' ),
				'permission_callback' => '__return_true',
				'args'                => array(),
			)
		);
	}

	/**
	 * Fresh nonce for goal POST (avoids stale nonces when HTML is full-page cached).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_tracking_nonce( $request ) {
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		}
		return new \WP_REST_Response(
			array(
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'restUrl' => rest_url( 'rwgo/v1/goal' ),
			),
			200
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_goal( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) || array() === $params ) {
			$body = $request->get_body();
			if ( is_string( $body ) && '' !== $body ) {
				$decoded = json_decode( $body, true );
				if ( is_array( $decoded ) && array() !== $decoded ) {
					$params = $decoded;
				}
			}
		}
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$nonce = isset( $params['nonce'] ) ? (string) $params['nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new \WP_Error(
				'rwgo_invalid_nonce',
				__( 'Invalid or expired security token.', 'reactwoo-geo-optimise' ),
				array( 'status' => 403 )
			);
		}

		$referer = wp_get_referer();
		if ( is_string( $referer ) && '' !== $referer ) {
			$ref_host = wp_parse_url( $referer, PHP_URL_HOST );
			if ( is_string( $ref_host ) && '' !== $ref_host ) {
				$ref_host = strtolower( $ref_host );
				$allowed  = array();
				foreach ( array( home_url(), site_url() ) as $u ) {
					$h = wp_parse_url( $u, PHP_URL_HOST );
					if ( is_string( $h ) && '' !== $h ) {
						$allowed[ strtolower( $h ) ] = true;
					}
				}
				if ( ! isset( $allowed[ $ref_host ] ) ) {
					return new \WP_Error(
						'rwgo_invalid_referer',
						__( 'Request not allowed from this origin.', 'reactwoo-geo-optimise' ),
						array( 'status' => 403 )
					);
				}
			}
		}

		/**
		 * Allow blocking client goal persistence (e.g. custom analytics only).
		 *
		 * @param bool $allow Default true.
		 */
		if ( ! apply_filters( 'rwgo_allow_client_goal_rest', true ) ) {
			return new \WP_Error(
				'rwgo_disabled',
				__( 'Client goal recording is disabled.', 'reactwoo-geo-optimise' ),
				array( 'status' => 403 )
			);
		}

		$experiment_key = isset( $params['experiment_key'] ) ? sanitize_key( (string) $params['experiment_key'] ) : '';
		$goal_id        = isset( $params['goal_id'] ) ? sanitize_key( (string) $params['goal_id'] ) : '';
		$handler_id     = isset( $params['handler_id'] ) ? sanitize_key( (string) $params['handler_id'] ) : '';
		$variant_id     = isset( $params['variant_id'] ) ? sanitize_key( (string) $params['variant_id'] ) : '';

		if ( '' === $experiment_key || '' === $goal_id || '' === $handler_id || '' === $variant_id ) {
			return new \WP_Error(
				'rwgo_missing_fields',
				__( 'Missing experiment, goal, handler, or variant.', 'reactwoo-geo-optimise' ),
				array( 'status' => 400 )
			);
		}

		$post = RWGO_Experiment_Repository::find_by_experiment_key( $experiment_key );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error(
				'rwgo_unknown_experiment',
				__( 'Unknown or inactive experiment.', 'reactwoo-geo-optimise' ),
				array( 'status' => 404 )
			);
		}

		$cfg = RWGO_Experiment_Repository::get_config( $post->ID );
		if ( empty( $cfg['status'] ) || 'active' !== $cfg['status'] ) {
			return new \WP_Error(
				'rwgo_inactive_experiment',
				__( 'Experiment is not active.', 'reactwoo-geo-optimise' ),
				array( 'status' => 400 )
			);
		}

		if ( RWGO_Goal_Service::is_assignment_only( $cfg ) || empty( $cfg['goals'] ) || ! is_array( $cfg['goals'] ) ) {
			return new \WP_Error(
				'rwgo_no_goals',
				__( 'This test has no conversion goals.', 'reactwoo-geo-optimise' ),
				array( 'status' => 400 )
			);
		}

		$allowed_variants = RWGO_Experiment_Service::assignment_variant_slugs( $cfg );
		if ( ! in_array( $variant_id, $allowed_variants, true ) ) {
			return new \WP_Error(
				'rwgo_bad_variant',
				__( 'Invalid variant for this experiment.', 'reactwoo-geo-optimise' ),
				array( 'status' => 400 )
			);
		}

		if ( ! self::config_has_goal_handler( $cfg, $goal_id, $handler_id ) ) {
			return new \WP_Error(
				'rwgo_bad_goal',
				__( 'Goal or handler does not match this experiment.', 'reactwoo-geo-optimise' ),
				array( 'status' => 400 )
			);
		}

		if ( class_exists( 'RWGO_Goal_Mapping', false ) && RWGO_Goal_Mapping::is_active( $cfg ) ) {
			$goal_id = RWGO_Goal_Mapping::normalize_stored_goal_id( $cfg, $goal_id, $handler_id, $variant_id );
		}

		$page_context_id = isset( $params['page_context_id'] ) ? (int) $params['page_context_id'] : 0;
		$page_variant_id = isset( $params['page_variant_post_id'] ) ? (int) $params['page_variant_post_id'] : $page_context_id;

		$client_goal_label = isset( $params['goal_label'] ) ? sanitize_text_field( (string) $params['goal_label'] ) : '';

		$parts = array(
			'experiment_id'          => (int) $post->ID,
			'experiment_key'         => $experiment_key,
			'variant_id'             => $variant_id,
			'goal_id'                => $goal_id,
			'handler_id'             => $handler_id,
			'client_goal_label'      => $client_goal_label,
			'page_context_id'        => $page_context_id,
			'page_variant_post_id'   => (int) $page_variant_id,
			'goal_type'              => isset( $params['goal_type'] ) ? sanitize_key( (string) $params['goal_type'] ) : '',
			'element_fingerprint'    => isset( $params['element_fingerprint'] ) ? sanitize_text_field( (string) $params['element_fingerprint'] ) : '',
			'source'                 => 'geo_optimise_rest',
		);

		if ( ! empty( $params['event_instance_id'] ) ) {
			$parts['event_instance_id'] = substr( sanitize_text_field( (string) $params['event_instance_id'] ), 0, 64 );
		}

		$payload = RWGO_Event_Payload::normalize_goal_fired( $parts );

		/**
		 * Client goal payload before storage (REST).
		 *
		 * @param array<string, mixed> $payload Canonical payload.
		 * @param array<string, mixed> $params  Raw JSON params.
		 */
		$payload = apply_filters( 'rwgo_rest_client_goal_payload', $payload, $params );

		do_action( 'rwgo_goal_fired', $payload );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'stored'  => true,
			),
			201
		);
	}

	/**
	 * @param array<string, mixed> $cfg         Experiment config.
	 * @param string               $goal_id     Goal id.
	 * @param string               $handler_id  Handler id.
	 * @return bool
	 */
	private static function config_has_goal_handler( array $cfg, $goal_id, $handler_id ) {
		$goal_id    = sanitize_key( (string) $goal_id );
		$handler_id = sanitize_key( (string) $handler_id );
		if ( class_exists( 'RWGO_Goal_Mapping', false ) && RWGO_Goal_Mapping::is_active( $cfg ) ) {
			$m       = isset( $cfg['defined_goal_mapping'] ) && is_array( $cfg['defined_goal_mapping'] ) ? $cfg['defined_goal_mapping'] : array();
			$targets = isset( $m['targets'] ) && is_array( $m['targets'] ) ? $m['targets'] : array();
			foreach ( $targets as $pairs ) {
				if ( ! is_array( $pairs ) ) {
					continue;
				}
				foreach ( $pairs as $p ) {
					if ( ! is_array( $p ) ) {
						continue;
					}
					$g = sanitize_key( (string) ( $p['goal_id'] ?? '' ) );
					$h = sanitize_key( (string) ( $p['handler_id'] ?? '' ) );
					if ( $g === $goal_id && $h === $handler_id ) {
						return true;
					}
				}
			}
		}
		if ( empty( $cfg['goals'] ) || ! is_array( $cfg['goals'] ) ) {
			return false;
		}
		foreach ( $cfg['goals'] as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			if ( sanitize_key( (string) $g['goal_id'] ) !== $goal_id ) {
				continue;
			}
			$handlers = isset( $g['handlers'] ) && is_array( $g['handlers'] ) ? $g['handlers'] : array();
			foreach ( $handlers as $h ) {
				if ( is_array( $h ) && ! empty( $h['handler_id'] ) && sanitize_key( (string) $h['handler_id'] ) === $handler_id ) {
					return true;
				}
			}
		}
		return false;
	}
}
