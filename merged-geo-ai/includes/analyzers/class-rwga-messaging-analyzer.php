<?php
/**
 * Deterministic messaging intelligence — what the page communicates, not widget inventory.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extracts promise, UVP, audience signals, objections, and clarity scores from builder context.
 */
class RWGA_Messaging_Analyzer {

	const VERSION = '1.0.0';

	/**
	 * @param array<string, mixed> $builder_payload Output from {@see RWGA_Page_Context_Builder::build()}.
	 * @param int                  $post_id         Optional post ID for title fallback.
	 * @return array<string, mixed>
	 */
	public static function analyze( array $builder_payload, $post_id = 0 ) {
		$post_id = (int) $post_id;
		$corpus  = self::build_text_corpus( $builder_payload, $post_id );
		$hero    = self::extract_hero_copy( $builder_payload, $corpus );

		$promise = self::extract_promise( $hero, $corpus );
		$uvp     = self::extract_uvp( $hero, $corpus, $promise );
		$audience = self::infer_audience( $corpus, $builder_payload );
		$drivers  = self::detect_emotional_drivers( $corpus );
		$objections = self::detect_objections( $builder_payload, $corpus );
		$clarity  = self::score_clarity( $hero, $corpus, $builder_payload, $uvp );

		$result = array(
			'schema_version'     => self::VERSION,
			'promise'            => $promise,
			'uvp'                => $uvp,
			'audience'           => $audience,
			'emotional_drivers'  => $drivers,
			'objections'         => $objections,
			'clarity'            => $clarity,
			'corpus_word_count'  => self::word_count( $corpus['full'] ),
			'analyzed_at_gmt'    => gmdate( 'c' ),
		);

		/**
		 * Filter messaging intelligence before persistence.
		 *
		 * @param array<string, mixed> $result          Analyzer output.
		 * @param array<string, mixed> $builder_payload Source payload.
		 * @param int                  $post_id         Post ID.
		 */
		return apply_filters( 'rwga_messaging_intelligence', $result, $builder_payload, $post_id );
	}

	/**
	 * Build UX insight rows for {@see RWGA_DB_UX_Insights}.
	 *
	 * @param array<string, mixed> $messaging Output from analyze().
	 * @param string               $entity_hash Page entity hash.
	 * @param string               $snapshot_hash Site snapshot hash.
	 * @return array<int, array<string, mixed>>
	 */
	public static function to_insight_rows( array $messaging, $entity_hash = '', $snapshot_hash = '' ) {
		$rows = array();

		$promise_text = isset( $messaging['promise']['text'] ) ? trim( (string) $messaging['promise']['text'] ) : '';
		if ( '' !== $promise_text ) {
			$rows[] = array(
				'insight_key'    => 'primary_promise',
				'finding'        => $promise_text,
				'severity'       => 'low',
				'category'       => 'messaging',
				'insight_type'   => 'promise',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'scores'         => array(
					'confidence' => isset( $messaging['promise']['confidence'] ) ? (float) $messaging['promise']['confidence'] : 0,
				),
			);
		}

		$uvp_text = isset( $messaging['uvp']['text'] ) ? trim( (string) $messaging['uvp']['text'] ) : '';
		if ( '' !== $uvp_text && $uvp_text !== $promise_text ) {
			$rows[] = array(
				'insight_key'    => 'unique_value_proposition',
				'finding'        => $uvp_text,
				'severity'       => 'low',
				'category'       => 'messaging',
				'insight_type'   => 'uvp',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'scores'         => array(
					'confidence' => isset( $messaging['uvp']['confidence'] ) ? (float) $messaging['uvp']['confidence'] : 0,
				),
			);
		}

		$clarity = isset( $messaging['clarity'] ) && is_array( $messaging['clarity'] ) ? $messaging['clarity'] : array();
		if ( ! empty( $clarity ) ) {
			$overall = isset( $clarity['overall'] ) ? (int) $clarity['overall'] : 0;
			$rows[]  = array(
				'insight_key'    => 'messaging_clarity',
				'finding'        => sprintf(
					/* translators: 1: overall clarity score 0-100 */
					__( 'Messaging clarity score: %d/100 (specificity %d, differentiation %d, credibility %d).', 'reactwoo-geo-ai' ),
					$overall,
					(int) ( $clarity['specificity'] ?? 0 ),
					(int) ( $clarity['differentiation'] ?? 0 ),
					(int) ( $clarity['credibility'] ?? 0 )
				),
				'severity'       => $overall < 50 ? 'high' : ( $overall < 70 ? 'medium' : 'low' ),
				'category'       => 'messaging',
				'insight_type'   => 'clarity',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'scores'         => $clarity,
			);
		}

		$audience = isset( $messaging['audience'] ) && is_array( $messaging['audience'] ) ? $messaging['audience'] : array();
		if ( ! empty( $audience['persona'] ) ) {
			$rows[] = array(
				'insight_key'    => 'audience_persona',
				'finding'        => sprintf(
					/* translators: 1: persona slug, 2: awareness stage */
					__( 'Inferred audience: %1$s (%2$s awareness).', 'reactwoo-geo-ai' ),
					sanitize_text_field( (string) $audience['persona'] ),
					sanitize_text_field( (string) ( $audience['awareness_stage'] ?? 'unknown' ) )
				),
				'severity'       => 'low',
				'category'       => 'messaging',
				'insight_type'   => 'audience',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'evidence'       => $audience,
			);
		}

		$objections = isset( $messaging['objections'] ) && is_array( $messaging['objections'] ) ? $messaging['objections'] : array();
		foreach ( $objections as $obj ) {
			if ( ! is_array( $obj ) ) {
				continue;
			}
			$key = isset( $obj['key'] ) ? sanitize_key( (string) $obj['key'] ) : 'objection';
			$rows[] = array(
				'insight_key'    => 'objection_' . $key,
				'finding'        => isset( $obj['label'] ) ? (string) $obj['label'] : $key,
				'severity'       => isset( $obj['severity'] ) ? sanitize_key( (string) $obj['severity'] ) : 'medium',
				'category'       => 'messaging',
				'insight_type'   => 'objection',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'evidence'       => $obj,
			);
		}

		$drivers = isset( $messaging['emotional_drivers'] ) && is_array( $messaging['emotional_drivers'] ) ? $messaging['emotional_drivers'] : array();
		if ( ! empty( $drivers ) ) {
			$rows[] = array(
				'insight_key'    => 'emotional_drivers',
				'finding'        => sprintf(
					/* translators: %s: comma-separated driver slugs */
					__( 'Emotional drivers detected: %s.', 'reactwoo-geo-ai' ),
					implode( ', ', array_map( 'sanitize_key', $drivers ) )
				),
				'severity'       => 'low',
				'category'       => 'messaging',
				'insight_type'   => 'emotional_drivers',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => $snapshot_hash,
				'evidence'       => array( 'drivers' => $drivers ),
			);
		}

		return $rows;
	}

	/**
	 * One-line summary for page context row.
	 *
	 * @param array<string, mixed> $messaging Analyzer output.
	 * @return string
	 */
	public static function format_summary( array $messaging ) {
		$promise = isset( $messaging['promise']['text'] ) ? trim( (string) $messaging['promise']['text'] ) : '';
		$uvp     = isset( $messaging['uvp']['text'] ) ? trim( (string) $messaging['uvp']['text'] ) : '';
		$clarity = isset( $messaging['clarity']['overall'] ) ? (int) $messaging['clarity']['overall'] : 0;

		$parts = array();
		if ( '' !== $promise ) {
			$parts[] = $promise;
		} elseif ( '' !== $uvp ) {
			$parts[] = $uvp;
		}
		if ( $clarity > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: clarity score */
				__( 'Clarity %d/100', 'reactwoo-geo-ai' ),
				$clarity
			);
		}
		return implode( ' — ', $parts );
	}

	/**
	 * @param array<string, mixed> $builder_payload Builder payload.
	 * @param int                  $post_id         Post ID.
	 * @return array{full: string, hero: string, headings: array<int, string>}
	 */
	private static function build_text_corpus( array $builder_payload, $post_id ) {
		$parts    = array();
		$headings = array();

		if ( ! empty( $builder_payload['page_title'] ) ) {
			$parts[] = (string) $builder_payload['page_title'];
		} elseif ( $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post ) {
				$parts[] = $post->post_title;
			}
		}

		foreach ( isset( $builder_payload['widgets'] ) && is_array( $builder_payload['widgets'] ) ? $builder_payload['widgets'] : array() as $w ) {
			if ( ! is_array( $w ) || empty( $w['content'] ) ) {
				continue;
			}
			$text = trim( (string) $w['content'] );
			if ( '' === $text ) {
				continue;
			}
			$parts[] = $text;
			$type    = isset( $w['type'] ) ? sanitize_key( (string) $w['type'] ) : '';
			if ( in_array( $type, array( 'heading', 'title', 'text-editor', 'text', 'core/heading', 'core/paragraph' ), true ) ) {
				$headings[] = $text;
			}
		}

		foreach ( isset( $builder_payload['content_blocks'] ) && is_array( $builder_payload['content_blocks'] ) ? $builder_payload['content_blocks'] : array() as $b ) {
			if ( is_array( $b ) && ! empty( $b['text'] ) ) {
				$parts[] = trim( (string) $b['text'] );
			}
		}

		$full = implode( "\n", array_unique( array_filter( $parts ) ) );
		return array(
			'full'     => $full,
			'hero'     => '',
			'headings' => $headings,
		);
	}

	/**
	 * @param array<string, mixed>        $builder_payload Builder payload.
	 * @param array{full: string, hero: string, headings: array<int, string>} $corpus Text corpus.
	 * @return array{headline: string, subheadline: string, section_type: string}
	 */
	private static function extract_hero_copy( array $builder_payload, array $corpus ) {
		$headline    = '';
		$subheadline = '';
		$section_type = '';

		$sections = isset( $builder_payload['sections'] ) && is_array( $builder_payload['sections'] ) ? $builder_payload['sections'] : array();
		foreach ( $sections as $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$type = isset( $sec['classification']['type'] ) ? sanitize_key( (string) $sec['classification']['type'] ) : '';
			if ( 'hero' !== $type && '' === $headline ) {
				continue;
			}
			if ( 'hero' === $type || ( '' === $headline && ! empty( $sec['heading'] ) ) ) {
				$headline = trim( (string) ( $sec['heading'] ?? '' ) );
				$section_type = $type ?: 'hero';
				if ( ! empty( $sec['subheading'] ) ) {
					$subheadline = trim( (string) $sec['subheading'] );
				}
				if ( 'hero' === $type ) {
					break;
				}
			}
		}

		if ( '' === $headline && ! empty( $corpus['headings'][0] ) ) {
			$headline = (string) $corpus['headings'][0];
		}
		if ( '' === $subheadline && ! empty( $corpus['headings'][1] ) ) {
			$subheadline = (string) $corpus['headings'][1];
		}

		return array(
			'headline'     => RWGA_Builder_Normalize::trim_text( $headline, 200 ),
			'subheadline'  => RWGA_Builder_Normalize::trim_text( $subheadline, 300 ),
			'section_type' => $section_type,
		);
	}

	/**
	 * @param array{headline: string, subheadline: string, section_type: string} $hero Hero copy.
	 * @param array{full: string, hero: string, headings: array<int, string>}   $corpus Corpus.
	 * @return array{text: string, confidence: float}
	 */
	private static function extract_promise( array $hero, array $corpus ) {
		$candidate = '' !== $hero['headline'] ? $hero['headline'] : '';
		if ( '' === $candidate && ! empty( $corpus['headings'][0] ) ) {
			$candidate = (string) $corpus['headings'][0];
		}

		$confidence = 0.45;
		if ( '' !== $candidate ) {
			$confidence = 0.72;
			if ( self::has_outcome_language( $candidate ) ) {
				$confidence = 0.85;
			}
			if ( self::is_generic_headline( $candidate ) ) {
				$confidence = 0.55;
			}
		}

		return array(
			'text'       => $candidate,
			'confidence' => round( $confidence, 2 ),
		);
	}

	/**
	 * @param array{headline: string, subheadline: string, section_type: string} $hero Hero copy.
	 * @param array{full: string, hero: string, headings: array<int, string>}     $corpus Corpus.
	 * @param array{text: string, confidence: float}                              $promise Promise.
	 * @return array{text: string, confidence: float}
	 */
	private static function extract_uvp( array $hero, array $corpus, array $promise ) {
		$candidate = '' !== $hero['subheadline'] ? $hero['subheadline'] : '';
		if ( '' === $candidate ) {
			$lines = preg_split( '/\n+/', $corpus['full'] );
			if ( is_array( $lines ) && count( $lines ) > 1 ) {
				$candidate = trim( (string) $lines[1] );
			}
		}
		if ( $candidate === ( $promise['text'] ?? '' ) ) {
			$candidate = '';
		}

		$confidence = '' !== $candidate ? 0.65 : 0.35;
		if ( '' !== $candidate && self::has_differentiation_language( $candidate ) ) {
			$confidence = 0.8;
		}

		return array(
			'text'       => RWGA_Builder_Normalize::trim_text( $candidate, 300 ),
			'confidence' => round( $confidence, 2 ),
		);
	}

	/**
	 * @param array{full: string, hero: string, headings: array<int, string>} $corpus Corpus.
	 * @param array<string, mixed>                                            $builder_payload Payload.
	 * @return array{persona: string, awareness_stage: string, confidence: float}
	 */
	private static function infer_audience( array $corpus, array $builder_payload ) {
		$text = strtolower( $corpus['full'] );
		$persona_map = array(
			'warehouse_operations_manager' => array( 'warehouse', 'fulfillment', 'inventory', 'logistics', 'supply chain' ),
			'ecommerce_merchant'           => array( 'woocommerce', 'shopify', 'store owner', 'online store', 'ecommerce', 'e-commerce' ),
			'marketing_manager'            => array( 'marketing', 'campaign', 'conversion', 'landing page', 'lead generation' ),
			'developer'                    => array( 'api', 'integration', 'developer', 'sdk', 'wordpress plugin' ),
			'small_business_owner'         => array( 'small business', 'entrepreneur', 'startup', 'grow your business' ),
		);

		$persona    = 'general_visitor';
		$best_score = 0;
		foreach ( $persona_map as $slug => $needles ) {
			$score = 0;
			foreach ( $needles as $needle ) {
				if ( false !== strpos( $text, $needle ) ) {
					++$score;
				}
			}
			if ( $score > $best_score ) {
				$best_score = $score;
				$persona    = $slug;
			}
		}

		$awareness = 'solution_aware';
		if ( preg_match( '/\b(how to|what is|guide to|learn)\b/i', $corpus['full'] ) ) {
			$awareness = 'problem_aware';
		} elseif ( preg_match( '/\b(pricing|buy now|get started|free trial|demo)\b/i', $corpus['full'] ) ) {
			$awareness = 'product_aware';
		} elseif ( preg_match( '/\b(vs\.?|compare|alternative|switch from)\b/i', $corpus['full'] ) ) {
			$awareness = 'most_aware';
		}

		$page_type = isset( $builder_payload['page_type'] ) ? sanitize_key( (string) $builder_payload['page_type'] ) : '';
		if ( 'landing_page' === $page_type && 'problem_aware' === $awareness ) {
			$awareness = 'solution_aware';
		}

		return array(
			'persona'         => $persona,
			'awareness_stage' => $awareness,
			'confidence'      => round( min( 0.9, 0.4 + ( $best_score * 0.12 ) ), 2 ),
		);
	}

	/**
	 * @param array{full: string, hero: string, headings: array<int, string>} $corpus Corpus.
	 * @return array<int, string>
	 */
	private static function detect_emotional_drivers( array $corpus ) {
		$text = strtolower( $corpus['full'] );
		$map  = array(
			'certainty'       => array( 'guarantee', 'proven', 'reliable', 'trusted', 'certified' ),
			'trust'           => array( 'testimonial', 'review', 'clients', 'customers', 'case study' ),
			'cost_reduction'  => array( 'save', 'reduce costs', 'cut costs', 'affordable', 'roi' ),
			'growth'          => array( 'grow', 'scale', 'increase revenue', 'more sales' ),
			'speed'           => array( 'fast', 'quick', 'instant', 'in minutes', 'same day' ),
			'security'        => array( 'secure', 'safe', 'protected', 'gdpr', 'compliance' ),
			'status'          => array( 'premium', 'exclusive', 'leader', 'award' ),
		);

		$found = array();
		foreach ( $map as $driver => $needles ) {
			foreach ( $needles as $needle ) {
				if ( false !== strpos( $text, $needle ) ) {
					$found[] = $driver;
					break;
				}
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * @param array<string, mixed>                                            $builder_payload Payload.
	 * @param array{full: string, hero: string, headings: array<int, string>} $corpus Corpus.
	 * @return array<int, array{key: string, label: string, severity: string, evidence: string}>
	 */
	private static function detect_objections( array $builder_payload, array $corpus ) {
		$text          = strtolower( $corpus['full'] );
		$section_types = array();
		foreach ( isset( $builder_payload['sections'] ) && is_array( $builder_payload['sections'] ) ? $builder_payload['sections'] : array() as $sec ) {
			if ( is_array( $sec ) && ! empty( $sec['classification']['type'] ) ) {
				$section_types[] = sanitize_key( (string) $sec['classification']['type'] );
			}
		}

		$objections = array();

		if ( in_array( 'pricing', $section_types, true ) && ! in_array( 'testimonials', $section_types, true ) && ! in_array( 'trust', $section_types, true ) ) {
			$objections[] = array(
				'key'      => 'price_justification_missing',
				'label'    => __( 'Pricing appears without nearby proof or risk reversal.', 'reactwoo-geo-ai' ),
				'severity' => 'medium',
				'evidence' => 'pricing_without_trust',
			);
		}

		if ( ! preg_match( '/\b(free trial|money back|guarantee|no credit card)\b/i', $corpus['full'] ) && in_array( 'form', $section_types, true ) ) {
			$objections[] = array(
				'key'      => 'commitment_risk',
				'label'    => __( 'Form asks for commitment without visible risk reversal.', 'reactwoo-geo-ai' ),
				'severity' => 'medium',
				'evidence' => 'form_without_guarantee',
			);
		}

		if ( false !== strpos( $text, 'switch' ) || false !== strpos( $text, 'migrate' ) ) {
			if ( ! preg_match( '/\b(easy setup|onboarding|migration|we handle)\b/i', $corpus['full'] ) ) {
				$objections[] = array(
					'key'      => 'switching_risk',
					'label'    => __( 'Switching/migration mentioned but switching ease is not addressed.', 'reactwoo-geo-ai' ),
					'severity' => 'high',
					'evidence' => 'switching_without_reassurance',
				);
			}
		}

		if ( preg_match( '/\b(enterprise|custom pricing|contact sales)\b/i', $corpus['full'] ) && ! preg_match( '/\b(roi|case study|results)\b/i', $corpus['full'] ) ) {
			$objections[] = array(
				'key'      => 'too_expensive',
				'label'    => __( 'High-commitment offer lacks ROI or proof framing.', 'reactwoo-geo-ai' ),
				'severity' => 'medium',
				'evidence' => 'enterprise_without_roi',
			);
		}

		if ( preg_match( '/\b(implementation|setup|install)\b/i', $corpus['full'] ) && ! preg_match( '/\b(days|hours|minutes|quick|fast)\b/i', $corpus['full'] ) ) {
			$objections[] = array(
				'key'      => 'implementation_time',
				'label'    => __( 'Implementation implied but timeframe not stated.', 'reactwoo-geo-ai' ),
				'severity' => 'low',
				'evidence' => 'implementation_without_timeline',
			);
		}

		return $objections;
	}

	/**
	 * @param array{headline: string, subheadline: string, section_type: string} $hero Hero.
	 * @param array{full: string, hero: string, headings: array<int, string>}   $corpus Corpus.
	 * @param array<string, mixed>                                              $builder_payload Payload.
	 * @param array{text: string, confidence: float}                             $uvp UVP.
	 * @return array{clarity: int, specificity: int, differentiation: int, credibility: int, overall: int}
	 */
	private static function score_clarity( array $hero, array $corpus, array $builder_payload, array $uvp ) {
		$headline = $hero['headline'];
		$full     = $corpus['full'];

		$clarity = 50;
		if ( '' !== $headline ) {
			$clarity += 15;
			if ( self::word_count( $headline ) >= 3 && self::word_count( $headline ) <= 12 ) {
				$clarity += 10;
			}
			if ( self::has_outcome_language( $headline ) ) {
				$clarity += 10;
			}
		}
		if ( self::is_generic_headline( $headline ) ) {
			$clarity -= 20;
		}

		$specificity = 45;
		if ( preg_match( '/\d+[%$£€]|\d+\s*(days|hours|minutes|weeks)/i', $full ) ) {
			$specificity += 25;
		}
		if ( self::word_count( $full ) > 80 ) {
			$specificity += 10;
		}
		if ( '' !== ( $uvp['text'] ?? '' ) ) {
			$specificity += 10;
		}

		$differentiation = 40;
		if ( self::has_differentiation_language( $full ) ) {
			$differentiation += 20;
		}
		if ( preg_match( '/\b(only|unique|first|unlike|vs\.?|better than)\b/i', $full ) ) {
			$differentiation += 15;
		}
		$types = array();
		foreach ( isset( $builder_payload['sections'] ) && is_array( $builder_payload['sections'] ) ? $builder_payload['sections'] : array() as $sec ) {
			if ( is_array( $sec ) && ! empty( $sec['classification']['type'] ) ) {
				$types[] = (string) $sec['classification']['type'];
			}
		}
		if ( in_array( 'features', $types, true ) || in_array( 'benefits', $types, true ) ) {
			$differentiation += 10;
		}

		$credibility = 35;
		if ( in_array( 'testimonials', $types, true ) || in_array( 'trust', $types, true ) || in_array( 'logos', $types, true ) ) {
			$credibility += 25;
		}
		if ( preg_match( '/\b(\d+\+?\s*(customers|clients|users|reviews)|as seen in)\b/i', $full ) ) {
			$credibility += 15;
		}
		$trust_score = isset( $builder_payload['ux_scores']['trust_score'] ) ? (int) $builder_payload['ux_scores']['trust_score'] : 0;
		if ( $trust_score > 0 ) {
			$credibility = (int) round( ( $credibility + $trust_score ) / 2 );
		}

		$scores = array(
			'clarity'         => self::clamp_score( $clarity ),
			'specificity'     => self::clamp_score( $specificity ),
			'differentiation' => self::clamp_score( $differentiation ),
			'credibility'     => self::clamp_score( $credibility ),
		);
		$scores['overall'] = (int) round(
			$scores['clarity'] * 0.3
			+ $scores['specificity'] * 0.25
			+ $scores['differentiation'] * 0.2
			+ $scores['credibility'] * 0.25
		);

		return $scores;
	}

	/**
	 * @param string $text Text.
	 * @return bool
	 */
	private static function has_outcome_language( $text ) {
		return (bool) preg_match( '/\b(increase|reduce|save|grow|boost|cut|improve|get more|achieve|deliver)\b/i', $text );
	}

	/**
	 * @param string $text Text.
	 * @return bool
	 */
	private static function has_differentiation_language( $text ) {
		return (bool) preg_match( '/\b(only|unique|exclusive|unlike|different|proprietary|patented|first)\b/i', $text );
	}

	/**
	 * @param string $text Headline.
	 * @return bool
	 */
	private static function is_generic_headline( $text ) {
		$generic = array(
			'welcome',
			'home',
			'about us',
			'contact us',
			'our services',
			'learn more',
			'get started',
			'click here',
		);
		$lower = strtolower( trim( $text ) );
		foreach ( $generic as $g ) {
			if ( $lower === $g || $lower === $g . '!' ) {
				return true;
			}
		}
		return strlen( $lower ) < 8;
	}

	/**
	 * @param string $text Text.
	 * @return int
	 */
	private static function word_count( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );
		if ( '' === $text ) {
			return 0;
		}
		return count( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) );
	}

	/**
	 * @param int|float $score Raw score.
	 * @return int
	 */
	private static function clamp_score( $score ) {
		return max( 0, min( 100, (int) round( $score ) ) );
	}
}
