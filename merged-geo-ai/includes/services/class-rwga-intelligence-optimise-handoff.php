<?php
/**
 * Hand off optimisation intelligence results to Geo Optimise Create Test (prefill only).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds admin URLs for Geo Optimise experiment setup from intelligence runs.
 */
class RWGA_Intelligence_Optimise_Handoff {

	/**
	 * @return bool
	 */
	public static function is_available() {
		if ( ! class_exists( 'RWGC_Admin_UI', false ) ) {
			return class_exists( 'RWGO_Admin', false );
		}
		return RWGC_Admin_UI::is_plugin_active( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' );
	}

	/**
	 * @param string $workflow_key Workflow key.
	 * @return bool
	 */
	public static function supports_workflow( $workflow_key ) {
		$key = sanitize_key( (string) $workflow_key );
		return in_array( $key, array( 'optimisation_recommendation', 'variant_relationship_audit', 'tracking_gap_audit', 'ux_opportunity_review' ), true );
	}

	/**
	 * Build Create Test URL from a cloud intelligence run record.
	 *
	 * @param array<string, mixed> $run Cloud run row from API.
	 * @return string|\WP_Error
	 */
	public static function build_from_cloud_run( array $run ) {
		if ( ! self::is_available() ) {
			return new WP_Error( 'rwga_optimise_missing', __( 'Geo Optimise is not active.', 'reactwoo-geo-ai' ) );
		}

		$workflow = isset( $run['workflow_key'] ) ? sanitize_key( (string) $run['workflow_key'] ) : '';
		if ( ! self::supports_workflow( $workflow ) ) {
			return new WP_Error( 'rwga_handoff_unsupported', __( 'This workflow does not support Geo Optimise handoff.', 'reactwoo-geo-ai' ) );
		}

		$context = self::resolve_context_from_run( $run );
		$context['workflow_key'] = $workflow;
		if ( ! empty( $run['run_id'] ) ) {
			$context['intel_run_id'] = sanitize_text_field( (string) $run['run_id'] );
		}

		return self::build_create_test_url( $context );
	}

	/**
	 * Build Create Test URL from a local intelligence action row.
	 *
	 * @param array<string, mixed> $row Action DB row.
	 * @return string|\WP_Error
	 */
	public static function build_from_action_row( array $row ) {
		if ( ! self::is_available() ) {
			return new WP_Error( 'rwga_optimise_missing', __( 'Geo Optimise is not active.', 'reactwoo-geo-ai' ) );
		}

		$workflow = isset( $row['workflow_key'] ) ? sanitize_key( (string) $row['workflow_key'] ) : '';
		if ( ! self::supports_workflow( $workflow ) ) {
			return new WP_Error( 'rwga_handoff_unsupported', __( 'This workflow does not support Geo Optimise handoff.', 'reactwoo-geo-ai' ) );
		}

		$page_id = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
		$context = array(
			'workflow_key' => $workflow,
			'test_name'    => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
			'source_id'    => $page_id,
		);

		if ( $page_id <= 0 && ! empty( $row['action_json'] ) ) {
			$payload = json_decode( (string) $row['action_json'], true );
			if ( is_array( $payload ) ) {
				if ( ! empty( $payload['source_id'] ) ) {
					$context['source_id'] = absint( $payload['source_id'] );
				}
				if ( ! empty( $payload['master_page_ids'] ) && is_array( $payload['master_page_ids'] ) ) {
					$context['source_id'] = absint( $payload['master_page_ids'][0] );
				} elseif ( ! empty( $payload['page_id'] ) ) {
					$context['source_id'] = absint( $payload['page_id'] );
				}
				if ( ! empty( $payload['variant_page_id'] ) ) {
					$context['variant_b_id'] = absint( $payload['variant_page_id'] );
				}
				if ( ! empty( $payload['test_name'] ) ) {
					$context['test_name'] = sanitize_text_field( (string) $payload['test_name'] );
				}
			}
		}

		return self::build_create_test_url( $context );
	}

	/**
	 * @param array<string, mixed> $context Handoff context.
	 * @return string|\WP_Error
	 */
	public static function build_create_test_url( array $context ) {
		if ( ! self::is_available() ) {
			return new WP_Error( 'rwga_optimise_missing', __( 'Geo Optimise is not active.', 'reactwoo-geo-ai' ) );
		}

		$source_id   = isset( $context['source_id'] ) ? absint( $context['source_id'] ) : 0;
		$variant_b   = isset( $context['variant_b_id'] ) ? absint( $context['variant_b_id'] ) : 0;
		$test_name   = isset( $context['test_name'] ) ? sanitize_text_field( (string) $context['test_name'] ) : '';
		$test_type   = isset( $context['test_type'] ) ? sanitize_key( (string) $context['test_type'] ) : '';
		$variant_mode = isset( $context['variant_mode'] ) ? sanitize_key( (string) $context['variant_mode'] ) : '';

		if ( $source_id <= 0 && $variant_b > 0 ) {
			$resolved = self::resolve_master_from_variant( $variant_b );
			if ( $resolved > 0 ) {
				$source_id = $resolved;
			} else {
				$source_id = $variant_b;
				$variant_b = 0;
			}
		}

		if ( $source_id <= 0 ) {
			return new WP_Error( 'rwga_handoff_no_page', __( 'No page context available for Geo Optimise handoff.', 'reactwoo-geo-ai' ) );
		}

		if ( ! current_user_can( 'edit_page', $source_id ) && ! current_user_can( 'edit_post', $source_id ) ) {
			return new WP_Error( 'rwga_handoff_forbidden', __( 'You do not have permission to create a test for this content.', 'reactwoo-geo-ai' ) );
		}

		if ( '' === $test_type ) {
			$test_type = self::guess_test_type( $source_id );
		}
		if ( '' === $variant_mode ) {
			$variant_mode = $variant_b > 0 ? 'existing' : 'duplicate';
		}
		if ( '' === $test_name ) {
			$test_name = self::default_test_name( $source_id, $context );
		}

		$args = array(
			'page'                    => 'rwgo-create-test',
			'rwgc_handoff'            => '1',
			'rwgc_from'               => 'geo_ai',
			'rwgc_launcher'           => 'intelligence_optimise',
			'rwgo_prefill_name'       => $test_name,
			'rwgo_prefill_source'     => $source_id,
			'rwgo_prefill_test_type'  => $test_type,
			'rwgo_prefill_variant_mode' => $variant_mode,
		);

		if ( $variant_b > 0 ) {
			$args['rwgo_prefill_variant_b']     = $variant_b;
			$args['rwgc_variant_page_id']       = $variant_b;
		}

		if ( ! empty( $context['intel_run_id'] ) ) {
			$args['rwga_intel_run_id'] = sanitize_text_field( (string) $context['intel_run_id'] );
		}

		/**
		 * Filter Geo Optimise Create Test handoff query args from Geo AI intelligence.
		 *
		 * @param array<string, scalar> $args    Query args for admin.php.
		 * @param array<string, mixed>  $context Resolved handoff context.
		 */
		$args = apply_filters( 'rwga_intelligence_optimise_handoff_args', $args, $context );

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * @param array<string, mixed> $run Cloud run record.
	 * @return array<string, mixed>
	 */
	private static function resolve_context_from_run( array $run ) {
		$result = isset( $run['result'] ) && is_array( $run['result'] ) ? $run['result'] : $run;

		$variant_b = 0;
		if ( ! empty( $run['variant_page_id'] ) ) {
			$variant_b = absint( $run['variant_page_id'] );
		}

		$source_id = 0;
		$recs      = isset( $result['recommendations'] ) && is_array( $result['recommendations'] ) ? $result['recommendations'] : array();
		$findings  = isset( $result['findings'] ) && is_array( $result['findings'] ) ? $result['findings'] : array();
		$actions   = isset( $result['actions'] ) && is_array( $result['actions'] ) ? $result['actions'] : array();

		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) || empty( $action['action_json'] ) || ! is_array( $action['action_json'] ) ) {
				continue;
			}
			$aj = $action['action_json'];
			if ( ! empty( $aj['master_page_ids'] ) && is_array( $aj['master_page_ids'] ) ) {
				$source_id = absint( $aj['master_page_ids'][0] );
				break;
			}
			if ( ! empty( $aj['page_id'] ) ) {
				$source_id = absint( $aj['page_id'] );
				break;
			}
		}

		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) || $source_id > 0 ) {
				continue;
			}
			$etype = isset( $finding['entity_type'] ) ? sanitize_key( (string) $finding['entity_type'] ) : '';
			$eid   = isset( $finding['entity_id'] ) ? absint( $finding['entity_id'] ) : 0;
			if ( $eid > 0 && in_array( $etype, array( 'page', 'variant', 'master' ), true ) ) {
				$source_id = $eid;
			}
		}

		if ( $variant_b > 0 && $source_id <= 0 ) {
			$source_id = self::resolve_master_from_variant( $variant_b );
			if ( $source_id <= 0 ) {
				$source_id = $variant_b;
				$variant_b = 0;
			}
		}

		$test_name = '';
		if ( ! empty( $recs[0] ) && is_array( $recs[0] ) && ! empty( $recs[0]['title'] ) ) {
			$test_name = sanitize_text_field( (string) $recs[0]['title'] );
		} elseif ( ! empty( $run['summary'] ) ) {
			$test_name = sanitize_text_field( wp_trim_words( (string) $run['summary'], 8, '' ) );
		}

		return array(
			'source_id'    => $source_id,
			'variant_b_id' => $variant_b,
			'test_name'    => $test_name,
			'test_type'    => $source_id > 0 ? self::guess_test_type( $source_id ) : 'page_ab',
			'variant_mode' => $variant_b > 0 ? 'existing' : 'duplicate',
		);
	}

	/**
	 * @param int                  $source_id Source post id.
	 * @param array<string, mixed> $context   Context.
	 * @return string
	 */
	private static function default_test_name( $source_id, array $context ) {
		if ( ! empty( $context['test_name'] ) ) {
			return sanitize_text_field( (string) $context['test_name'] );
		}
		$post = get_post( (int) $source_id );
		if ( $post instanceof WP_Post ) {
			return sprintf(
				/* translators: %s: page title */
				__( 'Geo AI: %s', 'reactwoo-geo-ai' ),
				get_the_title( $post )
			);
		}
		return __( 'Geo AI optimisation test', 'reactwoo-geo-ai' );
	}

	/**
	 * @param int $variant_id Variant page id.
	 * @return int Master page id or 0.
	 */
	private static function resolve_master_from_variant( $variant_id ) {
		$variant_id = (int) $variant_id;
		if ( $variant_id <= 0 ) {
			return 0;
		}
		if ( class_exists( 'RWGC_Routing', false ) ) {
			$cfg = RWGC_Routing::get_page_route_config( $variant_id );
			if ( is_array( $cfg ) && ! empty( $cfg['master_page_id'] ) ) {
				return absint( $cfg['master_page_id'] );
			}
		}
		$meta = (int) get_post_meta( $variant_id, '_rwgc_route_master_page_id', true );
		return $meta > 0 ? $meta : 0;
	}

	/**
	 * @param int $post_id Post id.
	 * @return string
	 */
	private static function guess_test_type( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			return 'page_ab';
		}
		if ( 'product' === $post->post_type ) {
			return 'woo_product';
		}
		if ( class_exists( '\Elementor\Plugin', false ) ) {
			$el_data = get_post_meta( $post_id, '_elementor_data', true );
			if ( is_string( $el_data ) && '' !== trim( $el_data ) && '[]' !== trim( $el_data ) ) {
				return 'elementor_page';
			}
		}
		if ( function_exists( 'has_blocks' ) && has_blocks( $post ) ) {
			return 'gutenberg_page';
		}
		return 'page_ab';
	}
}
