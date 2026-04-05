<?php
/**
 * Gutenberg: Geo Optimise panel for CTA-capable blocks + front-end attributes.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block attributes + render filter + editor script.
 */
class RWGO_Gutenberg_Goals {

	/**
	 * Blocks that support Geo Optimise goal attributes + inspector UI.
	 *
	 * @return list<string>
	 */
	public static function goal_block_names() {
		$names = array(
			'core/button',
			'core/read-more',
			'core/navigation-link',
			'core/image',
			'core/media-text',
			'core/cover',
			'core/file',
			'core/social-link',
			'core/site-logo',
			'core/query-pagination-next',
		);
		if ( class_exists( 'WooCommerce', false ) ) {
			$names[] = 'woocommerce/product-button';
			$names[] = 'woocommerce/product-image';
			$names[] = 'woocommerce/add-to-cart-form';
		}
		/**
		 * Block names (incl. third-party) that expose Geo Optimise goal controls.
		 *
		 * @param list<string> $names Block names.
		 */
		$names = apply_filters( 'rwgo_gutenberg_goal_block_names', $names );
		$names = array_values( array_unique( array_map( 'sanitize_text_field', $names ) ) );
		return $names;
	}

	/**
	 * Shared custom attributes for goal-capable blocks.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function goal_attributes_schema() {
		return array(
			'rwgoGoalEnabled' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'rwgoGoalLabel'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'rwgoGoalType'    => array(
				'type'    => 'string',
				'default' => 'cta_click',
			),
			'rwgoGoalNote'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'rwgoGoalId'      => array(
				'type'    => 'string',
				'default' => '',
			),
			'rwgoHandlerId'   => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'register_block_type_args', array( __CLASS__, 'register_block_attributes' ), 10, 2 );
		add_filter( 'render_block', array( __CLASS__, 'render_block' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ) );
	}

	/**
	 * @param array<string, mixed> $args Block type args.
	 * @param string                 $name Block name.
	 * @return array<string, mixed>
	 */
	public static function register_block_attributes( $args, $name ) {
		if ( ! is_array( $args ) || ! in_array( $name, self::goal_block_names(), true ) ) {
			return $args;
		}
		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}
		foreach ( self::goal_attributes_schema() as $key => $schema ) {
			$args['attributes'][ $key ] = $schema;
		}
		return $args;
	}

	/**
	 * @param string               $block_content HTML.
	 * @param array<string, mixed> $block Block.
	 * @return string
	 */
	public static function render_block( $block_content, $block ) {
		if ( ! is_array( $block ) || ! is_string( $block_content ) || '' === $block_content ) {
			return $block_content;
		}
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( ! in_array( $name, self::goal_block_names(), true ) ) {
			return $block_content;
		}
		$a = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		if ( empty( $a['rwgoGoalEnabled'] ) ) {
			return $block_content;
		}
		$gid = isset( $a['rwgoGoalId'] ) ? sanitize_key( (string) $a['rwgoGoalId'] ) : '';
		$hid = isset( $a['rwgoHandlerId'] ) ? sanitize_key( (string) $a['rwgoHandlerId'] ) : '';
		$post_id = get_the_ID();
		if ( $post_id <= 0 ) {
			return $block_content;
		}
		if ( '' === $gid || '' === $hid ) {
			$sig = wp_json_encode( array( 'n' => $name, 'a' => $a ) );
			$h   = hash( 'sha256', 'rwgo|gb|' . $post_id . '|' . ( is_string( $sig ) ? $sig : '' ) );
			if ( '' === $gid ) {
				$gid = 'goal_' . substr( $h, 0, 14 );
			}
			if ( '' === $hid ) {
				$hid = 'hdl_' . substr( $h, 14, 14 );
			}
		}
		$label = isset( $a['rwgoGoalLabel'] ) ? sanitize_text_field( (string) $a['rwgoGoalLabel'] ) : '';
		$type  = isset( $a['rwgoGoalType'] ) ? sanitize_key( (string) $a['rwgoGoalType'] ) : 'cta_click';
		if ( '' === $label ) {
			$label = __( 'Block goal', 'reactwoo-geo-optimise' );
		}
		$attr_pairs = array(
			'data-rwgo-goal-id'             => $gid,
			'data-rwgo-goal-label'          => $label,
			'data-rwgo-goal-type'           => $type,
			'data-rwgo-handler-id'          => $hid,
			'data-rwgo-builder'             => 'gutenberg',
			'data-rwgo-element-fingerprint' => 'rwgo_defined',
		);
		$ui_t = $type;
		if ( 'form_submit' === $ui_t ) {
			return self::inject_first_tag( $block_content, array( 'form', 'FORM' ), $attr_pairs );
		}
		$link_first = array( 'a', 'A', 'button', 'BUTTON' );
		$out        = self::inject_first_tag( $block_content, $link_first, $attr_pairs );
		return is_string( $out ) ? $out : $block_content;
	}

	/**
	 * Inject data-* attributes into the first matching tag (WP_HTML_Tag_Processor or regex fallback).
	 *
	 * @param string                       $html HTML.
	 * @param list<string>                 $prefer_tags Tag names to try in order.
	 * @param array<string, string>        $attr_pairs data-* attributes.
	 * @return string
	 */
	private static function inject_first_tag( $html, array $prefer_tags, array $attr_pairs ) {
		$want = array();
		foreach ( $prefer_tags as $pt ) {
			$want[ strtoupper( (string) $pt ) ] = true;
		}
		if ( class_exists( 'WP_HTML_Tag_Processor', false ) ) {
			$p = new WP_HTML_Tag_Processor( $html );
			while ( $p->next_tag() ) {
				$t = strtoupper( (string) $p->get_tag() );
				if ( $want && ! isset( $want[ $t ] ) ) {
					continue;
				}
				foreach ( $attr_pairs as $aname => $aval ) {
					$p->set_attribute( $aname, $aval );
				}
				return $p->get_updated_html();
			}
			// No preferred tag: first opening tag.
			$p2 = new WP_HTML_Tag_Processor( $html );
			if ( $p2->next_tag() ) {
				foreach ( $attr_pairs as $aname => $aval ) {
					$p2->set_attribute( $aname, $aval );
				}
				return $p2->get_updated_html();
			}
		}
		// Regex: first <a ...> or <form ...>
		if ( preg_match( '/<a\s/i', $html ) ) {
			$chunk = '';
			foreach ( $attr_pairs as $aname => $aval ) {
				$chunk .= ' ' . $aname . '="' . esc_attr( $aval ) . '"';
			}
			return (string) preg_replace( '/<a\s/i', '<a' . $chunk . ' ', $html, 1 );
		}
		if ( preg_match( '/<form\s/i', $html ) ) {
			$chunk = '';
			foreach ( $attr_pairs as $aname => $aval ) {
				$chunk .= ' ' . $aname . '="' . esc_attr( $aval ) . '"';
			}
			return (string) preg_replace( '/<form\s/i', '<form' . $chunk . ' ', $html, 1 );
		}
		return $html;
	}

	/**
	 * @return void
	 */
	public static function enqueue_editor() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_enqueue_script(
			'rwgo-block-goals',
			RWGO_URL . 'admin/js/rwgo-block-goals.js',
			array(
				'wp-plugins',
				'wp-edit-post',
				'wp-element',
				'wp-components',
				'wp-compose',
				'wp-hooks',
				'wp-block-editor',
				'wp-i18n',
			),
			RWGO_VERSION,
			true
		);
		wp_set_script_translations( 'rwgo-block-goals', 'reactwoo-geo-optimise' );
		wp_localize_script(
			'rwgo-block-goals',
			'rwgoBlockGoals',
			array(
				'blockNames' => self::goal_block_names(),
			)
		);
	}
}
