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
}
