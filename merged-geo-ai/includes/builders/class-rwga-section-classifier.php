<?php
/**
 * Deterministic UX section classification from normalized builder context.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classifies sections into hero, features, faq, etc.
 */
class RWGA_Section_Classifier {

	/**
	 * @var array<int, string>
	 */
	private static $types = array(
		'hero',
		'features',
		'benefits',
		'testimonials',
		'pricing',
		'faq',
		'cta',
		'form',
		'trust',
		'logos',
		'gallery',
		'content',
		'unknown',
	);

	/**
	 * @param array<string, mixed> $builder_context Normalized builder context.
	 * @return array<int, array<string, mixed>>
	 */
	public static function classify( array $builder_context ) {
		$sections = isset( $builder_context['sections'] ) && is_array( $builder_context['sections'] )
			? $builder_context['sections']
			: array();
		$widgets = isset( $builder_context['widgets'] ) && is_array( $builder_context['widgets'] )
			? $builder_context['widgets']
			: array();

		$widgets_by_section = array();
		foreach ( $widgets as $w ) {
			if ( ! is_array( $w ) ) {
				continue;
			}
			$sid = isset( $w['section_id'] ) ? (string) $w['section_id'] : '';
			if ( ! isset( $widgets_by_section[ $sid ] ) ) {
				$widgets_by_section[ $sid ] = array();
			}
			$widgets_by_section[ $sid ][] = $w;
		}

		$out = array();
		$total = count( $sections );
		foreach ( $sections as $i => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			$sid      = isset( $section['id'] ) ? (string) $section['id'] : '';
			$sec_w    = isset( $widgets_by_section[ $sid ] ) ? $widgets_by_section[ $sid ] : array();
			$result   = self::classify_one( $section, $sec_w, (int) $i, $total );
			$out[]    = array_merge(
				$section,
				array(
					'classification' => $result,
				)
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed>           $section Section row.
	 * @param array<int, array<string, mixed>> $widgets Section widgets.
	 * @param int                            $index   Section index.
	 * @param int                            $total   Total sections.
	 * @return array{type: string, confidence: float, signals: array<int, string>}
	 */
	public static function classify_one( array $section, array $widgets, $index, $total ) {
		$signals    = array();
		$scores     = array_fill_keys( self::$types, 0.0 );
		$is_first   = ( 0 === (int) $index );
		$is_last    = ( (int) $index === max( 0, (int) $total - 1 ) );
		$has_h1     = ! empty( $section['has_h1'] );
		$has_cta    = ! empty( $section['has_cta'] );
		$has_form   = ! empty( $section['has_form'] );
		$has_media  = ! empty( $section['has_media'] );

		$type_counts = array();
		foreach ( $widgets as $w ) {
			$t = isset( $w['type'] ) ? sanitize_key( (string) $w['type'] ) : '';
			if ( '' === $t ) {
				continue;
			}
			$type_counts[ $t ] = isset( $type_counts[ $t ] ) ? $type_counts[ $t ] + 1 : 1;
		}

		$icon_boxes   = (int) ( $type_counts['icon-box'] ?? 0 );
		$testimonials = (int) ( ( $type_counts['testimonial'] ?? 0 ) + ( $type_counts['testimonial-carousel'] ?? 0 ) );
		$pricing      = (int) ( $type_counts['price-table'] ?? 0 );
		$accordions   = (int) ( ( $type_counts['accordion'] ?? 0 ) + ( $type_counts['toggle'] ?? 0 ) );
		$images       = (int) ( ( $type_counts['image'] ?? 0 ) + ( $type_counts['image-gallery'] ?? 0 ) );
		$buttons      = (int) ( $type_counts['button'] ?? 0 );

		if ( $is_first ) {
			$signals[] = 'first_section';
			$scores['hero'] += 0.25;
		}
		if ( $has_h1 ) {
			$signals[] = 'has_h1';
			$scores['hero'] += 0.35;
		}
		if ( $has_cta && $is_first ) {
			$signals[] = 'has_cta';
			$scores['hero'] += 0.3;
		}
		if ( $has_media && $is_first ) {
			$scores['hero'] += 0.1;
		}

		if ( $has_form ) {
			$signals[] = 'has_form_widget';
			$scores['form'] += 0.85;
		}

		if ( $accordions >= 1 ) {
			$signals[] = 'accordion_or_toggle';
			$scores['faq'] += 0.8;
		}

		if ( $testimonials >= 1 ) {
			$signals[] = 'testimonial_widget';
			$scores['testimonials'] += 0.85;
		}

		if ( $pricing >= 1 ) {
			$signals[] = 'pricing_widget';
			$scores['pricing'] += 0.85;
		}

		if ( $icon_boxes >= 2 ) {
			$signals[] = 'repeated_icon_boxes';
			$scores['features'] += 0.7;
			$scores['benefits'] += 0.5;
		}

		if ( $images >= 3 && $buttons < 2 ) {
			$signals[] = 'image_row';
			$scores['logos'] += 0.55;
			$scores['gallery'] += 0.4;
		}

		if ( $has_cta && ! $is_first && $buttons >= 1 ) {
			$signals[] = 'cta_after_content';
			$scores['cta'] += 0.65;
		}
		if ( $is_last && $has_cta ) {
			$scores['cta'] += 0.25;
		}

		if ( $testimonials >= 1 || ( $images >= 2 && $icon_boxes >= 1 ) ) {
			$scores['trust'] += 0.5;
		}

		$heading = isset( $section['heading'] ) ? strtolower( (string) $section['heading'] ) : '';
		if ( str_contains( $heading, 'faq' ) || str_contains( $heading, 'question' ) ) {
			$signals[] = 'faq_heading';
			$scores['faq'] += 0.4;
		}
		if ( str_contains( $heading, 'pricing' ) || str_contains( $heading, 'plan' ) ) {
			$signals[] = 'pricing_heading';
			$scores['pricing'] += 0.35;
		}

		$best_type  = 'unknown';
		$best_score = 0.0;
		foreach ( $scores as $type => $score ) {
			if ( 'unknown' === $type ) {
				continue;
			}
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_type  = $type;
			}
		}

		if ( $best_score < 0.35 ) {
			$widget_count = isset( $section['widget_count'] ) ? (int) $section['widget_count'] : count( $widgets );
			if ( $widget_count > 0 ) {
				$best_type  = 'content';
				$best_score = 0.4;
				$signals[]  = 'default_content';
			} else {
				$best_type  = 'unknown';
				$best_score = 0.2;
			}
		}

		$confidence = min( 0.98, max( 0.2, $best_score ) );

		return array(
			'type'       => $best_type,
			'confidence' => round( $confidence, 2 ),
			'signals'    => array_values( array_unique( $signals ) ),
		);
	}
}
