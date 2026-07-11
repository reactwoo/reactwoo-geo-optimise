<?php
/**
 * Targeting assistant: preview, interpret, execute orchestration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Assistant_Service {

	/**
	 * @param string              $message Raw user message.
	 * @param array<string,mixed> $context Admin context.
	 * @return array<string,mixed>
	 */
	public static function preview( $message, array $context = array() ) {
		$phrase  = RWGA_Local_Intent_Interpreter::normalise( $message );
		$bundle  = self::bundle();
		$debug   = self::bundle_debug( $bundle );
		$flat    = is_array( $bundle ) && is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
		$ctx     = self::resolve_context( $context );

		$detected = self::detect_terms( $phrase, $flat, $ctx );
		$multi    = class_exists( 'RWGA_Variant_Plan_Interpreter', false )
			? RWGA_Variant_Plan_Interpreter::parse( $phrase, $flat, $ctx )
			: array( 'matched' => false );
		if ( empty( $multi['matched'] ) ) {
			$multi = class_exists( 'RWGA_Multi_Variant_Interpreter', false )
				? RWGA_Multi_Variant_Interpreter::parse( $phrase, $flat, $ctx )
				: array( 'matched' => false );
		}

		$summary    = '';
		$confidence = 0.0;
		$missing    = array();

		if ( ! empty( $multi['matched'] ) ) {
			$summary    = (string) ( $multi['summary'] ?? '' );
			$confidence = (float) ( $multi['confidence'] ?? 0.8 );
		} elseif ( ! empty( $detected['intents'] ) ) {
			$summary    = (string) ( $detected['intents'][0]['label'] ?? '' );
			$confidence = (float) ( $detected['intents'][0]['confidence'] ?? 0.5 );
		}

		if ( empty( $debug['bundle_loaded'] ) ) {
			$missing[] = 'intelligence_bundle';
		}

		return array(
			'success'             => true,
			'status'              => 'preview',
			'request_id'          => (int) ( $context['preview_request_id'] ?? 0 ),
			'detected'            => $detected,
			'summary'             => $summary,
			'missing_information' => $missing,
			'confidence'          => round( $confidence, 2 ),
			'debug'               => $debug,
		);
	}

	/**
	 * @param string              $message Raw user message.
	 * @param array<string,mixed> $context Admin context.
	 * @param bool                $include_debug Include debug block.
	 * @return array<string,mixed>
	 */
	public static function interpret( $message, array $context = array(), $include_debug = false ) {
		$ctx    = self::resolve_context( $context );
		$raw    = RWGA_Local_Intent_Interpreter::interpret( $message, $ctx );
		$bundle = self::bundle();
		$debug  = self::build_interpret_debug( $message, $raw, $bundle );

		if ( empty( $raw['matched_action'] ) && empty( $raw['compound'] ) && empty( $raw['steps'] ) && empty( $raw['missing_information'] ) && empty( $raw['ambiguities'] ) ) {
			return array(
				'success' => false,
				'status'  => 'error',
				'message' => (string) ( $raw['summary'] ?? __( 'Could not interpret that command.', 'reactwoo-geo-ai' ) ),
				'debug'   => $include_debug ? $debug : null,
			);
		}

		$proposal_ready = ! isset( $raw['proposal_ready'] ) || false !== $raw['proposal_ready'];
		$entities       = array();
		if ( class_exists( 'RWGA_Intelligence_Sync_Service', false ) ) {
			$bundle   = RWGA_Intelligence_Sync_Service::ensure_bundle();
			$entities = is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
		}
		$inferred_plan  = class_exists( 'RWGA_Inferred_Plan_Builder', false )
			? RWGA_Inferred_Plan_Builder::from_interpreter_result( $raw, $entities )
			: null;
		if ( is_array( $inferred_plan ) ) {
			$raw['inferred_plan'] = $inferred_plan;
		}
		$proposal       = self::format_proposal( $raw, $message, $entities, $inferred_plan );
		$meta           = class_exists( 'RWGA_Interpretation_Status', false )
			? RWGA_Interpretation_Status::from_result( $raw )
			: array(
				'status'        => $proposal_ready ? 'complete' : 'needs_clarification',
				'source'        => (string) ( $raw['interpretation_source'] ?? '' ),
				'confidence'    => (float) ( $raw['confidence'] ?? 0 ),
				'can_execute'   => $proposal_ready,
				'clarification' => array( 'question' => '', 'options' => array() ),
				'memory'        => array(),
				'learning'      => array(),
			);
		$id = self::should_persist_proposal( $proposal, $meta ) ? RWGA_Proposal_Store::save( $proposal ) : '';

		$summary = (string) ( $proposal['summary'] ?? '' );
		if ( ! empty( $meta['can_execute'] ) && 'create_geo_variant_plan' === ( $proposal['intent'] ?? '' ) ) {
			$summary .= "\n\n" . __( 'Please confirm before I create anything.', 'reactwoo-geo-ai' );
			$proposal['summary'] = $summary;
		}
		$display_message = self::format_message_with_badge( $summary, $raw, $inferred_plan, $entities );
		$ai_available    = (bool) apply_filters( 'rwga_interpretation_ai_fallback_enabled', false );
		$ambiguities     = isset( $raw['ambiguities'] ) && is_array( $raw['ambiguities'] ) ? $raw['ambiguities'] : array();
		$ai_interpretation = isset( $raw['ai_interpretation'] ) && is_array( $raw['ai_interpretation'] ) ? $raw['ai_interpretation'] : null;

		$response = array(
			'success'             => true,
			'status'              => (string) ( $meta['status'] ?? ( $proposal_ready ? 'complete' : 'needs_clarification' ) ),
			'source'              => (string) ( $meta['source'] ?? '' ),
			'confidence'          => (float) ( $meta['confidence'] ?? $proposal['confidence'] ?? 0 ),
			'can_execute'         => ! empty( $meta['can_execute'] ),
			'message'             => $display_message,
			'proposal_id'         => $id,
			'proposal'            => $proposal,
			'inferred_plan'       => $inferred_plan,
			'ambiguities'         => $ambiguities,
			'ai_interpretation'   => $ai_interpretation,
			'clarification'       => $meta['clarification'] ?? array( 'question' => '', 'options' => array() ),
			'memory'        => $meta['memory'] ?? array(),
			'learning'      => $meta['learning'] ?? array(),
			'ai_available'  => $ai_available,
			'actions'       => self::action_buttons( $meta, $raw, $inferred_plan, $ai_available ),
			'badge'         => self::interpretation_badge( $raw ),
		);
		if ( $include_debug ) {
			$response['debug'] = $debug;
		}
		return $response;
	}

	/**
	 * Build an assistant response from a pre-interpreted raw result (e.g. forced AI).
	 *
	 * @param string              $message Raw user message.
	 * @param array<string,mixed> $raw     Interpreter output.
	 * @param array<string,mixed> $context Admin context.
	 * @param bool                $include_debug Include debug block.
	 * @return array<string,mixed>
	 */
	public static function interpret_from_raw( $message, array $raw, array $context = array(), $include_debug = false ) {
		$bundle = self::bundle();
		$debug  = self::build_interpret_debug( $message, $raw, $bundle );
		$entities = array();
		if ( is_array( $bundle['entities'] ?? null ) ) {
			$entities = $bundle['entities'];
		}
		$inferred_plan = class_exists( 'RWGA_Inferred_Plan_Builder', false )
			? RWGA_Inferred_Plan_Builder::from_interpreter_result( $raw, $entities )
			: null;
		if ( is_array( $inferred_plan ) ) {
			$raw['inferred_plan'] = $inferred_plan;
		}
		$meta     = class_exists( 'RWGA_Interpretation_Status', false )
			? RWGA_Interpretation_Status::from_result( $raw )
			: array( 'status' => 'complete', 'can_execute' => true, 'clarification' => array( 'question' => '', 'options' => array() ), 'memory' => array(), 'learning' => array(), 'source' => '', 'confidence' => 0 );
		$proposal = self::format_proposal( $raw, $message, $entities, $inferred_plan );
		$id       = self::should_persist_proposal( $proposal, $meta ) ? RWGA_Proposal_Store::save( $proposal ) : '';
		$ai_available = (bool) apply_filters( 'rwga_interpretation_ai_fallback_enabled', false );
		$ambiguities       = isset( $raw['ambiguities'] ) && is_array( $raw['ambiguities'] ) ? $raw['ambiguities'] : array();
		$ai_interpretation = isset( $raw['ai_interpretation'] ) && is_array( $raw['ai_interpretation'] ) ? $raw['ai_interpretation'] : null;
		$response = array(
			'success'       => true,
			'status'        => (string) ( $meta['status'] ?? 'complete' ),
			'source'        => (string) ( $meta['source'] ?? ( $raw['interpretation_source'] ?? '' ) ),
			'confidence'    => (float) ( $meta['confidence'] ?? $proposal['confidence'] ?? 0 ),
			'can_execute'   => ! empty( $meta['can_execute'] ),
			'message'       => self::format_message_with_badge( (string) ( $proposal['summary'] ?? '' ), $raw, $inferred_plan, $entities ),
			'proposal_id'   => $id,
			'proposal'      => $proposal,
			'inferred_plan' => $inferred_plan,
			'ambiguities'         => $ambiguities,
			'ai_interpretation'   => $ai_interpretation,
			'clarification' => $meta['clarification'] ?? array( 'question' => '', 'options' => array() ),
			'memory'        => $meta['memory'] ?? array(),
			'learning'      => $meta['learning'] ?? array(),
			'ai_available'  => $ai_available,
			'actions'       => self::action_buttons( $meta, $raw, $inferred_plan, $ai_available ),
			'badge'         => self::interpretation_badge( $raw ),
		);
		if ( $include_debug ) {
			$response['debug'] = $debug;
		}
		return $response;
	}

	/**
	 * Confirm an inferred split and return an executable proposal.
	 *
	 * @param string              $message       Original user message.
	 * @param array<string,mixed> $inferred_plan Inferred plan payload.
	 * @param array<string,mixed> $context       Admin context.
	 * @param string              $source        Interpretation source key.
	 * @param bool                $include_debug Include debug block.
	 * @return array<string,mixed>
	 */
	public static function confirm_inferred_split( $message, array $inferred_plan, array $context = array(), $source = 'local_parser', $include_debug = false ) {
		$entities = array();
		if ( class_exists( 'RWGA_Intelligence_Sync_Service', false ) ) {
			$bundle   = RWGA_Intelligence_Sync_Service::ensure_bundle();
			$entities = is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
		}
		$params = class_exists( 'RWGA_Inferred_Plan_Builder', false )
			? RWGA_Inferred_Plan_Builder::to_confirmed_params( $inferred_plan, $entities )
			: array();
		$action = (string) ( $params['_matched_action'] ?? 'geocore_create_variant_plan_with_conditions' );
		unset( $params['_matched_action'] );

		$raw = array(
			'intent'                => 'create_geo_variant_plan',
			'matched_action'        => $action,
			'confidence'            => 0.9,
			'proposal_ready'        => true,
			'interpretation_source' => $source,
			'interpretation_status' => RWGA_Interpretation_Status::COMPLETE,
			'params'                => $params,
			'summary'               => __( 'Split confirmed. You can create the setup when ready.', 'reactwoo-geo-ai' ),
			'inferred_plan'         => $inferred_plan,
		);

		if ( class_exists( 'RWGA_Variant_Plan_Interpreter', false ) ) {
			$plan = RWGA_Variant_Plan_Interpreter::parse( RWGA_Local_Intent_Interpreter::normalise( $message ), $entities, self::resolve_context( $context ) );
			if ( ! empty( $plan['matched'] ) && ! empty( $plan['steps'] ) ) {
				$raw['steps']   = $plan['steps'];
				$raw['summary'] = (string) ( $plan['summary'] ?? $raw['summary'] );
			}
		}

		$proposal = self::format_proposal( $raw, $message, $entities, $inferred_plan );
		$id       = RWGA_Proposal_Store::save( $proposal );
		$ai_available = (bool) apply_filters( 'rwga_interpretation_ai_fallback_enabled', false );
		$response = array(
			'success'       => true,
			'status'        => RWGA_Interpretation_Status::COMPLETE,
			'source'        => RWGA_Interpretation_Status::normalise_source( $source ),
			'confidence'    => (float) ( $raw['confidence'] ?? 0.9 ),
			'can_execute'   => true,
			'message'       => (string) ( $raw['summary'] ?? '' ),
			'proposal_id'   => $id,
			'proposal'      => $proposal,
			'inferred_plan' => $inferred_plan,
			'clarification' => array( 'question' => '', 'options' => array() ),
			'ai_available'  => $ai_available,
			'actions'       => self::action_buttons(
				array(
					'status'      => RWGA_Interpretation_Status::COMPLETE,
					'can_execute' => true,
				),
				$raw,
				$inferred_plan,
				$ai_available
			),
			'badge'         => self::interpretation_badge( $raw ),
		);
		if ( $include_debug ) {
			$response['debug'] = self::build_interpret_debug( $message, $raw, self::bundle() );
		}
		return $response;
	}

	/**
	 * Confirm an ambiguous interpretation after user review.
	 *
	 * @param string              $message       Original message.
	 * @param array<string,mixed> $proposal_data Confirmed proposal payload.
	 * @param array<string,mixed> $context       Context.
	 * @param string              $source        Source key.
	 * @param bool                $include_debug Debug flag.
	 * @return array<string,mixed>
	 */
	public static function confirm_interpretation( $message, array $payload, array $context = array(), $source = 'local_parser', $include_debug = false ) {
		unset( $context );
		$ambiguities     = isset( $payload['ambiguities'] ) && is_array( $payload['ambiguities'] ) ? $payload['ambiguities'] : array();
		$resolutions     = isset( $payload['resolutions'] ) && is_array( $payload['resolutions'] ) ? $payload['resolutions'] : array();
		$ai_interpretation = isset( $payload['ai_interpretation'] ) && is_array( $payload['ai_interpretation'] ) ? $payload['ai_interpretation'] : array();
		$base            = isset( $payload['base'] ) && is_array( $payload['base'] ) ? $payload['base'] : array();

		foreach ( $ambiguities as $idx => $row ) {
			$field = (string) ( $row['field'] ?? '' );
			if ( '' !== $field && isset( $resolutions[ $field ] ) ) {
				$ambiguities[ $idx ]['likely'] = (string) $resolutions[ $field ];
			}
		}

		$raw = class_exists( 'RWGA_AI_Interpretation_Builder', false )
			? RWGA_AI_Interpretation_Builder::build_confirmed_raw( $message, $ambiguities, $ai_interpretation, $base )
			: array_merge( $base, array( 'proposal_ready' => true, 'interpretation_status' => RWGA_Interpretation_Status::COMPLETE ) );
		$raw['interpretation_source'] = $source;
		$raw['ambiguities']           = $ambiguities;

		$entities = array();
		if ( class_exists( 'RWGA_Intelligence_Sync_Service', false ) ) {
			$bundle   = RWGA_Intelligence_Sync_Service::ensure_bundle();
			$entities = is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
		}

		if ( class_exists( 'RWGA_Interpretation_Memory_Matcher', false ) ) {
			RWGA_Interpretation_Memory_Matcher::remember( $message, $raw, $entities );
		}

		if ( class_exists( 'RWGA_Planner_Learned_Patterns', false ) ) {
			$plan = isset( $payload['interpretation_plan'] ) && is_array( $payload['interpretation_plan'] )
				? $payload['interpretation_plan']
				: ( isset( $raw['interpretation_plan'] ) && is_array( $raw['interpretation_plan'] ) ? $raw['interpretation_plan'] : null );
			if ( is_array( $plan ) ) {
				RWGA_Planner_Learned_Patterns::save_from_plan( $plan );
			}
		}

		unset( $raw['ambiguities'] );
		$raw['interpretation_status'] = RWGA_Interpretation_Status::COMPLETE;
		$raw['proposal_ready']        = true;

		$proposal     = self::format_proposal( $raw, $message, $entities, null );
		$id           = RWGA_Proposal_Store::save( $proposal );
		$ai_available = (bool) apply_filters( 'rwga_interpretation_ai_fallback_enabled', false );
		$summary      = __( 'Interpretation confirmed. You can create the setup when ready.', 'reactwoo-geo-ai' );
		return array(
			'success'           => true,
			'status'            => RWGA_Interpretation_Status::COMPLETE,
			'source'            => RWGA_Interpretation_Status::normalise_source( $source ),
			'confidence'        => (float) ( $raw['confidence'] ?? 0.9 ),
			'can_execute'       => true,
			'message'           => $summary,
			'proposal_id'       => $id,
			'proposal'          => $proposal,
			'ambiguities'       => array(),
			'ai_interpretation' => null,
			'clarification'     => array( 'question' => '', 'options' => array() ),
			'ai_available'      => $ai_available,
			'actions'           => self::action_buttons(
				array(
					'status'      => RWGA_Interpretation_Status::COMPLETE,
					'can_execute' => true,
				),
				$raw,
				null,
				$ai_available
			),
			'badge'             => self::interpretation_badge( $raw ),
			'debug'             => $include_debug ? self::build_interpret_debug( $message, $raw, self::bundle() ) : null,
		);
	}

	/**
	 * Whether a proposal should be persisted to the store.
	 *
	 * Executable proposals are always stored. Card-based plans are stored even
	 * while unresolved so the client can apply field-level resolutions through
	 * the execute endpoint (the server re-validates before creating anything).
	 *
	 * @param array<string,mixed> $proposal Formatted proposal.
	 * @param array<string,mixed> $meta     Interpretation status meta.
	 * @return bool
	 */
	private static function should_persist_proposal( array $proposal, array $meta ) {
		if ( ! empty( $meta['can_execute'] ) ) {
			return true;
		}
		return ! empty( $proposal['action_cards'] ) && is_array( $proposal['action_cards'] );
	}

	/**
	 * @param string              $proposal_id Proposal ID.
	 * @param array<int,array<string,mixed>> $resolutions Card resolution rows from the client.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function execute( $proposal_id, array $resolutions = array() ) {
		$proposal = RWGA_Proposal_Store::get( $proposal_id );
		if ( ! $proposal ) {
			return new WP_Error( 'rwga_proposal_expired', __( 'Proposal expired or not found. Send your message again.', 'reactwoo-geo-ai' ), array( 'status' => 404 ) );
		}

		if ( ! empty( $proposal['invalid_interpretation'] ) ) {
			return new WP_Error(
				'rwga_plan_invalid',
				__( 'This interpretation was split incorrectly. Resolve or ask AI to re-check before creating anything.', 'reactwoo-geo-ai' ),
				array( 'status' => 409 )
			);
		}

		$plan     = is_array( $proposal['interpretation_plan'] ?? null ) ? $proposal['interpretation_plan'] : array();
		$actions  = is_array( $plan['actions'] ?? null ) ? $plan['actions'] : array();
		$entities = is_array( $plan['entities'] ?? null ) ? $plan['entities'] : array();

		if ( ! empty( $actions ) ) {
			if ( ! is_array( $resolutions ) ) {
				$resolutions = array();
			}
			if ( ! empty( $resolutions ) && class_exists( 'RWGA_Card_Resolution_Applier', false ) ) {
				$actions = RWGA_Card_Resolution_Applier::apply( $actions, $resolutions );
			}
			if ( empty( $actions ) ) {
				return new WP_Error(
					'rwga_plan_empty',
					__( 'Every action was removed, so there is nothing to create.', 'reactwoo-geo-ai' ),
					array( 'status' => 400 )
				);
			}

			$validated = self::validate_plan_actions_for_execute( $actions, $entities );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			$proposal['interpretation_plan']['actions'] = $actions;
			if ( is_array( $validated ) ) {
				$proposal['action_cards']             = $validated['cards'];
				$proposal['fields_needing_attention'] = $validated['fields_needing_attention'];
				$proposal['requires_resolution']      = $validated['requires_resolution'];
				$proposal['can_execute']              = empty( $validated['requires_resolution'] );
			}
		} else {
			$stored_error = self::validate_stored_action_cards( $proposal );
			if ( is_wp_error( $stored_error ) ) {
				return $stored_error;
			}
		}

		$proposal['proposal_id'] = (string) $proposal_id;

		$action = (string) ( $proposal['matched_action'] ?? '' );
		$steps  = isset( $proposal['steps'] ) && is_array( $proposal['steps'] ) ? $proposal['steps'] : array();

		/**
		 * Execute a confirmed assistant proposal (no-op until handlers register).
		 *
		 * @param array<string,mixed>            $result      Filtered result (null until a handler runs).
		 * @param array<string,mixed>            $proposal    Stored proposal.
		 * @param string                         $action      Action key.
		 * @param array<int,array<string,mixed>> $resolutions Client card resolutions.
		 */
		$result = apply_filters( 'rwga_assistant_execute_proposal', null, $proposal, $action, $resolutions );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( null === $result ) {
			$result = array(
				'executed'        => false,
				'message'         => __( 'Proposal confirmed. Complete setup in the guided workflow.', 'reactwoo-geo-ai' ),
				'redirect_steps'  => self::build_redirect_steps( $proposal ),
				'matched_action'  => $action,
				'steps'           => $steps,
			);
		}

		$rule_error = self::rule_create_failure_from_result( $result, $proposal );
		if ( is_wp_error( $rule_error ) ) {
			return $rule_error;
		}

		RWGA_Proposal_Store::delete( $proposal_id );

		if ( class_exists( 'RWGA_Learning_Event_Service', false ) ) {
			do_action(
				'rwga_intelligence_interpretation_feedback',
				$proposal,
				array(
					'outcome'          => 'executed',
					'approved_by_user' => true,
					'raw_phrase'       => (string) ( $proposal['original_message'] ?? '' ),
					'action_type'      => self::primary_action_type( $proposal ),
					'resolved_fields'  => self::resolved_fields_from_resolutions( $resolutions ),
					'proposal_id'      => (string) $proposal_id,
				)
			);
		}

		return array(
			'success' => true,
			'status'  => 'executed',
			'result'  => $result,
		) + self::execute_rule_response_fields( $result );
	}

	/**
	 * Top-level rule fields for clients that do not read result.created_rules[0].
	 *
	 * @param array<string,mixed> $result Execute handler result.
	 * @return array<string,mixed>
	 */
	private static function execute_rule_response_fields( array $result ) {
		$created = isset( $result['created_rules'] ) && is_array( $result['created_rules'] ) ? $result['created_rules'] : array();
		$first   = ! empty( $created[0] ) && is_array( $created[0] ) ? $created[0] : array();
		if ( empty( $first['id'] ) || empty( $first['verified'] ) || empty( $first['edit_url'] ) ) {
			return array();
		}
		return array(
			'rule_id'    => (int) $first['id'],
			'post_id'    => (int) $first['id'],
			'rule_label' => (string) ( $first['rule_label'] ?? $first['title'] ?? '' ),
			'edit_url'   => (string) $first['edit_url'],
			'created_rule' => $first,
		);
	}

	/**
	 * Fail execute when rule actions were expected but no verified Geo Core rule was created.
	 *
	 * @param array<string,mixed> $result   Execute handler result.
	 * @param array<string,mixed> $proposal Stored proposal.
	 * @return true|\WP_Error
	 */
	private static function rule_create_failure_from_result( array $result, array $proposal ) {
		$plan    = is_array( $proposal['interpretation_plan'] ?? null ) ? $proposal['interpretation_plan'] : array();
		$actions = is_array( $plan['actions'] ?? null ) ? $plan['actions'] : array();
		$expects = false;
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$type = (string) ( $action['type'] ?? '' );
			if ( in_array( $type, array( RWGA_Geo_Action_Types::CREATE_RULE, RWGA_Geo_Action_Types::CREATE_VARIANT, RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING, RWGA_Geo_Action_Types::HIDE ), true ) ) {
				$expects = true;
				break;
			}
		}
		if ( ! $expects ) {
			return true;
		}
		$created = isset( $result['created_rules'] ) && is_array( $result['created_rules'] ) ? $result['created_rules'] : array();
		if ( ! empty( $created ) ) {
			foreach ( $created as $row ) {
				if ( ! is_array( $row ) || empty( $row['verified'] ) || empty( $row['edit_url'] ) ) {
					return new WP_Error(
						'rule_create_failed',
						__( 'The targeting rule could not be created.', 'reactwoo-geo-ai' ),
						array(
							'status'  => 500,
							'code'    => 'rule_create_failed',
							'details' => array(
								'reason'              => 'unverified_created_rule',
								'created_object_id'   => (int) ( $row['id'] ?? 0 ),
								'created_object_type' => class_exists( 'RWGC_Visibility_Rule_CPT', false )
									? RWGC_Visibility_Rule_CPT::POST_TYPE
									: 'rwgc_visibility_rule',
								'can_edit'            => ! empty( $row['can_edit'] ),
								'edit_url'            => (string) ( $row['edit_url'] ?? '' ),
							),
						)
					);
				}
			}
			return true;
		}
		$needs = isset( $result['needs_attention'] ) && is_array( $result['needs_attention'] ) ? $result['needs_attention'] : array();
		if ( empty( $needs ) ) {
			return true;
		}
		$detail = is_array( $needs[0] ) ? $needs[0] : array();
		return new WP_Error(
			'rule_create_failed',
			__( 'The targeting rule could not be created.', 'reactwoo-geo-ai' ),
			array(
				'status'  => 500,
				'code'    => 'rule_create_failed',
				'details' => array(
					'reason'              => (string) ( $detail['verification_reason'] ?? $detail['reason'] ?? 'create_failed' ),
					'created_object_id'   => (int) ( $detail['created_object_id'] ?? 0 ),
					'created_object_type' => (string) ( $detail['created_object_type'] ?? '' ),
					'can_edit'            => false,
				),
			)
		);
	}

	/**
	 * Rebuild action cards from planner actions and gate execution until ready.
	 *
	 * @param array<int,array<string,mixed>> $actions  Planner actions (after resolutions).
	 * @param array<int,array<string,mixed>> $entities Entity registry.
	 * @return array{cards:array<int,array<string,mixed>>,fields_needing_attention:int,requires_resolution:bool}|\WP_Error
	 */
	public static function validate_plan_actions_for_execute( array $actions, array $entities = array() ) {
		if ( empty( $actions ) ) {
			return new WP_Error(
				'rwga_plan_empty',
				__( 'Every action was removed, so there is nothing to create.', 'reactwoo-geo-ai' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( 'RWGA_Planner_Action_Card_Builder', false ) ) {
			return array(
				'cards'                    => array(),
				'fields_needing_attention' => 0,
				'requires_resolution'      => false,
			);
		}

		$rebuilt = RWGA_Planner_Action_Card_Builder::build( $actions, array(), $entities );
		$cards   = (array) ( $rebuilt['cards'] ?? array() );

		if ( ! empty( $rebuilt['requires_resolution'] ) ) {
			return self::plan_unresolved_error( $cards );
		}

		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			if ( RWGA_Planner_Action_Card_Builder::STATUS_READY !== (string) ( $card['status'] ?? '' ) ) {
				return self::plan_unresolved_error( array( $card ) );
			}
			foreach ( (array) ( $card['condition_rows'] ?? array() ) as $row ) {
				if ( ! is_array( $row ) || ! empty( $row['is_note'] ) ) {
					continue;
				}
				if ( 'valid' !== (string) ( $row['status'] ?? '' ) ) {
					return self::plan_unresolved_error( $cards );
				}
				if ( 'condition_group' === (string) ( $row['type'] ?? '' ) ) {
					foreach ( (array) ( $row['children'] ?? array() ) as $child ) {
						if ( is_array( $child ) && 'valid' !== (string) ( $child['status'] ?? '' ) ) {
							return self::plan_unresolved_error( $cards );
						}
					}
				}
			}
		}

		return $rebuilt;
	}

	/**
	 * Validate a stored proposal that has no interpretation plan (legacy path).
	 *
	 * @param array<string,mixed> $proposal Stored proposal.
	 * @return true|\WP_Error
	 */
	private static function validate_stored_action_cards( array $proposal ) {
		$cards = isset( $proposal['action_cards'] ) && is_array( $proposal['action_cards'] ) ? $proposal['action_cards'] : array();

		if ( ! empty( $proposal['requires_resolution'] ) ) {
			return self::plan_unresolved_error( $cards );
		}

		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			if ( 'ready' !== (string) ( $card['status'] ?? '' ) ) {
				return self::plan_unresolved_error( array( $card ) );
			}
			foreach ( (array) ( $card['condition_rows'] ?? array() ) as $row ) {
				if ( ! is_array( $row ) || ! empty( $row['is_note'] ) ) {
					continue;
				}
				if ( 'valid' !== (string) ( $row['status'] ?? '' ) ) {
					return new WP_Error(
						'rwga_plan_unresolved',
						__( 'Some conditions still need resolving before this setup can be created.', 'reactwoo-geo-ai' ),
						array( 'status' => 409, 'unresolved' => self::unresolved_labels_from_cards( $cards ) )
					);
				}
				if ( 'condition_group' === (string) ( $row['type'] ?? '' ) ) {
					foreach ( (array) ( $row['children'] ?? array() ) as $child ) {
						if ( is_array( $child ) && 'valid' !== (string) ( $child['status'] ?? '' ) ) {
							return new WP_Error(
								'rwga_plan_unresolved',
								__( 'Some conditions still need resolving before this setup can be created.', 'reactwoo-geo-ai' ),
								array( 'status' => 409, 'unresolved' => self::unresolved_labels_from_cards( $cards ) )
							);
						}
					}
				}
			}
		}

		return true;
	}

	/**
	 * @param array<int,array<string,mixed>> $cards Action cards.
	 * @return \WP_Error
	 */
	private static function plan_unresolved_error( array $cards ) {
		$labels  = self::unresolved_labels_from_cards( $cards );
		$details = class_exists( 'RWGA_Planner_Action_Card_Builder', false )
			? RWGA_Planner_Action_Card_Builder::unresolved_field_details( $cards )
			: array();
		$message = ! empty( $labels )
			? sprintf(
				/* translators: %s: comma-separated unresolved field labels */
				__( 'This rule still has unresolved fields: %s.', 'reactwoo-geo-ai' ),
				implode( ', ', $labels )
			)
			: __( 'This rule still has unresolved fields.', 'reactwoo-geo-ai' );

		return new WP_Error(
			'rwga_plan_unresolved',
			$message,
			array(
				'status'             => 409,
				'code'               => 'unresolved_fields',
				'unresolved'         => $labels,
				'unresolved_details' => $details,
				'action_cards'       => $cards,
				'fields_needing_attention' => class_exists( 'RWGA_Planner_Action_Card_Builder', false )
					? count( $labels )
					: 0,
				'requires_resolution' => true,
			)
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $cards Action cards.
	 * @return array<int,string>
	 */
	private static function unresolved_labels_from_cards( array $cards ) {
		return class_exists( 'RWGA_Planner_Action_Card_Builder', false )
			? RWGA_Planner_Action_Card_Builder::unresolved_field_labels( $cards )
			: array();
	}

	/**
	 * @param array<string,mixed> $proposal Proposal.
	 * @return string
	 */
	private static function primary_action_type( array $proposal ) {
		$plan = is_array( $proposal['interpretation_plan'] ?? null ) ? $proposal['interpretation_plan'] : array();
		$actions = is_array( $plan['actions'] ?? null ) ? $plan['actions'] : array();
		if ( ! empty( $actions[0]['type'] ) ) {
			return (string) $actions[0]['type'];
		}
		$cards = is_array( $proposal['action_cards'] ?? null ) ? $proposal['action_cards'] : array();
		if ( ! empty( $cards[0]['type'] ) ) {
			return (string) $cards[0]['type'];
		}
		return '';
	}

	/**
	 * @param array<int,array<string,mixed>> $resolutions Client resolution rows.
	 * @return array<string,string>
	 */
	private static function resolved_fields_from_resolutions( array $resolutions ) {
		$out = array();
		foreach ( $resolutions as $row ) {
			if ( ! is_array( $row ) || 'choose' !== (string) ( $row['action'] ?? '' ) ) {
				continue;
			}
			$field = (string) ( $row['field'] ?? '' );
			if ( '' === $field ) {
				continue;
			}
			$out[ $field ] = (string) ( $row['id'] ?? $row['label'] ?? '' );
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private static function bundle() {
		if ( ! class_exists( 'RWGA_Intelligence_Sync_Service', false ) ) {
			return null;
		}
		return RWGA_Intelligence_Sync_Service::ensure_bundle();
	}

	/**
	 * @param array<string,mixed>|null $bundle Bundle.
	 * @return array<string,mixed>
	 */
	private static function bundle_debug( $bundle ) {
		$status = class_exists( 'RWGA_Intelligence_Sync_Service', false )
			? RWGA_Intelligence_Sync_Service::get_status()
			: array();
		return array(
			'bundle_loaded'    => is_array( $bundle ) && ! empty( $bundle['phrase_patterns'] ),
			'bundle_source'    => (string) ( $status['source'] ?? ( is_array( $bundle ) ? (string) ( $bundle['_source'] ?? 'unknown' ) : 'none' ) ),
			'pattern_count'    => is_array( $bundle ) ? count( (array) ( $bundle['phrase_patterns'] ?? array() ) ) : 0,
			'entity_count'     => is_array( $bundle ) ? count( (array) ( $bundle['entities'] ?? array() ) ) : 0,
			'bundle_version'   => (string) ( $status['version'] ?? '' ),
			'last_sync'        => (int) ( $status['last_sync'] ?? 0 ),
			'last_error'       => (string) ( $status['last_error'] ?? '' ),
		);
	}

	/**
	 * @param string              $message Raw message.
	 * @param array<string,mixed> $raw     Interpreter result.
	 * @param array<string,mixed>|null $bundle Bundle.
	 * @return array<string,mixed>
	 */
	private static function build_interpret_debug( $message, array $raw, $bundle ) {
		$base = self::bundle_debug( $bundle );
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $message );
		$base['normalised_input']    = $phrase;
		$base['detected_intent']     = (string) ( $raw['intent'] ?? '' );
		$base['matched_patterns']    = isset( $raw['_debug_patterns'] ) ? $raw['_debug_patterns'] : array();
		$base['detected_entities']   = isset( $raw['_debug_entities'] ) ? $raw['_debug_entities'] : array();
		$base['proposed_action']     = (string) ( $raw['matched_action'] ?? '' );
		$base['confidence']          = (float) ( $raw['confidence'] ?? 0 );
		$base['missing_information'] = isset( $raw['missing_information'] ) ? $raw['missing_information'] : array();
		$base['variant_count']       = (int) ( $raw['variant_count'] ?? 0 );
		$base['duplicate_count']     = (int) ( $raw['duplicate_count'] ?? ( $raw['params']['duplicate_count'] ?? 0 ) );
		$base['source_targeting']    = $raw['source_targeting'] ?? ( $raw['params']['source_targeting'] ?? null );
		$base['variant_groups']      = isset( $raw['variant_groups'] ) && is_array( $raw['variant_groups'] )
			? $raw['variant_groups']
			: ( $raw['_debug_entities']['variant_groups'] ?? array() );
		$base['matched_terms']       = $raw['_debug_entities']['matched_terms'] ?? array();
		$parser_debug                = is_array( $raw['_debug_entities'] ?? null ) ? $raw['_debug_entities'] : array();
		foreach ( array( 'parser_used', 'variant_plan_terms_detected', 'source_page_ref', 'total_version_count', 'segments', 'fallback_pattern_match_used', 'warnings', 'countries_detected' ) as $key ) {
			if ( isset( $parser_debug[ $key ] ) ) {
				$base[ $key ] = $parser_debug[ $key ];
			}
		}
		if ( class_exists( 'RWGA_Page_Reference_Resolver', false ) ) {
			$page = RWGA_Page_Reference_Resolver::detect( $phrase );
			if ( $page ) {
				$base['detected_page'] = array(
					'label' => (string) ( $page['label'] ?? $page['value'] ?? '' ),
					'value' => (string) ( $page['value'] ?? '' ),
				);
			}
		}
		$base['proposal'] = $raw;
		$base['_interpretation_trace'] = isset( $raw['_interpretation_trace'] ) ? $raw['_interpretation_trace'] : array();
		$base['interpretation_source'] = (string) ( $raw['interpretation_source'] ?? '' );
		if ( class_exists( 'RWGA_Interpretation_Status', false ) ) {
			$meta = RWGA_Interpretation_Status::from_result( $raw );
			$base['interpretation_status'] = $meta['status'];
			$base['can_execute']           = $meta['can_execute'];
			$base['inferred_plan']         = $raw['inferred_plan'] ?? null;
			$base['ambiguities']           = $raw['ambiguities'] ?? array();
			$base['ai_interpretation']     = $raw['ai_interpretation'] ?? null;
			$base['ambiguity_gate']        = $base['_interpretation_trace']['ambiguity_gate'] ?? array();
			$base['ai_available']          = (bool) apply_filters( 'rwga_interpretation_ai_fallback_enabled', false );
			$base['ai_called']             = ! empty( $base['_interpretation_trace']['ai_fallback']['called'] );
			$base['final']                 = array(
				'source'     => $meta['source'],
				'status'     => $meta['status'],
				'validation' => ! empty( $meta['can_execute'] ) ? 'passed' : 'incomplete',
			);
			$base['memory'] = array(
				'local_attempted'  => ! empty( $base['_interpretation_trace']['interpretation_memory']['attempted'] ),
				'local_matched'    => ! empty( $base['_interpretation_trace']['interpretation_memory']['matched'] ),
				'remote_attempted' => ! empty( $base['_interpretation_trace']['interpretation_memory']['attempted'] ),
				'remote_matched'   => 'remote_memory' === ( $meta['source'] ?? '' ),
			);
			if ( ! empty( $parser_debug['countries_per_clause'] ) ) {
				$base['clauses'] = $parser_debug['countries_per_clause'];
			}
		}
		return $base;
	}

	/**
	 * @param array<string,mixed> $raw Interpreter output.
	 * @return string
	 */
	private static function interpretation_badge( array $raw ) {
		$status = (string) ( $raw['interpretation_status'] ?? '' );
		if ( RWGA_Interpretation_Status::NEEDS_CONFIRMATION === $status || ! empty( $raw['ambiguities'] ) ) {
			return __( 'Needs confirmation', 'reactwoo-geo-ai' );
		}
		if ( in_array( $status, array( RWGA_Interpretation_Status::PARTIAL, RWGA_Interpretation_Status::NEEDS_CLARIFICATION, RWGA_Interpretation_Status::NEEDS_AI, RWGA_Interpretation_Status::AMBIGUOUS ), true ) ) {
			return __( 'Needs clarification', 'reactwoo-geo-ai' );
		}
		$source = RWGA_Interpretation_Status::normalise_source( (string) ( $raw['interpretation_source'] ?? '' ) );
		if ( 'local_memory' === $source || 'remote_memory' === $source ) {
			return __( 'Learned interpretation', 'reactwoo-geo-ai' );
		}
		if ( 'ai_fallback' === $source ) {
			return __( 'AI-assisted interpretation', 'reactwoo-geo-ai' );
		}
		if ( in_array( $source, array( 'local_parser', 'pattern_bundle', 'parser_hints' ), true ) ) {
			$ready = ! isset( $raw['proposal_ready'] ) || false !== $raw['proposal_ready'];
			if ( $ready && RWGA_Interpretation_Status::COMPLETE === ( $status ?: RWGA_Interpretation_Status::infer_status( $raw, (float) ( $raw['confidence'] ?? 0 ) ) ) ) {
				return __( 'Local smart action', 'reactwoo-geo-ai' );
			}
			return __( 'Needs clarification', 'reactwoo-geo-ai' );
		}
		return '';
	}

	/**
	 * @param string                   $summary       Summary text.
	 * @param array<string,mixed>      $raw           Raw interpreter output.
	 * @param array<string,mixed>|null $inferred_plan Inferred plan.
	 * @param array<int,array>         $entities      Entities.
	 * @return string
	 */
	private static function format_message_with_badge( $summary, array $raw, $inferred_plan = null, array $entities = array() ) {
		unset( $entities );
		$status      = (string) ( $raw['interpretation_status'] ?? '' );
		$ambiguities = isset( $raw['ambiguities'] ) && is_array( $raw['ambiguities'] ) ? $raw['ambiguities'] : array();
		$ai_interp   = isset( $raw['ai_interpretation'] ) && is_array( $raw['ai_interpretation'] ) ? $raw['ai_interpretation'] : array();

		if ( RWGA_Interpretation_Status::NEEDS_CONFIRMATION === $status || ! empty( $ambiguities ) ) {
			$parts = array( (string) ( $raw['summary'] ?? $summary ) );
			if ( ! empty( $ai_interp['likely_meaning'] ) ) {
				$parts[] = (string) $ai_interp['likely_meaning'];
			}
			if ( ! empty( $ai_interp['reason'] ) ) {
				$parts[] = __( 'Why I’m asking:', 'reactwoo-geo-ai' ) . "\n" . (string) $ai_interp['reason'];
			} elseif ( ! empty( $ambiguities ) ) {
				$why = array();
				foreach ( $ambiguities as $row ) {
					foreach ( (array) ( $row['notes'] ?? array() ) as $note ) {
						$why[] = (string) $note;
					}
					if ( ! empty( $row['question'] ) ) {
						$why[] = (string) $row['question'];
					}
				}
				if ( ! empty( $why ) ) {
					$parts[] = __( 'Why I’m asking:', 'reactwoo-geo-ai' ) . "\n- " . implode( "\n- ", array_unique( $why ) );
				}
			}
			$parts[] = __( 'Is this interpretation correct?', 'reactwoo-geo-ai' );
			return implode( "\n\n", array_filter( $parts ) );
		}

		if ( is_array( $inferred_plan ) && in_array( $status, array( RWGA_Interpretation_Status::NEEDS_CLARIFICATION, RWGA_Interpretation_Status::PARTIAL, RWGA_Interpretation_Status::NEEDS_AI ), true ) ) {
			$message = (string) ( $raw['summary'] ?? $summary );
			$badge   = self::interpretation_badge( $raw );
			if ( '' !== $badge ) {
				$message .= "\n\n" . $badge;
			}
			return $message;
		}
		$badge = self::interpretation_badge( $raw );
		if ( '' === $badge ) {
			return $summary;
		}
		return $summary . "\n\n" . $badge;
	}

	/**
	 * @param array<string,mixed> $context Context.
	 * @return array<string,mixed>
	 */
	private static function resolve_context( array $context ) {
		if ( class_exists( 'RWGA_Context_Resolver', false ) ) {
			return RWGA_Context_Resolver::resolve( $context );
		}
		return $context;
	}

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $entities Entities.
	 * @param array<string,mixed> $context  Context.
	 * @return array<string,mixed>
	 */
	private static function detect_terms( $phrase, array $entities, array $context ) {
		$intents          = array();
		$entities_out     = array();
		$keywords         = array();
		$variant_groups   = array();
		$source_targeting = null;

		$plan = class_exists( 'RWGA_Variant_Plan_Interpreter', false )
			? RWGA_Variant_Plan_Interpreter::parse( $phrase, $entities, $context )
			: array( 'matched' => false );

		if ( ! empty( $plan['matched'] ) && ! empty( $plan['params']['source_targeting'] ) ) {
			$source    = $plan['params']['source_targeting'];
			$src_label = (string) ( $source['label'] ?? '' );
			if ( ! empty( $source['countries'] ) && class_exists( 'RWGA_Variant_Group_Extractor', false ) ) {
				$src_label = (string) ( $source['targeting_label'] ?? RWGA_Variant_Group_Extractor::label_for_countries( (array) $source['countries'], $entities ) );
			}
			$source_weather_suffix = '';
			if ( ! empty( $source['weather']['mode'] ) && 'any' === $source['weather']['mode'] ) {
				$source_weather_suffix = ' + ' . __( 'all weather', 'reactwoo-geocore' );
			} elseif ( ! empty( $source['weather']['condition'] ) ) {
				$source_weather_suffix = ' + ' . (string) $source['weather']['condition'];
			}
			$source_targeting = array(
				'label' => sprintf(
					/* translators: %s: country targeting label */
					__( 'Original: %s', 'reactwoo-geocore' ),
					$src_label . $source_weather_suffix
				),
			);
			$dup_count = (int) ( $plan['params']['duplicate_count'] ?? count( (array) ( $plan['params']['variants'] ?? array() ) ) );
			if ( $dup_count > 0 ) {
				$keywords[] = array(
					'text' => sprintf(
						/* translators: %d: variant count */
						__( 'Create %d variants', 'reactwoo-geocore' ),
						$dup_count
					),
					'type' => 'version_signal',
				);
			}
			$total_versions = (int) ( $plan['params']['total_version_count'] ?? 0 );
			if ( $total_versions > 0 && empty( $keywords ) ) {
				$keywords[] = array(
					'text' => sprintf(
						/* translators: %d: version count */
						__( 'Create %d variations', 'reactwoo-geocore' ),
						$total_versions
					),
					'type' => 'version_signal',
				);
			} elseif ( preg_match( '/\b(?:duplicate|copy|clone)\s+(?:the\s+)?\w+\s+twice\b/i', $phrase ) ) {
				$keywords[] = array(
					'text' => __( 'Duplicate homepage twice', 'reactwoo-geocore' ),
					'type' => 'duplicate_signal',
				);
			}
			$intents[] = array(
				'key'        => 'create_geo_variant_plan',
				'label'      => __( 'Homepage targeting plan', 'reactwoo-geocore' ),
				'confidence' => (float) ( $plan['confidence'] ?? 0.88 ),
			);
			foreach ( (array) ( $plan['params']['variants'] ?? array() ) as $variant ) {
				$label = (string) ( $variant['label'] ?? '' );
				$ordinal = (int) ( $variant['ordinal'] ?? 0 );
				if ( $ordinal <= 0 ) {
					$ordinal = count( $variant_groups ) + 1;
				}
				if ( '' === $label && ! empty( $variant['countries'] ) && class_exists( 'RWGA_Variant_Group_Extractor', false ) ) {
					$label = RWGA_Variant_Group_Extractor::label_for_countries( (array) $variant['countries'], $entities );
				}
				if ( ! empty( $variant['weather']['condition'] ) ) {
					$label .= ' + ' . (string) $variant['weather']['condition'];
				}
				$variant_groups[] = array(
					'index' => $ordinal,
					'label' => sprintf(
						/* translators: 1: variant number, 2: targeting label */
						__( 'Variant %1$d: %2$s', 'reactwoo-geocore' ),
						$ordinal,
						$label
					),
				);
			}
		} elseif ( ! empty( $plan['matched'] ) && ! empty( $plan['missing_information'] ) ) {
			$intents[] = array(
				'key'        => 'create_geo_variant_plan',
				'label'      => __( 'Variant plan (needs clarification)', 'reactwoo-geocore' ),
				'confidence' => (float) ( $plan['confidence'] ?? 0.65 ),
			);
		} else {
			$multi = class_exists( 'RWGA_Multi_Variant_Interpreter', false )
				? RWGA_Multi_Variant_Interpreter::parse( $phrase, $entities, $context )
				: array( 'matched' => false );

			if ( ! empty( $multi['matched'] ) ) {
				self::append_multi_variant_detected( $phrase, $multi, $intents, $entities_out, $keywords, $variant_groups );
			} else {
				if ( preg_match( '/\b(create|show|hide|target|audit|diagnose|clean|apply|exclude)\b/i', $phrase, $m ) ) {
					$keywords[] = array( 'text' => $m[1], 'type' => 'action' );
				}

				$country_rule = class_exists( 'RWGA_Country_Rule_Interpreter', false )
					? RWGA_Country_Rule_Interpreter::parse( $phrase, $entities )
					: array( 'matched' => false );
				if ( ! empty( $country_rule['matched'] ) ) {
					$intents[] = array(
						'key'        => (string) ( $country_rule['intent'] ?? 'country_include' ),
						'label'      => (string) ( $country_rule['summary'] ?? __( 'Country rule', 'reactwoo-geocore' ) ),
						'confidence' => (float) ( $country_rule['confidence'] ?? 0.75 ),
					);
					foreach ( (array) ( $country_rule['params']['countries'] ?? array() ) as $code ) {
						$entities_out[] = array(
							'type'   => 'country',
							'label'  => $code,
							'value'  => $code,
							'source' => 'phrase',
						);
					}
				}
			}
		}

		$page = class_exists( 'RWGA_Page_Reference_Resolver', false )
			? RWGA_Page_Reference_Resolver::detect( $phrase )
			: null;
		if ( $page ) {
			$entities_out[] = array(
				'type'   => 'page',
				'label'  => (string) ( $page['label'] ?? $page['value'] ),
				'value'  => (string) ( $page['value'] ?? '' ),
				'source' => 'phrase',
			);
		}

		if ( preg_match( '/\bonly\b/i', $phrase ) && empty( $variant_groups ) && empty( $source_targeting ) ) {
			$keywords[] = array( 'text' => 'only', 'type' => 'rule_mode' );
		}

		foreach ( array( 'mobile', 'desktop', 'tablet' ) as $device ) {
			if ( false !== strpos( $phrase, $device ) ) {
				$entities_out[] = array(
					'type'   => 'device',
					'label'  => ucfirst( $device ),
					'value'  => $device,
					'source' => 'phrase',
				);
			}
		}

		return array(
			'intents'          => $intents,
			'entities'         => $entities_out,
			'keywords'         => $keywords,
			'variant_groups'   => $variant_groups,
			'source_targeting' => $source_targeting,
		);
	}

	/**
	 * @param string              $phrase         Phrase.
	 * @param array<string,mixed> $multi          Multi-variant parse result.
	 * @param array<int,array>    $intents        Intent chips (by ref).
	 * @param array<int,array>    $entities_out   Entity chips (by ref).
	 * @param array<int,array>    $keywords       Keyword chips (by ref).
	 * @param array<int,array>    $variant_groups Variant group chips (by ref).
	 * @return void
	 */
	private static function append_multi_variant_detected( $phrase, array $multi, array &$intents, array &$entities_out, array &$keywords, array &$variant_groups ) {
		$variants = isset( $multi['params']['variants'] ) && is_array( $multi['params']['variants'] )
			? $multi['params']['variants']
			: array();
		$count    = (int) ( $multi['variant_count'] ?? count( $variants ) );
		if ( $count < 1 && ! empty( $multi['missing_information'] ) ) {
			$count = max( 1, count( (array) ( $multi['params']['countries'] ?? array() ) ) );
		}
		$intents[] = array(
			'key'        => 'create_geo_variants',
			'label'      => sprintf(
				/* translators: %d: variant count */
				__( 'Create %d variants', 'reactwoo-geocore' ),
				max( 1, $count )
			),
			'confidence' => (float) ( $multi['confidence'] ?? 0.8 ),
		);

		if ( ! empty( $multi['params']['source_page_ref'] ) ) {
			$entities_out[] = array(
				'type'   => 'page',
				'label'  => ucfirst( (string) $multi['params']['source_page_ref'] ),
				'value'  => (string) $multi['params']['source_page_ref'],
				'source' => 'phrase',
			);
		}

		foreach ( $variants as $idx => $variant ) {
			$label = (string) ( $variant['label'] ?? '' );
			if ( '' === $label && ! empty( $variant['countries'] ) ) {
				$label = implode( ' + ', (array) $variant['countries'] );
			}
			$variant_groups[] = array(
				'index' => $idx + 1,
				'label' => sprintf(
					/* translators: 1: variant number, 2: targeting label */
					__( 'Variant %1$d: %2$s', 'reactwoo-geocore' ),
					$idx + 1,
					$label
				),
			);
		}

		if ( preg_match( '/\b(duplicate|twice|another|the other)\b/i', $phrase, $m ) ) {
			$keywords[] = array( 'text' => $m[1], 'type' => 'variant_signal' );
		}
	}

	/**
	 * @param array<string,mixed>      $raw           Interpreter output.
	 * @param string                   $message       Original message.
	 * @param array<int,array>         $entities      Entity rows.
	 * @param array<string,mixed>|null $inferred_plan Inferred plan.
	 * @return array<string,mixed>
	 */
	private static function format_proposal( array $raw, $message, array $entities = array(), $inferred_plan = null ) {
		$steps = isset( $raw['steps'] ) && is_array( $raw['steps'] ) ? $raw['steps'] : array();
		if ( empty( $steps ) && ! empty( $raw['params']['variants'] ) ) {
			foreach ( (array) $raw['params']['variants'] as $idx => $variant ) {
				$label = (string) ( $variant['label'] ?? '' );
				if ( '' === $label ) {
					$countries = is_array( $variant['countries'] ?? null ) ? $variant['countries'] : array();
					$label     = implode( ', ', $countries );
				}
				$steps[] = array(
					'label'  => sprintf(
						/* translators: 1: variant number, 2: variant label */
						__( 'Variant %1$d: %2$s', 'reactwoo-geocore' ),
						$idx + 1,
						$label
					),
					'action' => 'geocore_create_variant',
					'params' => array(
						'countries' => $variant['countries'] ?? array(),
						'mode'      => $variant['mode'] ?? 'include_only',
					),
				);
			}
		}

		$can_execute = class_exists( 'RWGA_Interpretation_Status', false )
			? RWGA_Interpretation_Status::from_result( $raw )['can_execute']
			: ( ! isset( $raw['proposal_ready'] ) || false !== $raw['proposal_ready'] );
		$status_label = $can_execute
			? __( 'Pending confirmation', 'reactwoo-geocore' )
			: __( 'Needs confirmation', 'reactwoo-geocore' );
		$setup_summary = ! empty( $raw['setup_summary'] )
			? (string) $raw['setup_summary']
			: self::format_setup_summary( $raw, $inferred_plan, $entities, $status_label );

		return array(
			'intent'                => (string) ( $raw['intent'] ?? '' ),
			'matched_action'        => (string) ( $raw['matched_action'] ?? '' ),
			'confidence'            => (float) ( $raw['confidence'] ?? 0 ),
			'proposal_ready'        => ! isset( $raw['proposal_ready'] ) || false !== $raw['proposal_ready'],
			'can_execute'           => $can_execute,
			'interpretation_status' => (string) ( $raw['interpretation_status'] ?? '' ),
			'requires_confirmation' => ! empty( $raw['requires_confirmation'] ),
			'summary'               => (string) ( $raw['summary'] ?? '' ),
			'setup_summary'         => $setup_summary,
			'inferred_plan'         => is_array( $inferred_plan ) ? $inferred_plan : null,
			'interpretation_plan'   => isset( $raw['interpretation_plan'] ) && is_array( $raw['interpretation_plan'] ) ? $raw['interpretation_plan'] : null,
			'ambiguities'           => isset( $raw['ambiguities'] ) && is_array( $raw['ambiguities'] ) ? $raw['ambiguities'] : array(),
			'action_cards'          => isset( $raw['action_cards'] ) && is_array( $raw['action_cards'] ) ? $raw['action_cards'] : array(),
			'actions'               => isset( $raw['action_cards'] ) && is_array( $raw['action_cards'] ) ? $raw['action_cards'] : array(),
			'source'                => self::resolve_source( $raw ),
			'shared_targets'        => isset( $raw['shared_targets'] ) && is_array( $raw['shared_targets'] ) ? $raw['shared_targets'] : array(),
			'shared_target'         => isset( $raw['shared_target'] ) && is_array( $raw['shared_target'] ) ? $raw['shared_target'] : null,
			'confirmation_instruction' => isset( $raw['confirmation_instruction'] ) && is_array( $raw['confirmation_instruction'] ) ? $raw['confirmation_instruction'] : null,
			'fields_needing_attention' => (int) ( $raw['fields_needing_attention'] ?? 0 ),
			'requires_resolution'   => ! empty( $raw['requires_resolution'] ),
			'invalid_interpretation' => isset( $raw['invalid_interpretation'] ) && is_array( $raw['invalid_interpretation'] ) ? $raw['invalid_interpretation'] : null,
			'ai_interpretation'     => isset( $raw['ai_interpretation'] ) && is_array( $raw['ai_interpretation'] ) ? $raw['ai_interpretation'] : null,
			'target'                => isset( $raw['ai_interpretation']['proposal_draft']['target'] ) ? $raw['ai_interpretation']['proposal_draft']['target'] : null,
			'rule'                  => isset( $raw['ai_interpretation']['proposal_draft']['rule'] ) ? $raw['ai_interpretation']['proposal_draft']['rule'] : null,
			'params'                => isset( $raw['params'] ) && is_array( $raw['params'] ) ? $raw['params'] : array(),
			'steps'                 => $steps,
			'warnings'              => isset( $raw['warnings'] ) && is_array( $raw['warnings'] ) ? $raw['warnings'] : array(),
			'missing_information'   => isset( $raw['missing_information'] ) && is_array( $raw['missing_information'] ) ? $raw['missing_information'] : array(),
			'suggested_options'     => isset( $raw['suggested_options'] ) && is_array( $raw['suggested_options'] ) ? $raw['suggested_options'] : array(),
			'conditions'            => in_array( (string) ( $raw['intent'] ?? '' ), array( 'create_geo_variants', 'create_geo_variant_plan' ), true ) ? array() : ( isset( $raw['conditions'] ) && is_array( $raw['conditions'] ) ? $raw['conditions'] : array() ),
			'condition_match'       => in_array( (string) ( $raw['intent'] ?? '' ), array( 'create_geo_variants', 'create_geo_variant_plan' ), true ) ? '' : (string) ( $raw['condition_match'] ?? '' ),
			'portable_rule_set'     => $raw['portable_rule_set'] ?? null,
			'resolved_target'       => $raw['resolved_target'] ?? null,
			'original_message'      => $message,
			'interpretation_source' => (string) ( $raw['interpretation_source'] ?? '' ),
			'interpretation_badge'  => self::interpretation_badge( $raw ),
		);
	}

	/**
	 * Map the interpreter output to a coarse "source" badge for the chat UI:
	 * local_parser | local_memory | remote_memory | ai_fallback | clarification.
	 *
	 * @param array<string,mixed> $raw Interpreter output.
	 * @return string
	 */
	private static function resolve_source( array $raw ) {
		if ( ! empty( $raw['ai_interpretation'] ) ) {
			return 'ai_fallback';
		}
		$source = (string) ( $raw['interpretation_source'] ?? ( $raw['source'] ?? '' ) );
		if ( in_array( $source, array( 'local_parser', 'local_memory', 'remote_memory', 'ai_fallback', 'clarification' ), true ) ) {
			return $source;
		}
		$status = (string) ( $raw['interpretation_status'] ?? $raw['status'] ?? '' );
		if ( in_array( $status, array( 'needs_clarification', 'needs_resolution' ), true ) || ! empty( $raw['requires_resolution'] ) ) {
			return 'clarification';
		}
		return 'local_parser';
	}

	/**
	 * Human-readable setup panel summary (not raw condition syntax).
	 *
	 * @param array<string,mixed>      $raw           Interpreter output.
	 * @param array<string,mixed>|null $inferred_plan Inferred plan.
	 * @param array<int,array>         $entities      Entities.
	 * @param string                   $status_label  Status label.
	 * @return string
	 */
	private static function format_setup_summary( array $raw, $inferred_plan = null, array $entities = array(), $status_label = '' ) {
		if ( is_array( $inferred_plan ) && class_exists( 'RWGA_Inferred_Plan_Builder', false ) ) {
			$can_execute = class_exists( 'RWGA_Interpretation_Status', false )
				? RWGA_Interpretation_Status::from_result( $raw )['can_execute']
				: ( ! isset( $raw['proposal_ready'] ) || false !== $raw['proposal_ready'] );
			if ( ! $can_execute ) {
				return RWGA_Inferred_Plan_Builder::setup_summary(
					$inferred_plan,
					$entities,
					'' !== $status_label ? $status_label : __( 'Needs confirmation', 'reactwoo-geocore' )
				);
			}
		}
		$intent = (string) ( $raw['intent'] ?? '' );
		if ( 'create_geo_variant_plan' === $intent ) {
			$page_ref = (string) ( $raw['params']['source_page_ref'] ?? 'homepage' );
			$lines    = array(
				ucfirst( $page_ref ) . ' ' . __( 'targeting plan', 'reactwoo-geocore' ),
				'',
				__( 'Original homepage', 'reactwoo-geocore' ),
			);
			$source = $raw['params']['source_targeting'] ?? null;
			if ( is_array( $source ) ) {
				$label = (string) ( $source['targeting_label'] ?? $source['label'] ?? '' );
				if ( '' === $label && ! empty( $source['countries'] ) ) {
					$label = implode( ', ', (array) $source['countries'] );
				}
				$lines[] = $label . ' ' . __( 'only', 'reactwoo-geocore' );
				if ( ! empty( $source['weather']['mode'] ) && 'any' === $source['weather']['mode'] ) {
					$lines[] = __( 'All weather conditions', 'reactwoo-geocore' );
				} elseif ( ! empty( $source['weather']['condition'] ) ) {
					$lines[] = sprintf(
						/* translators: %s: weather condition */
						__( 'Weather: %s', 'reactwoo-geocore' ),
						(string) $source['weather']['condition']
					);
				}
			}
			foreach ( (array) ( $raw['params']['variants'] ?? array() ) as $variant ) {
				$ordinal = (int) ( $variant['ordinal'] ?? 0 );
				if ( $ordinal <= 0 ) {
					$ordinal = 1;
				}
				$lines[] = '';
				$lines[] = sprintf(
					/* translators: %d: variant number */
					__( 'Variant %d', 'reactwoo-geocore' ),
					$ordinal
				);
				$lines[] = (string) ( $variant['label'] ?? implode( ', ', (array) ( $variant['countries'] ?? array() ) ) );
				if ( ! empty( $variant['weather']['condition'] ) ) {
					$lines[] = sprintf(
						/* translators: %s: weather condition */
						__( 'Weather: %s', 'reactwoo-geocore' ),
						(string) $variant['weather']['condition']
					);
				} elseif ( ! empty( $variant['weather']['mode'] ) && 'any' === $variant['weather']['mode'] ) {
					$lines[] = __( 'All weather conditions', 'reactwoo-geocore' );
				}
			}
			$lines[] = '';
			$lines[] = __( 'Status', 'reactwoo-geocore' );
			$lines[] = '' !== $status_label ? $status_label : __( 'Pending confirmation', 'reactwoo-geocore' );
			return implode( "\n", $lines );
		}
		if ( 'weather_rule' === $intent ) {
			return __( 'Weather rule', 'reactwoo-geocore' ) . "\n" . (string) ( $raw['summary'] ?? '' );
		}
		if ( 'create_geo_variants' === $intent && ! empty( $raw['params']['variants'] ) ) {
			$page_ref = (string) ( $raw['params']['source_page_ref'] ?? $raw['params']['page_ref'] ?? 'page' );
			$lines    = array( ucfirst( $page_ref ) . ' ' . __( 'variants', 'reactwoo-geocore' ) );
			foreach ( (array) $raw['params']['variants'] as $variant ) {
				$label = (string) ( $variant['label'] ?? '' );
				if ( '' === $label && ! empty( $variant['countries'] ) ) {
					$label = implode( ', ', (array) $variant['countries'] );
				}
				$lines[] = $label;
			}
			return implode( "\n", $lines );
		}
		if ( 'country_include' === $intent ) {
			return __( 'Country rule', 'reactwoo-geocore' ) . "\n" . (string) ( $raw['summary'] ?? '' );
		}
		if ( 'country_exclude' === $intent ) {
			return __( 'Country rule', 'reactwoo-geocore' ) . "\n" . (string) ( $raw['summary'] ?? '' );
		}
		return (string) ( $raw['summary'] ?? '' );
	}

	/**
	 * @param array<string,mixed>      $meta          Status meta from RWGA_Interpretation_Status.
	 * @param array<string,mixed>      $raw           Interpreter output.
	 * @param array<string,mixed>|null $inferred_plan Inferred plan.
	 * @param bool                     $ai_available  Whether AI fallback is enabled.
	 * @return array<int,array<string,string>>
	 */
	private static function action_buttons( array $meta, array $raw = array(), $inferred_plan = null, $ai_available = false ) {
		$status      = (string) ( $meta['status'] ?? '' );
		$can_execute = ! empty( $meta['can_execute'] );
		$has_inferred = is_array( $inferred_plan );
		$has_cards    = ! empty( $raw['action_cards'] ) && is_array( $raw['action_cards'] );
		$needs_resolution = ! empty( $raw['requires_resolution'] ) || ! empty( $raw['invalid_interpretation'] );

		if ( $has_cards && $needs_resolution ) {
			$buttons = array(
				array( 'key' => 'resolve_items', 'label' => __( 'Resolve items', 'reactwoo-geocore' ) ),
			);
			if ( $ai_available ) {
				$buttons[] = array(
					'key'   => 'ask_ai',
					'label' => ! empty( $raw['invalid_interpretation'] )
						? __( 'Ask AI to re-check', 'reactwoo-geocore' )
						: __( 'Ask AI to check', 'reactwoo-geocore' ),
				);
			}
			$buttons[] = array( 'key' => 'debug', 'label' => __( 'Show debug', 'reactwoo-geocore' ) );
			$buttons[] = array( 'key' => 'cancel', 'label' => __( 'Cancel', 'reactwoo-geocore' ) );
			return $buttons;
		}

		if ( $has_cards && $can_execute && RWGA_Interpretation_Status::COMPLETE === $status ) {
			return array(
				array( 'key' => 'confirm', 'label' => __( 'Create rule', 'reactwoo-geocore' ) ),
				array( 'key' => 'edit', 'label' => __( 'Edit rule', 'reactwoo-geocore' ) ),
				array( 'key' => 'debug', 'label' => __( 'Show debug', 'reactwoo-geocore' ) ),
				array( 'key' => 'cancel', 'label' => __( 'Cancel', 'reactwoo-geocore' ) ),
			);
		}

		if ( $can_execute && RWGA_Interpretation_Status::COMPLETE === $status ) {
			return array(
				array( 'key' => 'confirm', 'label' => __( 'Create setup', 'reactwoo-geocore' ) ),
				array( 'key' => 'edit', 'label' => __( 'Edit setup', 'reactwoo-geocore' ) ),
				array( 'key' => 'debug', 'label' => __( 'Show debug', 'reactwoo-geocore' ) ),
				array( 'key' => 'cancel', 'label' => __( 'Cancel', 'reactwoo-geocore' ) ),
			);
		}

		if ( RWGA_Interpretation_Status::NEEDS_CONFIRMATION === $status ) {
			$buttons = array(
				array( 'key' => 'accept_likely_interpretation', 'label' => __( 'Use this interpretation', 'reactwoo-geocore' ) ),
				array( 'key' => 'edit_ambiguities', 'label' => __( 'Choose location/audience', 'reactwoo-geocore' ) ),
			);
			if ( $ai_available ) {
				$buttons[] = array( 'key' => 'ask_ai_again', 'label' => __( 'Ask AI again', 'reactwoo-geocore' ) );
			}
			$buttons[] = array( 'key' => 'cancel', 'label' => __( 'Cancel', 'reactwoo-geocore' ) );
			return $buttons;
		}

		if ( RWGA_Interpretation_Status::NEEDS_CLARIFICATION === $status && $has_inferred ) {
			$buttons = array(
				array( 'key' => 'use_split', 'label' => __( 'Yes, use this split', 'reactwoo-geocore' ) ),
				array( 'key' => 'edit_split', 'label' => __( 'Edit split', 'reactwoo-geocore' ) ),
			);
			if ( $ai_available ) {
				$buttons[] = array( 'key' => 'ask_ai', 'label' => __( 'Ask AI to check', 'reactwoo-geocore' ) );
			}
			$buttons[] = array( 'key' => 'cancel', 'label' => __( 'Cancel', 'reactwoo-geocore' ) );
			return $buttons;
		}

		if ( RWGA_Interpretation_Status::NEEDS_CLARIFICATION === $status ) {
			$buttons = array(
				array( 'key' => 'choose_split', 'label' => __( 'Choose split', 'reactwoo-geocore' ) ),
			);
			if ( $ai_available ) {
				$buttons[] = array( 'key' => 'ask_ai', 'label' => __( 'Ask AI', 'reactwoo-geocore' ) );
			}
			$buttons[] = array( 'key' => 'edit_manually', 'label' => __( 'Edit manually', 'reactwoo-geocore' ) );
			$buttons[] = array( 'key' => 'cancel', 'label' => __( 'Cancel', 'reactwoo-geocore' ) );
			return $buttons;
		}

		if ( RWGA_Interpretation_Status::PARTIAL === $status ) {
			$buttons = array();
			if ( $ai_available ) {
				$buttons[] = array( 'key' => 'ask_ai', 'label' => __( 'Ask AI', 'reactwoo-geocore' ) );
			}
			$buttons[] = array( 'key' => 'edit_manually', 'label' => __( 'Edit manually', 'reactwoo-geocore' ) );
			$buttons[] = array( 'key' => 'cancel', 'label' => __( 'Cancel', 'reactwoo-geocore' ) );
			return $buttons;
		}

		unset( $raw );
		return array(
			array( 'key' => 'edit', 'label' => __( 'Edit setup', 'reactwoo-geocore' ) ),
			array( 'key' => 'debug', 'label' => __( 'Show debug', 'reactwoo-geocore' ) ),
			array( 'key' => 'cancel', 'label' => __( 'Cancel', 'reactwoo-geocore' ) ),
		);
	}

	/**
	 * @param array<string,mixed> $proposal Proposal.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_redirect_steps( array $proposal ) {
		$page_id = 0;
		if ( ! empty( $proposal['resolved_target']['id'] ) ) {
			$page_id = (int) $proposal['resolved_target']['id'];
		} elseif ( ! empty( $proposal['params']['page_ref'] ) && class_exists( 'RWGA_Page_Reference_Resolver', false ) ) {
			$ref = RWGA_Page_Reference_Resolver::detect( (string) $proposal['params']['page_ref'] );
			if ( $ref && ! empty( $ref['page_id'] ) ) {
				$page_id = (int) $ref['page_id'];
			}
		}
		$base = admin_url( 'admin.php?page=rwgc-workflow-variant' );
		$out  = array();
		foreach ( (array) ( $proposal['steps'] ?? array() ) as $step ) {
			$url = $base;
			if ( $page_id ) {
				$url = add_query_arg( 'rwgc_master_page_id', $page_id, $url );
			}
			$countries = $step['params']['countries'] ?? array();
			if ( ! empty( $countries ) ) {
				$url = add_query_arg( 'rwgc_condition_type', 'countries', $url );
			}
			$out[] = array(
				'label' => (string) ( $step['label'] ?? '' ),
				'url'   => $url,
			);
		}
		if ( empty( $out ) ) {
			$out[] = array(
				'label' => __( 'Open variant workflow', 'reactwoo-geocore' ),
				'url'   => $page_id ? add_query_arg( 'rwgc_master_page_id', $page_id, $base ) : $base,
			);
		}
		return $out;
	}
}
