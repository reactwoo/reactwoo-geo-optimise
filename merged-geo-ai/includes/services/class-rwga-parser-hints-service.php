<?php
/**
 * Memory-derived parser hints (clause boundaries, verbs, aliases).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Parser_Hints_Service {

	const OPTION_CACHE = 'geo_ai_parser_hints_cache';

	/**
	 * @return array<string,mixed>
	 */
	public static function get_hints() {
		$cache = get_option( self::OPTION_CACHE, array() );
		if ( ! is_array( $cache ) ) {
			return self::defaults();
		}
		return array_merge( self::defaults(), $cache );
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function defaults() {
		return array(
			'clause_boundaries' => array(),
			'targeting_verbs'   => array(),
			'weather_aliases'   => array(),
			'source_markers'    => array(),
			'variant_markers'   => array(),
			'version'           => 1,
			'updated'           => 0,
		);
	}

	/**
	 * Extract reusable rules from a confirmed interpretation.
	 *
	 * @param string              $message Raw phrase.
	 * @param array<string,mixed> $result  Interpretation result.
	 * @return array<string,mixed>
	 */
	public static function extract_learned_rules( $message, array $result ) {
		$phrase = class_exists( 'RWGA_Local_Intent_Interpreter', false )
			? RWGA_Local_Intent_Interpreter::normalise( $message )
			: strtolower( trim( (string) $message ) );

		$rules = array(
			'clause_boundaries' => array(),
			'targeting_verbs'   => array(),
			'weather_aliases'   => array(),
			'source_markers'    => array(),
			'variant_markers'   => array(),
		);

		$boundary_patterns = array(
			'/\bone\s+should\b/i'      => 'one should',
			'/\bthe\s+other\s+can\b/i' => 'the other can',
			'/\bthe\s+other\s+should\b/i' => 'the other should',
			'/\bone\s+for\b/i'         => 'one for',
			'/\bupdate\s+homepage\b/i' => 'update homepage',
			'/\bupdate\s+original\b/i' => 'update original',
			'/\bkeep\s+the\s+original\b/i' => 'keep the original',
		);
		foreach ( $boundary_patterns as $pattern => $label ) {
			if ( preg_match( $pattern, $phrase ) ) {
				$rules['clause_boundaries'][] = $label;
			}
		}

		foreach ( array( 'fire', 'trigger', 'activate', 'render', 'serve' ) as $verb ) {
			if ( false !== strpos( $phrase, $verb ) ) {
				$rules['targeting_verbs'][] = $verb;
			}
		}

		$weather_map = array(
			'when its raining'       => 'rain',
			"when it's raining"      => 'rain',
			'when it is sunny'       => 'sunny',
			"when it's sunny"        => 'sunny',
			'all weather conditions' => 'any',
			'all weather'            => 'any',
		);
		foreach ( $weather_map as $alias => $value ) {
			if ( false !== strpos( $phrase, $alias ) ) {
				$rules['weather_aliases'][ $alias ] = $value;
			}
		}

		if ( preg_match( '/\bupdate\s+(?:the\s+)?(?:homepage|original)\b/i', $phrase ) ) {
			$rules['source_markers'][] = 'update homepage';
		}
		if ( preg_match( '/\bone\s+should\b/i', $phrase ) ) {
			$rules['variant_markers'][] = 'one should';
		}
		if ( preg_match( '/\bthe\s+other\s+can\b/i', $phrase ) ) {
			$rules['variant_markers'][] = 'the other can';
		}

		$debug = is_array( $result['_debug_entities'] ?? null ) ? $result['_debug_entities'] : array();
		if ( ! empty( $debug['segments'] ) && is_array( $debug['segments'] ) ) {
			foreach ( $debug['segments'] as $segment ) {
				if ( ! is_array( $segment ) ) {
					continue;
				}
				$marker = (string) ( $segment['marker'] ?? '' );
				if ( '' !== $marker ) {
					$rules['clause_boundaries'][] = $marker;
				}
			}
		}

		foreach ( $rules as $key => $values ) {
			if ( is_array( $values ) ) {
				$rules[ $key ] = array_values( array_unique( $values ) );
			}
		}

		return $rules;
	}

	/**
	 * @param array<string,mixed> $rules Learned rules.
	 * @return void
	 */
	public static function merge_hints( array $rules ) {
		$current = self::get_hints();
		foreach ( array( 'clause_boundaries', 'targeting_verbs', 'source_markers', 'variant_markers' ) as $key ) {
			$incoming = isset( $rules[ $key ] ) && is_array( $rules[ $key ] ) ? $rules[ $key ] : array();
			$current[ $key ] = array_values(
				array_unique(
					array_merge(
						is_array( $current[ $key ] ?? null ) ? $current[ $key ] : array(),
						$incoming
					)
				)
			);
		}
		if ( ! empty( $rules['weather_aliases'] ) && is_array( $rules['weather_aliases'] ) ) {
			$current['weather_aliases'] = array_merge(
				is_array( $current['weather_aliases'] ?? null ) ? $current['weather_aliases'] : array(),
				$rules['weather_aliases']
			);
		}
		$current['updated'] = time();
		update_option( self::OPTION_CACHE, $current, false );
	}

	/**
	 * Extra clause boundary regex rows for variant plan parser.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function dynamic_boundary_patterns() {
		$hints = self::get_hints();
		$rows  = array();
		foreach ( (array) ( $hints['clause_boundaries'] ?? array() ) as $boundary ) {
			$boundary = trim( (string) $boundary );
			if ( '' === $boundary ) {
				continue;
			}
			$quoted = preg_quote( $boundary, '/' );
			$rows[] = array(
				'pattern'  => '/(?<!\band\s)(' . $quoted . ')\b/i',
				'capture'  => 1,
				'type'     => false !== strpos( $boundary, 'update' ) || false !== strpos( $boundary, 'original' ) || false !== strpos( $boundary, 'keep' )
					? 'source'
					: 'variant',
				'marker'   => $boundary,
				'ordinal'  => 0,
				'priority' => 48,
			);
		}
		return $rows;
	}
}
