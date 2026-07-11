<?php
/**
 * Extract grouped variant targeting from natural-language phrases.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Variant_Group_Extractor {

	/** @var string[] */
	const MULTI_VARIANT_TERMS = array(
		'duplicate',
		'variant',
		'variants',
		'version',
		'versions',
		'twice',
		'two times',
		'another',
		'the other',
		'one for',
		'one version',
		'separate versions',
		'country variants',
	);

	/**
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	public static function is_multi_variant_command( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( '' === $phrase ) {
			return false;
		}
		foreach ( self::MULTI_VARIANT_TERMS as $term ) {
			if ( false !== strpos( $phrase, $term ) ) {
				return true;
			}
		}
		return (bool) preg_match( '/\b(create|make)\b.*\b(two|2|three|3|four|4)\b.*\b(variants?|versions?)\b/i', $phrase );
	}

	/**
	 * @param string           $phrase   Normalised phrase.
	 * @param array<int,array> $entities Entity rows.
	 * @return array<string,mixed>
	 */
	public static function extract( $phrase, array $entities ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		$groups = self::split_variant_groups( $phrase, $entities );
		$count  = self::detect_variant_count( $phrase, count( $groups ) );

		return array(
			'variant_count'   => $count,
			'variant_groups'  => $groups,
			'matched_terms'   => self::matched_terms( $phrase ),
		);
	}

	/**
	 * @param string           $phrase   Phrase.
	 * @param array<int,array> $entities Entities.
	 * @return array<int,array{raw:string,countries:array<int,string>,mode:string,label:string}>
	 */
	public static function split_variant_groups( $phrase, array $entities ) {
		$regex_pairs = array(
			'/with a version for (.+?) only and a version which works in both (.+?)$/i',
			'/with a version for (.+?) only and a version for (.+?)$/i',
			'/with a version for (.+?) only and another(?: version)? for (.+?)$/i',
			'/with one version for (.+?) only and another for (.+?)$/i',
			'/one will display in (.+?) only the other in (.+?)$/i',
			'/one for (.+?) and (?:one|another)(?: version)? for (.+?)$/i',
			'/one version for (.+?) and another(?: version)? for (.+?)$/i',
			'/make one version for (.+?) and another for (.+?)$/i',
			'/one in (.+?) and (?:one|another) in (.+?)$/i',
		);

		foreach ( $regex_pairs as $regex ) {
			if ( preg_match( $regex, $phrase, $m ) ) {
				$g1 = self::group_from_segment( trim( $m[1] ), $entities );
				$g2 = self::group_from_segment( trim( $m[2] ), $entities );
				if ( ! empty( $g1['countries'] ) && ! empty( $g2['countries'] ) ) {
					return array( $g1, $g2 );
				}
			}
		}

		$parts = preg_split(
			'/\s+(?:and\s+)?(?:a\s+)?version(?:\s+which|\s+that|\s+for|\s+works)?\s+/i',
			$phrase
		);
		if ( is_array( $parts ) && count( $parts ) >= 3 ) {
			$groups = array();
			foreach ( array_slice( $parts, 1 ) as $part ) {
				$group = self::group_from_segment( trim( (string) $part ), $entities );
				if ( ! empty( $group['countries'] ) ) {
					$groups[] = $group;
				}
			}
			if ( count( $groups ) >= 2 ) {
				return $groups;
			}
		}

		return array();
	}

	/**
	 * Variant groups for a plan (excludes original/source segment).
	 *
	 * @param string                    $phrase   Normalised phrase.
	 * @param array<int,array>          $entities Entity rows.
	 * @param array<string,mixed>|null  $source   Extracted source targeting.
	 * @return array<int,array{raw:string,countries:array<int,string>,mode:string,label:string}>
	 */
	public static function extract_plan_variant_groups( $phrase, array $entities, $source = null ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		$remaining = $phrase;

		if ( is_array( $source ) && ! empty( $source['raw'] ) ) {
			$remaining = str_replace( (string) $source['raw'], ' ', $remaining );
		}

		$remaining = preg_replace(
			'/^(?:.*?\b)?(?:duplicate|copy|clone)\s+(?:the\s+)?[\w\s-]+?\s+(?:twice|two times|three times)\b/i',
			' ',
			$remaining
		);
		$remaining = trim( preg_replace( '/\s+/', ' ', (string) $remaining ) );

		$pair_patterns = array(
			'/(?:and\s+)?create\s+one version for (.+?) and another for (.+?)$/i',
			'/create one version for (.+?) and another for (.+?)$/i',
			'/one version for (.+?) and another(?: version)? for (.+?)$/i',
			'/one for (.+?) and another for (.+?)$/i',
		);
		foreach ( $pair_patterns as $pattern ) {
			if ( preg_match( $pattern, $remaining, $m ) ) {
				$g1 = self::group_from_segment( trim( $m[1] ), $entities );
				$g2 = self::group_from_segment( trim( $m[2] ), $entities );
				if ( ! empty( $g1['countries'] ) && ! empty( $g2['countries'] ) ) {
					return array( $g1, $g2 );
				}
			}
		}

		$groups = array();
		$regex  = '/(?:\band\s+)?(?:the\s+)?(?:(?:one|\d+(?:st|nd|rd|th)|first|second|third|another)\s+version\s+(?:will\s+show\s+in|should\s+show\s+in|is\s+for|for|show\s+in)?\s*(?:both\s+)?.+?)(?=\s+and\s+(?:the\s+)?(?:(?:one|\d+(?:st|nd|rd|th)|another)\s+version)|$)/i';
		if ( preg_match_all( $regex, $remaining, $matches ) && ! empty( $matches[0] ) ) {
			foreach ( $matches[0] as $seg ) {
				$group = self::group_from_segment( trim( (string) $seg ), $entities );
				if ( ! empty( $group['countries'] ) ) {
					$groups[] = $group;
				}
			}
			if ( count( $groups ) >= 1 ) {
				return $groups;
			}
		}

		return array();
	}

	/**
	 * @param array<int,string> $codes    Country codes.
	 * @param array<int,array>  $entities Entities.
	 * @return string
	 */
	public static function label_for_countries( array $codes, array $entities ) {
		return self::label_for_group( $codes, $entities );
	}

	/**
	 * @param string           $segment  Raw segment text.
	 * @param array<int,array> $entities Entities.
	 * @return array{raw:string,countries:array<int,string>,mode:string,label:string}
	 */
	public static function group_from_segment( $segment, array $entities ) {
		$raw      = $segment;
		$segment  = RWGA_Local_Intent_Interpreter::normalise( $segment );
		$mode     = ( false !== strpos( $segment, 'only' ) || false !== strpos( $segment, 'both' ) )
			? 'include_only'
			: 'include_only';
		$countries = RWGA_Multi_Variant_Interpreter::parse_country_list( $segment, $entities );
		$label     = self::label_for_group( $countries, $entities );

		return array(
			'raw'       => $raw,
			'countries' => $countries,
			'mode'      => $mode,
			'label'     => $label,
		);
	}

	/**
	 * @param string $phrase       Phrase.
	 * @param int    $group_count  Extracted group count.
	 * @return int
	 */
	public static function detect_variant_count( $phrase, $group_count ) {
		if ( $group_count >= 2 ) {
			return $group_count;
		}
		if ( preg_match( '/\b(twice|two times)\b/i', $phrase ) ) {
			return 2;
		}
		if ( preg_match( '/\b(three times|thrice)\b/i', $phrase ) ) {
			return 3;
		}
		if ( preg_match( '/\b(two|2)\b/i', $phrase ) ) {
			return 2;
		}
		if ( preg_match( '/\b(three|3)\b/i', $phrase ) ) {
			return 3;
		}
		return max( 1, $group_count );
	}

	/**
	 * @param array<int,string> $codes    Country codes.
	 * @param array<int,array>  $entities Entities.
	 * @return string
	 */
	private static function label_for_group( array $codes, array $entities ) {
		if ( empty( $codes ) ) {
			return '';
		}
		$names = array();
		foreach ( $codes as $code ) {
			$names[] = self::country_display_name( $code, $entities );
		}
		if ( 1 === count( $names ) ) {
			return $names[0] . ' only';
		}
		return implode( ' + ', $names );
	}

	/**
	 * @param string           $code     ISO code.
	 * @param array<int,array> $entities Entities.
	 * @return string
	 */
	private static function country_display_name( $code, array $entities ) {
		foreach ( $entities as $row ) {
			if ( ! is_array( $row ) || ( $row['entity_type'] ?? '' ) !== 'country' ) {
				continue;
			}
			$val = (string) ( $row['value'] ?? $row['entity_key'] ?? '' );
			if ( $val === $code ) {
				return (string) ( $row['display_name'] ?? $code );
			}
		}
		return $code;
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<int,string>
	 */
	private static function matched_terms( $phrase ) {
		$found = array();
		foreach ( self::MULTI_VARIANT_TERMS as $term ) {
			if ( false !== strpos( $phrase, $term ) ) {
				$found[] = $term;
			}
		}
		if ( preg_match( '/\bhomepage\b|\bhome page\b/i', $phrase ) ) {
			$found[] = 'homepage';
		}
		return $found;
	}

	/**
	 * Ambiguous: duplicate + multiple countries without clear grouping.
	 *
	 * @param string           $phrase   Phrase.
	 * @param array<int,array> $entities Entities.
	 * @return bool
	 */
	public static function is_ambiguous_grouping( $phrase, array $entities ) {
		if ( ! preg_match( '/\bduplicate\b/i', $phrase ) ) {
			return false;
		}
		$groups = self::split_variant_groups( $phrase, $entities );
		if ( count( $groups ) >= 2 ) {
			return false;
		}
		$countries = RWGA_Multi_Variant_Interpreter::parse_country_list( $phrase, $entities );
		return count( $countries ) >= 3;
	}
}
