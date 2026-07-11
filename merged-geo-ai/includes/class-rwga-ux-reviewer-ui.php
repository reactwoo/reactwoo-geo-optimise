<?php
/**
 * AI UX Reviewer workspace presenter.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the productised AI UX Reviewer workspace (findings feed, score, actions).
 */
class RWGA_UX_Reviewer_UI {

	/**
	 * @return array<string, string>
	 */
	public static function ux_area_labels() {
		return array(
			'copy_cta'      => __( 'CTA clarity', 'reactwoo-geo-ai' ),
			'accessibility' => __( 'UX & accessibility', 'reactwoo-geo-ai' ),
			'copy'          => __( 'Copy audit', 'reactwoo-geo-ai' ),
			'seo'           => __( 'SEO optimization', 'reactwoo-geo-ai' ),
			'conversion'    => __( 'Conversion friction', 'reactwoo-geo-ai' ),
			'targeting'     => __( 'Geo personalisation', 'reactwoo-geo-ai' ),
			'commerce'      => __( 'Product impact', 'reactwoo-geo-ai' ),
			'experiment'    => __( 'Experiment opportunity', 'reactwoo-geo-ai' ),
			'trust'         => __( 'Trust signals', 'reactwoo-geo-ai' ),
			'mobile'        => __( 'Mobile layout', 'reactwoo-geo-ai' ),
			'variant'       => __( 'Rule/variant mismatch', 'reactwoo-geo-ai' ),
			'general'       => __( 'UX review', 'reactwoo-geo-ai' ),
		);
	}

	/**
	 * @param array<string, mixed> $card Card row.
	 * @return string high|medium|low
	 */
	public static function priority_from_card( array $card ) {
		$impact = sanitize_key( (string) ( $card['expected_impact'] ?? 'medium' ) );
		if ( in_array( $impact, array( 'high', 'critical' ), true ) ) {
			return 'high';
		}
		if ( in_array( $impact, array( 'low', 'minor' ), true ) ) {
			return 'low';
		}
		return 'medium';
	}

	/**
	 * @param string $priority Priority slug.
	 * @return string
	 */
	public static function priority_label( $priority ) {
		$map = array(
			'high'   => __( 'High priority', 'reactwoo-geo-ai' ),
			'medium' => __( 'Medium priority', 'reactwoo-geo-ai' ),
			'low'    => __( 'Low priority', 'reactwoo-geo-ai' ),
		);
		return $map[ $priority ] ?? $map['medium'];
	}

	/**
	 * @param array<string, mixed> $card Card row.
	 * @return string
	 */
	public static function category_label( array $card ) {
		$labels = self::ux_area_labels();
		$area   = sanitize_key( (string) ( $card['ux_area'] ?? 'general' ) );
		return $labels[ $area ] ?? $labels['general'];
	}

	/**
	 * Product audit categories (Copy, SEO, UI, Site architecture).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function audit_type_definitions() {
		return array(
			'copy_audit' => array(
				'label'       => __( 'Copy & CTA', 'reactwoo-geo-ai' ),
				'description' => __( 'Tone of voice, clarity, and CTA messaging.', 'reactwoo-geo-ai' ),
				'ux_areas'    => array( 'copy', 'copy_cta', 'trust', 'conversion' ),
				'keywords'    => array(
					'copy',
					'copywriting',
					'cta',
					'call to action',
					'headline',
					'hero',
					'messaging',
					'tone',
					'wording',
					'value proposition',
					'trust',
					'conversion',
					'clarity',
					'subheading',
					'button text',
					'voice',
				),
			),
			'seo' => array(
				'label'       => __( 'SEO', 'reactwoo-geo-ai' ),
				'description' => __( 'Geo-specific keywords, meta, and discoverability.', 'reactwoo-geo-ai' ),
				'ux_areas'    => array( 'seo', 'targeting' ),
				'keywords'    => array(
					'seo',
					'meta',
					'meta title',
					'meta description',
					'keywords',
					'discoverability',
					'search',
					'title tag',
					'schema',
					'indexing',
					'serp',
					'organic',
					'geo keywords',
				),
			),
			'ui_accessibility' => array(
				'label'       => __( 'UX & accessibility', 'reactwoo-geo-ai' ),
				'description' => __( 'Visual hierarchy, contrast, and mobile layout.', 'reactwoo-geo-ai' ),
				'ux_areas'    => array( 'accessibility', 'mobile' ),
				'keywords'    => array(
					'ui',
					'ux design',
					'accessibility',
					'a11y',
					'wcag',
					'contrast',
					'colour contrast',
					'color contrast',
					'mobile',
					'mobile layout',
					'responsive',
					'layout',
					'visual',
					'visual hierarchy',
					'readability',
					'tap target',
					'spacing',
					'usability',
				),
			),
			'site_architecture' => array(
				'label'       => __( 'Site structure', 'reactwoo-geo-ai' ),
				'description' => __( 'Linking, variants, experiments, and user flow.', 'reactwoo-geo-ai' ),
				'ux_areas'    => array( 'variant', 'experiment', 'commerce', 'general' ),
				'keywords'    => array(
					'architecture',
					'site architecture',
					'navigation',
					'internal links',
					'user flow',
					'structure',
					'experiment',
					'a/b test',
					'ab test',
					'funnel',
					'journey',
					'linking',
					'information architecture',
					'site map',
					'sitemap',
				),
			),
		);
	}

	/**
	 * Review target types for phrase detection.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function target_type_definitions() {
		return array(
			'site' => array(
				'label'    => __( 'Homepage', 'reactwoo-geo-ai' ),
				'keywords' => array( 'homepage', 'home page', 'front page', 'home' ),
			),
			'product' => array(
				'label'    => __( 'Product', 'reactwoo-geo-ai' ),
				'keywords' => array( 'product', 'woocommerce', 'shop page', 'product page' ),
			),
			'variant' => array(
				'label'    => __( 'Variant', 'reactwoo-geo-ai' ),
				'keywords' => array( 'variant', 'variant page', 'geo variant', 'page variant' ),
			),
			'rule' => array(
				'label'    => __( 'Rule', 'reactwoo-geo-ai' ),
				'keywords' => array( 'rule', 'targeting rule', 'visibility rule' ),
			),
			'site_wide' => array(
				'label'    => __( 'Site-wide', 'reactwoo-geo-ai' ),
				'keywords' => array( 'site-wide', 'site wide', 'whole site', 'entire site' ),
			),
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function full_audit_keywords() {
		return array(
			'full audit',
			'complete audit',
			'full review',
			'everything',
			'all categories',
			'all audits',
		);
	}

	/**
	 * Generic review terms that imply a full audit when no category matches.
	 *
	 * @return array<int, string>
	 */
	public static function generic_review_keywords() {
		return array(
			'ux review',
			'ux audit',
			'usability review',
			'run a review',
			'run review',
		);
	}

	/**
	 * @param string $lower     Lowercased phrase.
	 * @param string $term      Keyword or phrase.
	 * @return bool
	 */
	private static function phrase_contains_term( $lower, $term ) {
		$term = strtolower( trim( (string) $term ) );
		if ( '' === $term ) {
			return false;
		}
		if ( false !== strpos( $term, ' ' ) ) {
			return false !== strpos( $lower, $term );
		}
		return (bool) preg_match( '/\b' . preg_quote( $term, '/' ) . '\b/', $lower );
	}

	/**
	 * @param string           $lower Lowercased phrase.
	 * @param array<int,string> $terms Terms to match.
	 * @return bool
	 */
	private static function phrase_matches_any_term( $lower, array $terms ) {
		foreach ( $terms as $term ) {
			if ( self::phrase_contains_term( $lower, $term ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_detection_rules() {
		$scope_keywords = array();
		foreach ( self::audit_type_definitions() as $slug => $def ) {
			$scope_keywords[ $slug ] = isset( $def['keywords'] ) && is_array( $def['keywords'] ) ? array_values( $def['keywords'] ) : array();
		}

		$target_keywords = array();
		foreach ( self::target_type_definitions() as $slug => $def ) {
			$target_keywords[ $slug ] = isset( $def['keywords'] ) && is_array( $def['keywords'] ) ? array_values( $def['keywords'] ) : array();
		}

		return array(
			'scopes'       => $scope_keywords,
			'targets'      => $target_keywords,
			'fullAudit'    => self::full_audit_keywords(),
			'genericReview'=> self::generic_review_keywords(),
			'genericUx'    => array( 'ux', 'review', 'audit' ),
		);
	}

	/**
	 * Keyword glossary groups for the review assistant (targeting-style hint cloud).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_assistant_keyword_hints() {
		return array(
			array(
				'label' => __( 'Examples', 'reactwoo-geo-ai' ),
				'items' => array(
					array(
						'label'  => __( 'Homepage copy', 'reactwoo-geo-ai' ),
						'insert' => __( 'Run a UX review on the copy of the homepage', 'reactwoo-geo-ai' ),
					),
					array(
						'label'  => __( 'Landing page SEO', 'reactwoo-geo-ai' ),
						'insert' => __( 'Check SEO on my landing page', 'reactwoo-geo-ai' ),
					),
					array(
						'label'  => __( 'Contact page UX', 'reactwoo-geo-ai' ),
						'insert' => __( 'Accessibility and UX review of the contact page', 'reactwoo-geo-ai' ),
					),
				),
			),
		);
	}

	/**
	 * @param string $lower Lowercased phrase.
	 * @return array<int, string>
	 */
	private static function detect_audit_scopes_from_phrase( $lower ) {
		if ( self::phrase_matches_any_term( $lower, self::full_audit_keywords() ) ) {
			return self::all_audit_scope_slugs();
		}

		$scopes = array();
		foreach ( self::audit_type_definitions() as $slug => $def ) {
			$keywords = isset( $def['keywords'] ) && is_array( $def['keywords'] ) ? $def['keywords'] : array();
			if ( self::phrase_matches_any_term( $lower, $keywords ) ) {
				$scopes[] = $slug;
			}
		}

		if ( empty( $scopes ) ) {
			if (
				self::phrase_matches_any_term( $lower, self::generic_review_keywords() )
				|| self::phrase_matches_any_term( $lower, array( 'ux', 'review', 'audit' ) )
			) {
				return self::all_audit_scope_slugs();
			}
		}

		return array_values( array_unique( $scopes ) );
	}

	/**
	 * @param string                         $lower         Lowercased phrase.
	 * @param array<int, WP_Post|object>     $pages         Pages/products.
	 * @param int                            $front_page_id Front page ID.
	 * @return array<string, mixed>
	 */
	private static function detect_target_from_phrase( $lower, array $pages, $front_page_id = 0 ) {
		$target_type = 'page';
		$page_id     = 0;
		$product_id  = 0;
		$chips       = array();
		$matched     = false;

		$target_defs = self::target_type_definitions();
		$ordered     = array();
		foreach ( $target_defs as $slug => $def ) {
			$keywords = isset( $def['keywords'] ) && is_array( $def['keywords'] ) ? $def['keywords'] : array();
			usort(
				$keywords,
				static function ( $a, $b ) {
					return strlen( (string) $b ) - strlen( (string) $a );
				}
			);
			$ordered[ $slug ] = $keywords;
		}

		foreach ( $ordered as $slug => $keywords ) {
			if ( ! self::phrase_matches_any_term( $lower, $keywords ) ) {
				continue;
			}
			$label = (string) ( $target_defs[ $slug ]['label'] ?? $slug );
			if ( 'site' === $slug || 'site_wide' === $slug ) {
				$target_type = 'site';
				$page_id     = (int) $front_page_id;
			} elseif ( 'product' === $slug ) {
				$target_type = 'product';
			} elseif ( 'variant' === $slug ) {
				$target_type = 'variant';
			} elseif ( 'rule' === $slug ) {
				$target_type = 'rule';
			}
			$chips[] = array(
				'label' => $label,
				'type'  => 'target',
			);
			$matched = true;
			break;
		}

		if ( self::phrase_contains_term( $lower, 'landing page' ) && ! $matched ) {
			$chips[] = array(
				'label' => __( 'Landing page', 'reactwoo-geo-ai' ),
				'type'  => 'page',
			);
		}

		foreach ( $pages as $p ) {
			if ( ! ( $p instanceof WP_Post ) ) {
				continue;
			}
			$title = trim( (string) $p->post_title );
			if ( '' === $title ) {
				continue;
			}
			if ( false !== strpos( $lower, strtolower( $title ) ) ) {
				if ( 'product' === $p->post_type ) {
					$target_type = 'product';
					$product_id  = (int) $p->ID;
				} elseif ( 'page' === $p->post_type ) {
					$target_type = 'page';
					$page_id     = (int) $p->ID;
				}
				$chips[] = array(
					'label' => $title,
					'type'  => 'page',
				);
				break;
			}
		}

		return array(
			'target_type' => $target_type,
			'page_id'     => $page_id,
			'product_id'  => $product_id,
			'chips'       => $chips,
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function all_audit_scope_slugs() {
		return array_keys( self::audit_type_definitions() );
	}

	/**
	 * @param array<string, mixed> $card Card row.
	 * @return string
	 */
	public static function audit_scope_for_card( array $card ) {
		$area = sanitize_key( (string) ( $card['ux_area'] ?? 'general' ) );
		foreach ( self::audit_type_definitions() as $slug => $def ) {
			$areas = isset( $def['ux_areas'] ) && is_array( $def['ux_areas'] ) ? $def['ux_areas'] : array();
			if ( in_array( $area, $areas, true ) ) {
				return $slug;
			}
		}
		return 'site_architecture';
	}

	/**
	 * @param mixed $raw Posted audit scope values.
	 * @return array<int, string>
	 */
	public static function parse_audit_scopes_from_input( $raw ) {
		$allowed = self::all_audit_scope_slugs();
		$scopes  = array();

		if ( is_array( $raw ) ) {
			foreach ( $raw as $scope ) {
				$scope = sanitize_key( (string) $scope );
				if ( in_array( $scope, $allowed, true ) ) {
					$scopes[] = $scope;
				}
			}
		} elseif ( is_string( $raw ) && '' !== $raw ) {
			$scope = sanitize_key( $raw );
			if ( 'full' === $scope ) {
				return $allowed;
			}
			if ( in_array( $scope, $allowed, true ) ) {
				$scopes[] = $scope;
			}
		}

		$scopes = array_values( array_unique( $scopes ) );
		return ! empty( $scopes ) ? $scopes : $allowed;
	}

	/**
	 * @param array<int, array<string, mixed>> $cards Finding cards.
	 * @param array<int, string>               $scopes Audit scopes to keep.
	 * @return array<int, array<string, mixed>>
	 */
	public static function filter_cards_by_audit_scopes( array $cards, array $scopes ) {
		$scopes = array_values( array_unique( array_filter( array_map( 'sanitize_key', $scopes ) ) ) );
		if ( empty( $scopes ) ) {
			return $cards;
		}

		$filtered = array();
		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			if ( in_array( self::audit_scope_for_card( $card ), $scopes, true ) ) {
				$filtered[] = $card;
			}
		}
		return $filtered;
	}

	/**
	 * @param array<int, array<string, mixed>> $cards Cards in a category bucket.
	 * @return array{percent: int|null, status: string, issues: int, high_issues: int}
	 */
	public static function summarize_category_bucket( array $cards ) {
		$issues = count( $cards );
		if ( $issues <= 0 ) {
			return array(
				'percent'     => 100,
				'status'      => 'pass',
				'issues'      => 0,
				'high_issues' => 0,
			);
		}

		$penalty = 0;
		$high    = 0;
		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			$priority = self::priority_from_card( $card );
			if ( 'high' === $priority ) {
				++$high;
				$penalty += 12;
			} elseif ( 'low' === $priority ) {
				$penalty += 3;
			} else {
				$penalty += 6;
			}
		}

		$percent = max( 0, min( 100, 100 - $penalty ) );
		$status  = 'pass';
		if ( $high >= 2 || $percent < 50 ) {
			$status = 'critical';
		} elseif ( $percent < 80 ) {
			$status = 'fail';
		} elseif ( $percent < 92 ) {
			$status = 'warn';
		}

		return array(
			'percent'     => $percent,
			'status'      => $status,
			'issues'      => $issues,
			'high_issues' => $high,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $cards Finding cards.
	 * @param array<int, string>|null          $selected_scopes Scopes included in the last run.
	 * @param bool                             $has_review Whether a review has been run.
	 * @return array<string, array<string, mixed>>
	 */
	public static function compute_category_summaries( array $cards, $selected_scopes = null, $has_review = false ) {
		$definitions = self::audit_type_definitions();
		$scopes      = is_array( $selected_scopes ) ? $selected_scopes : self::all_audit_scope_slugs();
		$buckets     = array();
		foreach ( array_keys( $definitions ) as $slug ) {
			$buckets[ $slug ] = array();
		}
		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			$scope = self::audit_scope_for_card( $card );
			if ( ! isset( $buckets[ $scope ] ) ) {
				$buckets[ $scope ] = array();
			}
			$buckets[ $scope ][] = $card;
		}

		$summaries = array();
		foreach ( $definitions as $slug => $def ) {
			$included = in_array( $slug, $scopes, true );
			if ( ! $has_review ) {
				$summaries[ $slug ] = array(
					'slug'        => $slug,
					'label'       => (string) $def['label'],
					'description' => (string) $def['description'],
					'included'    => true,
					'status'      => 'pending',
					'percent'     => null,
					'issues'      => 0,
					'status_label'=> __( 'Not run', 'reactwoo-geo-ai' ),
				);
				continue;
			}
			if ( ! $included ) {
				$summaries[ $slug ] = array(
					'slug'        => $slug,
					'label'       => (string) $def['label'],
					'description' => (string) $def['description'],
					'included'    => false,
					'status'      => 'skipped',
					'percent'     => null,
					'issues'      => 0,
					'status_label'=> __( 'Skipped', 'reactwoo-geo-ai' ),
				);
				continue;
			}

			$bucket = self::summarize_category_bucket( $buckets[ $slug ] ?? array() );
			$labels = array(
				'pass'     => __( 'Pass', 'reactwoo-geo-ai' ),
				'warn'     => __( 'Review', 'reactwoo-geo-ai' ),
				'fail'     => __( 'Fail', 'reactwoo-geo-ai' ),
				'critical' => __( 'Critical', 'reactwoo-geo-ai' ),
			);
			$summaries[ $slug ] = array(
				'slug'         => $slug,
				'label'        => (string) $def['label'],
				'description'  => (string) $def['description'],
				'included'     => true,
				'status'       => $bucket['status'],
				'percent'      => $bucket['percent'],
				'issues'       => $bucket['issues'],
				'status_label' => $labels[ $bucket['status'] ] ?? $labels['pass'],
			);
		}

		return $summaries;
	}

	/**
	 * @param string $status Category status slug.
	 * @return string
	 */
	public static function category_status_label( $status ) {
		$labels = array(
			'pass'     => __( 'Pass', 'reactwoo-geo-ai' ),
			'warn'     => __( 'Review', 'reactwoo-geo-ai' ),
			'fail'     => __( 'Fail', 'reactwoo-geo-ai' ),
			'critical' => __( 'Critical', 'reactwoo-geo-ai' ),
			'pending'  => __( 'Not run', 'reactwoo-geo-ai' ),
			'skipped'  => __( 'Skipped', 'reactwoo-geo-ai' ),
		);
		return $labels[ sanitize_key( (string) $status ) ] ?? $labels['pending'];
	}

	/**
	 * @param array<string, mixed> $meta Session metadata.
	 * @return void
	 */
	public static function set_session_meta( array $meta ) {
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return;
		}
		set_transient( 'rwga_ux_review_meta_' . $uid, $meta, HOUR_IN_SECONDS );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_session_meta() {
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return array();
		}
		$cached = get_transient( 'rwga_ux_review_meta_' . $uid );
		return is_array( $cached ) ? $cached : array();
	}

	/**
	 * @param array<int, array<string, mixed>> $cards Finding cards.
	 * @return array<string, mixed>
	 */
	public static function compute_score_summary( array $cards ) {
		$count = count( $cards );
		if ( $count <= 0 ) {
			return array(
				'score'                  => null,
				'conversion_potential'   => __( '—', 'reactwoo-geo-ai' ),
				'user_friction'          => __( '—', 'reactwoo-geo-ai' ),
				'geo_personalisation'    => __( '—', 'reactwoo-geo-ai' ),
				'accessibility'          => __( '—', 'reactwoo-geo-ai' ),
				'key_recommendations'    => array(),
			);
		}

		$high = 0;
		$areas = array();
		foreach ( $cards as $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			if ( 'high' === self::priority_from_card( $card ) ) {
				++$high;
			}
			$areas[] = sanitize_key( (string) ( $card['ux_area'] ?? 'general' ) );
		}

		$score = max( 35, min( 95, 88 - ( $high * 6 ) - max( 0, $count - 3 ) * 2 ) );

		$has_targeting = in_array( 'targeting', $areas, true );
		$has_commerce  = in_array( 'commerce', $areas, true );
		$has_experiment = in_array( 'experiment', $areas, true );

		$key = array();
		foreach ( array_slice( $cards, 0, 4 ) as $idx => $card ) {
			if ( ! is_array( $card ) || empty( $card['title'] ) ) {
				continue;
			}
			$key[] = array(
				'index' => $idx + 1,
				'title' => (string) $card['title'],
				'anchor'=> 'rwga-finding-' . $idx,
			);
		}

		return array(
			'score'                => $score,
			'conversion_potential' => $has_commerce || $high > 0
				? __( 'High', 'reactwoo-geo-ai' )
				: __( 'Moderate', 'reactwoo-geo-ai' ),
			'user_friction'        => $high >= 2
				? __( 'Elevated', 'reactwoo-geo-ai' )
				: __( 'Moderate', 'reactwoo-geo-ai' ),
			'geo_personalisation'  => $has_targeting
				? __( 'Opportunity', 'reactwoo-geo-ai' )
				: __( 'Low', 'reactwoo-geo-ai' ),
			'accessibility'        => in_array( 'accessibility', $areas, true )
				? __( 'Needs attention', 'reactwoo-geo-ai' )
				: __( 'Review recommended', 'reactwoo-geo-ai' ),
			'key_recommendations'  => $key,
			'has_experiment'       => $has_experiment,
		);
	}

	/**
	 * Example findings for empty state (clearly labelled).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_example_findings() {
		return array(
			array(
				'title'          => __( 'Low contrast on CTA buttons', 'reactwoo-geo-ai' ),
				'problem'        => __( 'The primary CTA may not stand out enough on mobile screens.', 'reactwoo-geo-ai' ),
				'recommendation' => __( 'Increase contrast and button size for thumb reach on small viewports.', 'reactwoo-geo-ai' ),
				'ux_area'        => 'accessibility',
				'expected_impact'=> 'high',
				'is_example'     => true,
			),
			array(
				'title'          => __( 'Value proposition clarity', 'reactwoo-geo-ai' ),
				'problem'        => __( 'The hero headline may be too generic for geo-targeted visitors.', 'reactwoo-geo-ai' ),
				'recommendation' => __( 'Lead with a region-specific outcome and proof point above the fold.', 'reactwoo-geo-ai' ),
				'ux_area'        => 'copy',
				'expected_impact'=> 'medium',
				'is_example'     => true,
			),
			array(
				'title'          => __( 'Geo-specific meta gaps', 'reactwoo-geo-ai' ),
				'problem'        => __( 'Title and description may not reflect regional keywords or intent.', 'reactwoo-geo-ai' ),
				'recommendation' => __( 'Add geo-modifiers to meta title and description for priority markets.', 'reactwoo-geo-ai' ),
				'ux_area'        => 'targeting',
				'expected_impact'=> 'medium',
				'is_example'     => true,
			),
			array(
				'title'          => __( 'Internal link optimization', 'reactwoo-geo-ai' ),
				'problem'        => __( 'Key conversion paths may lack inline links from high-traffic sections.', 'reactwoo-geo-ai' ),
				'recommendation' => __( 'Add contextual links to contact or pricing from the solutions section.', 'reactwoo-geo-ai' ),
				'ux_area'        => 'general',
				'expected_impact'=> 'low',
				'is_example'     => true,
			),
		);
	}

	/**
	 * @param array<string, mixed> $card Card.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_card_actions( array $card ) {
		$actions = array();
		$suggested = sanitize_key( (string) ( $card['suggested_action'] ?? '' ) );
		$available = ! empty( $card['available_now'] );
		$pending_url = admin_url( 'admin.php?page=rwga-intelligence-actions&status=pending&workflow_key=ux_opportunity_review' );
		$drafts_url  = admin_url( 'admin.php?page=rwga-implementation-drafts' );

		if ( $available && 'create_implementation_draft' === $suggested ) {
			$actions[] = array(
				'label'  => __( 'Create draft', 'reactwoo-geo-ai' ),
				'url'    => $drafts_url,
				'class'  => 'rwgc-btn rwgc-btn--primary rwgc-btn--sm',
				'active' => true,
			);
			$actions[] = array(
				'label'  => __( 'Apply suggestion', 'reactwoo-geo-ai' ),
				'url'    => $pending_url,
				'class'  => 'rwgc-btn rwgc-btn--secondary rwgc-btn--sm',
				'active' => true,
				'note'   => __( 'Approval required', 'reactwoo-geo-ai' ),
			);
		} elseif ( $available && 'open_admin_route' === $suggested && ! empty( $card['admin_page'] ) ) {
			$args = isset( $card['query_args'] ) && is_array( $card['query_args'] ) ? $card['query_args'] : array();
			$args['page'] = sanitize_key( (string) $card['admin_page'] );
			$actions[] = array(
				'label'  => __( 'Open rule', 'reactwoo-geo-ai' ),
				'url'    => add_query_arg( $args, admin_url( 'admin.php' ) ),
				'class'  => 'rwgc-btn rwgc-btn--primary rwgc-btn--sm',
				'active' => true,
			);
		} elseif ( $available && 'create_optimise_test_prefill' === $suggested ) {
			$actions[] = array(
				'label'  => __( 'Create A/B test', 'reactwoo-geo-ai' ),
				'url'    => admin_url( 'admin.php?page=rwgo-create-test' ),
				'class'  => 'rwgc-btn rwgc-btn--primary rwgc-btn--sm',
				'active' => true,
			);
		}

		if ( empty( $actions ) && ! empty( $card['upgrade_label'] ) ) {
			$actions[] = array(
				'label'  => (string) $card['upgrade_label'],
				'url'    => '',
				'class'  => 'rwgc-geo-badge rwgc-geo-badge--locked',
				'active' => false,
			);
		}

		return $actions;
	}

	/**
	 * Local phrase parser for the UX review chat assistant (no external URLs).
	 *
	 * @param string                   $phrase        User phrase.
	 * @param array<int, WP_Post|object> $pages       Pages/products for title matching.
	 * @param int                      $front_page_id Front page ID.
	 * @return array<string, mixed>
	 */
	public static function parse_review_phrase( $phrase, array $pages, $front_page_id = 0 ) {
		$phrase = trim( (string) $phrase );
		$lower  = strtolower( $phrase );

		if ( preg_match( '#https?://#i', $phrase ) ) {
			return array(
				'blocked'      => true,
				'block_reason' => __( 'UX reviews run on pages inside your WordPress site only — not external URLs.', 'reactwoo-geo-ai' ),
				'chips'        => array(),
				'audit_scopes' => array(),
				'target_type'  => '',
				'page_id'      => 0,
				'product_id'   => 0,
			);
		}

		$scopes      = self::detect_audit_scopes_from_phrase( $lower );
		$target      = self::detect_target_from_phrase( $lower, $pages, $front_page_id );
		$chips       = isset( $target['chips'] ) && is_array( $target['chips'] ) ? $target['chips'] : array();
		$target_type = (string) ( $target['target_type'] ?? 'page' );
		$page_id     = (int) ( $target['page_id'] ?? 0 );
		$product_id  = (int) ( $target['product_id'] ?? 0 );

		$defs = self::audit_type_definitions();
		foreach ( $scopes as $scope ) {
			if ( isset( $defs[ $scope ]['label'] ) ) {
				$chips[] = array(
					'label' => (string) $defs[ $scope ]['label'],
					'type'  => 'audit',
				);
			}
		}

		return array(
			'blocked'      => false,
			'block_reason' => '',
			'target_type'  => $target_type,
			'page_id'      => $page_id,
			'product_id'   => $product_id,
			'audit_scopes' => $scopes,
			'chips'        => $chips,
		);
	}

	/**
	 * @param array<int, WP_Post|object> $pages Pages list.
	 * @param int                        $front_page_id Front page ID.
	 * @return array<string, mixed>
	 */
	public static function get_assistant_config( array $pages, $front_page_id = 0 ) {
		$audit_labels = array();
		foreach ( self::audit_type_definitions() as $slug => $def ) {
			$audit_labels[ $slug ] = (string) ( $def['label'] ?? $slug );
		}
		$target_labels = array();
		foreach ( self::target_type_definitions() as $slug => $def ) {
			$target_labels[ $slug ] = (string) ( $def['label'] ?? $slug );
		}

		$page_index = array();
		foreach ( $pages as $p ) {
			if ( ! ( $p instanceof WP_Post ) ) {
				continue;
			}
			$page_index[] = array(
				'id'    => (int) $p->ID,
				'title' => (string) $p->post_title,
				'type'  => (string) $p->post_type,
			);
		}

		return array(
			'frontPageId'     => (int) $front_page_id,
			'pages'           => $page_index,
			'auditLabels'     => $audit_labels,
			'targetLabels'    => $target_labels,
			'allScopes'       => self::all_audit_scope_slugs(),
			'detectionRules'  => self::get_detection_rules(),
			'keywordHints'    => self::get_assistant_keyword_hints(),
			'i18n'            => array(
				'detectedLabel'   => __( 'Detected:', 'reactwoo-geo-ai' ),
				'welcome'         => __( 'Tell me what you would like to improve, or choose a review type below.', 'reactwoo-geo-ai' ),
				'externalBlocked' => __( 'UX reviews run on pages inside your WordPress site only — not external URLs.', 'reactwoo-geo-ai' ),
				'applied'         => __( 'I updated the review types and detected setup. Run the review when you are ready, or adjust setup if needed.', 'reactwoo-geo-ai' ),
				'noScopes'        => __( 'I could not detect a review type — choose one below, or try copy, SEO, UX, accessibility, site structure, or full review.', 'reactwoo-geo-ai' ),
				'detecting'       => __( 'Detecting…', 'reactwoo-geo-ai' ),
				'startOver'       => __( 'Start over', 'reactwoo-geo-ai' ),
				'placeholder'     => __( 'Describe the UX review you want to run…', 'reactwoo-geo-ai' ),
				'tryExample'      => __( 'Try an example', 'reactwoo-geo-ai' ),
				'fullReview'      => __( 'Full review', 'reactwoo-geo-ai' ),
				'homepage'        => __( 'Homepage', 'reactwoo-geo-ai' ),
				'pageFallback'    => __( 'Page', 'reactwoo-geo-ai' ),
				'productFallback' => __( 'Product', 'reactwoo-geo-ai' ),
				'variantFallback' => __( 'Variant', 'reactwoo-geo-ai' ),
				'ruleFallback'    => __( 'Rule', 'reactwoo-geo-ai' ),
				'allVisitors'     => __( 'All visitors', 'reactwoo-geo-ai' ),
				'desktop'         => __( 'Desktop', 'reactwoo-geo-ai' ),
				'selectAudit'     => __( 'Select at least one review type.', 'reactwoo-geo-ai' ),
				'selectTarget'    => __( 'Choose a review target in Refine setup.', 'reactwoo-geo-ai' ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $card Card.
	 * @return string
	 */
	public static function affected_target_label( array $card ) {
		if ( ! empty( $card['product_id'] ) ) {
			$title = get_the_title( (int) $card['product_id'] );
			return $title ? sprintf( __( 'Product: %s', 'reactwoo-geo-ai' ), $title ) : __( 'Product', 'reactwoo-geo-ai' );
		}
		if ( ! empty( $card['variant_page_id'] ) ) {
			$title = get_the_title( (int) $card['variant_page_id'] );
			return $title ? sprintf( __( 'Variant: %s', 'reactwoo-geo-ai' ), $title ) : __( 'Variant page', 'reactwoo-geo-ai' );
		}
		if ( ! empty( $card['page_id'] ) ) {
			$title = get_the_title( (int) $card['page_id'] );
			return $title ? sprintf( __( 'Page: %s', 'reactwoo-geo-ai' ), $title ) : __( 'Page', 'reactwoo-geo-ai' );
		}
		if ( ! empty( $card['rule_id'] ) ) {
			return sprintf( __( 'Rule #%s', 'reactwoo-geo-ai' ), (string) $card['rule_id'] );
		}
		return __( 'Review target', 'reactwoo-geo-ai' );
	}

	/**
	 * Load session finding cards for the current user.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_session_cards() {
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return array();
		}
		$cached = get_transient( 'rwga_ux_review_' . $uid );
		return is_array( $cached ) ? $cached : array();
	}

	/**
	 * @param array<string, mixed> $args Workspace context.
	 * @return void
	 */
	public static function render_workspace( array $args = array() ) {
		$defaults = array(
			'display_mode'      => 'fresh',
			'source'            => 'dashboard',
			'page_id'           => 0,
			'product_id'        => 0,
			'variant_page_id'   => 0,
			'rule_id'           => '',
			'engine_source'     => '',
			'action_count'      => 0,
			'capabilities'      => function_exists( 'rwgc_get_suite_capability_map' ) ? rwgc_get_suite_capability_map() : array(),
			'cards'             => array(),
			'session_meta'      => array(),
			'show_inner_nav'    => true,
			'nav_current'       => 'rwga-ux-opportunity-review',
			'form_action_page'  => 'rwga-ux-opportunity-review',
			'wrap_class'        => '',
			'embed'             => false,
		);
		$ctx = wp_parse_args( $args, $defaults );

		$display_mode = sanitize_key( (string) ( $ctx['display_mode'] ?? 'fresh' ) );
		if ( 'result' !== $display_mode ) {
			$display_mode = 'fresh';
		}

		$capabilities = is_array( $ctx['capabilities'] ) ? $ctx['capabilities'] : array();
		$cards        = is_array( $ctx['cards'] ) ? $ctx['cards'] : array();
		$session_meta = isset( $ctx['session_meta'] ) && is_array( $ctx['session_meta'] ) ? $ctx['session_meta'] : array();

		if ( 'result' === $display_mode ) {
			if ( empty( $cards ) ) {
				$cards = self::get_session_cards();
			}
			if ( empty( $session_meta ) ) {
				$session_meta = self::get_session_meta();
			}
			if ( empty( $cards ) ) {
				$display_mode = 'fresh';
				$session_meta = array();
			}
		} else {
			$cards        = array();
			$session_meta = array();
		}

		$has_findings = ! empty( $cards );
		$has_review   = ( 'result' === $display_mode ) && $has_findings;

		foreach ( $cards as $i => $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			if ( class_exists( 'RWGA_UX_Opportunity_Action_Filter', false ) && empty( $card['upgrade_label'] ) && ! isset( $card['available_now'] ) ) {
				$cards[ $i ] = RWGA_UX_Opportunity_Action_Filter::enrich_card( $card, $capabilities );
			}
		}

		$selected_scopes = ( $has_review && isset( $session_meta['audit_scopes'] ) && is_array( $session_meta['audit_scopes'] ) )
			? self::parse_audit_scopes_from_input( $session_meta['audit_scopes'] )
			: self::all_audit_scope_slugs();
		$category_summaries = self::compute_category_summaries( $cards, $selected_scopes, $has_review );
		$audit_type_defs    = self::audit_type_definitions();
		$score_delta        = $has_review && isset( $session_meta['score_delta'] ) ? $session_meta['score_delta'] : null;
		$score_summary      = self::compute_score_summary( $cards );
		$can_run       = current_user_can( RWGA_Capabilities::CAP_RUN_AI )
			&& class_exists( 'RWGA_License', false )
			&& RWGA_License::can_run_workflows();
		$geo_ai_licensed  = ! empty( $capabilities['geo_ai_licensed'] );
		$remote_available = ! empty( $capabilities['remote_ai_available'] );
		$usage_cache      = class_exists( 'RWGA_Usage', false ) ? RWGA_Usage::get_cache() : null;
		$usage_available  = is_array( $usage_cache );
		$license_url      = ( defined( 'RWGO_AI_EMBEDDED' ) && RWGO_AI_EMBEDDED && class_exists( 'RWGO_Optimise_Hub', false ) )
			? RWGO_Optimise_Hub::tab_url( 'settings' )
			: admin_url( 'admin.php?page=rwga-license' );
		$has_run_cap      = current_user_can( RWGA_Capabilities::CAP_RUN_AI );
		$history_url      = ( defined( 'RWGO_AI_EMBEDDED' ) && RWGO_AI_EMBEDDED && class_exists( 'RWGO_Optimise_Hub', false ) )
			? RWGO_Optimise_Hub::tab_url( 'history' )
			: admin_url( 'admin.php?page=rwga-intelligence-actions&workflow_key=ux_opportunity_review' );
		$export_url       = ( defined( 'RWGO_AI_EMBEDDED' ) && RWGO_AI_EMBEDDED && class_exists( 'RWGO_Optimise_Hub', false ) )
			? RWGO_Optimise_Hub::tab_url( 'history' )
			: admin_url( 'admin.php?page=rwga-analyses' );
		$recent_activity  = class_exists( 'RWGO_Optimise_History', false )
			? RWGO_Optimise_History::recent_ai_runs( 3 )
			: array();

		$engine_label = '';
		if ( 'remote_ai' === $ctx['engine_source'] ) {
			$engine_label = __( 'Remote Geo AI', 'reactwoo-geo-ai' );
		} elseif ( 'local_deterministic' === $ctx['engine_source'] ) {
			$engine_label = __( 'Local deterministic review', 'reactwoo-geo-ai' );
		}

		$pages = get_posts(
			array(
				'post_type'      => array( 'page', 'product' ),
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$front_page_id = (int) get_option( 'page_on_front' );
		if ( $front_page_id <= 0 ) {
			$front_page_id = (int) get_option( 'page_for_posts' );
		}

		$flash = isset( $_GET['rwga_ux'] ) ? sanitize_key( wp_unslash( $_GET['rwga_ux'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$flash_error = isset( $_GET['rwga_err'] ) ? sanitize_text_field( wp_unslash( $_GET['rwga_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		include RWGA_PATH . 'admin/views/partials/ux-reviewer-workspace.php';
	}
}
