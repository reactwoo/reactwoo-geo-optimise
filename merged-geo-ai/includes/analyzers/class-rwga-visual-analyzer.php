<?php
/**
 * Visual emphasis intelligence — attention flow and CTA emphasis, not colour values alone.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analyses visual hierarchy signals available from normalized builder metadata.
 */
class RWGA_Visual_Analyzer {

	const VERSION = '1.0.0';

	/**
	 * @param array<string, mixed> $builder_payload Builder payload.
	 * @return array<string, mixed>
	 */
	public static function analyze( array $builder_payload ) {
		$sections = isset( $builder_payload['sections'] ) && is_array( $builder_payload['sections'] ) ? $builder_payload['sections'] : array();
		$widgets  = isset( $builder_payload['widgets'] ) && is_array( $builder_payload['widgets'] ) ? $builder_payload['widgets'] : array();
		$ctas     = isset( $builder_payload['ctas'] ) && is_array( $builder_payload['ctas'] ) ? $builder_payload['ctas'] : array();
		$ux       = isset( $builder_payload['ux_scores'] ) && is_array( $builder_payload['ux_scores'] ) ? $builder_payload['ux_scores'] : array();

		$attention   = self::analyze_attention_flow( $sections );
		$cta_emphasis = self::analyze_cta_emphasis( $widgets, $ctas, $sections, $ux );
		$competition  = self::analyze_visual_competition( $sections, $widgets, $ctas );
		$colours      = self::analyze_colour_meaning( $widgets );

		$result = array(
			'schema_version'    => self::VERSION,
			'attention_flow'    => $attention,
			'cta_emphasis'      => $cta_emphasis,
			'visual_competition'=> $competition,
			'colour_roles'      => $colours,
			'analyzed_at_gmt'   => gmdate( 'c' ),
		);

		/**
		 * @param array<string, mixed> $result          Visual intelligence output.
		 * @param array<string, mixed> $builder_payload Source payload.
		 */
		return apply_filters( 'rwga_visual_intelligence', $result, $builder_payload );
	}

	/**
	 * @param array<string, mixed> $visual Visual analyzer output.
	 * @return string
	 */
	public static function format_summary( array $visual ) {
		$cta = isset( $visual['cta_emphasis'] ) && is_array( $visual['cta_emphasis'] ) ? $visual['cta_emphasis'] : array();
		$parts = array();
		if ( isset( $cta['primary_cta_emphasis'] ) ) {
			$parts[] = sprintf(
				/* translators: %d: emphasis score */
				__( 'CTA emphasis %d/100', 'reactwoo-geo-ai' ),
				(int) $cta['primary_cta_emphasis']
			);
		}
		$comp = isset( $visual['visual_competition'] ) && is_array( $visual['visual_competition'] ) ? $visual['visual_competition'] : array();
		if ( isset( $comp['focus_conflicts'] ) && (int) $comp['focus_conflicts'] > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: conflict count */
				__( '%d focus conflicts', 'reactwoo-geo-ai' ),
				(int) $comp['focus_conflicts']
			);
		}
		$flow = isset( $visual['attention_flow'] ) && is_array( $visual['attention_flow'] ) ? $visual['attention_flow'] : array();
		if ( ! empty( $flow['stages'] ) && is_array( $flow['stages'] ) ) {
			$parts[] = implode( ' → ', array_map( 'sanitize_key', $flow['stages'] ) );
		}
		return implode( ' — ', $parts );
	}

	/**
	 * @param array<string, mixed> $visual        Output.
	 * @param string               $entity_hash   Hash.
	 * @param string               $snapshot_hash Snapshot hash.
	 * @return array<int, array<string, mixed>>
	 */
	public static function to_insight_rows( array $visual, $entity_hash = '', $snapshot_hash = '' ) {
		$rows = array();
		$cta  = isset( $visual['cta_emphasis'] ) && is_array( $visual['cta_emphasis'] ) ? $visual['cta_emphasis'] : array();
		if ( ! empty( $cta ) ) {
			$rows[] = array(
				'insight_key'    => 'visual_cta_emphasis',
				'finding'        => sprintf(
					/* translators: 1: primary emphasis, 2: secondary competition */
					__( 'Primary CTA emphasis %1$d/100; secondary CTA competition %2$d/100.', 'reactwoo-geo-ai' ),
					(int) ( $cta['primary_cta_emphasis'] ?? 0 ),
					(int) ( $cta['secondary_cta_competition'] ?? 0 )
				),
				'severity'       => (int) ( $cta['primary_cta_emphasis'] ?? 0 ) < 50 ? 'high' : 'low',
				'category'       => 'conversion',
				'insight_type'   => 'visual_emphasis',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'scores'         => $cta,
			);
		}

		$comp = isset( $visual['visual_competition'] ) && is_array( $visual['visual_competition'] ) ? $visual['visual_competition'] : array();
		if ( ! empty( $comp['focus_conflicts'] ) ) {
			$rows[] = array(
				'insight_key'    => 'visual_focus_conflicts',
				'finding'        => sprintf(
					/* translators: %d: number of conflicts */
					__( '%d visual focus conflicts detected (competing CTAs or dense hero).', 'reactwoo-geo-ai' ),
					(int) $comp['focus_conflicts']
				),
				'severity'       => (int) $comp['focus_conflicts'] >= 2 ? 'medium' : 'low',
				'category'       => 'ux',
				'insight_type'   => 'visual_competition',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'evidence'       => $comp,
			);
		}

		$colours = isset( $visual['colour_roles'] ) && is_array( $visual['colour_roles'] ) ? $visual['colour_roles'] : array();
		if ( ! empty( $colours['primary_action'] ) || ! empty( $colours['trust'] ) ) {
			$rows[] = array(
				'insight_key'    => 'colour_roles',
				'finding'        => sprintf(
					/* translators: 1: primary action role, 2: trust role */
					__( 'Colour roles — primary action: %1$s; trust: %2$s.', 'reactwoo-geo-ai' ),
					(string) ( $colours['primary_action'] ?? __( 'not detected', 'reactwoo-geo-ai' ) ),
					(string) ( $colours['trust'] ?? __( 'not detected', 'reactwoo-geo-ai' ) )
				),
				'severity'       => 'low',
				'category'       => 'ux',
				'insight_type'   => 'colour_meaning',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'evidence'       => $colours,
			);
		}

		$flow = isset( $visual['attention_flow'] ) && is_array( $visual['attention_flow'] ) ? $visual['attention_flow'] : array();
		if ( ! empty( $flow['stages'] ) ) {
			$rows[] = array(
				'insight_key'    => 'attention_flow',
				'finding'        => sprintf(
					/* translators: %s: arrow-separated stages */
					__( 'Attention flow: %s.', 'reactwoo-geo-ai' ),
					implode( ' → ', array_map( 'sanitize_key', (array) $flow['stages'] ) )
				),
				'severity'       => 'low',
				'category'       => 'ux',
				'insight_type'   => 'attention_flow',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'evidence'       => $flow,
			);
		}

		return $rows;
	}

	/**
	 * @param array<int, array<string, mixed>> $sections Classified sections.
	 * @return array{stages: array<int, string>, confidence: float}
	 */
	private static function analyze_attention_flow( array $sections ) {
		$map = array(
			'hero'         => 'hero',
			'features'     => 'benefits',
			'benefits'     => 'benefits',
			'testimonials' => 'proof',
			'trust'        => 'proof',
			'logos'        => 'proof',
			'pricing'      => 'cta',
			'cta'          => 'cta',
			'form'         => 'cta',
			'faq'          => 'support',
			'gallery'      => 'visual',
			'content'      => 'content',
		);

		$stages = array();
		foreach ( $sections as $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$type = isset( $sec['classification']['type'] ) ? sanitize_key( (string) $sec['classification']['type'] ) : '';
			$stage = isset( $map[ $type ] ) ? $map[ $type ] : '';
			if ( '' === $stage ) {
				continue;
			}
			if ( empty( $stages ) || end( $stages ) !== $stage ) {
				$stages[] = $stage;
			}
		}

		$confidence = min( 0.92, 0.45 + ( count( $stages ) * 0.08 ) );

		return array(
			'stages'     => $stages,
			'confidence' => round( $confidence, 2 ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $widgets  Widgets.
	 * @param array<int, array<string, mixed>> $ctas     CTAs.
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @param array<string, mixed>             $ux_scores UX scores.
	 * @return array<string, int>
	 */
	private static function analyze_cta_emphasis( array $widgets, array $ctas, array $sections, array $ux_scores ) {
		$primary_emphasis = isset( $ux_scores['cta_score'] ) ? (int) $ux_scores['cta_score'] : 40;
		$hero_has_cta     = false;
		$hero_widgets     = 0;

		foreach ( $sections as $i => $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$type = isset( $sec['classification']['type'] ) ? (string) $sec['classification']['type'] : '';
			if ( 0 === $i || 'hero' === $type ) {
				if ( ! empty( $sec['has_cta'] ) ) {
					$hero_has_cta = true;
					$primary_emphasis += 20;
				}
				$hero_widgets = isset( $sec['widget_count'] ) ? (int) $sec['widget_count'] : 0;
			}
		}

		if ( ! $hero_has_cta && ! empty( $ctas ) ) {
			$primary_emphasis -= 15;
		}
		if ( $hero_widgets > 8 ) {
			$primary_emphasis -= 10;
		}

		$primary_widget = null;
		foreach ( $widgets as $w ) {
			if ( is_array( $w ) && ! empty( $w['is_cta'] ) ) {
				$primary_widget = $w;
				break;
			}
		}
		if ( is_array( $primary_widget ) && ! empty( $primary_widget['settings']['background_role'] ) ) {
			if ( 'primary_action' === (string) $primary_widget['settings']['background_role'] ) {
				$primary_emphasis += 10;
			}
		}

		$secondary_competition = min( 100, max( 0, ( count( $ctas ) - 1 ) * 22 ) );
		if ( count( $ctas ) > 3 ) {
			$secondary_competition = min( 100, $secondary_competition + 15 );
		}

		return array(
			'primary_cta_emphasis'     => self::clamp( $primary_emphasis ),
			'secondary_cta_competition' => self::clamp( $secondary_competition ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @param array<int, array<string, mixed>> $widgets  Widgets.
	 * @param array<int, array<string, mixed>> $ctas     CTAs.
	 * @return array{focus_conflicts: int, details: array<int, string>}
	 */
	private static function analyze_visual_competition( array $sections, array $widgets, array $ctas ) {
		$conflicts = 0;
		$details   = array();

		$cta_sections = 0;
		foreach ( $sections as $sec ) {
			if ( is_array( $sec ) && ! empty( $sec['has_cta'] ) ) {
				++$cta_sections;
			}
		}
		if ( $cta_sections > 2 ) {
			++$conflicts;
			$details[] = 'multiple_cta_sections';
		}
		if ( count( $ctas ) > 3 ) {
			++$conflicts;
			$details[] = 'too_many_cta_buttons';
		}

		$hero_buttons = 0;
		foreach ( $sections as $i => $sec ) {
			if ( ! is_array( $sec ) || ( 0 !== $i && empty( $sec['classification']['type'] ) ) ) {
				continue;
			}
			$type = isset( $sec['classification']['type'] ) ? (string) $sec['classification']['type'] : '';
			if ( 0 === $i || 'hero' === $type ) {
				if ( (int) ( $sec['widget_count'] ?? 0 ) > 10 ) {
					++$conflicts;
					$details[] = 'dense_hero';
				}
				foreach ( $widgets as $w ) {
					if ( ! is_array( $w ) || empty( $w['is_cta'] ) ) {
						continue;
					}
					if ( isset( $w['section_id'] ) && isset( $sec['id'] ) && (string) $w['section_id'] === (string) $sec['id'] ) {
						++$hero_buttons;
					}
				}
			}
		}
		if ( $hero_buttons > 1 ) {
			++$conflicts;
			$details[] = 'competing_hero_ctas';
		}

		return array(
			'focus_conflicts' => $conflicts,
			'details'         => array_values( array_unique( $details ) ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @return array<string, string>
	 */
	private static function analyze_colour_meaning( array $widgets ) {
		$roles = array(
			'primary_action' => '',
			'secondary_action' => '',
			'trust'          => '',
			'warning'        => '',
		);

		$cta_colors = array();
		foreach ( $widgets as $w ) {
			if ( ! is_array( $w ) || empty( $w['is_cta'] ) ) {
				continue;
			}
			$settings = isset( $w['settings'] ) && is_array( $w['settings'] ) ? $w['settings'] : array();
			if ( ! empty( $settings['background_role'] ) ) {
				$cta_colors[] = (string) $settings['background_role'];
			} elseif ( ! empty( $settings['background_color'] ) ) {
				$cta_colors[] = RWGA_Builder_Normalize::interpret_color_role( (string) $settings['background_color'] );
			}
		}

		if ( ! empty( $cta_colors[0] ) ) {
			$roles['primary_action'] = self::role_label( $cta_colors[0] );
		}
		if ( isset( $cta_colors[1] ) ) {
			$roles['secondary_action'] = self::role_label( $cta_colors[1] );
		}

		foreach ( $widgets as $w ) {
			if ( ! is_array( $w ) ) {
				continue;
			}
			$settings = isset( $w['settings'] ) && is_array( $w['settings'] ) ? $w['settings'] : array();
			$role     = isset( $settings['background_role'] ) ? (string) $settings['background_role'] : '';
			if ( '' === $role && ! empty( $settings['background_color'] ) ) {
				$role = RWGA_Builder_Normalize::interpret_color_role( (string) $settings['background_color'] );
			}
			if ( 'trust' === $role && '' === $roles['trust'] ) {
				$roles['trust'] = self::role_label( 'trust' );
			}
			if ( 'warning' === $role && '' === $roles['warning'] ) {
				$roles['warning'] = self::role_label( 'warning' );
			}
		}

		if ( '' === $roles['trust'] && in_array( 'trust', $cta_colors, true ) ) {
			$roles['trust'] = self::role_label( 'trust' );
		}

		return array_filter( $roles );
	}

	/**
	 * @param string $role Role slug.
	 * @return string Human label.
	 */
	private static function role_label( $role ) {
		$labels = array(
			'primary_action' => __( 'high-contrast warm action', 'reactwoo-geo-ai' ),
			'trust'          => __( 'cool neutral trust', 'reactwoo-geo-ai' ),
			'warning'        => __( 'amber urgency accent', 'reactwoo-geo-ai' ),
			'neutral'        => __( 'low-contrast neutral', 'reactwoo-geo-ai' ),
			'accent'         => __( 'secondary accent', 'reactwoo-geo-ai' ),
		);
		$role = sanitize_key( (string) $role );
		return isset( $labels[ $role ] ) ? $labels[ $role ] : $role;
	}

	/**
	 * @param int|float $value Score.
	 * @return int
	 */
	private static function clamp( $value ) {
		return max( 0, min( 100, (int) round( $value ) ) );
	}
}
