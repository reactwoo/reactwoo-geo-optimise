<?php
/**
 * Shared semantic extraction from classified builder payloads.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builder-agnostic meaning extraction.
 */
abstract class RWGA_Context_Extractor_Base implements RWGA_Context_Extractor_Interface {

	const VERSION = '1.0.0';

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $payload Classified builder payload.
	 * @return array<string, mixed>
	 */
	public function extract( $post_id, array $payload ) {
		$sections = isset( $payload['sections'] ) && is_array( $payload['sections'] ) ? $payload['sections'] : array();
		$widgets  = isset( $payload['widgets'] ) && is_array( $payload['widgets'] ) ? $payload['widgets'] : array();
		$ctas     = isset( $payload['ctas'] ) && is_array( $payload['ctas'] ) ? $payload['ctas'] : array();
		$forms    = isset( $payload['forms'] ) && is_array( $payload['forms'] ) ? $payload['forms'] : array();

		$page_goal = self::infer_page_goal( $payload, $sections );
		$beats     = self::build_narrative_beats( $sections, $widgets );
		$persuasion = self::build_persuasion_summary( $beats, $widgets, $ctas );
		$trust     = self::build_trust_signals( $sections );
		$path      = self::build_conversion_path( $ctas, $forms, $beats );
		$gaps      = self::detect_structure_gaps( $page_goal, $beats, $trust );

		$result = array(
			'schema_version'   => self::VERSION,
			'builder'          => $this->get_builder_slug(),
			'page_goal'        => $page_goal,
			'page_role'        => isset( $payload['page_type'] ) ? sanitize_key( (string) $payload['page_type'] ) : 'content_page',
			'narrative_beats'  => $beats,
			'persuasion'       => $persuasion,
			'trust_signals'    => $trust,
			'conversion_path'  => $path,
			'structure_gaps'   => $gaps,
			'builder_meta'     => $this->extract_builder_meta( (int) $post_id, $payload ),
			'extracted_at_gmt' => gmdate( 'c' ),
		);

		/**
		 * @param array<string, mixed> $result  Semantic context.
		 * @param int                  $post_id Post ID.
		 * @param array<string, mixed> $payload Builder payload.
		 */
		return apply_filters( 'rwga_builder_semantics', $result, (int) $post_id, $payload );
	}

	/**
	 * Builder-specific metadata (template type, patterns, etc.).
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $payload Builder payload.
	 * @return array<string, mixed>
	 */
	abstract protected function extract_builder_meta( $post_id, array $payload );

	/**
	 * @param array<string, mixed>             $payload  Builder payload.
	 * @param array<int, array<string, mixed>> $sections Classified sections.
	 * @return string
	 */
	protected static function infer_page_goal( array $payload, array $sections ) {
		$types = array();
		foreach ( $sections as $sec ) {
			if ( is_array( $sec ) && ! empty( $sec['classification']['type'] ) ) {
				$types[] = sanitize_key( (string) $sec['classification']['type'] );
			}
		}
		if ( ! empty( $payload['forms'] ) || in_array( 'form', $types, true ) ) {
			return 'lead_generation';
		}
		if ( in_array( 'pricing', $types, true ) ) {
			return 'sales_conversion';
		}
		if ( in_array( 'hero', $types, true ) && count( $types ) >= 3 ) {
			return 'lead_generation';
		}
		if ( ! empty( $payload['ctas'] ) && count( (array) $payload['ctas'] ) >= 2 ) {
			return 'sales_conversion';
		}
		return 'information';
	}

	/**
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @param array<int, array<string, mixed>> $widgets  Widgets.
	 * @return array<int, array<string, mixed>>
	 */
	protected static function build_narrative_beats( array $sections, array $widgets ) {
		$beats = array();
		foreach ( $sections as $i => $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$role   = isset( $sec['classification']['type'] ) ? sanitize_key( (string) $sec['classification']['type'] ) : 'content';
			$sec_id = isset( $sec['id'] ) ? (string) $sec['id'] : '';
			$elements = self::section_elements( $sec_id, $widgets );
			$beats[] = array(
				'index'      => (int) $i,
				'section_id' => $sec_id,
				'role'       => $role,
				'intent'     => self::intent_for_role( $role ),
				'headline'   => RWGA_Builder_Normalize::trim_text( isset( $sec['heading'] ) ? (string) $sec['heading'] : '', 120 ),
				'elements'   => $elements,
				'has_proof'  => in_array( $role, array( 'testimonials', 'trust', 'logos' ), true ) || in_array( 'social_proof', $elements, true ),
				'has_cta'    => ! empty( $sec['has_cta'] ),
				'has_form'   => ! empty( $sec['has_form'] ),
			);
		}
		return $beats;
	}

	/**
	 * @param string                           $section_id Section id.
	 * @param array<int, array<string, mixed>> $widgets    Widgets.
	 * @return array<int, string>
	 */
	protected static function section_elements( $section_id, array $widgets ) {
		$elements = array();
		foreach ( $widgets as $w ) {
			if ( ! is_array( $w ) || (string) ( $w['section_id'] ?? '' ) !== (string) $section_id ) {
				continue;
			}
			$type = isset( $w['type'] ) ? sanitize_key( (string) $w['type'] ) : '';
			if ( 'heading' === $type ) {
				$size = isset( $w['settings']['header_size'] ) ? (string) $w['settings']['header_size'] : '';
				if ( '' === $size && isset( $w['settings']['level'] ) ) {
					$size = 'h' . (int) $w['settings']['level'];
				}
				$elements[] = ( 'h1' === $size ) ? 'headline' : 'subheading';
				continue;
			}
			if ( ! empty( $w['is_cta'] ) ) {
				$elements[] = empty( $elements ) || ! in_array( 'primary_cta', $elements, true ) ? 'primary_cta' : 'secondary_cta';
				continue;
			}
			if ( ! empty( $w['is_form'] ) ) {
				$elements[] = 'lead_capture';
				continue;
			}
			if ( in_array( $type, array( 'testimonial', 'testimonial-carousel', 'reviews' ), true ) ) {
				$elements[] = 'social_proof';
				continue;
			}
			if ( in_array( $type, array( 'image-gallery', 'logo', 'icon-list' ), true ) ) {
				$elements[] = 'trust_visual';
				continue;
			}
			if ( in_array( $type, array( 'accordion', 'toggle', 'faq' ), true ) ) {
				$elements[] = 'objection_handler';
			}
		}
		return array_values( array_unique( $elements ) );
	}

	/**
	 * @param string $role Section classification type.
	 * @return string
	 */
	protected static function intent_for_role( $role ) {
		$map = array(
			'hero'         => 'capture_attention',
			'features'     => 'explain_value',
			'benefits'     => 'explain_value',
			'testimonials' => 'build_trust',
			'trust'        => 'build_trust',
			'logos'        => 'build_trust',
			'pricing'      => 'justify_price',
			'cta'          => 'convert',
			'form'         => 'capture_lead',
			'faq'          => 'handle_objections',
			'gallery'      => 'show_product',
			'content'      => 'inform',
		);
		$role = sanitize_key( (string) $role );
		return isset( $map[ $role ] ) ? $map[ $role ] : 'inform';
	}

	/**
	 * @param array<int, array<string, mixed>> $beats   Narrative beats.
	 * @param array<int, array<string, mixed>> $widgets Widgets.
	 * @param array<int, array<string, mixed>> $ctas    CTAs.
	 * @return array<string, mixed>
	 */
	protected static function build_persuasion_summary( array $beats, array $widgets, array $ctas ) {
		$headline    = '';
		$primary_cta = '';
		$proof       = 0;
		$objections  = 0;

		foreach ( $beats as $beat ) {
			if ( ! is_array( $beat ) ) {
				continue;
			}
			if ( '' === $headline && 'hero' === ( $beat['role'] ?? '' ) && ! empty( $beat['headline'] ) ) {
				$headline = (string) $beat['headline'];
			}
			if ( ! empty( $beat['has_proof'] ) ) {
				++$proof;
			}
			if ( in_array( 'objection_handler', (array) ( $beat['elements'] ?? array() ), true ) ) {
				++$objections;
			}
		}
		if ( '' === $headline ) {
			foreach ( $widgets as $w ) {
				if ( ! is_array( $w ) || 'heading' !== ( $w['type'] ?? '' ) ) {
					continue;
				}
				$size = isset( $w['settings']['header_size'] ) ? (string) $w['settings']['header_size'] : '';
				if ( 'h1' === $size || ( isset( $w['settings']['level'] ) && 1 === (int) $w['settings']['level'] ) ) {
					$headline = RWGA_Builder_Normalize::trim_text( (string) ( $w['content'] ?? '' ), 120 );
					break;
				}
			}
		}
		if ( ! empty( $ctas[0]['label'] ) ) {
			$primary_cta = (string) $ctas[0]['label'];
		}

		return array(
			'headline'            => $headline,
			'primary_cta'         => $primary_cta,
			'proof_sections'      => $proof,
			'objection_handlers'  => $objections,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $sections Sections.
	 * @return array<string, int>
	 */
	protected static function build_trust_signals( array $sections ) {
		$signals = array(
			'testimonial_sections' => 0,
			'trust_sections'       => 0,
			'logo_sections'        => 0,
			'form_sections'        => 0,
		);
		foreach ( $sections as $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$role = isset( $sec['classification']['type'] ) ? sanitize_key( (string) $sec['classification']['type'] ) : '';
			switch ( $role ) {
				case 'testimonials':
					++$signals['testimonial_sections'];
					break;
				case 'trust':
					++$signals['trust_sections'];
					break;
				case 'logos':
					++$signals['logo_sections'];
					break;
				case 'form':
					++$signals['form_sections'];
					break;
			}
			if ( ! empty( $sec['has_form'] ) ) {
				++$signals['form_sections'];
			}
		}
		return $signals;
	}

	/**
	 * @param array<int, array<string, mixed>> $ctas  CTAs.
	 * @param array<int, array<string, mixed>> $forms Forms.
	 * @param array<int, array<string, mixed>> $beats Beats.
	 * @return array<int, array<string, mixed>>
	 */
	protected static function build_conversion_path( array $ctas, array $forms, array $beats ) {
		$path  = array();
		$step  = 1;
		foreach ( $ctas as $i => $cta ) {
			if ( ! is_array( $cta ) ) {
				continue;
			}
			$path[] = array(
				'step'  => $step++,
				'type'  => 'cta',
				'label' => RWGA_Builder_Normalize::trim_text( (string) ( $cta['label'] ?? '' ), 80 ),
				'role'  => 0 === $i ? 'primary' : 'secondary',
			);
		}
		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}
			$path[] = array(
				'step'  => $step++,
				'type'  => 'form',
				'label' => RWGA_Builder_Normalize::trim_text( (string) ( $form['label'] ?? $form['name'] ?? __( 'Form', 'reactwoo-geo-ai' ) ), 80 ),
				'role'  => 'lead_capture',
			);
		}
		if ( empty( $path ) ) {
			foreach ( $beats as $beat ) {
				if ( ! is_array( $beat ) || empty( $beat['has_cta'] ) ) {
					continue;
				}
				$path[] = array(
					'step'  => $step++,
					'type'  => 'cta_section',
					'label' => RWGA_Builder_Normalize::trim_text( (string) ( $beat['headline'] ?? '' ), 80 ),
					'role'  => 'section_cta',
				);
			}
		}
		return $path;
	}

	/**
	 * @param string                           $page_goal Page goal slug.
	 * @param array<int, array<string, mixed>> $beats     Beats.
	 * @param array<string, int>               $trust     Trust signals.
	 * @return array<int, string>
	 */
	protected static function detect_structure_gaps( $page_goal, array $beats, array $trust ) {
		$gaps  = array();
		$roles = array_column( $beats, 'role' );
		if ( 'lead_generation' === $page_goal || 'sales_conversion' === $page_goal ) {
			if ( ! in_array( 'hero', $roles, true ) ) {
				$gaps[] = 'missing_hero';
			}
			$cta_before_proof = false;
			$saw_proof        = false;
			foreach ( $beats as $beat ) {
				if ( ! is_array( $beat ) ) {
					continue;
				}
				if ( ! empty( $beat['has_proof'] ) ) {
					$saw_proof = true;
				}
				if ( ! $saw_proof && ! empty( $beat['has_cta'] ) && 'hero' !== ( $beat['role'] ?? '' ) ) {
					$cta_before_proof = true;
				}
			}
			if ( $cta_before_proof || ( ! $saw_proof && ! empty( array_filter( array_column( $beats, 'has_cta' ) ) ) ) ) {
				$gaps[] = 'proof_before_cta_missing';
			}
			if ( 0 === (int) ( $trust['testimonial_sections'] ?? 0 ) + (int) ( $trust['trust_sections'] ?? 0 ) + (int) ( $trust['logo_sections'] ?? 0 ) ) {
				$gaps[] = 'missing_trust_signals';
			}
		}
		return array_values( array_unique( $gaps ) );
	}

	/**
	 * @param array<string, mixed> $semantics Full semantics output.
	 * @return string
	 */
	public static function format_summary( array $semantics ) {
		$parts = array();
		if ( ! empty( $semantics['page_goal'] ) ) {
			$parts[] = sanitize_key( (string) $semantics['page_goal'] );
		}
		$beats = isset( $semantics['narrative_beats'] ) && is_array( $semantics['narrative_beats'] ) ? $semantics['narrative_beats'] : array();
		if ( ! empty( $beats ) ) {
			$roles = array_map(
				static function ( $beat ) {
					return is_array( $beat ) ? sanitize_key( (string) ( $beat['role'] ?? '' ) ) : '';
				},
				$beats
			);
			$parts[] = implode( ' → ', array_filter( $roles ) );
		}
		$gaps = isset( $semantics['structure_gaps'] ) && is_array( $semantics['structure_gaps'] ) ? $semantics['structure_gaps'] : array();
		if ( ! empty( $gaps ) ) {
			$parts[] = count( $gaps ) . ' structure gaps';
		}
		return implode( ' — ', $parts );
	}

	/**
	 * Token-efficient semantics for API payloads.
	 *
	 * @param array<string, mixed> $semantics Full semantics.
	 * @return array<string, mixed>
	 */
	public static function compact_for_api( array $semantics ) {
		if ( array() === $semantics ) {
			return array();
		}
		$beats = array();
		foreach ( isset( $semantics['narrative_beats'] ) && is_array( $semantics['narrative_beats'] ) ? $semantics['narrative_beats'] : array() as $beat ) {
			if ( ! is_array( $beat ) ) {
				continue;
			}
			$beats[] = array(
				'role'    => isset( $beat['role'] ) ? (string) $beat['role'] : '',
				'intent'  => isset( $beat['intent'] ) ? (string) $beat['intent'] : '',
				'headline'=> RWGA_Builder_Normalize::trim_text( (string) ( $beat['headline'] ?? '' ), 60 ),
				'has_cta' => ! empty( $beat['has_cta'] ),
			);
		}
		$path = array();
		foreach ( isset( $semantics['conversion_path'] ) && is_array( $semantics['conversion_path'] ) ? array_slice( $semantics['conversion_path'], 0, 8 ) : array() as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}
			$path[] = array(
				'type'  => isset( $step['type'] ) ? (string) $step['type'] : '',
				'label' => RWGA_Builder_Normalize::trim_text( (string) ( $step['label'] ?? '' ), 40 ),
				'role'  => isset( $step['role'] ) ? (string) $step['role'] : '',
			);
		}
		return array(
			'page_goal'       => isset( $semantics['page_goal'] ) ? (string) $semantics['page_goal'] : '',
			'page_role'       => isset( $semantics['page_role'] ) ? (string) $semantics['page_role'] : '',
			'narrative_beats' => $beats,
			'persuasion'      => isset( $semantics['persuasion'] ) && is_array( $semantics['persuasion'] ) ? $semantics['persuasion'] : array(),
			'conversion_path' => $path,
			'structure_gaps'  => array_slice( isset( $semantics['structure_gaps'] ) && is_array( $semantics['structure_gaps'] ) ? $semantics['structure_gaps'] : array(), 0, 6 ),
			'builder_meta'    => isset( $semantics['builder_meta'] ) && is_array( $semantics['builder_meta'] ) ? $semantics['builder_meta'] : array(),
		);
	}
}
