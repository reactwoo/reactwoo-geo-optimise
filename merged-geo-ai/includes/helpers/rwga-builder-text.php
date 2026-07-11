<?php
/**
 * Extract human-readable text for AI workflows without sending builder markup.
 *
 * Strips Gutenberg block delimiters, shortcodes, and HTML tags so models analyse
 * copy only—not Elementor/Gutenberg/shortcode syntax (reduces “rewrite tags” risk).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recursively collect plain text from parsed blocks (innerHTML only).
 *
 * @param array<int, mixed> $blocks Parsed blocks.
 * @return string
 */
function rwga_blocks_inner_text( array $blocks ) {
	$parts = array();
	foreach ( $blocks as $b ) {
		if ( ! is_array( $b ) ) {
			continue;
		}
		if ( ! empty( $b['innerHTML'] ) && is_string( $b['innerHTML'] ) ) {
			$chunk = $b['innerHTML'];
			if ( function_exists( 'strip_shortcodes' ) ) {
				$chunk = strip_shortcodes( $chunk );
			}
			$chunk = wp_strip_all_tags( $chunk );
			$chunk = is_string( $chunk ) ? trim( $chunk ) : '';
			if ( '' !== $chunk ) {
				$parts[] = $chunk;
			}
		}
		if ( ! empty( $b['innerBlocks'] ) && is_array( $b['innerBlocks'] ) ) {
			$sub = rwga_blocks_inner_text( $b['innerBlocks'] );
			if ( is_string( $sub ) && '' !== trim( $sub ) ) {
				$parts[] = $sub;
			}
		}
	}
	return implode( "\n\n", array_filter( $parts ) );
}

/**
 * Strip block comments and tags from non-block (classic / hybrid) content.
 *
 * @param string $content Raw post content.
 * @return string
 */
function rwga_classic_text_for_ai( $content ) {
	$content = (string) $content;
	if ( function_exists( 'strip_shortcodes' ) ) {
		$content = strip_shortcodes( $content );
	}
	$content = preg_replace( '/<!--\s*(?:wp:|\/wp:)[\s\S]*?-->/u', '', $content );
	$content = wp_strip_all_tags( $content );
	return is_string( $content ) ? $content : '';
}

/**
 * Internal: text + which extraction path was used.
 *
 * @param string               $raw  Post content.
 * @param array<string, mixed> $args Optional: max_chars (int).
 * @return array{text: string, extraction: string}
 */
function rwga_extract_text_for_ai_with_source( $raw, array $args = array() ) {
	$raw       = (string) $raw;
	$max_chars = isset( $args['max_chars'] ) ? max( 200, min( 50000, (int) $args['max_chars'] ) ) : 8000;
	$post_id   = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;

	if ( $post_id > 0 && class_exists( 'RWGA_Elementor_Adapter', false ) && RWGA_Elementor_Adapter::post_has_elementor_data( $post_id ) ) {
		$adapter = new RWGA_Elementor_Adapter();
		$widgets = $adapter->extract_widgets( $post_id );
		$parts   = array();
		foreach ( $widgets as $w ) {
			if ( ! is_array( $w ) || '' === trim( (string) ( $w['content'] ?? '' ) ) ) {
				continue;
			}
			$parts[] = (string) $w['content'];
		}
		$plain = implode( ' ', $parts );
		$plain = is_string( $plain ) ? preg_replace( '/\s+/u', ' ', $plain ) : '';
		$plain = trim( (string) $plain );
		if ( strlen( $plain ) > $max_chars ) {
			$plain = substr( $plain, 0, $max_chars );
		}
		return array(
			'text'       => $plain,
			'extraction' => 'elementor_widgets',
		);
	}

	$plain       = '';
	$used_blocks = false;

	if ( function_exists( 'has_blocks' ) && has_blocks( $raw ) && function_exists( 'parse_blocks' ) ) {
		$parsed = parse_blocks( $raw );
		if ( is_array( $parsed ) && array() !== $parsed ) {
			$plain       = rwga_blocks_inner_text( $parsed );
			$used_blocks = true;
		}
	}

	if ( ! is_string( $plain ) || '' === trim( $plain ) ) {
		$plain = rwga_classic_text_for_ai( $raw );
		$extraction = $used_blocks ? 'gutenberg_fallback_classic' : 'classic';
	} else {
		$extraction = $used_blocks ? 'gutenberg_blocks' : 'classic';
	}

	$plain = is_string( $plain ) ? preg_replace( '/\s+/u', ' ', $plain ) : '';
	$plain = trim( (string) $plain );

	if ( strlen( $plain ) > $max_chars ) {
		$plain = substr( $plain, 0, $max_chars );
	}

	return array(
		'text'       => $plain,
		'extraction' => $extraction,
	);
}

/**
 * Reader-facing plain text for AI (no shortcodes, HTML, or block markup).
 *
 * @param string               $raw  Post content or HTML fragment.
 * @param array<string, mixed> $args Optional: max_chars (int).
 * @return string
 */
function rwga_extract_text_for_ai( $raw, array $args = array() ) {
	$pack = rwga_extract_text_for_ai_with_source( $raw, $args );
	$text = isset( $pack['text'] ) ? (string) $pack['text'] : '';
	$src  = isset( $pack['extraction'] ) ? (string) $pack['extraction'] : 'classic';

	/**
	 * Plain text extracted for AI (after builder stripping).
	 *
	 * @param string               $text  Extracted text.
	 * @param string               $raw   Original content.
	 * @param string               $src   gutenberg_blocks|gutenberg_fallback_classic|classic.
	 * @param array<string, mixed> $args  Args.
	 */
	return (string) apply_filters( 'rwga_extract_text_for_ai', $text, $raw, $src, $args );
}

/**
 * Minimal fields for remote AI (token-efficient): no duplicate raw HTML, only plain text + metadata.
 *
 * @param array<string, mixed> $ctx Full {@see RWGA_Page_Context::collect} array.
 * @return array<string, mixed>
 */
function rwga_ai_reading_bundle_from_page_context( array $ctx ) {
	$blocks = isset( $ctx['blocks'] ) && is_array( $ctx['blocks'] ) ? $ctx['blocks'] : array();
	$blocks = array_slice( array_values( array_filter( array_map( 'strval', $blocks ) ) ), 0, 25 );

	$url = isset( $ctx['permalink'] ) ? esc_url_raw( (string) $ctx['permalink'] ) : '';
	if ( $url && ! wp_http_validate_url( $url ) ) {
		$url = '';
	}

	$bundle = array(
		'title'                => isset( $ctx['title'] ) ? (string) $ctx['title'] : '',
		'permalink'            => $url,
		'excerpt'              => isset( $ctx['excerpt'] ) ? wp_strip_all_tags( (string) $ctx['excerpt'] ) : '',
		'content_plain'        => isset( $ctx['content_plain'] ) ? (string) $ctx['content_plain'] : '',
		'word_count'           => isset( $ctx['word_count'] ) ? (int) $ctx['word_count'] : 0,
		'builder'              => isset( $ctx['builder'] ) ? sanitize_key( (string) $ctx['builder'] ) : '',
		'content_plain_source' => isset( $ctx['content_plain_source'] ) ? sanitize_key( (string) $ctx['content_plain_source'] ) : '',
		'block_names'          => $blocks,
	);

	if ( ! empty( $ctx['builder_context'] ) && is_array( $ctx['builder_context'] ) && class_exists( 'RWGA_Page_Context_Builder', false ) ) {
		$bundle['builder_context'] = RWGA_Page_Context_Builder::compact_for_api( $ctx['builder_context'] );
	}

	/**
	 * @param array<string, mixed> $bundle Compact reading bundle for the API.
	 * @param array<string, mixed> $ctx    Original page context.
	 */
	$out = apply_filters( 'rwga_ai_reading_bundle', $bundle, $ctx );
	return is_array( $out ) ? $out : $bundle;
}
