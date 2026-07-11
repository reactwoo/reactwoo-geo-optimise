<?php
/**
 * Build AI-style interpretation payloads with likely meaning and alternatives.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_AI_Interpretation_Builder {

	/**
	 * @param string                           $phrase       Normalised phrase.
	 * @param array<int,array<string,mixed>>   $ambiguities  Ambiguities.
	 * @param array<string,mixed>              $draft        Parser draft.
	 * @param array<int,array>                 $entities     Entities.
	 * @param array<string,mixed>              $context      Context.
	 * @param string                           $source_label Source label for reason text.
	 * @return array<string,mixed>
	 */
	public static function build( $phrase, array $ambiguities, array $draft, array $entities, array $context = array(), $source_label = 'local_intelligence' ) {
		unset( $entities, $context );
		$alternatives = array();
		$reasons      = array();

		foreach ( $ambiguities as $row ) {
			$field = (string) ( $row['field'] ?? '' );
			if ( 'location' === $field ) {
				foreach ( self::location_alternative_buttons( $row ) as $alt ) {
					$alternatives[] = $alt;
				}
				foreach ( (array) ( $row['notes'] ?? array() ) as $note ) {
					$reasons[] = (string) $note;
				}
			} elseif ( 'audience' === $field ) {
				$alternatives[] = array(
					'key'        => 'use_any_audience',
					'label'      => __( 'Match any audience', 'reactwoo-geocore' ),
					'confidence' => 0.78,
					'value'      => 'any_audience',
				);
				$alternatives[] = array(
					'key'                 => 'use_selected_audiences',
					'label'               => __( 'Choose audience groups', 'reactwoo-geocore' ),
					'confidence'          => 0.55,
					'value'               => 'selected_audience_groups',
					'requires_capability' => 'variant_type_audience',
				);
				foreach ( (array) ( $row['notes'] ?? array() ) as $note ) {
					$reasons[] = (string) $note;
				}
			}
		}

		$alternatives[] = array(
			'key'        => 'ask_user',
			'label'      => __( 'Ask me to clarify', 'reactwoo-geocore' ),
			'confidence' => 1,
		);

		$proposal = self::build_proposal_from_likely( $phrase, $ambiguities, $draft );
		$likely   = self::likely_meaning_text( $ambiguities, $proposal );

		return array(
			'likely_meaning' => $likely,
			'reason'         => self::reason_text( $source_label, $reasons, $phrase ),
			'alternatives'   => self::dedupe_alternatives( $alternatives ),
			'proposal_draft' => $proposal,
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $ambiguities Ambiguities.
	 * @param array<string,mixed>           $proposal    Proposal draft.
	 * @return string
	 */
	public static function likely_meaning_text( array $ambiguities, array $proposal ) {
		$parts = array();
		$target = (string) ( $proposal['target']['label'] ?? __( 'Home page', 'reactwoo-geocore' ) );
		$parts[] = sprintf(
			/* translators: %s: target label */
			__( 'Target: %s', 'reactwoo-geo-ai' ),
			$target
		);
		foreach ( (array) ( $proposal['rule']['conditions'] ?? array() ) as $cond ) {
			$parts[] = (string) ( $cond['label'] ?? '' );
		}
		if ( empty( $parts ) ) {
			return __( 'The intelligence layer found a likely targeting interpretation.', 'reactwoo-geo-ai' );
		}
		return __( 'The intelligence layer thinks you mean:', 'reactwoo-geo-ai' ) . ' ' . implode( '; ', array_filter( $parts ) );
	}

	/**
	 * @param string              $phrase      Phrase.
	 * @param array<int,array>    $ambiguities Ambiguities.
	 * @param array<string,mixed> $draft       Draft.
	 * @return array<string,mixed>
	 */
	public static function build_proposal_from_likely( $phrase, array $ambiguities, array $draft ) {
		$page = 'homepage';
		if ( class_exists( 'RWGA_Page_Reference_Resolver', false ) ) {
			$ref = RWGA_Page_Reference_Resolver::detect( $phrase );
			if ( $ref && ! empty( $ref['value'] ) ) {
				$page = (string) $ref['value'];
			}
		}

		$conditions = array();
		if ( ! empty( $draft['conditions'] ) && is_array( $draft['conditions'] ) ) {
			$conditions = $draft['conditions'];
		}

		$location = self::likely_value_for_field( $ambiguities, 'location', 'GB' );
		if ( $location && ! self::has_condition_type( $conditions, 'country' ) ) {
			$conditions[] = self::condition_from_location( $location );
		}

		if ( preg_match( '/\b(?:sunny|sun|rain|raining|snow)\b/i', $phrase, $m ) && ! self::has_condition_type( $conditions, 'weather_condition' ) ) {
			$weather = 'sunny';
			if ( preg_match( '/\b(?:rain|raining)\b/i', $phrase ) ) {
				$weather = 'rain';
			} elseif ( preg_match( '/\bsnow\b/i', $phrase ) ) {
				$weather = 'snow';
			}
			$conditions[] = array(
				'type'     => 'weather_condition',
				'operator' => 'in',
				'value'    => array( $weather ),
				'label'    => sprintf(
					/* translators: %s: weather */
					__( 'Weather: %s', 'reactwoo-geocore' ),
					ucfirst( $weather )
				),
			);
		}

		$audience = self::likely_value_for_field( $ambiguities, 'audience', 'any_audience' );
		if ( $audience && ! self::has_condition_type( $conditions, 'audience' ) ) {
			$conditions[] = array(
				'type'     => 'audience',
				'operator' => 'matches',
				'value'    => array( $audience ),
				'label'    => 'any_audience' === $audience
					? __( 'Audience: Matches any audience', 'reactwoo-geocore' )
					: __( 'Audience: Selected groups', 'reactwoo-geocore' ),
			);
		}

		return array(
			'target' => array(
				'type'  => 'page',
				'ref'   => $page,
				'label' => ucfirst( $page ),
			),
			'rule'   => array(
				'logic'      => (string) ( $draft['condition_match'] ?? 'all' ),
				'conditions' => $conditions,
			),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $ambiguities Ambiguities.
	 * @param string                         $field       Field.
	 * @param string                         $fallback    Fallback.
	 * @return string
	 */
	private static function likely_value_for_field( array $ambiguities, $field, $fallback ) {
		foreach ( $ambiguities as $row ) {
			if ( $field === ( $row['field'] ?? '' ) && ! empty( $row['likely'] ) ) {
				return (string) $row['likely'];
			}
		}
		return $fallback;
	}

	/**
	 * @param string $location Likely location value.
	 * @return array<string,mixed>
	 */
	private static function condition_from_location( $location ) {
		if ( 0 === strpos( $location, 'region:' ) ) {
			return array(
				'type'     => 'region',
				'operator' => 'in',
				'value'    => array( substr( $location, 7 ) ),
				'label'    => sprintf(
					/* translators: %s: region */
					__( 'Location: %s region targeting', 'reactwoo-geocore' ),
					ucfirst( str_replace( array( 'region:', '-', '_' ), array( '', ' ', ' ' ), $location ) )
				),
			);
		}
		return array(
			'type'     => 'country',
			'operator' => 'in',
			'value'    => array( $location ),
			'label'    => sprintf(
				/* translators: %s: country label */
				__( 'Location: %s country targeting', 'reactwoo-geocore' ),
				'GB' === $location ? __( 'United Kingdom', 'reactwoo-geocore' ) : $location
			),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $conditions Conditions.
	 * @param string                         $type       Type.
	 * @return bool
	 */
	private static function has_condition_type( array $conditions, $type ) {
		foreach ( $conditions as $cond ) {
			if ( $type === ( $cond['type'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string              $source_label Source.
	 * @param array<int,string>   $reasons      Reasons.
	 * @param string              $phrase       Phrase.
	 * @return string
	 */
	private static function reason_text( $source_label, array $reasons, $phrase ) {
		unset( $phrase );
		$prefix = 'ai_fallback' === $source_label
			? __( 'The ReactWoo intelligence layer checked this phrase.', 'reactwoo-geo-ai' )
			: __( 'The local intelligence layer analysed this phrase.', 'reactwoo-geo-ai' );
		if ( empty( $reasons ) ) {
			return $prefix;
		}
		return $prefix . ' ' . implode( ' ', $reasons );
	}

	/**
	 * @param array<int,array<string,mixed>> $alternatives Alternatives.
	 * @return array<int,array<string,mixed>>
	 */
	private static function dedupe_alternatives( array $alternatives ) {
		$seen = array();
		$out  = array();
		foreach ( $alternatives as $row ) {
			$key = (string) ( $row['key'] ?? '' );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $row;
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $ai_payload External AI payload.
	 * @return array<string,mixed>
	 */
	public static function normalize_external( array $ai_payload ) {
		$out = array(
			'likely_meaning' => (string) ( $ai_payload['likely_meaning'] ?? $ai_payload['summary'] ?? '' ),
			'reason'         => (string) ( $ai_payload['reason'] ?? '' ),
			'alternatives'   => isset( $ai_payload['alternatives'] ) && is_array( $ai_payload['alternatives'] )
				? $ai_payload['alternatives']
				: array(),
			'proposal_draft' => is_array( $ai_payload['proposal_draft'] ?? null ) ? $ai_payload['proposal_draft'] : array(),
		);
		if ( empty( $out['alternatives'] ) && ! empty( $ai_payload['ai_interpretation']['alternatives'] ) ) {
			$out['alternatives'] = $ai_payload['ai_interpretation']['alternatives'];
		}
		return $out;
	}

	/**
	 * @param array<int,array<string,mixed>> $conditions Conditions.
	 * @param string                         $rule_match all|any.
	 * @return array<string,mixed>
	 */
	/**
	 * Build a confirmed interpreter result from resolved ambiguities.
	 *
	 * @param string                           $message         Raw message.
	 * @param array<int,array<string,mixed>>   $ambiguities     Ambiguities (with likely values set).
	 * @param array<string,mixed>              $ai_interpretation AI interpretation payload.
	 * @param array<string,mixed>              $base            Optional base result.
	 * @return array<string,mixed>
	 */
	public static function build_confirmed_raw( $message, array $ambiguities, array $ai_interpretation = array(), array $base = array() ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $message );
		$draft  = array(
			'conditions'       => $base['conditions'] ?? array(),
			'condition_match'  => $base['condition_match'] ?? 'all',
			'params'           => $base['params'] ?? array(),
			'portable_rule_set'=> $base['portable_rule_set'] ?? null,
		);
		if ( ! empty( $ai_interpretation['proposal_draft']['rule']['conditions'] ) ) {
			$draft['conditions']      = $ai_interpretation['proposal_draft']['rule']['conditions'];
			$draft['condition_match'] = (string) ( $ai_interpretation['proposal_draft']['rule']['logic'] ?? 'all' );
		}
		$proposal = self::build_proposal_from_likely( $phrase, $ambiguities, $draft );
		$conditions = (array) ( $proposal['rule']['conditions'] ?? array() );
		$rule_match = (string) ( $proposal['rule']['logic'] ?? 'all' );
		$page_ref   = (string) ( $proposal['target']['ref'] ?? 'homepage' );

		return array_merge(
			$base,
			array(
				'intent'                => 'compound_targeting',
				'matched_action'        => 'geocore_create_portable_rule',
				'compound'              => true,
				'confidence'            => 0.9,
				'proposal_ready'        => true,
				'interpretation_status' => RWGA_Interpretation_Status::COMPLETE,
				'conditions'            => $conditions,
				'condition_match'       => $rule_match,
				'portable_rule_set'     => self::portable_rule_set_from_conditions( $conditions, $rule_match ),
				'params'                => array_merge(
					is_array( $base['params'] ?? null ) ? $base['params'] : array(),
					array(
						'source_page_ref' => $page_ref,
						'page_ref'        => $page_ref,
					)
				),
				'summary'               => self::likely_meaning_text( $ambiguities, $proposal ),
				'ambiguities'           => array(),
				'ai_interpretation'     => $ai_interpretation,
			)
		);
	}

	/**
	 * @param array<string,mixed> $row Ambiguity row.
	 * @return array<int,array<string,mixed>>
	 */
	private static function location_alternative_buttons( array $row ) {
		$alts = array();
		$raw  = (string) ( $row['raw'] ?? '' );
		foreach ( (array) ( $row['alternatives'] ?? array() ) as $idx => $value ) {
			$value = (string) $value;
			if ( '' === $value ) {
				continue;
			}
			$is_region = 0 === strpos( $value, 'region:' );
			$alts[]    = array(
				'key'                 => $is_region ? 'use_region_' . substr( $value, 7 ) : 'use_country_' . strtolower( $value ),
				'label'               => $is_region
					? sprintf(
						/* translators: %s: region name */
						__( 'Use %s region targeting', 'reactwoo-geocore' ),
						ucfirst( str_replace( array( '-', '_' ), ' ', substr( $value, 7 ) ) )
					)
					: sprintf(
						/* translators: %s: country code or name */
						__( 'Use %s country targeting', 'reactwoo-geocore' ),
						'GB' === $value ? __( 'United Kingdom', 'reactwoo-geocore' ) : $value
					),
				'confidence'          => max( 0.5, 0.82 - ( 0.18 * $idx ) ),
				'value'               => $value,
				'requires_capability' => $is_region ? 'region_targeting' : '',
			);
		}
		if ( empty( $alts ) && '' !== $raw ) {
			$alts[] = array(
				'key'        => 'use_detected_location',
				'label'      => sprintf(
					/* translators: %s: detected location */
					__( 'Use detected location: %s', 'reactwoo-geocore' ),
					$raw
				),
				'confidence' => 0.6,
				'value'      => (string) ( $row['likely'] ?? '' ),
			);
		}
		return $alts;
	}

	/**
	 * @param array<int,array<string,mixed>> $conditions Conditions.
	 * @param string                         $rule_match all|any.
	 * @return array<string,mixed>
	 */
	public static function portable_rule_set_from_conditions( array $conditions, $rule_match = 'all' ) {
		$portable_conditions = array();
		foreach ( $conditions as $cond ) {
			$portable_conditions[] = array(
				'type'     => (string) ( $cond['type'] ?? '' ),
				'operator' => (string) ( $cond['operator'] ?? 'in' ),
				'value'    => $cond['value'] ?? array(),
			);
		}
		return array(
			'schema_version' => class_exists( 'RWGC_Targeting_Rule_Set_Schema', false )
				? RWGC_Targeting_Rule_Set_Schema::VERSION
				: 2,
			'enabled'        => true,
			'mode'           => 'show_if',
			'match'          => 'all',
			'rules'          => array(
				array(
					'id'         => 'rule_assistant',
					'label'      => '',
					'match'      => 'any' === $rule_match ? 'any' : 'all',
					'conditions' => $portable_conditions,
				),
			),
		);
	}
}
