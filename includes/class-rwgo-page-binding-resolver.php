<?php
/**
 * Resolve experiment page IDs from stored snapshots when raw IDs are stale (clone, import, staging).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stable locators + resolution for control / variant page bindings.
 */
class RWGO_Page_Binding_Resolver {

	/**
	 * Build a portable snapshot for a post (for config source_page / variant rows).
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function snapshot_for_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return array();
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'trash' === $post->post_status ) {
			return array();
		}

		return array(
			'page_id'       => $post_id,
			'post_type'     => $post->post_type,
			'post_name'     => (string) $post->post_name,
			'relative_path' => self::relative_path_for_post( $post_id ),
		);
	}

	/**
	 * Public URL path (relative to site root path), e.g. `/` or `/about/`.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function relative_path_for_post( $post_id ) {
		$url = get_permalink( (int) $post_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return '/';
		}
		if ( function_exists( 'wp_make_link_relative' ) ) {
			$rel = wp_make_link_relative( $url );
			if ( is_string( $rel ) && '' !== $rel ) {
				return '/' === $rel[0] ? $rel : '/' . $rel;
			}
		}
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return is_string( $path ) && '' !== $path ? $path : '/';
	}

	/**
	 * Resolve a live post ID from a binding (ID first, then path, then slug).
	 *
	 * @param array<string, mixed> $binding Keys: page_id, relative_path, post_name, post_type.
	 * @return int 0 if unknown.
	 */
	public static function resolve_post_id( array $binding ) {
		$id = (int) ( $binding['page_id'] ?? 0 );
		if ( $id > 0 ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post && 'trash' !== $post->post_status ) {
				return $id;
			}
		}

		$path = isset( $binding['relative_path'] ) ? (string) $binding['relative_path'] : '';
		if ( '' !== $path ) {
			$path = '/' . trim( $path, '/' );
			if ( '/' === $path || '' === trim( $path, '/' ) ) {
				$url = home_url( '/' );
			} else {
				$url = home_url( $path );
			}
			$resolved = (int) url_to_postid( $url );
			if ( $resolved > 0 ) {
				return $resolved;
			}
		}

		$slug = isset( $binding['post_name'] ) ? (string) $binding['post_name'] : '';
		$type = isset( $binding['post_type'] ) ? sanitize_key( (string) $binding['post_type'] ) : 'page';
		if ( '' !== $slug ) {
			$post = get_page_by_path( $slug, OBJECT, $type );
			if ( $post instanceof \WP_Post && 'trash' !== $post->post_status ) {
				return (int) $post->ID;
			}
		}

		return 0;
	}
}
