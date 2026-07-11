<?php
/**
 * Convert a planner action's include/exclude groups into a portable Geo Core
 * targeting rule set (conditions + visibility mode).
 *
 * Include conditions become positive operators ("in" / "is"); exclude
 * conditions become their negations ("not_in" / "is_not"). Unsupported inputs
 * (regions, raw URLs, custom visitor states) are reported as warnings so the
 * executor can surface a "manual step needed" instead of silently dropping
 * intent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Plan_Condition_Converter {

	/**
	 * @param array<string,mixed> $conditions Action conditions (include/exclude groups).
	 * @param array<string,mixed> $operation  Action operation (mode/visibility).
	 * @return array{conditions:array<int,array<string,mixed>>,mode:string,warnings:array<int,string>}
	 */
	public static function convert( array $conditions, array $operation = array() ) {
		$include = class_exists( 'RWGA_Planner_Condition_Polarity_Resolver', false )
			? RWGA_Planner_Condition_Polarity_Resolver::include_group( $conditions )
			: ( is_array( $conditions['include'] ?? null ) ? $conditions['include'] : $conditions );
		$exclude = class_exists( 'RWGA_Planner_Condition_Polarity_Resolver', false )
			? RWGA_Planner_Condition_Polarity_Resolver::exclude_group( $conditions )
			: ( is_array( $conditions['exclude'] ?? null ) ? $conditions['exclude'] : array() );

		$rows     = array();
		$warnings = array();

		$group_rows = self::convert_condition_groups( $include, false, $warnings );
		$skip_flat_utm = ! empty( $group_rows );

		self::convert_group( $include, false, $rows, $warnings, $skip_flat_utm );
		self::convert_group( $exclude, true, $rows, $warnings, false );
		$rows = array_merge( $rows, $group_rows );

		return array(
			'conditions' => $rows,
			'mode'       => self::mode_from_operation( $operation ),
			'warnings'   => array_values( array_unique( $warnings ) ),
		);
	}

	/**
	 * @param array<string,mixed>            $group         Condition group.
	 * @param bool                           $negate        Whether this is the exclude group.
	 * @param array<int,array<string,mixed>> $rows          Output rows (by reference).
	 * @param array<int,string>              $warnings      Warnings (by reference).
	 * @param bool                           $skip_flat_utm Skip top-level UTM when OR groups exist.
	 * @return void
	 */
	private static function convert_group( array $group, $negate, array &$rows, array &$warnings, $skip_flat_utm = false ) {
		$countries = self::clean_list( $group['countries'] ?? array() );
		if ( ! empty( $countries ) ) {
			$rows[] = array(
				'type'     => 'country',
				'operator' => $negate ? 'not_in' : 'in',
				'value'    => array_map( 'strtoupper', $countries ),
			);
		}

		$devices = self::clean_list( $group['devices'] ?? array() );
		if ( ! empty( $devices ) ) {
			$rows[] = array(
				'type'     => 'device_type',
				'operator' => $negate ? 'not_in' : 'in',
				'value'    => array_map( 'strtolower', $devices ),
			);
		}

		$page_types = self::clean_list( $group['pageTypes'] ?? array() );
		if ( ! empty( $page_types ) ) {
			$rows[] = array(
				'type'     => 'page_type',
				'operator' => $negate ? 'not_in' : 'in',
				'value'    => array_map( 'strtolower', $page_types ),
			);
		}

		if ( ! $skip_flat_utm ) {
			foreach ( (array) ( $group['utm'] ?? array() ) as $utm ) {
				if ( ! is_array( $utm ) || empty( $utm['key'] ) ) {
					continue;
				}
				$type = strtolower( (string) $utm['key'] );
				if ( ! in_array( $type, array( 'utm_source', 'utm_medium', 'utm_campaign' ), true ) ) {
					$warnings[] = sprintf( 'Unsupported UTM parameter: %s', $type );
					continue;
				}
				$rows[] = array(
					'type'     => $type,
					'operator' => $negate ? 'is_not' : 'is',
					'value'    => array( (string) ( $utm['value'] ?? '' ) ),
				);
			}
		}

		$audiences = self::audience_names( $group['audiences'] ?? array() );
		if ( ! empty( $audiences ) ) {
			$rows[] = array(
				'type'     => 'audience',
				'operator' => $negate ? 'not_in' : 'in',
				'value'    => $audiences,
			);
		}

		$weather = self::clean_list( $group['weather'] ?? array() );
		if ( ! empty( $weather ) ) {
			$rows[] = array(
				'type'     => 'weather_facet',
				'operator' => $negate ? 'not_in' : 'in',
				'value'    => array_map( 'strtolower', $weather ),
			);
		}

		foreach ( self::clean_list( $group['visitorStates'] ?? array() ) as $state ) {
			$state = strtolower( $state );
			if ( 'logged_in' === $state ) {
				$rows[] = array( 'type' => 'logged_in', 'operator' => $negate ? 'is_not' : 'is', 'value' => true );
			} elseif ( 'logged_out' === $state ) {
				$rows[] = array( 'type' => 'logged_in', 'operator' => $negate ? 'is' : 'is_not', 'value' => true );
			} else {
				$warnings[] = sprintf( 'Visitor state not supported in rules: %s', $state );
			}
		}

		if ( ! empty( $group['regions'] ) ) {
			$warnings[] = 'Region targeting is not available in portable rules — set it manually.';
		}
		if ( ! $skip_flat_utm && ! empty( $group['urls'] ) ) {
			$warnings[] = 'URL/path targeting must be configured manually.';
		}
	}

	/**
	 * @param array<string,mixed> $include  Include group.
	 * @param bool                $negate   Negate operators (unused for groups today).
	 * @param array<int,string>   $warnings Warnings.
	 * @return array<int,array<string,mixed>>
	 */
	private static function convert_condition_groups( array $include, $negate, array &$warnings ) {
		unset( $negate );
		$out    = array();
		$groups = (array) ( $include['condition_groups'] ?? array() );
		foreach ( $groups as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			$branches = array();
			foreach ( (array) ( $group['conditions'] ?? array() ) as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				$branch = self::convert_group_child_branch( $child, $warnings );
				if ( null !== $branch ) {
					$branches[] = $branch;
				}
			}
			if ( count( $branches ) < 2 ) {
				if ( 1 === count( $branches ) ) {
					foreach ( (array) ( $branches[0]['conditions'] ?? array() ) as $cond ) {
						$out[] = $cond;
					}
				}
				continue;
			}
			$logic = strtoupper( (string) ( $group['logic'] ?? 'OR' ) );
			$out[] = array(
				'type'     => 'condition_group',
				'operator' => 'match',
				'value'    => array(
					'match'    => 'OR' === $logic ? 'any' : 'all',
					'label'    => (string) ( $group['label'] ?? '' ),
					'branches' => $branches,
				),
			);
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $child    Planner child condition.
	 * @param array<int,string>   $warnings Warnings.
	 * @return array<string,mixed>|null
	 */
	private static function convert_group_child_branch( array $child, array &$warnings ) {
		$type = (string) ( $child['type'] ?? '' );
		if ( 'traffic_source' === $type ) {
			$mapping = (string) ( $child['mapping_key'] ?? 'utm_source_google_and_medium_cpc' );
			$conds   = self::portable_conditions_from_traffic_mapping( $mapping );
			if ( empty( $conds ) ) {
				$warnings[] = 'Google Ads traffic mapping could not be converted.';
				return null;
			}
			return array(
				'label'      => (string) ( $child['label'] ?? __( 'Google Ads standard UTM', 'reactwoo-geo-ai' ) ),
				'match'      => 'all',
				'conditions' => $conds,
			);
		}
		if ( 'url' === $type || 'page_url' === $type ) {
			$path = trim( (string) ( $child['value'] ?? '' ) );
			if ( '' === $path ) {
				return null;
			}
			return array(
				'label'      => (string) ( $child['label'] ?? sprintf( __( 'URL contains %s', 'reactwoo-geo-ai' ), $path ) ),
				'match'      => 'all',
				'conditions' => array(
					array(
						'type'     => 'request_uri',
						'operator' => 'contains',
						'value'    => array( $path ),
					),
				),
			);
		}
		return null;
	}

	/**
	 * @param string $mapping_key Mapping option key from the resolver UI.
	 * @return array<int,array<string,mixed>>
	 */
	private static function portable_conditions_from_traffic_mapping( $mapping_key ) {
		switch ( (string) $mapping_key ) {
			case 'utm_source_google':
				return array(
					array(
						'type'     => 'utm_source',
						'operator' => 'is',
						'value'    => array( 'google' ),
					),
				);
			case 'utm_medium_cpc':
				return array(
					array(
						'type'     => 'utm_medium',
						'operator' => 'is',
						'value'    => array( 'cpc' ),
					),
				);
			case 'utm_source_google_and_medium_cpc':
				return array(
					array(
						'type'     => 'utm_source',
						'operator' => 'is',
						'value'    => array( 'google' ),
					),
					array(
						'type'     => 'utm_medium',
						'operator' => 'is',
						'value'    => array( 'cpc' ),
					),
				);
			case 'gclid_exists':
				return array(
					array(
						'type'     => 'gclid',
						'operator' => 'not_empty',
						'value'    => array(),
					),
				);
			default:
				return array();
		}
	}

	/**
	 * @param array<string,mixed> $operation Operation.
	 * @return string show_if|hide_if
	 */
	private static function mode_from_operation( array $operation ) {
		$visibility = strtolower( (string) ( $operation['visibility'] ?? '' ) );
		return 'hide' === $visibility ? 'hide_if' : 'show_if';
	}

	/**
	 * @param mixed $list Raw list.
	 * @return array<int,string>
	 */
	private static function clean_list( $list ) {
		$out = array();
		foreach ( (array) $list as $item ) {
			$item = trim( (string) $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param mixed $audiences Audience rows (objects or strings).
	 * @return array<int,string>
	 */
	private static function audience_names( $audiences ) {
		$names = array();
		foreach ( (array) $audiences as $audience ) {
			if ( is_array( $audience ) ) {
				$name = trim( (string) ( $audience['name'] ?? '' ) );
			} else {
				$name = trim( (string) $audience );
			}
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		return array_values( array_unique( $names ) );
	}
}
