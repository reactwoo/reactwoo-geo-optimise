<?php
/**
 * Bounded Atomic style summaries (breakpoint / state) for AI context.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Summarizes Elementor Atomic `styles` without dumping full CSS.
 */
class RWGA_Elementor_Style_Summary {

	/**
	 * Keys useful for UX / a11y / responsive analysis.
	 *
	 * @var array<int, string>
	 */
	private static $interesting_props = array(
		'display',
		'flex-direction',
		'justify-content',
		'align-items',
		'gap',
		'grid-template-columns',
		'width',
		'max-width',
		'min-height',
		'padding',
		'margin',
		'font-size',
		'font-weight',
		'line-height',
		'color',
		'background-color',
		'background',
		'border-radius',
		'text-align',
		'opacity',
	);

	/**
	 * @param array<string, mixed> $styles Atomic styles object.
	 * @return array<string, mixed>
	 */
	public static function summarize( array $styles ) {
		$classes = array();
		$count   = 0;

		foreach ( $styles as $class_id => $definition ) {
			if ( $count >= 12 ) {
				break;
			}
			if ( ! is_array( $definition ) ) {
				continue;
			}
			$class_id = (string) $class_id;
			$variants = array();
			$props_src = isset( $definition['props'] ) && is_array( $definition['props'] )
				? $definition['props']
				: $definition;

			foreach ( $props_src as $breakpoint => $states ) {
				if ( ! is_array( $states ) ) {
					continue;
				}
				// Shape A: breakpoint => state => props
				$looks_like_states = false;
				foreach ( $states as $maybe_state => $maybe_props ) {
					if ( is_array( $maybe_props ) && self::looks_like_css_map( $maybe_props ) ) {
						$looks_like_states = true;
						$variants[ (string) $breakpoint ][ (string) $maybe_state ] = self::pick_props( $maybe_props );
					}
				}
				if ( ! $looks_like_states && self::looks_like_css_map( $states ) ) {
					// Shape B: flat props under class.
					$variants['desktop']['normal'] = self::pick_props( $states );
				}
			}

			if ( array() === $variants && self::looks_like_css_map( $definition ) ) {
				$variants['desktop']['normal'] = self::pick_props( $definition );
			}

			if ( array() === $variants ) {
				continue;
			}

			$classes[] = array(
				'id'       => $class_id,
				'scope'    => self::class_scope( $class_id ),
				'variants' => $variants,
			);
			++$count;
		}

		return array(
			'class_count' => count( $styles ),
			'classes'     => $classes,
		);
	}

	/**
	 * @param string $class_id Class id.
	 * @return string local|global|unknown
	 */
	public static function class_scope( $class_id ) {
		$id = (string) $class_id;
		if ( '' === $id ) {
			return 'unknown';
		}
		// Elementor often prefixes local style ids; global classes use longer/global-looking ids.
		if ( 0 === strpos( $id, 'e-global-' ) || 0 === strpos( $id, 'global-' ) ) {
			return 'global';
		}
		if ( 0 === strpos( $id, 'e-' ) || preg_match( '/^[a-z0-9]{6,}$/i', $id ) ) {
			return 'local';
		}
		return 'unknown';
	}

	/**
	 * @param array<string, mixed> $props Props map.
	 * @return bool
	 */
	private static function looks_like_css_map( array $props ) {
		foreach ( array_keys( $props ) as $key ) {
			$key = (string) $key;
			if ( in_array( $key, self::$interesting_props, true ) || false !== strpos( $key, '-' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $props Props.
	 * @return array<string, mixed>
	 */
	private static function pick_props( array $props ) {
		$out = array();
		foreach ( self::$interesting_props as $key ) {
			if ( ! array_key_exists( $key, $props ) ) {
				continue;
			}
			$val = RWGA_Elementor_Atomic_Prop_Resolver::resolve( $props[ $key ] );
			if ( is_scalar( $val ) || null === $val ) {
				$out[ $key ] = $val;
			} elseif ( is_array( $val ) ) {
				// Keep compact size/color objects.
				$out[ $key ] = self::compact_value( $val );
			}
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $val Value.
	 * @return mixed
	 */
	private static function compact_value( array $val ) {
		if ( isset( $val['size'], $val['unit'] ) ) {
			return array(
				'size' => $val['size'],
				'unit' => $val['unit'],
			);
		}
		if ( isset( $val['color'] ) ) {
			return array( 'color' => $val['color'] );
		}
		if ( count( $val ) <= 4 ) {
			return $val;
		}
		return array_slice( $val, 0, 4, true );
	}
}
