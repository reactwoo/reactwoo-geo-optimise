<?php
/**
 * Single entry point for AI-ready page context (builder-aware).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepares trimmed builder context for workflows and remote API.
 */
class RWGA_Page_Context_Builder {

	/**
	 * Max sections/widgets in AI payload.
	 */
	const MAX_SECTIONS = 20;
	const MAX_WIDGETS  = 60;
	const MAX_CTAS     = 15;

	/**
	 * Build full AI payload for a post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $args    Optional: include_debug (bool).
	 * @return array<string, mixed>
	 */
	public static function build( $post_id, array $args = array() ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return array();
		}

		$builder_ctx = RWGA_Builder_Registry::get_page_context( $post_id );
		if ( array() === $builder_ctx ) {
			return array();
		}

		$classified = RWGA_Section_Classifier::classify( $builder_ctx );
		$builder_ctx['sections'] = $classified;

		$ux_scores = RWGA_UX_Structure_Scorer::score( $builder_ctx );

		$page_type = self::infer_page_type( $classified );

		$payload = array(
			'builder'         => isset( $builder_ctx['builder'] ) ? (string) $builder_ctx['builder'] : '',
			'page_type'       => $page_type,
			'post_id'         => $post_id,
			'page_title'      => isset( $builder_ctx['page_title'] ) ? (string) $builder_ctx['page_title'] : '',
			'sections'        => self::trim_sections( $classified ),
			'widgets'         => self::trim_widgets( isset( $builder_ctx['widgets'] ) ? $builder_ctx['widgets'] : array() ),
			'content_blocks'  => self::trim_content_blocks( isset( $builder_ctx['content_blocks'] ) ? $builder_ctx['content_blocks'] : array() ),
			'ctas'            => array_slice( isset( $builder_ctx['ctas'] ) ? $builder_ctx['ctas'] : array(), 0, self::MAX_CTAS ),
			'forms'           => isset( $builder_ctx['forms'] ) ? $builder_ctx['forms'] : array(),
			'media'           => array_slice( isset( $builder_ctx['media'] ) ? $builder_ctx['media'] : array(), 0, 20 ),
			'ux_scores'       => $ux_scores,
			'detected_issues' => isset( $ux_scores['detected_issues'] ) ? $ux_scores['detected_issues'] : array(),
		);

		if ( class_exists( 'RWGA_Context_Extractor_Registry', false ) ) {
			$payload['builder_semantics'] = RWGA_Context_Extractor_Registry::extract( $post_id, $payload );
		}

		/**
		 * Filter AI page context payload before workflows/API.
		 *
		 * @param array<string, mixed> $payload Full payload.
		 * @param int                  $post_id Post ID.
		 * @param array<string, mixed> $args    Builder args.
		 */
		$payload = apply_filters( 'rwga_ai_page_context', $payload, $post_id, $args );
		return is_array( $payload ) ? $payload : array();
	}

	/**
	 * Compact bundle for remote API (token-efficient).
	 *
	 * @param array<string, mixed> $payload Full payload from build().
	 * @return array<string, mixed>
	 */
	public static function compact_for_api( array $payload ) {
		$sections_summary = array();
		foreach ( isset( $payload['sections'] ) && is_array( $payload['sections'] ) ? $payload['sections'] : array() as $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$cls = isset( $sec['classification'] ) && is_array( $sec['classification'] ) ? $sec['classification'] : array();
			$sections_summary[] = array(
				'id'         => isset( $sec['id'] ) ? (string) $sec['id'] : '',
				'type'       => isset( $cls['type'] ) ? (string) $cls['type'] : 'unknown',
				'confidence' => isset( $cls['confidence'] ) ? (float) $cls['confidence'] : 0,
				'heading'    => isset( $sec['heading'] ) ? RWGA_Builder_Normalize::trim_text( (string) $sec['heading'], 80 ) : '',
				'has_cta'    => ! empty( $sec['has_cta'] ),
			);
		}

		$widgets_summary = array();
		foreach ( isset( $payload['widgets'] ) && is_array( $payload['widgets'] ) ? array_slice( $payload['widgets'], 0, 30 ) : array() as $w ) {
			if ( ! is_array( $w ) ) {
				continue;
			}
			$widgets_summary[] = array(
				'id'         => isset( $w['id'] ) ? (string) $w['id'] : '',
				'type'       => isset( $w['type'] ) ? (string) $w['type'] : '',
				'section_id' => isset( $w['section_id'] ) ? (string) $w['section_id'] : '',
				'content'    => RWGA_Builder_Normalize::trim_text( isset( $w['content'] ) ? (string) $w['content'] : '', 120 ),
				'is_cta'     => ! empty( $w['is_cta'] ),
			);
		}

		$ux = isset( $payload['ux_scores'] ) && is_array( $payload['ux_scores'] ) ? $payload['ux_scores'] : array();

		$semantics = array();
		if ( class_exists( 'RWGA_Context_Extractor_Base', false ) && ! empty( $payload['builder_semantics'] ) && is_array( $payload['builder_semantics'] ) ) {
			$semantics = RWGA_Context_Extractor_Base::compact_for_api( $payload['builder_semantics'] );
		}

		return array(
			'builder'          => isset( $payload['builder'] ) ? (string) $payload['builder'] : '',
			'page_type'        => isset( $payload['page_type'] ) ? (string) $payload['page_type'] : '',
			'sections'         => $sections_summary,
			'widgets'          => $widgets_summary,
			'ctas'             => isset( $payload['ctas'] ) ? array_slice( $payload['ctas'], 0, 10 ) : array(),
			'forms'            => isset( $payload['forms'] ) ? $payload['forms'] : array(),
			'ux_scores'        => array(
				'overall_score' => isset( $ux['overall_score'] ) ? (int) $ux['overall_score'] : 0,
				'hero_score'    => isset( $ux['hero_score'] ) ? (int) $ux['hero_score'] : 0,
				'cta_score'     => isset( $ux['cta_score'] ) ? (int) $ux['cta_score'] : 0,
				'trust_score'   => isset( $ux['trust_score'] ) ? (int) $ux['trust_score'] : 0,
			),
			'detected_issues'  => array_slice( isset( $payload['detected_issues'] ) ? $payload['detected_issues'] : array(), 0, 8 ),
			'builder_semantics'=> $semantics,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $sections Classified sections.
	 * @return string
	 */
	private static function infer_page_type( array $sections ) {
		$types = array();
		foreach ( $sections as $sec ) {
			if ( ! is_array( $sec ) || empty( $sec['classification']['type'] ) ) {
				continue;
			}
			$types[] = (string) $sec['classification']['type'];
		}
		if ( in_array( 'form', $types, true ) || in_array( 'pricing', $types, true ) ) {
			return 'landing_page';
		}
		if ( in_array( 'hero', $types, true ) && count( $types ) >= 4 ) {
			return 'landing_page';
		}
		return 'content_page';
	}

	/**
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @return array<int, array<string, mixed>>
	 */
	private static function trim_sections( array $sections ) {
		return array_slice( $sections, 0, self::MAX_SECTIONS );
	}

	/**
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @return array<int, array<string, mixed>>
	 */
	private static function trim_widgets( array $widgets ) {
		$out = array();
		foreach ( array_slice( $widgets, 0, self::MAX_WIDGETS ) as $w ) {
			if ( ! is_array( $w ) ) {
				continue;
			}
			$w['content']  = RWGA_Builder_Normalize::trim_text( isset( $w['content'] ) ? (string) $w['content'] : '', 300 );
			$w['settings'] = isset( $w['settings'] ) && is_array( $w['settings'] ) ? $w['settings'] : array();
			unset( $w['controls'] );
			$out[] = $w;
		}
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $blocks Blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private static function trim_content_blocks( array $blocks ) {
		$out = array();
		foreach ( array_slice( $blocks, 0, 40 ) as $b ) {
			if ( ! is_array( $b ) ) {
				continue;
			}
			$b['text'] = RWGA_Builder_Normalize::trim_text( isset( $b['text'] ) ? (string) $b['text'] : '', 300 );
			$out[]     = $b;
		}
		return $out;
	}
}
