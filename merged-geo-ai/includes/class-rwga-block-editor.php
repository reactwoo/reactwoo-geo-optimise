<?php
/**
 * Block editor: variant-draft REST URL in document sidebar (pages only).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a lightweight block-editor script when REST + Geo Core expose the draft route.
 */
class RWGA_Block_Editor {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_block_assets' ) );
	}

	/**
	 * @return void
	 */
	public static function enqueue_block_assets() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'page' !== $screen->post_type ) {
			return;
		}
		if ( method_exists( $screen, 'is_block_editor' ) && ! $screen->is_block_editor() ) {
			return;
		}
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$url = function_exists( 'rwgc_get_rest_v1_url' ) ? rwgc_get_rest_v1_url( 'ai/variant-draft' ) : '';
		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}

		$handle = 'rwga-block-editor';
		wp_register_script(
			$handle,
			RWGA_URL . 'assets/js/block-editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-i18n' ),
			RWGA_VERSION,
			true
		);
		wp_localize_script(
			$handle,
			'rwgaBlockEditor',
			array(
				'variantDraftUrl' => $url,
			)
		);
		wp_enqueue_script( $handle );
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( $handle, 'reactwoo-geo-ai', RWGA_PATH . 'languages' );
		}
	}
}
