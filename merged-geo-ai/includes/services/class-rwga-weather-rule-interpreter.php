<?php
/**
 * Simple weather (+ optional country) targeting rules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Weather_Rule_Interpreter {

	/**
	 * @param string           $phrase   Normalised phrase.
	 * @param array<int,array> $entities Entity rows.
	 * @return array<string,mixed>
	 */
	public static function parse( $phrase, array $entities ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( '' === $phrase ) {
			return array( 'matched' => false );
		}

		if ( class_exists( 'RWGA_Variant_Group_Extractor', false )
			&& RWGA_Variant_Group_Extractor::is_multi_variant_command( $phrase ) ) {
			return array( 'matched' => false );
		}

		if ( ! class_exists( 'RWGA_Segment_Condition_Extractor', false ) ) {
			return array( 'matched' => false );
		}

		$weather = RWGA_Segment_Condition_Extractor::extract_weather( $phrase, $entities );
		if ( null === $weather ) {
			return array( 'matched' => false );
		}

		$has_show = (bool) preg_match( '/\b(show|display|target|use|offer)\b/i', $phrase );
		if ( ! $has_show && ! preg_match( '/\bwhen\s+it\b/i', $phrase ) ) {
			return array( 'matched' => false );
		}

		$countries = RWGA_Segment_Condition_Extractor::extract_countries( $phrase, $entities );
		$weather_param = RWGA_Segment_Condition_Extractor::weather_param( $weather );
		$weather_label = RWGA_Segment_Condition_Extractor::weather_label( $weather, $entities );

		$params = array(
			'weather' => $weather_param,
		);
		if ( ! empty( $countries ) ) {
			$params['countries'] = $countries;
		}

		$country_part = ! empty( $countries )
			? implode( ', ', $countries )
			: '';

		return array(
			'matched'        => true,
			'intent'         => 'weather_rule',
			'matched_action' => 'geocore_create_weather_rule',
			'confidence'     => 0.82,
			'params'         => $params,
			'summary'        => $country_part
				? sprintf(
					/* translators: 1: weather label, 2: countries */
					__( 'Show when %1$s in %2$s', 'reactwoo-geocore' ),
					strtolower( $weather_label ),
					$country_part
				)
				: sprintf(
					/* translators: %s: weather label */
					__( 'Show when %s', 'reactwoo-geocore' ),
					strtolower( $weather_label )
				),
			'proposal_ready' => true,
		);
	}
}
