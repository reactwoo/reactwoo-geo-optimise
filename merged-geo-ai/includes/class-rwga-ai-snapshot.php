<?php
/**
 * Geo AI intelligence block for Geo Core AI site intelligence snapshot.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Appends compact page intelligence summaries via {@see rwgc_ai_snapshot_payload}.
 */
class RWGA_AI_Snapshot {

	const MAX_PAGE_ROWS = 24;

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_ai_snapshot_payload', array( __CLASS__, 'append_intelligence_block' ), 25, 2 );
	}

	/**
	 * @param array<string, mixed> $payload Snapshot payload.
	 * @param array<string, mixed> $context Builder context.
	 * @return array<string, mixed>
	 */
	public static function append_intelligence_block( array $payload, array $context = array() ) {
		unset( $context );

		if ( ! class_exists( 'RWGA_Local_Intelligence', false ) ) {
			return $payload;
		}

		$block = array(
			'active'             => true,
			'version'            => defined( 'RWGA_VERSION' ) ? (string) RWGA_VERSION : '',
			'intelligence_version' => class_exists( 'RWGA_Local_Intelligence', false ) ? RWGA_Local_Intelligence::VERSION : '',
			'counts'             => self::collect_counts(),
			'pages'              => self::collect_page_summaries(),
			'graph'              => self::compact_graph(),
		);

		/**
		 * Filter Geo AI intelligence block appended to the site intelligence snapshot.
		 *
		 * @param array<string, mixed> $block   Intelligence metadata block.
		 * @param array<string, mixed> $payload Full snapshot before normalization.
		 */
		$block = apply_filters( 'rwga_ai_snapshot_block', $block, $payload );

		$payload['geo_ai_intelligence'] = is_array( $block ) ? $block : array();

		return $payload;
	}

	/**
	 * @return array<string, int>
	 */
	private static function collect_counts() {
		$counts = array(
			'pages_indexed' => 0,
			'insights'      => 0,
			'ai_runs'       => 0,
		);

		if ( class_exists( 'RWGA_DB_Page_Intelligence', false ) ) {
			$rows = RWGA_DB_Page_Intelligence::list_recent( self::MAX_PAGE_ROWS );
			$counts['pages_indexed'] = count( $rows );
		}

		if ( class_exists( 'RWGA_DB_UX_Insights', false ) ) {
			$insights = RWGA_DB_UX_Insights::list_active( 200 );
			$counts['insights'] = is_array( $insights ) ? count( $insights ) : 0;
		}

		if ( class_exists( 'RWGA_DB_AI_Runs', false ) ) {
			$runs = RWGA_DB_AI_Runs::list_recent( 1 );
			$counts['ai_runs'] = is_array( $runs ) ? count( $runs ) : 0;
		}

		return $counts;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_page_summaries() {
		if ( ! class_exists( 'RWGA_DB_Page_Intelligence', false ) ) {
			return array();
		}

		$rows = RWGA_DB_Page_Intelligence::list_recent( self::MAX_PAGE_ROWS );
		$out  = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$page_id = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
			if ( $page_id <= 0 ) {
				continue;
			}

			$context = array();
			if ( ! empty( $row['context_json'] ) ) {
				$decoded = json_decode( (string) $row['context_json'], true );
				if ( is_array( $decoded ) ) {
					$context = $decoded;
				}
			}

			$ux_scores = array();
			if ( isset( $context['ux_intelligence']['scores'] ) && is_array( $context['ux_intelligence']['scores'] ) ) {
				$ux_scores = $context['ux_intelligence']['scores'];
			}

			$out[] = array(
				'page_id'            => $page_id,
				'page_type'          => isset( $context['page_type'] ) ? sanitize_key( (string) $context['page_type'] ) : 'page',
				'entity_hash'        => isset( $row['entity_hash'] ) ? sanitize_text_field( (string) $row['entity_hash'] ) : '',
				'messaging_summary'  => isset( $row['messaging_summary'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $row['messaging_summary'] ) ) : '',
				'ux_summary'         => isset( $row['ux_summary'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $row['ux_summary'] ) ) : '',
				'conversion_summary' => isset( $row['conversion_summary'] ) ? sanitize_text_field( wp_strip_all_tags( (string) $row['conversion_summary'] ) ) : '',
				'ux_scores'          => $ux_scores,
				'messaging'          => isset( $context['messaging'] ) && is_array( $context['messaging'] )
					? self::compact_messaging( $context['messaging'] )
					: array(),
				'visual'             => isset( $context['visual_intelligence'] ) && is_array( $context['visual_intelligence'] )
					? self::compact_visual( $context['visual_intelligence'] )
					: array(),
			);

			if ( count( $out ) >= self::MAX_PAGE_ROWS ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function compact_graph() {
		if ( ! class_exists( 'RWGA_Relationship_Graph', false ) ) {
			return array();
		}
		$graph = RWGA_Relationship_Graph::get_graph();
		if ( ! is_array( $graph ) ) {
			return array();
		}
		return array(
			'schema_version' => isset( $graph['schema_version'] ) ? (int) $graph['schema_version'] : 1,
			'counts'         => isset( $graph['counts'] ) && is_array( $graph['counts'] ) ? $graph['counts'] : array(),
		);
	}

	/**
	 * @param array<string, mixed> $messaging Messaging analyzer output.
	 * @return array<string, mixed>
	 */
	private static function compact_messaging( array $messaging ) {
		return array(
			'uvp'           => isset( $messaging['uvp'] ) ? sanitize_text_field( (string) $messaging['uvp'] ) : '',
			'cta_strength'  => isset( $messaging['cta_strength'] ) ? (int) $messaging['cta_strength'] : 0,
			'objections'    => isset( $messaging['objections'] ) && is_array( $messaging['objections'] )
				? array_slice( array_map( 'sanitize_key', $messaging['objections'] ), 0, 6 )
				: array(),
		);
	}

	/**
	 * @param array<string, mixed> $visual Visual analyzer output.
	 * @return array<string, mixed>
	 */
	private static function compact_visual( array $visual ) {
		return array(
			'cta_emphasis_score' => isset( $visual['cta_emphasis_score'] ) ? (int) $visual['cta_emphasis_score'] : 0,
			'focus_conflicts'    => isset( $visual['focus_conflicts'] ) && is_array( $visual['focus_conflicts'] )
				? count( $visual['focus_conflicts'] )
				: 0,
		);
	}
}
