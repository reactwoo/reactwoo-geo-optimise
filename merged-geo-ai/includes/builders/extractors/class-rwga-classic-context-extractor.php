<?php
/**
 * Classic editor semantic context.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal semantics for classic/default post content.
 */
class RWGA_Classic_Context_Extractor extends RWGA_Context_Extractor_Base {

	/**
	 * @return string
	 */
	public function get_builder_slug() {
		return 'classic';
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $payload Builder payload.
	 * @return array<string, mixed>
	 */
	protected function extract_builder_meta( $post_id, array $payload ) {
		unset( $payload );
		$post = get_post( (int) $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}
		return array(
			'post_type' => sanitize_key( (string) $post->post_type ),
		);
	}
}
