<?php
/**
 * Normalised page/post context for bounded workflows (no remote calls).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects editor-safe context for UX/SEO workflows.
 *
 * `content_plain` is reader-facing text only (Gutenberg inner text, shortcodes stripped, HTML tags removed).
 */
class RWGA_Page_Context {

	/**
	 * Build context for a post ID. Omits heavy HTML by default; extend via filter.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $args    Optional: content_max_chars (int).
	 * @return array<string, mixed>
	 */
	public static function collect( $post_id, array $args = array() ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return array();
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$readable = current_user_can( 'read_post', $post_id );

		$max_chars = isset( $args['content_max_chars'] ) ? max( 500, min( 12000, (int) $args['content_max_chars'] ) ) : 8000;

		$plain     = '';
		$plain_src = '';
		if ( $readable && function_exists( 'rwga_extract_text_for_ai_with_source' ) ) {
			$pack = rwga_extract_text_for_ai_with_source(
				(string) $post->post_content,
				array(
					'max_chars' => $max_chars,
					'post_id'   => $post_id,
				)
			);
			$plain_src = isset( $pack['extraction'] ) ? (string) $pack['extraction'] : '';
			$plain     = isset( $pack['text'] ) ? (string) $pack['text'] : '';
			$plain     = (string) apply_filters(
				'rwga_extract_text_for_ai',
				$plain,
				(string) $post->post_content,
				$plain_src,
				array_merge( $args, array( 'max_chars' => $max_chars ) )
			);
		}

		$word_count = 0;
		if ( $readable && is_string( $plain ) && '' !== trim( $plain ) ) {
			$parts = preg_split( '/\s+/u', trim( $plain ), -1, PREG_SPLIT_NO_EMPTY );
			$word_count = is_array( $parts ) ? count( $parts ) : 0;
		}

		$builder = 'classic';
		$blocks  = array();
		if ( class_exists( 'RWGA_Builder_Registry', false ) ) {
			$builder = RWGA_Builder_Registry::detect_builder_name( $post_id );
		}
		$has_blk = function_exists( 'has_blocks' ) && has_blocks( $post->post_content );
		if ( $has_blk && function_exists( 'parse_blocks' ) ) {
			$parsed = parse_blocks( $post->post_content );
			if ( is_array( $parsed ) ) {
				$n = 0;
				foreach ( $parsed as $b ) {
					if ( ! is_array( $b ) ) {
						continue;
					}
					$name = isset( $b['blockName'] ) && is_string( $b['blockName'] ) ? $b['blockName'] : '';
					if ( '' === $name ) {
						continue;
					}
					$blocks[] = $name;
					++$n;
					if ( $n >= 40 ) {
						break;
					}
				}
			}
		}

		$builder_context = array();
		if ( class_exists( 'RWGA_Page_Context_Builder', false ) ) {
			$builder_context = RWGA_Page_Context_Builder::build( $post_id );
		}

		$permalink = get_permalink( $post );
		if ( ! is_string( $permalink ) ) {
			$permalink = '';
		}

		$thumb = false;
		if ( $readable && has_post_thumbnail( $post_id ) ) {
			$thumb = true;
		}

		if ( $readable && 'elementor' === $builder && class_exists( 'RWGA_Builder_Registry', false ) ) {
			$el_ctx = RWGA_Builder_Registry::get_page_context( $post_id );
			if ( ! empty( $el_ctx['widgets'] ) && is_array( $el_ctx['widgets'] ) ) {
				$parts = array();
				foreach ( $el_ctx['widgets'] as $w ) {
					if ( ! is_array( $w ) || '' === trim( (string) ( $w['content'] ?? '' ) ) ) {
						continue;
					}
					$parts[] = (string) $w['content'];
				}
				if ( ! empty( $parts ) ) {
					$plain     = RWGA_Builder_Normalize::trim_text( implode( ' ', $parts ), $max_chars );
					$plain_src = 'elementor_widgets';
					$parts_wc  = preg_split( '/\s+/u', trim( $plain ), -1, PREG_SPLIT_NO_EMPTY );
					$word_count = is_array( $parts_wc ) ? count( $parts_wc ) : 0;
				}
			}
		}

		$ctx = array(
			'post_id'              => $post_id,
			'title'                => get_the_title( $post ),
			'permalink'            => $permalink,
			'post_type'            => $post->post_type,
			'status'               => $post->post_status,
			'excerpt'              => $readable ? wp_strip_all_tags( (string) $post->post_excerpt ) : '',
			'content_plain'        => $readable ? (string) $plain : '',
			'content_plain_source' => $readable ? (string) $plain_src : '',
			'word_count'           => (int) $word_count,
			'builder'              => $builder,
			'blocks'                 => $blocks,
			'has_featured_image'     => $thumb,
			'accessible'             => $readable,
			'builder_context'        => $builder_context,
		);

		if ( function_exists( 'rwgc_get_context_snapshot' ) ) {
			$runtime_context = rwgc_get_context_snapshot();
			if ( is_array( $runtime_context ) ) {
				$attribution = isset( $runtime_context['attribution'] ) && is_array( $runtime_context['attribution'] )
					? $runtime_context['attribution']
					: array();
				$matched_profile = isset( $runtime_context['matched_profile'] ) ? $runtime_context['matched_profile'] : null;
				$ctx['profile_context'] = array(
					'matched_profile' => $matched_profile,
					'source'          => isset( $attribution['source'] ) ? (string) $attribution['source'] : '',
					'medium'          => isset( $attribution['medium'] ) ? (string) $attribution['medium'] : '',
					'campaign'        => isset( $attribution['campaign'] ) ? (string) $attribution['campaign'] : '',
					'content'         => isset( $attribution['content'] ) ? (string) $attribution['content'] : '',
					'term'            => isset( $attribution['term'] ) ? (string) $attribution['term'] : '',
					'gclid'           => isset( $attribution['gclid'] ) ? (string) $attribution['gclid'] : '',
				);
			}
		}

		/**
		 * Enrich or trim page context before workflow payloads.
		 *
		 * @param array<string, mixed> $ctx     Context.
		 * @param WP_Post               $post    Post object.
		 * @param array<string, mixed> $args    Collector args.
		 */
		return apply_filters( 'rwga_page_context', $ctx, $post, $args );
	}
}
