<?php
/**
 * Per-clause country and weather extraction for variant plan parsing.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts targeting conditions from a single clause segment (not the full phrase).
 */
class RWGA_Segment_Condition_Extractor {

	/**
	 * @param string           $segment  Clause text.
	 * @param array<int,array> $entities Entity rows.
	 * @return array<int,string>
	 */
	public static function extract_countries( $segment, array $entities ) {
		if ( ! class_exists( 'RWGA_Multi_Variant_Interpreter', false ) ) {
			return array();
		}
		return RWGA_Multi_Variant_Interpreter::parse_country_list( (string) $segment, $entities );
	}

	/**
	 * @param string           $segment  Clause text.
	 * @param array<int,array> $entities Entity rows.
	 * @return string|null Entity value e.g. rain, sunny, any.
	 */
	public static function extract_weather( $segment, array $entities ) {
		$text = RWGA_Local_Intent_Interpreter::normalise( (string) $segment );
		if ( '' === $text ) {
			return null;
		}

		if ( preg_match( '/\b(?:all\s+weather(?:\s+conditions)?|any\s+weather|regardless\s+of\s+weather|whatever\s+the\s+weather)\b/i', $text ) ) {
			return 'any';
		}

		$hits = array();
		foreach ( $entities as $row ) {
			if ( ! is_array( $row ) || ( $row['entity_type'] ?? '' ) !== 'weather_condition' ) {
				continue;
			}
			$value = (string) ( $row['value'] ?? $row['entity_key'] ?? '' );
			if ( '' === $value ) {
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
				$alias = RWGA_Local_Intent_Interpreter::normalise( (string) $alias );
				if ( '' === $alias ) {
					continue;
				}
				if ( self::alias_in_text( $text, $alias ) ) {
					$hits[ $value ] = strlen( $alias );
					break;
				}
			}
		}

		if ( empty( $hits ) ) {
			return null;
		}
		arsort( $hits );
		return (string) key( $hits );
	}

	/**
	 * @param string|null $weather_key Weather entity value.
	 * @return array<string,mixed>|null
	 */
	public static function weather_param( $weather_key ) {
		if ( null === $weather_key || '' === $weather_key ) {
			return null;
		}
		if ( 'any' === $weather_key ) {
			return array( 'mode' => 'any' );
		}
		return array( 'condition' => $weather_key );
	}

	/**
	 * @param string|null              $weather_key Weather key.
	 * @param array<int,array>         $entities    Entities.
	 * @return string
	 */
	public static function weather_label( $weather_key, array $entities ) {
		if ( null === $weather_key || '' === $weather_key ) {
			return '';
		}
		if ( 'any' === $weather_key ) {
			return __( 'All weather conditions', 'reactwoo-geocore' );
		}
		foreach ( $entities as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( ( $row['entity_type'] ?? '' ) === 'weather_condition'
				&& (string) ( $row['value'] ?? $row['entity_key'] ?? '' ) === $weather_key ) {
				return (string) ( $row['display_name'] ?? $weather_key );
			}
		}
		return (string) $weather_key;
	}

	/**
	 * @param string $text  Normalised text.
	 * @param string $alias Normalised alias.
	 * @return bool
	 */
	private static function alias_in_text( $text, $alias ) {
		if ( false !== strpos( $alias, ' ' ) ) {
			return false !== strpos( $text, $alias );
		}
		$pattern = '/\b' . preg_quote( $alias, '/' ) . '\b/i';
		return 1 === preg_match( $pattern, $text );
	}
}
