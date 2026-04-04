<?php
/**
 * Filter pages/posts/products for Create Test by test type (admin UI + server validation).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content picker catalog for experiments.
 */
class RWGO_Admin_Content_Catalog {

	const QUERY_LIMIT = 500;

	/**
	 * Flat choices for dropdowns / JSON (id + label).
	 *
	 * @param string $test_type page_ab|elementor_page|gutenberg_page|woo_product|custom_php.
	 * @return list<array{id:int,text:string}>
	 */
	public static function get_choices( $test_type ) {
		$test_type = sanitize_key( (string) $test_type );
		$posts     = self::get_posts_for_type( $test_type );
		$out       = array();
		foreach ( $posts as $p ) {
			if ( ! $p instanceof \WP_Post ) {
				continue;
			}
			$out[] = array(
				'id'   => (int) $p->ID,
				'text' => self::format_choice_label( $p ),
			);
		}
		return $out;
	}

	/**
	 * Whether a post ID is valid as Control or Variant B for the test type.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $test_type Test type key.
	 * @return bool
	 */
	public static function is_valid_for_test_type( $post_id, $test_type ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
			return false;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}
		return self::post_matches_test_type( $post, $test_type );
	}

	/**
	 * @param \WP_Post $post      Post.
	 * @param string   $test_type Test type.
	 * @return bool
	 */
	public static function post_matches_test_type( \WP_Post $post, $test_type ) {
		$test_type = sanitize_key( (string) $test_type );
		$pt        = $post->post_type;
		switch ( $test_type ) {
			case 'page_ab':
				return 'page' === $pt;
			case 'woo_product':
				return 'product' === $pt && class_exists( 'WooCommerce', false );
			case 'gutenberg_page':
				// Catalog may list all pages/posts when no block-only set exists; accept any page/post.
				return in_array( $pt, array( 'page', 'post' ), true );
			case 'elementor_page':
				// Catalog may fall back to all pages/posts when no Elementor entries exist.
				return in_array( $pt, array( 'page', 'post' ), true );
			case 'custom_php':
				return in_array( $pt, array( 'page', 'post', 'product' ), true );
			default:
				return false;
		}
	}

	/**
	 * @param string $test_type Test type.
	 * @return \WP_Post[]
	 */
	private static function get_posts_for_type( $test_type ) {
		switch ( $test_type ) {
			case 'page_ab':
				return self::query_simple( array( 'page' ) );
			case 'woo_product':
				if ( ! class_exists( 'WooCommerce', false ) ) {
					return array();
				}
				return self::query_simple( array( 'product' ) );
			case 'gutenberg_page':
				return self::filter_gutenberg( self::query_simple( array( 'page', 'post' ) ) );
			case 'elementor_page':
				$el = self::query_elementor();
				if ( ! empty( $el ) ) {
					return $el;
				}
				return self::query_simple( array( 'page', 'post' ) );
			case 'custom_php':
				$types = array( 'page', 'post' );
				if ( class_exists( 'WooCommerce', false ) ) {
					$types[] = 'product';
				}
				return self::query_simple( $types );
			default:
				return array();
		}
	}

	/**
	 * @param list<string> $post_types Post types.
	 * @return \WP_Post[]
	 */
	private static function query_simple( array $post_types ) {
		$q = new \WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => self::QUERY_LIMIT,
				'orderby'                  => 'title',
				'order'                    => 'ASC',
				'no_found_rows'            => true,
				'update_post_meta_cache'   => false,
				'update_post_term_cache'   => false,
			)
		);
		$posts = $q->posts;
		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * @return \WP_Post[]
	 */
	private static function query_elementor() {
		$q = new \WP_Query(
			array(
				'post_type'              => array( 'page', 'post' ),
				'post_status'            => 'publish',
				'posts_per_page'         => self::QUERY_LIMIT,
				'orderby'                  => 'title',
				'order'                    => 'ASC',
				'meta_key'                 => '_elementor_edit_mode',
				'meta_value'               => 'builder',
				'no_found_rows'            => true,
				'update_post_meta_cache'   => false,
				'update_post_term_cache'   => false,
			)
		);
		$posts = $q->posts;
		return is_array( $posts ) ? $posts : array();
	}

	/**
	 * @param \WP_Post[] $posts Posts.
	 * @return \WP_Post[]
	 */
	private static function filter_gutenberg( array $posts ) {
		$out = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$content = (string) $post->post_content;
			if ( function_exists( 'has_blocks' ) && has_blocks( $content ) ) {
				$out[] = $post;
			}
		}
		if ( ! empty( $out ) ) {
			return $out;
		}
		return $posts;
	}

	/**
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	private static function format_choice_label( \WP_Post $post ) {
		$type = $post->post_type;
		/* translators: 1: post title, 2: post type, 3: post ID */
		return sprintf( __( '%1$s (%2$s #%3$d)', 'reactwoo-geo-optimise' ), $post->post_title, $type, (int) $post->ID );
	}
}
