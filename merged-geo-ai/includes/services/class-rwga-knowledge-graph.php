<?php
/**
 * UX knowledge graph — anonymous benchmark intelligence (read cache).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Local read cache for cross-site UX benchmarks; authoritative store is API-side.
 */
class RWGA_Knowledge_Graph {

	const VERSION = '1.0.0';

	const CACHE_OPTION = 'rwga_knowledge_graph_cache';

	const CACHE_TTL = 86400;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwga_loaded', array( __CLASS__, 'on_loaded' ), 55 );
	}

	/**
	 * @return void
	 */
	public static function on_loaded() {
		add_action( 'rwga_site_intelligence_synced', array( __CLASS__, 'maybe_refresh_remote' ), 15, 2 );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function seed_benchmarks() {
		return array(
			array(
				'id'                 => 'b2b_trust_before_cta',
				'industry'           => 'b2b_saas',
				'page_type'          => 'homepage',
				'finding'            => 'Homepages with social proof before the primary CTA report higher demo conversion intent.',
				'confidence'         => 0.78,
				'sample_size_bucket' => '50-200',
				'region'             => 'global',
				'signal_type'        => 'trust_placement',
			),
			array(
				'id'                 => 'pricing_testimonials_above_table',
				'industry'           => 'hosting',
				'page_type'          => 'pricing',
				'finding'            => 'Pricing pages with testimonials above the pricing table reduce perceived switching risk.',
				'confidence'         => 0.82,
				'sample_size_bucket' => '50-200',
				'region'             => 'EU',
				'signal_type'        => 'trust_placement',
			),
			array(
				'id'                 => 'action_verb_cta',
				'industry'           => 'ecommerce',
				'page_type'          => 'product',
				'finding'            => 'Action-verb CTAs outperform noun-only labels on product pages in mid-funnel tests.',
				'confidence'         => 0.71,
				'sample_size_bucket' => '200+',
				'region'             => 'US',
				'signal_type'        => 'cta_performance',
			),
			array(
				'id'                 => 'currency_first_localisation',
				'industry'           => 'ecommerce',
				'page_type'          => 'pricing',
				'finding'            => 'Currency-first pricing headers improve clarity for cross-border visitors in EU storefronts.',
				'confidence'         => 0.69,
				'sample_size_bucket' => '50-200',
				'region'             => 'EU',
				'signal_type'        => 'localisation',
			),
			array(
				'id'                 => 'hero_single_cta',
				'industry'           => 'general',
				'page_type'          => 'homepage',
				'finding'            => 'Hero sections with one dominant CTA reduce decision friction versus dual competing actions.',
				'confidence'         => 0.74,
				'sample_size_bucket' => '200+',
				'region'             => 'global',
				'signal_type'        => 'conversion_pattern',
			),
		);
	}

	/**
	 * @param string $industry  Industry slug (empty = any).
	 * @param string $page_type Page type slug (empty = any).
	 * @param string $region    ISO region bucket (empty = any).
	 * @param int    $limit     Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function fetch_relevant( $industry = '', $page_type = '', $region = '', $limit = 5 ) {
		$industry  = sanitize_key( (string) $industry );
		$page_type = sanitize_key( (string) $page_type );
		$region    = strtoupper( sanitize_text_field( (string) $region ) );
		$limit     = max( 1, min( 12, (int) $limit ) );

		$rows = self::get_cached_rows();
		$scored = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$score = self::relevance_score( $row, $industry, $page_type, $region );
			if ( $score <= 0 ) {
				continue;
			}
			$row['_relevance'] = $score;
			$scored[]          = $row;
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				$sa = isset( $a['_relevance'] ) ? (float) $a['_relevance'] : 0;
				$sb = isset( $b['_relevance'] ) ? (float) $b['_relevance'] : 0;
				if ( $sa === $sb ) {
					$ca = isset( $a['confidence'] ) ? (float) $a['confidence'] : 0;
					$cb = isset( $b['confidence'] ) ? (float) $b['confidence'] : 0;
					return $cb <=> $ca;
				}
				return $sb <=> $sa;
			}
		);

		$out = array_slice( $scored, 0, $limit );
		foreach ( $out as &$item ) {
			unset( $item['_relevance'] );
		}
		unset( $item );

		/**
		 * @param array<int, array<string, mixed>> $out       Matched benchmark rows.
		 * @param string                           $industry  Industry filter.
		 * @param string                           $page_type Page type filter.
		 * @param string                           $region    Region filter.
		 */
		$out = apply_filters( 'rwga_knowledge_graph_relevant', $out, $industry, $page_type, $region );
		return is_array( $out ) ? $out : array();
	}

	/**
	 * @param array<string, mixed> $args page_id, industry, page_type, region, geo_target.
	 * @return array<string, mixed>
	 */
	public static function benchmark_context( array $args = array() ) {
		$page_id   = isset( $args['page_id'] ) ? (int) $args['page_id'] : 0;
		$industry  = isset( $args['industry'] ) ? sanitize_key( (string) $args['industry'] ) : '';
		$page_type = isset( $args['page_type'] ) ? sanitize_key( (string) $args['page_type'] ) : '';
		$region    = isset( $args['region'] ) ? (string) $args['region'] : '';

		if ( '' === $industry && class_exists( 'RWGA_Local_Intelligence', false ) ) {
			$site = RWGA_Local_Intelligence::get_site_context();
			if ( is_array( $site ) && ! empty( $site['industry'] ) ) {
				$industry = sanitize_key( (string) $site['industry'] );
			}
		}

		if ( '' === $page_type && $page_id > 0 && class_exists( 'RWGA_Page_Context_Builder', false ) ) {
			$ctx = RWGA_Page_Context_Builder::build( $page_id );
			if ( is_array( $ctx ) && ! empty( $ctx['page_type'] ) ) {
				$page_type = sanitize_key( (string) $ctx['page_type'] );
			}
		}

		if ( '' === $region && ! empty( $args['geo_target'] ) ) {
			$region = strtoupper( substr( sanitize_text_field( (string) $args['geo_target'] ), 0, 2 ) );
		}

		$rows = self::fetch_relevant( $industry, $page_type, $region, 5 );

		return array(
			'schema_version' => 1,
			'graph_version'  => self::VERSION,
			'industry'       => $industry,
			'page_type'      => $page_type,
			'region'         => $region,
			'benchmarks'     => $rows,
			'counts'         => array(
				'matched' => count( $rows ),
				'cached'  => count( self::get_cached_rows() ),
			),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_cached_rows() {
		$cached = get_option( self::CACHE_OPTION, array() );
		if ( is_array( $cached ) && ! empty( $cached['rows'] ) && is_array( $cached['rows'] ) ) {
			$fetched = isset( $cached['fetched_at'] ) ? (int) $cached['fetched_at'] : 0;
			if ( $fetched > 0 && ( time() - $fetched ) < self::CACHE_TTL ) {
				return $cached['rows'];
			}
		}
		return self::seed_benchmarks();
	}

	/**
	 * @param array<string, mixed> $upload   Upload response.
	 * @param array<string, mixed> $snapshot Snapshot payload.
	 * @return void
	 */
	public static function maybe_refresh_remote( $upload, $snapshot ) {
		unset( $upload, $snapshot );
		if ( ! class_exists( 'RWGA_Platform_Client', false ) || ! RWGA_Platform_Client::is_configured() ) {
			return;
		}

		$path = apply_filters( 'rwga_knowledge_graph_remote_path', '/api/v5/geo-ai/knowledge/benchmarks', array() );
		if ( ! is_string( $path ) || '' === $path ) {
			return;
		}

		$result = RWGA_Platform_Client::request( 'GET', $path, null, true );
		if ( is_wp_error( $result ) ) {
			return;
		}
		$code = isset( $result['code'] ) ? (int) $result['code'] : 0;
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : null;
		if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
			return;
		}

		$rows = array();
		if ( isset( $data['benchmarks'] ) && is_array( $data['benchmarks'] ) ) {
			$rows = $data['benchmarks'];
		} elseif ( isset( $data['rows'] ) && is_array( $data['rows'] ) ) {
			$rows = $data['rows'];
		}

		if ( array() === $rows ) {
			return;
		}

		update_option(
			self::CACHE_OPTION,
			array(
				'fetched_at' => time(),
				'rows'       => array_slice( $rows, 0, 100 ),
				'source'     => 'remote',
			),
			false
		);
	}

	/**
	 * @param array<string, mixed> $row       Benchmark row.
	 * @param string               $industry  Industry filter.
	 * @param string               $page_type Page type filter.
	 * @param string               $region    Region filter.
	 * @return float
	 */
	private static function relevance_score( array $row, $industry, $page_type, $region ) {
		$score = 0.1;
		$row_industry  = isset( $row['industry'] ) ? sanitize_key( (string) $row['industry'] ) : '';
		$row_page_type = isset( $row['page_type'] ) ? sanitize_key( (string) $row['page_type'] ) : '';
		$row_region    = isset( $row['region'] ) ? strtoupper( sanitize_text_field( (string) $row['region'] ) ) : '';

		if ( '' !== $industry && ( $row_industry === $industry || 'general' === $row_industry ) ) {
			$score += 0.45;
		} elseif ( '' === $industry ) {
			$score += 0.2;
		}

		if ( '' !== $page_type && $row_page_type === $page_type ) {
			$score += 0.35;
		} elseif ( '' === $page_type ) {
			$score += 0.15;
		}

		if ( '' !== $region && ( $row_region === $region || 'GLOBAL' === $row_region ) ) {
			$score += 0.2;
		} elseif ( '' === $region ) {
			$score += 0.1;
		}

		if ( isset( $row['confidence'] ) ) {
			$score += min( 0.2, (float) $row['confidence'] * 0.2 );
		}

		return $score;
	}
}
