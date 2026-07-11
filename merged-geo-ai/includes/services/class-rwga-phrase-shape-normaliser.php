<?php
/**
 * Convert raw phrases into reusable phrase shapes and entity maps.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Phrase_Shape_Normaliser {

	/** @var array<string,int> */
	const NUMBER_WORDS = array(
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

	/** @var array<string,string> */
	const PAGE_ALIASES = array(
		'homepage'  => 'homepage',
		'home page' => 'homepage',
		'front page' => 'homepage',
		'checkout page' => 'checkout',
		'checkout' => 'checkout',
		'cart page' => 'cart',
		'basket page' => 'cart',
		'landing page' => 'landing',
		'shop page' => 'shop',
		'product page' => 'product',
	);

	/**
	 * @param string              $message  Raw or normalised message.
	 * @param array<int,array>    $entities Entity rows.
	 * @return array{normalised_phrase:string,phrase_shape:string,entity_map:array<string,mixed>}
	 */
	public static function build( $message, array $entities = array() ) {
		$normalised = class_exists( 'RWGA_Local_Intent_Interpreter', false )
			? RWGA_Local_Intent_Interpreter::normalise( $message )
			: strtolower( trim( (string) $message ) );

		$entity_map = array();
		$shape      = $normalised;
		$country_idx = 0;
		$list_idx    = 0;
		$ordinal_idx = 0;

		foreach ( self::sorted_page_aliases() as $alias => $value ) {
			if ( false !== strpos( $shape, $alias ) ) {
				$entity_map['page'] = $value;
				$shape              = str_replace( $alias, '{page}', $shape );
				break;
			}
		}

		if ( preg_match( '/\b(?:create|make|build)\s+(one|two|three|four|five|\d+)\s+(?:variations?|variants?|versions?)\b/i', $normalised, $m ) ) {
			$key = strtolower( $m[1] );
			$entity_map['number'] = (string) ( self::NUMBER_WORDS[ $key ] ?? $key );
			$shape                = preg_replace(
				'/\b(?:create|make|build)\s+(?:one|two|three|four|five|\d+)\s+(?:variations?|variants?|versions?)\b/i',
				'create {number} variations',
				$shape,
				1
			);
		}

		$ordinal_patterns = array(
			'/\bvariant\s+(one|two|three|\d+)\b/i' => '{ordinal}',
			'/\bversion\s+(one|two|three|\d+)\b/i' => '{ordinal}',
			'/\bvariation\s+(one|two|three|\d+)\b/i' => '{ordinal}',
			'/\bthe\s+(first|second|third)\b/i' => '{ordinal}',
			'/\b(?:1st|2nd|3rd)\b/i' => '{ordinal}',
		);
		foreach ( $ordinal_patterns as $pattern => $placeholder ) {
			if ( preg_match( $pattern, $shape, $m ) ) {
				++$ordinal_idx;
				$key = strtolower( (string) ( $m[1] ?? $m[0] ) );
				$entity_map[ 'ordinal_' . $ordinal_idx ] = self::ordinal_value( $key );
				$shape = preg_replace( $pattern, 'variant ' . $placeholder, $shape, 1 );
			}
		}

		$countries = class_exists( 'RWGA_Multi_Variant_Interpreter', false )
			? RWGA_Multi_Variant_Interpreter::parse_country_list( $normalised, $entities )
			: array();

		$aliases = self::country_aliases( $entities );
		usort(
			$aliases,
			static function ( $a, $b ) {
				return strlen( (string) $b['alias'] ) - strlen( (string) $a['alias'] );
			}
		);

		foreach ( $aliases as $row ) {
			$alias = (string) $row['alias'];
			$code  = (string) $row['code'];
			if ( '' === $alias || false === strpos( $shape, $alias ) ) {
				continue;
			}
			if ( preg_match( '/\b' . preg_quote( $alias, '/' ) . '\s+and\s+' . preg_quote( $alias, '/' ) . '\b/i', $shape ) ) {
				continue;
			}
			if ( preg_match( '/\b' . preg_quote( $alias, '/' ) . '\s*,\s*|\s+and\s+|\s*&\s+|\s+plus\s+/i', $shape ) ) {
				++$list_idx;
				$key = 'country_list_' . $list_idx;
				if ( empty( $entity_map[ $key ] ) ) {
					$entity_map[ $key ] = array();
				}
				if ( is_array( $entity_map[ $key ] ) && ! in_array( $code, $entity_map[ $key ], true ) ) {
					$entity_map[ $key ][] = $code;
				}
				$shape = preg_replace( '/\b' . preg_quote( $alias, '/' ) . '\b/i', '{country_list}', $shape, 1 );
				continue;
			}
			++$country_idx;
			$entity_map[ 'country_' . $country_idx ] = $code;
			$shape                                   = preg_replace( '/\b' . preg_quote( $alias, '/' ) . '\b/i', '{country}', $shape, 1 );
		}

		if ( ! empty( $countries ) && empty( $entity_map['country_1'] ) && empty( $entity_map['country_list_1'] ) ) {
			if ( count( $countries ) === 1 ) {
				$entity_map['country_1'] = $countries[0];
			} else {
				$entity_map['country_list_1'] = $countries;
			}
		}

		$shape = preg_replace( '/\s+/', ' ', trim( (string) $shape ) );
		$shape = preg_replace( '/\bthe\s+(first|second|third)\b/i', 'variant {ordinal}', $shape );
		$shape = preg_replace( '/\s+/', ' ', trim( (string) $shape ) );

		return array(
			'normalised_phrase' => $normalised,
			'phrase_shape'      => $shape,
			'entity_map'        => $entity_map,
		);
	}

	/**
	 * Build params template from resolved params.
	 *
	 * @param array<string,mixed> $params     Resolved params.
	 * @param array<string,mixed> $entity_map Entity map.
	 * @return array<string,mixed>
	 */
	public static function build_params_template( array $params, array $entity_map ) {
		$json = wp_json_encode( $params );
		if ( ! is_string( $json ) ) {
			return $params;
		}
		foreach ( $entity_map as $key => $value ) {
			if ( is_array( $value ) ) {
				$encoded = wp_json_encode( array_values( $value ) );
				if ( is_string( $encoded ) ) {
					$json = str_replace( $encoded, '{' . $key . '}', $json );
				}
				continue;
			}
			$json = str_replace( wp_json_encode( (string) $value ), '{' . $key . '}', $json );
			$json = str_replace( wp_json_encode( (int) $value ), '{' . $key . '}', $json );
		}
		if ( ! empty( $entity_map['page'] ) ) {
			$json = str_replace( wp_json_encode( (string) $entity_map['page'] ), '{page}', $json );
		}
		if ( ! empty( $entity_map['number'] ) ) {
			$json = str_replace( wp_json_encode( (int) $entity_map['number'] ), '{number}', $json );
			$json = str_replace( wp_json_encode( (string) $entity_map['number'] ), '{number}', $json );
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : $params;
	}

	/**
	 * @param array<string,mixed> $template  Params template.
	 * @param array<string,mixed> $entity_map Entity map.
	 * @return array<string,mixed>
	 */
	public static function apply_params_template( array $template, array $entity_map ) {
		$json = wp_json_encode( $template );
		if ( ! is_string( $json ) ) {
			return $template;
		}
		foreach ( $entity_map as $key => $value ) {
			$replacement = is_array( $value ) ? wp_json_encode( array_values( $value ) ) : wp_json_encode( $value );
			if ( ! is_string( $replacement ) ) {
				continue;
			}
			$json = str_replace( '{' . $key . '}', trim( $replacement, '"' ), $json );
			$json = str_replace( '"{' . $key . '}"', $replacement, $json );
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : $template;
	}

	/**
	 * @param string $token Ordinal token.
	 * @return int
	 */
	private static function ordinal_value( $token ) {
		$map = array(
			'one'    => 1,
			'two'    => 2,
			'three'  => 3,
			'first'  => 1,
			'second' => 2,
			'third'  => 3,
			'1st'    => 1,
			'2nd'    => 2,
			'3rd'    => 3,
			'1'      => 1,
			'2'      => 2,
			'3'      => 3,
		);
		$key = strtolower( trim( (string) $token ) );
		return (int) ( $map[ $key ] ?? $token );
	}

	/**
	 * @return array<string,string>
	 */
	private static function sorted_page_aliases() {
		$aliases = self::PAGE_ALIASES;
		uksort(
			$aliases,
			static function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);
		return $aliases;
	}

	/**
	 * @param array<int,array> $entities Entities.
	 * @return array<int,array{alias:string,code:string}>
	 */
	private static function country_aliases( array $entities ) {
		$out = array();
		foreach ( $entities as $row ) {
			if ( ! is_array( $row ) || ( $row['entity_type'] ?? '' ) !== 'country' ) {
				continue;
			}
			$code    = (string) ( $row['value'] ?? $row['entity_key'] ?? '' );
			$aliases = isset( $row['aliases'] ) && is_array( $row['aliases'] ) ? $row['aliases'] : array();
			$aliases[] = (string) ( $row['display_name'] ?? '' );
			$aliases[] = $code;
			foreach ( $aliases as $alias ) {
				$alias = strtolower( trim( (string) $alias ) );
				if ( '' !== $alias ) {
					$out[] = array(
						'alias' => $alias,
						'code'  => $code,
					);
				}
			}
		}
		return $out;
	}
}
