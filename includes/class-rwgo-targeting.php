<?php
/**
 * Experiment targeting (Geo Core when available).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Targeting evaluation.
 */
class RWGO_Targeting {

	/**
	 * @param array<string, mixed> $targeting From experiment config.
	 * @return bool Whether the current visitor matches.
	 */
	public static function passes( $targeting ) {
		if ( ! is_array( $targeting ) ) {
			return true;
		}
		$mode = isset( $targeting['mode'] ) ? (string) $targeting['mode'] : 'everyone';
		if ( 'everyone' === $mode || '' === $mode ) {
			return true;
		}
		if ( 'countries' === $mode ) {
			$codes = isset( $targeting['countries'] ) && is_array( $targeting['countries'] ) ? $targeting['countries'] : array();
			$codes = array_map( 'strtoupper', array_map( 'strval', $codes ) );
			$cc    = self::current_country_code();
			if ( '' === $cc ) {
				return false;
			}
			return in_array( strtoupper( $cc ), $codes, true );
		}
		if ( 'weather_facets' === $mode ) {
			return self::passes_weather_facets( $targeting );
		}
		if ( 'saved_rule' === $mode ) {
			return self::passes_saved_rule( $targeting );
		}
		/**
		 * Filters whether targeting passes for custom modes.
		 *
		 * @param bool  $pass   Default false for unknown modes.
		 * @param array $targeting Targeting config.
		 */
		return (bool) apply_filters( 'rwgo_targeting_passes', false, $targeting );
	}

	/**
	 * @return string ISO2 or empty.
	 */
	private static function current_country_code() {
		if ( function_exists( 'rwgc_get_visitor_country' ) ) {
			$c = rwgc_get_visitor_country();
			return is_string( $c ) ? strtoupper( trim( $c ) ) : '';
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $targeting Targeting config.
	 * @return bool
	 */
	private static function passes_saved_rule( $targeting ) {
		if ( ! is_array( $targeting ) ) {
			return false;
		}
		$set = class_exists( 'RWGO_Test_Rule_Adapter', false )
			? RWGO_Test_Rule_Adapter::get_evaluation_rule_set( $targeting )
			: null;
		if ( null === $set ) {
			return false;
		}
		if ( ! class_exists( 'RWGC_Rule_Evaluator', false ) || ! class_exists( 'RWGC_Context_Resolver', false ) ) {
			return false;
		}
		$snapshot = RWGC_Context_Resolver::resolve_current();
		return RWGC_Rule_Evaluator::matches( $set, $snapshot );
	}

	/**
	 * @param array<string, mixed> $targeting Targeting config.
	 * @return bool
	 */
	private static function passes_weather_facets( $targeting ) {
		if ( ! class_exists( 'RWGCM_Weather_Affinity', false ) ) {
			return false;
		}
		$required = isset( $targeting['weather_facets'] ) && is_array( $targeting['weather_facets'] )
			? RWGCM_Weather_Affinity::sanitize_facet_list( $targeting['weather_facets'] )
			: array();
		if ( empty( $required ) ) {
			return false;
		}
		$visitor = RWGCM_Weather_Affinity::get_visitor_facets();
		if ( empty( $visitor ) ) {
			return false;
		}
		$match_mode = isset( $targeting['weather_match'] ) ? sanitize_key( (string) $targeting['weather_match'] ) : 'any';
		$overlap    = array_intersect( $required, $visitor );
		if ( 'all' === $match_mode ) {
			return count( $overlap ) === count( $required );
		}
		return ! empty( $overlap );
	}
}
