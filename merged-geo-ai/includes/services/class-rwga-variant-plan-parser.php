<?php
/**
 * Structured slot/segment parser for variant planning commands.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Variant_Plan_Parser {

	/** @var array<string,int> */
	const COUNT_MAP = array(
		'one'   => 1,
		'two'   => 2,
		'three' => 3,
		'four'  => 4,
		'five'  => 5,
		'1'     => 1,
		'2'     => 2,
		'3'     => 3,
		'4'     => 4,
		'5'     => 5,
	);

	/**
	 * @param string              $message  Raw user message.
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $context  Context.
	 * @return array<string,mixed>
	 */
	public static function parse( $message, array $entities, array $context = array() ) {
		unset( $context );
		$phrase = self::normalise( $message );
		if ( '' === $phrase ) {
			return array( 'matched' => false, 'reason' => 'empty_input' );
		}

		$debug = array(
			'parser_used'                 => 'RWGA_Variant_Plan_Parser',
			'normalised_input'            => $phrase,
			'variant_plan_terms_detected' => self::is_variant_plan_command( $phrase ),
			'fallback_pattern_match_used' => false,
		);

		if ( ! $debug['variant_plan_terms_detected'] ) {
			return array_merge(
				array( 'matched' => false, 'reason' => 'no_variant_plan_terms' ),
				array( '_debug' => $debug )
			);
		}

		$page_context        = self::detect_page_context( $phrase );
		$page_value          = (string) ( $page_context['variant_source'] ?? $page_context['primary'] ?? 'homepage' );
		$total_version_count = self::detect_total_version_count( $phrase );
		$duplicate_count     = self::detect_duplicate_count( $phrase );
		$segments            = self::split_segments_from_boundaries( $phrase );
		$raw_clauses         = array_values(
			array_filter(
				array_map(
					static function ( $segment ) {
						return trim( (string) ( $segment['raw'] ?? '' ) );
					},
					$segments
				)
			)
		);
		foreach ( $segments as $idx => $segment ) {
			$raw = (string) ( $segment['raw'] ?? '' );
			$segments[ $idx ]['type']    = self::classify_clause_type( $raw );
			$segments[ $idx ]['marker']  = self::clause_marker_label( $raw, $segments[ $idx ]['type'] );
			$segments[ $idx ]['ordinal'] = (int) ( $segment['ordinal'] ?? 0 ) > 0
				? (int) $segment['ordinal']
				: self::clause_ordinal_hint( $raw );
		}

		$debug['source_page_ref']      = $page_value;
		$debug['page_context']         = $page_context;
		$debug['total_version_count']  = $total_version_count;
		$debug['duplicate_count']      = $duplicate_count;
		$debug['raw_clauses']          = $raw_clauses;
		$debug['segments']             = $segments;

		$source   = null;
		$variants = array();
		$variant_ordinal = 0;

		foreach ( $segments as $segment ) {
			$countries = self::extract_country_list_from_segment( $segment['raw'], $entities );
			$weather   = class_exists( 'RWGA_Segment_Condition_Extractor', false )
				? RWGA_Segment_Condition_Extractor::extract_weather( $segment['raw'], $entities )
				: null;
			$mode      = self::detect_mode( $segment['raw'] );
			$label     = self::label_for_segment( $countries, $weather, $entities );

			if ( empty( $countries ) ) {
				continue;
			}

			if ( 'source' === $segment['type'] ) {
				$source = array(
					'raw'       => $segment['raw'],
					'marker'    => $segment['marker'] ?? 'original',
					'label'     => self::original_page_label( (string) ( $page_context['original_page'] ?? 'homepage' ) ),
					'countries' => $countries,
					'mode'      => $mode,
					'weather'   => $weather,
				);
				continue;
			}

			$variant_ordinal++;
			$ordinal = (int) ( $segment['ordinal'] ?? 0 );
			if ( $ordinal <= 0 ) {
				$ordinal = $variant_ordinal;
			}
			$variants[] = array(
				'ordinal'   => $ordinal,
				'raw'       => $segment['raw'],
				'marker'    => $segment['marker'] ?? '',
				'label'     => $label,
				'mode'      => $mode,
				'countries' => $countries,
				'weather'   => $weather,
			);
		}

		$variants = self::expand_one_other_variants( $variants, $entities );

		$debug['detected_source_clause']   = $source;
		$debug['detected_variant_clauses'] = $variants;
		$debug['countries_per_clause']     = array_map(
			static function ( $segment ) use ( $entities ) {
				$raw = (string) ( $segment['raw'] ?? '' );
				return array(
					'raw'       => $raw,
					'type'      => $segment['type'] ?? '',
					'countries' => self::extract_country_list_from_segment( $raw, $entities ),
					'weather'   => class_exists( 'RWGA_Segment_Condition_Extractor', false )
						? RWGA_Segment_Condition_Extractor::extract_weather( $raw, $entities )
						: null,
				);
			},
			$segments
		);
		$debug['mixed_conditions_detected'] = self::has_mixed_conditions( $segments, $entities );

		if ( null === $source && self::has_original_marker( $phrase ) ) {
			$legacy = class_exists( 'RWGA_Original_Source_Targeting_Extractor', false )
				? RWGA_Original_Source_Targeting_Extractor::extract( $phrase, $entities )
				: null;
			if ( is_array( $legacy ) && ! empty( $legacy['countries'] ) ) {
				$source = $legacy;
			}
		}

		if ( empty( $variants ) && class_exists( 'RWGA_Variant_Group_Extractor', false ) ) {
			$groups = RWGA_Variant_Group_Extractor::extract_plan_variant_groups( $phrase, $entities, $source );
			foreach ( $groups as $idx => $group ) {
				$variants[] = array(
					'ordinal'   => $idx + ( $source ? 2 : 1 ),
					'raw'       => $group['raw'] ?? '',
					'marker'    => '',
					'label'     => $group['label'] ?? '',
					'mode'      => $group['mode'] ?? 'include_only',
					'countries' => $group['countries'] ?? array(),
				);
			}
		}

		$all_countries = RWGA_Multi_Variant_Interpreter::parse_country_list( $phrase, $entities );
		$debug['countries_detected'] = $all_countries;

		if ( $total_version_count >= 3 && null === $source && ! self::has_original_marker( $phrase ) ) {
			return self::ambiguous_source_usage( $phrase, $page_value, $debug );
		}

		if ( empty( $variants ) ) {
			$debug['segments_found'] = count( $segments );
			$debug['reason']         = empty( $segments ) ? 'failed_to_segment_variant_plan' : 'incomplete_variant_plan';
			if ( ! empty( $all_countries ) && $page_value ) {
				return self::partial_clarification( $phrase, $page_value, $all_countries, $debug );
			}
			return array_merge(
				array( 'matched' => false, 'reason' => $debug['reason'] ),
				array( '_debug' => $debug )
			);
		}

		if ( null === $source && self::has_original_marker( $phrase ) ) {
			$debug['segments_found'] = count( $segments );
			$debug['reason']         = 'incomplete_variant_plan';
			if ( ! empty( $all_countries ) && $page_value ) {
				return self::partial_clarification( $phrase, $page_value, $all_countries, $debug );
			}
			return array_merge(
				array( 'matched' => false, 'reason' => $debug['reason'] ),
				array( '_debug' => $debug )
			);
		}

		$enriched_segments = array();
		foreach ( $segments as $segment ) {
			$countries = self::extract_country_list_from_segment( $segment['raw'], $entities );
			if ( empty( $countries ) ) {
				continue;
			}
			$weather = class_exists( 'RWGA_Segment_Condition_Extractor', false )
				? RWGA_Segment_Condition_Extractor::extract_weather( $segment['raw'], $entities )
				: null;
			$enriched_segments[] = array_merge(
				$segment,
				array(
					'countries' => $countries,
					'weather'   => $weather,
					'mode'      => self::detect_mode( $segment['raw'] ),
				)
			);
		}
		$debug['segments'] = $enriched_segments;

		$result = self::build_matched_result( $phrase, $page_value, $source, $variants, $total_version_count, $duplicate_count, $enriched_segments, $debug, $entities, $page_context );
		self::log_parse_result( $phrase, $debug, $result );
		return $result;
	}

	/**
	 * @param string $message Raw message.
	 * @return string
	 */
	public static function normalise( $message ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $message );
		$phrase = str_replace( array( '’', '`' ), "'", $phrase );
		$phrase = preg_replace( '/[.!?;:]+/', ' ', $phrase );
		return trim( preg_replace( '/\s+/', ' ', (string) $phrase ) );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	public static function is_variant_plan_command( $phrase ) {
		$has_variant = (bool) preg_match(
			'/\b(variation|variations|variant|variants|version|versions|duplicate|copy|clone|original|source|default|current page|existing page)\b/i',
			$phrase
		);
		$has_targeting = (bool) preg_match(
			'/\b(show in|show for|display in|display for|works in|fire in|trigger in|target|only show|visible in|for users in|for visitors in|only display|when its|when it is|when it\'s|all weather)\b/i',
			$phrase
		);
		$has_creation = (bool) preg_match(
			'/\b(create|make|duplicate|copy|clone|build|setup|set up|update)\b/i',
			$phrase
		);
		return $has_variant && ( $has_targeting || $has_creation );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	public static function has_original_marker( $phrase ) {
		if ( class_exists( 'RWGA_Original_Source_Targeting_Extractor', false )
			&& RWGA_Original_Source_Targeting_Extractor::has_original_marker( $phrase ) ) {
			return true;
		}
		return (bool) preg_match(
			'/\b(?:update|keep|leave|make)\s+(?:the\s+)?original\b|\bthe\s+original\b|\boriginal\s+(?:version|homepage|page)\b|\bupdate\s+(?:the\s+)?homepage\b/i',
			$phrase
		);
	}

	/**
	 * @param string           $phrase   Phrase.
	 * @param array<int,array> $entities Entities.
	 * @return array<int,string>
	 */
	public static function extract_country_list_from_segment( $segment, array $entities ) {
		return RWGA_Multi_Variant_Interpreter::parse_country_list( $segment, $entities );
	}

	/**
	 * @param string $segment Segment text.
	 * @return string
	 */
	public static function detect_mode( $segment ) {
		if ( preg_match( '/\b(hide from|exclude|do not show|don\'t show|block|prevent)\b/i', $segment ) ) {
			return 'exclude';
		}
		return 'include_only';
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return string
	 */
	private static function detect_page_ref( $phrase ) {
		$ctx = self::detect_page_context( $phrase );
		return (string) ( $ctx['variant_source'] ?? $ctx['primary'] ?? 'homepage' );
	}

	/**
	 * Resolve variant source page vs original page references.
	 *
	 * @param string $phrase Normalised phrase.
	 * @return array<string,mixed>
	 */
	public static function detect_page_context( $phrase ) {
		$variant_source = null;
		$original_page  = null;

		if ( preg_match( '/\bvariants?\s+of\s+(?:the\s+)?(shop(?:\s+page)?|home\s+page|homepage|checkout|cart|pricing(?:\s+page)?|contact(?:\s+page)?)\b/i', $phrase, $m ) ) {
			$variant_source = self::normalise_page_token( (string) $m[1] );
		}
		if ( preg_match( '/\boriginal\s+(shop(?:\s+page)?|home\s+page|homepage|checkout|cart)\b/i', $phrase, $m ) ) {
			$original_page = self::normalise_page_token( (string) $m[1] );
		} elseif ( preg_match( '/\bupdate\s+(?:the\s+)?(homepage|shop(?:\s+page)?|home\s+page)\b/i', $phrase, $m ) ) {
			$original_page = self::normalise_page_token( (string) $m[1] );
		}

		if ( ! $variant_source && class_exists( 'RWGA_Page_Reference_Resolver', false ) ) {
			$head = $phrase;
			if ( preg_match( '/^(.*?)(?:\b(?:one(?:\s+(?:variant|variation|version))?|update\s+(?:the\s+)?original)\b)/i', $phrase, $m ) ) {
				$head = (string) $m[1];
			}
			$page = RWGA_Page_Reference_Resolver::detect( $head );
			if ( is_array( $page ) && ! empty( $page['value'] ) ) {
				$variant_source = (string) $page['value'];
			}
		}

		if ( ! $original_page && preg_match( '/\b(?:update|keep)\s+(?:the\s+)?original\b/i', $phrase ) ) {
			$original_page = 'homepage';
		}

		$primary = $variant_source ?: $original_page ?: 'homepage';
		return array(
			'variant_source' => $variant_source,
			'original_page'  => $original_page,
			'primary'        => $primary,
			'has_mismatch'   => $variant_source && $original_page && $variant_source !== $original_page,
		);
	}

	/**
	 * @param string $token Raw page token.
	 * @return string
	 */
	private static function normalise_page_token( $token ) {
		$token = strtolower( trim( (string) $token ) );
		if ( in_array( $token, array( 'shop', 'shop page' ), true ) ) {
			return 'shop';
		}
		if ( in_array( $token, array( 'homepage', 'home page', 'home' ), true ) ) {
			return 'homepage';
		}
		if ( in_array( $token, array( 'pricing', 'pricing page' ), true ) ) {
			return 'pricing';
		}
		if ( in_array( $token, array( 'contact', 'contact page' ), true ) ) {
			return 'contact';
		}
		if ( 'checkout' === $token ) {
			return 'checkout';
		}
		if ( 'cart' === $token ) {
			return 'cart';
		}
		return $token;
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_total_version_count( $phrase ) {
		$new_variants = self::detect_create_new_variant_count( $phrase );
		if ( $new_variants > 0 ) {
			return self::has_original_marker( $phrase ) ? $new_variants + 1 : $new_variants;
		}
		$total_variations = self::detect_total_variation_count( $phrase );
		if ( $total_variations > 0 ) {
			return $total_variations;
		}
		return 0;
	}

	/**
	 * Count of new variant duplicates from explicit "create N variants" / "create N new …".
	 *
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_create_new_variant_count( $phrase ) {
		if ( preg_match( '/\b(?:create|make|build)\s+(?:an\s+)?(?:additional\s+)?(?:(\d+|one|two|three|four|five))(?:\s+new)?\s+variants\b/i', $phrase, $m ) ) {
			$key = strtolower( $m[1] );
			return self::COUNT_MAP[ $key ] ?? (int) $m[1];
		}
		if ( preg_match( '/\b(?:create|make|build)\s+(?:(\d+|one|two|three|four|five))\s+new\s+(?:variations?|versions?)\b/i', $phrase, $m ) ) {
			$key = strtolower( $m[1] );
			return self::COUNT_MAP[ $key ] ?? (int) $m[1];
		}
		return 0;
	}

	/**
	 * Total version count when user names variations/versions (may include original).
	 *
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_total_variation_count( $phrase ) {
		if ( preg_match( '/\b(?:create|make|build)\s+(?:(\d+|one|two|three|four|five))\s+(?:variations?|versions?)\b/i', $phrase, $m ) ) {
			$key = strtolower( $m[1] );
			return self::COUNT_MAP[ $key ] ?? (int) $m[1];
		}
		return 0;
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return int
	 * @deprecated Use detect_create_new_variant_count() or detect_total_variation_count().
	 */
	public static function detect_create_variant_count( $phrase ) {
		return self::detect_create_new_variant_count( $phrase );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function detect_duplicate_count( $phrase ) {
		$new_variants = self::detect_create_new_variant_count( $phrase );
		if ( $new_variants > 0 ) {
			return $new_variants;
		}
		$total_variations = self::detect_total_variation_count( $phrase );
		if ( $total_variations > 0 && self::has_original_marker( $phrase ) ) {
			return max( 0, $total_variations - 1 );
		}
		if ( preg_match( '/\b(?:duplicate|copy|clone)\s+(?:the\s+)?[\w\s-]+?\s+twice\b/i', $phrase ) ) {
			return 2;
		}
		if ( preg_match( '/\b(?:duplicate|copy|clone)\s+(?:the\s+)?[\w\s-]+?\s+two times\b/i', $phrase ) ) {
			return 2;
		}
		return 0;
	}

	/**
	 * Split a variant plan into sibling clauses before country extraction.
	 *
	 * @param string $normalised Normalised phrase.
	 * @return array<int,string>
	 */
	public static function split_variant_plan_clauses( $normalised ) {
		$phrase = trim( (string) $normalised );
		if ( '' === $phrase ) {
			return array();
		}

		$segments = self::split_segments_from_boundaries( $phrase );
		if ( empty( $segments ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					static function ( $segment ) {
						return trim( (string) ( $segment['raw'] ?? '' ) );
					},
					$segments
				)
			)
		);
	}

	/**
	 * @param array<int,string> $raw_clauses Clause strings.
	 * @return array<int,array<string,mixed>>
	 */
	private static function classify_plan_clauses( array $raw_clauses ) {
		$segments = array();
		foreach ( $raw_clauses as $raw ) {
			$raw = trim( (string) $raw );
			if ( '' === $raw ) {
				continue;
			}
			$type = self::classify_clause_type( $raw );
			$segments[] = array(
				'type'    => $type,
				'marker'  => self::clause_marker_label( $raw, $type ),
				'ordinal' => self::clause_ordinal_hint( $raw ),
				'raw'     => $raw,
			);
		}
		return $segments;
	}

	/**
	 * @param string $clause Raw clause text.
	 * @return string source|variant
	 */
	private static function classify_clause_type( $clause ) {
		if ( preg_match( '/\b(?:update|keep|leave|make)\s+(?:the\s+)?original\b|\bthe\s+original\s+(?:version|homepage|page)\b|\boriginal\s+(?:should|will|would|can)\b|\bupdate\s+(?:the\s+)?homepage\b|\bupdate\s+original\b/i', $clause ) ) {
			return 'source';
		}
		return 'variant';
	}

	/**
	 * @param string $clause Clause text.
	 * @param string $type   source|variant.
	 * @return string
	 */
	private static function clause_marker_label( $clause, $type ) {
		if ( 'source' === $type ) {
			return 'update the original';
		}
		if ( preg_match( '/\b(the\s+other|another)\b/i', $clause ) ) {
			return 'the other should';
		}
		if ( preg_match( '/\bone\s+for\b/i', $clause ) ) {
			return 'one for';
		}
		return 'one should';
	}

	/**
	 * @param string $clause Clause text.
	 * @return int
	 */
	private static function clause_ordinal_hint( $clause ) {
		if ( preg_match( '/\b(?:the\s+third|variant\s+three|version\s+three|3rd|variant\s+3|version\s+3)\b/i', $clause ) ) {
			return 3;
		}
		if ( preg_match( '/\b(?:the\s+second|variant\s+two|version\s+two|2nd|variant\s+2|version\s+2)\b/i', $clause ) ) {
			return 2;
		}
		return 0;
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<int,array<string,mixed>>
	 */
	public static function split_segments( $phrase ) {
		return self::split_segments_from_boundaries( $phrase );
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<int,array<string,mixed>>
	 */
	private static function split_segments_from_boundaries( $phrase ) {
		$hits = array();

		foreach ( self::clause_boundary_patterns() as $row ) {
			$capture = (int) ( $row['capture'] ?? 0 );
			if ( ! preg_match_all( $row['pattern'], $phrase, $matches, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}
			if ( ! isset( $matches[ $capture ] ) ) {
				continue;
			}
			foreach ( $matches[ $capture ] as $match ) {
				if ( ! is_array( $match ) || '' === $match[0] ) {
					continue;
				}
				$hits[] = array(
					'offset'  => (int) $match[1],
					'length'  => strlen( $match[0] ),
					'type'    => $row['type'],
					'marker'  => $row['marker'],
					'ordinal' => (int) ( $row['ordinal'] ?? 0 ),
					'priority'=> (int) ( $row['priority'] ?? 10 ),
				);
			}
		}

		if ( empty( $hits ) ) {
			return array();
		}

		usort(
			$hits,
			static function ( $a, $b ) {
				if ( $a['offset'] === $b['offset'] ) {
					if ( $a['priority'] === $b['priority'] ) {
						return $b['length'] - $a['length'];
					}
					return $b['priority'] - $a['priority'];
				}
				return $a['offset'] - $b['offset'];
			}
		);

		$filtered = array();
		$last_end = -1;
		foreach ( $hits as $hit ) {
			if ( $hit['offset'] < $last_end ) {
				continue;
			}
			$filtered[] = $hit;
			$last_end     = $hit['offset'] + $hit['length'];
		}

		$segments = array();
		foreach ( $filtered as $idx => $hit ) {
			$start = $hit['offset'];
			$end   = isset( $filtered[ $idx + 1 ] ) ? $filtered[ $idx + 1 ]['offset'] : strlen( $phrase );
			$raw   = trim( substr( $phrase, $start, $end - $start ) );
			$raw   = preg_replace( '/\s+and\s*$/i', '', (string) $raw );
			$raw   = preg_replace( '/\s*-\s*$/', '', (string) $raw );
			if ( '' === $raw ) {
				continue;
			}
			$segments[] = array(
				'type'    => $hit['type'],
				'marker'  => $hit['marker'],
				'ordinal' => $hit['ordinal'],
				'raw'     => $raw,
			);
		}

		return $segments;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private static function clause_boundary_patterns() {
		$verbs  = '(?:show|display|fire|trigger|work|activate|appear|serve|render)';
		$modal  = '(?:should|can|will|would|needs\\s+to)';
		$actor  = '(?:(?:one|another|the\\s+other)(?:\\s+(?:variant|variation|version))?\\s+' . $modal . '\\s+' . $verbs . ')';
		$variant_should = $actor;
		$variant_will   = '(?:(?:one|another|the\\s+other)(?:\\s+(?:variant|variation|version))?\\s+will\\s+' . $verbs . ')';
		$variant_would  = '(?:(?:one|another|the\\s+other)(?:\\s+(?:variant|variation|version))?\\s+would\\s+' . $verbs . ')';
		$variant_for    = '(?:(?:one|another|the\\s+other)(?:\\s+(?:variant|variation|version))?\\s+for)';
		$variant_in     = '(?:the\\s+other\\s+(?:in|for|to))';
		$version_target = '(?:(?:one|another)(?:\\s+(?:variant|variation|version))?\\s+(?:' . $modal . ')\\s+' . $verbs . ')';
		$ordinal_target = '(?:(?:the\\s+)?(?:3rd|third|2nd|second)\\s+(?:version|variant|variation)\\s+(?:' . $modal . ')\\s+' . $verbs . ')';
		$update_homepage = '(?:update\\s+(?:the\\s+)?homepage(?:\\s+to\\s+(?:show|display|for))?)';

		return array(
			array( 'pattern' => '/\band\s+(' . $variant_in . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and the other in', 'ordinal' => 2, 'priority' => 115 ),
			array( 'pattern' => '/\band\s+(' . $variant_should . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and one should', 'ordinal' => 0, 'priority' => 100 ),
			array( 'pattern' => '/\band\s+(' . $variant_will . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and one will', 'ordinal' => 0, 'priority' => 100 ),
			array( 'pattern' => '/\band\s+(' . $variant_would . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and one would', 'ordinal' => 0, 'priority' => 100 ),
			array( 'pattern' => '/\band\s+(' . $variant_for . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and one for', 'ordinal' => 0, 'priority' => 100 ),
			array( 'pattern' => '/\band\s+(' . $version_target . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and one version should', 'ordinal' => 0, 'priority' => 99 ),
			array( 'pattern' => '/\band\s+(another\\s+' . $modal . '\\s+' . $verbs . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and another should', 'ordinal' => 0, 'priority' => 99 ),
			array( 'pattern' => '/\band\s+(another\s+for)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and another for', 'ordinal' => 0, 'priority' => 99 ),
			array( 'pattern' => '/\band\s+(?:create\s+)?(one\s+(?:version|variant)\s+for)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and create one version for', 'ordinal' => 0, 'priority' => 98 ),
			array( 'pattern' => '/\band\s+(' . $ordinal_target . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and ordinal version', 'ordinal' => 0, 'priority' => 98 ),
			array( 'pattern' => '/\band\s+((?:variant|variation|version)\s+(?:two|2))\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and variant two', 'ordinal' => 2, 'priority' => 95 ),
			array( 'pattern' => '/\band\s+((?:variant|variation|version)\s+(?:three|3))\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and variant three', 'ordinal' => 3, 'priority' => 95 ),
			array( 'pattern' => '/\band\s+(the\s+second)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and the second', 'ordinal' => 2, 'priority' => 90 ),
			array( 'pattern' => '/\band\s+(the\s+third)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'and the third', 'ordinal' => 3, 'priority' => 90 ),
			array( 'pattern' => '/(?:^|[,\-]\s*|\band\s+|\s)(the\s+original\s+(?:version|homepage|page)\s+(?:' . $modal . ')\\s+' . $verbs . '(?:\s+in)?)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'the original version should', 'ordinal' => 0, 'priority' => 88 ),
			array( 'pattern' => '/(?:^|\s)(keep\s+the\s+original(?:\s+homepage)?(?:\s+for)?)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'keep the original', 'ordinal' => 0, 'priority' => 86 ),
			array( 'pattern' => '/(?:^|[,\-]\s*|\band\s+)(then\s+update\s+the\s+original(?:\s+homepage)?)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'then update the original homepage', 'ordinal' => 0, 'priority' => 85 ),
			array( 'pattern' => '/(?<=\s)(' . $update_homepage . ')\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'update homepage', 'ordinal' => 0, 'priority' => 84 ),
			array( 'pattern' => '/(?:^|[,\-]\s*|\band\s+)(' . $update_homepage . ')\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'update homepage', 'ordinal' => 0, 'priority' => 83 ),
			array( 'pattern' => '/(?<=\s)(update\s+the\s+original(?:\s+(?:homepage|page|version))?(?:\s+(?:to\s+)?(?:display|show|for))?)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'update the original', 'ordinal' => 0, 'priority' => 82 ),
			array( 'pattern' => '/(?:^|[,\-]\s*|\band\s+)(update\s+the\s+original(?:\s+(?:homepage|page|version))?(?:\s+(?:to\s+)?(?:display|show|for))?)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'update the original', 'ordinal' => 0, 'priority' => 80 ),
			array( 'pattern' => '/(?:^|[,\-]\s*|\band\s+)(update\s+original(?:\s+for)?)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'update original', 'ordinal' => 0, 'priority' => 81 ),
			array( 'pattern' => '/(?<=\s)(update\s+original(?:\s+for)?)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'update original', 'ordinal' => 0, 'priority' => 81 ),
			array( 'pattern' => '/(?:^|[,\-]\s*|\band\s+)(keep\s+the\s+original\s+for)\b/i', 'capture' => 1, 'type' => 'source', 'marker' => 'keep the original for', 'ordinal' => 0, 'priority' => 79 ),
			array( 'pattern' => '/(?<!\band\s)(' . $variant_should . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'one should', 'ordinal' => 0, 'priority' => 50 ),
			array( 'pattern' => '/(?<!\band\s)(' . $variant_will . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'one will', 'ordinal' => 0, 'priority' => 50 ),
			array( 'pattern' => '/(?<!\band\s)(' . $variant_would . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'one would', 'ordinal' => 0, 'priority' => 50 ),
			array( 'pattern' => '/(?<!\band\s)(' . $variant_for . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'one for', 'ordinal' => 0, 'priority' => 50 ),
			array( 'pattern' => '/(?<!\band\s)(' . $version_target . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'one version should', 'ordinal' => 0, 'priority' => 52 ),
			array( 'pattern' => '/(?<!\band\s)(create\s+one\s+(?:version|variant)\s+for)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'create one version for', 'ordinal' => 0, 'priority' => 52 ),
			array( 'pattern' => '/(?<!\band\s)(' . $ordinal_target . ')\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'ordinal version', 'ordinal' => 0, 'priority' => 52 ),
			array( 'pattern' => '/(?<!\band\s)((?:variant|variation|version)\s+(?:two|2))\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'variant two', 'ordinal' => 2, 'priority' => 45 ),
			array( 'pattern' => '/(?<!\band\s)((?:variant|variation|version)\s+(?:three|3))\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'variant three', 'ordinal' => 3, 'priority' => 45 ),
			array( 'pattern' => '/(?<!\band\s)((?:2nd|3rd)\s+(?:variant|variation|version))\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'ordinal variant', 'ordinal' => 0, 'priority' => 45 ),
			array( 'pattern' => '/(?<!\band\s)(the\s+second)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'the second', 'ordinal' => 2, 'priority' => 40 ),
			array( 'pattern' => '/(?<!\band\s)(the\s+third)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'the third', 'ordinal' => 3, 'priority' => 40 ),
			array( 'pattern' => '/(?<!\band\s)(another(?:\s+(?:version|variant|variation))?)\b/i', 'capture' => 1, 'type' => 'variant', 'marker' => 'another', 'ordinal' => 0, 'priority' => 35 ),
		);

		if ( class_exists( 'RWGA_Parser_Hints_Service', false ) ) {
			$patterns = array_merge( $patterns, RWGA_Parser_Hints_Service::dynamic_boundary_patterns() );
		}

		return $patterns;
	}

	/**
	 * @param string                         $phrase              Phrase.
	 * @param string                         $page_value          Page ref.
	 * @param array<string,mixed>            $source              Source targeting.
	 * @param array<int,array<string,mixed>> $variants            Variants.
	 * @param int                            $total_version_count Total versions.
	 * @param int                            $duplicate_count     Duplicate count.
	 * @param array<int,array>               $segments            Segments.
	 * @param array<string,mixed>            $debug               Debug.
	 * @param array<int,array>               $entities            Entities.
	 * @param array<string,mixed>            $page_context        Page context.
	 * @return array<string,mixed>
	 */
	private static function build_matched_result( $phrase, $page_value, $source, array $variants, $total_version_count, $duplicate_count, array $segments, array $debug, array $entities, array $page_context = array() ) {
		$has_source       = is_array( $source ) && ! empty( $source['countries'] );
		$source_countries = $has_source ? (array) ( $source['countries'] ?? array() ) : array();
		$source_weather   = $has_source ? ( $source['weather'] ?? null ) : null;
		$source_display   = $has_source
			? self::label_for_segment( $source_countries, $source_weather, $entities )
			: '';
		$has_weather      = null !== $source_weather;
		foreach ( $variants as $variant ) {
			if ( null !== ( $variant['weather'] ?? null ) ) {
				$has_weather = true;
				break;
			}
		}
		$matched_action = $has_weather
			? 'geocore_create_variant_plan_with_conditions'
			: 'geocore_create_variant_plan_with_country_rules';

		$steps = array();
		$source_label = self::original_page_label( (string) ( $page_context['original_page'] ?? 'homepage' ) );
		if ( $has_source ) {
			$step_params = array(
				'source_page_ref' => (string) ( $page_context['original_page'] ?? $page_value ),
				'countries'       => $source_countries,
				'mode'            => $source['mode'] ?? 'include_only',
			);
			if ( null !== $source_weather && class_exists( 'RWGA_Segment_Condition_Extractor', false ) ) {
				$step_params['weather'] = RWGA_Segment_Condition_Extractor::weather_param( $source_weather );
			}
			$steps[] = array(
				'label'  => sprintf(
					/* translators: %s: country label */
					__( 'Apply %s targeting to %s', 'reactwoo-geocore' ),
					$source_display,
					strtolower( $source_label )
				),
				'action' => 'geocore_apply_country_rule_to_source',
				'params' => $step_params,
			);
		}

		$variant_out = array();
		foreach ( $variants as $variant ) {
			$ordinal       = (int) ( $variant['ordinal'] ?? 0 );
			$label         = (string) ( $variant['label'] ?? '' );
			$weather_key   = $variant['weather'] ?? null;
			$variant_row   = array(
				'ordinal'   => $ordinal,
				'label'     => $label,
				'mode'      => $variant['mode'] ?? 'include_only',
				'countries' => $variant['countries'] ?? array(),
			);
			if ( null !== $weather_key && class_exists( 'RWGA_Segment_Condition_Extractor', false ) ) {
				$variant_row['weather'] = RWGA_Segment_Condition_Extractor::weather_param( $weather_key );
			}
			$variant_out[] = $variant_row;
			$step_params   = array(
				'source_page_ref' => $page_value,
				'countries'       => $variant['countries'] ?? array(),
				'mode'            => $variant['mode'] ?? 'include_only',
			);
			if ( null !== $weather_key && class_exists( 'RWGA_Segment_Condition_Extractor', false ) ) {
				$step_params['weather'] = RWGA_Segment_Condition_Extractor::weather_param( $weather_key );
			}
			$steps[] = array(
				'label'  => sprintf(
					/* translators: 1: variant number, 2: label */
					__( 'Variant %1$d: %2$s', 'reactwoo-geocore' ),
					$ordinal,
					$label
				),
				'action' => 'geocore_duplicate_page_as_variant',
				'params' => $step_params,
			);
		}

		if ( $duplicate_count <= 0 ) {
			$duplicate_count = count( $variant_out );
		}

		$variant_labels = array_map(
			static function ( $v ) {
				return (string) ( $v['label'] ?? '' );
			},
			$variant_out
		);

		$summary = self::build_plan_summary( $has_source, $source_display, $source_weather, $variant_labels, $entities );

		$source_targeting_param = null;
		if ( $has_source ) {
			$source_targeting_param = array(
				'label'           => $source_label,
				'targeting_label' => $source_display,
				'mode'            => $source['mode'] ?? 'include_only',
				'countries'       => $source_countries,
			);
			if ( null !== $source_weather && class_exists( 'RWGA_Segment_Condition_Extractor', false ) ) {
				$source_targeting_param['weather'] = RWGA_Segment_Condition_Extractor::weather_param( $source_weather );
			}
		}

		$debug['matched_action'] = $matched_action;
		$debug['confidence']     = 0.88;
		$debug['warnings']       = $has_weather && ! (bool) apply_filters( 'rwgc_pro_enabled', false )
			? array( __( 'Weather conditions may require Geo Core Pro for full runtime support.', 'reactwoo-geocore' ) )
			: array();

		$has_page_mismatch = ! empty( $page_context['has_mismatch'] );
		$proposal_ready    = ! $has_page_mismatch;
		$missing           = array();
		if ( $has_page_mismatch ) {
			$variant_page  = self::page_display_name( (string) ( $page_context['variant_source'] ?? $page_value ) );
			$original_page = self::page_display_name( (string) ( $page_context['original_page'] ?? 'homepage' ) );
			$missing[]     = array(
				'key'      => 'page_mismatch',
				'question' => sprintf(
					/* translators: 1: variant source page, 2: original page */
					__( 'You asked for variants of the %1$s, but said the original %2$s should be updated. Should the original page be the %1$s, or did you mean the %2$s?', 'reactwoo-geo-ai' ),
					$variant_page,
					$original_page
				),
			);
		}

		return array(
			'matched'          => true,
			'intent'           => 'create_geo_variant_plan',
			'matched_action'   => $matched_action,
			'confidence'       => $has_page_mismatch ? 0.72 : 0.88,
			'proposal_ready'   => $proposal_ready,
			'interpretation_status' => $has_page_mismatch ? 'needs_clarification' : '',
			'source_targeting' => $has_source ? $source : null,
			'variant_groups'   => $variants,
			'duplicate_count'  => $duplicate_count,
			'matched_terms'    => array_filter( array_map( static function ( $s ) {
				return (string) ( $s['marker'] ?? $s['raw'] ?? '' );
			}, $segments ) ),
			'params'           => array(
				'source_page_ref'     => $page_value,
				'variant_source_page' => (string) ( $page_context['variant_source'] ?? $page_value ),
				'original_page_ref'   => (string) ( $page_context['original_page'] ?? '' ),
				'page_context'        => $page_context,
				'total_version_count' => $total_version_count > 0 ? $total_version_count : ( $has_source ? 1 + count( $variant_out ) : count( $variant_out ) ),
				'duplicate_count'     => $duplicate_count,
				'source_targeting'    => $source_targeting_param,
				'variants'            => $variant_out,
			),
			'steps'            => $steps,
			'summary'          => $summary,
			'warnings'         => $debug['warnings'],
			'missing_information' => $missing,
			'page_context'     => $page_context,
			'_debug'           => $debug,
		);
	}

	/**
	 * @param string $page_ref Page ref slug.
	 * @return string
	 */
	private static function original_page_label( $page_ref ) {
		if ( 'shop' === $page_ref ) {
			return __( 'Original shop page', 'reactwoo-geocore' );
		}
		if ( 'checkout' === $page_ref ) {
			return __( 'Original checkout', 'reactwoo-geocore' );
		}
		return __( 'Original homepage', 'reactwoo-geocore' );
	}

	/**
	 * @param string $page_ref Page ref slug.
	 * @return string
	 */
	private static function page_display_name( $page_ref ) {
		if ( 'shop' === $page_ref ) {
			return __( 'shop page', 'reactwoo-geocore' );
		}
		if ( 'homepage' === $page_ref ) {
			return __( 'homepage', 'reactwoo-geocore' );
		}
		return $page_ref;
	}

	/**
	 * When a single variant segment contains "one … the other …", split into two variant rows.
	 *
	 * @param array<int,array<string,mixed>> $variants Variants.
	 * @param array<int,array>               $entities Entities.
	 * @return array<int,array<string,mixed>>
	 */
	private static function expand_one_other_variants( array $variants, array $entities ) {
		if ( 1 !== count( $variants ) ) {
			return $variants;
		}
		$pair = self::split_one_other_pair( (string) ( $variants[0]['raw'] ?? '' ), $entities );
		if ( count( $pair ) < 2 ) {
			return $variants;
		}
		return $pair;
	}

	/**
	 * @param string           $raw      Clause text.
	 * @param array<int,array> $entities Entities.
	 * @return array<int,array<string,mixed>>
	 */
	public static function split_one_other_pair( $raw, array $entities ) {
		$raw = trim( (string) $raw );
		$patterns = array(
			'/^(?:one(?:\s+(?:variant|variation|version))?\s+(?:should|can|will|would|needs\s+to)?\s*(?:display|show|fire|trigger|work|activate|appear|serve|render)?\s*(?:in|for)?\s*)(.+?)\s+and\s+the\s+other\s+(?:in|for|to)\s+(.+)$/i',
			'/^(?:one(?:\s+(?:variant|variation|version))?\s+(?:should|can|will|would)?\s*(?:display|show|fire|trigger|work)?\s*(?:in|for)?\s*)(.+?)\s+and\s+the\s+other\s+(.+)$/i',
		);
		foreach ( $patterns as $pattern ) {
			if ( ! preg_match( $pattern, $raw, $m ) ) {
				continue;
			}
			$first  = self::extract_country_list_from_segment( trim( $m[1] ), $entities );
			$second = self::extract_country_list_from_segment( trim( $m[2] ), $entities );
			if ( empty( $first ) || empty( $second ) ) {
				continue;
			}
			return array(
				array(
					'ordinal'   => 1,
					'raw'       => trim( $m[1] ),
					'marker'    => 'one should',
					'label'     => self::label_for_segment( $first, null, $entities ),
					'mode'      => 'include_only',
					'countries' => $first,
					'weather'   => null,
				),
				array(
					'ordinal'   => 2,
					'raw'       => trim( $m[2] ),
					'marker'    => 'the other in',
					'label'     => self::label_for_segment( $second, null, $entities ),
					'mode'      => 'include_only',
					'countries' => $second,
					'weather'   => null,
				),
			);
		}
		return array();
	}

	/**
	 * @param bool               $has_source      Whether source clause exists.
	 * @param string             $source_display  Source label.
	 * @param string|null        $source_weather  Source weather key.
	 * @param array<int,string>  $variant_labels  Variant labels.
	 * @param array<int,array>   $entities        Entities.
	 * @return string
	 */
	private static function build_plan_summary( $has_source, $source_display, $source_weather, array $variant_labels, array $entities ) {
		if ( $has_source ) {
			$source_part = $source_display;
			if ( null !== $source_weather && 'any' === $source_weather ) {
				$source_part .= ' ' . __( 'with all weather conditions', 'reactwoo-geocore' );
			}
			return sprintf(
				/* translators: 1: source targeting, 2: variant labels */
				__( 'Update the original homepage for %1$s visitors, then create variants for %2$s.', 'reactwoo-geocore' ),
				$source_part,
				implode( ' and ', array_filter( $variant_labels ) )
			);
		}
		return sprintf(
			/* translators: %s: variant labels */
			__( 'Create variants for %s.', 'reactwoo-geocore' ),
			implode( ' and ', array_filter( $variant_labels ) )
		);
	}

	/**
	 * @param array<int,string>  $countries Country codes.
	 * @param string|null        $weather   Weather key.
	 * @param array<int,array>   $entities  Entities.
	 * @return string
	 */
	private static function label_for_segment( array $countries, $weather, array $entities ) {
		$country_label = class_exists( 'RWGA_Variant_Group_Extractor', false )
			? RWGA_Variant_Group_Extractor::label_for_countries( $countries, $entities )
			: implode( ' + ', $countries );
		if ( null === $weather || '' === $weather ) {
			return $country_label;
		}
		$weather_label = class_exists( 'RWGA_Segment_Condition_Extractor', false )
			? RWGA_Segment_Condition_Extractor::weather_label( $weather, $entities )
			: (string) $weather;
		return $country_label . ' + ' . strtolower( $weather_label );
	}

	/**
	 * @param array<int,array<string,mixed>> $segments Segments.
	 * @param array<int,array>               $entities Entities.
	 * @return bool
	 */
	private static function has_mixed_conditions( array $segments, array $entities ) {
		foreach ( $segments as $segment ) {
			$raw = (string) ( $segment['raw'] ?? '' );
			if ( '' === $raw ) {
				continue;
			}
			$weather = class_exists( 'RWGA_Segment_Condition_Extractor', false )
				? RWGA_Segment_Condition_Extractor::extract_weather( $raw, $entities )
				: null;
			if ( null !== $weather ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string              $phrase Normalised phrase.
	 * @param array<string,mixed> $debug  Debug payload.
	 * @param array<string,mixed> $result Parse result.
	 * @return void
	 */
	private static function log_parse_result( $phrase, array $debug, array $result ) {
		if ( ! class_exists( 'RWGA_Interpreter_Debug', false ) || ! RWGA_Interpreter_Debug::is_enabled() ) {
			return;
		}
		$params = isset( $result['params'] ) && is_array( $result['params'] ) ? $result['params'] : array();
		RWGA_Interpreter_Debug::log(
			'variant_plan_parser',
			array(
				'normalised_input'         => $phrase,
				'parser_used'              => 'RWGA_Variant_Plan_Parser',
				'local_confidence'         => (float) ( $result['confidence'] ?? 0 ),
				'raw_clauses'              => $debug['raw_clauses'] ?? array(),
				'classified_clauses'       => $debug['segments'] ?? array(),
				'detected_source_clause'   => $debug['detected_source_clause'] ?? null,
				'detected_variant_clauses' => $debug['detected_variant_clauses'] ?? array(),
				'countries_per_clause'     => $debug['countries_per_clause'] ?? array(),
				'final_params'             => $params,
				'validation_status'        => ! empty( $result['matched'] ) ? 'matched' : (string) ( $result['reason'] ?? 'unmatched' ),
			)
		);
	}

	/**
	 * @param string              $phrase     Phrase.
	 * @param string              $page_value Page ref.
	 * @param array<string,mixed> $debug      Debug.
	 * @return array<string,mixed>
	 */
	private static function ambiguous_source_usage( $phrase, $page_value, array $debug ) {
		unset( $phrase );
		return array(
			'matched'             => true,
			'intent'              => 'create_geo_variant_plan',
			'matched_action'      => 'geocore_create_variant_plan_with_country_rules',
			'confidence'          => 0.65,
			'missing_information' => array(
				array(
					'key'      => 'source_usage',
					'question' => __( 'Should the original homepage become one of these versions, or should I create three new variants?', 'reactwoo-geocore' ),
				),
			),
			'suggested_options'   => array(
				__( 'Use original as the first version', 'reactwoo-geocore' ),
				__( 'Create three new variants', 'reactwoo-geocore' ),
			),
			'params'              => array(
				'source_page_ref' => $page_value,
			),
			'summary'             => __( 'I need to know whether to use the original page as one of the versions.', 'reactwoo-geocore' ),
			'_debug'              => $debug,
		);
	}

	/**
	 * @param string              $phrase     Phrase.
	 * @param string              $page_value Page ref.
	 * @param array<int,string>   $countries  Countries.
	 * @param array<string,mixed> $debug      Debug.
	 * @return array<string,mixed>
	 */
	private static function partial_clarification( $phrase, $page_value, array $countries, array $debug ) {
		$debug['reason'] = 'could_not_confidently_split_variant_conditions';
		$entities        = array();
		if ( class_exists( 'RWGA_Intelligence_Sync_Service', false ) ) {
			$bundle   = RWGA_Intelligence_Sync_Service::ensure_bundle();
			$entities = is_array( $bundle['entities'] ?? null ) ? $bundle['entities'] : array();
		}

		$page_context = is_array( $debug['page_context'] ?? null ) ? $debug['page_context'] : self::detect_page_context( $phrase );
		foreach ( (array) ( $debug['raw_clauses'] ?? array() ) as $clause ) {
			$pair = self::split_one_other_pair( (string) $clause, $entities );
			if ( count( $pair ) >= 2 ) {
				$debug['detected_variant_clauses'] = $pair;
				$inferred = class_exists( 'RWGA_Inferred_Plan_Builder', false )
					? RWGA_Inferred_Plan_Builder::from_debug( (string) $page_value, $debug, $entities )
					: null;
				if ( is_array( $inferred ) ) {
					return array(
						'matched'             => true,
						'intent'              => 'create_geo_variant_plan',
						'matched_action'      => 'geocore_create_variant_plan_with_country_rules',
						'confidence'          => 0.68,
						'proposal_ready'      => false,
						'escalate'            => true,
						'inferred_plan'       => $inferred,
						'missing_information' => array(
							array(
								'key'      => 'variant_grouping',
								'question' => __( 'Is this split correct?', 'reactwoo-geo-ai' ),
							),
						),
						'params'              => array(
							'source_page_ref' => $page_value,
							'page_context'    => $page_context,
						),
						'summary'             => __( 'I found a likely split, but need you to confirm it before creating anything.', 'reactwoo-geo-ai' ),
						'_debug'              => $debug,
					);
				}
			}
		}

		$inferred = class_exists( 'RWGA_Inferred_Plan_Builder', false )
			? RWGA_Inferred_Plan_Builder::from_debug( (string) $page_value, $debug, $entities )
			: null;

		if ( is_array( $inferred ) && ! empty( $inferred['variants'] ) && 1 === count( $inferred['variants'] ) ) {
			$all_codes = array();
			foreach ( (array) ( $inferred['variants'][0]['countries'] ?? array() ) as $code ) {
				$all_codes[] = (string) $code;
			}
			if ( count( $all_codes ) >= 3 ) {
				$inferred = null;
			}
		}

		if ( is_array( $inferred ) ) {
			$summary = __( 'I found a likely split, but need you to confirm it before creating anything.', 'reactwoo-geo-ai' );
			return array(
				'matched'             => true,
				'intent'              => 'create_geo_variant_plan',
				'matched_action'      => 'geocore_create_variant_plan_with_conditions',
				'confidence'          => 0.62,
				'proposal_ready'      => false,
				'escalate'            => true,
				'inferred_plan'       => $inferred,
				'missing_information' => array(
					array(
						'key'      => 'variant_grouping',
						'question' => __( 'Is this split correct?', 'reactwoo-geo-ai' ),
					),
				),
				'params'              => array(
					'source_page_ref' => $page_value,
					'countries'       => $countries,
				),
				'summary'             => $summary,
				'_debug'              => $debug,
			);
		}

		$country_names = class_exists( 'RWGA_Inferred_Plan_Builder', false )
			? RWGA_Inferred_Plan_Builder::countries_label( $countries, $entities )
			: implode( ', ', $countries );
		$summary       = sprintf(
			/* translators: %s: detected country list */
			__( 'I found %s, but I could not tell which conditions belong to which variant. Which split should I use?', 'reactwoo-geo-ai' ),
			$country_names
		);

		return array(
			'matched'             => true,
			'intent'              => 'create_geo_variant_plan',
			'matched_action'      => 'geocore_create_variant_plan_with_country_rules',
			'confidence'          => 0.48,
			'proposal_ready'      => false,
			'escalate'            => true,
			'missing_information' => array(
				array(
					'key'      => 'variant_grouping',
					'question' => $summary,
				),
			),
			'suggested_options'   => array(
				__( 'Original UK, Portugal rain, Russia + Germany sunny', 'reactwoo-geocore' ),
				__( 'Two variants only', 'reactwoo-geocore' ),
				__( 'One shared rule', 'reactwoo-geocore' ),
			),
			'params'              => array(
				'source_page_ref' => $page_value,
				'countries'       => $countries,
			),
			'summary'             => $summary,
			'_debug'              => $debug,
		);
	}
}
