<?php
/**
 * Gutenberg: Geo Optimise panel for core/button + front-end attributes.
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
	 * @return void
	 */
	public static function init() {
		add_filter( 'register_block_type_args', array( __CLASS__, 'register_button_attributes' ), 10, 2 );
		add_filter( 'render_block', array( __CLASS__, 'render_button_block' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ) );
	}

	/**
	 * @param array<string, mixed> $args Block type args.
	 * @param string                 $name Block name.
	 * @return array<string, mixed>
	 */
	public static function register_button_attributes( $args, $name ) {
		if ( 'core/button' !== $name || ! is_array( $args ) ) {
			return $args;
		}
		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			$args['attributes'] = array();
		}
		$args['attributes']['rwgoGoalEnabled'] = array(
			'type'    => 'boolean',
			'default' => false,
		);
		$args['attributes']['rwgoGoalLabel'] = array(
			'type'    => 'string',
			'default' => '',
		);
		$args['attributes']['rwgoGoalType'] = array(
			'type'    => 'string',
			'default' => 'cta_click',
		);
		$args['attributes']['rwgoGoalNote'] = array(
			'type'    => 'string',
			'default' => '',
		);
		$args['attributes']['rwgoGoalId'] = array(
			'type'    => 'string',
			'default' => '',
		);
		$args['attributes']['rwgoHandlerId'] = array(
			'type'    => 'string',
			'default' => '',
		);
		return $args;
	}

	/**
	 * @param string               $block_content HTML.
	 * @param array<string, mixed> $block Block.
	 * @return string
	 */
	public static function render_button_block( $block_content, $block ) {
		if ( ! is_array( $block ) || ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) !== 'core/button' ) {
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
			$sig = wp_json_encode( $a );
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
			$label = __( 'Button', 'reactwoo-geo-optimise' );
		}
		$attrs = sprintf(
			' data-rwgo-goal-id="%s" data-rwgo-goal-label="%s" data-rwgo-goal-type="%s" data-rwgo-handler-id="%s" data-rwgo-builder="gutenberg" data-rwgo-element-fingerprint="rwgo_defined"',
			esc_attr( $gid ),
			esc_attr( $label ),
			esc_attr( $type ),
			esc_attr( $hid )
		);
		$block_content = preg_replace( '/<a\s/', '<a' . $attrs . ' ', $block_content, 1 );
		return is_string( $block_content ) ? $block_content : '';
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
	}
}
