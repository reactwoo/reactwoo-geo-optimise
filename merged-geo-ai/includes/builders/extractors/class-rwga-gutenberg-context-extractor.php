<?php
/**
 * Gutenberg semantic context — template and block pattern hints.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds Gutenberg template metadata to shared semantics.
 */
class RWGA_Gutenberg_Context_Extractor extends RWGA_Context_Extractor_Base {

	/**
	 * @return string
	 */
	public function get_builder_slug() {
		return 'gutenberg';
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $payload Builder payload.
	 * @return array<string, mixed>
	 */
	protected function extract_builder_meta( $post_id, array $payload ) {
		$post_id = (int) $post_id;
		$meta    = array(
			'template' => sanitize_key( (string) get_post_meta( $post_id, '_wp_page_template', true ) ),
		);

		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			$meta['block_count'] = count( isset( $payload['widgets'] ) && is_array( $payload['widgets'] ) ? $payload['widgets'] : array() );
			if ( function_exists( 'parse_blocks' ) ) {
				$parsed = parse_blocks( (string) $post->post_content );
				$meta['top_level_blocks'] = $this->top_level_block_names( is_array( $parsed ) ? $parsed : array() );
			}
		}

		return array_filter( $meta, static function ( $value ) {
			if ( is_array( $value ) ) {
				return array() !== $value;
			}
			return '' !== $value;
		} );
	}

	/**
	 * @param array<int, mixed> $blocks Parsed blocks.
	 * @return array<int, string>
	 */
	private function top_level_block_names( array $blocks ) {
		$names = array();
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || empty( $block['blockName'] ) ) {
				continue;
			}
			$name = (string) $block['blockName'];
			$parts = explode( '/', $name );
			$names[] = sanitize_key( end( $parts ) );
		}
		return array_values( array_unique( array_filter( $names ) ) );
	}
}
