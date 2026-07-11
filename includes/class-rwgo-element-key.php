<?php
/**
 * Stable semantic element keys for measurement contracts.
 *
 * Logical keys (e.g. hero.primary_cta) stay aligned across Control and Variant B
 * even when Elementor physical element IDs differ.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize and derive data-rwgo-element-key values.
 */
class RWGO_Element_Key {

	const ATTR = 'data-rwgo-element-key';

	/**
	 * Sanitize a semantic key. Allows dots for hierarchy (unlike sanitize_key).
	 *
	 * @param string $key Raw key.
	 * @return string Empty when invalid.
	 */
	public static function sanitize( $key ) {
		$key = strtolower( trim( (string) $key ) );
		if ( '' === $key ) {
			return '';
		}
		$key = preg_replace( '/[^a-z0-9._-]+/', '-', $key );
		$key = is_string( $key ) ? $key : '';
		$key = trim( $key, '.-_' );
		$key = preg_replace( '/\.{2,}/', '.', $key );
		$key = is_string( $key ) ? $key : '';
		if ( '' === $key || strlen( $key ) > 96 ) {
			return '' === $key ? '' : substr( $key, 0, 96 );
		}
		return $key;
	}

	/**
	 * Map UI goal type to a short semantic prefix.
	 *
	 * @param string $ui_type Goal UI type.
	 * @return string
	 */
	public static function prefix_for_ui_type( $ui_type ) {
		$ui_type = sanitize_key( (string) $ui_type );
		$map     = array(
			'cta_click'        => 'cta',
			'navigation_click' => 'nav',
			'form_submit'      => 'form',
			'checkbox_optin'   => 'optin',
			'add_to_cart'      => 'cart',
			'begin_checkout'   => 'checkout',
			'purchase'         => 'purchase',
			'custom'           => 'custom',
			'page_visit'       => 'page',
		);
		/**
		 * @param string $prefix  Semantic prefix.
		 * @param string $ui_type UI goal type.
		 */
		$prefix = isset( $map[ $ui_type ] ) ? $map[ $ui_type ] : 'goal';
		return (string) apply_filters( 'rwgo_element_key_prefix', $prefix, $ui_type );
	}

	/**
	 * Resolve explicit key or derive from label + type (stable across variants when labels match).
	 *
	 * @param string $explicit Explicit key from builder settings (may be empty).
	 * @param string $label    Goal label.
	 * @param string $ui_type  UI goal type.
	 * @return string Sanitized key (never empty when label/type present).
	 */
	public static function resolve( $explicit, $label, $ui_type ) {
		$from_explicit = self::sanitize( $explicit );
		if ( '' !== $from_explicit ) {
			return $from_explicit;
		}
		$prefix = self::prefix_for_ui_type( $ui_type );
		$slug   = sanitize_title( (string) $label );
		$slug   = str_replace( '_', '-', $slug );
		if ( '' === $slug ) {
			$slug = 'unnamed';
		}
		return self::sanitize( $prefix . '.' . $slug );
	}
}
