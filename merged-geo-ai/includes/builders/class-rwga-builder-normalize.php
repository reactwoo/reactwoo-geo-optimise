<?php
/**
 * Shared helpers for normalized builder output.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Factory helpers for adapter payloads.
 */
class RWGA_Builder_Normalize {

	/**
	 * Empty page context skeleton.
	 *
	 * @param string $builder Builder slug.
	 * @param int    $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function empty_page_context( $builder, $post_id ) {
		return array(
			'builder'                    => sanitize_key( (string) $builder ),
			'post_id'                    => (int) $post_id,
			'page_title'                 => $post_id > 0 ? get_the_title( $post_id ) : '',
			'sections'                   => array(),
			'widgets'                    => array(),
			'content_blocks'             => array(),
			'ctas'                       => array(),
			'forms'                      => array(),
			'media'                      => array(),
			'raw_builder_meta_available' => false,
		);
	}

	/**
	 * Normalized widget row.
	 *
	 * @param array<string, mixed> $args Widget fields.
	 * @return array<string, mixed>
	 */
	public static function widget_row( array $args ) {
		$settings = isset( $args['settings'] ) && is_array( $args['settings'] ) ? $args['settings'] : array();
		$content  = isset( $args['content'] ) ? (string) $args['content'] : '';

		return array(
			'id'         => isset( $args['id'] ) ? (string) $args['id'] : '',
			'type'       => isset( $args['type'] ) ? sanitize_key( (string) $args['type'] ) : '',
			'name'       => isset( $args['name'] ) ? (string) $args['name'] : '',
			'section_id' => isset( $args['section_id'] ) ? (string) $args['section_id'] : '',
			'parent_id'  => isset( $args['parent_id'] ) ? (string) $args['parent_id'] : '',
			'content'    => $content,
			'settings'   => $settings,
			'controls'   => isset( $args['controls'] ) && is_array( $args['controls'] ) ? $args['controls'] : array(),
			'is_cta'     => ! empty( $args['is_cta'] ),
			'is_form'    => ! empty( $args['is_form'] ),
		);
	}

	/**
	 * Normalized section row.
	 *
	 * @param array<string, mixed> $args Section fields.
	 * @return array<string, mixed>
	 */
	public static function section_row( array $args ) {
		return array(
			'id'           => isset( $args['id'] ) ? (string) $args['id'] : '',
			'type'         => isset( $args['type'] ) ? sanitize_key( (string) $args['type'] ) : 'section',
			'index'        => isset( $args['index'] ) ? (int) $args['index'] : 0,
			'widget_ids'   => isset( $args['widget_ids'] ) && is_array( $args['widget_ids'] ) ? array_values( array_map( 'strval', $args['widget_ids'] ) ) : array(),
			'heading'      => isset( $args['heading'] ) ? (string) $args['heading'] : '',
			'has_h1'       => ! empty( $args['has_h1'] ),
			'has_cta'      => ! empty( $args['has_cta'] ),
			'has_form'     => ! empty( $args['has_form'] ),
			'has_media'    => ! empty( $args['has_media'] ),
			'widget_count' => isset( $args['widget_count'] ) ? (int) $args['widget_count'] : 0,
		);
	}

	/**
	 * Detect generic weak CTA labels.
	 *
	 * @param string $text Button label.
	 * @return bool
	 */
	public static function is_weak_cta_label( $text ) {
		$text = strtolower( trim( (string) $text ) );
		if ( '' === $text ) {
			return true;
		}
		$weak = array( 'click here', 'read more', 'learn more', 'submit', 'go', 'here', 'link' );
		return in_array( $text, $weak, true );
	}

	/**
	 * CTA widget/block types across builders.
	 *
	 * @return array<int, string>
	 */
	public static function cta_widget_types() {
		return array( 'button', 'call-to-action', 'cta', 'slides', 'price-table', 'e-button' );
	}

	/**
	 * Form widget/block types.
	 *
	 * @return array<int, string>
	 */
	public static function form_widget_types() {
		return array( 'form', 'wpforms', 'gravityforms', 'contact-form-7', 'ninja-forms', 'fluentform' );
	}

	/**
	 * Heading levels from HTML snippet.
	 *
	 * @param string $html HTML fragment.
	 * @return array<int, string>
	 */
	public static function headings_from_html( $html ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return array();
		}
		$out = array();
		if ( preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$out[] = array(
					'level' => (int) $match[1],
					'text'  => trim( wp_strip_all_tags( $match[2] ) ),
				);
			}
		}
		return $out;
	}

	/**
	 * Trim string for AI payloads.
	 *
	 * @param string $text    Text.
	 * @param int    $max_len Max length.
	 * @return string
	 */
	public static function trim_text( $text, $max_len = 500 ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( strlen( $text ) > $max_len ) {
			return substr( $text, 0, $max_len ) . '…';
		}
		return $text;
	}

	/**
	 * Normalize a builder color value to #rrggbb when possible.
	 *
	 * @param mixed $value Raw color from builder settings.
	 * @return string Empty when not parseable.
	 */
	public static function normalize_color_value( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value, $m ) ) {
			$hex = strtolower( $m[1] );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			return '#' . $hex;
		}
		if ( preg_match( '/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $value, $m ) ) {
			return sprintf( '#%02x%02x%02x', (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		return '';
	}

	/**
	 * Interpret color purpose for intelligence (not exact design tokens).
	 *
	 * @param string $hex Normalized #rrggbb.
	 * @return string Role slug: primary_action|trust|warning|neutral|accent.
	 */
	public static function interpret_color_role( $hex ) {
		$hex = self::normalize_color_value( $hex );
		if ( '' === $hex ) {
			return 'neutral';
		}
		$r = hexdec( substr( $hex, 1, 2 ) );
		$g = hexdec( substr( $hex, 3, 2 ) );
		$b = hexdec( substr( $hex, 5, 2 ) );
		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$delta = $max - $min;

		if ( $delta < 18 && $max < 90 ) {
			return 'neutral';
		}
		if ( $delta < 18 ) {
			return 'trust';
		}

		if ( $r >= 200 && $g >= 120 && $b < 80 ) {
			return 'warning';
		}
		if ( $g >= $r + 20 && $g >= $b + 10 ) {
			return 'trust';
		}
		if ( $r >= $g + 15 && $r >= $b + 15 ) {
			return 'primary_action';
		}
		if ( $b >= $r + 10 && $b >= $g + 10 ) {
			return 'trust';
		}
		return 'accent';
	}
}
