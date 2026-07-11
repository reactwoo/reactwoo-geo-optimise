<?php
/**
 * Local-first multi-action geo assistant interpretation planner.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Geo_Assistant_Planner {

	const AI_CONFIDENCE_THRESHOLD = 0.72;

	/**
	 * @param string              $raw_phrase User input.
	 * @param array<string,mixed> $context    Context.
	 * @param array<int,array>    $entities   Entity rows.
	 * @return array<string,mixed>
	 */
	public static function interpret( $raw_phrase, array $context = array(), array $entities = array() ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $raw_phrase );
		$plan   = self::empty_plan( $raw_phrase );
		$decisions = array();

		$confirmation = class_exists( 'RWGA_Planner_Confirmation_Instruction_Resolver', false )
			? RWGA_Planner_Confirmation_Instruction_Resolver::extract( $phrase )
			: array(
				'phrase'                   => $phrase,
				'confirmation_instruction' => null,
				'ignored'                  => array(),
			);
		$phrase_for_parse = (string) ( $confirmation['phrase'] ?? $phrase );
		$plan['confirmation_instruction'] = $confirmation['confirmation_instruction'] ?? null;
		$plan['debug']['normalised_input']     = $phrase;
		$plan['debug']['ignored_meta_phrases'] = is_array( $confirmation['ignored'] ?? null ) ? $confirmation['ignored'] : array();

		if ( '' === $phrase_for_parse && empty( $plan['confirmation_instruction'] ) ) {
			$plan['status'] = RWGA_Geo_Action_Types::STATUS_FAILED;
			return $plan;
		}

		$learned = '' !== $phrase_for_parse ? RWGA_Planner_Learned_Patterns::match( $phrase_for_parse ) : null;
		if ( is_array( $learned ) ) {
			$decisions[] = 'learned_pattern_matched';
		}

		$page_context = array();
		$session      = array( 'currentTarget' => null );
		$context['location_clarification'] = '' !== $phrase_for_parse
			&& class_exists( 'RWGA_Planner_Region_Ambiguity_Resolver', false )
			&& RWGA_Planner_Region_Ambiguity_Resolver::wants_clarification( $phrase_for_parse );
		$campaign     = '' !== $phrase_for_parse && class_exists( 'RWGA_Planner_Campaign_Resolver', false )
			? RWGA_Planner_Campaign_Resolver::detect_from_clause( $phrase_for_parse )
			: null;

		$actions = array();
		$clauses = array();
		$raw_for_parse = self::strip_confirmation_from_raw( $raw_phrase, $plan['debug']['ignored_meta_phrases'] );

		if ( self::should_use_rule_plan_parser( $phrase_for_parse ) ) {
			$clause_row = array(
				'raw'   => $phrase_for_parse,
				'type'  => 'rule',
				'index' => 0,
			);
			$actions    = self::build_actions_from_clause(
				$phrase_for_parse,
				$phrase_for_parse,
				$entities,
				$context,
				array(),
				$clause_row
			);
			if ( ! empty( $actions ) ) {
				$decisions[]                  = 'rule_plan_parser';
				$plan['debug']['parser_used'] = 'rule_plan_parser';
				$plan['debug']['intent']      = 'create_rule';
				self::attach_rule_plan_debug( $plan, $actions );
			}
		}

		if ( empty( $actions ) && self::should_use_variant_plan_parser( $phrase_for_parse ) ) {
			$variant_plan = RWGA_Variant_Plan_Parser::parse( $raw_for_parse, $entities, $context );
			$actions      = RWGA_Planner_Variant_Resolver::from_variant_plan_parse( $variant_plan, $entities );
			if ( ! empty( $actions ) ) {
				$decisions[] = 'variant_plan_parser';
				$plan['debug']['parser_used'] = 'variant_plan_parser';
			}
		}

		if ( empty( $actions ) && '' !== $phrase_for_parse ) {
			$clauses = RWGA_Planner_Action_Clause_Splitter::split( $phrase_for_parse );
			$plan['debug']['clauses'] = $clauses;

			if ( count( $clauses ) > 1 || self::clauses_have_typed_rows( $clauses ) ) {
				foreach ( $clauses as $clause_row ) {
					$context['session']  = $session;
					$context['campaign'] = $campaign;
					$built = self::build_actions_from_clause_row(
						$clause_row,
						$phrase_for_parse,
						$entities,
						$context,
						$page_context
					);
					foreach ( $built as $action ) {
						if ( is_array( $action ) && class_exists( 'RWGA_Planner_Inherited_Target_Resolver', false ) ) {
							$session = RWGA_Planner_Inherited_Target_Resolver::remember_target(
								$session,
								is_array( $action['target'] ?? null ) ? $action['target'] : array()
							);
						}
					}
					$actions = array_merge( $actions, $built );
				}
				$decisions[] = 'multi_clause_split';
			} else {
				$actions = self::build_actions_from_clause( $phrase_for_parse, $phrase_for_parse, $entities, $context, $page_context );
			}
		}

		if ( ! empty( $plan['debug']['ignored_meta_phrases'] ) ) {
			$plan['debug']['phantom_actions_removed'] = true;
		}
		$guard = self::guard_create_rule_interpretation( $actions, $phrase_for_parse, $entities, $context );
		if ( is_array( $guard ) ) {
			if ( ! empty( $guard['actions'] ) && is_array( $guard['actions'] ) ) {
				$actions = $guard['actions'];
			}
			if ( ! empty( $guard['decision'] ) ) {
				$decisions[] = (string) $guard['decision'];
				$plan['debug']['decisions'] = $decisions;
			}
			if ( ! empty( $guard['invalid_interpretation'] ) && is_array( $guard['invalid_interpretation'] ) ) {
				$plan['invalid_interpretation'] = $guard['invalid_interpretation'];
				$plan['status']                   = RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION;
				$plan['confidence']               = min( (float) ( $plan['confidence'] ?? 1 ), 0.35 );
			}
		}
		$actions          = self::mark_shared_variant_targets( $actions, $phrase_for_parse );
		$plan['debug']['action_count'] = count( $actions );

		if ( '' !== $phrase_for_parse
			&& self::is_variant_list_grouping_ambiguous( $phrase_for_parse, $entities )
			&& count( $actions ) < 2 ) {
			$plan['clarification'] = array(
				'type'    => 'variant_grouping',
				'message' => __( 'Do you want one shared variant for all listed countries, or separate variants for each country?', 'reactwoo-geocore' ),
				'options' => array(
					array( 'label' => __( 'One shared variant', 'reactwoo-geocore' ), 'value' => 'shared_variant' ),
					array( 'label' => __( 'Separate variants per country', 'reactwoo-geocore' ), 'value' => 'separate_variants' ),
				),
			);
			$plan['status'] = RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION;
			$decisions[]    = 'variant_grouping_ambiguous';
		}

		$page_context = '' !== $phrase_for_parse && class_exists( 'RWGA_Variant_Plan_Parser', false )
			? RWGA_Variant_Plan_Parser::detect_page_context( $phrase_for_parse )
			: array();
		$clarification = '' !== $phrase_for_parse
			? RWGA_Planner_Resolve_Clarifications::detect( $actions, $phrase_for_parse, $page_context, $entities )
			: null;
		if ( is_array( $clarification ) ) {
			$plan['clarification'] = $clarification;
			$plan['status']        = RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION;
		}

		$warnings = array();
		foreach ( $actions as $idx => $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			if ( ! empty( $action['warnings'] ) && is_array( $action['warnings'] ) ) {
				$warnings = array_merge( $warnings, $action['warnings'] );
				continue;
			}
			$loc = RWGA_Planner_Location_Resolver::resolve_from_text(
				(string) ( $action['sourceClause'] ?? $phrase_for_parse ),
				$entities
			);
			if ( ! empty( $loc['warnings'] ) ) {
				$actions[ $idx ]['warnings'] = $loc['warnings'];
				$warnings                    = array_merge( $warnings, $loc['warnings'] );
			}
		}
		$plan['warnings'] = array_values( array_unique( $warnings ) );
		$actions          = self::apply_weather_notes( $actions );
		$plan['actions']  = $actions;
		if ( ! empty( $plan['confirmation_instruction'] ) && ! empty( $plan['actions'][0] ) && is_array( $plan['actions'][0] ) ) {
			$plan['actions'][0]['confirmation_instruction'] = $plan['confirmation_instruction'];
		}
		$plan['confidence'] = self::plan_confidence( $actions, $learned );
		$plan['debug']['decisions'] = $decisions;

		$entity_clarification = self::build_unresolved_entity_clarification( $actions );
		if ( is_array( $entity_clarification ) ) {
			// Unresolved synced audiences/campaigns: keep the actions visible (with
			// their unresolved markers) but require the user to choose before run.
			$plan['clarification']      = $entity_clarification;
			$plan['status']             = RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION;
			$plan['confidence']         = min( (float) $plan['confidence'], 0.5 );
			$decisions[]                = 'synced_entity_clarification';
			$plan['debug']['decisions'] = $decisions;
		} elseif ( class_exists( 'RWGA_Planner_Plan_Validator', false ) ) {
			$validation = RWGA_Planner_Plan_Validator::validate( $phrase_for_parse, $actions, $entities, $clauses );
			if ( is_array( $validation ) ) {
				$plan['debug']['draft_actions'] = $actions;
				$plan['actions']                = array();
				$plan['clarification']          = $validation;
				$plan['status']                 = RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION;
				$plan['confidence']             = min( (float) $plan['confidence'], 0.45 );
				$decisions[]                    = 'plan_validation_failed';
				$plan['debug']['decisions']     = $decisions;
			}
		}

		if ( empty( $plan['actions'] ) && empty( $plan['clarification'] ) ) {
			if ( ! empty( $plan['confirmation_instruction'] ) ) {
				$plan['status']     = RWGA_Geo_Action_Types::STATUS_NEEDS_CONFIRMATION;
				$plan['confidence'] = 0.85;
				return $plan;
			}
			$plan['status'] = RWGA_Geo_Action_Types::STATUS_FAILED;
			return $plan;
		}

		if ( RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION !== $plan['status'] ) {
			$plan['status'] = $plan['confidence'] >= self::AI_CONFIDENCE_THRESHOLD
				? RWGA_Geo_Action_Types::STATUS_NEEDS_CONFIRMATION
				: RWGA_Geo_Action_Types::STATUS_DRAFT;
		}

		$ai_plan = RWGA_Planner_Ai_Fallback::maybe_improve( $raw_phrase, $plan, $context, $entities );
		if ( is_array( $ai_plan ) ) {
			$plan = array_merge( $plan, $ai_plan );
			$plan['debug']['decisions'][] = 'ai_fallback_merged';
		}

		if ( ! empty( $plan['actions'] ) && class_exists( 'RWGA_Planner_Action_Card_Builder', false ) ) {
			$cards = RWGA_Planner_Action_Card_Builder::build( $plan['actions'], $context, $entities );
			$plan['action_cards']             = $cards['cards'];
			$plan['fields_needing_attention'] = $cards['fields_needing_attention'];
			$plan['requires_resolution']      = $cards['requires_resolution'];
			$plan['shared_targets']           = $cards['shared_targets'];
			if ( ! empty( $cards['shared_targets'][0] ) && is_array( $cards['shared_targets'][0] ) ) {
				$shared = $cards['shared_targets'][0];
				$plan['shared_target'] = array(
					'type'    => (string) ( $shared['type'] ?? 'page' ),
					'raw'     => (string) ( $shared['raw'] ?? '' ),
					'status'  => (string) ( $shared['status'] ?? 'needs_confirmation' ),
					'matches' => is_array( $shared['suggestions'] ?? null ) ? $shared['suggestions'] : array(),
				);
			} else {
				$shared = self::infer_shared_target( $plan['actions'], $phrase_for_parse );
				if ( is_array( $shared ) ) {
					$plan['shared_target'] = $shared;
				}
			}
			if ( $cards['requires_resolution'] && RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION !== $plan['status'] ) {
				$plan['status']     = RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION;
				$plan['confidence'] = min( (float) $plan['confidence'], 0.5 );
			}
		}

		$copy = RWGA_Planner_Confirmation_Builder::build( $plan );
		$plan['confirmationSummary'] = $copy['summary'];
		$plan['setupSummary']        = $copy['setup_summary'];

		return $plan;
	}

	/**
	 * @param array<string,mixed> $clause_row   Clause row from splitter.
	 * @param string              $phrase       Full phrase.
	 * @param array<int,array>    $entities     Entities.
	 * @param array<string,mixed> $context      Context.
	 * @param array<string,mixed> $page_context Page context.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_actions_from_clause_row( array $clause_row, $phrase, array $entities, array $context, array $page_context ) {
		$clause = trim( (string) ( $clause_row['raw'] ?? '' ) );
		$type   = (string) ( $clause_row['type'] ?? '' );

		if ( class_exists( 'RWGA_Planner_Confirmation_Instruction_Resolver', false )
			&& RWGA_Planner_Confirmation_Instruction_Resolver::is_confirmation_only( $clause ) ) {
			return array();
		}

		if ( 'variant_child' === $type && ! empty( $clause_row['parent'] ) && is_array( $clause_row['parent'] ) ) {
			$child_index = isset( $clause_row['childIndex'] )
				? (int) $clause_row['childIndex']
				: ( isset( $clause_row['index'] ) ? (int) $clause_row['index'] + 1 : null );
			return array(
				RWGA_Planner_Parent_Variant_Resolver::build_child_action(
					$clause,
					$clause_row['parent'],
					$entities,
					$child_index
				),
			);
		}

		return self::build_actions_from_clause( $clause, $phrase, $entities, $context, $page_context, $clause_row );
	}

	/**
	 * @param array<int,array<string,mixed>> $clauses Clause rows.
	 * @return bool
	 */
	private static function clauses_have_typed_rows( array $clauses ) {
		foreach ( $clauses as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type = (string) ( $row['type'] ?? '' );
			if ( in_array( $type, array( 'variant_child', 'rule', 'test', 'diagnose', 'update', 'campaign_targeting', 'variant_version', 'variant_create' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string                   $clause       Clause text.
	 * @param string                   $phrase       Full phrase.
	 * @param array<int,array>         $entities     Entities.
	 * @param array<string,mixed>      $context      Context.
	 * @param array<string,mixed>      $page_context Page context.
	 * @param array<string,mixed>|null $clause_row   Optional clause metadata.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_actions_from_clause( $clause, $phrase, array $entities, array $context, array $page_context, $clause_row = null ) {
		unset( $page_context );
		$clause   = trim( (string) $clause );
		$type_row = RWGA_Planner_Action_Type_Detector::detect( $clause, is_array( $clause_row ) ? $clause_row : array() );
		$target   = RWGA_Planner_Target_Resolver::resolve( $clause, $phrase, $context, (string) $type_row['type'], is_array( $clause_row ) ? $clause_row : array() );
		if ( class_exists( 'RWGA_Planner_Target_Resolver', false ) && is_array( $target ) ) {
			$clean_label = RWGA_Planner_Target_Resolver::clean_detected_label(
				(string) ( $target['label'] ?? '' ),
				(string) ( $target['type'] ?? 'page' )
			);
			if ( '' !== $clean_label ) {
				$target['label'] = $clean_label;
				$target['slug']  = sanitize_title( $clean_label );
			}
		}

		if ( RWGA_Geo_Action_Types::CREATE_VARIANT === $type_row['type']
			&& ( RWGA_Planner_Action_Clause_Splitter::has_variant_pair_marker( $clause )
				|| preg_match( '/\bone\s+will\b|\bone\s+should\b/i', $clause ) ) ) {
			$expanded = RWGA_Planner_Variant_Resolver::expand_from_clause( $clause, $target, $entities, $type_row );
			if ( ! empty( $expanded ) ) {
				return $expanded;
			}
		}

		$cond         = RWGA_Planner_Condition_Resolver::resolve( $clause, $entities, $context );
		$relationship = RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING === $type_row['type'] ? 'original' : 'variant';
		$variant_idx  = RWGA_Geo_Action_Types::CREATE_VARIANT === $type_row['type'] ? 1 : null;
		$variant_lbl  = '';
		$variant_src  = '' !== (string) ( $target['sourcePage'] ?? '' )
			? (string) $target['sourcePage']
			: (string) ( $target['label'] ?? '' );

		if ( RWGA_Geo_Action_Types::CREATE_VARIANT === $type_row['type']
			&& class_exists( 'RWGA_Planner_Second_Version_Resolver', false ) ) {
			$version_row = RWGA_Planner_Second_Version_Resolver::detect( $clause );
			if ( is_array( $version_row ) ) {
				$variant_idx = (int) ( $version_row['index'] ?? 2 );
			}
		}
		if ( RWGA_Geo_Action_Types::CREATE_VARIANT === $type_row['type'] && '' === $variant_lbl ) {
			$variant_lbl = self::variant_label_from_conditions( $target, $cond );
		}

		$unresolved = array(
			'audiences'       => (array) ( $cond['unresolved']['audiences'] ?? array() ),
			'campaigns'       => array(),
			'locations'       => (array) ( $cond['unresolved']['locations'] ?? array() ),
			'traffic_sources' => (array) ( $cond['unresolved']['traffic_sources'] ?? array() ),
		);

		$action = array(
			'id'                  => self::new_id(),
			'type'                => (string) $type_row['type'],
			'target'              => $target,
			'variant'             => array(
				'index'        => $variant_idx,
				'label'        => $variant_lbl,
				'sourcePage'   => $variant_src,
				'relationship' => $relationship,
			),
			'conditions'          => $cond['conditions'],
			'location_labels'     => $cond['location_labels'] ?? array(),
			'warnings'            => $cond['warnings'] ?? array(),
			'operation'           => array(
				'visibility' => (string) $type_row['visibility'],
				'mode'       => (string) $type_row['mode'],
			),
			'confidence'          => min( (float) $type_row['confidence'], (float) $cond['confidence'] ),
			'needsClarification'  => false,
			'clarificationReason' => null,
			'sourceClause'        => $clause,
		);

		if ( RWGA_Geo_Action_Types::UPDATE_CAMPAIGN_TARGETING === $type_row['type']
			&& class_exists( 'RWGA_Planner_Campaign_Resolver', false ) ) {
			$campaign_resolution = RWGA_Planner_Campaign_Resolver::resolve_synced( $clause, $entities, $context );
			if ( is_array( $campaign_resolution ) ) {
				if ( is_array( $campaign_resolution['matched'] ?? null ) ) {
					$action['campaign'] = $campaign_resolution['matched'];
				} elseif ( is_array( $campaign_resolution['unresolved'] ?? null ) ) {
					$unresolved['campaigns'][] = $campaign_resolution['unresolved'];
					$detected                  = RWGA_Planner_Campaign_Resolver::detect_from_clause( $clause );
					if ( is_array( $detected ) ) {
						$action['campaign'] = array(
							'label'  => (string) $detected['label'],
							'status' => (string) $campaign_resolution['unresolved']['status'],
						);
					}
				}
			}
		}

		$action['unresolved'] = $unresolved;
		if ( ! empty( $unresolved['audiences'] ) || ! empty( $unresolved['campaigns'] ) ) {
			$action['needsClarification']  = true;
			$action['clarificationReason'] = ! empty( $unresolved['audiences'] )
				? 'audience_not_defined'
				: 'campaign_not_defined';
		}

		return array( $action );
	}

	/**
	 * Build a clarification payload when any action references a synced audience
	 * or campaign that could not be resolved against the site's synced registry.
	 *
	 * @param array<int,array<string,mixed>> $actions Actions.
	 * @return array<string,mixed>|null
	 */
	private static function build_unresolved_entity_clarification( array $actions ) {
		$audiences   = array();
		$campaigns   = array();
		$ambiguities = array();

		$position = 0;
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$position++;
			if ( empty( $action['unresolved'] ) || ! is_array( $action['unresolved'] ) ) {
				continue;
			}
			$target_label = self::action_target_label( $action );
			foreach ( (array) ( $action['unresolved']['audiences'] ?? array() ) as $row ) {
				if ( ! is_array( $row ) || '' === (string) ( $row['raw'] ?? '' ) ) {
					continue;
				}
				$row_status = (string) ( $row['status'] ?? '' );
				if ( in_array( $row_status, array( 'matches_any', 'audience_any' ), true ) ) {
					continue;
				}
				$row['action_index'] = $position;
				$row['target_label'] = $target_label;
				$audiences[]         = $row;
				$ambiguities[]       = self::entity_ambiguity_row( 'audience', $row, $position, $target_label );
			}
			foreach ( (array) ( $action['unresolved']['campaigns'] ?? array() ) as $row ) {
				if ( ! is_array( $row ) || '' === (string) ( $row['raw'] ?? '' ) ) {
					continue;
				}
				$row['action_index'] = $position;
				$row['target_label'] = $target_label;
				$campaigns[]         = $row;
				$ambiguities[]       = self::entity_ambiguity_row( 'campaign', $row, $position, $target_label );
			}
		}

		if ( empty( $audiences ) && empty( $campaigns ) ) {
			return null;
		}

		if ( ! empty( $audiences ) ) {
			$reason  = 'audience_not_defined';
			$message = __( 'One or more audiences are not defined in your synced audiences. Choose an audience to continue.', 'reactwoo-geo-ai' );
		} else {
			$reason  = 'campaign_not_defined';
			$message = __( 'One or more campaigns are not defined in your synced campaigns. Choose a campaign to continue.', 'reactwoo-geo-ai' );
		}

		return array(
			'type'        => 'synced_entity_unresolved',
			'reason'      => $reason,
			'message'     => $message,
			'unresolved'  => array(
				'audiences' => $audiences,
				'campaigns' => $campaigns,
			),
			'ambiguities' => $ambiguities,
			'options'     => array(
				array( 'label' => __( 'Choose audience/campaign', 'reactwoo-geo-ai' ), 'value' => 'choose_entity' ),
				array( 'label' => __( 'Ignore this condition', 'reactwoo-geo-ai' ), 'value' => 'ignore_condition' ),
				array( 'label' => __( 'Cancel', 'reactwoo-geo-ai' ), 'value' => 'cancel' ),
			),
		);
	}

	/**
	 * Human-readable target label for an action (page/variant/rule/popup, etc.).
	 *
	 * @param array<string,mixed> $action Action.
	 * @return string
	 */
	private static function action_target_label( array $action ) {
		$target = is_array( $action['target'] ?? null ) ? $action['target'] : array();
		$label  = trim( (string) ( $target['label'] ?? '' ) );
		if ( '' === $label && is_array( $action['variant'] ?? null ) ) {
			$label = trim( (string) ( $action['variant']['label'] ?? '' ) );
		}
		return $label;
	}

	/**
	 * Build a per-action ambiguity row for an unresolved synced audience/campaign
	 * so the assistant can name the exact action it relates to.
	 *
	 * @param string              $field        'audience' or 'campaign'.
	 * @param array<string,mixed> $row          Unresolved row.
	 * @param int                 $position     1-based action index.
	 * @param string              $target_label Owning action target label.
	 * @return array<string,mixed>
	 */
	private static function entity_ambiguity_row( $field, array $row, $position, $target_label ) {
		$suggestions  = array();
		$alternatives = array();
		foreach ( (array) ( $row['suggestions'] ?? array() ) as $suggestion ) {
			if ( ! is_array( $suggestion ) ) {
				continue;
			}
			$name = trim( (string) ( $suggestion['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$suggestions[]  = $suggestion;
			$alternatives[] = $name;
		}

		return array(
			'field'        => $field,
			'raw'          => (string) ( $row['raw'] ?? '' ),
			'status'       => (string) ( $row['status'] ?? '' ),
			'likely'       => '',
			'alternatives' => $alternatives,
			'suggestions'  => $suggestions,
			'action_index' => (int) $position,
			'target_label' => (string) $target_label,
			'question'     => self::entity_clarification_question( $field, (string) ( $row['raw'] ?? '' ), $position, $target_label ),
			'notes'        => array_values( array_filter( array( (string) ( $row['message'] ?? '' ) ) ) ),
		);
	}

	/**
	 * @param string $field        'audience' or 'campaign'.
	 * @param string $raw          Raw phrase.
	 * @param int    $position     1-based action index.
	 * @param string $target_label Owning action target label.
	 * @return string
	 */
	private static function entity_clarification_question( $field, $raw, $position, $target_label ) {
		$where = '' !== (string) $target_label
			/* translators: 1: action number, 2: target label. */
			? sprintf( __( 'action %1$d (%2$s)', 'reactwoo-geo-ai' ), (int) $position, $target_label )
			/* translators: %d: action number. */
			: sprintf( __( 'action %d', 'reactwoo-geo-ai' ), (int) $position );

		if ( 'campaign' === $field ) {
			/* translators: 1: detected phrase, 2: action reference. */
			return sprintf( __( 'Which synced campaign should “%1$s” use for %2$s?', 'reactwoo-geo-ai' ), $raw, $where );
		}
		/* translators: 1: detected phrase, 2: action reference. */
		return sprintf( __( 'Which synced audience should “%1$s” use for %2$s?', 'reactwoo-geo-ai' ), $raw, $where );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	private static function has_multi_action_markers( $phrase ) {
		if ( class_exists( 'RWGA_Rule_Plan_Parser', false )
			&& RWGA_Rule_Plan_Parser::has_create_rule_primary_intent( $phrase )
			&& ! RWGA_Rule_Plan_Parser::has_explicit_multi_action_boundary( $phrase ) ) {
			return false;
		}
		return class_exists( 'RWGA_Planner_Plan_Validator', false )
			&& RWGA_Planner_Plan_Validator::expected_action_count( $phrase ) > 1;
	}

	/**
	 * Whether the stripped phrase should use the single-action rule plan parser
	 * instead of multi-clause splitting (show/hide inside one create_rule).
	 *
	 * @param string $phrase Normalised phrase without confirmation/meta tail.
	 * @return bool
	 */
	private static function should_use_rule_plan_parser( $phrase ) {
		if ( '' === trim( (string) $phrase ) ) {
			return false;
		}
		if ( ! class_exists( 'RWGA_Rule_Plan_Parser', false )
			|| ! RWGA_Rule_Plan_Parser::is_rule_plan_command( $phrase ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Recover single create_rule actions when a compound phrase was split into show/hide/update-original rows.
	 *
	 * @param array<int,array<string,mixed>> $actions  Built actions.
	 * @param string                         $phrase   Normalised phrase.
	 * @param array<int,array>               $entities Entities.
	 * @param array<string,mixed>            $context  Context.
	 * @return array{actions?:array<int,array<string,mixed>>,decision?:string,invalid_interpretation?:array<string,mixed>}|null
	 */
	private static function guard_create_rule_interpretation( array $actions, $phrase, array $entities, array $context ) {
		if ( ! class_exists( 'RWGA_Rule_Plan_Parser', false )
			|| ! RWGA_Rule_Plan_Parser::has_create_rule_primary_intent( $phrase ) ) {
			return null;
		}

		$phantom_types = array(
			RWGA_Geo_Action_Types::SHOW,
			RWGA_Geo_Action_Types::HIDE,
			RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING,
		);
		$has_phantom = false;
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			if ( in_array( (string) ( $action['type'] ?? '' ), $phantom_types, true ) ) {
				$has_phantom = true;
				break;
			}
		}

		if ( ! $has_phantom ) {
			return null;
		}

		$recovered = self::build_actions_from_clause(
			$phrase,
			$phrase,
			$entities,
			$context,
			array(),
			array(
				'raw'   => $phrase,
				'type'  => 'rule',
				'index' => 0,
			)
		);
		if ( 1 === count( $recovered )
			&& RWGA_Geo_Action_Types::CREATE_RULE === (string) ( $recovered[0]['type'] ?? '' ) ) {
			return array(
				'actions'  => $recovered,
				'decision' => 'create_rule_phantom_split_recovered',
			);
		}

		return array(
			'invalid_interpretation' => array(
				'type'    => 'phantom_create_rule_split',
				'message' => __( 'This was split into multiple actions, but it looks like one rule. Ask AI to re-check?', 'reactwoo-geocore' ),
			),
		);
	}

	/**
	 * @param array<string,mixed>            $plan    Plan (by ref).
	 * @param array<int,array<string,mixed>> $actions Built actions.
	 * @return void
	 */
	private static function attach_rule_plan_debug( array &$plan, array $actions ) {
		if ( empty( $actions[0] ) || ! is_array( $actions[0] ) ) {
			return;
		}
		$action     = $actions[0];
		$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
		$include    = class_exists( 'RWGA_Planner_Condition_Polarity_Resolver', false )
			? RWGA_Planner_Condition_Polarity_Resolver::include_group( $conditions )
			: (array) ( $conditions['include'] ?? array() );
		$exclude    = class_exists( 'RWGA_Planner_Condition_Polarity_Resolver', false )
			? RWGA_Planner_Condition_Polarity_Resolver::exclude_group( $conditions )
			: (array) ( $conditions['exclude'] ?? array() );

		$plan['debug']['include_conditions'] = self::debug_condition_snapshot( $include, $action );
		$plan['debug']['exclude_conditions'] = self::debug_condition_snapshot( $exclude );

		$target = is_array( $action['target'] ?? null ) ? $action['target'] : array();
		if ( ! empty( $target['label'] ) || ! empty( $target['raw'] ) ) {
			$plan['debug']['shared_target'] = array(
				'raw'    => (string) ( $target['raw'] ?? $target['label'] ?? '' ),
				'status' => (string) ( $target['status'] ?? 'needs_resolution' ),
			);
		}
	}

	/**
	 * @param array<string,mixed>            $group  Include or exclude group.
	 * @param array<string,mixed>|null       $action Optional action for unresolved audiences.
	 * @return array<int,array<string,mixed>>
	 */
	private static function debug_condition_snapshot( array $group, $action = null ) {
		$rows = array();
		foreach ( (array) ( $group['devices'] ?? array() ) as $device ) {
			$rows[] = array(
				'type'  => 'device',
				'value' => (string) $device,
			);
		}
		$countries = (array) ( $group['countries'] ?? array() );
		if ( ! empty( $countries ) ) {
			$row = array(
				'type'  => 'country',
				'value' => $countries,
			);
			if ( count( $countries ) > 1 ) {
				$row['join'] = 'OR';
			}
			$rows[] = $row;
		}
		foreach ( (array) ( $group['utm'] ?? array() ) as $utm ) {
			if ( ! is_array( $utm ) ) {
				continue;
			}
			$field = (string) ( $utm['field'] ?? str_replace( 'utm_', '', (string) ( $utm['key'] ?? '' ) ) );
			$rows[] = array(
				'type'  => 'utm',
				'field' => $field,
				'value' => (string) ( $utm['value'] ?? '' ),
			);
		}
		foreach ( (array) ( $group['pageTypes'] ?? array() ) as $page_type ) {
			$rows[] = array(
				'type'  => 'page_type',
				'value' => (string) $page_type,
			);
		}
		foreach ( (array) ( $group['condition_groups'] ?? array() ) as $condition_group ) {
			if ( ! is_array( $condition_group ) ) {
				continue;
			}
			$rows[] = array(
				'type'       => 'condition_group',
				'label'      => (string) ( $condition_group['label'] ?? '' ),
				'logic'      => (string) ( $condition_group['logic'] ?? 'OR' ),
				'conditions' => (array) ( $condition_group['conditions'] ?? array() ),
			);
		}
		foreach ( (array) ( $group['audiences'] ?? array() ) as $audience ) {
			if ( is_array( $audience ) ) {
				$rows[] = array(
					'type'  => 'audience',
					'value' => (string) ( $audience['name'] ?? $audience['raw'] ?? '' ),
				);
			}
		}
		if ( is_array( $action ) ) {
			foreach ( (array) ( $action['unresolved']['audiences'] ?? array() ) as $audience ) {
				if ( ! is_array( $audience ) ) {
					continue;
				}
				$row = array(
					'type'   => 'audience',
					'status' => (string) ( $audience['status'] ?? 'needs_resolution' ),
				);
				if ( 'matches_any' === ( $row['status'] ?? '' ) && is_array( $audience['segment_keys'] ?? null ) ) {
					$row['operator'] = 'matches_any';
					$row['value']    = $audience['segment_keys'];
				} else {
					$row['value'] = (string) ( $audience['raw'] ?? '' );
				}
				$rows[] = $row;
			}
		}
		return $rows;
	}

	/**
	 * Whether the stripped phrase should use the structured variant plan parser
	 * instead of generic multi-clause splitting.
	 *
	 * @param string $phrase Normalised phrase without confirmation/meta tail.
	 * @return bool
	 */
	private static function should_use_variant_plan_parser( $phrase ) {
		if ( '' === trim( (string) $phrase ) ) {
			return false;
		}
		if ( self::has_multi_action_markers( $phrase ) ) {
			return false;
		}
		if ( ! class_exists( 'RWGA_Variant_Plan_Parser', false )
			|| ! RWGA_Variant_Plan_Parser::is_variant_plan_command( $phrase ) ) {
			return false;
		}
		// Compound phrases with trailing rules, popups, or diagnose clauses belong on
		// the multi-clause path even when they mention versions/variants.
		if ( preg_match( '/\b(?:,\.\s*)?(?:then|and)\s+(?:hide|show|create\s+a\s+rule|diagnose|test\s+what|preview\s+what|check\s+what)\b/i', $phrase ) ) {
			return false;
		}
		// Shop/category variants plus a separate "update the original homepage" action
		// must stay on the multi-clause path so page mismatch is preserved.
		if ( preg_match( '/\b(?:update|change|keep|leave)\s+(?:the\s+)?original\b/i', $phrase )
			&& preg_match( '/\b(?:variant|variation|version)s?\s+of\s+(?!homepage\b|home\b)/i', $phrase ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param array<string,mixed> $target Target row.
	 * @param array<string,mixed> $cond   Condition bundle.
	 * @return string
	 */
	private static function variant_label_from_conditions( array $target, array $cond ) {
		$include = RWGA_Planner_Condition_Polarity_Resolver::include_group( $cond['conditions'] ?? array() );
		$parts   = array();
		if ( ! empty( $cond['location_labels'] ) ) {
			$parts = (array) $cond['location_labels'];
		} else {
			$loc = RWGA_Planner_Location_Resolver::display_label(
				array(
					'countries' => $include['countries'] ?? array(),
					'regions'   => $include['regions'] ?? array(),
					'labels'    => array(),
				)
			);
			if ( '' !== $loc ) {
				$parts[] = $loc;
			}
		}
		if ( ! empty( $include['devices'] ) ) {
			$parts[] = ucfirst( implode( ' + ', (array) $include['devices'] ) );
		}
		if ( ! empty( $include['audiences'] ) ) {
			$names = array();
			foreach ( (array) $include['audiences'] as $audience ) {
				$names[] = is_array( $audience )
					? (string) ( $audience['name'] ?? '' )
					: ucwords( str_replace( '_', ' ', (string) $audience ) );
			}
			$names = array_values( array_filter( $names ) );
			if ( ! empty( $names ) ) {
				$parts[] = implode( ' + ', $names );
			}
		}
		if ( ! empty( $include['visitorStates'] ) ) {
			$parts[] = ucwords( str_replace( '_', ' ', implode( ' + ', (array) $include['visitorStates'] ) ) );
		}
		$page = ucfirst( (string) ( $target['label'] ?? 'page' ) );
		return $page . ( $parts ? ' - ' . implode( ' ', $parts ) : '' );
	}

	/**
	 * @param string           $phrase   Phrase.
	 * @param array<int,array> $entities Entities.
	 * @return bool
	 */
	private static function is_variant_list_grouping_ambiguous( $phrase, $entities ) {
		if ( ! preg_match( '/\bvariants?\b/i', $phrase ) ) {
			return false;
		}
		if ( RWGA_Planner_Action_Clause_Splitter::has_variant_pair_marker( $phrase ) ) {
			return false;
		}
		if ( preg_match( '/\b(?:one|two|three|four|five)\s+(?:variant|variants|shared)\b/i', $phrase ) ) {
			return false;
		}
		$countries = class_exists( 'RWGA_Multi_Variant_Interpreter', false )
			? RWGA_Multi_Variant_Interpreter::parse_country_list( $phrase, $entities )
			: array();
		if ( count( $countries ) < 3
			&& preg_match_all( '/\b(france|spain|italy|germany|portugal|russia|ireland|poland|netherlands|belgium|sweden|norway|denmark|finland|austria|switzerland|greece|turkey|japan|china|india|brazil|mexico|canada|australia|uae|america|usa)\b/i', $phrase, $matches )
			&& count( $matches[0] ) >= 3 ) {
			return true;
		}
		return count( $countries ) >= 3;
	}

	/**
	 * @param array<int,array<string,mixed>> $actions Actions.
	 * @param array<string,mixed>|null       $learned Learned pattern.
	 * @return float
	 */
	private static function plan_confidence( array $actions, $learned ) {
		if ( empty( $actions ) ) {
			return 0.0;
		}
		$sum = 0.0;
		foreach ( $actions as $action ) {
			$sum += (float) ( $action['confidence'] ?? 0.5 );
		}
		$avg = $sum / count( $actions );
		if ( is_array( $learned ) ) {
			$avg += (float) ( $learned['confidenceBoost'] ?? 0 );
		}
		return min( 0.98, round( $avg, 2 ) );
	}

	/**
	 * Flag variant-plan actions that share one detected page target.
	 *
	 * @param array<int,array<string,mixed>> $actions Actions.
	 * @param string                         $phrase  Normalised phrase.
	 * @return array<int,array<string,mixed>>
	 */
	private static function mark_shared_variant_targets( array $actions, $phrase ) {
		if ( ! is_array( self::infer_shared_target( $actions, $phrase ) ) ) {
			return $actions;
		}
		foreach ( $actions as $idx => $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$type = (string) ( $action['type'] ?? '' );
			if ( in_array( $type, array( RWGA_Geo_Action_Types::CREATE_VARIANT, RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING ), true ) ) {
				$actions[ $idx ]['uses_shared_target'] = true;
			}
		}
		return $actions;
	}

	/**
	 * Infer a shared page target when every action points at the same detected page.
	 *
	 * @param array<int,array<string,mixed>> $actions Parsed actions.
	 * @param string                         $phrase  Normalised phrase.
	 * @return array<string,mixed>|null
	 */
	private static function infer_shared_target( array $actions, $phrase ) {
		if ( count( $actions ) < 2 ) {
			return null;
		}
		$labels = array();
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$label = strtolower( trim( (string) ( $action['target']['label'] ?? '' ) ) );
			if ( '' !== $label ) {
				$labels[] = $label;
			}
		}
		if ( count( $labels ) >= 2 && 1 === count( array_unique( $labels ) ) ) {
			return array(
				'type'    => 'page',
				'raw'     => (string) ( $actions[0]['target']['label'] ?? $labels[0] ),
				'status'  => 'needs_confirmation',
				'matches' => array(),
			);
		}
		if ( ! class_exists( 'RWGA_Variant_Plan_Parser', false )
			|| ! preg_match( '/\b(?:variant|variation|version)s?\s+of\b/i', $phrase ) ) {
			return null;
		}
		$page_ctx = RWGA_Variant_Plan_Parser::detect_page_context( $phrase );
		$raw      = (string) ( $page_ctx['variant_source'] ?? $page_ctx['primary'] ?? '' );
		if ( '' === $raw ) {
			return null;
		}
		return array(
			'type'    => 'page',
			'raw'     => $raw,
			'status'  => 'needs_confirmation',
			'matches' => array(),
		);
	}

	/**
	 * Attach no-weather-restriction notes when the source clause explicitly allows all weather.
	 *
	 * @param array<int,array<string,mixed>> $actions Actions.
	 * @return array<int,array<string,mixed>>
	 */
	private static function apply_weather_notes( array $actions ) {
		foreach ( $actions as $idx => $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$clause = (string) ( $action['sourceClause'] ?? '' );
			if ( ! preg_match( '/\b(?:all\s+weather(?:\s+conditions)?|any\s+weather|regardless\s+of\s+weather|whatever\s+the\s+weather)\b/i', $clause ) ) {
				continue;
			}
			$notes = is_array( $action['notes'] ?? null ) ? $action['notes'] : array();
			if ( ! in_array( 'no_weather_restriction', $notes, true ) ) {
				$notes[] = 'no_weather_restriction';
			}
			$actions[ $idx ]['notes'] = $notes;
			if ( is_array( $action['conditions'] ?? null ) ) {
				$conditions = $action['conditions'];
				if ( isset( $conditions['weather'] ) && is_array( $conditions['weather'] ) ) {
					$conditions['weather'] = array_values(
						array_filter(
							$conditions['weather'],
							static function ( $value ) {
								return 'any' !== strtolower( (string) $value );
							}
						)
					);
				}
				if ( isset( $conditions['include']['weather'] ) && is_array( $conditions['include']['weather'] ) ) {
					$conditions['include']['weather'] = array_values(
						array_filter(
							$conditions['include']['weather'],
							static function ( $value ) {
								return 'any' !== strtolower( (string) $value );
							}
						)
					);
				}
				$actions[ $idx ]['conditions'] = $conditions;
			}
		}
		return $actions;
	}

	/**
	 * @param string $source_text Source text.
	 * @return array<string,mixed>
	 */
	private static function empty_plan( $source_text ) {
		return array(
			'id'                       => self::new_id(),
			'intent'                   => RWGA_Geo_Action_Types::PLAN_INTENT,
			'status'                   => RWGA_Geo_Action_Types::STATUS_DRAFT,
			'sourceText'               => $source_text,
			'confidence'               => 0,
			'actions'                  => array(),
			'clarification'            => null,
			'confirmation_instruction' => null,
			'warnings'                 => array(),
			'debug'                    => array(
				'clauses'   => array(),
				'entities'  => array(),
				'decisions' => array(),
			),
		);
	}

	/**
	 * Remove confirmation/meta phrases from the raw user message before parsing.
	 *
	 * @param string              $raw_phrase Raw input.
	 * @param array<int,string>   $ignored    Ignored meta phrases.
	 * @return string
	 */
	private static function strip_confirmation_from_raw( $raw_phrase, array $ignored ) {
		$raw = (string) $raw_phrase;
		foreach ( $ignored as $phrase ) {
			if ( '' === trim( (string) $phrase ) ) {
				continue;
			}
			$raw = (string) preg_replace( '/' . preg_quote( (string) $phrase, '/' ) . '/i', '', $raw );
		}
		return trim( preg_replace( '/\s+/', ' ', $raw ), " \t\n\r\0\x0B,." );
	}

	/**
	 * @return string
	 */
	public static function new_id() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return 'geo_' . bin2hex( random_bytes( 8 ) );
	}

	/**
	 * @param string              $raw_phrase User input.
	 * @param array<string,mixed> $context    Context.
	 * @param array<int,array>    $entities   Entities.
	 * @return array<string,mixed>|null Legacy interpreter result or null when planner cannot match.
	 */
	public static function interpret_as_legacy( $raw_phrase, array $context = array(), array $entities = array() ) {
		$plan = self::interpret( $raw_phrase, $context, $entities );
		if ( empty( $plan['actions'] ) && empty( $plan['clarification'] ) ) {
			return null;
		}
		return RWGA_Planner_Legacy_Adapter::to_interpreter_result( $plan, $context, $entities );
	}
}
