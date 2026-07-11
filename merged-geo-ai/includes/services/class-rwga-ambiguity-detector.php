<?php
/**
 * Detect ambiguous targeting language without silently mapping meanings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Ambiguity_Detector {

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $draft    Optional parser draft.
	 * @param array<string,mixed> $context  Context.
	 * @return array<int,array<string,mixed>>
	 */
	public static function detect( $phrase, array $entities, array $draft = array(), array $context = array() ) {
		unset( $context );
		$phrase      = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		$ambiguities = array();

		foreach ( self::location_definitions() as $key => $def ) {
			$hit = self::find_term_in_phrase( $phrase, (array) ( $def['patterns'] ?? array() ) );
			if ( '' === $hit ) {
				continue;
			}
			$likely = self::suggest_likely_location( $key, $def, $phrase );
			$ambiguities[] = array(
				'field'        => 'location',
				'raw'          => $hit,
				'term_key'     => $key,
				'likely'       => $likely,
				'alternatives' => self::location_alternatives( $key, $def ),
				'question'     => (string) ( $def['question'] ?? __( 'How should this location be targeted?', 'reactwoo-geo-ai' ) ),
				'notes'        => self::location_notes( $key, $phrase ),
			);
		}

		if ( preg_match( '/\b(?:audiences?|audience)\s+(?:matches?|match|is|are)\s+any\b/i', $phrase )
			|| preg_match( '/\bmatches?\s+any\s+audiences?\b/i', $phrase ) ) {
			$ambiguities[] = array(
				'field'        => 'audience',
				'raw'          => 'audiences matches any',
				'term_key'     => 'audience_any',
				'likely'       => 'any_audience',
				'alternatives' => array( 'any_audience', 'selected_audience_groups' ),
				'question'     => __( 'Should this match any audience, or only selected audience groups?', 'reactwoo-geo-ai' ),
				'notes'        => array(
					__( '“Audiences matches any” may mean any audience, or any selected audience group.', 'reactwoo-geo-ai' ),
				),
			);
		}

		if ( preg_match( '/\bcounty\s+([a-z]+)\b/i', $phrase, $m ) ) {
			$term = strtolower( (string) $m[1] );
			if ( ! self::has_ambiguity_field( $ambiguities, 'location', $term ) ) {
				$ambiguities[] = array(
					'field'        => 'location',
					'raw'          => 'county ' . $term,
					'term_key'     => 'county_typo_' . $term,
					'likely'       => in_array( $term, array( 'england', 'scotland', 'wales' ), true ) ? 'GB' : '',
					'alternatives' => array( 'GB', 'region:' . $term ),
					'question'     => __( 'Did you mean country targeting rather than county?', 'reactwoo-geo-ai' ),
					'notes'        => array(
						__( '“County” may be a typo for “country”.', 'reactwoo-geo-ai' ),
					),
				);
			}
		}

		$ambiguities = self::dedupe_ambiguities( $ambiguities );
		/**
		 * @param array<int,array<string,mixed>> $ambiguities Detected ambiguities.
		 * @param string                         $phrase      Normalised phrase.
		 * @param array<string,mixed>            $draft       Parser draft.
		 */
		return (array) apply_filters( 'rwga_detected_ambiguities', $ambiguities, $phrase, $draft );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function location_definitions() {
		$region_available = class_exists( 'RWGA_Site_Interpretation_Preferences', false )
			&& RWGA_Site_Interpretation_Preferences::region_targeting_available();

		$defs = array(
			'england' => array(
				'patterns' => array( 'england' ),
				'question' => __( 'Do you want United Kingdom country targeting or England region targeting?', 'reactwoo-geo-ai' ),
				'country'  => 'GB',
				'region'   => 'region:england',
			),
			'scotland' => array(
				'patterns' => array( 'scotland' ),
				'question' => __( 'Do you want United Kingdom country targeting or Scotland region targeting?', 'reactwoo-geo-ai' ),
				'country'  => 'GB',
				'region'   => 'region:scotland',
			),
			'wales' => array(
				'patterns' => array( 'wales' ),
				'question' => __( 'Do you want United Kingdom country targeting or Wales region targeting?', 'reactwoo-geo-ai' ),
				'country'  => 'GB',
				'region'   => 'region:wales',
			),
			'northern_ireland' => array(
				'patterns' => array( 'northern ireland' ),
				'question' => __( 'Do you want United Kingdom country targeting or Northern Ireland region targeting?', 'reactwoo-geo-ai' ),
				'country'  => 'GB',
				'region'   => 'region:northern_ireland',
			),
			'georgia' => array(
				'patterns' => array( 'georgia' ),
				'question' => __( 'Do you want Georgia the country, or Georgia the US state?', 'reactwoo-geo-ai' ),
				'country'  => 'GE',
				'region'   => 'region:us-ga',
			),
		);

		if ( ! $region_available ) {
			foreach ( $defs as $key => $def ) {
				unset( $defs[ $key ]['region'] );
			}
		}

		return $defs;
	}

	/**
	 * @param string              $key  Term key.
	 * @param array<string,mixed> $def  Definition.
	 * @param string              $phrase Phrase.
	 * @return string
	 */
	private static function suggest_likely_location( $key, array $def, $phrase ) {
		$policy = class_exists( 'RWGA_Site_Interpretation_Preferences', false )
			? RWGA_Site_Interpretation_Preferences::location_policy( $key )
			: 'ask';

		if ( 'prefer_country' === $policy && ! empty( $def['country'] ) ) {
			return (string) $def['country'];
		}
		if ( 'prefer_region' === $policy && ! empty( $def['region'] ) ) {
			return (string) $def['region'];
		}
		if ( preg_match( '/\bcounty\s+' . preg_quote( str_replace( '_', ' ', $key ), '/' ) . '\b/i', $phrase ) ) {
			return (string) ( $def['country'] ?? '' );
		}
		return (string) ( $def['country'] ?? '' );
	}

	/**
	 * @param string              $key Term key.
	 * @param array<string,mixed> $def Definition.
	 * @return array<int,string>
	 */
	private static function location_alternatives( $key, array $def ) {
		unset( $key );
		$alts = array();
		if ( ! empty( $def['country'] ) ) {
			$alts[] = (string) $def['country'];
		}
		if ( ! empty( $def['region'] ) ) {
			$alts[] = (string) $def['region'];
		}
		return array_values( array_unique( $alts ) );
	}

	/**
	 * @param string $key    Term key.
	 * @param string $phrase Phrase.
	 * @return array<int,string>
	 */
	private static function location_notes( $key, $phrase ) {
		$notes = array();
		if ( preg_match( '/\bcounty\b/i', $phrase ) && false !== strpos( $phrase, str_replace( '_', ' ', $key ) ) ) {
			$notes[] = __( '“County” may be a typo for “country”.', 'reactwoo-geo-ai' );
		}
		if ( in_array( $key, array( 'england', 'scotland', 'wales', 'northern_ireland' ), true ) ) {
			$notes[] = __( 'Geo Core country targeting normally uses United Kingdom unless region targeting is enabled.', 'reactwoo-geo-ai' );
		}
		if ( 'georgia' === $key ) {
			$notes[] = __( '“Georgia” can mean the country or the US state.', 'reactwoo-geo-ai' );
		}
		return $notes;
	}

	/**
	 * @param array<int,string> $patterns Patterns.
	 * @param string            $phrase   Phrase.
	 * @return string
	 */
	private static function find_term_in_phrase( $phrase, array $patterns ) {
		foreach ( $patterns as $pattern ) {
			$pattern = RWGA_Local_Intent_Interpreter::normalise( (string) $pattern );
			if ( '' === $pattern ) {
				continue;
			}
			if ( preg_match( '/\b' . preg_quote( $pattern, '/' ) . '\b/i', $phrase ) ) {
				return $pattern;
			}
		}
		return '';
	}

	/**
	 * @param array<int,array<string,mixed>> $ambiguities Ambiguities.
	 * @param string                         $field       Field.
	 * @param string                         $raw         Raw token.
	 * @return bool
	 */
	private static function has_ambiguity_field( array $ambiguities, $field, $raw ) {
		foreach ( $ambiguities as $row ) {
			if ( $field === ( $row['field'] ?? '' ) && false !== strpos( (string) ( $row['raw'] ?? '' ), $raw ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<int,array<string,mixed>> $ambiguities Ambiguities.
	 * @return array<int,array<string,mixed>>
	 */
	private static function dedupe_ambiguities( array $ambiguities ) {
		$seen = array();
		$out  = array();
		foreach ( $ambiguities as $row ) {
			$key = (string) ( $row['field'] ?? '' ) . ':' . (string) ( $row['term_key'] ?? $row['raw'] ?? '' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $row;
		}
		return $out;
	}
}
