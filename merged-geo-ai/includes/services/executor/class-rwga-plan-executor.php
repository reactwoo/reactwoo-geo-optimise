<?php
/**
 * Execute a confirmed multi-action geo plan into Geo Core entities.
 *
 * Each action becomes a DRAFT visibility rule in the Geo Core library so the
 * admin can review before publishing. Variant / original-targeting actions also
 * create a draft rule and list a manual step (page-variant wiring is not safe to
 * fully automate from free Geo Core). Test / diagnose actions are preview-only.
 *
 * The executor is intentionally conservative: anything it cannot translate
 * cleanly is reported as a manual step rather than guessed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Plan_Executor {

	/**
	 * @param array<int,array<string,mixed>> $actions Resolved plan actions.
	 * @param array<string,mixed>            $context Planner context.
	 * @return array<string,mixed>
	 */
	public static function execute_plan( array $actions, array $context = array() ) {
		$created  = array();
		$manual   = array();
		$preview  = array();
		$needs    = array();
		$position = 0;

		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$position++;
			$type  = (string) ( $action['type'] ?? '' );
			$label = self::action_label( $action, $position );

			if ( in_array( $type, array( RWGA_Geo_Action_Types::CREATE_TEST, RWGA_Geo_Action_Types::DIAGNOSE ), true ) ) {
				$preview[] = array(
					'index' => $position,
					'label' => $label,
				);
				continue;
			}

			$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
			$operation  = is_array( $action['operation'] ?? null ) ? $action['operation'] : array();
			$converted  = RWGA_Plan_Condition_Converter::convert( $conditions, $operation );

			$rule_id = self::create_rule( $label, $converted, $context, $action );
			if ( ! $rule_id ) {
				$needs[] = array(
					'index'  => $position,
					'label'  => $label,
					'reason' => __( 'No usable conditions were available to build a rule (some may require GeoCore Pro). Configure this one manually.', 'reactwoo-geo-ai' ),
				);
				continue;
			}

			$verified = self::verify_created_rule( $rule_id );
			if ( ! is_array( $verified ) || empty( $verified['valid'] ) ) {
				$needs[] = array(
					'index'               => $position,
					'label'               => $label,
					'reason'              => __( 'The targeting rule could not be verified after creation.', 'reactwoo-geo-ai' ),
					'created_object_id'   => $rule_id,
					'created_object_type' => class_exists( 'RWGC_Visibility_Rule_CPT', false )
						? RWGC_Visibility_Rule_CPT::POST_TYPE
						: 'rwgc_visibility_rule',
					'verification_reason' => is_array( $verified ) ? (string) ( $verified['reason'] ?? '' ) : 'verification_failed',
				);
				continue;
			}

			$created[] = array(
				'index'      => $position,
				'id'         => $rule_id,
				'rule_id'    => $rule_id,
				'title'      => $label,
				'rule_label' => $label,
				'type'       => $type,
				'edit_url'   => (string) ( $verified['edit_url'] ?? '' ),
				'can_edit'   => ! empty( $verified['can_edit'] ),
				'verified'   => true,
				'warnings'   => $converted['warnings'],
			);

			if ( RWGA_Geo_Action_Types::CREATE_VARIANT === $type ) {
				$manual[] = array(
					'index'   => $position,
					'label'   => $label,
					'rule_id' => $rule_id,
					'reason'  => __( 'Create the page variant and attach this draft rule to it.', 'reactwoo-geo-ai' ),
				);
			} elseif ( RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING === $type ) {
				$manual[] = array(
					'index'   => $position,
					'label'   => $label,
					'rule_id' => $rule_id,
					'reason'  => __( 'Attach this draft rule to the target page to apply the updated targeting.', 'reactwoo-geo-ai' ),
				);
			}
		}

		return array(
			'executed'        => ! empty( $created ) || ! empty( $preview ),
			'created_rules'   => $created,
			'manual_steps'    => $manual,
			'preview_only'    => $preview,
			'needs_attention' => $needs,
			'message'         => self::summary_message( $created, $manual, $preview, $needs ),
		);
	}

	/**
	 * @param string                                                                 $label     Rule title.
	 * @param array{conditions:array<int,array<string,mixed>>,mode:string,warnings:array} $converted Converted conditions.
	 * @param array<string,mixed>                                                    $context   Execution context.
	 * @param array<string,mixed>                                                    $action    Planner action.
	 * @return int Post ID, or 0 on failure.
	 */
	private static function create_rule( $label, array $converted, array $context = array(), array $action = array() ) {
		$rows = $converted['conditions'];
		if ( empty( $rows ) ) {
			return 0;
		}

		$set = array(
			'schema_version' => class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ? RWGC_Targeting_Rule_Set_Schema::VERSION : 2,
			'enabled'        => true,
			'mode'           => $converted['mode'],
			'match'          => 'all',
			'rules'          => array(
				array(
					'id'         => 'rule_assistant',
					'label'      => $label,
					'match'      => 'all',
					'conditions' => $rows,
				),
			),
		);

		if ( class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			$sanitized = RWGC_Targeting_Rule_Set_Schema::sanitize( $set );
			if ( null === $sanitized ) {
				return 0;
			}
			$set = $sanitized;
		}

		if ( ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return 0;
		}

		$portable_json = is_string( $set ) ? $set : wp_json_encode( $set );
		if ( ! is_string( $portable_json ) || '' === $portable_json ) {
			return 0;
		}

		$post_id = (int) RWGC_Visibility_Rule_Repository::save( $label, 'draft', $portable_json, 0 );

		if ( $post_id > 0 && function_exists( 'update_post_meta' ) ) {
			$target    = is_array( $action['target'] ?? null ) ? $action['target'] : array();
			$resolved  = is_array( $target['user_resolved'] ?? null ) ? $target['user_resolved'] : array();
			$source_meta = array(
				'created_by'            => 'geo_assistant',
				'source_phrase'         => (string) ( $context['source_phrase'] ?? '' ),
				'interpretation_source' => (string) ( $context['interpretation_source'] ?? '' ),
				'proposal_id'           => (string) ( $context['proposal_id'] ?? '' ),
				'action_type'           => (string) ( $action['type'] ?? '' ),
				'target_type'           => (string) ( $resolved['type'] ?? $target['type'] ?? '' ),
				'target_id'             => (string) ( $resolved['id'] ?? '' ),
				'target_label'          => (string) ( $resolved['name'] ?? $target['label'] ?? '' ),
			);
			update_post_meta( $post_id, '_rwga_assistant_source', wp_json_encode( $source_meta ) );
		}

		return $post_id;
	}

	/**
	 * Confirm the created row is a persisted Geo Core visibility rule the user can open.
	 *
	 * @param int $rule_id Rule post ID.
	 * @return array<string,mixed>|null Verification row, or null after cleanup on failure.
	 */
	private static function verify_created_rule( $rule_id ) {
		$rule_id = absint( $rule_id );
		if ( $rule_id <= 0 ) {
			return null;
		}
		if ( ! class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			return null;
		}
		$check = RWGC_Visibility_Rule_Repository::assistant_rule_verification( $rule_id );
		if ( ! empty( $check['valid'] ) ) {
			return $check;
		}
		RWGC_Visibility_Rule_Repository::delete( $rule_id );
		return $check;
	}

	/**
	 * @param int $rule_id Rule post ID.
	 * @return string
	 */
	private static function edit_url( $rule_id ) {
		if ( class_exists( 'RWGC_Visibility_Rule_Repository', false ) ) {
			$url = RWGC_Visibility_Rule_Repository::get_edit_url( $rule_id );
			if ( '' !== $url ) {
				return $url;
			}
		}
		if ( function_exists( 'admin_url' ) ) {
			return admin_url( 'admin.php?page=rwgc-visibility-rules&rwgc_edit=' . (int) $rule_id );
		}
		return '';
	}

	/**
	 * @param array<string,mixed> $action   Action.
	 * @param int                 $position 1-based index.
	 * @return string
	 */
	private static function action_label( array $action, $position ) {
		$target = is_array( $action['target'] ?? null ) ? $action['target'] : array();
		$name   = trim( (string) ( $target['user_resolved']['name'] ?? ( $target['label'] ?? '' ) ) );
		$type   = (string) ( $action['type'] ?? '' );

		$map = array(
			RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING => __( 'Updated targeting', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::CREATE_VARIANT            => __( 'Page variant rule', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::CREATE_RULE               => __( 'Visibility rule', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::HIDE                      => __( 'Hide rule', 'reactwoo-geo-ai' ),
		);
		$prefix = $map[ $type ] ?? __( 'Geo rule', 'reactwoo-geo-ai' );

		if ( '' !== $name ) {
			return sprintf( '%s — %s', $prefix, $name );
		}
		return sprintf( '%s (action %d)', $prefix, $position );
	}

	/**
	 * @param array<int,array> $created Created rules.
	 * @param array<int,array> $manual  Manual steps.
	 * @param array<int,array> $preview Preview-only actions.
	 * @param array<int,array> $needs   Needs-attention actions.
	 * @return string
	 */
	private static function summary_message( array $created, array $manual, array $preview, array $needs ) {
		$parts = array();
		if ( ! empty( $created ) ) {
			$parts[] = sprintf(
				/* translators: %d: number of draft rules created. */
				_n( 'Created %d draft visibility rule.', 'Created %d draft visibility rules.', count( $created ), 'reactwoo-geo-ai' ),
				count( $created )
			);
		}
		if ( ! empty( $manual ) ) {
			$parts[] = sprintf(
				/* translators: %d: number of manual follow-up steps. */
				_n( '%d step needs manual setup.', '%d steps need manual setup.', count( $manual ), 'reactwoo-geo-ai' ),
				count( $manual )
			);
		}
		if ( ! empty( $preview ) ) {
			$parts[] = sprintf(
				/* translators: %d: number of preview-only actions. */
				_n( '%d preview was skipped (nothing created).', '%d previews were skipped (nothing created).', count( $preview ), 'reactwoo-geo-ai' ),
				count( $preview )
			);
		}
		if ( ! empty( $needs ) ) {
			$parts[] = sprintf(
				/* translators: %d: number of actions needing attention. */
				_n( '%d action could not be created automatically.', '%d actions could not be created automatically.', count( $needs ), 'reactwoo-geo-ai' ),
				count( $needs )
			);
		}
		if ( empty( $parts ) ) {
			return __( 'Nothing was created from this plan.', 'reactwoo-geo-ai' );
		}
		return implode( ' ', $parts );
	}
}
