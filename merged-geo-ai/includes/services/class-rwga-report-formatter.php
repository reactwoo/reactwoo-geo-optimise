<?php
/**
 * Structured report rendering for Geo AI outputs.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts workflow payloads into readable, sanitised HTML reports.
 */
class RWGA_Report_Formatter {

	/**
	 * @return array<string, array<string, bool>>
	 */
	private static function allowed_tags() {
		return array(
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'h5'         => array(),
			'p'          => array( 'class' => true ),
			'ul'         => array( 'class' => true ),
			'ol'         => array(),
			'li'         => array( 'class' => true ),
			'div'        => array( 'class' => true ),
			'span'       => array( 'class' => true ),
			'strong'     => array( 'class' => true ),
			'em'         => array(),
			'br'         => array(),
			'blockquote' => array( 'class' => true ),
		);
	}

	/**
	 * @param string $html Raw html.
	 * @return string
	 */
	private static function clean( $html ) {
		$html = is_string( $html ) ? $html : '';
		return wp_kses( $html, self::allowed_tags() );
	}

	/**
	 * @param array<string, mixed> $result Analysis result.
	 * @return string
	 */
	public static function format_analysis_report( array $run, array $findings = array(), array $recommendations = array() ) {
		$summary = isset( $run['summary'] ) ? (string) $run['summary'] : '';
		$score   = isset( $run['score'] ) ? (string) $run['score'] : '—';
		$conf    = isset( $run['confidence'] ) ? (string) $run['confidence'] : '—';
		if ( empty( $findings ) && isset( $run['findings'] ) && is_array( $run['findings'] ) ) {
			$findings = $run['findings'];
		}

		$html  = self::render_section( __( 'Executive Summary', 'reactwoo-geo-ai' ), $summary );
		$html .= '<h2>' . esc_html__( 'Overall Score', 'reactwoo-geo-ai' ) . '</h2>';
		$html .= '<p><strong>' . esc_html( $score ) . '</strong> / 100 &mdash; ' . esc_html__( 'Confidence', 'reactwoo-geo-ai' ) . ': ' . esc_html( $conf ) . '</p>';
		$html .= '<h2>' . esc_html__( 'Findings by Category', 'reactwoo-geo-ai' ) . '</h2>';

		if ( empty( $findings ) ) {
			$html .= '<p>' . esc_html__( 'No findings were produced for this run.', 'reactwoo-geo-ai' ) . '</p>';
		} else {
			foreach ( $findings as $finding ) {
				$f     = is_array( $finding ) ? $finding : array();
				$title = isset( $f['title'] ) ? (string) $f['title'] : '';
				$cat   = isset( $f['category'] ) ? (string) $f['category'] : 'general';
				$sev   = isset( $f['severity'] ) ? (string) $f['severity'] : 'medium';
				$evi   = isset( $f['evidence'] ) ? (string) $f['evidence'] : '';
				$hint  = isset( $f['recommendation_hint'] ) ? (string) $f['recommendation_hint'] : '';
				$html .= '<h3>' . esc_html( $title ) . '</h3>';
				$html .= '<p><strong>' . esc_html__( 'Category', 'reactwoo-geo-ai' ) . ':</strong> ' . esc_html( $cat ) . ' &mdash; <strong>' . esc_html__( 'Severity', 'reactwoo-geo-ai' ) . ':</strong> ' . esc_html( $sev ) . '</p>';
				$html .= self::render_paragraphs( $evi );
				if ( '' !== trim( $hint ) ) {
					$html .= '<p><strong>' . esc_html__( 'Suggested next step:', 'reactwoo-geo-ai' ) . '</strong> ' . esc_html( $hint ) . '</p>';
				}
			}
		}
		if ( ! empty( $recommendations ) ) {
			$html .= self::format_recommendation_report( $recommendations, array( 'show_title' => true ) );
		}
		return self::clean( $html );
	}

	/**
	 * @param array<string, mixed> $recommendation Recommendation row/payload.
	 * @return string
	 */
	/**
	 * Human label for a recommendation category key.
	 *
	 * @param string $cat Category slug.
	 * @return string
	 */
	public static function recommendation_category_label( $cat ) {
		$cat = sanitize_key( (string) $cat );
		$labels = array(
			'messaging'       => __( 'Copy & messaging', 'reactwoo-geo-ai' ),
			'conversion'      => __( 'CTAs & conversion', 'reactwoo-geo-ai' ),
			'trust'           => __( 'Trust & proof', 'reactwoo-geo-ai' ),
			'layout'          => __( 'Layout & structure', 'reactwoo-geo-ai' ),
			'performance'     => __( 'Performance', 'reactwoo-geo-ai' ),
			'accessibility'   => __( 'Accessibility', 'reactwoo-geo-ai' ),
			'content'         => __( 'Content', 'reactwoo-geo-ai' ),
			'general'         => __( 'General', 'reactwoo-geo-ai' ),
		);
		return isset( $labels[ $cat ] ) ? $labels[ $cat ] : ucwords( str_replace( '_', ' ', $cat ) );
	}

	/**
	 * CSS modifier for priority pill (matches finding severity styling).
	 *
	 * @param string $p priority_level raw.
	 * @return string
	 */
	private static function priority_badge_class( $p ) {
		$p = sanitize_key( (string) $p );
		if ( 'high' === $p ) {
			return 'rwga-rec-priority--high';
		}
		if ( 'low' === $p ) {
			return 'rwga-rec-priority--low';
		}
		return 'rwga-rec-priority--medium';
	}

	/**
	 * Short label for priority pill.
	 *
	 * @param string $p priority_level.
	 * @return string
	 */
	private static function priority_badge_label( $p ) {
		$p = sanitize_key( (string) $p );
		if ( 'high' === $p ) {
			return __( 'High priority', 'reactwoo-geo-ai' );
		}
		if ( 'low' === $p ) {
			return __( 'Low priority', 'reactwoo-geo-ai' );
		}
		return __( 'Medium priority', 'reactwoo-geo-ai' );
	}

	/**
	 * Decode suggested_copy_json from a DB row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, string>
	 */
	public static function decode_suggested_copy( array $row ) {
		if ( empty( $row['suggested_copy_json'] ) || ! is_string( $row['suggested_copy_json'] ) ) {
			return array();
		}
		$decoded = json_decode( $row['suggested_copy_json'], true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * HTML for one recommendation card (placement + paste-ready copy + context).
	 * Layout mirrors analysis finding cards: head row (priority + title + category), then sections.
	 *
	 * @param array<string, mixed> $row Recommendation row.
	 * @param array<string, mixed> $opts Optional: tag (div|li), show_category_badge (bool).
	 * @return string
	 */
	public static function format_recommendation_card_html( array $row, array $opts = array() ) {
		$tag      = isset( $opts['tag'] ) && 'li' === $opts['tag'] ? 'li' : 'div';
		$show_cat = ! isset( $opts['show_category_badge'] ) || false !== $opts['show_category_badge'];

		$title    = isset( $row['title'] ) ? (string) $row['title'] : '';
		$problem  = isset( $row['problem'] ) ? trim( (string) $row['problem'] ) : '';
		$why      = isset( $row['why_it_matters'] ) ? trim( (string) $row['why_it_matters'] ) : '';
		$tactic   = isset( $row['recommendation'] ) ? trim( (string) $row['recommendation'] ) : '';
		$place    = isset( $row['page_placement'] ) ? trim( (string) $row['page_placement'] ) : '';
		$prio     = isset( $row['priority_level'] ) ? sanitize_key( (string) $row['priority_level'] ) : 'medium';
		$cat      = isset( $row['category'] ) ? sanitize_key( (string) $row['category'] ) : '';
		$copy     = self::decode_suggested_copy( $row );
		if ( empty( $copy ) && isset( $row['suggested_copy'] ) && is_array( $row['suggested_copy'] ) ) {
			$copy = $row['suggested_copy'];
		}

		$html  = '<' . $tag . ' class="rwga-rec-card">';
		$html .= '<div class="rwga-rec-card__head">';
		$html .= '<span class="rwga-rec-priority ' . esc_attr( self::priority_badge_class( $prio ) ) . '">' . esc_html( self::priority_badge_label( $prio ) ) . '</span>';
		$html .= '<strong class="rwga-rec-card__title">' . esc_html( $title ) . '</strong>';
		if ( $show_cat && '' !== $cat ) {
			$html .= '<span class="rwga-rec-card__category description">' . esc_html( self::recommendation_category_label( $cat ) ) . '</span>';
		}
		$html .= '</div>';

		if ( '' !== $place ) {
			$html .= '<p class="rwga-rec-card__where"><strong>' . esc_html__( 'Where to apply', 'reactwoo-geo-ai' ) . '</strong> — ' . esc_html( $place ) . '</p>';
		}

		$has_paste = ! empty( $copy['primary_cta_label'] ) || ! empty( $copy['headline'] ) || ! empty( $copy['subheadline'] );

		if ( '' !== $problem || '' !== $why ) {
			$html .= '<div class="rwga-rec-card__section rwga-rec-card__section--context">';
			$html .= '<h5>' . esc_html__( 'Context', 'reactwoo-geo-ai' ) . '</h5>';
			if ( '' !== $problem ) {
				$html .= '<p class="rwga-rec-card__meta"><strong>' . esc_html__( 'Issue', 'reactwoo-geo-ai' ) . '</strong> — ' . esc_html( $problem ) . '</p>';
			}
			if ( '' !== $why ) {
				$html .= '<p class="rwga-rec-card__meta"><strong>' . esc_html__( 'Why it matters', 'reactwoo-geo-ai' ) . '</strong> — ' . esc_html( $why ) . '</p>';
			}
			$html .= '</div>';
		}

		if ( $has_paste ) {
			$html .= '<div class="rwga-rec-card__section rwga-rec-card__section--paste">';
			$html .= '<h5>' . esc_html__( 'Suggested copy (paste-ready)', 'reactwoo-geo-ai' ) . '</h5>';
			if ( ! empty( $copy['replace_this'] ) ) {
				$html .= '<p class="rwga-rec-card__replace"><em>' . esc_html__( 'Replace or update:', 'reactwoo-geo-ai' ) . '</em> ' . esc_html( (string) $copy['replace_this'] ) . '</p>';
			}
			if ( ! empty( $copy['headline'] ) ) {
				$html .= '<p class="rwga-rec-card__field"><strong>' . esc_html__( 'Headline', 'reactwoo-geo-ai' ) . '</strong></p>';
				$html .= '<blockquote class="rwga-paste-copy">' . esc_html( (string) $copy['headline'] ) . '</blockquote>';
			}
			if ( ! empty( $copy['subheadline'] ) ) {
				$html .= '<p class="rwga-rec-card__field"><strong>' . esc_html__( 'Subheadline / supporting line', 'reactwoo-geo-ai' ) . '</strong></p>';
				$html .= '<blockquote class="rwga-paste-copy">' . esc_html( (string) $copy['subheadline'] ) . '</blockquote>';
			}
			if ( ! empty( $copy['primary_cta_label'] ) ) {
				$html .= '<p class="rwga-rec-card__cta"><strong>' . esc_html__( 'Primary button', 'reactwoo-geo-ai' ) . '</strong> — ' . esc_html( (string) $copy['primary_cta_label'] ) . '</p>';
			}
			if ( ! empty( $copy['secondary_cta_label'] ) ) {
				$html .= '<p class="rwga-rec-card__cta"><strong>' . esc_html__( 'Secondary button', 'reactwoo-geo-ai' ) . '</strong> — ' . esc_html( (string) $copy['secondary_cta_label'] ) . '</p>';
			}
			if ( ! empty( $copy['supporting_snippet'] ) ) {
				$html .= '<p class="rwga-rec-card__field"><strong>' . esc_html__( 'Trust / proof line', 'reactwoo-geo-ai' ) . '</strong></p>';
				$html .= '<blockquote class="rwga-paste-copy">' . esc_html( (string) $copy['supporting_snippet'] ) . '</blockquote>';
			}
			$html .= '</div>';
		} elseif ( '' !== $tactic ) {
			$html .= '<div class="rwga-rec-card__section rwga-rec-card__section--paste">';
			$html .= '<h5>' . esc_html__( 'Guidance', 'reactwoo-geo-ai' ) . '</h5>';
			$html .= self::render_paragraphs( $tactic );
			$html .= '</div>';
		}

		$show_tactic = '' !== $tactic && $has_paste;
		if ( $show_tactic && '' !== $problem && strlen( $tactic ) > 15 ) {
			$pct = 0.0;
			similar_text( strtolower( $problem ), strtolower( $tactic ), $pct );
			if ( $pct > 62.0 ) {
				$show_tactic = false;
			}
		}
		if ( $show_tactic ) {
			$html .= '<div class="rwga-rec-card__section rwga-rec-card__section--how">';
			$html .= '<h5>' . esc_html__( 'How to implement', 'reactwoo-geo-ai' ) . '</h5>';
			$html .= '<p class="rwga-rec-card__meta">' . esc_html( $tactic ) . '</p>';
			$html .= '</div>';
		}

		$html .= '</' . $tag . '>';
		return self::clean( $html );
	}

	/**
	 * @param array<int, array<string, mixed>>|array<string, mixed> $recommendations List or single card.
	 * @param array<string, mixed>                                  $context         Options: show_title.
	 * @return string
	 */
	public static function format_recommendation_report( array $recommendations, array $context = array() ) {
		$list = array();
		if ( isset( $recommendations['title'] ) || isset( $recommendations['recommendation'] ) ) {
			$list[] = $recommendations;
		} else {
			$list = $recommendations;
		}
		$grouped = array();
		foreach ( $list as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cat = isset( $row['category'] ) ? sanitize_key( (string) $row['category'] ) : 'general';
			if ( ! isset( $grouped[ $cat ] ) ) {
				$grouped[ $cat ] = array();
			}
			$grouped[ $cat ][] = $row;
		}
		$html = '';
		if ( ! empty( $context['show_title'] ) ) {
			$html .= '<h2>' . esc_html__( 'Recommendation report', 'reactwoo-geo-ai' ) . '</h2>';
		}
		$html .= '<h3>' . esc_html__( 'What you get in this report', 'reactwoo-geo-ai' ) . '</h3>';
		$html .= '<p>' . esc_html__( 'Each item names where on the page to work, then gives paste-ready headline, subhead, and button labels where applicable. Use your brand voice—treat the quoted lines as drafts to drop into your hero, CTA row, or trust strip.', 'reactwoo-geo-ai' ) . '</p>';

		$html .= '<h2>' . esc_html__( 'Recommended changes', 'reactwoo-geo-ai' ) . '</h2>';

		foreach ( $grouped as $cat => $rows ) {
			$html .= '<h3>' . esc_html( self::recommendation_category_label( $cat ) ) . '</h3>';
			$html .= '<ul class="rwga-rec-list">';
			foreach ( $rows as $r ) {
				$html .= self::format_recommendation_card_html(
					$r,
					array(
						'tag'                   => 'li',
						'show_category_badge'   => false,
					)
				);
			}
			$html .= '</ul>';
		}

		return self::clean( $html );
	}

	/**
	 * @param array<string, mixed> $draft Draft row.
	 * @return string
	 */
	public static function format_implementation_report( array $drafts, array $context = array() ) {
		$list = isset( $drafts['id'] ) ? array( $drafts ) : $drafts;
		$html = '<h2>' . esc_html__( 'Implementation draft', 'reactwoo-geo-ai' ) . '</h2>';
		foreach ( $list as $draft ) {
			if ( ! is_array( $draft ) ) {
				continue;
			}
			$title   = isset( $draft['title'] ) ? (string) $draft['title'] : __( 'Section', 'reactwoo-geo-ai' );
			$problem = isset( $context['issue'] ) ? (string) $context['issue'] : __( 'Issue inferred from recommendation context', 'reactwoo-geo-ai' );
			$html   .= '<h3>' . esc_html( $title ) . '</h3>';
			$html   .= '<p><strong>' . esc_html__( 'Issue:', 'reactwoo-geo-ai' ) . '</strong> ' . esc_html( $problem ) . '</p>';
			$payload = isset( $draft['draft_payload'] ) ? $draft['draft_payload'] : array();
			if ( ! is_array( $payload ) ) {
				$payload = json_decode( (string) $payload, true );
			}
			$payload = is_array( $payload ) ? $payload : array();
			foreach ( $payload as $key => $value ) {
				$html .= '<h4>' . esc_html( ucwords( str_replace( '_', ' ', (string) $key ) ) ) . '</h4>';
				$html .= self::render_paragraphs( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) );
			}
		}
		return self::clean( $html );
	}

	/**
	 * Backward-compatible single draft formatter.
	 *
	 * @param array<string, mixed> $draft Draft row.
	 * @return string
	 */
	public static function format_draft_report( array $draft ) {
		return self::format_implementation_report( array( $draft ) );
	}

	/**
	 * @param string               $heading Heading.
	 * @param string               $body Body.
	 * @param array<int, string>   $items List items.
	 * @return string
	 */
	public static function render_section( $heading, $body = '', array $items = array() ) {
		$html = '<h2>' . esc_html( (string) $heading ) . '</h2>';
		$html .= self::render_paragraphs( (string) $body );
		if ( ! empty( $items ) ) {
			$html .= self::render_bullets( $items );
		}
		return $html;
	}

	/**
	 * @param array<int, string> $items Bullets.
	 * @return string
	 */
	public static function render_bullets( array $items ) {
		if ( empty( $items ) ) {
			return '';
		}
		$html = '<ul>';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( (string) $item ) . '</li>';
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * @param string $text Text.
	 * @return string
	 */
	public static function render_paragraphs( $text ) {
		return wpautop( esc_html( (string) $text ) );
	}
}

