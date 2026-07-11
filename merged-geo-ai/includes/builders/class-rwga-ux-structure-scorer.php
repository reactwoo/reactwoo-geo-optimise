<?php
/**
 * UX structure scoring from normalized builder context.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scores page structure deterministically (no AI).
 */
class RWGA_UX_Structure_Scorer {

	/**
	 * @param array<string, mixed> $builder_context Context with classified sections.
	 * @return array<string, mixed>
	 */
	public static function score( array $builder_context ) {
		$sections = isset( $builder_context['sections'] ) && is_array( $builder_context['sections'] )
			? $builder_context['sections']
			: array();
		$widgets  = isset( $builder_context['widgets'] ) && is_array( $builder_context['widgets'] )
			? $builder_context['widgets']
			: array();
		$ctas     = isset( $builder_context['ctas'] ) && is_array( $builder_context['ctas'] )
			? $builder_context['ctas']
			: array();
		$forms    = isset( $builder_context['forms'] ) && is_array( $builder_context['forms'] )
			? $builder_context['forms']
			: array();

		$section_types = array();
		foreach ( $sections as $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$cls = isset( $sec['classification']['type'] ) ? (string) $sec['classification']['type'] : 'unknown';
			$section_types[] = $cls;
		}

		$hero_score            = self::score_hero( $sections, $section_types );
		$cta_score             = self::score_cta( $ctas, $section_types );
		$trust_score           = self::score_trust( $section_types, $widgets );
		$form_score            = self::score_form( $forms, $widgets );
		$content_clarity_score = self::score_content_clarity( $widgets, $sections );
		$structure_score       = self::score_hierarchy( $sections, $section_types );

		$scores = array(
			'hero_score'            => $hero_score,
			'cta_score'             => $cta_score,
			'trust_score'           => $trust_score,
			'form_score'            => $form_score,
			'content_clarity_score' => $content_clarity_score,
			'structure_score'       => $structure_score,
		);

		$overall = (
			$hero_score * 0.25
			+ $cta_score * 0.2
			+ $trust_score * 0.15
			+ $form_score * 0.1
			+ $content_clarity_score * 0.15
			+ $structure_score * 0.15
		);

		$recommendations = self::build_recommendations( $scores, $ctas, $section_types, $widgets );
		$detected_issues = self::detect_issues( $scores, $ctas, $section_types, $widgets );

		return array(
			'overall_score'     => (int) round( $overall ),
			'hero_score'        => (int) round( $hero_score ),
			'cta_score'         => (int) round( $cta_score ),
			'trust_score'       => (int) round( $trust_score ),
			'form_score'        => (int) round( $form_score ),
			'content_clarity_score' => (int) round( $content_clarity_score ),
			'structure_score'   => (int) round( $structure_score ),
			'recommendations'   => $recommendations,
			'detected_issues'   => $detected_issues,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $sections      Sections.
	 * @param array<int, string>               $section_types Types.
	 * @return float 0-100
	 */
	private static function score_hero( array $sections, array $section_types ) {
		$score = 20.0;
		if ( in_array( 'hero', $section_types, true ) ) {
			$score += 35;
		}
		foreach ( $sections as $sec ) {
			if ( ! is_array( $sec ) || empty( $sec['has_h1'] ) ) {
				continue;
			}
			$score += 15;
			if ( ! empty( $sec['has_cta'] ) ) {
				$score += 15;
			}
			if ( ! empty( $sec['has_media'] ) ) {
				$score += 10;
			}
			break;
		}
		return min( 100.0, $score );
	}

	/**
	 * @param array<int, array<string, mixed>> $ctas          CTAs.
	 * @param array<int, string>               $section_types Types.
	 * @return float
	 */
	private static function score_cta( array $ctas, array $section_types ) {
		$score = 15.0;
		if ( count( $ctas ) >= 1 ) {
			$score += 30;
		}
		if ( count( $ctas ) >= 2 ) {
			$score += 15;
		}
		if ( in_array( 'hero', $section_types, true ) && in_array( 'cta', $section_types, true ) ) {
			$score += 20;
		}
		$weak = 0;
		foreach ( $ctas as $cta ) {
			if ( ! is_array( $cta ) ) {
				continue;
			}
			if ( RWGA_Builder_Normalize::is_weak_cta_label( isset( $cta['label'] ) ? (string) $cta['label'] : '' ) ) {
				++$weak;
			}
		}
		if ( $weak > 0 && count( $ctas ) === $weak ) {
			$score -= 25;
		}
		return max( 0.0, min( 100.0, $score ) );
	}

	/**
	 * @param array<int, string>               $section_types Types.
	 * @param array<int, array<string, mixed>> $widgets       Widgets.
	 * @return float
	 */
	private static function score_trust( array $section_types, array $widgets ) {
		$score = 20.0;
		foreach ( array( 'trust', 'testimonials', 'logos' ) as $t ) {
			if ( in_array( $t, $section_types, true ) ) {
				$score += 25;
			}
		}
		foreach ( $widgets as $w ) {
			if ( ! is_array( $w ) ) {
				continue;
			}
			if ( in_array( $w['type'], array( 'testimonial', 'testimonial-carousel', 'icon-box' ), true ) ) {
				$score += 5;
			}
		}
		return min( 100.0, $score );
	}

	/**
	 * @param array<int, array<string, mixed>> $forms   Forms.
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @return float
	 */
	private static function score_form( array $forms, array $widgets ) {
		if ( empty( $forms ) ) {
			return 50.0;
		}
		$score = 55.0;
		foreach ( $widgets as $w ) {
			if ( empty( $w['is_form'] ) ) {
				continue;
			}
			$btn = isset( $w['settings']['button_text'] ) ? (string) $w['settings']['button_text'] : '';
			if ( '' !== trim( $btn ) && ! RWGA_Builder_Normalize::is_weak_cta_label( $btn ) ) {
				$score += 25;
			}
		}
		return min( 100.0, $score );
	}

	/**
	 * @param array<int, array<string, mixed>> $widgets  Widgets.
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @return float
	 */
	private static function score_content_clarity( array $widgets, array $sections ) {
		$headings = 0;
		$orphan_buttons = 0;
		foreach ( $widgets as $w ) {
			if ( ! is_array( $w ) ) {
				continue;
			}
			if ( 'heading' === $w['type'] ) {
				++$headings;
			}
			if ( ! empty( $w['is_cta'] ) && '' === trim( (string) $w['content'] ) ) {
				++$orphan_buttons;
			}
		}
		$score = 40.0 + min( 40.0, $headings * 8 );
		if ( $orphan_buttons > 0 ) {
			$score -= 15 * $orphan_buttons;
		}
		if ( count( $sections ) >= 3 ) {
			$score += 10;
		}
		return max( 0.0, min( 100.0, $score ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $sections      Sections.
	 * @param array<int, string>               $section_types Types.
	 * @return float
	 */
	private static function score_hierarchy( array $sections, array $section_types ) {
		$count = count( $sections );
		$score = 30.0;
		if ( $count >= 2 ) {
			$score += 20;
		}
		if ( $count >= 4 ) {
			$score += 15;
		}
		$unique = count( array_unique( $section_types ) );
		if ( $unique >= 3 ) {
			$score += 20;
		}
		if ( in_array( 'hero', $section_types, true ) ) {
			$score += 15;
		}
		return min( 100.0, $score );
	}

	/**
	 * @param array<string, float>             $scores        Sub-scores.
	 * @param array<int, array<string, mixed>> $ctas          CTAs.
	 * @param array<int, string>               $section_types Types.
	 * @param array<int, array<string, mixed>> $widgets       Widgets.
	 * @return array<int, string>
	 */
	private static function build_recommendations( array $scores, array $ctas, array $section_types, array $widgets ) {
		$recs = array();
		if ( $scores['hero_score'] < 60 ) {
			$recs[] = __( 'Strengthen the hero with a clear H1, supporting line, primary CTA, and optional media.', 'reactwoo-geo-ai' );
		}
		if ( $scores['cta_score'] < 55 ) {
			$recs[] = __( 'Add a visible primary CTA early and repeat near conversion points.', 'reactwoo-geo-ai' );
		}
		if ( $scores['trust_score'] < 50 ) {
			$recs[] = __( 'Add trust signals (logos, testimonials) before or near forms/checkout.', 'reactwoo-geo-ai' );
		}
		foreach ( $ctas as $cta ) {
			if ( RWGA_Builder_Normalize::is_weak_cta_label( isset( $cta['label'] ) ? (string) $cta['label'] : '' ) ) {
				$recs[] = __( 'Replace generic CTA labels like “Click here” with outcome-focused text.', 'reactwoo-geo-ai' );
				break;
			}
		}
		if ( ! in_array( 'faq', $section_types, true ) && count( $section_types ) > 5 ) {
			$recs[] = __( 'Long pages benefit from an FAQ section to reduce friction.', 'reactwoo-geo-ai' );
		}
		return array_values( array_unique( $recs ) );
	}

	/**
	 * @param array<string, float>             $scores        Sub-scores.
	 * @param array<int, array<string, mixed>> $ctas          CTAs.
	 * @param array<int, string>               $section_types Types.
	 * @param array<int, array<string, mixed>> $widgets       Widgets.
	 * @return array<int, array<string, mixed>>
	 */
	private static function detect_issues( array $scores, array $ctas, array $section_types, array $widgets ) {
		$issues = array();
		if ( empty( $ctas ) ) {
			$issues[] = array(
				'code'     => 'missing_cta',
				'severity' => 'high',
				'message'  => __( 'No CTA buttons detected on this page.', 'reactwoo-geo-ai' ),
			);
		}
		if ( $scores['trust_score'] < 45 ) {
			$issues[] = array(
				'code'     => 'missing_trust',
				'severity' => 'medium',
				'message'  => __( 'Few or no trust signals detected near conversion areas.', 'reactwoo-geo-ai' ),
			);
		}
		if ( ! in_array( 'hero', $section_types, true ) ) {
			$issues[] = array(
				'code'     => 'weak_hero',
				'severity' => 'medium',
				'message'  => __( 'No hero section pattern detected (H1 + CTA in first section).', 'reactwoo-geo-ai' ),
			);
		}
		foreach ( $widgets as $w ) {
			if ( ! empty( $w['is_cta'] ) && RWGA_Builder_Normalize::is_weak_cta_label( (string) $w['content'] ) ) {
				$issues[] = array(
					'code'      => 'weak_cta_label',
					'severity'  => 'low',
					'message'   => __( 'A CTA uses a weak generic label.', 'reactwoo-geo-ai' ),
					'widget_id' => isset( $w['id'] ) ? (string) $w['id'] : '',
				);
				break;
			}
		}
		return $issues;
	}
}
