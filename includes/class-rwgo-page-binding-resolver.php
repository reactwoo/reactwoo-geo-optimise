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

		$show_on_front = (string) get_option( 'show_on_front', 'posts' );
		$page_on_front = (int) get_option( 'page_on_front', 0 );
		$page_for_posts = (int) get_option( 'page_for_posts', 0 );
		$shop_id        = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;

		$is_front = ( 'page' === $show_on_front && $page_on_front > 0 && $post_id === $page_on_front );
		$is_posts = ( 'page' === $show_on_front && $page_for_posts > 0 && $post_id === $page_for_posts );
		$is_shop  = ( $shop_id > 0 && $post_id === $shop_id );

		$rel = $is_front ? '/' : self::relative_path_for_post( $post_id );

		return array(
			'page_id'         => $post_id,
			'post_type'       => $post->post_type,
			'post_name'       => (string) $post->post_name,
			'relative_path'   => $rel,
			'is_front_page'   => $is_front,
			'is_posts_page'   => $is_posts,
			'is_shop_page'    => $is_shop,
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
	 * Canonical Control page ID when creating a test (aligns homepage selection with Reading settings).
	 *
	 * @param int $selected_post_id Admin-selected source page ID.
	 * @return int
	 */
	public static function canonical_source_page_id_for_new_test( $selected_post_id ) {
		$selected_post_id = (int) $selected_post_id;
		if ( $selected_post_id <= 0 ) {
			return 0;
		}
		$show_on_front = (string) get_option( 'show_on_front', 'posts' );
		$page_on_front = (int) get_option( 'page_on_front', 0 );
		if ( 'page' !== $show_on_front || $page_on_front <= 0 ) {
			return $selected_post_id;
		}
		if ( $selected_post_id === $page_on_front ) {
			return $page_on_front;
		}
		$fp_url = get_permalink( $page_on_front );
		$sel_url = get_permalink( $selected_post_id );
		if ( is_string( $fp_url ) && is_string( $sel_url ) && '' !== $fp_url && '' !== $sel_url ) {
			if ( self::urls_same_location( $fp_url, $sel_url ) ) {
				return $page_on_front;
			}
		}
		return $selected_post_id;
	}

	/**
	 * @param string $a Full URL.
	 * @param string $b Full URL.
	 * @return bool
	 */
	private static function urls_same_location( $a, $b ) {
		$pa = wp_parse_url( $a );
		$pb = wp_parse_url( $b );
		if ( ! is_array( $pa ) || ! is_array( $pb ) ) {
			return false;
		}
		$path_a = isset( $pa['path'] ) ? untrailingslashit( (string) $pa['path'] ) : '';
		$path_b = isset( $pb['path'] ) ? untrailingslashit( (string) $pb['path'] ) : '';
		if ( $path_a !== $path_b ) {
			return false;
		}
		$qa = isset( $pa['query'] ) ? (string) $pa['query'] : '';
		$qb = isset( $pb['query'] ) ? (string) $pb['query'] : '';
		return $qa === $qb;
	}

	/**
	 * Resolve a live post ID from a binding (special roles first, then ID, then path, then slug).
	 *
	 * @param array<string, mixed> $binding Keys: page_id, relative_path, post_name, post_type, is_front_page, ….
	 * @return int 0 if unknown.
	 */
	public static function resolve_post_id( array $binding ) {
		$show_on_front = (string) get_option( 'show_on_front', 'posts' );
		$page_on_front = (int) get_option( 'page_on_front', 0 );
		$page_for_posts = (int) get_option( 'page_for_posts', 0 );

		$is_front_flag = ! empty( $binding['is_front_page'] );
		$rp            = array_key_exists( 'relative_path', $binding ) ? (string) $binding['relative_path'] : null;
		$path_is_root  = ( is_string( $rp ) && '' === trim( $rp, '/' ) );

		if ( ! empty( $binding['is_posts_page'] ) && 'page' === $show_on_front && $page_for_posts > 0 ) {
			$p = get_post( $page_for_posts );
			if ( $p instanceof \WP_Post && 'trash' !== $p->post_status ) {
				return $page_for_posts;
			}
		}

		if ( ! empty( $binding['is_shop_page'] ) && function_exists( 'wc_get_page_id' ) ) {
			$sid = (int) wc_get_page_id( 'shop' );
			if ( $sid > 0 ) {
				$p = get_post( $sid );
				if ( $p instanceof \WP_Post && 'trash' !== $p->post_status ) {
					return $sid;
				}
			}
		}

		if ( ( $is_front_flag || $path_is_root ) && 'page' === $show_on_front && $page_on_front > 0 ) {
			$p = get_post( $page_on_front );
			if ( $p instanceof \WP_Post && 'trash' !== $p->post_status ) {
				return $page_on_front;
			}
		}

		$id = (int) ( $binding['page_id'] ?? 0 );
		if ( $id > 0 ) {
			$post = get_post( $id );
			if ( $post instanceof \WP_Post && 'trash' !== $post->post_status ) {
				if ( 'page' === $show_on_front && $page_on_front > 0 && $id !== $page_on_front ) {
					if ( $is_front_flag ) {
						return $page_on_front;
					}
					$home_url = get_permalink( $page_on_front );
					$this_url = get_permalink( $id );
					if ( is_string( $home_url ) && is_string( $this_url )
						&& self::urls_same_location( $home_url, $this_url ) ) {
						return $page_on_front;
					}
				}
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
