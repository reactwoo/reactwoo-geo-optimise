<?php
/**
 * Gate interpretations that contain ambiguous language behind memory/AI + user confirmation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Ambiguity_Gate {

	/**
	 * @param array<string,mixed> $result     Interpreter result.
	 * @param string              $raw_phrase Raw phrase.
	 * @param string              $phrase     Normalised phrase.
	 * @param array<int,array>    $entities   Entities.
	 * @param array<string,mixed> $context    Context.
	 * @param array<string,mixed> $trace      Trace (by ref).
	 * @return array<string,mixed>
	 */
	public static function apply( array $result, $raw_phrase, $phrase, array $entities, array $context, array &$trace ) {
		if ( self::should_skip( $result ) ) {
			return $result;
		}

		$draft       = self::draft_from_result( $result );
		$ambiguities = RWGA_Ambiguity_Detector::detect( $phrase, $entities, $draft, $context );
		if ( empty( $ambiguities ) ) {
			return $result;
		}

		$trace['ambiguity_gate'] = array(
			'detected' => count( $ambiguities ),
			'fields'   => array_values( array_unique( array_map( static function ( $row ) {
				return (string) ( $row['field'] ?? '' );
			}, $ambiguities ) ) ),
		);

		$memory_hint = self::memory_resolutions( $raw_phrase, $phrase, $entities, $context );
		if ( ! empty( $memory_hint['ambiguity_resolutions'] ) ) {
			$ambiguities = self::apply_resolutions( $ambiguities, $memory_hint['ambiguity_resolutions'] );
			$trace['ambiguity_gate']['memory_resolutions'] = $memory_hint['ambiguity_resolutions'];
		}

		$source_label = 'local_intelligence';
		$ai_interp    = null;
		if ( ! empty( $memory_hint['ai_interpretation'] ) && is_array( $memory_hint['ai_interpretation'] ) ) {
			$ai_interp    = $memory_hint['ai_interpretation'];
			$source_label = 'local_memory';
		} else {
			$ai = self::try_ai( $raw_phrase, $phrase, $entities, $context, $ambiguities, $draft, $trace );
			if ( is_array( $ai ) ) {
				$ai_interp    = RWGA_AI_Interpretation_Builder::normalize_external( $ai );
				$source_label = 'ai_fallback';
				if ( ! empty( $ai['interpretation_source'] ) ) {
					$result['interpretation_source'] = (string) $ai['interpretation_source'];
				}
			}
		}

		if ( ! is_array( $ai_interp ) ) {
			$ai_interp = RWGA_AI_Interpretation_Builder::build( $phrase, $ambiguities, $draft, $entities, $context, $source_label );
		}

		$merged = self::merge_ai_into_result( $result, $ambiguities, $ai_interp, $phrase, $draft );
		$merged['proposal_ready']        = false;
		$merged['interpretation_status'] = RWGA_Interpretation_Status::NEEDS_CONFIRMATION;
		$merged['summary']               = self::summary_message( $ambiguities, $ai_interp );
		if ( empty( $merged['interpretation_source'] ) ) {
			$merged['interpretation_source'] = 'local_parser';
		}
		if ( 'ai_fallback' === $source_label ) {
			$merged['interpretation_source'] = 'ai_fallback';
		} elseif ( 'local_memory' === $source_label || 'remote_memory' === $source_label ) {
			$merged['interpretation_source'] = $source_label;
		}

		return $merged;
	}

	/**
	 * @param array<string,mixed> $result Result.
	 * @return bool
	 */
	private static function should_skip( array $result ) {
		if ( ! empty( $result['ambiguities'] ) && ! empty( $result['ai_interpretation'] ) ) {
			return true;
		}
		$status = (string) ( $result['interpretation_status'] ?? '' );
		return RWGA_Interpretation_Status::NEEDS_CLARIFICATION === $status && ! empty( $result['inferred_plan'] );
	}

	/**
	 * @param array<string,mixed> $result Result.
	 * @return array<string,mixed>
	 */
	private static function draft_from_result( array $result ) {
		return array(
			'conditions'       => $result['conditions'] ?? array(),
			'condition_match'  => $result['condition_match'] ?? 'all',
			'params'           => $result['params'] ?? array(),
			'portable_rule_set'=> $result['portable_rule_set'] ?? null,
		);
	}

	/**
	 * @param string              $raw_phrase Raw phrase.
	 * @param string              $phrase     Normalised phrase.
	 * @param array<int,array>    $entities   Entities.
	 * @param array<string,mixed> $context    Context.
	 * @return array<string,mixed>
	 */
	private static function memory_resolutions( $raw_phrase, $phrase, array $entities, array $context ) {
		if ( ! class_exists( 'RWGA_Interpretation_Memory_Matcher', false ) ) {
			return array();
		}
		$match = RWGA_Interpretation_Memory_Matcher::match( $raw_phrase, $phrase, $entities, $context );
		if ( empty( $match['matched'] ) ) {
			return array();
		}
		$row = array();
		if ( class_exists( 'RWGA_Interpretation_Memory_Store', false ) ) {
			$shape = RWGA_Phrase_Shape_Normaliser::build( $raw_phrase, $entities );
			$row   = RWGA_Interpretation_Memory_Store::find_by_shape( (string) ( $shape['phrase_shape'] ?? '' ) );
			if ( ! $row ) {
				$row = RWGA_Interpretation_Memory_Store::find_by_normalised( $phrase );
			}
		}
		if ( ! is_array( $row ) ) {
			return array();
		}
		return array(
			'ambiguity_resolutions' => is_array( $row['ambiguity_resolutions'] ?? null ) ? $row['ambiguity_resolutions'] : array(),
			'ai_interpretation'     => is_array( $row['ai_interpretation'] ?? null ) ? $row['ai_interpretation'] : array(),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $ambiguities Ambiguities.
	 * @param array<string,mixed>            $resolutions Resolutions keyed by field.
	 * @return array<int,array<string,mixed>>
	 */
	private static function apply_resolutions( array $ambiguities, array $resolutions ) {
		foreach ( $ambiguities as $idx => $row ) {
			$field = (string) ( $row['field'] ?? '' );
			if ( isset( $resolutions[ $field ] ) ) {
				$ambiguities[ $idx ]['likely'] = (string) $resolutions[ $field ];
				$ambiguities[ $idx ]['from_memory'] = true;
			}
		}
		return $ambiguities;
	}

	/**
	 * @param string                           $raw_phrase   Raw phrase.
	 * @param string                           $phrase       Normalised phrase.
	 * @param array<int,array>                 $entities     Entities.
	 * @param array<string,mixed>              $context      Context.
	 * @param array<int,array<string,mixed>>   $ambiguities  Ambiguities.
	 * @param array<string,mixed>              $draft        Draft.
	 * @param array<string,mixed>              $trace        Trace.
	 * @return array<string,mixed>|null
	 */
	private static function try_ai( $raw_phrase, $phrase, array $entities, array $context, array $ambiguities, array $draft, array &$trace ) {
		if ( ! (bool) apply_filters( 'rwga_interpretation_ai_fallback_enabled', false ) ) {
			$trace['ai_fallback']['reason'] = 'not_enabled';
			return null;
		}
		$trace['ai_fallback']['called'] = true;
		$trace['ai_fallback']['reason'] = 'ambiguous_phrase';
		$result = apply_filters(
			'rwga_interpretation_ai_fallback',
			null,
			$raw_phrase,
			$phrase,
			array_merge(
				$context,
				array(
					'ambiguities' => $ambiguities,
					'draft'       => $draft,
				)
			),
			$entities,
			array(
				'ambiguities' => $ambiguities,
				'draft'       => $draft,
			)
		);
		if ( ! is_array( $result ) ) {
			$trace['ai_fallback']['matched'] = false;
			return null;
		}
		$trace['ai_fallback']['matched'] = true;
		$result['interpretation_source'] = 'ai_fallback';
		return $result;
	}

	/**
	 * @param array<string,mixed>              $result      Result.
	 * @param array<int,array<string,mixed>>   $ambiguities Ambiguities.
	 * @param array<string,mixed>              $ai_interp   AI interpretation.
	 * @param string                           $phrase      Phrase.
	 * @param array<string,mixed>              $draft       Draft.
	 * @return array<string,mixed>
	 */
	private static function merge_ai_into_result( array $result, array $ambiguities, array $ai_interp, $phrase, array $draft ) {
		unset( $phrase );
		$result['ambiguities']       = $ambiguities;
		$result['ai_interpretation'] = $ai_interp;

		if ( empty( $result['matched_action'] ) && ! empty( $ai_interp['proposal_draft'] ) ) {
			$result['intent']         = 'compound_targeting';
			$result['matched_action'] = 'geocore_create_portable_rule';
			$result['compound']       = true;
		}

		if ( ! empty( $ai_interp['proposal_draft']['rule']['conditions'] ) ) {
			$result['conditions']        = $ai_interp['proposal_draft']['rule']['conditions'];
			$result['condition_match']   = (string) ( $ai_interp['proposal_draft']['rule']['logic'] ?? 'all' );
			$result['portable_rule_set'] = RWGA_AI_Interpretation_Builder::portable_rule_set_from_conditions(
				$result['conditions'],
				$result['condition_match']
			);
		} elseif ( ! empty( $draft['portable_rule_set'] ) ) {
			$result['portable_rule_set'] = $draft['portable_rule_set'];
		}

		return $result;
	}

	/**
	 * @param array<int,array<string,mixed>> $ambiguities Ambiguities.
	 * @param array<string,mixed>            $ai_interp   AI interpretation.
	 * @return string
	 */
	private static function summary_message( array $ambiguities, array $ai_interp ) {
		$count = count( $ambiguities );
		if ( $count > 1 ) {
			return __( 'I found a rule request, but some parts need confirmation before applying anything.', 'reactwoo-geo-ai' );
		}
		if ( ! empty( $ai_interp['likely_meaning'] ) ) {
			return __( 'I found a likely interpretation, but need confirmation before applying it.', 'reactwoo-geo-ai' );
		}
		return __( 'I found a likely interpretation, but need you to confirm it before creating anything.', 'reactwoo-geo-ai' );
	}

	/**
	 * Build a standalone ambiguous response when parser did not fully match.
	 *
	 * @param string              $raw_phrase Raw phrase.
	 * @param string              $phrase     Normalised phrase.
	 * @param array<int,array>    $entities   Entities.
	 * @param array<string,mixed> $context    Context.
	 * @param array<string,mixed> $trace      Trace (by ref).
	 * @return array<string,mixed>|null
	 */
	public static function build_standalone( $raw_phrase, $phrase, array $entities, array $context, array &$trace ) {
		$ambiguities = RWGA_Ambiguity_Detector::detect( $phrase, $entities, array(), $context );
		if ( empty( $ambiguities ) ) {
			return null;
		}
		$seed = array(
			'intent'              => 'compound_targeting',
			'matched_action'      => 'geocore_create_portable_rule',
			'confidence'          => 0.62,
			'normalised_phrase'   => $phrase,
			'interpretation_source' => 'local_parser',
		);
		return self::apply( $seed, $raw_phrase, $phrase, $entities, $context, $trace );
	}
}
