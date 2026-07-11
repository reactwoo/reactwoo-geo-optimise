<?php
/**
 * Parse natural-language phrases into portable targeting conditions (AND/OR).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Compound_Condition_Interpreter {

	/**
	 * @param string              $phrase  Normalised phrase.
	 * @param array<int,array>    $entities Entity rows from intelligence bundle.
	 * @param array<string,mixed> $options  Editor context (pro, available types).
	 * @return array<string,mixed>
	 */
	public static function parse( $phrase, array $entities, array $options = array() ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( '' === $phrase ) {
			return self::empty_result();
		}

		if ( class_exists( 'RWGA_Variant_Group_Extractor', false )
			&& RWGA_Variant_Group_Extractor::is_multi_variant_command( $phrase ) ) {
			return self::empty_result();
		}

		$segments = self::split_segments( $phrase );
		if ( count( $segments['parts'] ) <= 1 && ! $segments['has_explicit_logic'] ) {
			$implicit = self::scan_implicit_conditions( $phrase, $entities, $options );
			if ( count( $implicit['conditions'] ) >= 2 ) {
				return self::finalize_compound(
					$implicit['conditions'],
					'all',
					$implicit['warnings']
				);
			}
			return self::empty_result();
		}

		$conditions = array();
		$warnings   = array();

		foreach ( $segments['parts'] as $part ) {
			$cond = self::segment_to_condition( $part, $entities, $options, $warnings );
			if ( $cond ) {
				$conditions[] = $cond;
			}
		}

		if ( empty( $conditions ) ) {
			return self::empty_result();
		}

		return self::finalize_compound( $conditions, $segments['rule_match'], $warnings );
	}

	/**
	 * Detect multiple condition types in one phrase without explicit AND/OR (e.g. "mobile visitors in australia").
	 *
	 * @param string              $phrase   Normalised phrase.
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $options  Editor context.
	 * @return array{conditions:array<int,array<string,mixed>>,warnings:array<int,string>}
	 */
	private static function scan_implicit_conditions( $phrase, array $entities, array $options ) {
		$warnings   = array();
		$conditions = array();
		$seen_types = array();

		foreach ( array( 'country', 'device', 'weather', 'logged_in', 'campaign' ) as $kind ) {
			$cond = null;
			if ( 'country' === $kind ) {
				$country = self::find_entity_in_text( $phrase, $entities, 'country' );
				if ( $country ) {
					$exclude = (bool) preg_match( '/\b(hide|exclude|block|without|except|not)\b/i', $phrase );
					$cond    = array(
						'type'     => 'country',
						'operator' => $exclude ? 'not_in' : 'in',
						'value'    => array( $country ),
						'label'    => $country,
					);
				}
			} elseif ( 'device' === $kind ) {
				$device = self::find_entity_in_text( $phrase, $entities, 'device' );
				if ( ! $device && preg_match( '/\bmobile\b/i', $phrase ) ) {
					$device = 'mobile';
				} elseif ( ! $device && preg_match( '/\bdesktop\b/i', $phrase ) ) {
					$device = 'desktop';
				} elseif ( ! $device && preg_match( '/\btablet\b/i', $phrase ) ) {
					$device = 'tablet';
				}
				if ( $device ) {
					$cond = array(
						'type'     => 'device_type',
						'operator' => 'in',
						'value'    => array( $device ),
						'label'    => $device,
					);
				}
			} elseif ( 'weather' === $kind ) {
				$weather = self::find_entity_in_text( $phrase, $entities, 'weather_condition' );
				if ( $weather ) {
					if ( empty( $options['pro'] ) && ! self::is_free_type( 'weather_condition', $options ) ) {
						$warnings[] = __( 'Weather conditions require GeoCore Pro.', 'reactwoo-geocore' );
					} else {
						$cond = array(
							'type'     => 'weather_condition',
							'operator' => 'in',
							'value'    => array( $weather ),
							'label'    => $weather,
						);
					}
				}
			} elseif ( 'logged_in' === $kind && preg_match( '/\b(logged\s*in|logged\s*out)\b/i', $phrase, $m ) ) {
				$logged_in = false !== stripos( $m[1], 'in' );
				$cond      = array(
					'type'     => 'logged_in',
					'operator' => 'is',
					'value'    => $logged_in,
					'label'    => $logged_in ? 'logged_in' : 'logged_out',
				);
			} elseif ( 'campaign' === $kind && preg_match( '/\bcampaign\b/i', $phrase ) ) {
				if ( empty( $options['pro'] ) ) {
					$warnings[] = __( 'Campaign conditions require GeoCore Pro.', 'reactwoo-geocore' );
				} else {
					$cond = array(
						'type'     => 'campaign',
						'operator' => 'in',
						'value'    => array(),
						'label'    => 'campaign',
					);
				}
			}

			if ( $cond && ! in_array( $cond['type'], $seen_types, true ) ) {
				$seen_types[] = $cond['type'];
				$conditions[] = $cond;
			}
		}

		return array(
			'conditions' => $conditions,
			'warnings'   => $warnings,
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $conditions Parsed conditions.
	 * @param string                         $rule_match all|any.
	 * @param array<int,string>              $warnings   Warnings.
	 * @return array<string,mixed>
	 */
	private static function finalize_compound( array $conditions, $rule_match, array $warnings ) {
		$portable = self::build_portable_rule_set( $conditions, $rule_match );

		return array(
			'compound'          => true,
			'condition_match'   => $rule_match,
			'conditions'        => $conditions,
			'portable_rule_set' => $portable,
			'warnings'          => $warnings,
			'intent'            => 'compound_targeting',
			'matched_action'    => 'geocore_create_portable_rule',
			'confidence'        => min( 0.98, 0.65 + ( 0.1 * count( $conditions ) ) ),
			'summary'           => self::build_summary( $conditions, $rule_match ),
		);
	}

	/**
	 * @return array{parts:array<int,string>,rule_match:string,has_explicit_logic:bool}
	 */
	private static function split_segments( $phrase ) {
		$parts      = preg_split( '/\s+(?:and|or|plus|also)\s+/i', $phrase ) ?: array( $phrase );
		$parts      = array_values(
			array_filter(
				array_map(
					static function ( $p ) {
						return trim( (string) $p );
					},
					$parts
				)
			)
		);
		$has_or     = (bool) preg_match( '/\s+or\s+/i', $phrase );
		$has_and    = (bool) preg_match( '/\s+and\s+/i', $phrase );
		$rule_match = $has_or && ! $has_and ? 'any' : 'all';

		return array(
			'parts'               => $parts,
			'rule_match'          => $rule_match,
			'has_explicit_logic'  => $has_or || $has_and || count( $parts ) > 1,
		);
	}

	/**
	 * @param string              $segment  Phrase segment.
	 * @param array<int,array>    $entities Entities.
	 * @param array<string,mixed> $options  Options.
	 * @param array<int,string>   $warnings Warnings (by ref append).
	 * @return array<string,mixed>|null
	 */
	private static function segment_to_condition( $segment, array $entities, array $options, array &$warnings ) {
		$segment = RWGA_Local_Intent_Interpreter::normalise( $segment );
		$pro     = ! empty( $options['pro'] );

		$country = self::find_entity_in_text( $segment, $entities, 'country' );
		if ( $country ) {
			$exclude = (bool) preg_match( '/\b(hide|exclude|block|without|except|not)\b/i', $segment );
			return array(
				'type'     => 'country',
				'operator' => $exclude ? 'not_in' : 'in',
				'value'    => array( $country ),
				'label'    => $country,
			);
		}

		$device = self::find_entity_in_text( $segment, $entities, 'device' );
		if ( ! $device && preg_match( '/\bmobile\b/i', $segment ) ) {
			$device = 'mobile';
		} elseif ( ! $device && preg_match( '/\bdesktop\b/i', $segment ) ) {
			$device = 'desktop';
		} elseif ( ! $device && preg_match( '/\btablet\b/i', $segment ) ) {
			$device = 'tablet';
		}
		if ( $device ) {
			$exclude = (bool) preg_match( '/\b(hide|exclude|block|without|except|not)\b/i', $segment );
			return array(
				'type'     => 'device_type',
				'operator' => $exclude ? 'not_in' : 'in',
				'value'    => array( $device ),
				'label'    => $device,
			);
		}

		$weather = self::find_entity_in_text( $segment, $entities, 'weather_condition' );
		if ( $weather ) {
			if ( ! $pro && ! self::is_free_type( 'weather_condition', $options ) ) {
				$warnings[] = __( 'Weather conditions require GeoCore Pro.', 'reactwoo-geocore' );
				return null;
			}
			return array(
				'type'     => 'weather_condition',
				'operator' => 'in',
				'value'    => array( $weather ),
				'label'    => $weather,
			);
		}

		if ( preg_match( '/\b(utm|campaign|audience|referrer)\b/i', $segment ) ) {
			if ( ! $pro ) {
				$warnings[] = __( 'Campaign, UTM, and audience conditions require GeoCore Pro.', 'reactwoo-geocore' );
				return null;
			}
			if ( preg_match( '/\butm[_\s]?source\b/i', $segment ) && preg_match( '/[\w.-]+/', $segment, $m ) ) {
				return array(
					'type'     => 'utm_source',
					'operator' => 'contains',
					'value'    => array(),
					'label'    => 'utm_source',
				);
			}
			if ( preg_match( '/\bcampaign\b/i', $segment ) ) {
				return array(
					'type'     => 'campaign',
					'operator' => 'in',
					'value'    => array(),
					'label'    => 'campaign',
				);
			}
		}

		if ( preg_match( '/\b(logged\s*in|logged\s*out)\b/i', $segment, $m ) ) {
			$logged_in = false !== stripos( $m[1], 'in' );
			return array(
				'type'     => 'logged_in',
				'operator' => 'is',
				'value'    => $logged_in,
				'label'    => $logged_in ? 'logged_in' : 'logged_out',
			);
		}

		return null;
	}

	/**
	 * @param string              $text     Text.
	 * @param array<int,array>    $entities All entities.
	 * @param string              $type     Entity type.
	 * @return string|null
	 */
	private static function find_entity_in_text( $text, array $entities, $type ) {
		foreach ( $entities as $row ) {
			if ( ! is_array( $row ) || ( $row['entity_type'] ?? '' ) !== $type ) {
				continue;
			}
			$aliases = isset( $row['aliases'] ) && is_array( $row['aliases'] ) ? $row['aliases'] : array();
			$aliases[] = (string) ( $row['display_name'] ?? '' );
			$aliases[] = (string) ( $row['entity_key'] ?? '' );
			usort(
				$aliases,
				static function ( $a, $b ) {
					return strlen( (string) $b ) - strlen( (string) $a );
				}
			);
			foreach ( $aliases as $alias ) {
				$alias = RWGA_Local_Intent_Interpreter::normalise( $alias );
				if ( '' !== $alias && false !== strpos( $text, $alias ) ) {
					return (string) ( $row['value'] ?? $row['entity_key'] ?? '' );
				}
			}
		}
		return null;
	}

	/**
	 * @param string $type Portable type.
	 * @param array  $options Options.
	 * @return bool
	 */
	private static function is_free_type( $type, array $options ) {
		if ( class_exists( 'RWGC_Targeting_Rule_Set_Schema', false ) ) {
			return in_array( $type, RWGC_Targeting_Rule_Set_Schema::FREE_CONDITION_TYPES, true );
		}
		return in_array( $type, array( 'country', 'device_type', 'device', 'language', 'locale' ), true );
	}

	/**
	 * @param array<int,array<string,mixed>> $conditions Conditions.
	 * @param string                         $rule_match all|any.
	 * @return array<string,mixed>
	 */
	private static function build_portable_rule_set( array $conditions, $rule_match ) {
		$portable_conditions = array();
		foreach ( $conditions as $cond ) {
			$portable_conditions[] = array(
				'type'     => (string) $cond['type'],
				'operator' => (string) $cond['operator'],
				'value'    => $cond['value'],
			);
		}

		return array(
			'schema_version' => class_exists( 'RWGC_Targeting_Rule_Set_Schema', false )
				? RWGC_Targeting_Rule_Set_Schema::VERSION
				: 2,
			'enabled'        => true,
			'mode'           => 'show_if',
			'match'          => 'all',
			'rules'          => array(
				array(
					'id'         => 'rule_assistant',
					'label'      => '',
					'match'      => 'any' === $rule_match ? 'any' : 'all',
					'conditions' => $portable_conditions,
				),
			),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $conditions Conditions.
	 * @param string                         $rule_match Match mode.
	 * @return string
	 */
	private static function build_summary( array $conditions, $rule_match ) {
		$labels = array_map(
			static function ( $c ) {
				$type = (string) ( $c['type'] ?? '' );
				$op   = (string) ( $c['operator'] ?? 'in' );
				$val  = is_array( $c['value'] ?? null ) ? implode( ', ', $c['value'] ) : (string) ( $c['value'] ?? '' );
				$prefix = in_array( $op, array( 'not_in', 'is_not', 'not_contains' ), true ) ? 'NOT ' : '';
				return $prefix . strtoupper( $type ) . ( $val ? ': ' . $val : '' );
			},
			$conditions
		);
		$join = 'any' === $rule_match ? ' OR ' : ' AND ';
		return sprintf(
			/* translators: %s: joined condition labels */
			__( 'Targeting conditions: %s', 'reactwoo-geocore' ),
			implode( $join, $labels )
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function empty_result() {
		return array(
			'compound' => false,
		);
	}
}
