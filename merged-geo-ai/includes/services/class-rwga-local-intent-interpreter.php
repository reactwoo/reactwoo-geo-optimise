<?php
/**
 * Local phrase → intent/action interpreter (no external AI).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Local_Intent_Interpreter {

	const CONFIDENCE_THRESHOLD = 0.85;

	/** @var array<string,mixed> */
	private static $gate_bag = array();

	/**
	 * @param string              $raw_phrase User input.
	 * @param array<string,mixed> $context    From RWGA_Context_Resolver.
	 * @return array<string,mixed>
	 */
	public static function interpret( $raw_phrase, array $context = array() ) {
		$phrase = self::normalise( $raw_phrase );
		$trace  = array(
			'local_parser'          => array( 'attempted' => true, 'matched' => false, 'confidence' => 0 ),
			'pattern_bundle'        => array( 'attempted' => false, 'matched' => false ),
			'interpretation_memory' => array( 'attempted' => false, 'matched' => false ),
			'ai_fallback'           => array( 'called' => false ),
		);
		$bundle = class_exists( 'RWGA_Intelligence_Sync_Service', false )
			? RWGA_Intelligence_Sync_Service::ensure_bundle()
			: null;

		if ( ! is_array( $bundle ) || empty( $bundle['phrase_patterns'] ) ) {
			return self::empty_result( $phrase, __( 'Intelligence bundle not loaded.', 'reactwoo-geo-ai' ), array(
				'_debug_bundle' => array(
					'loaded' => false,
					'hint'   => __( 'Ensure ReactWoo Geo AI is active and the intelligence bundle file is present.', 'reactwoo-geo-ai' ),
				),
			) );
		}

		$patterns      = is_array( $bundle['phrase_patterns'] ) ? $bundle['phrase_patterns'] : array();
		$flat_entities = is_array( $bundle['entities'] ) ? $bundle['entities'] : array();
		$entities      = self::index_entities( $flat_entities );
		$actions       = self::index_actions( is_array( $bundle['actions'] ) ? $bundle['actions'] : array() );
		$intents       = self::index_intents( is_array( $bundle['intents'] ) ? $bundle['intents'] : array() );
		$editor_opts   = self::editor_options( $context );
		$deferred_plan = null;
		self::$gate_bag = array(
			'raw'      => $raw_phrase,
			'entities' => $flat_entities,
			'context'  => $context,
		);

		if ( class_exists( 'RWGA_Geo_Assistant_Planner', false ) ) {
			$planner_result = RWGA_Geo_Assistant_Planner::interpret_as_legacy( $raw_phrase, $context, $flat_entities );
			if ( is_array( $planner_result ) && ! empty( $planner_result['matched'] ) ) {
				$trace['local_parser']['matched']    = true;
				$trace['local_parser']['parser']       = 'RWGA_Geo_Assistant_Planner';
				$trace['local_parser']['confidence']   = (float) ( $planner_result['confidence'] ?? 0 );
				$trace['local_parser']['action_count'] = count( (array) ( $planner_result['interpretation_plan']['actions'] ?? array() ) );
				return self::attach_trace(
					array_merge(
						$planner_result,
						array(
							'interpretation_source' => 'geo_assistant_planner',
							'proposal_ready'        => ! isset( $planner_result['proposal_ready'] ) || false !== $planner_result['proposal_ready'],
						)
					),
					$trace
				);
			}
		}

		if ( class_exists( 'RWGA_Variant_Plan_Interpreter', false ) ) {
			$plan = RWGA_Variant_Plan_Interpreter::parse( $phrase, $flat_entities, $context );
			$trace['local_parser']['parser']     = 'RWGA_Variant_Plan_Interpreter';
			$trace['local_parser']['confidence'] = (float) ( $plan['confidence'] ?? 0 );
			if ( is_array( $plan['_debug'] ?? null ) ) {
				$trace['local_parser'] = array_merge( $trace['local_parser'], self::variant_plan_trace_fields( $plan['_debug'] ) );
			}
			if ( ! empty( $plan['matched'] ) ) {
				if ( self::is_ready_local_result( $plan ) ) {
					$trace['local_parser']['matched'] = true;
					return self::attach_trace(
						array_merge(
							self::build_variant_plan_result( $plan, $context, $phrase ),
							array( 'interpretation_source' => 'local_parser', 'proposal_ready' => true )
						),
						$trace
					);
				}
				$deferred_plan = $plan;
				$trace['local_parser']['matched'] = false;
				$trace['local_parser']['reason']  = (string) ( $plan['_debug']['reason'] ?? 'low_confidence_variant_plan' );
			} else {
				$trace['local_parser']['reason'] = (string) ( $plan['reason'] ?? 'no_variant_plan_match' );
			}
		}

		if ( class_exists( 'RWGA_Multi_Variant_Interpreter', false ) ) {
			$multi = RWGA_Multi_Variant_Interpreter::parse( $phrase, $flat_entities, $context );
			if ( ! empty( $multi['matched'] ) && self::is_ready_local_result( $multi ) ) {
				$trace['local_parser']['matched']    = true;
				$trace['local_parser']['confidence'] = (float) ( $multi['confidence'] ?? 0.85 );
				$trace['local_parser']['parser']     = 'RWGA_Multi_Variant_Interpreter';
				return self::attach_trace(
					array_merge(
						self::build_multi_variant_result( $multi, $context, $phrase ),
						array( 'interpretation_source' => 'local_parser', 'proposal_ready' => true )
					),
					$trace
				);
			}
		}

		if ( class_exists( 'RWGA_Country_Rule_Interpreter', false ) ) {
			$country_rule = RWGA_Country_Rule_Interpreter::parse( $phrase, $flat_entities );
			if ( ! empty( $country_rule['matched'] ) ) {
				$trace['local_parser']['matched']    = true;
				$trace['local_parser']['confidence'] = (float) ( $country_rule['confidence'] ?? 0.8 );
				$trace['local_parser']['parser']     = 'RWGA_Country_Rule_Interpreter';
				return self::attach_trace(
					array_merge(
						self::build_country_rule_result( $country_rule, $context, $phrase ),
						array( 'interpretation_source' => 'local_parser', 'proposal_ready' => true )
					),
					$trace
				);
			}
		}

		if ( class_exists( 'RWGA_Weather_Rule_Interpreter', false ) ) {
			$weather_rule = RWGA_Weather_Rule_Interpreter::parse( $phrase, $flat_entities );
			if ( ! empty( $weather_rule['matched'] ) ) {
				$trace['local_parser']['matched']    = true;
				$trace['local_parser']['confidence'] = (float) ( $weather_rule['confidence'] ?? 0.82 );
				$trace['local_parser']['parser']     = 'RWGA_Weather_Rule_Interpreter';
				return self::attach_trace(
					array_merge(
						self::build_weather_rule_result( $weather_rule, $context, $phrase ),
						array( 'interpretation_source' => 'local_parser', 'proposal_ready' => true )
					),
					$trace
				);
			}
		}

		$compound = class_exists( 'RWGA_Compound_Condition_Interpreter', false )
			&& ( ! class_exists( 'RWGA_Variant_Group_Extractor', false ) || ! RWGA_Variant_Group_Extractor::is_multi_variant_command( $phrase ) )
			? RWGA_Compound_Condition_Interpreter::parse( $phrase, $flat_entities, $editor_opts )
			: array( 'compound' => false );

		$best       = null;
		$best_score = 0.0;
		$trace['pattern_bundle']['attempted'] = true;

		foreach ( $patterns as $pattern_row ) {
			if ( ! is_array( $pattern_row ) || ( $pattern_row['status'] ?? 'active' ) !== 'active' ) {
				continue;
			}
			$match = self::match_pattern( $phrase, $pattern_row, $entities );
			if ( ! $match ) {
				continue;
			}
			$bootstrap = (float) ( $pattern_row['confidence_weight'] ?? 0.5 );
			$score     = $match['confidence'] * $bootstrap;
			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = array_merge( $match, array( 'pattern' => $pattern_row ) );
			}
		}

		if ( $best && $best_score >= self::CONFIDENCE_THRESHOLD ) {
			$trace['pattern_bundle']['matched']    = true;
			$trace['pattern_bundle']['confidence'] = round( $best_score, 2 );
			$result = self::build_pattern_bundle_result( $best, $best_score, $actions, $intents, $context, $phrase, $compound );
			$result['interpretation_source'] = 'pattern_bundle';
			$result['proposal_ready']        = true;
			return self::attach_trace( $result, $trace );
		}

		$trace['pattern_bundle']['matched'] = (bool) $best;
		if ( ! empty( $compound['compound'] ) && ! empty( $compound['conditions'] ) ) {
			return self::attach_trace(
				array_merge(
					self::build_compound_result( $compound, $context, $phrase ),
					array( 'interpretation_source' => 'local_parser', 'proposal_ready' => true )
				),
				$trace
			);
		}

		$escalated = self::escalate_interpretation( $raw_phrase, $phrase, $context, $flat_entities, $trace, $deferred_plan );
		if ( null !== $escalated ) {
			return self::attach_trace( $escalated, $trace );
		}

		$clarification = self::focused_clarification_from_deferred( $deferred_plan, $phrase, $flat_entities );
		if ( null !== $clarification ) {
			return self::attach_trace( $clarification, $trace );
		}

		$clarification = self::meaningful_entity_clarification( $phrase, $flat_entities );
		if ( null !== $clarification ) {
			return self::attach_trace( $clarification, $trace );
		}
		if ( class_exists( 'RWGA_Ambiguity_Gate', false ) ) {
			$standalone = RWGA_Ambiguity_Gate::build_standalone( $raw_phrase, $phrase, $flat_entities, $context, $trace );
			if ( is_array( $standalone ) ) {
				return self::attach_trace( $standalone, $trace );
			}
		}
		return self::attach_trace(
			self::empty_result( $phrase, __( 'No matching command pattern found.', 'reactwoo-geo-ai' ) ),
			$trace
		);
	}

	/**
	 * Force AI fallback interpretation (user-initiated "Ask AI to check").
	 *
	 * @param string              $raw_phrase User input.
	 * @param array<string,mixed> $context    Context.
	 * @return array<string,mixed>
	 */
	public static function interpret_with_ai_fallback( $raw_phrase, array $context = array() ) {
		$phrase = self::normalise( $raw_phrase );
		$trace  = array(
			'local_parser'          => array( 'attempted' => false, 'matched' => false ),
			'pattern_bundle'        => array( 'attempted' => false, 'matched' => false ),
			'interpretation_memory' => array( 'attempted' => false, 'matched' => false ),
			'ai_fallback'           => array( 'called' => false, 'reason' => 'user_requested' ),
		);
		$bundle = class_exists( 'RWGA_Intelligence_Sync_Service', false )
			? RWGA_Intelligence_Sync_Service::ensure_bundle()
			: null;
		$flat_entities = is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
		$deferred      = null;
		if ( class_exists( 'RWGA_Variant_Plan_Interpreter', false ) ) {
			$plan = RWGA_Variant_Plan_Interpreter::parse( $phrase, $flat_entities, $context );
			if ( ! empty( $plan['matched'] ) ) {
				$deferred = $plan;
			}
		}
		$ai = self::try_ai_fallback( $raw_phrase, $phrase, $context, $flat_entities, $trace, $deferred );
		if ( null === $ai ) {
			return self::attach_trace(
				self::empty_result( $phrase, __( 'AI fallback is not available or could not interpret that command.', 'reactwoo-geo-ai' ) ),
				$trace
			);
		}
		$ai['proposal_ready']        = true;
		$ai['interpretation_status'] = RWGA_Interpretation_Status::COMPLETE;
		return self::attach_trace( $ai, $trace );
	}

	/**
	 * @param array<string,mixed> $result Local parser result.
	 * @return bool
	 */
	private static function is_ready_local_result( array $result ) {
		if ( empty( $result['matched'] ) ) {
			return false;
		}
		if ( isset( $result['proposal_ready'] ) && false === $result['proposal_ready'] ) {
			return false;
		}
		if ( ! empty( $result['missing_information'] ) ) {
			return false;
		}
		if ( ! empty( $result['escalate'] ) ) {
			return false;
		}
		return (float) ( $result['confidence'] ?? 0 ) >= self::CONFIDENCE_THRESHOLD;
	}

	/**
	 * @param array<string,mixed> $debug Parser debug payload.
	 * @return array<string,mixed>
	 */
	private static function variant_plan_trace_fields( array $debug ) {
		$fields = array();
		foreach ( array( 'parser_used', 'variant_plan_terms_detected', 'mixed_conditions_detected', 'raw_clauses', 'segments', 'countries_detected', 'reason' ) as $key ) {
			if ( isset( $debug[ $key ] ) ) {
				$fields[ $key ] = $debug[ $key ];
			}
		}
		if ( isset( $debug['countries_per_clause'] ) ) {
			$fields['clauses'] = $debug['countries_per_clause'];
		}
		return $fields;
	}

	/**
	 * Memory and AI escalation when local parsing is uncertain.
	 *
	 * @param string                   $raw_phrase    Raw phrase.
	 * @param string                   $phrase        Normalised phrase.
	 * @param array<string,mixed>      $context       Context.
	 * @param array<int,array>         $flat_entities Entities.
	 * @param array<string,mixed>      $trace         Trace (by ref).
	 * @param array<string,mixed>|null $deferred_plan Weak local plan hint.
	 * @return array<string,mixed>|null
	 */
	private static function escalate_interpretation( $raw_phrase, $phrase, array $context, array $flat_entities, array &$trace, $deferred_plan = null ) {
		if ( class_exists( 'RWGA_Interpretation_Memory_Matcher', false ) ) {
			$trace['interpretation_memory']['attempted'] = true;
			$memory = RWGA_Interpretation_Memory_Matcher::match( $raw_phrase, $phrase, $flat_entities, $context );
			$trace['interpretation_memory'] = array_merge( $trace['interpretation_memory'], $memory['trace'] ?? array() );
			if ( ! empty( $memory['matched'] ) && (float) ( $memory['confidence'] ?? 0 ) >= self::CONFIDENCE_THRESHOLD ) {
				$source = 'local_memory';
				if ( ! empty( $memory['trace']['source'] ) && 'global' !== $memory['trace']['source'] ) {
					$scope = (string) $memory['trace']['source'];
					$source = in_array( $scope, array( 'global', 'license' ), true ) ? 'remote_memory' : 'local_memory';
				}
				return array_merge(
					self::build_memory_result( $memory, $context, $phrase ),
					array(
						'proposal_ready'          => true,
						'interpretation_source'   => $source,
						'interpretation_status'   => RWGA_Interpretation_Status::COMPLETE,
					)
				);
			}
		}

		$ai_reason = 'local_parser_low_confidence';
		if ( is_array( $deferred_plan ) && ! empty( $deferred_plan['_debug']['countries_detected'] ) ) {
			$ai_reason = 'local_parser_low_confidence_with_entities';
		}
		$trace['ai_fallback']['reason'] = $ai_reason;
		$ai = self::try_ai_fallback( $raw_phrase, $phrase, $context, $flat_entities, $trace, $deferred_plan );
		if ( null !== $ai ) {
			$ai['proposal_ready'] = true;
			$ai['interpretation_status'] = RWGA_Interpretation_Status::COMPLETE;
			if ( 'ai_fallback' === ( $ai['interpretation_source'] ?? '' ) ) {
				$ai['summary'] = __( 'I could not split this locally, so I checked the ReactWoo intelligence layer.', 'reactwoo-geo-ai' )
					. ' ' . (string) ( $ai['summary'] ?? '' );
			}
			return $ai;
		}

		return null;
	}

	/**
	 * @param array<string,mixed>|null $deferred_plan Deferred local plan.
	 * @param string                   $phrase        Normalised phrase.
	 * @param array<int,array>         $entities      Entities.
	 * @return array<string,mixed>|null
	 */
	private static function focused_clarification_from_deferred( $deferred_plan, $phrase, array $entities ) {
		if ( ! is_array( $deferred_plan ) || empty( $deferred_plan['matched'] ) ) {
			return null;
		}
		$result = self::build_variant_plan_result( $deferred_plan, array(), $phrase );
		$result['proposal_ready']          = false;
		$result['interpretation_source']   = 'local_parser';
		$result['interpretation_status']   = RWGA_Interpretation_Status::NEEDS_CLARIFICATION;
		$result['requires_confirmation']   = false;
		$inferred                          = class_exists( 'RWGA_Inferred_Plan_Builder', false )
			? RWGA_Inferred_Plan_Builder::from_interpreter_result( array_merge( $deferred_plan, $result ), $entities )
			: null;
		if ( is_array( $inferred ) ) {
			$result['inferred_plan'] = $inferred;
			$result['summary']       = __( 'I found a likely split, but need you to confirm it before creating anything.', 'reactwoo-geo-ai' );
			if ( empty( $result['missing_information'] ) ) {
				$result['missing_information'] = array(
					array(
						'key'      => 'variant_grouping',
						'question' => __( 'Is this split correct?', 'reactwoo-geo-ai' ),
					),
				);
			}
		} elseif ( empty( $result['suggested_options'] ) ) {
			$result['suggested_options'] = array(
				__( 'Use this split', 'reactwoo-geo-ai' ),
				__( 'Edit setup', 'reactwoo-geo-ai' ),
				__( 'Ask AI', 'reactwoo-geo-ai' ),
				__( 'Cancel', 'reactwoo-geo-ai' ),
			);
		}
		return $result;
	}

	/**
	 * @param array<string,mixed> $best       Best pattern match.
	 * @param float               $best_score Score.
	 * @param array<string,array> $actions    Actions.
	 * @param array<string,array> $intents    Intents.
	 * @param array<string,mixed> $context    Context.
	 * @param string              $phrase     Phrase.
	 * @param array<string,mixed> $compound   Compound parse.
	 * @return array<string,mixed>
	 */
	private static function build_pattern_bundle_result( array $best, $best_score, array $actions, array $intents, array $context, $phrase, array $compound ) {
		$pattern_row = $best['pattern'];
		$intent_key  = (string) ( $pattern_row['intent_key'] ?? '' );
		$action_key  = (string) ( $pattern_row['action_key'] ?? '' );
		$action      = isset( $actions[ $action_key ] ) ? $actions[ $action_key ] : null;
		$intent      = isset( $intents[ $intent_key ] ) ? $intents[ $intent_key ] : null;

		$params = self::build_params( $best['extracted'], $pattern_row, array_merge( $context, array( 'normalised_phrase' => $phrase ) ) );
		$params = self::apply_context_diagnostics( $params, $intent_key, $context, $phrase );

		if ( ! empty( $params['_resolved_action'] ) ) {
			$action_key = (string) $params['_resolved_action'];
			$action     = isset( $actions[ $action_key ] ) ? $actions[ $action_key ] : $action;
			unset( $params['_resolved_action'] );
		}

		$resolved_target = RWGA_Context_Resolver::resolve_reference( 'this', $context );
		$missing         = array();
		if ( $action && ! empty( $action['required_params'] ) ) {
			foreach ( (array) $action['required_params'] as $req ) {
				if ( ! isset( $params[ $req ] ) || '' === $params[ $req ] || ( is_array( $params[ $req ] ) && empty( $params[ $req ] ) ) ) {
					$missing[] = (string) $req;
				}
			}
		}
		if ( ( $intent['requires_context'] ?? false ) && ! $resolved_target ) {
			$missing[] = 'target_context';
		}

		$confidence = min( 1.0, $best_score );
		if ( $intent && $confidence < (float) ( $intent['min_confidence'] ?? 0 ) ) {
			return self::empty_result(
				$phrase,
				__( 'Confidence below minimum threshold for this intent.', 'reactwoo-geo-ai' ),
				array(
					'intent'     => $intent_key,
					'confidence' => $confidence,
				)
			);
		}

		$requires_confirmation = ! empty( $action['requires_confirmation'] );
		$summary               = self::build_summary( $intent_key, $action_key, $params, $resolved_target );

		$result = array(
			'intent'                => $intent_key,
			'matched_action'        => $action_key,
			'confidence'            => round( $confidence, 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => $resolved_target,
			'params'                => $params,
			'missing_information'   => array_values( array_unique( $missing ) ),
			'requires_confirmation' => $requires_confirmation,
			'summary'               => $summary,
			'warnings'              => array(),
			'normalised_phrase'     => $phrase,
		);

		return self::merge_compound_into_result( $result, $compound, $phrase );
	}

	/**
	 * @param array<string,mixed> $result Interpretation result.
	 * @param array<string,mixed> $trace  Layer trace.
	 * @return array<string,mixed>
	 */
	private static function attach_trace( array $result, array $trace ) {
		if ( ! empty( self::$gate_bag ) && class_exists( 'RWGA_Ambiguity_Gate', false ) ) {
			$phrase = (string) ( $result['normalised_phrase'] ?? '' );
			if ( '' === $phrase && ! empty( self::$gate_bag['raw'] ) ) {
				$phrase = self::normalise( (string) self::$gate_bag['raw'] );
				$result['normalised_phrase'] = $phrase;
			}
			$result = RWGA_Ambiguity_Gate::apply(
				$result,
				(string) self::$gate_bag['raw'],
				$phrase,
				(array) self::$gate_bag['entities'],
				(array) self::$gate_bag['context'],
				$trace
			);
		}
		$result['_interpretation_trace'] = $trace;
		if ( empty( $result['interpretation_status'] ) ) {
			$result['interpretation_status'] = RWGA_Interpretation_Status::infer_status(
				$result,
				(float) ( $result['confidence'] ?? 0 )
			);
		}
		if ( class_exists( 'RWGA_Interpreter_Debug', false ) && RWGA_Interpreter_Debug::is_enabled() ) {
			RWGA_Interpreter_Debug::log(
				'interpret',
				array(
					'normalised_input'       => (string) ( $result['normalised_phrase'] ?? '' ),
					'intent'                 => (string) ( $result['intent'] ?? '' ),
					'interpretation_source'  => (string) ( $result['interpretation_source'] ?? '' ),
					'local_confidence'       => (float) ( $result['confidence'] ?? 0 ),
					'final_params'           => isset( $result['params'] ) && is_array( $result['params'] ) ? $result['params'] : array(),
					'fallback_layer_used'    => (string) ( $result['interpretation_source'] ?? 'none' ),
					'memory_match_attempted' => ! empty( $trace['interpretation_memory']['attempted'] ),
					'ai_fallback_called'     => ! empty( $trace['ai_fallback']['called'] ),
					'validation_status'      => (string) ( $result['intent'] ?? 'unknown' ),
					'trace'                  => $trace,
				)
			);
		}
		return $result;
	}

	/**
	 * @param array<string,mixed> $memory  Memory match result.
	 * @param array<string,mixed> $context Context.
	 * @param string              $phrase  Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function build_memory_result( array $memory, array $context, $phrase ) {
		$params = is_array( $memory['params'] ?? null ) ? $memory['params'] : array();
		return array(
			'intent'                => (string) ( $memory['intent'] ?? '' ),
			'matched_action'        => (string) ( $memory['matched_action'] ?? '' ),
			'confidence'            => round( (float) ( $memory['confidence'] ?? 0.88 ), 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => RWGA_Context_Resolver::resolve_reference( 'this', $context ),
			'params'                => $params,
			'missing_information'   => array(),
			'requires_confirmation' => true,
			'summary'               => (string) ( $memory['summary'] ?? __( 'Learned interpretation.', 'reactwoo-geo-ai' ) ),
			'warnings'              => array(),
			'normalised_phrase'     => $phrase,
			'interpretation_source' => 'local_memory',
			'interpretation_status' => RWGA_Interpretation_Status::COMPLETE,
			'proposal_ready'        => true,
			'memory_id'             => (string) ( $memory['memory_id'] ?? '' ),
			'memory_scope'          => (string) ( $memory['scope'] ?? '' ),
		);
	}

	/**
	 * Optional AI fallback via filter (no direct LLM call in satellite by default).
	 *
	 * @param string              $raw_phrase Raw phrase.
	 * @param string              $phrase     Normalised phrase.
	 * @param array<string,mixed> $context    Context.
	 * @param array<int,array>    $entities   Entities.
	 * @param array<string,mixed> $trace      Trace (by ref).
	 * @return array<string,mixed>|null
	 */
	private static function try_ai_fallback( $raw_phrase, $phrase, array $context, array $entities, array &$trace, $deferred_plan = null ) {
		$trace['ai_fallback']['called'] = true;
		$enabled = (bool) apply_filters( 'rwga_interpretation_ai_fallback_enabled', false );
		if ( ! $enabled ) {
			$trace['ai_fallback']['reason'] = (string) ( $trace['ai_fallback']['reason'] ?? 'not_enabled' );
			return null;
		}
		$result = apply_filters(
			'rwga_interpretation_ai_fallback',
			null,
			$raw_phrase,
			$phrase,
			$context,
			$entities,
			$deferred_plan
		);
		if ( ! is_array( $result ) || empty( $result['matched_action'] ) ) {
			$trace['ai_fallback']['matched'] = false;
			return null;
		}
		$trace['ai_fallback']['matched'] = true;
		$result['interpretation_source'] = 'ai_fallback';
		$result['requires_confirmation'] = true;
		$result['normalised_phrase']     = $phrase;
		if ( class_exists( 'RWGA_Learning_Event_Service', false ) ) {
			RWGA_Learning_Event_Service::record(
				array_merge(
					$result,
					array(
						'raw_phrase' => $raw_phrase,
						'outcome'    => 'accepted',
					)
				)
			);
		}
		return $result;
	}

	/**
	 * @param array<string,mixed> $multi   Multi-variant parse result.
	 * @param array<string,mixed> $context Context.
	 * @param string              $phrase  Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function build_multi_variant_result( array $multi, array $context, $phrase ) {
		$page_ref = $multi['page_ref'] ?? null;
		$page_id  = is_array( $page_ref ) && ! empty( $page_ref['page_id'] ) ? (int) $page_ref['page_id'] : 0;
		if ( $page_id <= 0 && ! empty( $context['page_id'] ) ) {
			$page_id = (int) $context['page_id'];
		}
		$resolved = null;
		if ( $page_id > 0 ) {
			$resolved = array(
				'type' => 'page',
				'id'   => $page_id,
			);
		}

		return array(
			'intent'                => (string) ( $multi['intent'] ?? 'create_geo_variants' ),
			'matched_action'        => (string) ( $multi['matched_action'] ?? 'geocore_create_variants_with_country_rules' ),
			'confidence'            => round( (float) ( $multi['confidence'] ?? 0.85 ), 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => $resolved,
			'params'                => isset( $multi['params'] ) && is_array( $multi['params'] ) ? $multi['params'] : array(),
			'steps'                 => isset( $multi['steps'] ) && is_array( $multi['steps'] ) ? $multi['steps'] : array(),
			'variant_groups'        => isset( $multi['variant_groups'] ) && is_array( $multi['variant_groups'] ) ? $multi['variant_groups'] : array(),
			'variant_count'         => (int) ( $multi['variant_count'] ?? count( $multi['params']['variants'] ?? array() ) ),
			'missing_information'   => isset( $multi['missing_information'] ) && is_array( $multi['missing_information'] )
				? $multi['missing_information']
				: ( $page_id > 0 ? array() : array( 'page_ref' ) ),
			'suggested_options'     => isset( $multi['suggested_options'] ) && is_array( $multi['suggested_options'] ) ? $multi['suggested_options'] : array(),
			'requires_confirmation' => true,
			'summary'               => (string) ( $multi['summary'] ?? '' ),
			'warnings'              => array(),
			'normalised_phrase'     => $phrase,
			'_debug_entities'       => array(
				'matched_terms'  => $multi['matched_terms'] ?? array(),
				'variant_groups' => $multi['variant_groups'] ?? array(),
			),
		);
	}

	/**
	 * @param array<string,mixed> $plan    Variant plan parse result.
	 * @param array<string,mixed> $context Context.
	 * @param string              $phrase  Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function build_variant_plan_result( array $plan, array $context, $phrase ) {
		$page_ref = $plan['page_ref'] ?? null;
		$page_id  = is_array( $page_ref ) && ! empty( $page_ref['page_id'] ) ? (int) $page_ref['page_id'] : 0;
		if ( $page_id <= 0 && ! empty( $context['page_id'] ) ) {
			$page_id = (int) $context['page_id'];
		}
		$resolved = null;
		if ( $page_id > 0 ) {
			$resolved = array(
				'type' => 'page',
				'id'   => $page_id,
			);
		}

		return array(
			'intent'                => (string) ( $plan['intent'] ?? 'create_geo_variant_plan' ),
			'matched_action'        => (string) ( $plan['matched_action'] ?? 'geocore_create_variant_plan_with_country_rules' ),
			'confidence'            => round( (float) ( $plan['confidence'] ?? 0.88 ), 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => $resolved,
			'params'                => isset( $plan['params'] ) && is_array( $plan['params'] ) ? $plan['params'] : array(),
			'steps'                 => isset( $plan['steps'] ) && is_array( $plan['steps'] ) ? $plan['steps'] : array(),
			'source_targeting'      => $plan['source_targeting'] ?? ( $plan['params']['source_targeting'] ?? null ),
			'variant_groups'        => isset( $plan['variant_groups'] ) && is_array( $plan['variant_groups'] ) ? $plan['variant_groups'] : array(),
			'duplicate_count'       => (int) ( $plan['duplicate_count'] ?? 0 ),
			'missing_information'   => isset( $plan['missing_information'] ) && is_array( $plan['missing_information'] )
				? $plan['missing_information']
				: ( $page_id > 0 ? array() : array( 'page_ref' ) ),
			'suggested_options'     => isset( $plan['suggested_options'] ) && is_array( $plan['suggested_options'] ) ? $plan['suggested_options'] : array(),
			'requires_confirmation' => true,
			'summary'               => (string) ( $plan['summary'] ?? '' ),
			'warnings'              => isset( $plan['warnings'] ) && is_array( $plan['warnings'] ) ? $plan['warnings'] : array(),
			'normalised_phrase'     => $phrase,
			'proposal_ready'        => ! isset( $plan['proposal_ready'] ) || false !== $plan['proposal_ready'],
			'_debug_entities'       => array_merge(
				array(
					'matched_terms'    => $plan['matched_terms'] ?? array(),
					'variant_groups'   => $plan['variant_groups'] ?? array(),
					'source_targeting' => $plan['source_targeting'] ?? null,
					'duplicate_count'  => $plan['duplicate_count'] ?? 0,
				),
				is_array( $plan['_debug'] ?? null ) ? $plan['_debug'] : array()
			),
		);
	}

	/**
	 * When page + countries + action terms are present but no parser matched, suggest clarification.
	 *
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $entities Entity rows.
	 * @return array<string,mixed>|null
	 */
	private static function meaningful_entity_clarification( $phrase, array $entities ) {
		$has_action = (bool) preg_match( '/\b(create|make|duplicate|copy|clone|show|hide|target|update)\b/i', $phrase );
		$page       = class_exists( 'RWGA_Page_Reference_Resolver', false )
			? RWGA_Page_Reference_Resolver::detect( $phrase )
			: null;
		$countries  = class_exists( 'RWGA_Multi_Variant_Interpreter', false )
			? RWGA_Multi_Variant_Interpreter::parse_country_list( $phrase, $entities )
			: array();
		if ( ! $has_action || empty( $countries ) || ! $page ) {
			return null;
		}
		return array(
			'intent'                => 'create_geo_variant_plan',
			'matched_action'        => 'geocore_create_variant_plan_with_country_rules',
			'confidence'            => 0.55,
			'proposal_ready'        => false,
			'target_reference'      => 'this',
			'resolved_target'       => null,
			'params'                => array(
				'source_page_ref' => (string) ( $page['value'] ?? 'homepage' ),
				'countries'       => $countries,
			),
			'missing_information'   => array(
				array(
					'key'      => 'variant_grouping',
					'question' => __( 'I found a page, countries, and weather conditions, but I need to confirm the split before creating anything.', 'reactwoo-geo-ai' ),
				),
			),
			'suggested_options'     => array(
				__( 'Use this split', 'reactwoo-geo-ai' ),
				__( 'Edit setup', 'reactwoo-geo-ai' ),
				__( 'Ask AI', 'reactwoo-geo-ai' ),
				__( 'Cancel', 'reactwoo-geo-ai' ),
			),
			'requires_confirmation' => false,
			'summary'               => __( 'I found a page, countries, and weather conditions, but I need to confirm the split before creating anything.', 'reactwoo-geo-ai' ),
			'warnings'              => array(),
			'normalised_phrase'     => $phrase,
		);
	}

	/**
	 * @param array<string,mixed> $rule    Country rule parse result.
	 * @param array<string,mixed> $context Context.
	 * @param string              $phrase  Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function build_country_rule_result( array $rule, array $context, $phrase ) {
		return array(
			'intent'                => (string) ( $rule['intent'] ?? 'country_include' ),
			'matched_action'        => (string) ( $rule['matched_action'] ?? 'geocore_create_country_rule' ),
			'confidence'            => round( (float) ( $rule['confidence'] ?? 0.8 ), 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => RWGA_Context_Resolver::resolve_reference( 'this', $context ),
			'params'                => isset( $rule['params'] ) && is_array( $rule['params'] ) ? $rule['params'] : array(),
			'missing_information'   => array(),
			'requires_confirmation' => true,
			'summary'               => (string) ( $rule['summary'] ?? '' ),
			'warnings'              => array(),
			'normalised_phrase'     => $phrase,
			'proposal_ready'        => true,
		);
	}

	/**
	 * @param array<string,mixed> $rule    Weather rule parse result.
	 * @param array<string,mixed> $context Context.
	 * @param string              $phrase  Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function build_weather_rule_result( array $rule, array $context, $phrase ) {
		return array(
			'intent'                => (string) ( $rule['intent'] ?? 'weather_rule' ),
			'matched_action'        => (string) ( $rule['matched_action'] ?? 'geocore_create_weather_rule' ),
			'confidence'            => round( (float) ( $rule['confidence'] ?? 0.82 ), 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => RWGA_Context_Resolver::resolve_reference( 'this', $context ),
			'params'                => isset( $rule['params'] ) && is_array( $rule['params'] ) ? $rule['params'] : array(),
			'missing_information'   => array(),
			'requires_confirmation' => true,
			'summary'               => (string) ( $rule['summary'] ?? '' ),
			'warnings'              => array(),
			'normalised_phrase'     => $phrase,
			'proposal_ready'        => true,
		);
	}

	/**
	 * Editor context for compound condition gating (Core vs Pro types).
	 *
	 * @param array<string,mixed> $context Resolved context.
	 * @return array<string,mixed>
	 */
	private static function editor_options( array $context ) {
		$pro = (bool) apply_filters( 'rwgc_pro_enabled', false );
		if ( isset( $context['pro'] ) ) {
			$pro = (bool) $context['pro'];
		}
		return array(
			'pro' => $pro,
		);
	}

	/**
	 * @param array<string,mixed> $compound Compound parse payload.
	 * @param array<string,mixed> $context  Resolved context.
	 * @param string              $phrase   Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function build_compound_result( array $compound, array $context, $phrase ) {
		$resolved_target = RWGA_Context_Resolver::resolve_reference( 'this', $context );
		$missing         = array();
		if ( ! $resolved_target && preg_match( '/\b(this|current|page)\b/i', $phrase ) ) {
			$missing[] = 'target_context';
		}

		$variant_hint = (bool) preg_match( '/\b(version|variant|different page|different version|local page)\b/i', $phrase );

		return array(
			'intent'                => $variant_hint ? 'create_variant' : (string) ( $compound['intent'] ?? 'compound_targeting' ),
			'matched_action'        => (string) ( $compound['matched_action'] ?? 'geocore_create_portable_rule' ),
			'confidence'            => round( (float) ( $compound['confidence'] ?? 0.75 ), 2 ),
			'target_reference'      => 'this',
			'resolved_target'       => $resolved_target,
			'params'                => self::params_from_compound( $compound ),
			'missing_information'   => $missing,
			'requires_confirmation' => true,
			'summary'               => (string) ( $compound['summary'] ?? '' ),
			'warnings'              => isset( $compound['warnings'] ) && is_array( $compound['warnings'] ) ? $compound['warnings'] : array(),
			'normalised_phrase'     => $phrase,
			'compound'              => true,
			'condition_match'       => (string) ( $compound['condition_match'] ?? 'all' ),
			'conditions'            => isset( $compound['conditions'] ) && is_array( $compound['conditions'] ) ? $compound['conditions'] : array(),
			'portable_rule_set'     => isset( $compound['portable_rule_set'] ) && is_array( $compound['portable_rule_set'] ) ? $compound['portable_rule_set'] : null,
		);
	}

	/**
	 * @param array<string,mixed> $result   Pattern match result.
	 * @param array<string,mixed> $compound Compound parse payload.
	 * @param string              $phrase   Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function merge_compound_into_result( array $result, array $compound, $phrase ) {
		if ( empty( $compound['compound'] ) || empty( $compound['conditions'] ) ) {
			return $result;
		}

		$result['compound']          = true;
		$result['condition_match']   = (string) ( $compound['condition_match'] ?? 'all' );
		$result['conditions']        = $compound['conditions'];
		$result['portable_rule_set'] = $compound['portable_rule_set'] ?? null;
		$result['params']            = array_merge( self::params_from_compound( $compound ), is_array( $result['params'] ) ? $result['params'] : array() );
		$result['summary']           = (string) ( $compound['summary'] ?? $result['summary'] );
		if ( ! empty( $compound['warnings'] ) && is_array( $compound['warnings'] ) ) {
			$result['warnings'] = array_values(
				array_unique(
					array_merge(
						is_array( $result['warnings'] ) ? $result['warnings'] : array(),
						$compound['warnings']
					)
				)
			);
		}
		if ( preg_match( '/\b(version|variant|different page|different version|local page)\b/i', $phrase ) ) {
			$result['intent'] = 'create_variant';
		}
		return $result;
	}

	/**
	 * @param array<string,mixed> $compound Compound parse payload.
	 * @return array<string,mixed>
	 */
	private static function params_from_compound( array $compound ) {
		$params = array();
		$conds  = isset( $compound['conditions'] ) && is_array( $compound['conditions'] ) ? $compound['conditions'] : array();
		foreach ( $conds as $cond ) {
			if ( ! is_array( $cond ) ) {
				continue;
			}
			$type = (string) ( $cond['type'] ?? '' );
			if ( 'country' === $type && ! empty( $cond['value'] ) ) {
				$params['countries'] = is_array( $cond['value'] ) ? $cond['value'] : array( $cond['value'] );
			}
			if ( 'device_type' === $type && ! empty( $cond['value'] ) ) {
				$params['device'] = is_array( $cond['value'] ) ? (string) reset( $cond['value'] ) : (string) $cond['value'];
			}
		}
		return $params;
	}

	/**
	 * @param string $phrase Raw phrase.
	 * @return string
	 */
	public static function normalise( $phrase ) {
		$text = strtolower( trim( (string) $phrase ) );
		$text = str_replace( array( '’', '`' ), "'", $text );
		$text = preg_replace( '/\s+/', ' ', $text );
		return is_string( $text ) ? $text : '';
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @param string $message Error message.
	 * @param array  $extra   Extra fields.
	 * @return array<string,mixed>
	 */
	private static function empty_result( $phrase, $message, array $extra = array() ) {
		return array_merge(
			array(
				'intent'                => '',
				'matched_action'        => '',
				'confidence'            => 0,
				'target_reference'      => '',
				'resolved_target'       => null,
				'params'                => array(),
				'missing_information'   => array(),
				'requires_confirmation' => false,
				'summary'               => $message,
				'warnings'              => array(),
				'normalised_phrase'     => $phrase,
			),
			$extra
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $entities Entity rows.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	private static function index_entities( array $entities ) {
		$out = array();
		foreach ( $entities as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type = (string) ( $row['entity_type'] ?? '' );
			if ( '' === $type ) {
				continue;
			}
			if ( ! isset( $out[ $type ] ) ) {
				$out[ $type ] = array();
			}
			$out[ $type ][] = $row;
		}
		return $out;
	}

	/**
	 * @param array<string,array<int,array>> $indexed Indexed entities.
	 * @return array<int,array<string,mixed>>
	 */
	private static function flatten_entities( array $indexed ) {
		$out = array();
		foreach ( $indexed as $rows ) {
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					if ( is_array( $row ) ) {
						$out[] = $row;
					}
				}
			}
		}
		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $actions Action rows.
	 * @return array<string,array<string,mixed>>
	 */
	private static function index_actions( array $actions ) {
		$out = array();
		foreach ( $actions as $row ) {
			if ( is_array( $row ) && ! empty( $row['action_key'] ) ) {
				$out[ (string) $row['action_key'] ] = $row;
			}
		}
		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $intents Intent rows.
	 * @return array<string,array<string,mixed>>
	 */
	private static function index_intents( array $intents ) {
		$out = array();
		foreach ( $intents as $row ) {
			if ( is_array( $row ) && ! empty( $row['intent_key'] ) ) {
				$out[ (string) $row['intent_key'] ] = $row;
			}
		}
		return $out;
	}

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<string,mixed> $pattern  Pattern row.
	 * @param array<string,array> $entities Indexed entities.
	 * @return array{confidence:float,extracted:array<string,mixed>}|null
	 */
	private static function match_pattern( $phrase, array $pattern, array $entities ) {
		$type    = (string) ( $pattern['pattern_type'] ?? 'contains' );
		$pattern_text = (string) ( $pattern['pattern'] ?? '' );
		$extracted = array();

		switch ( $type ) {
			case 'exact':
				if ( $phrase === self::normalise( $pattern_text ) ) {
					return array( 'confidence' => 1.0, 'extracted' => $extracted );
				}
				return null;

			case 'regex':
				$regex = '/' . str_replace( '/', '\/', $pattern_text ) . '/i';
				if ( @preg_match( $regex, $phrase, $m ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					return array( 'confidence' => 0.9, 'extracted' => array( 'match' => $m ) );
				}
				return null;

			case 'template':
				return self::match_template( $phrase, $pattern_text, $entities );

			case 'semantic_alias':
			case 'contains':
			default:
				if ( false !== strpos( $phrase, self::normalise( $pattern_text ) ) ) {
					$extracted = self::extract_entities_from_phrase( $phrase, $entities );
					return array( 'confidence' => 0.85, 'extracted' => $extracted );
				}
				return null;
		}
	}

	/**
	 * @param string              $phrase        Normalised phrase.
	 * @param string              $template      Template with {placeholders}.
	 * @param array<string,array> $entities      Indexed entities.
	 * @return array{confidence:float,extracted:array<string,mixed>}|null
	 */
	private static function match_template( $phrase, $template, array $entities ) {
		$parts = preg_split( '/\{([a-z_]+)\}/', $template, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) || count( $parts ) < 1 ) {
			return null;
		}

		$regex_parts = array();
		$placeholders = array();
		for ( $i = 0; $i < count( $parts ); $i++ ) {
			if ( $i % 2 === 0 ) {
				$regex_parts[] = preg_quote( self::normalise( $parts[ $i ] ), '/' );
			} else {
				$placeholders[] = $parts[ $i ];
				$regex_parts[]  = '(.+?)';
			}
		}
		$regex = '/^' . implode( '', $regex_parts ) . '$/i';
		if ( ! preg_match( $regex, $phrase, $m ) ) {
			return null;
		}

		$extracted = array();
		foreach ( $placeholders as $idx => $placeholder ) {
			$raw_value = isset( $m[ $idx + 1 ] ) ? trim( (string) $m[ $idx + 1 ] ) : '';
			if ( 'country_list' === $placeholder && class_exists( 'RWGA_Multi_Variant_Interpreter', false ) ) {
				$list = RWGA_Multi_Variant_Interpreter::parse_country_list( $raw_value, self::flatten_entities( $entities ) );
				$extracted[ $placeholder ] = $list;
				continue;
			}
			if ( 'page' === $placeholder ) {
				$extracted[ $placeholder ] = self::normalise( $raw_value );
				continue;
			}
			$entity_type = self::placeholder_entity_type( $placeholder );
			$resolved    = self::resolve_entity_value( $raw_value, $entity_type, $entities );
			if ( $resolved ) {
				$extracted[ $placeholder ] = $resolved;
			} else {
				$extracted[ $placeholder ] = $raw_value;
			}
		}

		return array( 'confidence' => 0.95, 'extracted' => $extracted );
	}

	/**
	 * @param string $placeholder Placeholder name.
	 * @return string
	 */
	private static function placeholder_entity_type( $placeholder ) {
		$map = array(
			'country'            => 'country',
			'country_list'       => 'country',
			'page'               => 'page',
			'device'             => 'device',
			'weather_condition'  => 'weather_condition',
		);
		return isset( $map[ $placeholder ] ) ? $map[ $placeholder ] : $placeholder;
	}

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<string,array> $entities Indexed entities.
	 * @return array<string,mixed>
	 */
	private static function extract_entities_from_phrase( $phrase, array $entities ) {
		$extracted = array();
		foreach ( array( 'weather_condition', 'device', 'country' ) as $type ) {
			if ( empty( $entities[ $type ] ) ) {
				continue;
			}
			$val = self::find_entity_in_phrase( $phrase, $entities[ $type ] );
			if ( $val ) {
				$extracted[ $type ] = $val;
			}
		}
		return $extracted;
	}

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $rows     Entity rows.
	 * @return string|null
	 */
	private static function find_entity_in_phrase( $phrase, array $rows ) {
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$aliases = isset( $row['aliases'] ) && is_array( $row['aliases'] ) ? $row['aliases'] : array();
			$aliases[] = (string) ( $row['display_name'] ?? '' );
			$aliases[] = (string) ( $row['entity_key'] ?? '' );
			usort(
				$aliases,
				static function ( $a, $b ) {
					return strlen( (string) $b ) - strlen( (string) $a );
				}
			);
			foreach ( $aliases as $alias ) {
				$alias = self::normalise( $alias );
				if ( '' !== $alias && false !== strpos( $phrase, $alias ) ) {
					return (string) ( $row['value'] ?? $row['entity_key'] ?? '' );
				}
			}
		}
		return null;
	}

	/**
	 * @param string              $raw_value   Raw captured value.
	 * @param string              $entity_type Entity type.
	 * @param array<string,array> $entities    Indexed entities.
	 * @return string|null
	 */
	private static function resolve_entity_value( $raw_value, $entity_type, array $entities ) {
		if ( empty( $entities[ $entity_type ] ) ) {
			return null;
		}
		return self::find_entity_in_phrase( self::normalise( $raw_value ), $entities[ $entity_type ] )
			?: self::find_entity_in_phrase( self::normalise( $raw_value ), $entities[ $entity_type ] );
	}

	/**
	 * @param array<string,mixed> $extracted   Extracted placeholders.
	 * @param array<string,mixed> $pattern_row Pattern row.
	 * @param array<string,mixed> $context     Admin context.
	 * @return array<string,mixed>
	 */
	private static function build_params( array $extracted, array $pattern_row, array $context ) {
		$params = array();
		$schema = isset( $pattern_row['param_schema'] ) && is_array( $pattern_row['param_schema'] )
			? $pattern_row['param_schema']
			: array();

		foreach ( $schema as $key => $value ) {
			if ( is_string( $value ) && preg_match( '/^\{([a-z_]+)\}$/', $value, $m ) ) {
				$placeholder = $m[1];
				if ( isset( $extracted[ $placeholder ] ) ) {
					$params[ $key ] = 'countries' === $key ? array( $extracted[ $placeholder ] ) : $extracted[ $placeholder ];
				}
			} else {
				$params[ $key ] = $value;
			}
		}

		// Merge extracted entities not in param_schema.
		foreach ( $extracted as $key => $value ) {
			if ( 'country' === $key && empty( $params['countries'] ) ) {
				$params['countries'] = array( $value );
			} elseif ( 'country_list' === $key && empty( $params['variants'] ) ) {
				$params['variants'] = array(
					array(
						'countries' => is_array( $value ) ? $value : array( $value ),
						'mode'      => 'include_only',
					),
				);
			} elseif ( 'page' === $key && empty( $params['page_ref'] ) ) {
				$params['page_ref'] = is_string( $value ) ? $value : '';
			} elseif ( 'weather_condition' === $key && empty( $params['weather_condition'] ) ) {
				$params['weather_condition'] = $value;
			} elseif ( 'device' === $key && empty( $params['device'] ) ) {
				$params['device'] = $value;
				if ( empty( $params['mode'] ) ) {
					$params['mode'] = false !== strpos( (string) ( $context['normalised_phrase'] ?? '' ), 'hide' ) ? 'exclude' : 'include_only';
				}
			}
		}

		if ( ! empty( $context['target_type'] ) && empty( $params['target_type'] ) ) {
			$params['target_type'] = (string) $context['target_type'];
		}
		if ( ! empty( $context['target_id'] ) && empty( $params['target_id'] ) ) {
			$params['target_id'] = (int) $context['target_id'];
		}

		return $params;
	}

	/**
	 * Map diagnostics intents to context-specific actions.
	 *
	 * @param array<string,mixed> $params     Params.
	 * @param string              $intent_key Intent key.
	 * @param array<string,mixed> $context    Context.
	 * @param string              $phrase     Normalised phrase.
	 * @return array<string,mixed>
	 */
	private static function apply_context_diagnostics( array $params, $intent_key, array $context, $phrase ) {
		if ( 'diagnose_visibility' !== $intent_key && 'rule_conflict_explain' !== $intent_key ) {
			return $params;
		}
		if ( false !== strpos( $phrase, 'popup' ) || ! empty( $context['popup_id'] ) ) {
			$params['_resolved_action'] = 'geocore_run_popup_diagnostics';
		} elseif ( ! empty( $context['rule_id'] ) || false !== strpos( $phrase, 'rule' ) ) {
			$params['_resolved_action'] = 'geocore_run_rule_diagnostics';
		} elseif ( false !== strpos( $phrase, 'conflict' ) || false !== strpos( $phrase, 'explain' ) ) {
			$params['_resolved_action'] = 'geocore_explain_rule_conflicts';
		} else {
			$params['_resolved_action'] = 'geocore_run_page_audit';
		}
		return $params;
	}

	/**
	 * @param string                   $intent_key Intent.
	 * @param string                   $action_key Action.
	 * @param array<string,mixed>      $params     Params.
	 * @param array{type:string,id:int}|null $target Target.
	 * @return string
	 */
	private static function build_summary( $intent_key, $action_key, array $params, $target ) {
		$target_label = $target ? sprintf( '%s #%d', $target['type'], $target['id'] ) : __( 'current context', 'reactwoo-geo-ai' );

		if ( 'country_include' === $intent_key && ! empty( $params['countries'] ) ) {
			$countries = is_array( $params['countries'] ) ? implode( ', ', $params['countries'] ) : (string) $params['countries'];
			return sprintf(
				/* translators: 1: country codes, 2: target label */
				__( 'Create a country-only targeting rule for %2$s (%1$s).', 'reactwoo-geo-ai' ),
				$countries,
				$target_label
			);
		}
		if ( 'country_exclude' === $intent_key && ! empty( $params['countries'] ) ) {
			$countries = is_array( $params['countries'] ) ? implode( ', ', $params['countries'] ) : (string) $params['countries'];
			return sprintf(
				/* translators: 1: country codes, 2: target label */
				__( 'Exclude countries from targeting for %2$s (%1$s).', 'reactwoo-geo-ai' ),
				$countries,
				$target_label
			);
		}

		return sprintf(
			/* translators: 1: action key, 2: target label */
			__( 'Proposed action: %1$s for %2$s.', 'reactwoo-geo-ai' ),
			str_replace( 'geocore_', '', $action_key ),
			$target_label
		);
	}
}
