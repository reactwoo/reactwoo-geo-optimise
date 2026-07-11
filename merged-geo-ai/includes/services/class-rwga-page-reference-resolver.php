<?php
/**
 * Resolve natural page references (homepage, checkout, etc.) to WP pages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Page_Reference_Resolver {

	/** @var array<string,string> */
	const ALIASES = array(
		'homepage'      => 'homepage',
		'home page'     => 'homepage',
		'home'          => 'homepage',
		'front page'    => 'homepage',
		'checkout'      => 'checkout',
		'cart'          => 'cart',
		'basket'        => 'cart',
		'shop page'     => 'shop',
		'shop'          => 'shop',
		'product page'  => 'product',
		'category page' => 'category',
		'landing page'  => 'landing',
	);

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<string,mixed>|null
	 */
	public static function detect( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		foreach ( self::ALIASES as $alias => $ref ) {
			if ( false !== strpos( $phrase, $alias ) ) {
				return self::resolve_ref( $ref, $alias );
			}
		}
		if ( preg_match( '/\bpage\s+["\']?([^"\']+)["\']?/i', $phrase, $m ) ) {
			return self::resolve_by_title( trim( $m[1] ) );
		}
		return null;
	}

	/**
	 * @param string $ref   Reference slug.
	 * @param string $label Display label.
	 * @return array<string,mixed>
	 */
	private static function resolve_ref( $ref, $label ) {
		$page_id = 0;
		if ( 'homepage' === $ref ) {
			$page_id = (int) get_option( 'page_on_front', 0 );
		} elseif ( function_exists( 'wc_get_page_id' ) ) {
			if ( 'checkout' === $ref ) {
				$page_id = (int) wc_get_page_id( 'checkout' );
			} elseif ( 'cart' === $ref ) {
				$page_id = (int) wc_get_page_id( 'cart' );
			} elseif ( 'shop' === $ref ) {
				$page_id = (int) wc_get_page_id( 'shop' );
			}
		}
		if ( $page_id < 0 ) {
			$page_id = 0;
		}
		return array(
			'type'              => 'page_reference',
			'value'             => $ref,
			'label'             => ucwords( $label ),
			'page_id'           => $page_id > 0 ? $page_id : null,
			'needs_resolution'  => $page_id <= 0,
		);
	}

	/**
	 * @param string $title Page title fragment.
	 * @return array<string,mixed>|null
	 */
	private static function resolve_by_title( $title ) {
		$title = RWGA_Local_Intent_Interpreter::normalise( $title );
		if ( '' === $title ) {
			return null;
		}
		$posts = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				's'                      => $title,
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$page_id = ! empty( $posts[0] ) ? (int) $posts[0]->ID : 0;
		return array(
			'type'             => 'page_reference',
			'value'            => $title,
			'label'            => $title,
			'page_id'          => $page_id > 0 ? $page_id : null,
			'needs_resolution' => $page_id <= 0,
		);
	}
}
