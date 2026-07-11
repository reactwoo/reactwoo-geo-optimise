<?php
/**
 * Resolve country and region mentions without silent UK collapse for England.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Location_Resolver {

	/** @var array<string,array<string,mixed>> */
	private static $region_map = array(
		'england' => array(
			'raw'                       => 'England',
			'resolved'                  => 'England',
			'type'                      => 'region',
			'country_code'              => 'GB',
			'region_code'               => 'GB-ENG',
			'requires_regional_targeting' => true,
			'fallback_country_code'     => 'GB',
		),
		'scotland' => array(
			'raw'                       => 'Scotland',
			'resolved'                  => 'Scotland',
			'type'                      => 'region',
			'country_code'              => 'GB',
			'region_code'               => 'GB-SCT',
			'requires_regional_targeting' => true,
			'fallback_country_code'     => 'GB',
		),
		'wales' => array(
			'raw'                       => 'Wales',
			'resolved'                  => 'Wales',
			'type'                      => 'region',
			'country_code'              => 'GB',
			'region_code'               => 'GB-WLS',
			'requires_regional_targeting' => true,
			'fallback_country_code'     => 'GB',
		),
	);

	/**
	 * @param string           $text     Clause text.
	 * @param array<int,array> $entities Entity rows.
	 * @return array{countries:array,regions:array,warnings:array,labels:array}
	 */
	public static function resolve_from_text( $text, array $entities ) {
		$text     = RWGA_Local_Intent_Interpreter::normalise( $text );
		$warnings = array();
		$labels   = array();
		$regions  = array();
		$countries = array();

		foreach ( self::$region_map as $key => $def ) {
			if ( ! preg_match( '/\b' . preg_quote( $key, '/' ) . '\b/i', $text ) ) {
				continue;
			}
			$region_available = class_exists( 'RWGA_Site_Interpretation_Preferences', false )
				&& RWGA_Site_Interpretation_Preferences::region_targeting_available();
			if ( $region_available ) {
				$regions[] = (string) $def['region_code'];
				$labels[]  = (string) $def['resolved'];
			} else {
				$warnings[] = sprintf(
					/* translators: %s: region name */
					__( '%s requires regional targeting. Geo Core country targeting can only apply United Kingdom unless regional targeting is enabled.', 'reactwoo-geo-ai' ),
					(string) $def['resolved']
				);
				$labels[] = (string) $def['resolved'];
				$regions[] = (string) $def['region_code'];
			}
		}

		$country_codes = class_exists( 'RWGA_Multi_Variant_Interpreter', false )
			? RWGA_Multi_Variant_Interpreter::parse_country_list( $text, $entities )
			: array();

		foreach ( $country_codes as $code ) {
			$code = (string) $code;
			$skip = false;
			foreach ( self::$region_map as $key => $def ) {
				if ( preg_match( '/\b' . preg_quote( $key, '/' ) . '\b/i', $text )
					&& (string) ( $def['fallback_country_code'] ?? '' ) === $code ) {
					$skip = true;
					break;
				}
			}
			if ( ! $skip && ! in_array( $code, $countries, true ) ) {
				$countries[] = $code;
				$labels[]    = self::country_label( $code, $entities );
			}
		}

		if ( preg_match( '/\bgerman\b/i', $text ) && ! in_array( 'DE', $countries, true ) ) {
			$countries[] = 'DE';
			$labels[]    = __( 'Germany', 'reactwoo-geocore' );
		}

		return array(
			'countries' => array_values( array_unique( $countries ) ),
			'regions'   => array_values( array_unique( $regions ) ),
			'warnings'  => $warnings,
			'labels'    => array_values( array_filter( array_unique( $labels ) ) ),
		);
	}

	/**
	 * @param string           $code     ISO code.
	 * @param array<int,array> $entities Entities.
	 * @return string
	 */
	public static function country_label( $code, array $entities ) {
		foreach ( $entities as $row ) {
			if ( is_array( $row ) && (string) ( $row['value'] ?? '' ) === $code ) {
				return (string) ( $row['display_name'] ?? $code );
			}
		}
		return (string) $code;
	}

	/**
	 * @param array{countries:array,regions:array,labels:array} $location Location row.
	 * @return string
	 */
	public static function display_label( array $location ) {
		if ( ! empty( $location['labels'] ) ) {
			return implode( ' + ', (array) $location['labels'] );
		}
		if ( ! empty( $location['regions'] ) ) {
			return implode( ', ', (array) $location['regions'] );
		}
		if ( ! empty( $location['countries'] ) ) {
			return implode( ' + ', (array) $location['countries'] );
		}
		return '';
	}
}
