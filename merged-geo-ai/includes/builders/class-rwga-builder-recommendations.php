<?php
/**
 * Builder-aware recommendation shaping from UX scores and parsed structure.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts detected issues into structured builder-targeted recommendations.
 */
class RWGA_Builder_Recommendations {

	/**
	 * @param array<string, mixed> $ai_context Payload from RWGA_Page_Context_Builder.
	 * @return array<int, array<string, mixed>>
	 */
	public static function from_context( array $ai_context ) {
		$builder = isset( $ai_context['builder'] ) ? sanitize_key( (string) $ai_context['builder'] ) : 'classic';
		$issues  = isset( $ai_context['detected_issues'] ) && is_array( $ai_context['detected_issues'] )
			? $ai_context['detected_issues']
			: array();
		$widgets = isset( $ai_context['widgets'] ) && is_array( $ai_context['widgets'] )
			? $ai_context['widgets']
			: array();
		$sections = isset( $ai_context['sections'] ) && is_array( $ai_context['sections'] )
			? $ai_context['sections']
			: array();

		$recs = array();
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$code = isset( $issue['code'] ) ? sanitize_key( (string) $issue['code'] ) : '';
			$rec  = self::recommendation_for_issue( $code, $builder, $issue, $widgets, $sections );
			if ( array() !== $rec ) {
				$recs[] = $rec;
			}
		}

		$ux = isset( $ai_context['ux_scores'] ) && is_array( $ai_context['ux_scores'] ) ? $ai_context['ux_scores'] : array();
		if ( isset( $ux['hero_score'] ) && (int) $ux['hero_score'] < 55 ) {
			$recs[] = self::base_rec(
				$builder,
				'improve_hierarchy',
				array(
					'section_id' => self::first_section_id( $sections ),
					'widget_id'  => self::first_widget_id( $widgets, 'heading' ),
					'widget_type'=> 'heading',
				),
				__( 'Hero lacks a strong headline + CTA pattern.', 'reactwoo-geo-ai' ),
				__( 'Add or refine H1, supporting text, and a primary CTA in the first section.', 'reactwoo-geo-ai' )
			);
		}

		/**
		 * Filter builder-aware recommendations.
		 *
		 * @param array<int, array<string, mixed>> $recs Recommendations.
		 * @param array<string, mixed>             $ai_context Context.
		 */
		$recs = apply_filters( 'rwga_builder_recommendations', $recs, $ai_context );
		return is_array( $recs ) ? $recs : array();
	}

	/**
	 * @param string                           $code     Issue code.
	 * @param string                           $builder  Builder slug.
	 * @param array<string, mixed>             $issue    Issue row.
	 * @param array<int, array<string, mixed>> $widgets  Widgets.
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @return array<string, mixed>
	 */
	private static function recommendation_for_issue( $code, $builder, array $issue, array $widgets, array $sections ) {
		switch ( $code ) {
			case 'missing_cta':
				return self::base_rec(
					$builder,
					'add_widget',
					array(
						'section_id'  => self::first_section_id( $sections ),
						'widget_id'   => '',
						'widget_type' => 'button',
					),
					isset( $issue['message'] ) ? (string) $issue['message'] : '',
					__( 'Add a primary CTA button in the hero or first content section.', 'reactwoo-geo-ai' )
				);
			case 'missing_trust':
				return self::base_rec(
					$builder,
					'add_trust_signal',
					array(
						'section_id'  => self::section_before_form( $sections ),
						'widget_id'   => '',
						'widget_type' => 'testimonial',
					),
					isset( $issue['message'] ) ? (string) $issue['message'] : '',
					__( 'Insert logos or testimonials before the form or final CTA.', 'reactwoo-geo-ai' )
				);
			case 'weak_cta_label':
				$wid = isset( $issue['widget_id'] ) ? (string) $issue['widget_id'] : '';
				$target = self::widget_target( $widgets, $wid );
				return self::base_rec(
					$builder,
					'update_cta',
					$target,
					isset( $issue['message'] ) ? (string) $issue['message'] : '',
					__( 'Use outcome-focused CTA copy (e.g. “Start free trial”).', 'reactwoo-geo-ai' )
				);
			case 'weak_hero':
				return self::base_rec(
					$builder,
					'improve_hierarchy',
					array(
						'section_id'  => self::first_section_id( $sections ),
						'widget_id'   => self::first_widget_id( $widgets, 'heading' ),
						'widget_type' => 'heading',
					),
					isset( $issue['message'] ) ? (string) $issue['message'] : '',
					__( 'Structure the first section as hero: H1, subhead, CTA, optional image.', 'reactwoo-geo-ai' )
				);
			default:
				return array();
		}
	}

	/**
	 * @param string               $builder Builder.
	 * @param string               $type    Recommendation type.
	 * @param array<string, mixed> $target  Target refs.
	 * @param string               $reason  Reason.
	 * @param string               $change  Suggested change.
	 * @return array<string, mixed>
	 */
	private static function base_rec( $builder, $type, array $target, $reason, $change ) {
		return array(
			'builder'                 => sanitize_key( (string) $builder ),
			'recommendation_type'     => sanitize_key( (string) $type ),
			'target'                  => $target,
			'reason'                  => (string) $reason,
			'suggested_change'        => (string) $change,
			'implementation_possible' => true,
			'risk_level'              => 'low',
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @return string
	 */
	private static function first_section_id( array $sections ) {
		foreach ( $sections as $sec ) {
			if ( is_array( $sec ) && ! empty( $sec['id'] ) ) {
				return (string) $sec['id'];
			}
		}
		return '';
	}

	/**
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @return string
	 */
	private static function section_before_form( array $sections ) {
		$form_idx = null;
		foreach ( $sections as $i => $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$cls = isset( $sec['classification']['type'] ) ? (string) $sec['classification']['type'] : '';
			if ( 'form' === $cls || ! empty( $sec['has_form'] ) ) {
				$form_idx = (int) $i;
				break;
			}
		}
		if ( null === $form_idx || $form_idx <= 0 ) {
			return self::first_section_id( $sections );
		}
		$prev = $sections[ $form_idx - 1 ];
		return is_array( $prev ) && ! empty( $prev['id'] ) ? (string) $prev['id'] : self::first_section_id( $sections );
	}

	/**
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @param string                           $type    Widget type.
	 * @return string
	 */
	private static function first_widget_id( array $widgets, $type ) {
		$type = sanitize_key( (string) $type );
		foreach ( $widgets as $w ) {
			if ( is_array( $w ) && isset( $w['type'] ) && $type === $w['type'] && ! empty( $w['id'] ) ) {
				return (string) $w['id'];
			}
		}
		return '';
	}

	/**
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @param string                           $id      Widget id.
	 * @return array<string, mixed>
	 */
	private static function widget_target( array $widgets, $id ) {
		foreach ( $widgets as $w ) {
			if ( ! is_array( $w ) || (string) $w['id'] !== (string) $id ) {
				continue;
			}
			return array(
				'section_id'  => isset( $w['section_id'] ) ? (string) $w['section_id'] : '',
				'widget_id'   => (string) $w['id'],
				'widget_type' => isset( $w['type'] ) ? (string) $w['type'] : '',
			);
		}
		return array(
			'section_id'  => '',
			'widget_id'   => (string) $id,
			'widget_type' => 'button',
		);
	}
}
