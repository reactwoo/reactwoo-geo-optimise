<?php
/**
 * UX persuasion intelligence — experience quality beyond structure inventory.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates messaging hierarchy, CTA effectiveness, trust, friction, and mobile UX heuristics.
 */
class RWGA_UX_Insight_Builder {

	const VERSION = '1.0.0';

	/**
	 * @param array<string, mixed> $builder_payload Output from {@see RWGA_Page_Context_Builder::build()}.
	 * @return array<string, mixed>
	 */
	public static function analyze( array $builder_payload ) {
		$sections      = isset( $builder_payload['sections'] ) && is_array( $builder_payload['sections'] ) ? $builder_payload['sections'] : array();
		$section_types = self::ordered_section_types( $sections );
		$ctas          = isset( $builder_payload['ctas'] ) && is_array( $builder_payload['ctas'] ) ? $builder_payload['ctas'] : array();
		$forms         = isset( $builder_payload['forms'] ) && is_array( $builder_payload['forms'] ) ? $builder_payload['forms'] : array();
		$widgets       = isset( $builder_payload['widgets'] ) && is_array( $builder_payload['widgets'] ) ? $builder_payload['widgets'] : array();
		$ux_scores     = isset( $builder_payload['ux_scores'] ) && is_array( $builder_payload['ux_scores'] ) ? $builder_payload['ux_scores'] : array();

		$hierarchy = self::analyze_messaging_hierarchy( $section_types );
		$cta       = self::analyze_cta_effectiveness( $ctas, $section_types, $ux_scores );
		$trust     = self::analyze_trust( $section_types, $widgets, $ux_scores, $section_types, $ctas );
		$friction  = self::analyze_friction( $ctas, $forms, $section_types, $widgets );
		$mobile    = self::analyze_mobile_experience( $sections, $widgets, $ctas, $forms );

		$result = array(
			'schema_version'      => self::VERSION,
			'messaging_hierarchy' => $hierarchy,
			'cta_effectiveness'   => $cta,
			'trust'               => $trust,
			'friction'            => $friction,
			'mobile_experience'   => $mobile,
			'analyzed_at_gmt'     => gmdate( 'c' ),
		);

		/**
		 * @param array<string, mixed> $result          UX insight output.
		 * @param array<string, mixed> $builder_payload Source payload.
		 */
		return apply_filters( 'rwga_ux_intelligence', $result, $builder_payload );
	}

	/**
	 * @param array<string, mixed> $ux_insights Analyzer output.
	 * @return string
	 */
	public static function format_summary( array $ux_insights ) {
		$parts = array();
		$cta   = isset( $ux_insights['cta_effectiveness'] ) && is_array( $ux_insights['cta_effectiveness'] ) ? $ux_insights['cta_effectiveness'] : array();
		if ( ! empty( $cta['primary_cta'] ) ) {
			$parts[] = sprintf(
				/* translators: 1: CTA label, 2: strength score */
				__( 'Primary CTA "%1$s" (strength %2$d)', 'reactwoo-geo-ai' ),
				(string) $cta['primary_cta'],
				(int) ( $cta['cta_strength'] ?? 0 )
			);
		}
		$trust = isset( $ux_insights['trust'] ) && is_array( $ux_insights['trust'] ) ? $ux_insights['trust'] : array();
		if ( isset( $trust['trust_score'] ) ) {
			$parts[] = sprintf(
				/* translators: %d: trust score */
				__( 'Trust %d/100', 'reactwoo-geo-ai' ),
				(int) $trust['trust_score']
			);
		}
		$friction = isset( $ux_insights['friction'] ) && is_array( $ux_insights['friction'] ) ? $ux_insights['friction'] : array();
		if ( ! empty( $friction['friction'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: low/medium/high */
				__( 'Friction: %s', 'reactwoo-geo-ai' ),
				(string) $friction['friction']
			);
		}
		return implode( ' — ', $parts );
	}

	/**
	 * @param array<string, mixed> $ux_insights Analyzer output.
	 * @param string               $entity_hash Page hash.
	 * @param string               $snapshot_hash Site snapshot hash.
	 * @return array<int, array<string, mixed>>
	 */
	public static function to_insight_rows( array $ux_insights, $entity_hash = '', $snapshot_hash = '' ) {
		$rows = array();
		$cta  = isset( $ux_insights['cta_effectiveness'] ) && is_array( $ux_insights['cta_effectiveness'] ) ? $ux_insights['cta_effectiveness'] : array();
		if ( ! empty( $cta['primary_cta'] ) ) {
			$rows[] = array(
				'insight_key'    => 'cta_effectiveness',
				'finding'        => sprintf(
					/* translators: 1: label, 2: strength, 3: visibility, 4: commitment */
					__( 'Primary CTA "%1$s" — strength %2$d, visibility %3$d, commitment %4$s.', 'reactwoo-geo-ai' ),
					(string) $cta['primary_cta'],
					(int) ( $cta['cta_strength'] ?? 0 ),
					(int) ( $cta['cta_visibility'] ?? 0 ),
					(string) ( $cta['commitment_level'] ?? 'medium' )
				),
				'severity'       => (int) ( $cta['cta_strength'] ?? 0 ) < 50 ? 'high' : 'low',
				'category'       => 'conversion',
				'insight_type'   => 'cta_effectiveness',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'scores'         => $cta,
			);
		}

		$trust = isset( $ux_insights['trust'] ) && is_array( $ux_insights['trust'] ) ? $ux_insights['trust'] : array();
		if ( ! empty( $trust ) ) {
			$gap_msg = '';
			if ( ! empty( $trust['trust_gap'] ) ) {
				$gap_msg = ' ' . sprintf(
					/* translators: %s: gap code */
					__( 'Gap: %s.', 'reactwoo-geo-ai' ),
					(string) $trust['trust_gap']
				);
			}
			$rows[] = array(
				'insight_key'    => 'trust_assessment',
				'finding'        => sprintf(
					/* translators: 1: score, 2: gap detail */
					__( 'Trust score %1$d/100.%2$s', 'reactwoo-geo-ai' ),
					(int) ( $trust['trust_score'] ?? 0 ),
					$gap_msg
				),
				'severity'       => (int) ( $trust['trust_score'] ?? 0 ) < 50 ? 'medium' : 'low',
				'category'       => 'trust',
				'insight_type'   => 'trust',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'scores'         => array( 'trust_score' => (int) ( $trust['trust_score'] ?? 0 ) ),
				'evidence'       => $trust,
			);
		}

		$hierarchy = isset( $ux_insights['messaging_hierarchy'] ) && is_array( $ux_insights['messaging_hierarchy'] ) ? $ux_insights['messaging_hierarchy'] : array();
		if ( ! empty( $hierarchy['message_order'] ) ) {
			$rows[] = array(
				'insight_key'    => 'message_hierarchy',
				'finding'        => sprintf(
					/* translators: %s: comma-separated message stages */
					__( 'Message flow: %s.', 'reactwoo-geo-ai' ),
					implode( ' → ', array_map( 'sanitize_key', (array) $hierarchy['message_order'] ) )
				),
				'severity'       => 'low',
				'category'       => 'ux',
				'insight_type'   => 'messaging_hierarchy',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'evidence'       => $hierarchy,
			);
		}

		$friction = isset( $ux_insights['friction'] ) && is_array( $ux_insights['friction'] ) ? $ux_insights['friction'] : array();
		if ( ! empty( $friction['friction'] ) ) {
			$rows[] = array(
				'insight_key'    => 'friction_assessment',
				'finding'        => sprintf(
					/* translators: 1: friction level, 2: choice complexity, 3: confidence required */
					__( 'Friction %1$s; choice complexity %2$s; confidence required %3$s.', 'reactwoo-geo-ai' ),
					(string) $friction['friction'],
					(string) ( $friction['choice_complexity'] ?? 'medium' ),
					(string) ( $friction['confidence_required'] ?? 'medium' )
				),
				'severity'       => 'high' === (string) ( $friction['friction'] ?? '' ) ? 'medium' : 'low',
				'category'       => 'conversion',
				'insight_type'   => 'friction',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'evidence'       => $friction,
			);
		}

		$mobile = isset( $ux_insights['mobile_experience'] ) && is_array( $ux_insights['mobile_experience'] ) ? $ux_insights['mobile_experience'] : array();
		if ( ! empty( $mobile ) ) {
			$rows[] = array(
				'insight_key'    => 'mobile_experience',
				'finding'        => sprintf(
					/* translators: 1: CTA visibility, 2: scroll depth, 3: density */
					__( 'Mobile: CTA visibility %1$d, scroll %2$s, density %3$s.', 'reactwoo-geo-ai' ),
					(int) ( $mobile['cta_visibility_mobile'] ?? 0 ),
					(string) ( $mobile['scroll_depth_estimate'] ?? 'medium' ),
					(string) ( $mobile['visual_density'] ?? 'medium' )
				),
				'severity'       => ! empty( $mobile['content_overload'] ) ? 'medium' : 'low',
				'category'       => 'ux',
				'insight_type'   => 'mobile',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'evidence'       => $mobile,
			);
		}

		return $rows;
	}

	/**
	 * @param array<int, string> $section_types Ordered section types.
	 * @return array{message_order: array<int, string>, ideal_match: bool}
	 */
	private static function analyze_messaging_hierarchy( array $section_types ) {
		$order = array();
		foreach ( $section_types as $type ) {
			$stage = self::section_type_to_message_stage( $type );
			if ( '' === $stage ) {
				continue;
			}
			if ( empty( $order ) || end( $order ) !== $stage ) {
				$order[] = $stage;
			}
		}

		$ideal = array( 'problem', 'solution', 'proof', 'cta' );
		$match = true;
		$idx   = 0;
		foreach ( $ideal as $stage ) {
			while ( isset( $order[ $idx ] ) && $order[ $idx ] !== $stage ) {
				++$idx;
			}
			if ( ! isset( $order[ $idx ] ) ) {
				$match = false;
				break;
			}
			++$idx;
		}

		return array(
			'message_order' => $order,
			'ideal_match'   => $match && count( $order ) >= 3,
		);
	}

	/**
	 * @param string $type Section type.
	 * @return string
	 */
	private static function section_type_to_message_stage( $type ) {
		$type = sanitize_key( (string) $type );
		$map  = array(
			'hero'         => 'problem',
			'content'      => 'problem',
			'features'     => 'solution',
			'benefits'     => 'solution',
			'testimonials' => 'proof',
			'trust'        => 'proof',
			'logos'        => 'proof',
			'gallery'      => 'proof',
			'pricing'      => 'cta',
			'cta'          => 'cta',
			'form'         => 'cta',
			'faq'          => 'proof',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : '';
	}

	/**
	 * @param array<int, array<string, mixed>> $ctas          CTAs.
	 * @param array<int, string>               $section_types Section types.
	 * @param array<string, mixed>             $ux_scores     Structure scores.
	 * @return array<string, mixed>
	 */
	private static function analyze_cta_effectiveness( array $ctas, array $section_types, array $ux_scores ) {
		$primary_label = '';
		if ( ! empty( $ctas[0]['label'] ) ) {
			$primary_label = trim( (string) $ctas[0]['label'] );
		}

		$strength = isset( $ux_scores['cta_score'] ) ? (int) $ux_scores['cta_score'] : 40;
		if ( '' !== $primary_label && ! RWGA_Builder_Normalize::is_weak_cta_label( $primary_label ) ) {
			$strength = min( 100, $strength + 10 );
		}
		if ( RWGA_Builder_Normalize::is_weak_cta_label( $primary_label ) ) {
			$strength = max( 0, $strength - 20 );
		}

		$visibility = 35;
		if ( in_array( 'hero', $section_types, true ) && ! empty( $ctas ) ) {
			$visibility += 35;
		}
		if ( count( $ctas ) === 1 ) {
			$visibility += 15;
		} elseif ( count( $ctas ) > 3 ) {
			$visibility -= 15;
		}

		return array(
			'primary_cta'       => $primary_label,
			'cta_strength'      => self::clamp( $strength ),
			'cta_visibility'    => self::clamp( $visibility ),
			'commitment_level'  => self::cta_commitment_level( $primary_label, $ctas, $section_types ),
			'competing_ctas'    => max( 0, count( $ctas ) - 1 ),
		);
	}

	/**
	 * @param string                             $primary_label Primary CTA label.
	 * @param array<int, array<string, mixed>> $ctas          All CTAs.
	 * @param array<int, string>                 $section_types Section types.
	 * @return string low|medium|high
	 */
	private static function cta_commitment_level( $primary_label, array $ctas, array $section_types ) {
		$label = strtolower( $primary_label );
		if ( preg_match( '/\b(buy|purchase|checkout|subscribe|sign up|get started|start trial|book demo|contact sales)\b/i', $label ) ) {
			return 'high';
		}
		if ( in_array( 'pricing', $section_types, true ) || in_array( 'form', $section_types, true ) ) {
			return 'high';
		}
		if ( preg_match( '/\b(learn more|read more|download|watch|see how)\b/i', $label ) ) {
			return 'low';
		}
		if ( count( $ctas ) > 2 ) {
			return 'medium';
		}
		return 'medium';
	}

	/**
	 * @param array<int, string>                 $section_types Section types.
	 * @param array<int, array<string, mixed>>   $widgets       Widgets.
	 * @param array<string, mixed>               $ux_scores     Scores.
	 * @param array<int, string>                 $types_ordered Ordered types (duplicate param kept for clarity in caller).
	 * @param array<int, array<string, mixed>>   $ctas          CTAs.
	 * @return array<string, mixed>
	 */
	private static function analyze_trust( array $section_types, array $widgets, array $ux_scores, array $types_ordered, array $ctas ) {
		$score   = isset( $ux_scores['trust_score'] ) ? (int) $ux_scores['trust_score'] : 35;
		$signals = array();
		foreach ( array( 'testimonials' => 'reviews', 'trust' => 'guarantees', 'logos' => 'logos', 'faq' => 'faq' ) as $type => $signal ) {
			if ( in_array( $type, $section_types, true ) ) {
				$signals[] = $signal;
			}
		}
		foreach ( $widgets as $w ) {
			if ( ! is_array( $w ) ) {
				continue;
			}
			if ( in_array( $w['type'], array( 'testimonial', 'testimonial-carousel' ), true ) && ! in_array( 'reviews', $signals, true ) ) {
				$signals[] = 'reviews';
			}
		}

		$trust_gap = '';
		$proof_idx = self::first_index_of_stage( $types_ordered, 'proof' );
		$cta_idx   = self::first_index_of_stage( $types_ordered, 'cta' );
		if ( $cta_idx >= 0 && ( $proof_idx < 0 || $proof_idx > $cta_idx ) && ! empty( $ctas ) ) {
			$trust_gap = 'proof_before_cta_missing';
		} elseif ( $score < 45 ) {
			$trust_gap = 'trust_signals_sparse';
		}

		return array(
			'trust_score' => self::clamp( $score ),
			'signals'     => array_values( array_unique( $signals ) ),
			'trust_gap'   => $trust_gap,
		);
	}

	/**
	 * @param array<int, string> $section_types Types in order.
	 * @param string             $stage         Message stage.
	 * @return int
	 */
	private static function first_index_of_stage( array $section_types, $stage ) {
		foreach ( $section_types as $i => $type ) {
			if ( self::section_type_to_message_stage( $type ) === $stage ) {
				return (int) $i;
			}
		}
		return -1;
	}

	/**
	 * @param array<int, array<string, mixed>> $ctas          CTAs.
	 * @param array<int, array<string, mixed>> $forms         Forms.
	 * @param array<int, string>               $section_types Types.
	 * @param array<int, array<string, mixed>> $widgets       Widgets.
	 * @return array<string, string>
	 */
	private static function analyze_friction( array $ctas, array $forms, array $section_types, array $widgets ) {
		$cta_count    = count( $ctas );
		$form_count   = count( $forms );
		$pricing_rows = in_array( 'pricing', $section_types, true ) ? 1 : 0;
		$widget_count = count( $widgets );

		$choice = 'low';
		if ( $cta_count > 3 || ( $pricing_rows && $cta_count > 1 ) ) {
			$choice = 'high';
		} elseif ( $cta_count > 1 ) {
			$choice = 'medium';
		}

		$confidence = 'medium';
		if ( $form_count > 0 || in_array( 'pricing', $section_types, true ) ) {
			$confidence = 'high';
		} elseif ( $cta_count <= 1 && ! in_array( 'form', $section_types, true ) ) {
			$confidence = 'low';
		}

		$friction = 'low';
		if ( $choice === 'high' || ( $confidence === 'high' && empty( array_intersect( $section_types, array( 'testimonials', 'trust', 'logos', 'faq' ) ) ) ) ) {
			$friction = 'high';
		} elseif ( $choice === 'medium' || $widget_count > 40 ) {
			$friction = 'medium';
		}

		return array(
			'friction'            => $friction,
			'choice_complexity'     => $choice,
			'confidence_required' => $confidence,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @param array<int, array<string, mixed>> $widgets  Widgets.
	 * @param array<int, array<string, mixed>> $ctas     CTAs.
	 * @param array<int, array<string, mixed>> $forms    Forms.
	 * @return array<string, mixed>
	 */
	private static function analyze_mobile_experience( array $sections, array $widgets, array $ctas, array $forms ) {
		$section_count = count( $sections );
		$widget_count  = count( $widgets );

		$cta_visibility = 40;
		if ( ! empty( $ctas ) ) {
			$cta_visibility += 25;
			foreach ( $sections as $i => $sec ) {
				if ( ! is_array( $sec ) || $i > 1 ) {
					break;
				}
				if ( ! empty( $sec['has_cta'] ) ) {
					$cta_visibility += 25;
					break;
				}
			}
		}

		$scroll = 'shallow';
		if ( $section_count >= 8 ) {
			$scroll = 'deep';
		} elseif ( $section_count >= 5 ) {
			$scroll = 'medium';
		}

		$density = 'low';
		if ( $widget_count > 45 ) {
			$density = 'high';
		} elseif ( $widget_count > 25 ) {
			$density = 'medium';
		}

		$form_usability = 'good';
		if ( ! empty( $forms ) && $widget_count > 35 ) {
			$form_usability = 'fair';
		}
		if ( ! empty( $forms ) && $section_count > 6 ) {
			$form_usability = 'poor';
		}

		return array(
			'cta_visibility_mobile'  => self::clamp( $cta_visibility ),
			'scroll_depth_estimate'  => $scroll,
			'visual_density'         => $density,
			'content_overload'       => $widget_count > 50 || $section_count > 10,
			'form_usability'         => $form_usability,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @return array<int, string>
	 */
	private static function ordered_section_types( array $sections ) {
		$out = array();
		foreach ( $sections as $sec ) {
			if ( ! is_array( $sec ) || empty( $sec['classification']['type'] ) ) {
				continue;
			}
			$out[] = sanitize_key( (string) $sec['classification']['type'] );
		}
		return $out;
	}

	/**
	 * @param int $value Score.
	 * @return int
	 */
	private static function clamp( $value ) {
		return max( 0, min( 100, (int) round( $value ) ) );
	}
}
