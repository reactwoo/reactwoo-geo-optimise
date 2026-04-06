<?php
/**
 * Rewrite enqueued style/script URLs when they point at another host but the path is under /wp-content/.
 *
 * Fixes CORS/font failures after DB clones (e.g. staging still references production domain in Elementor CSS handles).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optional front-end URL normalisation for cloned/staging sites.
 */
class RWGO_Staging_Asset_Fix {

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! apply_filters( 'rwgo_fix_cross_origin_wp_content_urls', true ) ) {
			return;
		}
		add_filter( 'style_loader_src', array( __CLASS__, 'maybe_rewrite_host' ), 99, 2 );
		add_filter( 'script_loader_src', array( __CLASS__, 'maybe_rewrite_host' ), 99, 2 );
	}

	/**
	 * @param string|false $src    Source URL.
	 * @param string       $handle Style/script handle (unused).
	 * @return string|false
	 */
	public static function maybe_rewrite_host( $src, $handle = '' ) {
		unset( $handle );
		if ( ! is_string( $src ) || '' === $src ) {
			return $src;
		}
		$home = wp_parse_url( home_url() );
		if ( empty( $home['scheme'] ) || empty( $home['host'] ) ) {
			return $src;
		}
		$target_host = strtolower( (string) $home['host'] );
		$parts         = wp_parse_url( $src );
		if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return $src;
		}
		if ( strpos( $parts['path'], '/wp-content/' ) === false ) {
			return $src;
		}
		$src_host = strtolower( (string) $parts['host'] );
		if ( $src_host === $target_host ) {
			return $src;
		}
		$base = $home['scheme'] . '://' . $home['host'];
		if ( ! empty( $home['port'] ) ) {
			$base .= ':' . $home['port'];
		}
		$out = $base . $parts['path'];
		if ( ! empty( $parts['query'] ) ) {
			$out .= '?' . $parts['query'];
		}
		if ( ! empty( $parts['fragment'] ) ) {
			$out .= '#' . $parts['fragment'];
		}
		/**
		 * @param string $out   Rewritten URL.
		 * @param string $src   Original URL.
		 * @param array  $parts wp_parse_url( $src ).
		 */
		return apply_filters( 'rwgo_rewritten_wp_content_asset_url', $out, $src, $parts );
	}
}
