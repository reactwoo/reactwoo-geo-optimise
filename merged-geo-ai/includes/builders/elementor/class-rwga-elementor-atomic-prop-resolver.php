<?php
/**
 * Recursive resolver for Elementor Atomic typed properties ({ $$type, value }).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unwraps Atomic $$type / value wrappers into plain PHP values for analysis.
 */
class RWGA_Elementor_Atomic_Prop_Resolver {

	/**
	 * Max recursion depth to avoid pathological trees.
	 *
	 * @var int
	 */
	const MAX_DEPTH = 12;

	/**
	 * Resolve a single value (typed or plain).
	 *
	 * @param mixed $value Raw value.
	 * @param int   $depth Depth.
	 * @return mixed
	 */
	public static function resolve( $value, $depth = 0 ) {
		if ( $depth > self::MAX_DEPTH ) {
			return null;
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( isset( $value['$$type'] ) ) {
			$type = (string) $value['$$type'];
			$inner = array_key_exists( 'value', $value ) ? $value['value'] : null;

			switch ( $type ) {
				case 'string':
				case 'number':
				case 'boolean':
				case 'url':
				case 'image':
				case 'color':
				case 'size':
					return self::resolve( $inner, $depth + 1 );
				case 'html':
				case 'html-v3':
					return self::resolve_html_payload( $inner, $depth + 1 );
				case 'classes':
				case 'class-list':
					return self::resolve_list( $inner, $depth + 1 );
				case 'link':
					return self::resolve_link( $inner, $depth + 1 );
				case 'array':
				case 'object':
					return self::resolve( $inner, $depth + 1 );
				default:
					// Preserve unknown typed payloads as bounded structure.
					return array(
						'$$type' => sanitize_key( $type ),
						'value'  => self::resolve( $inner, $depth + 1 ),
					);
			}
		}

		$out = array();
		foreach ( $value as $key => $item ) {
			$out[ $key ] = self::resolve( $item, $depth + 1 );
		}
		return $out;
	}

	/**
	 * Resolve an entire settings bag.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	public static function resolve_settings( array $settings ) {
		$out = array();
		foreach ( $settings as $key => $value ) {
			$out[ (string) $key ] = self::resolve( $value );
		}
		return $out;
	}

	/**
	 * Extract plain text from a resolved or typed title/html field.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function to_plain_text( $value ) {
		$resolved = self::resolve( $value );
		if ( is_string( $resolved ) || is_numeric( $resolved ) ) {
			return wp_strip_all_tags( (string) $resolved );
		}
		if ( is_array( $resolved ) ) {
			if ( isset( $resolved['content'] ) && ( is_string( $resolved['content'] ) || is_numeric( $resolved['content'] ) ) ) {
				return wp_strip_all_tags( (string) $resolved['content'] );
			}
			if ( isset( $resolved['text'] ) && ( is_string( $resolved['text'] ) || is_numeric( $resolved['text'] ) ) ) {
				return wp_strip_all_tags( (string) $resolved['text'] );
			}
			if ( isset( $resolved['value'] ) && ( is_string( $resolved['value'] ) || is_numeric( $resolved['value'] ) ) ) {
				return wp_strip_all_tags( (string) $resolved['value'] );
			}
		}
		return '';
	}

	/**
	 * @param mixed $inner Inner html payload.
	 * @param int   $depth Depth.
	 * @return mixed
	 */
	private static function resolve_html_payload( $inner, $depth ) {
		$resolved = self::resolve( $inner, $depth );
		if ( is_array( $resolved ) && isset( $resolved['content'] ) ) {
			$content = self::resolve( $resolved['content'], $depth + 1 );
			if ( is_string( $content ) || is_numeric( $content ) ) {
				return (string) $content;
			}
			if ( is_array( $content ) && isset( $content['value'] ) && ( is_string( $content['value'] ) || is_numeric( $content['value'] ) ) ) {
				return (string) $content['value'];
			}
		}
		if ( is_string( $resolved ) || is_numeric( $resolved ) ) {
			return (string) $resolved;
		}
		return $resolved;
	}

	/**
	 * @param mixed $inner List payload.
	 * @param int   $depth Depth.
	 * @return array<int, mixed>
	 */
	private static function resolve_list( $inner, $depth ) {
		$resolved = self::resolve( $inner, $depth );
		if ( ! is_array( $resolved ) ) {
			return array();
		}
		// Numeric list of class ids / strings.
		if ( array_keys( $resolved ) === range( 0, count( $resolved ) - 1 ) ) {
			return array_values( $resolved );
		}
		if ( isset( $resolved['value'] ) && is_array( $resolved['value'] ) ) {
			return array_values( $resolved['value'] );
		}
		return array_values( $resolved );
	}

	/**
	 * @param mixed $inner Link payload.
	 * @param int   $depth Depth.
	 * @return array<string, mixed>
	 */
	private static function resolve_link( $inner, $depth ) {
		$resolved = self::resolve( $inner, $depth );
		if ( ! is_array( $resolved ) ) {
			return array( 'url' => is_string( $resolved ) ? $resolved : '' );
		}
		$url = '';
		if ( isset( $resolved['url'] ) ) {
			$url = self::to_plain_text( $resolved['url'] );
			if ( '' === $url && ( is_string( $resolved['url'] ) || is_numeric( $resolved['url'] ) ) ) {
				$url = (string) $resolved['url'];
			}
			if ( is_array( $resolved['url'] ) ) {
				$url = self::to_plain_text( $resolved['url'] );
			}
		} elseif ( isset( $resolved['href'] ) ) {
			$url = self::to_plain_text( $resolved['href'] );
		}
		return array(
			'url'         => $url,
			'is_external' => ! empty( $resolved['is_external'] ),
			'nofollow'    => ! empty( $resolved['nofollow'] ),
		);
	}
}
