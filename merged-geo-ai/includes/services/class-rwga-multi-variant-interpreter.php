<?php
/**
 * Parse multi-variant creation phrases (country-specific page versions).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Multi_Variant_Interpreter {

	/**
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $context  Resolved context.
	 * @return array<string,mixed>
	 */
	public static function parse( $phrase, array $entities, array $context = array() ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( '' === $phrase || ! self::is_multi_variant_command( $phrase ) ) {
			return array( 'matched' => false );
		}

		if ( class_exists( 'RWGA_Original_Source_Targeting_Extractor', false )
			&& RWGA_Original_Source_Targeting_Extractor::has_original_marker( $phrase ) ) {
			return array( 'matched' => false );
		}

		if ( class_exists( 'RWGA_Variant_Group_Extractor', false )
			&& RWGA_Variant_Group_Extractor::is_ambiguous_grouping( $phrase, $entities ) ) {
			return array(
				'matched'             => true,
				'intent'              => 'create_geo_variants',
				'matched_action'      => 'geocore_create_variants_with_country_rules',
				'confidence'          => 0.62,
				'missing_information' => array(
					array(
						'key'      => 'variant_grouping',
						'question' => __( 'Do you want one shared variant for all listed countries, or separate variants for each country?', 'reactwoo-geocore' ),
					),
				),
				'suggested_options'   => array(
					__( 'One shared variant', 'reactwoo-geocore' ),
					__( 'Separate variants per country', 'reactwoo-geocore' ),
				),
				'params'              => array(
					'countries' => self::parse_country_list( $phrase, $entities ),
				),
				'summary'             => __( 'I need to know how to group these countries into variants.', 'reactwoo-geocore' ),
			);
		}

		$extracted = class_exists( 'RWGA_Variant_Group_Extractor', false )
			? RWGA_Variant_Group_Extractor::extract( $phrase, $entities )
			: array( 'variant_groups' => array(), 'variant_count' => 0 );

		$groups = $extracted['variant_groups'] ?? array();
		if ( count( $groups ) < 2 ) {
			$groups = self::legacy_groups( $phrase, $entities );
		}
		if ( count( $groups ) < 1 ) {
			return array(
				'matched'  => false,
				'_debug'   => array(
					'reason'                  => 'multi_variant_terms_detected_but_groups_not_extracted',
					'countries_detected'      => self::parse_country_list( $phrase, $entities ),
					'variant_terms_detected'    => $extracted['matched_terms'] ?? array(),
				),
			);
		}

		$page_ref   = class_exists( 'RWGA_Page_Reference_Resolver', false )
			? RWGA_Page_Reference_Resolver::detect( $phrase )
			: null;
		$page_value = $page_ref ? (string) ( $page_ref['value'] ?? 'homepage' ) : self::extract_page_token( $phrase );
		$variants   = array();
		$steps      = array();

		foreach ( $groups as $idx => $group ) {
			$countries = $group['countries'] ?? array();
			$label     = (string) ( $group['label'] ?? self::default_group_label( $countries, $entities ) );
			$variants[] = array(
				'label'     => $label,
				'mode'      => $group['mode'] ?? 'include_only',
				'countries' => $countries,
			);
			$steps[] = array(
				'label'  => sprintf(
					/* translators: 1: variant number, 2: country group label */
					__( 'Variant %1$d: %2$s', 'reactwoo-geocore' ),
					$idx + 1,
					$label
				),
				'action' => 'geocore_create_variant',
				'params' => array(
					'source_page_ref' => $page_value,
					'countries'       => $countries,
					'mode'            => 'include_only',
				),
			);
		}

		$count = (int) ( $extracted['variant_count'] ?? count( $variants ) );

		return array(
			'matched'        => true,
			'intent'         => 'create_geo_variants',
			'matched_action' => 'geocore_create_variants_with_country_rules',
			'confidence'     => min( 0.98, 0.78 + ( 0.06 * count( $variants ) ) ),
			'page_ref'       => $page_ref,
			'variant_groups' => $groups,
			'variant_count'  => $count,
			'matched_terms'  => $extracted['matched_terms'] ?? array(),
			'params'         => array(
				'source_page_ref' => $page_value,
				'page_ref'        => $page_value,
				'variant_count'   => $count,
				'variants'        => $variants,
			),
			'steps'          => $steps,
			'summary'        => self::build_summary( $page_value, $variants ),
		);
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	public static function is_multi_variant_command( $phrase ) {
		return class_exists( 'RWGA_Variant_Group_Extractor', false )
			? RWGA_Variant_Group_Extractor::is_multi_variant_command( $phrase )
			: (bool) preg_match( '/\bvariants?\b|\bversions?\b|\bduplicate\b|\btwice\b/i', $phrase );
	}

	/**
	 * @param string           $phrase   Phrase.
	 * @param array<int,array> $entities Entities.
	 * @return array<int,array{raw:string,countries:array,mode:string,label:string}>
	 */
	private static function legacy_groups( $phrase, array $entities ) {
		if ( ! class_exists( 'RWGA_Variant_Group_Extractor', false ) ) {
			return array();
		}
		return RWGA_Variant_Group_Extractor::split_variant_groups( $phrase, $entities );
	}

	/**
	 * @param string           $segment  Text segment.
	 * @param array<int,array> $entities Entity rows.
	 * @return array<int,string>
	 */
	public static function parse_country_list( $segment, array $entities ) {
		$segment = RWGA_Local_Intent_Interpreter::normalise( $segment );

		$segment = preg_replace( '/\b(?:1st|2nd|3rd|\d+(?:st|nd|rd|th)|first|second|third)\s+version\b/i', ' ', $segment );
		$segment = preg_replace( '/\b(?:both|either|together|also)\b/i', ' ', $segment );
		$segment = preg_replace( '/\b(?:version|variant|works|which|that|will|would|should|the|a|an)\b/i', ' ', $segment );
		$segment = preg_replace( '/\b(?:for|display|show|in|only)\b/i', ' ', $segment );
		$segment = trim( preg_replace( '/\s+/', ' ', (string) $segment ) );

		$parts = preg_split( '/\s*(?:,|\/|&|\*|\+|\bplus\b|\bas well as\b|\band\b|\bor\b)\s*/i', $segment );
		if ( ! is_array( $parts ) ) {
			$parts = array( $segment );
		}

		$found   = array();
		$indexed = self::index_countries( $entities );
		uksort(
			$indexed,
			static function ( $a, $b ) use ( $indexed ) {
				$la = max( array_map( 'strlen', $indexed[ $a ] ) );
				$lb = max( array_map( 'strlen', $indexed[ $b ] ) );
				return $lb - $la;
			}
		);

		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( '' === $part ) {
				continue;
			}
			foreach ( self::match_countries_in_text( $part, $indexed ) as $code ) {
				if ( ! in_array( $code, $found, true ) ) {
					$found[] = $code;
				}
			}
		}

		if ( ! empty( $found ) ) {
			return $found;
		}

		return self::match_countries_in_text( $segment, $indexed );
	}

	/**
	 * @param string                         $text    Text to scan.
	 * @param array<string,array<int,string>> $indexed Country alias index.
	 * @return array<int,string>
	 */
	private static function match_countries_in_text( $text, array $indexed ) {
		$found = array();
		$text  = RWGA_Local_Intent_Interpreter::normalise( $text );

		foreach ( $indexed as $code => $aliases ) {
			usort(
				$aliases,
				static function ( $a, $b ) {
					return strlen( (string) $b ) - strlen( (string) $a );
				}
			);
			foreach ( $aliases as $alias ) {
				$alias = RWGA_Local_Intent_Interpreter::normalise( $alias );
				if ( '' === $alias ) {
					continue;
				}
				$pattern = '/\b' . preg_quote( $alias, '/' ) . '\b/i';
				if ( preg_match( $pattern, $text ) && ! in_array( $code, $found, true ) ) {
					$found[] = $code;
					$text    = preg_replace( $pattern, ' ', $text );
				}
			}
		}

		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		if ( '' !== $text ) {
			foreach ( preg_split( '/\s+/', $text ) as $token ) {
				if ( '' === $token ) {
					continue;
				}
				foreach ( $indexed as $code => $aliases ) {
					foreach ( $aliases as $alias ) {
						if ( $token === RWGA_Local_Intent_Interpreter::normalise( $alias ) && ! in_array( $code, $found, true ) ) {
							$found[] = $code;
						}
					}
				}
			}
		}

		return $found;
	}

	/**
	 * @param array<int,string> $codes    Codes.
	 * @param array<int,array>  $entities Entities.
	 * @return string
	 */
	private static function default_group_label( array $codes, array $entities ) {
		if ( class_exists( 'RWGA_Variant_Group_Extractor', false ) ) {
			$group = RWGA_Variant_Group_Extractor::group_from_segment( implode( ' ', $codes ), $entities );
			return (string) ( $group['label'] ?? implode( ' + ', $codes ) );
		}
		return implode( ' + ', $codes );
	}

	/**
	 * @param array<int,array> $entities Entities.
	 * @return array<string,array<int,string>>
	 */
	private static function index_countries( array $entities ) {
		$out = array();
		foreach ( $entities as $row ) {
			if ( ! is_array( $row ) || ( $row['entity_type'] ?? '' ) !== 'country' ) {
				continue;
			}
			$code = (string) ( $row['value'] ?? $row['entity_key'] ?? '' );
			if ( '' === $code ) {
				continue;
			}
			$aliases   = isset( $row['aliases'] ) && is_array( $row['aliases'] ) ? $row['aliases'] : array();
			$aliases[] = (string) ( $row['display_name'] ?? '' );
			$aliases[] = strtolower( (string) ( $row['display_name'] ?? '' ) );
			$aliases[] = $code;
			$aliases[] = strtolower( $code );
			$out[ $code ] = array_values( array_unique( array_filter( self::filter_ambiguous_aliases( $aliases ) ) ) );
		}
		return $out;
	}

	/**
	 * Drop 2-letter ISO codes that collide with common English words ("IT", "NO", "AT", "IN", "US", "BE").
	 *
	 * @param array<int,string> $aliases Alias list.
	 * @return array<int,string>
	 */
	private static function filter_ambiguous_aliases( array $aliases ) {
		$stopwords = array(
			'it', 'no', 'in', 'be', 'at', 'am', 'as', 'by', 'do', 'go',
			'he', 'if', 'me', 'my', 'of', 'on', 'or', 'so', 'to', 'oh', 'hi',
			'id', 're', 'we', 'an', 'is', 'up', 'pm',
		);
		$out = array();
		foreach ( $aliases as $alias ) {
			$normalised = strtolower( trim( (string) $alias ) );
			if ( 2 === strlen( $normalised ) && in_array( $normalised, $stopwords, true ) ) {
				continue;
			}
			$out[] = $alias;
		}
		return $out;
	}

	/**
	 * @param string $phrase Phrase.
	 * @return string
	 */
	private static function extract_page_token( $phrase ) {
		if ( preg_match( '/\b(?:duplicate|variants?|versions? of)\s+(?:the\s+)?([a-z0-9\s-]+?)(?:\s+twice|\s+with|\s+one|\s*,|\s*$)/i', $phrase, $m ) ) {
			return trim( $m[1] );
		}
		if ( preg_match( '/\bhomepage\b|\bhome page\b/i', $phrase ) ) {
			return 'homepage';
		}
		return 'homepage';
	}

	/**
	 * @param string                         $page     Page ref.
	 * @param array<int,array<string,mixed>> $variants Variants.
	 * @return string
	 */
	private static function build_summary( $page, array $variants ) {
		$labels = array_map(
			static function ( $v ) {
				return (string) ( $v['label'] ?? implode( ' + ', $v['countries'] ?? array() ) );
			},
			$variants
		);
		return sprintf(
			/* translators: 1: variant count, 2: page, 3: variant labels */
			__( 'I found a %1$d-variant setup for the %2$s: %3$s.', 'reactwoo-geocore' ),
			count( $variants ),
			$page,
			implode( '; ', $labels )
		);
	}
}
