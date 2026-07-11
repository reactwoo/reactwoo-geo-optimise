<?php
/**
 * Resolve per-clause targeting conditions (location, device, weather, audience)
 * with include/exclude polarity. Audiences resolve against synced data only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Condition_Resolver {

	/**
	 * @param string              $clause   Clause text (or sub-segment).
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $context  Planner context (synced registries).
	 * @return array{conditions:array,warnings:array,confidence:float,location_labels:array,unresolved:array}
	 */
	public static function resolve( $clause, array $entities, array $context = array() ) {
		$clause  = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$split   = RWGA_Planner_Condition_Polarity_Resolver::split_text( $clause );
		$include = self::resolve_group( self::strip_rule_preamble( (string) $split['include_text'] ), $entities, $context );
		$exclude = '' !== (string) $split['exclude_text']
			? self::resolve_group( (string) $split['exclude_text'], $entities, $context )
			: self::empty_group();

		self::merge_grouped_audiences_from_clause( $clause, $include, $entities, $context );
		self::apply_trigger_or_group( (string) $split['include_text'], $include, $context );

		$conditions = array(
			'include' => self::group_conditions( $include ),
			'exclude' => self::group_conditions( $exclude ),
		);

		$warnings   = array_values( array_unique( array_merge( $include['warnings'], $exclude['warnings'] ) ) );
		$unresolved = array(
			'audiences'       => self::dedupe_unresolved_audiences(
				array_merge(
					(array) ( $include['unresolved_audiences'] ?? array() ),
					(array) ( $exclude['unresolved_audiences'] ?? array() )
				)
			),
			'locations'       => array_merge(
				(array) ( $include['unresolved_locations'] ?? array() ),
				(array) ( $exclude['unresolved_locations'] ?? array() )
			),
			'traffic_sources' => (array) ( $include['unresolved_traffic_sources'] ?? array() ),
		);

		$confidence = 0.7;
		if ( ! empty( $include['countries'] ) || ! empty( $include['regions'] ) || ! empty( $exclude['countries'] ) ) {
			$confidence = 0.88;
		}
		if ( ! empty( $include['devices'] ) || ! empty( $exclude['devices'] ) ) {
			$confidence = min( 0.95, $confidence + 0.04 );
		}
		if ( ! empty( $include['urls'] ) ) {
			$confidence = min( 0.96, $confidence + 0.03 );
		}

		return array(
			'conditions'      => $conditions,
			'warnings'        => $warnings,
			'location_labels' => $include['labels'],
			'confidence'      => $confidence,
			'unresolved'      => $unresolved,
		);
	}

	/**
	 * @param array<string,mixed> $group Group row.
	 * @return array<string,mixed>
	 */
	private static function group_conditions( array $group ) {
		return array(
			'countries'        => $group['countries'],
			'regions'          => $group['regions'],
			'devices'          => $group['devices'],
			'weather'          => $group['weather'],
			'urls'             => $group['urls'],
			'utm'              => $group['utm'],
			'campaigns'        => $group['campaigns'],
			'audiences'        => $group['audiences'],
			'visitorStates'    => $group['visitorStates'],
			'pageTypes'        => $group['pageTypes'],
			'condition_groups' => $group['condition_groups'],
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function empty_group() {
		return array(
			'countries'                 => array(),
			'regions'                   => array(),
			'devices'                   => array(),
			'weather'                   => array(),
			'urls'                      => array(),
			'utm'                       => array(),
			'campaigns'                 => array(),
			'audiences'                 => array(),
			'visitorStates'             => array(),
			'pageTypes'                 => array(),
			'condition_groups'          => array(),
			'unresolved_audiences'      => array(),
			'unresolved_traffic_sources' => array(),
			'warnings'                  => array(),
			'labels'                    => array(),
		);
	}

	/**
	 * @param string              $text     Text segment.
	 * @param array<int,array>    $entities Entities.
	 * @param array<string,mixed> $context  Context.
	 * @return array<string,mixed>
	 */
	private static function resolve_group( $text, array $entities, array $context = array() ) {
		$text     = RWGA_Local_Intent_Interpreter::normalise( $text );
		$location = RWGA_Planner_Location_Resolver::resolve_from_text( $text, $entities );
		$devices  = self::detect_devices( $text );
		$weather  = class_exists( 'RWGA_Segment_Condition_Extractor', false )
			? RWGA_Segment_Condition_Extractor::extract_weather( $text, $entities )
			: null;
		$urls = class_exists( 'RWGA_Planner_Url_Condition_Resolver', false )
			? RWGA_Planner_Url_Condition_Resolver::extract_paths( $text )
			: array();
		$utm = class_exists( 'RWGA_Planner_Utm_Condition_Resolver', false )
			? RWGA_Planner_Utm_Condition_Resolver::extract( $text )
			: array();
		$audience = class_exists( 'RWGA_Planner_Audience_Resolver', false )
			? RWGA_Planner_Audience_Resolver::resolve( $text, $entities, $context )
			: array( 'audiences' => array(), 'visitorStates' => array(), 'unresolved' => array() );
		$weather_values = self::detect_weather_values( $text, $entities, $weather );
		$page_types     = self::detect_page_types( $text );

		$regions              = (array) $location['regions'];
		$unresolved_locations = array();
		if ( ! empty( $context['location_clarification'] )
			&& class_exists( 'RWGA_Planner_Region_Ambiguity_Resolver', false ) ) {
			$kept = array();
			foreach ( $regions as $region ) {
				$ambiguity = RWGA_Planner_Region_Ambiguity_Resolver::ambiguity_for_region( (string) $region );
				if ( null === $ambiguity ) {
					$kept[] = $region;
					continue;
				}
				$unresolved_locations[] = $ambiguity;
			}
			$regions = $kept;
		}

		return array(
			'countries'                 => $location['countries'],
			'regions'                   => $regions,
			'devices'                   => $devices,
			'weather'                   => $weather_values,
			'urls'                      => $urls,
			'utm'                       => $utm,
			'campaigns'                 => array(),
			'audiences'                 => (array) ( $audience['audiences'] ?? array() ),
			'visitorStates'             => (array) ( $audience['visitorStates'] ?? array() ),
			'pageTypes'                 => $page_types,
			'condition_groups'          => array(),
			'unresolved_audiences'      => (array) ( $audience['unresolved'] ?? array() ),
			'unresolved_traffic_sources' => array(),
			'unresolved_locations'      => $unresolved_locations,
			'warnings'                  => $location['warnings'],
			'labels'                    => $location['labels'],
		);
	}

	/**
	 * @param string $text Include text (may still contain rule preamble).
	 * @return string
	 */
	private static function strip_rule_preamble( $text ) {
		$text = RWGA_Local_Intent_Interpreter::normalise( (string) $text );
		if ( preg_match( '/\b(?:show|display|only\s+trigger|only\s+show)\b/i', $text ) ) {
			$text = (string) preg_replace(
				'/^(?:create|add|set\s+up|build)\s+(?:a\s+)?(?:targeting\s+)?rule\s+(?:for\s+)?(?:the\s+)?[^.]+?\.\s*/i',
				'',
				$text
			);
		}
		return trim( $text );
	}

	/**
	 * Build an OR group when Google Ads traffic and URL path triggers are paired.
	 *
	 * @param string              $include_text Original include phrase.
	 * @param array<string,mixed> $include      Include group (by ref).
	 * @param array<string,mixed> $context      Planner context.
	 * @return void
	 */
	private static function apply_trigger_or_group( $include_text, array &$include, array $context = array() ) {
		unset( $context );
		$include_text = RWGA_Local_Intent_Interpreter::normalise( (string) $include_text );
		$has_google   = (bool) preg_match( '/\bgoogle\s+ads\b/i', $include_text );
		$urls         = (array) ( $include['urls'] ?? array() );
		$paired_or    = $has_google && ! empty( $urls )
			&& preg_match( '/\b(?:google\s+ads|from\s+google\s+ads)\s+or\b/i', $include_text );

		if ( ! $paired_or ) {
			if ( $has_google && class_exists( 'RWGA_Planner_Utm_Condition_Resolver', false ) ) {
				$google = RWGA_Planner_Utm_Condition_Resolver::extract_google_ads( $include_text );
				if ( is_array( $google ) ) {
					$include['unresolved_traffic_sources'][] = $google;
				}
			}
			return;
		}

		$conditions = array();
		if ( class_exists( 'RWGA_Planner_Utm_Condition_Resolver', false ) ) {
			$google = RWGA_Planner_Utm_Condition_Resolver::extract_google_ads( $include_text );
			if ( is_array( $google ) ) {
				$conditions[] = array(
					'type'               => 'traffic_source',
					'value'              => 'google_ads',
					'status'             => (string) ( $google['status'] ?? 'needs_mapping' ),
					'label'              => (string) ( $google['label'] ?? __( 'Google Ads traffic', 'reactwoo-geocore' ) ),
					'warning'            => (string) ( $google['warning'] ?? '' ),
					'resolution_options' => (array) ( $google['resolution_options'] ?? array() ),
				);
				$include['unresolved_traffic_sources'][] = $google;
			}
		}
		foreach ( $urls as $url ) {
			$path = (string) $url;
			$conditions[] = array(
				'type'     => 'url',
				'operator' => 'contains',
				'value'    => $path,
				'status'   => 'valid',
				'label'    => sprintf(
					/* translators: %s: URL path fragment */
					__( 'URL contains %s', 'reactwoo-geocore' ),
					$path
				),
			);
		}

		if ( count( $conditions ) < 2 ) {
			return;
		}

		$label = __( 'Google Ads or URL contains /winter-sale', 'reactwoo-geocore' );
		if ( ! empty( $urls[0] ) ) {
			$label = sprintf(
				/* translators: %s: URL path fragment */
				__( 'Google Ads or URL contains %s', 'reactwoo-geocore' ),
				(string) $urls[0]
			);
		}

		$include['condition_groups'][] = array(
			'type'       => 'condition_group',
			'label'      => $label,
			'logic'      => 'OR',
			'group'      => 'traffic_or_url_trigger',
			'conditions' => $conditions,
		);
		$include['urls'] = array();
	}

	/**
	 * Ensure grouped audience phrases on the full clause survive include-only stripping.
	 *
	 * @param string              $clause   Full clause text.
	 * @param array<string,mixed> $include  Include group (by ref).
	 * @param array<int,array>    $entities Entities.
	 * @param array<string,mixed> $context  Planner context.
	 * @return void
	 */
	private static function merge_grouped_audiences_from_clause( $clause, array &$include, array $entities, array $context = array() ) {
		if ( ! class_exists( 'RWGA_Planner_Audience_Resolver', false ) ) {
			return;
		}
		$audience = RWGA_Planner_Audience_Resolver::resolve( (string) $clause, $entities, $context );
		foreach ( (array) ( $audience['unresolved'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) || 'matches_any' !== (string) ( $row['status'] ?? '' ) ) {
				continue;
			}
			$include['unresolved_audiences'] = self::dedupe_unresolved_audiences(
				array_merge( (array) ( $include['unresolved_audiences'] ?? array() ), array( $row ) )
			);
		}
	}

	/**
	 * @param array<int,array<string,mixed>> $rows Unresolved audience rows.
	 * @return array<int,array<string,mixed>>
	 */
	private static function dedupe_unresolved_audiences( array $rows ) {
		$out  = array();
		$seen = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( 'matches_any' === (string) ( $row['status'] ?? '' ) ) {
				$segment_key = implode( ',', (array) ( $row['segment_keys'] ?? array() ) );
				if ( isset( $seen[ 'matches_any:' . $segment_key ] ) ) {
					continue;
				}
				$seen[ 'matches_any:' . $segment_key ] = true;
				$out[] = $row;
				continue;
			}
			$key = strtolower( (string) ( $row['status'] ?? '' ) ) . '|' . strtolower( (string) ( $row['raw'] ?? '' ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $row;
		}
		return $out;
	}

	/**
	 * @param string $text Text segment.
	 * @return array<int,string>
	 */
	private static function detect_page_types( $text ) {
		$types = array();
		if ( preg_match( '/\bproduct\s+pages?\b/i', $text ) ) {
			$types[] = 'product';
		}
		if ( preg_match( '/\bcategory\s+pages?\b/i', $text ) ) {
			$types[] = 'category';
		}
		if ( preg_match( '/\b(?:home\s+page|homepage)\b/i', $text ) ) {
			$types[] = 'homepage';
		}
		return array_values( array_unique( $types ) );
	}

	/**
	 * @param string                $text        Text.
	 * @param array<int,array>      $entities    Entities.
	 * @param string|null           $weather_key Extracted weather key.
	 * @return array<int,string>
	 */
	private static function detect_weather_values( $text, array $entities, $weather_key ) {
		if ( preg_match( '/\brainy\b/i', $text ) ) {
			return array( 'rainy' );
		}
		if ( null !== $weather_key && '' !== $weather_key ) {
			if ( 'rain' === $weather_key && preg_match( '/\brainy\b/i', $text ) ) {
				return array( 'rainy' );
			}
			return array( (string) $weather_key );
		}
		return array();
	}

	/**
	 * @param string $clause Clause.
	 * @return array<int,string>
	 */
	private static function detect_devices( $clause ) {
		$devices = array();
		if ( preg_match( '/\bmobile\b/i', $clause ) ) {
			$devices[] = 'mobile';
		}
		if ( preg_match( '/\bdesktop\b/i', $clause ) ) {
			$devices[] = 'desktop';
		}
		if ( preg_match( '/\btablet\b/i', $clause ) ) {
			$devices[] = 'tablet';
		}
		return array_values( array_unique( $devices ) );
	}
}
