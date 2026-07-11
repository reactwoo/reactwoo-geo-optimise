<?php
/**
 * Single country include/exclude rules (not multi-variant).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Country_Rule_Interpreter {

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $entities Entity rows.
	 * @return array<string,mixed>
	 */
	public static function parse( $phrase, array $entities ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( '' === $phrase ) {
			return array( 'matched' => false );
		}

		if ( class_exists( 'RWGA_Rule_Plan_Parser', false )
			&& RWGA_Rule_Plan_Parser::has_create_rule_primary_intent( $phrase ) ) {
			return array( 'matched' => false );
		}

		if ( class_exists( 'RWGA_Variant_Group_Extractor', false )
			&& RWGA_Variant_Group_Extractor::is_multi_variant_command( $phrase ) ) {
			return array( 'matched' => false );
		}

		$exclude = (bool) preg_match( '/\b(hide|exclude|block|without|except|not show)\b/i', $phrase );
		$include = (bool) preg_match( '/\b(show|display|target|only|visible)\b/i', $phrase );
		if ( ! $include && ! $exclude ) {
			return array( 'matched' => false );
		}

		$countries = RWGA_Multi_Variant_Interpreter::parse_country_list( $phrase, $entities );
		if ( count( $countries ) < 1 ) {
			return array( 'matched' => false );
		}

		$mode   = $exclude ? 'exclude' : 'include_only';
		$intent = $exclude ? 'country_exclude' : 'country_include';
		$names  = array_map(
			static function ( $code ) use ( $entities ) {
				foreach ( $entities as $row ) {
					if ( is_array( $row ) && ( $row['value'] ?? '' ) === $code ) {
						return (string) ( $row['display_name'] ?? $code );
					}
				}
				return $code;
			},
			$countries
		);

		return array(
			'matched'        => true,
			'intent'         => $intent,
			'matched_action' => 'geocore_create_country_rule',
			'confidence'     => min( 0.95, 0.7 + ( 0.05 * count( $countries ) ) ),
			'params'         => array(
				'mode'      => $mode,
				'countries' => $countries,
			),
			'summary'        => $exclude
				? sprintf(
					/* translators: %s: country names */
					__( 'Hide from %s', 'reactwoo-geocore' ),
					implode( ', ', $names )
				)
				: sprintf(
					/* translators: %s: country names */
					__( 'Show only in %s', 'reactwoo-geocore' ),
					implode( ', ', $names )
				),
		);
	}
}
