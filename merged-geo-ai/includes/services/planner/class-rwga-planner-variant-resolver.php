<?php
/**
 * Expand variant pair phrases into separate create_variant actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Variant_Resolver {

	/**
	 * Build variant actions from a clause (handles one/other pairs).
	 *
	 * @param string              $clause       Clause text.
	 * @param array{type:string,label:string,slug:string,source:string} $target Target.
	 * @param array<int,array>    $entities     Entities.
	 * @param array{type:string,visibility:string,mode:string,confidence:float} $type_row Type row.
	 * @return array<int,array<string,mixed>>
	 */
	public static function expand_from_clause( $clause, array $target, array $entities, array $type_row ) {
		if ( ! class_exists( 'RWGA_Variant_Plan_Parser', false ) ) {
			return array();
		}

		$pair = self::extract_pair_from_clause( $clause, $entities );
		if ( count( $pair ) >= 2 ) {
			return self::pair_to_actions( $pair, $target, $type_row, $entities );
		}

		$cond    = RWGA_Planner_Condition_Resolver::resolve( $clause, $entities );
		$include = RWGA_Planner_Condition_Polarity_Resolver::include_group( $cond['conditions'] ?? array() );
		if ( empty( $include['countries'] ) && empty( $include['regions'] ) ) {
			return array();
		}

		return array(
			self::make_action(
				$type_row,
				$target,
				1,
				$cond,
				$clause
			),
		);
	}

	/**
	 * Convert variant plan parser output into planner actions.
	 *
	 * @param array<string,mixed> $plan     Parser result.
	 * @param array<int,array>    $entities Entities.
	 * @return array<int,array<string,mixed>>
	 */
	public static function from_variant_plan_parse( array $plan, array $entities ) {
		if ( empty( $plan['matched'] ) ) {
			return array();
		}

		$params       = is_array( $plan['params'] ?? null ) ? $plan['params'] : array();
		$page_ref     = (string) ( $params['source_page_ref'] ?? 'homepage' );
		$variant_page = (string) ( $params['variant_source_page'] ?? $page_ref );
		$original_ref = (string) ( $params['original_page_ref'] ?? $params['page_context']['original_page'] ?? $page_ref );
		$target       = array(
			'type'   => 'page',
			'label'  => $variant_page,
			'slug'   => $variant_page,
			'source' => 'detected',
		);
		$type_row = array(
			'type'       => RWGA_Geo_Action_Types::CREATE_VARIANT,
			'visibility' => 'show',
			'mode'       => 'create',
			'confidence' => 0.88,
		);

		$actions = array();
		$source  = $params['source_targeting'] ?? null;
		if ( is_array( $source ) && ! empty( $source['countries'] ) ) {
			$original_target = array(
				'type'   => 'page',
				'label'  => $original_ref,
				'slug'   => $original_ref,
				'source' => 'detected',
			);
			if ( '' === $original_target['label'] || 'homepage' === $original_target['slug'] ) {
				$original_target['label'] = 'homepage';
				$original_target['slug']  = 'homepage';
			}
			$regions   = array();
			$countries = (array) $source['countries'];
			foreach ( $countries as $code ) {
				if ( 'GB' === $code && preg_match( '/\bengland\b/i', (string) ( $source['raw'] ?? '' ) ) ) {
					$regions[] = 'GB-ENG';
				}
			}
			if ( ! empty( $regions ) ) {
				$countries = array_values( array_diff( $countries, array( 'GB' ) ) );
			}
			$source_weather = $source['weather'] ?? null;
			$actions[]      = self::make_action(
				array(
					'type'       => RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING,
					'visibility' => 'only_show',
					'mode'       => 'update',
					'confidence' => 0.88,
				),
				$original_target,
				null,
				array(
					'conditions' => array(
						'countries' => $countries,
						'regions'   => $regions,
						'devices'   => array(),
						'weather'   => self::weather_values( $source_weather ),
						'campaigns' => array(),
						'urls'      => array(),
						'audiences' => array(),
					),
					'warnings'   => array(),
					'confidence' => 0.88,
				),
				(string) ( $source['raw'] ?? '' ),
				'original',
				self::build_variant_label( $countries, $source_weather, $entities, true, $original_ref ),
				self::weather_notes( $source_weather ),
				true
			);
		}

		foreach ( (array) ( $params['variants'] ?? array() ) as $idx => $variant ) {
			if ( ! is_array( $variant ) ) {
				continue;
			}
			$countries      = (array) ( $variant['countries'] ?? array() );
			$variant_weather = $variant['weather'] ?? null;
			$actions[]      = self::make_action(
				$type_row,
				$target,
				$idx + 1,
				array(
					'conditions' => array(
						'countries' => $countries,
						'regions'   => array(),
						'devices'   => array(),
						'weather'   => self::weather_values( $variant_weather ),
						'campaigns' => array(),
						'urls'      => array(),
						'audiences' => array(),
					),
					'warnings'   => array(),
					'confidence' => 0.88,
				),
				(string) ( $variant['raw'] ?? '' ),
				'variant',
				self::build_variant_label( $countries, $variant_weather, $entities, false, $variant_page ),
				array(),
				true
			);
		}

		return $actions;
	}

	/**
	 * @param mixed $weather Weather key or param array.
	 * @return array<int,string>
	 */
	private static function weather_values( $weather ) {
		$key = self::normalize_weather_key( $weather );
		if ( null === $key || '' === $key || 'any' === $key ) {
			return array();
		}
		return array( $key );
	}

	/**
	 * @param mixed $weather Weather key or param array.
	 * @return array<int,string>
	 */
	private static function weather_notes( $weather ) {
		$key = self::normalize_weather_key( $weather );
		if ( null === $key || '' === $key || 'any' === $key ) {
			return array( 'no_weather_restriction' );
		}
		return array();
	}

	/**
	 * @param mixed $weather Weather value from parser.
	 * @return string|null
	 */
	private static function normalize_weather_key( $weather ) {
		if ( is_array( $weather ) ) {
			if ( isset( $weather['mode'] ) && 'any' === $weather['mode'] ) {
				return 'any';
			}
			if ( isset( $weather['condition'] ) && '' !== (string) $weather['condition'] ) {
				return (string) $weather['condition'];
			}
			return null;
		}
		if ( null === $weather || '' === $weather ) {
			return null;
		}
		return (string) $weather;
	}

	/**
	 * @param mixed               $weather   Weather key.
	 * @param array<int,array>    $entities  Entities.
	 * @return string
	 */
	private static function weather_label_text( $weather, array $entities ) {
		$key = self::normalize_weather_key( $weather );
		if ( null === $key || '' === $key || 'any' === $key ) {
			return '';
		}
		return class_exists( 'RWGA_Segment_Condition_Extractor', false )
			? strtolower( (string) RWGA_Segment_Condition_Extractor::weather_label( $key, $entities ) )
			: strtolower( $key );
	}

	/**
	 * @param array<int,string> $countries  Country codes.
	 * @param string|null       $weather    Weather key.
	 * @param array<int,array>  $entities   Entities.
	 * @param bool              $is_original Original targeting action.
	 * @param string            $page_ref   Page reference label.
	 * @return string
	 */
	private static function build_variant_label( array $countries, $weather, array $entities, $is_original, $page_ref ) {
		if ( $is_original ) {
			return sprintf(
				/* translators: %s: page reference such as homepage */
				__( 'Update original %s targeting', 'reactwoo-geocore' ),
				$page_ref
			);
		}
		$country_label = RWGA_Planner_Location_Resolver::display_label(
			array(
				'countries' => $countries,
				'regions'   => array(),
				'labels'    => array(),
			)
		);
		$weather_part = self::weather_label_text( $weather, $entities );
		if ( '' === $country_label ) {
			$country_label = implode( ' + ', $countries );
		}
		return 'Create ' . $country_label . ( '' !== $weather_part ? ' ' . $weather_part : '' ) . ' variant';
	}

	/**
	 * @param string           $clause   Clause text.
	 * @param array<int,array> $entities Entities.
	 * @return array<int,array<string,mixed>>
	 */
	private static function extract_pair_from_clause( $clause, array $entities ) {
		if ( ! class_exists( 'RWGA_Variant_Plan_Parser', false ) ) {
			return array();
		}
		$pair = RWGA_Variant_Plan_Parser::split_one_other_pair( $clause, $entities );
		if ( count( $pair ) >= 2 ) {
			return $pair;
		}
		if ( preg_match( '/\bone\s+variant\s+should\s+(?:display|show)\s+in\s+(.+?)\s+and\s+the\s+other\s+in\s+(.+)$/i', $clause, $m ) ) {
			$first  = RWGA_Variant_Plan_Parser::extract_country_list_from_segment( trim( (string) $m[1] ), $entities );
			$second = RWGA_Variant_Plan_Parser::extract_country_list_from_segment( trim( (string) $m[2] ), $entities );
			if ( ! empty( $first ) && ! empty( $second ) ) {
				return array(
					array(
						'ordinal'   => 1,
						'raw'       => trim( (string) $m[1] ),
						'countries' => $first,
						'label'     => RWGA_Planner_Location_Resolver::display_label(
							array( 'countries' => $first, 'regions' => array(), 'labels' => array() )
						),
					),
					array(
						'ordinal'   => 2,
						'raw'       => trim( (string) $m[2] ),
						'countries' => $second,
						'label'     => RWGA_Planner_Location_Resolver::display_label(
							array( 'countries' => $second, 'regions' => array(), 'labels' => array() )
						),
					),
				);
			}
		}
		$patterns = array(
			'/\bone\s+(?:variant|variation|version)?\s*(?:should|will|would|can).+?\b(?:and\s+)?(?:the\s+other|another)\s+(?:variant|variation|version)?\s*(?:should|will|would|can).+$/i',
			'/\bone\s+will\b.+?\bone\s+will\b.+$/i',
			'/\bone\s+should\b.+?\bthe\s+other\b.+$/i',
		);
		foreach ( $patterns as $pattern ) {
			if ( ! preg_match( $pattern, $clause, $m ) ) {
				continue;
			}
			$pair = RWGA_Variant_Plan_Parser::split_one_other_pair( trim( (string) $m[0] ), $entities );
			if ( count( $pair ) >= 2 ) {
				return $pair;
			}
		}
		if ( preg_match( '/\bone\s+will\s+(?:display|show)\s+(?:in|for)\s+(.+?)\s+and\s+one\s+will\s+(?:display|show)\s+(?:in|for)\s+(.+)$/i', $clause, $m ) ) {
			$first  = RWGA_Variant_Plan_Parser::extract_country_list_from_segment( trim( (string) $m[1] ), $entities );
			$second = RWGA_Variant_Plan_Parser::extract_country_list_from_segment( trim( (string) $m[2] ), $entities );
			if ( ! empty( $first ) && ! empty( $second ) ) {
				return array(
					array(
						'ordinal'   => 1,
						'raw'       => trim( (string) $m[1] ),
						'countries' => $first,
						'label'     => RWGA_Planner_Location_Resolver::display_label(
							array( 'countries' => $first, 'regions' => array(), 'labels' => array() )
						),
					),
					array(
						'ordinal'   => 2,
						'raw'       => trim( (string) $m[2] ),
						'countries' => $second,
						'label'     => RWGA_Planner_Location_Resolver::display_label(
							array( 'countries' => $second, 'regions' => array(), 'labels' => array() )
						),
					),
				);
			}
		}
		if ( preg_match( '/\s+-\s+(.+)$/i', $clause, $m ) ) {
			return RWGA_Variant_Plan_Parser::split_one_other_pair( trim( (string) $m[1] ), $entities );
		}
		return array();
	}

	/**
	 * @param array<int,array<string,mixed>> $pair     Pair rows.
	 * @param array<string,mixed>            $target   Target.
	 * @param array<string,mixed>            $type_row Type row.
	 * @return array<int,array<string,mixed>>
	 */
	private static function pair_to_actions( array $pair, array $target, array $type_row, array $entities ) {
		$actions = array();
		foreach ( $pair as $idx => $row ) {
			$raw       = (string) ( $row['raw'] ?? '' );
			$child_raw = (string) ( $row['child_clause'] ?? $raw );
			$cond    = RWGA_Planner_Condition_Resolver::resolve( $child_raw, $entities );
			$visibility = preg_match( '/\b(?:only|just)\s+(?:show|display)\b|\bshow\s+only\b|\bonly\s+show\b/i', $child_raw )
				? 'only_show'
				: (string) $type_row['visibility'];
			$type_row['visibility'] = $visibility;
			$actions[] = self::make_action(
				$type_row,
				$target,
				$idx + 1,
				array(
					'conditions'      => $cond['conditions'],
					'location_labels' => $cond['location_labels'] ?? array(),
					'warnings'        => $cond['warnings'] ?? array(),
					'confidence'      => (float) ( $cond['confidence'] ?? 0.9 ),
				),
				$child_raw,
				'variant',
				(string) ( $row['label'] ?? '' )
			);
		}
		return $actions;
	}

	/**
	 * @param array<string,mixed>        $type_row     Type row.
	 * @param array<string,mixed>        $target       Target.
	 * @param int|null                   $index        Variant index.
	 * @param array<string,mixed>        $cond         Condition bundle.
	 * @param string                     $clause       Source clause.
	 * @param string                     $relationship original|variant|other.
	 * @param string                     $label        Optional variant label.
	 * @param array<int,string|mixed>    $notes        Optional note tokens.
	 * @param bool                       $shared_target Uses shared target resolver.
	 * @return array<string,mixed>
	 */
	private static function make_action( array $type_row, array $target, $index, array $cond, $clause, $relationship = 'variant', $label = '', array $notes = array(), $shared_target = false ) {
		$page_label = (string) ( $target['label'] ?? 'page' );
		if ( '' === $label ) {
			$include   = RWGA_Planner_Condition_Polarity_Resolver::include_group( $cond['conditions'] ?? array() );
			$loc_label = RWGA_Planner_Location_Resolver::display_label(
				array(
					'countries' => $include['countries'] ?? array(),
					'regions'   => $include['regions'] ?? array(),
					'labels'    => array(),
				)
			);
			$label = ucfirst( $page_label ) . ( $loc_label ? ' - ' . $loc_label : '' );
		}

		$action = array(
			'id'                  => RWGA_Geo_Assistant_Planner::new_id(),
			'type'                => (string) $type_row['type'],
			'label'               => $label,
			'target'              => $target,
			'variant'             => array(
				'index'         => $index,
				'label'         => $label,
				'sourcePage'    => $page_label,
				'relationship'  => $relationship,
			),
			'conditions'          => $cond['conditions'],
			'location_labels'     => $cond['location_labels'] ?? array(),
			'operation'           => array(
				'visibility' => (string) $type_row['visibility'],
				'mode'       => (string) $type_row['mode'],
			),
			'confidence'          => (float) ( $cond['confidence'] ?? $type_row['confidence'] ?? 0.8 ),
			'needsClarification'  => false,
			'clarificationReason' => null,
			'sourceClause'        => $clause,
		);
		if ( ! empty( $notes ) ) {
			$action['notes'] = $notes;
		}
		if ( $shared_target ) {
			$action['uses_shared_target'] = true;
		}
		return $action;
	}
}
