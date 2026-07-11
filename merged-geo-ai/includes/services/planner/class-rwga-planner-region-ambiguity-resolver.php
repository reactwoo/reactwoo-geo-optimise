<?php
/**
 * Detect "country vs region" ambiguity for nation names that map to both a
 * country and a sub-region (England → United Kingdom country or GB-ENG region).
 *
 * We deliberately do NOT make these ambiguous by default — existing variant
 * commands ("display in England") still resolve straight to the region. The
 * ambiguity is only surfaced when the user explicitly asks for clarification
 * (e.g. "if England is unclear, ask me whether I mean United Kingdom country
 * targeting or England region targeting"). The resolved decision is then
 * applied by the executor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Region_Ambiguity_Resolver {

	/**
	 * Region code => human nation label. Each maps to GB country targeting.
	 *
	 * @var array<string,string>
	 */
	const NATION_REGIONS = array(
		'GB-ENG' => 'England',
		'GB-SCT' => 'Scotland',
		'GB-WLS' => 'Wales',
		'GB-NIR' => 'Northern Ireland',
	);

	/**
	 * Does the command explicitly ask to clarify country vs region targeting?
	 *
	 * @param string $phrase Full normalised phrase.
	 * @return bool
	 */
	public static function wants_clarification( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( (string) $phrase );
		if ( '' === $phrase ) {
			return false;
		}

		// "ask ... whether ... country ... region" / "X is unclear ... country ... region".
		$mentions_both = preg_match( '/\bcountry\b/i', $phrase ) && preg_match( '/\bregion\b/i', $phrase );
		if ( ! $mentions_both ) {
			return false;
		}

		return (bool) preg_match( '/\b(?:unclear|ambiguous|not\s+sure|ask\s+me|clarify|confirm|whether\s+i\s+mean|which\s+one)\b/i', $phrase );
	}

	/**
	 * Build an unresolved-location row for an ambiguous nation region.
	 *
	 * @param string $region Region code (e.g. GB-ENG).
	 * @return array<string,mixed>|null
	 */
	public static function ambiguity_for_region( $region ) {
		$region = strtoupper( trim( (string) $region ) );
		if ( ! isset( self::NATION_REGIONS[ $region ] ) ) {
			return null;
		}
		$nation  = self::NATION_REGIONS[ $region ];
		$country = self::country_for_region( $region );

		return array(
			'raw'         => $nation,
			'status'      => 'needs_resolution',
			'warning'     => sprintf(
				/* translators: 1: nation name e.g. England. */
				__( '%1$s could mean country targeting or region targeting. Choose how to apply it.', 'reactwoo-geocore' ),
				$nation
			),
			'candidates'  => array(
				array(
					'key'   => 'country_' . strtolower( $country ),
					'label' => sprintf(
						/* translators: 1: country name. */
						__( 'Use %1$s country targeting', 'reactwoo-geocore' ),
						self::country_label( $country )
					),
					'value' => array( 'type' => 'country', 'code' => $country ),
				),
				array(
					'key'   => 'region_' . strtolower( str_replace( '-', '_', $region ) ),
					'label' => sprintf(
						/* translators: 1: nation name. */
						__( 'Use %1$s region targeting', 'reactwoo-geocore' ),
						$nation
					),
					'value' => array( 'type' => 'region', 'code' => $region ),
				),
				array(
					'key'   => 'remove',
					'label' => __( 'Remove location condition', 'reactwoo-geocore' ),
				),
			),
		);
	}

	/**
	 * @param string $region Region code.
	 * @return string
	 */
	public static function country_for_region( $region ) {
		$region = strtoupper( (string) $region );
		$parts  = explode( '-', $region );
		return $parts[0] ?? 'GB';
	}

	/**
	 * @param string $code Country code.
	 * @return string
	 */
	private static function country_label( $code ) {
		$map = array( 'GB' => 'United Kingdom' );
		return $map[ strtoupper( (string) $code ) ] ?? strtoupper( (string) $code );
	}
}
