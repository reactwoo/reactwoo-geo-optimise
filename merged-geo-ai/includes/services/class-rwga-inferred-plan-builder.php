<?php
/**
 * Build and format inferred variant plans for partial Geo Assistant interpretations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Inferred_Plan_Builder {

	/**
	 * @param array<string,mixed> $raw      Interpreter output.
	 * @param array<int,array>    $entities Entity rows.
	 * @return array<string,mixed>|null
	 */
	public static function from_interpreter_result( array $raw, array $entities = array() ) {
		if ( ! empty( $raw['inferred_plan'] ) && is_array( $raw['inferred_plan'] ) ) {
			return self::normalise_plan( $raw['inferred_plan'], $entities );
		}

		$params = isset( $raw['params'] ) && is_array( $raw['params'] ) ? $raw['params'] : array();
		if ( ! empty( $params['source_targeting'] ) || ! empty( $params['variants'] ) ) {
			$plan = self::from_params(
				(string) ( $params['source_page_ref'] ?? 'homepage' ),
				$params,
				$entities
			);
			if ( $plan ) {
				return $plan;
			}
		}

		$debug = array();
		if ( is_array( $raw['_debug'] ?? null ) ) {
			$debug = $raw['_debug'];
		} elseif ( is_array( $raw['_debug_entities'] ?? null ) ) {
			$debug = $raw['_debug_entities'];
		}

		if ( ! empty( $debug ) ) {
			return self::from_debug(
				(string) ( $params['source_page_ref'] ?? $debug['source_page_ref'] ?? 'homepage' ),
				$debug,
				$entities
			);
		}

		return null;
	}

	/**
	 * @param string              $page_ref Page reference.
	 * @param array<string,mixed> $debug    Parser debug.
	 * @param array<int,array>    $entities Entities.
	 * @return array<string,mixed>|null
	 */
	public static function from_debug( $page_ref, array $debug, array $entities = array() ) {
		$source = is_array( $debug['detected_source_clause'] ?? null ) ? $debug['detected_source_clause'] : null;
		$variants = isset( $debug['detected_variant_clauses'] ) && is_array( $debug['detected_variant_clauses'] )
			? $debug['detected_variant_clauses']
			: array();

		if ( ( empty( $source ) || empty( $source['countries'] ) ) && empty( $variants ) && ! empty( $debug['countries_per_clause'] ) ) {
			$built = self::from_countries_per_clause( $page_ref, (array) $debug['countries_per_clause'], $entities );
			if ( $built ) {
				return $built;
			}
		}

		if ( empty( $variants ) && ( empty( $source ) || empty( $source['countries'] ) ) ) {
			return null;
		}

		return self::build_plan( $page_ref, $source, $variants, $entities );
	}

	/**
	 * @param string              $page_ref Page reference.
	 * @param array<string,mixed> $params   Plan params.
	 * @param array<int,array>    $entities Entities.
	 * @return array<string,mixed>|null
	 */
	public static function from_params( $page_ref, array $params, array $entities = array() ) {
		$source_row = null;
		if ( ! empty( $params['source_targeting'] ) && is_array( $params['source_targeting'] ) ) {
			$source_row = array(
				'label'     => (string) ( $params['source_targeting']['label'] ?? __( 'Original homepage', 'reactwoo-geocore' ) ),
				'countries' => (array) ( $params['source_targeting']['countries'] ?? array() ),
				'weather'   => self::weather_key_from_param( $params['source_targeting']['weather'] ?? null ),
			);
		}

		$variants = array();
		foreach ( (array) ( $params['variants'] ?? array() ) as $idx => $variant ) {
			if ( ! is_array( $variant ) || empty( $variant['countries'] ) ) {
				continue;
			}
			$ordinal = (int) ( $variant['ordinal'] ?? 0 );
			if ( $ordinal <= 0 ) {
				$ordinal = $idx + 1;
			}
			$variants[] = array(
				'ordinal'   => $ordinal,
				'label'     => (string) ( $variant['label'] ?? '' ),
				'countries' => (array) $variant['countries'],
				'weather'   => self::weather_key_from_param( $variant['weather'] ?? null ),
			);
		}

		if ( empty( $variants ) && ( empty( $source_row ) || empty( $source_row['countries'] ) ) ) {
			return null;
		}

		$plan = self::build_plan( $page_ref, $source_row, $variants, $entities );
		if ( is_array( $plan ) ) {
			$plan['variant_source_page'] = (string) ( $params['variant_source_page'] ?? $page_ref );
			$plan['original_page_ref']   = (string) ( $params['original_page_ref'] ?? '' );
		}
		return $plan;
	}

	/**
	 * @param array<string,mixed> $plan     Inferred plan.
	 * @param array<int,array>    $entities Entities.
	 * @return array<string,mixed>
	 */
	public static function normalise_plan( array $plan, array $entities = array() ) {
		$page_ref = (string) ( $plan['source_page_ref'] ?? 'homepage' );
		$source   = is_array( $plan['source_targeting'] ?? null ) ? $plan['source_targeting'] : null;
		$variants = isset( $plan['variants'] ) && is_array( $plan['variants'] ) ? $plan['variants'] : array();
		return self::build_plan( $page_ref, $source, $variants, $entities ) ?? $plan;
	}

	/**
	 * @param array<string,mixed> $inferred_plan Inferred plan.
	 * @param array<int,array>    $entities      Entities.
	 * @return array<string,mixed>
	 */
	public static function to_confirmed_params( array $inferred_plan, array $entities = array() ) {
		$plan = self::normalise_plan( $inferred_plan, $entities );
		$source_param = null;
		if ( ! empty( $plan['source_targeting'] ) ) {
			$source = $plan['source_targeting'];
			$source_param = array(
				'label'           => (string) ( $source['label'] ?? __( 'Original homepage', 'reactwoo-geocore' ) ),
				'targeting_label' => self::countries_label( (array) ( $source['countries'] ?? array() ), $entities ),
				'mode'            => 'include_only',
				'countries'       => (array) ( $source['countries'] ?? array() ),
			);
			$weather = $source['weather'] ?? null;
			if ( is_array( $weather ) ) {
				$source_param['weather'] = $weather;
			} elseif ( null !== $weather && '' !== $weather && class_exists( 'RWGA_Segment_Condition_Extractor', false ) ) {
				$source_param['weather'] = RWGA_Segment_Condition_Extractor::weather_param( (string) $weather );
			}
		}

		$variants_out = array();
		foreach ( (array) ( $plan['variants'] ?? array() ) as $variant ) {
			if ( ! is_array( $variant ) || empty( $variant['countries'] ) ) {
				continue;
			}
			$row = array(
				'ordinal'   => (int) ( $variant['ordinal'] ?? count( $variants_out ) + 1 ),
				'label'     => (string) ( $variant['label'] ?? self::countries_label( (array) $variant['countries'], $entities ) ),
				'mode'      => 'include_only',
				'countries' => (array) $variant['countries'],
			);
			$weather = $variant['weather'] ?? null;
			if ( is_array( $weather ) ) {
				$row['weather'] = $weather;
			} elseif ( null !== $weather && '' !== $weather && class_exists( 'RWGA_Segment_Condition_Extractor', false ) ) {
				$row['weather'] = RWGA_Segment_Condition_Extractor::weather_param( (string) $weather );
			}
			$variants_out[] = $row;
		}

		$has_weather = ( is_array( $source_param['weather'] ?? null ) )
			|| array_filter(
				$variants_out,
				static function ( $row ) {
					return ! empty( $row['weather'] );
				}
			);

		return array(
			'source_page_ref'     => (string) ( $plan['source_page_ref'] ?? 'homepage' ),
			'variant_source_page' => (string) ( $plan['variant_source_page'] ?? $plan['source_page_ref'] ?? 'homepage' ),
			'original_page_ref'   => (string) ( $plan['original_page_ref'] ?? '' ),
			'source_targeting'    => $source_param,
			'variants'         => $variants_out,
			'duplicate_count'  => count( $variants_out ),
			'total_version_count' => ( $source_param ? 1 : 0 ) + count( $variants_out ),
			'_matched_action'  => $has_weather
				? 'geocore_create_variant_plan_with_conditions'
				: 'geocore_create_variant_plan_with_country_rules',
		);
	}

	/**
	 * @param array<string,mixed> $inferred_plan Inferred plan.
	 * @param array<int,array>    $entities      Entities.
	 * @return string
	 */
	public static function bubble_html( array $inferred_plan, array $entities = array() ) {
		$plan = self::normalise_plan( $inferred_plan, $entities );
		$lines = array( '<p><strong>' . esc_html__( 'I think you mean:', 'reactwoo-geo-ai' ) . '</strong></p>', '<ul class="rwgc-targeting-assistant__inferred-plan">' );

		if ( ! empty( $plan['source_targeting'] ) ) {
			$lines[] = '<li><strong>' . esc_html( (string) ( $plan['source_targeting']['label'] ?? __( 'Original homepage', 'reactwoo-geocore' ) ) ) . '</strong>';
			$lines[] = esc_html__( 'Country:', 'reactwoo-geo-ai' ) . ' ' . esc_html( self::countries_label( (array) ( $plan['source_targeting']['countries'] ?? array() ), $entities ) );
			$weather = self::weather_display( $plan['source_targeting']['weather'] ?? null, $entities );
			if ( '' !== $weather ) {
				$lines[] = esc_html__( 'Weather:', 'reactwoo-geo-ai' ) . ' ' . esc_html( $weather );
			}
			$lines[] = '</li>';
		}

		foreach ( (array) ( $plan['variants'] ?? array() ) as $variant ) {
			if ( ! is_array( $variant ) ) {
				continue;
			}
			$label = (string) ( $variant['label'] ?? '' );
			if ( '' === $label ) {
				$label = sprintf(
					/* translators: %d: variant number */
					__( 'Variant %d', 'reactwoo-geocore' ),
					(int) ( $variant['ordinal'] ?? 1 )
				);
			}
			$countries = (array) ( $variant['countries'] ?? array() );
			$country_line = count( $countries ) > 1
				? esc_html__( 'Countries:', 'reactwoo-geo-ai' ) . ' ' . esc_html( self::countries_label( $countries, $entities ) )
				: esc_html__( 'Country:', 'reactwoo-geo-ai' ) . ' ' . esc_html( self::countries_label( $countries, $entities ) );
			$lines[] = '<li><strong>' . esc_html( $label ) . '</strong><br />' . $country_line;
			$weather = self::weather_display( $variant['weather'] ?? null, $entities );
			if ( '' !== $weather ) {
				$lines[] = '<br />' . esc_html__( 'Weather:', 'reactwoo-geo-ai' ) . ' ' . esc_html( $weather );
			}
			$lines[] = '</li>';
		}

		$lines[] = '</ul>';
		$lines[] = '<p><strong>' . esc_html__( 'Is this correct?', 'reactwoo-geo-ai' ) . '</strong></p>';
		return implode( "\n", $lines );
	}

	/**
	 * @param array<string,mixed> $inferred_plan Inferred plan.
	 * @param array<int,array>    $entities      Entities.
	 * @param string              $status_label  Status line.
	 * @return string
	 */
	public static function setup_summary( array $inferred_plan, array $entities = array(), $status_label = '' ) {
		$plan = self::normalise_plan( $inferred_plan, $entities );
		$page = (string) ( $plan['source_page_ref'] ?? 'homepage' );
		$variant_page = (string) ( $plan['variant_source_page'] ?? $page );
		$original_page = (string) ( $plan['original_page_ref'] ?? '' );
		$lines = array();
		if ( $variant_page && ( ! $original_page || $original_page !== $variant_page ) ) {
			$lines[] = sprintf(
				/* translators: %s: page ref */
				__( 'Page: %s', 'reactwoo-geocore' ),
				ucfirst( $variant_page )
			);
		} else {
			$lines[] = ucfirst( $page ) . ' ' . __( 'targeting plan', 'reactwoo-geocore' );
		}
		$lines[] = '';

		if ( ! empty( $plan['source_targeting'] ) ) {
			$lines[] = (string) ( $plan['source_targeting']['label'] ?? __( 'Original homepage', 'reactwoo-geocore' ) );
			$lines[] = self::countries_label( (array) ( $plan['source_targeting']['countries'] ?? array() ), $entities ) . ' ' . __( 'only', 'reactwoo-geocore' );
			$weather = self::weather_display( $plan['source_targeting']['weather'] ?? null, $entities );
			if ( '' !== $weather ) {
				$lines[] = __( 'Weather:', 'reactwoo-geocore' ) . ' ' . $weather;
			}
		}

		foreach ( (array) ( $plan['variants'] ?? array() ) as $variant ) {
			if ( ! is_array( $variant ) ) {
				continue;
			}
			$lines[] = '';
			$lines[] = (string) ( $variant['label'] ?? sprintf( __( 'Variant %d', 'reactwoo-geocore' ), (int) ( $variant['ordinal'] ?? 1 ) ) );
			$lines[] = self::countries_label( (array) ( $variant['countries'] ?? array() ), $entities ) . ' ' . __( 'only', 'reactwoo-geocore' );
			$weather = self::weather_display( $variant['weather'] ?? null, $entities );
			if ( '' !== $weather ) {
				$lines[] = __( 'Weather:', 'reactwoo-geocore' ) . ' ' . $weather;
			}
		}

		$lines[] = '';
		$lines[] = __( 'Status', 'reactwoo-geocore' );
		$lines[] = '' !== $status_label
			? $status_label
			: __( 'Needs confirmation', 'reactwoo-geocore' );

		return implode( "\n", $lines );
	}

	/**
	 * @param string|null              $page_ref Page ref.
	 * @param array<int,array>         $clauses  countries_per_clause rows.
	 * @param array<int,array>         $entities Entities.
	 * @return array<string,mixed>|null
	 */
	private static function from_countries_per_clause( $page_ref, array $clauses, array $entities ) {
		$source   = null;
		$variants = array();
		$ordinal  = 0;

		foreach ( $clauses as $clause ) {
			if ( ! is_array( $clause ) || empty( $clause['countries'] ) ) {
				continue;
			}
			$weather = self::weather_key_from_param( $clause['weather'] ?? null );
			$row     = array(
				'countries' => (array) $clause['countries'],
				'weather'   => $weather,
			);
			if ( 'source' === ( $clause['type'] ?? '' ) ) {
				$source = array_merge(
					$row,
					array( 'label' => __( 'Original homepage', 'reactwoo-geocore' ) )
				);
				continue;
			}
			$ordinal++;
			$variants[] = array_merge(
				$row,
				array(
					'ordinal' => $ordinal,
					'label'   => sprintf(
						/* translators: %d: variant number */
						__( 'Variant %d', 'reactwoo-geocore' ),
						$ordinal
					),
				)
			);
		}

		if ( empty( $variants ) ) {
			return null;
		}

		return self::build_plan( (string) $page_ref, $source, $variants, $entities );
	}

	/**
	 * @param string                   $page_ref Page ref.
	 * @param array<string,mixed>|null $source   Source row.
	 * @param array<int,array>         $variants Variant rows.
	 * @param array<int,array>         $entities Entities.
	 * @return array<string,mixed>|null
	 */
	private static function build_plan( $page_ref, $source, array $variants, array $entities ) {
		$source_out = null;
		if ( is_array( $source ) && ! empty( $source['countries'] ) ) {
			$weather = self::weather_key_from_param( $source['weather'] ?? null );
			$source_out = array(
				'label'     => (string) ( $source['label'] ?? __( 'Original homepage', 'reactwoo-geocore' ) ),
				'countries' => array_values( (array) $source['countries'] ),
				'weather'   => class_exists( 'RWGA_Segment_Condition_Extractor', false )
					? RWGA_Segment_Condition_Extractor::weather_param( $weather )
					: ( 'any' === $weather ? array( 'mode' => 'any' ) : ( $weather ? array( 'condition' => $weather ) : null ) ),
			);
		}

		$variants_out = array();
		$ordinal      = 0;
		foreach ( $variants as $variant ) {
			if ( ! is_array( $variant ) || empty( $variant['countries'] ) ) {
				continue;
			}
			$ordinal++;
			$weather = self::weather_key_from_param( $variant['weather'] ?? null );
			$label   = (string) ( $variant['label'] ?? '' );
			if ( '' === $label ) {
				$label = sprintf(
					/* translators: %d: variant number */
					__( 'Variant %d', 'reactwoo-geocore' ),
					(int) ( $variant['ordinal'] ?? $ordinal )
				);
			}
			$variants_out[] = array(
				'label'     => $label,
				'countries' => array_values( (array) $variant['countries'] ),
				'weather'   => class_exists( 'RWGA_Segment_Condition_Extractor', false )
					? RWGA_Segment_Condition_Extractor::weather_param( $weather )
					: ( 'any' === $weather ? array( 'mode' => 'any' ) : ( $weather ? array( 'condition' => $weather ) : null ) ),
			);
		}

		if ( empty( $variants_out ) && empty( $source_out ) ) {
			return null;
		}

		return array(
			'source_page_ref'     => (string) $page_ref,
			'variant_source_page' => (string) ( $params['variant_source_page'] ?? $page_ref ),
			'original_page_ref'   => (string) ( $params['original_page_ref'] ?? '' ),
			'source_targeting'    => $source_out,
			'variants'            => $variants_out,
		);
	}

	/**
	 * @param array<int,string> $countries Country codes.
	 * @param array<int,array>  $entities  Entities.
	 * @return string
	 */
	public static function countries_label( array $countries, array $entities ) {
		if ( empty( $countries ) ) {
			return '';
		}
		if ( class_exists( 'RWGA_Variant_Group_Extractor', false ) ) {
			return RWGA_Variant_Group_Extractor::label_for_countries( $countries, $entities );
		}
		return implode( ' + ', $countries );
	}

	/**
	 * @param mixed                  $weather  Weather param or key.
	 * @param array<int,array>       $entities Entities.
	 * @return string
	 */
	public static function weather_display( $weather, array $entities ) {
		$key = self::weather_key_from_param( $weather );
		if ( null === $key || '' === $key ) {
			return '';
		}
		if ( class_exists( 'RWGA_Segment_Condition_Extractor', false ) ) {
			return RWGA_Segment_Condition_Extractor::weather_label( $key, $entities );
		}
		return (string) $key;
	}

	/**
	 * @param mixed $weather Weather param or key.
	 * @return string|null
	 */
	private static function weather_key_from_param( $weather ) {
		if ( null === $weather || '' === $weather ) {
			return null;
		}
		if ( is_string( $weather ) ) {
			return $weather;
		}
		if ( ! is_array( $weather ) ) {
			return null;
		}
		if ( ! empty( $weather['mode'] ) && 'any' === $weather['mode'] ) {
			return 'any';
		}
		if ( ! empty( $weather['condition'] ) ) {
			return (string) $weather['condition'];
		}
		return null;
	}
}
