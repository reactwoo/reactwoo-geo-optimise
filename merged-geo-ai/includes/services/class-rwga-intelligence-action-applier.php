<?php
/**
 * Applies approved intelligence actions locally (bounded, no silent mutation).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Explicit allowlist of action_type handlers.
 */
class RWGA_Intelligence_Action_Applier {

	/**
	 * @return array<int, string>
	 */
	public static function allowed_action_types() {
		$types = array(
			'mark_orphaned_variant',
			'open_admin_route',
			'create_implementation_draft',
			'create_optimise_test_prefill',
		);
		/**
		 * Filter intelligence action types that may be applied after explicit approval.
		 *
		 * @param array<int, string> $types Allowed action_type keys.
		 */
		return apply_filters( 'rwga_intelligence_allowed_action_types', $types );
	}

	/**
	 * Apply a pending intelligence action after explicit admin approval.
	 *
	 * @param int $action_id Action row ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function apply( $action_id ) {
		$action_id = (int) $action_id;
		if ( $action_id <= 0 || ! class_exists( 'RWGA_DB_Intelligence_Actions', false ) ) {
			return new WP_Error( 'rwga_bad_action', __( 'Invalid intelligence action.', 'reactwoo-geo-ai' ) );
		}

		$row = RWGA_DB_Intelligence_Actions::get( $action_id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwga_action_missing', __( 'Intelligence action not found.', 'reactwoo-geo-ai' ) );
		}

		$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
		if ( 'pending' !== $status ) {
			return new WP_Error( 'rwga_action_not_pending', __( 'This action is no longer pending approval.', 'reactwoo-geo-ai' ) );
		}

		$type = isset( $row['action_type'] ) ? sanitize_key( (string) $row['action_type'] ) : '';
		if ( '' === $type || ! in_array( $type, self::allowed_action_types(), true ) ) {
			return new WP_Error( 'rwga_action_unsupported', __( 'This action type cannot be applied automatically.', 'reactwoo-geo-ai' ) );
		}

		$payload = self::decode_action_json( $row );
		$result  = self::dispatch( $type, $row, $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$uid = get_current_user_id();
		RWGA_DB_Intelligence_Actions::update_status(
			$action_id,
			'applied',
			array(
				'approved_by'       => $uid,
				'apply_result_json' => $result,
			)
		);

		if ( class_exists( 'RWGA_Memory_Service', false ) ) {
			RWGA_Memory_Service::append(
				'intelligence_action_applied',
				'intelligence_action',
				$action_id,
				isset( $row['page_id'] ) ? (int) $row['page_id'] : 0,
				'',
				array(
					'action_type'  => $type,
					'workflow_key' => isset( $row['workflow_key'] ) ? (string) $row['workflow_key'] : '',
					'result'       => $result,
				)
			);
		}

		/**
		 * Fires after an intelligence action is applied locally.
		 *
		 * @param int                  $action_id Action row ID.
		 * @param array<string, mixed> $row       Action row.
		 * @param array<string, mixed> $result    Apply result.
		 */
		do_action( 'rwga_intelligence_action_applied', $action_id, $row, $result );

		return array(
			'success'   => true,
			'action_id' => $action_id,
			'result'    => $result,
		);
	}

	/**
	 * @param int $action_id Action row ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function dismiss( $action_id ) {
		$action_id = (int) $action_id;
		if ( $action_id <= 0 || ! class_exists( 'RWGA_DB_Intelligence_Actions', false ) ) {
			return new WP_Error( 'rwga_bad_action', __( 'Invalid intelligence action.', 'reactwoo-geo-ai' ) );
		}
		$row = RWGA_DB_Intelligence_Actions::get( $action_id );
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'rwga_action_missing', __( 'Intelligence action not found.', 'reactwoo-geo-ai' ) );
		}
		if ( 'pending' !== sanitize_key( (string) ( $row['status'] ?? '' ) ) ) {
			return new WP_Error( 'rwga_action_not_pending', __( 'Only pending actions can be dismissed.', 'reactwoo-geo-ai' ) );
		}
		$uid = get_current_user_id();
		RWGA_DB_Intelligence_Actions::update_status(
			$action_id,
			'dismissed',
			array( 'approved_by' => $uid )
		);
		return array( 'success' => true, 'action_id' => $action_id );
	}

	/**
	 * @param array<string, mixed> $row DB row.
	 * @return array<string, mixed>
	 */
	private static function decode_action_json( array $row ) {
		if ( ! isset( $row['action_json'] ) || ! is_string( $row['action_json'] ) || '' === $row['action_json'] ) {
			return array();
		}
		$dec = json_decode( $row['action_json'], true );
		return ( JSON_ERROR_NONE === json_last_error() && is_array( $dec ) ) ? $dec : array();
	}

	/**
	 * @param string               $type Action type.
	 * @param array<string, mixed> $row Action row.
	 * @param array<string, mixed> $payload Decoded action_json.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function dispatch( $type, array $row, array $payload ) {
		switch ( $type ) {
			case 'mark_orphaned_variant':
				return self::apply_mark_orphaned_variant( $row, $payload );
			case 'open_admin_route':
				return self::apply_open_admin_route( $payload );
			case 'create_implementation_draft':
				return self::apply_create_implementation_draft( $row, $payload );
			case 'create_optimise_test_prefill':
				return self::apply_create_optimise_test_prefill( $row, $payload );
		}

		/**
		 * Filter custom intelligence action application.
		 *
		 * @param array<string, mixed>|\WP_Error $result   Result or error.
		 * @param string                         $type     Action type.
		 * @param array<string, mixed>           $row      Action row.
		 * @param array<string, mixed>           $payload  action_json.
		 */
		$filtered = apply_filters( 'rwga_intelligence_apply_action', null, $type, $row, $payload );
		if ( is_wp_error( $filtered ) || is_array( $filtered ) ) {
			return $filtered;
		}

		return new WP_Error( 'rwga_action_unsupported', __( 'No handler registered for this action type.', 'reactwoo-geo-ai' ) );
	}

	/**
	 * Audit-only: create an implementation draft, never mutate live variant routing.
	 *
	 * @param array<string, mixed> $row Action row.
	 * @param array<string, mixed> $payload action_json.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function apply_mark_orphaned_variant( array $row, array $payload ) {
		if ( ! class_exists( 'RWGA_DB_Implementation_Drafts', false ) ) {
			return new WP_Error( 'rwga_missing_drafts', __( 'Implementation drafts storage unavailable.', 'reactwoo-geo-ai' ) );
		}

		$count = isset( $payload['count'] ) ? (int) $payload['count'] : 0;
		$title = isset( $row['label'] ) ? (string) $row['label'] : __( 'Variant relationship audit', 'reactwoo-geo-ai' );

		$draft_id = RWGA_DB_Implementation_Drafts::insert(
			array(
				'recommendation_id'    => isset( $row['recommendation_id'] ) ? (int) $row['recommendation_id'] : 0,
				'workflow_key'         => isset( $row['workflow_key'] ) ? (string) $row['workflow_key'] : 'variant_relationship_audit',
				'draft_type'           => 'intelligence_audit',
				'page_id'              => isset( $row['page_id'] ) ? (int) $row['page_id'] : 0,
				'title'                => $title,
				'input_context'        => sprintf(
					/* translators: %d: orphan count */
					__( 'Orphaned variant relationships detected: %d', 'reactwoo-geo-ai' ),
					$count
				),
				'draft_payload'        => array(
					'action_type'   => 'mark_orphaned_variant',
					'action_json'   => $payload,
					'snapshot_hash' => isset( $row['snapshot_hash'] ) ? (string) $row['snapshot_hash'] : '',
				),
				'implementation_route' => 'draft',
				'status'               => 'draft',
				'created_by'           => get_current_user_id(),
			)
		);

		if ( $draft_id <= 0 ) {
			return new WP_Error( 'rwga_draft_failed', __( 'Could not create audit draft.', 'reactwoo-geo-ai' ) );
		}

		return array(
			'route'    => 'implementation_draft',
			'draft_id' => $draft_id,
			'message'  => __( 'Audit draft created for manual variant review.', 'reactwoo-geo-ai' ),
		);
	}

	/**
	 * @param array<string, mixed> $payload action_json.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function apply_open_admin_route( array $payload ) {
		$page = isset( $payload['admin_page'] ) ? sanitize_key( (string) $payload['admin_page'] ) : '';
		if ( '' === $page ) {
			return new WP_Error( 'rwga_bad_route', __( 'admin_page is required in action_json.', 'reactwoo-geo-ai' ) );
		}
		$args = array( 'page' => $page );
		if ( ! empty( $payload['query_args'] ) && is_array( $payload['query_args'] ) ) {
			foreach ( $payload['query_args'] as $k => $v ) {
				$args[ sanitize_key( (string) $k ) ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
			}
		}
		return array(
			'route'        => 'admin_redirect',
			'redirect_url' => add_query_arg( $args, admin_url( 'admin.php' ) ),
		);
	}

	/**
	 * @param array<string, mixed> $row Action row.
	 * @param array<string, mixed> $payload action_json.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function apply_create_implementation_draft( array $row, array $payload ) {
		if ( ! class_exists( 'RWGA_DB_Implementation_Drafts', false ) ) {
			return new WP_Error( 'rwga_missing_drafts', __( 'Implementation drafts storage unavailable.', 'reactwoo-geo-ai' ) );
		}

		$draft_payload = isset( $payload['draft_payload'] ) && is_array( $payload['draft_payload'] ) ? $payload['draft_payload'] : $payload;
		$draft_type    = isset( $payload['draft_type'] ) ? sanitize_key( (string) $payload['draft_type'] ) : 'intelligence';
		$title         = isset( $payload['title'] ) ? sanitize_text_field( (string) $payload['title'] ) : ( isset( $row['label'] ) ? (string) $row['label'] : '' );

		$draft_id = RWGA_DB_Implementation_Drafts::insert(
			array(
				'recommendation_id'    => isset( $row['recommendation_id'] ) ? (int) $row['recommendation_id'] : ( isset( $payload['recommendation_id'] ) ? (int) $payload['recommendation_id'] : 0 ),
				'workflow_key'         => isset( $row['workflow_key'] ) ? (string) $row['workflow_key'] : '',
				'draft_type'           => $draft_type,
				'page_id'              => isset( $payload['page_id'] ) ? (int) $payload['page_id'] : ( isset( $row['page_id'] ) ? (int) $row['page_id'] : 0 ),
				'title'                => $title,
				'input_context'        => isset( $payload['input_context'] ) ? wp_kses_post( (string) $payload['input_context'] ) : '',
				'draft_payload'        => array_merge(
					array( 'action_type' => isset( $row['action_type'] ) ? (string) $row['action_type'] : '' ),
					$draft_payload
				),
				'implementation_route' => 'draft',
				'status'               => 'draft',
				'created_by'           => get_current_user_id(),
			)
		);

		if ( $draft_id <= 0 ) {
			return new WP_Error( 'rwga_draft_failed', __( 'Could not create implementation draft.', 'reactwoo-geo-ai' ) );
		}

		return array(
			'route'    => 'implementation_draft',
			'draft_id' => $draft_id,
		);
	}

	/**
	 * Approval-gated handoff to Geo Optimise Create Test (prefill only).
	 *
	 * @param array<string, mixed> $row Action row.
	 * @param array<string, mixed> $payload action_json.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function apply_create_optimise_test_prefill( array $row, array $payload ) {
		if ( ! class_exists( 'RWGA_Intelligence_Optimise_Handoff', false ) ) {
			return new WP_Error( 'rwga_handoff_missing', __( 'Geo Optimise handoff is unavailable.', 'reactwoo-geo-ai' ) );
		}
		if ( ! RWGA_Intelligence_Optimise_Handoff::is_available() ) {
			return new WP_Error( 'rwga_optimise_missing', __( 'Geo Optimise is not active.', 'reactwoo-geo-ai' ) );
		}

		$source_id = isset( $payload['source_id'] ) ? (int) $payload['source_id'] : 0;
		if ( $source_id <= 0 && ! empty( $payload['page_id'] ) ) {
			$source_id = (int) $payload['page_id'];
		}
		if ( $source_id <= 0 && ! empty( $row['page_id'] ) ) {
			$source_id = (int) $row['page_id'];
		}

		$context = array(
			'workflow_key' => isset( $row['workflow_key'] ) ? (string) $row['workflow_key'] : 'ux_opportunity_review',
			'test_name'    => isset( $payload['test_name'] ) ? (string) $payload['test_name'] : ( isset( $row['label'] ) ? (string) $row['label'] : '' ),
			'source_id'    => $source_id,
		);
		if ( ! empty( $payload['variant_page_id'] ) ) {
			$context['variant_b_id'] = (int) $payload['variant_page_id'];
		}

		$url = RWGA_Intelligence_Optimise_Handoff::build_create_test_url( $context );
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		return array(
			'route'        => 'admin_redirect',
			'redirect_url' => (string) $url,
		);
	}
}
