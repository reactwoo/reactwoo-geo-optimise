<?php
/**
 * Context extractor contract — meaning, not widget inventory.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transforms normalized builder payloads into semantic page context.
 */
interface RWGA_Context_Extractor_Interface {

	/**
	 * Builder slug this extractor handles.
	 *
	 * @return string
	 */
	public function get_builder_slug();

	/**
	 * Extract semantic context from a classified builder payload.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $payload Classified payload from RWGA_Page_Context_Builder.
	 * @return array<string, mixed>
	 */
	public function extract( $post_id, array $payload );
}
