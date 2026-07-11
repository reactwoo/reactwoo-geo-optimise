<?php
/**
 * Formal interpretation status and execute gating for Geo Assistant.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Interpretation_Status {

	const COMPLETE             = 'complete';
	const PARTIAL              = 'partial';
	const AMBIGUOUS            = 'ambiguous';
	const UNSUPPORTED          = 'unsupported';
	const NEEDS_AI             = 'needs_ai';
	const NEEDS_CLARIFICATION  = 'needs_clarification';
	const NEEDS_CONFIRMATION   = 'needs_confirmation';
	const NEEDS_RESOLUTION     = 'needs_resolution';
	const FAILED               = 'failed';

	/**
	 * @param array<string,mixed> $result Interpreter or assistant raw result.
	 * @return array<string,mixed>
	 */
	public static function from_result( array $result ) {
		$source     = self::normalise_source( (string) ( $result['interpretation_source'] ?? '' ) );
		$confidence = (float) ( $result['confidence'] ?? 0 );
		$status     = (string) ( $result['interpretation_status'] ?? '' );
		if ( '' === $status ) {
			$status = self::infer_status( $result, $confidence );
		}
		if ( ! empty( $result['requires_resolution'] ) ) {
			$status = self::NEEDS_RESOLUTION;
		}

		$can_execute = self::COMPLETE === $status
			&& ! empty( $result['matched_action'] )
			&& ( ! isset( $result['proposal_ready'] ) || false !== $result['proposal_ready'] )
			&& empty( $result['missing_information'] )
			&& empty( $result['ambiguities'] )
			&& empty( $result['requires_resolution'] )
			&& empty( $result['invalid_interpretation'] );

		$trace = is_array( $result['_interpretation_trace'] ?? null ) ? $result['_interpretation_trace'] : array();
		$shape = '';
		if ( class_exists( 'RWGA_Phrase_Shape_Normaliser', false ) ) {
			$entities = array();
			if ( class_exists( 'RWGA_Intelligence_Sync_Service', false ) ) {
				$bundle   = RWGA_Intelligence_Sync_Service::ensure_bundle();
				$entities = is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
			}
			$raw = (string) ( $result['normalised_phrase'] ?? '' );
			if ( '' !== $raw ) {
				$built = RWGA_Phrase_Shape_Normaliser::build( $raw, $entities );
				$shape = (string) ( $built['phrase_shape'] ?? '' );
			}
		}

		return array(
			'status'        => $status,
			'source'        => $source,
			'confidence'    => round( $confidence, 2 ),
			'can_execute' => $can_execute,
			'clarification' => self::clarification_payload( $result, $status ),
			'memory'        => array(
				'matched'      => in_array( $source, array( 'local_memory', 'remote_memory' ), true ),
				'memory_id'    => (string) ( $result['memory_id'] ?? '' ),
				'phrase_shape' => $shape,
			),
			'learning'      => array(
				'should_store'        => in_array( $source, array( 'ai_fallback', 'clarification' ), true ) && self::COMPLETE === $status,
				'promotion_candidate' => class_exists( 'RWGA_Learning_Promotion_Service', false )
					? RWGA_Learning_Promotion_Service::is_promotion_candidate( $shape )
					: false,
			),
			'trace_summary' => array(
				'local_parser' => $trace['local_parser'] ?? array(),
				'memory'       => $trace['interpretation_memory'] ?? array(),
				'ai_fallback'  => $trace['ai_fallback'] ?? array(),
			),
		);
	}

	/**
	 * @param array<string,mixed> $result     Result.
	 * @param float               $confidence Confidence.
	 * @return string
	 */
	public static function infer_status( array $result, $confidence ) {
		if ( ! empty( $result['ambiguities'] ) || self::NEEDS_CONFIRMATION === ( $result['interpretation_status'] ?? '' ) ) {
			return self::NEEDS_CONFIRMATION;
		}
		if ( empty( $result['intent'] ) && empty( $result['matched_action'] ) && empty( $result['missing_information'] ) ) {
			return self::FAILED;
		}
		if ( ! empty( $result['missing_information'] ) ) {
			$key = is_array( $result['missing_information'][0] ?? null )
				? (string) ( $result['missing_information'][0]['key'] ?? '' )
				: '';
			if ( 'source_usage' === $key ) {
				return self::AMBIGUOUS;
			}
			if ( isset( $result['proposal_ready'] ) && false === $result['proposal_ready'] ) {
				return self::NEEDS_CLARIFICATION;
			}
			return self::PARTIAL;
		}
		if ( isset( $result['proposal_ready'] ) && false === $result['proposal_ready'] ) {
			return self::NEEDS_CLARIFICATION;
		}
		if ( ! empty( $result['escalate'] ) && $confidence < 0.85 ) {
			return self::NEEDS_AI;
		}
		if ( $confidence < 0.85 && ! empty( $result['matched_action'] ) ) {
			return self::PARTIAL;
		}
		if ( ! empty( $result['matched_action'] ) || ! empty( $result['steps'] ) ) {
			return self::COMPLETE;
		}
		return self::PARTIAL;
	}

	/**
	 * @param string $source Raw source key.
	 * @return string
	 */
	public static function normalise_source( $source ) {
		$map = array(
			'local_parser'          => 'local_parser',
			'pattern_bundle'        => 'pattern_bundle',
			'interpretation_memory' => 'local_memory',
			'local_memory'          => 'local_memory',
			'remote_memory'         => 'remote_memory',
			'ai_fallback'           => 'ai_fallback',
			'clarification'         => 'clarification',
			'parser_hints'          => 'local_parser',
		);
		return isset( $map[ $source ] ) ? $map[ $source ] : $source;
	}

	/**
	 * @param array<string,mixed> $result Result.
	 * @param string              $status Status.
	 * @return array<string,mixed>
	 */
	private static function clarification_payload( array $result, $status ) {
		if ( self::NEEDS_CONFIRMATION === $status ) {
			$ai_available = (bool) apply_filters( 'rwga_interpretation_ai_fallback_enabled', false );
			$options      = array(
				array(
					'key'   => 'accept_likely_interpretation',
					'label' => __( 'Use this interpretation', 'reactwoo-geocore' ),
				),
				array(
					'key'   => 'edit_ambiguities',
					'label' => __( 'Choose location/audience', 'reactwoo-geocore' ),
				),
			);
			if ( $ai_available ) {
				$options[] = array(
					'key'   => 'ask_ai_again',
					'label' => __( 'Ask AI again', 'reactwoo-geocore' ),
				);
			}
			$options[] = array(
				'key'   => 'cancel',
				'label' => __( 'Cancel', 'reactwoo-geocore' ),
			);
			return array(
				'question' => __( 'Is this interpretation correct?', 'reactwoo-geo-ai' ),
				'options'  => $options,
			);
		}

		if ( ! in_array( $status, array( self::PARTIAL, self::AMBIGUOUS, self::NEEDS_CLARIFICATION, self::NEEDS_AI ), true ) ) {
			return array(
				'question' => '',
				'options'  => array(),
			);
		}

		$missing  = isset( $result['missing_information'] ) && is_array( $result['missing_information'] )
			? $result['missing_information']
			: array();
		$question = ! empty( $missing[0]['question'] )
			? (string) $missing[0]['question']
			: __( 'I found the page, countries, and weather conditions, but I need to confirm how to split them.', 'reactwoo-geo-ai' );

		$inferred     = self::has_inferred_plan( $result );
		$ai_available = (bool) apply_filters( 'rwga_interpretation_ai_fallback_enabled', false );

		if ( self::NEEDS_CLARIFICATION === $status && $inferred ) {
			$options = array(
				array(
					'key'   => 'accept_inferred_split',
					'label' => __( 'Yes, use this split', 'reactwoo-geocore' ),
				),
				array(
					'key'   => 'edit_split',
					'label' => __( 'Edit split', 'reactwoo-geocore' ),
				),
			);
			if ( $ai_available ) {
				$options[] = array(
					'key'   => 'ask_ai',
					'label' => __( 'Ask AI to check', 'reactwoo-geocore' ),
				);
			}
			$options[] = array(
				'key'   => 'cancel',
				'label' => __( 'Cancel', 'reactwoo-geocore' ),
			);
			return array(
				'question' => __( 'Is this split correct?', 'reactwoo-geo-ai' ),
				'options'  => $options,
			);
		}

		if ( self::NEEDS_CLARIFICATION === $status ) {
			$options = array(
				array(
					'key'   => 'choose_split',
					'label' => __( 'Choose split', 'reactwoo-geocore' ),
				),
			);
			if ( $ai_available ) {
				$options[] = array(
					'key'   => 'ask_ai',
					'label' => __( 'Ask AI', 'reactwoo-geocore' ),
				);
			}
			$options[] = array(
				'key'   => 'edit_manually',
				'label' => __( 'Edit manually', 'reactwoo-geocore' ),
			);
			$options[] = array(
				'key'   => 'cancel',
				'label' => __( 'Cancel', 'reactwoo-geocore' ),
			);
			return array(
				'question' => $question,
				'options'  => $options,
			);
		}

		if ( self::PARTIAL === $status ) {
			$options = array();
			if ( $ai_available ) {
				$options[] = array(
					'key'   => 'ask_ai',
					'label' => __( 'Ask AI', 'reactwoo-geocore' ),
				);
			}
			$options[] = array(
				'key'   => 'edit_manually',
				'label' => __( 'Edit manually', 'reactwoo-geocore' ),
			);
			$options[] = array(
				'key'   => 'cancel',
				'label' => __( 'Cancel', 'reactwoo-geocore' ),
			);
			return array(
				'question' => $question,
				'options'  => $options,
			);
		}

		$legacy = isset( $result['suggested_options'] ) && is_array( $result['suggested_options'] )
			? array_values( $result['suggested_options'] )
			: array();
		$options = array();
		foreach ( $legacy as $idx => $label ) {
			$options[] = array(
				'key'   => 'option_' . ( $idx + 1 ),
				'label' => (string) $label,
			);
		}
		return array(
			'question' => $question,
			'options'  => $options,
		);
	}

	/**
	 * @param array<string,mixed> $result Interpreter result.
	 * @return bool
	 */
	public static function has_inferred_plan( array $result ) {
		if ( ! empty( $result['inferred_plan'] ) && is_array( $result['inferred_plan'] ) ) {
			return true;
		}
		if ( ! class_exists( 'RWGA_Inferred_Plan_Builder', false ) ) {
			return false;
		}
		$entities = array();
		if ( class_exists( 'RWGA_Intelligence_Sync_Service', false ) ) {
			$bundle   = RWGA_Intelligence_Sync_Service::ensure_bundle();
			$entities = is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
		}
		return null !== RWGA_Inferred_Plan_Builder::from_interpreter_result( $result, $entities );
	}
}
