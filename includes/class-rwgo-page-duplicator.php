<?php
/**
 * Duplicate pages/posts for variant B (Elementor + Gutenberg meta preserved).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page / post duplication.
 */
class RWGO_Page_Duplicator {

	/**
	 * @param int $post_id Source post.
	 * @return int|\WP_Error New post ID.
	 */
	public static function duplicate( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'rwgo_dup_missing', __( 'Source page not found.', 'reactwoo-geo-optimise' ) );
		}

		$new_post = array(
			'post_title'   => $post->post_title . ' — ' . __( 'Variant B', 'reactwoo-geo-optimise' ),
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => 'draft',
			'post_type'      => $post->post_type,
			'post_author'    => get_current_user_id() ? get_current_user_id() : (int) $post->post_author,
			'post_name'      => '',
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'menu_order'     => (int) $post->menu_order,
		);

		$new_id = wp_insert_post( wp_slash( $new_post ), true );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		$new_id = (int) $new_id;

		self::copy_post_meta( $post_id, $new_id );

		$thumb = get_post_thumbnail_id( $post_id );
		if ( $thumb ) {
			set_post_thumbnail( $new_id, $thumb );
		}

		/**
		 * After a variant page is duplicated.
		 *
		 * @param int $new_id New post ID.
		 * @param int $source_id Source post ID.
		 */
		do_action( 'rwgo_variant_page_duplicated', $new_id, $post_id );

		return $new_id;
	}

	/**
	 * @param int $source_id Source post ID.
	 * @param int $dest_id Destination post ID.
	 * @return void
	 */
	private static function copy_post_meta( $source_id, $dest_id ) {
		$meta = get_post_meta( $source_id );
		if ( ! is_array( $meta ) ) {
			return;
		}
		$skip_keys = array( '_edit_lock', '_edit_last' );
		foreach ( $meta as $key => $values ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			if ( in_array( $key, $skip_keys, true ) ) {
				continue;
			}
			if ( ! is_array( $values ) ) {
				continue;
			}
			foreach ( $values as $v ) {
				add_post_meta( $dest_id, $key, maybe_unserialize( $v ) );
			}
		}
	}
}
