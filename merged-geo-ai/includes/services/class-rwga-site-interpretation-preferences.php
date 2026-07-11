<?php
/**
 * Site-level interpretation preferences for ambiguous terms (default: ask).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Site_Interpretation_Preferences {

	const OPTION_KEY = 'rwga_interpretation_location_preferences';

	/**
	 * @param string $term Normalised ambiguous term key e.g. england, georgia.
	 * @return string ask|prefer_country|prefer_region
	 */
	public static function location_policy( $term ) {
		$term = sanitize_key( (string) $term );
		$all  = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$policy = isset( $all[ $term ] ) ? (string) $all[ $term ] : 'ask';
		/**
		 * @param string $policy ask|prefer_country|prefer_region
		 * @param string $term   Ambiguous term key.
		 */
		return (string) apply_filters( 'rwga_interpretation_location_policy', $policy, $term );
	}

	/**
	 * Whether region targeting is available on this site.
	 *
	 * @return bool
	 */
	public static function region_targeting_available() {
		return (bool) apply_filters( 'rwgc_region_targeting_enabled', false );
	}

	/**
	 * Whether audience targeting is available.
	 *
	 * @return bool
	 */
	public static function audience_targeting_available() {
		if ( class_exists( 'RWGC_Capability_Registry', false ) ) {
			$status = RWGC_Capability_Registry::get_status( 'variant_type_audience' );
			return ! empty( $status['available'] );
		}
		return (bool) apply_filters( 'rwgc_pro_enabled', false );
	}
}
