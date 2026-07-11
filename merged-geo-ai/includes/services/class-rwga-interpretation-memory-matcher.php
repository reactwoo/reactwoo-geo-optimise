<?php
/**
 * Match phrases against local and shared interpretation memory.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Interpretation_Memory_Matcher {

	const HIGH_CONFIDENCE = 0.85;

	/**
	 * @param string              $message  Raw message.
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $context  Context.
	 * @return array<string,mixed>
	 */
	public static function match( $message, $phrase, array $entities, array $context = array() ) {
		$trace = array(
			'attempted' => true,
			'matched'   => false,
			'source'    => '',
			'confidence'=> 0,
		);

		if ( ! RWGA_Interpretation_Memory_Store::is_local_enabled() && ! RWGA_Interpretation_Memory_Store::is_shared_enabled() ) {
			$trace['reason'] = 'memory_disabled';
			return array(
				'matched' => false,
				'trace'   => $trace,
			);
		}

		$shape_payload = RWGA_Phrase_Shape_Normaliser::build( $message, $entities );
		$phrase_shape  = (string) ( $shape_payload['phrase_shape'] ?? $phrase );
		$entity_map    = is_array( $shape_payload['entity_map'] ?? null ) ? $shape_payload['entity_map'] : array();

		$local = self::match_local( $phrase, $phrase_shape, $entity_map, $entities );
		if ( ! empty( $local['matched'] ) ) {
			$local['trace'] = array_merge(
				$trace,
				array(
					'matched'    => true,
					'source'     => 'local',
					'memory_id'  => (string) ( $local['memory_id'] ?? '' ),
					'confidence' => (float) ( $local['confidence'] ?? 0 ),
					'phrase_shape' => $phrase_shape,
				)
			);
			return $local;
		}

		$remote = self::match_remote( $message, $phrase, $phrase_shape, $entity_map, $context, $entities );
		if ( ! empty( $remote['matched'] ) ) {
			RWGA_Interpretation_Memory_Store::cache_remote(
				array_merge(
					$remote,
					array(
						'phrase_shape'      => $phrase_shape,
						'normalised_phrase' => $phrase,
					)
				)
			);
			$remote['trace'] = array_merge(
				$trace,
				array(
					'matched'      => true,
					'source'       => (string) ( $remote['scope'] ?? 'global' ),
					'memory_id'    => (string) ( $remote['memory_id'] ?? '' ),
					'confidence'   => (float) ( $remote['confidence'] ?? 0 ),
					'phrase_shape' => $phrase_shape,
				)
			);
			return $remote;
		}

		$trace['reason']       = 'no_memory_match';
		$trace['phrase_shape'] = $phrase_shape;
		return array(
			'matched' => false,
			'trace'   => $trace,
		);
	}

	/**
	 * @param string              $phrase       Normalised phrase.
	 * @param string              $phrase_shape Phrase shape.
	 * @param array<string,mixed> $entity_map   Entity map.
	 * @param array<int,array>    $entities     Entities.
	 * @return array<string,mixed>
	 */
	private static function match_local( $phrase, $phrase_shape, array $entity_map, array $entities ) {
		$row = RWGA_Interpretation_Memory_Store::find_by_normalised( $phrase );
		if ( ! $row ) {
			$row = RWGA_Interpretation_Memory_Store::find_by_shape( $phrase_shape );
		}
		if ( ! $row ) {
			return array( 'matched' => false );
		}
		return self::build_result_from_memory_row( $row, $entity_map, $entities, 'local' );
	}

	/**
	 * @param string              $message      Raw message.
	 * @param string              $phrase       Normalised phrase.
	 * @param string              $phrase_shape Phrase shape.
	 * @param array<string,mixed> $entity_map   Entity map.
	 * @param array<string,mixed> $context      Context.
	 * @param array<int,array>    $entities     Entities.
	 * @return array<string,mixed>
	 */
	private static function match_remote( $message, $phrase, $phrase_shape, array $entity_map, array $context, array $entities ) {
		if ( ! RWGA_Interpretation_Memory_Store::is_shared_enabled() ) {
			return array( 'matched' => false );
		}
		$hashes = RWGA_Interpretation_Memory_Client::hashes();
		$body   = RWGA_Interpretation_Memory_Client::match(
			array(
				'suite'             => 'geocore',
				'message'           => (string) $message,
				'normalised_phrase' => (string) $phrase,
				'phrase_shape'      => (string) $phrase_shape,
				'entity_map'        => $entity_map,
				'context'           => $context,
				'detected_entities' => array(),
				'site_hash'         => $hashes['site_hash'],
				'license_hash'      => $hashes['license_hash'],
				'context_type'      => (string) ( $context['target_type'] ?? '' ),
			)
		);
		if ( ! is_array( $body ) || empty( $body['matched'] ) || empty( $body['interpretation'] ) ) {
			return array( 'matched' => false );
		}
		$confidence = (float) ( $body['confidence'] ?? 0 );
		if ( $confidence < self::HIGH_CONFIDENCE ) {
			return array( 'matched' => false );
		}
		$interpretation = $body['interpretation'];
		if ( ! self::validate_interpretation( $interpretation, $entities ) ) {
			return array( 'matched' => false );
		}
		return array(
			'matched'        => true,
			'memory_id'      => (string) ( $body['memory_id'] ?? '' ),
			'scope'          => (string) ( $body['scope'] ?? 'global' ),
			'confidence'     => $confidence,
			'intent'         => (string) ( $interpretation['intent'] ?? '' ),
			'matched_action' => (string) ( $interpretation['matched_action'] ?? '' ),
			'params'         => is_array( $interpretation['params'] ?? null ) ? $interpretation['params'] : array(),
			'summary'        => (string) ( $interpretation['summary'] ?? '' ),
			'source_layer'   => 'interpretation_memory',
		);
	}

	/**
	 * @param array<string,mixed> $row        Memory row.
	 * @param array<string,mixed> $entity_map Entity map.
	 * @param array<int,array>    $entities   Entities.
	 * @param string              $source     Source label.
	 * @return array<string,mixed>
	 */
	private static function build_result_from_memory_row( array $row, array $entity_map, array $entities, $source ) {
		$confidence = (float) ( $row['confidence'] ?? 0.85 );
		if ( $confidence < self::HIGH_CONFIDENCE ) {
			return array( 'matched' => false );
		}
		$template = is_array( $row['params_template'] ?? null ) ? $row['params_template'] : array();
		if ( empty( $template ) && is_array( $row['resolved_params_example'] ?? null ) ) {
			$template = $row['resolved_params_example'];
		}
		$params = RWGA_Phrase_Shape_Normaliser::apply_params_template( $template, $entity_map );
		$interpretation = array(
			'intent'          => (string) ( $row['intent_key'] ?? '' ),
			'matched_action'  => (string) ( $row['action_key'] ?? '' ),
			'params'          => $params,
			'summary'         => self::summary_for( (string) ( $row['intent_key'] ?? '' ), $params ),
		);
		if ( ! self::validate_interpretation( $interpretation, $entities ) ) {
			return array( 'matched' => false );
		}
		return array(
			'matched'        => true,
			'memory_id'      => (string) ( $row['memory_id'] ?? $row['id'] ?? '' ),
			'scope'          => (string) ( $row['scope'] ?? $source ),
			'confidence'     => $confidence,
			'intent'         => $interpretation['intent'],
			'matched_action' => $interpretation['matched_action'],
			'params'         => $interpretation['params'],
			'summary'        => $interpretation['summary'],
			'source_layer'   => 'interpretation_memory',
		);
	}

	/**
	 * @param array<string,mixed> $interpretation Interpretation.
	 * @param array<int,array>    $entities       Entities.
	 * @return bool
	 */
	public static function validate_interpretation( array $interpretation, array $entities ) {
		unset( $entities );
		$intent = (string) ( $interpretation['intent'] ?? '' );
		$action = (string) ( $interpretation['matched_action'] ?? '' );
		if ( '' === $intent || '' === $action ) {
			return false;
		}
		if ( ! preg_match( '/^geocore_/', $action ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string              $intent Intent.
	 * @param array<string,mixed> $params Params.
	 * @return string
	 */
	private static function summary_for( $intent, array $params ) {
		if ( 'create_geo_variant_plan' === $intent ) {
			return __( 'I found a homepage targeting plan from learned interpretation memory.', 'reactwoo-geo-ai' );
		}
		if ( 'country_include' === $intent && ! empty( $params['countries'] ) ) {
			return sprintf(
				/* translators: %s: countries */
				__( 'Learned country targeting rule (%s).', 'reactwoo-geo-ai' ),
				is_array( $params['countries'] ) ? implode( ', ', $params['countries'] ) : (string) $params['countries']
			);
		}
		return __( 'Learned interpretation.', 'reactwoo-geo-ai' );
	}

	/**
	 * Store accepted interpretation in local memory.
	 *
	 * @param string              $message Raw message.
	 * @param array<string,mixed> $result  Interpretation result.
	 * @param array<int,array>    $entities Entities.
	 * @return void
	 */
	public static function remember( $message, array $result, array $entities = array() ) {
		if ( empty( $result['intent'] ) || empty( $result['matched_action'] ) ) {
			return;
		}
		if ( empty( $result['params'] ) && empty( $result['portable_rule_set'] ) ) {
			return;
		}
		$shape = RWGA_Phrase_Shape_Normaliser::build( $message, $entities );
		$source = (string) ( $result['interpretation_source'] ?? 'user_correction' );
		if ( 'ai_fallback' === $source ) {
			$source = 'ai_fallback';
		} elseif ( in_array( $source, array( 'local_memory', 'remote_memory', 'interpretation_memory' ), true ) ) {
			$source = 'promotion';
		} else {
			$source = 'user_correction';
		}
		$learned_rules = class_exists( 'RWGA_Parser_Hints_Service', false )
			? RWGA_Parser_Hints_Service::extract_learned_rules( $message, $result )
			: array();
		$existing = RWGA_Interpretation_Memory_Store::find_by_shape( (string) ( $shape['phrase_shape'] ?? '' ) );
		$success  = (int) ( is_array( $existing ) ? ( $existing['success_count'] ?? 0 ) : 0 );
		$resolutions = array();
		if ( ! empty( $result['ambiguities'] ) && is_array( $result['ambiguities'] ) ) {
			foreach ( $result['ambiguities'] as $row ) {
				$field = (string) ( $row['field'] ?? '' );
				if ( '' !== $field && ! empty( $row['likely'] ) ) {
					$resolutions[ $field ] = (string) $row['likely'];
				}
			}
		}
		RWGA_Interpretation_Memory_Store::upsert(
			array(
				'raw_phrase'              => (string) $message,
				'normalised_phrase'       => (string) ( $shape['normalised_phrase'] ?? '' ),
				'phrase_shape'            => (string) ( $shape['phrase_shape'] ?? '' ),
				'intent_key'              => (string) ( $result['intent'] ?? '' ),
				'action_key'              => (string) ( $result['matched_action'] ?? '' ),
				'params_template'         => RWGA_Phrase_Shape_Normaliser::build_params_template(
					is_array( $result['params'] ) ? $result['params'] : array(),
					is_array( $shape['entity_map'] ) ? $shape['entity_map'] : array()
				),
				'resolved_params_example' => is_array( $result['params'] ) ? $result['params'] : array(),
				'detected_entities'       => $shape['entity_map'] ?? array(),
				'learned_rules'           => $learned_rules,
				'confidence'              => (float) ( $result['confidence'] ?? 0.88 ),
				'source'                  => $source,
				'success_count'           => $success + 1,
				'status'                  => 'active',
				'scope'                   => 'site',
				'ambiguity_resolutions'   => $resolutions,
				'ai_interpretation'       => is_array( $result['ai_interpretation'] ?? null ) ? $result['ai_interpretation'] : array(),
			)
		);
		if ( class_exists( 'RWGA_Parser_Hints_Service', false ) && ! empty( $learned_rules ) ) {
			RWGA_Parser_Hints_Service::merge_hints( $learned_rules );
		}
	}
}
