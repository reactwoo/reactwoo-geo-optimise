<?php
/**
 * Builder adapter contract for normalized page structure extraction.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts builder-specific layout into a shared schema for Geo AI.
 */
interface RWGA_Builder_Adapter_Interface {

	/**
	 * Whether this adapter can handle the post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function supports( $post_id );

	/**
	 * Builder slug: elementor, gutenberg, classic.
	 *
	 * @return string
	 */
	public function get_builder_name();

	/**
	 * Full normalized page context.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function extract_page_context( $post_id );

	/**
	 * Top-level layout sections.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_sections( $post_id );

	/**
	 * Flat widget/block list with parent references.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_widgets( $post_id );

	/**
	 * Content blocks (headings, paragraphs, media snippets).
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function extract_content( $post_id );
}
