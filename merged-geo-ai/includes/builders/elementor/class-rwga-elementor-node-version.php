<?php
/**
 * Per-node Elementor document version detection (V3 legacy vs V4 Atomic).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects whether an Elementor node is legacy V3 or Atomic V4.
 */
class RWGA_Elementor_Node_Version {

	const V3 = 'v3';
	const V4 = 'v4';

	/**
	 * Atomic widget type prefixes / known Atomic layout widgets.
	 *
	 * @var array<int, string>
	 */
	private static $atomic_widget_types = array(
		'e-heading',
		'e-paragraph',
		'e-button',
		'e-image',
		'e-svg',
		'e-divider',
		'e-video',
		'e-div-block',
		'e-flexbox',
		'e-grid',
		'e-tabs',
		'e-accordion',
		'e-form',
	);

	/**
	 * @param array<string, mixed> $node Elementor node.
	 * @return string v3|v4
	 */
	public static function detect( array $node ) {
		$widget_type = isset( $node['widgetType'] ) ? sanitize_key( (string) $node['widgetType'] ) : '';
		if ( '' !== $widget_type ) {
			if ( 0 === strpos( $widget_type, 'e-' ) || in_array( $widget_type, self::$atomic_widget_types, true ) ) {
				return self::V4;
			}
		}

		if ( ! empty( $node['styles'] ) && is_array( $node['styles'] ) ) {
			return self::V4;
		}

		if ( isset( $node['interactions'] ) && is_array( $node['interactions'] ) && array() !== $node['interactions'] ) {
			return self::V4;
		}

		if ( self::settings_look_typed( isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array() ) ) {
			return self::V4;
		}

		// Explicit version field used by some Atomic documents (non-empty, not classic "0.0" alone is weak — prefer other signals).
		if ( isset( $node['version'] ) && is_string( $node['version'] ) && '' !== trim( $node['version'] ) ) {
			$ver = trim( (string) $node['version'] );
			if ( preg_match( '/^0\./', $ver ) || 'atomic' === strtolower( $ver ) ) {
				// Only treat as V4 when paired with Atomic-ish structure.
				if ( '' !== $widget_type && 0 === strpos( $widget_type, 'e-' ) ) {
					return self::V4;
				}
			}
		}

		return self::V3;
	}

	/**
	 * @param array<string, mixed> $settings Settings bag.
	 * @return bool
	 */
	private static function settings_look_typed( array $settings ) {
		foreach ( $settings as $value ) {
			if ( is_array( $value ) && isset( $value['$$type'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a node is an Atomic layout container (section-like).
	 *
	 * @param array<string, mixed> $node Node.
	 * @return bool
	 */
	public static function is_atomic_layout( array $node ) {
		$widget_type = isset( $node['widgetType'] ) ? sanitize_key( (string) $node['widgetType'] ) : '';
		return in_array( $widget_type, array( 'e-div-block', 'e-flexbox', 'e-grid' ), true );
	}

	/**
	 * Known Atomic widget types for mapping.
	 *
	 * @return array<int, string>
	 */
	public static function known_atomic_widget_types() {
		return self::$atomic_widget_types;
	}
}
