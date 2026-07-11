<?php
/**
 * Local intelligence layer — convert site content into reusable intelligence.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orchestrates site, page, and entity intelligence persistence.
 */
class RWGA_Local_Intelligence {

	const VERSION = '1.6.0';

	const PROMPT_VERSION = '1.0.0';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwga_loaded', array( __CLASS__, 'on_loaded' ), 50 );
		add_action( 'rwga_workflow_persisted', array( __CLASS__, 'on_workflow_persisted' ), 10, 4 );
	}

	/**
	 * @return void
	 */
	public static function on_loaded() {
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 30, 2 );
		add_action( 'rwga_site_intelligence_synced', array( __CLASS__, 'on_site_synced' ), 10, 2 );
	}

	/**
	 * Refresh page intelligence when a public page is saved.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object.
	 * @return void
	 */
	public static function on_save_post( $post_id, $post = null ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( (int) $post_id );
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! in_array( $post->post_type, array( 'page', 'post', 'product' ), true ) ) {
			return;
		}
		if ( 'publish' !== $post->post_status && 'private' !== $post->post_status ) {
			return;
		}
		self::refresh_page_context( (int) $post_id, false );
	}

	/**
	 * @param array<string, mixed> $upload   Upload response.
	 * @param array<string, mixed> $snapshot Snapshot payload.
	 * @return void
	 */
	public static function on_site_synced( $upload, $snapshot ) {
		unset( $upload );
		if ( ! is_array( $snapshot ) ) {
			$snapshot = function_exists( 'rwgc_build_ai_snapshot' ) ? rwgc_build_ai_snapshot() : array();
		}
		self::refresh_site_context( $snapshot );
		self::refresh_entity_context_from_snapshot( $snapshot );
	}

	/**
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $input        Workflow input.
	 * @param array<string, mixed> $result       Normalised result.
	 * @param array<string, mixed> $meta         Extra metadata (remote_run_id, usage, etc.).
	 * @return void
	 */
	public static function on_workflow_persisted( $workflow_key, array $input, array $result, array $meta = array() ) {
		$workflow_key = sanitize_key( (string) $workflow_key );
		$page_id      = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;

		if ( ! empty( $result['findings'] ) && is_array( $result['findings'] ) ) {
			self::ingest_workflow_findings( $page_id, $workflow_key, $result, $meta );
		}

		self::record_ai_run(
			array(
				'workflow_key'      => $workflow_key,
				'page_id'           => $page_id > 0 ? $page_id : null,
				'entity_type'       => $page_id > 0 ? 'page' : null,
				'entity_id'         => $page_id > 0 ? $page_id : null,
				'remote_run_id'     => isset( $meta['remote_run_id'] ) ? (string) $meta['remote_run_id'] : null,
				'analysis_run_id'   => isset( $meta['analysis_run_id'] ) ? (int) $meta['analysis_run_id'] : null,
				'snapshot_hash'     => isset( $meta['snapshot_hash'] ) ? (string) $meta['snapshot_hash'] : self::current_snapshot_hash(),
				'model'             => isset( $meta['model'] ) ? (string) $meta['model'] : '',
				'provider'          => isset( $meta['provider'] ) ? (string) $meta['provider'] : '',
				'prompt_version'    => isset( $meta['prompt_version'] ) ? (string) $meta['prompt_version'] : self::PROMPT_VERSION,
				'duration_ms'       => isset( $meta['duration_ms'] ) ? (int) $meta['duration_ms'] : null,
				'prompt_tokens'     => isset( $meta['prompt_tokens'] ) ? (int) $meta['prompt_tokens'] : null,
				'completion_tokens' => isset( $meta['completion_tokens'] ) ? (int) $meta['completion_tokens'] : null,
				'total_tokens'      => isset( $meta['total_tokens'] ) ? (int) $meta['total_tokens'] : null,
				'cache_hit'         => ! empty( $meta['cache_hit'] ),
				'result_summary'    => isset( $result['summary'] ) ? (string) $result['summary'] : '',
				'result'            => $result,
			)
		);
	}

	/**
	 * Build or update site-wide intelligence context.
	 *
	 * @param array<string, mixed>|null $snapshot Optional snapshot; built when omitted.
	 * @return int Row id or 0.
	 */
	public static function refresh_site_context( $snapshot = null ) {
		if ( ! is_array( $snapshot ) && function_exists( 'rwgc_build_ai_snapshot' ) ) {
			$snapshot = rwgc_build_ai_snapshot();
		}
		if ( ! is_array( $snapshot ) ) {
			$snapshot = array();
		}

		$uuid = function_exists( 'rwga_get_site_uuid' ) ? (string) rwga_get_site_uuid() : '';
		if ( '' === $uuid ) {
			return 0;
		}

		$variants = isset( $snapshot['variants'] ) && is_array( $snapshot['variants'] ) ? $snapshot['variants'] : array();
		$rules    = isset( $snapshot['rules'] ) && is_array( $snapshot['rules'] ) ? $snapshot['rules'] : array();
		$experiments = array();
		if ( isset( $snapshot['geo_optimise']['experiments'] ) && is_array( $snapshot['geo_optimise']['experiments'] ) ) {
			$experiments = $snapshot['geo_optimise']['experiments'];
		}

		$row = array(
			'site_uuid'             => $uuid,
			'site_type'             => self::infer_site_type( $snapshot ),
			'industry'              => '',
			'primary_goal'          => self::infer_primary_goal( $snapshot ),
			'conversion_model'      => self::infer_conversion_model( $snapshot ),
			'localisation_maturity' => self::infer_localisation_maturity( $variants, $rules ),
			'optimisation_maturity' => self::infer_optimisation_maturity( $experiments ),
			'installed_satellites'  => self::installed_satellites(),
			'intelligence_version'  => self::VERSION,
			'snapshot_hash'         => isset( $snapshot['snapshot_hash'] ) ? (string) $snapshot['snapshot_hash'] : '',
			'context_json'          => array(
				'rule_count'     => count( $rules ),
				'variant_count'  => count( $variants ),
				'popup_count'    => isset( $snapshot['popups'] ) && is_array( $snapshot['popups'] ) ? count( $snapshot['popups'] ) : 0,
				'experiment_count' => count( $experiments ),
			),
			'refreshed_at'          => current_time( 'mysql', true ),
		);

		/**
		 * Filter site intelligence context before persistence.
		 *
		 * @param array<string, mixed> $row      Row for {@see RWGA_DB_Site_Context::upsert()}.
		 * @param array<string, mixed> $snapshot Source snapshot.
		 */
		$row = apply_filters( 'rwga_local_site_context_row', $row, $snapshot );
		if ( ! is_array( $row ) ) {
			return 0;
		}

		$id = RWGA_DB_Site_Context::upsert( $row );

		if ( $id > 0 ) {
			if ( class_exists( 'RWGA_Relationship_Graph', false ) ) {
				RWGA_Relationship_Graph::refresh( $snapshot );
			}
			/**
			 * Fires after site intelligence context is refreshed locally.
			 *
			 * @param int                  $id   Row id.
			 * @param array<string, mixed> $row  Persisted row.
			 */
			do_action( 'rwga_local_site_context_refreshed', $id, $row );
		}

		return $id;
	}

	/**
	 * Build or update per-page intelligence from builder layer.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Refresh even when entity hash unchanged.
	 * @return int Row id or 0.
	 */
	public static function refresh_page_context( $post_id, $force = false ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! class_exists( 'RWGA_Page_Context_Builder', false ) ) {
			return 0;
		}

		$payload = RWGA_Page_Context_Builder::build( $post_id );
		if ( ! is_array( $payload ) || array() === $payload ) {
			return 0;
		}

		$entity_hash = self::hash_json( $payload );
		$existing    = RWGA_DB_Page_Intelligence::get_by_page_id( $post_id );
		if ( ! $force && is_array( $existing ) && isset( $existing['entity_hash'] ) && $entity_hash === (string) $existing['entity_hash'] ) {
			return (int) $existing['id'];
		}

		$messaging = class_exists( 'RWGA_Messaging_Analyzer', false )
			? RWGA_Messaging_Analyzer::analyze( $payload, $post_id )
			: array();
		$ux_intel  = class_exists( 'RWGA_UX_Insight_Builder', false )
			? RWGA_UX_Insight_Builder::analyze( $payload )
			: array();
		$visual    = class_exists( 'RWGA_Visual_Analyzer', false )
			? RWGA_Visual_Analyzer::analyze( $payload )
			: array();
		$summaries = self::summarise_page_payload( $payload );
		if ( ! empty( $messaging ) && class_exists( 'RWGA_Messaging_Analyzer', false ) ) {
			$msg_summary = RWGA_Messaging_Analyzer::format_summary( $messaging );
			if ( '' !== $msg_summary ) {
				$summaries['messaging'] = $msg_summary;
			}
		}
		if ( ! empty( $ux_intel ) && class_exists( 'RWGA_UX_Insight_Builder', false ) ) {
			$ux_summary = RWGA_UX_Insight_Builder::format_summary( $ux_intel );
			if ( '' !== $ux_summary ) {
				$summaries['ux'] = $ux_summary;
			}
			$cta = isset( $ux_intel['cta_effectiveness'] ) && is_array( $ux_intel['cta_effectiveness'] ) ? $ux_intel['cta_effectiveness'] : array();
			if ( ! empty( $cta['primary_cta'] ) ) {
				$summaries['conversion'] = sprintf(
					/* translators: 1: CTA label, 2: strength score */
					__( 'Primary CTA "%1$s" (strength %2$d/100).', 'reactwoo-geo-ai' ),
					(string) $cta['primary_cta'],
					(int) ( $cta['cta_strength'] ?? 0 )
				);
			}
		}
		if ( ! empty( $visual ) && class_exists( 'RWGA_Visual_Analyzer', false ) ) {
			$visual_summary = RWGA_Visual_Analyzer::format_summary( $visual );
			if ( '' !== $visual_summary ) {
				$summaries['ux'] = '' !== $summaries['ux']
					? $summaries['ux'] . ' | ' . $visual_summary
					: $visual_summary;
			}
		}
		$row       = array(
			'page_id'              => $post_id,
			'page_type'            => isset( $payload['page_type'] ) ? (string) $payload['page_type'] : '',
			'funnel_stage'         => self::infer_funnel_stage( $payload ),
			'builder_type'         => isset( $payload['builder'] ) ? (string) $payload['builder'] : '',
			'messaging_summary'    => $summaries['messaging'],
			'ux_summary'           => $summaries['ux'],
			'conversion_summary'   => $summaries['conversion'],
			'localisation_summary' => $summaries['localisation'],
			'context_json'         => array(
				'ux_scores'       => isset( $payload['ux_scores'] ) ? $payload['ux_scores'] : array(),
				'cta_count'       => isset( $payload['ctas'] ) && is_array( $payload['ctas'] ) ? count( $payload['ctas'] ) : 0,
				'section_types'   => self::section_types_from_payload( $payload ),
				'messaging'          => $messaging,
				'ux_intelligence'    => $ux_intel,
				'visual_intelligence'=> $visual,
				'builder_semantics'  => isset( $payload['builder_semantics'] ) && is_array( $payload['builder_semantics'] ) ? $payload['builder_semantics'] : array(),
				'builder_compact'    => class_exists( 'RWGA_Page_Context_Builder', false )
					? RWGA_Page_Context_Builder::compact_for_api( $payload )
					: array(),
			),
			'entity_hash'          => $entity_hash,
			'intelligence_version' => self::VERSION,
			'refreshed_at'         => current_time( 'mysql', true ),
		);

		/**
		 * @param array<string, mixed> $row     Page context row.
		 * @param int                  $post_id Post ID.
		 * @param array<string, mixed> $payload Builder payload.
		 */
		$row = apply_filters( 'rwga_local_page_context_row', $row, $post_id, $payload );
		if ( ! is_array( $row ) ) {
			return 0;
		}

		$id = RWGA_DB_Page_Intelligence::upsert( $row );
		if ( $id > 0 ) {
			self::ingest_structure_insights( $post_id, $payload, $entity_hash );
			if ( ! empty( $messaging ) && class_exists( 'RWGA_Messaging_Analyzer', false ) ) {
				self::ingest_messaging_insights( $post_id, $messaging, $entity_hash );
			}
			if ( ! empty( $ux_intel ) && class_exists( 'RWGA_UX_Insight_Builder', false ) ) {
				self::ingest_ux_insights( $post_id, $ux_intel, $entity_hash );
			}
			if ( ! empty( $visual ) && class_exists( 'RWGA_Visual_Analyzer', false ) ) {
				self::ingest_visual_insights( $post_id, $visual, $entity_hash );
			}
			if ( class_exists( 'RWGA_Relationship_Graph', false ) ) {
				RWGA_Relationship_Graph::refresh();
			}
			do_action( 'rwga_local_page_context_refreshed', $id, $post_id, $row );
		}
		return $id;
	}

	/**
	 * Persist messaging analyzer output as reusable UX insights.
	 *
	 * @param int                  $page_id     Post ID.
	 * @param array<string, mixed> $messaging   Analyzer output.
	 * @param string               $entity_hash Page entity hash.
	 * @return int Rows written.
	 */
	public static function ingest_messaging_insights( $page_id, array $messaging, $entity_hash = '' ) {
		$page_id = (int) $page_id;
		if ( $page_id <= 0 || ! class_exists( 'RWGA_Messaging_Analyzer', false ) ) {
			return 0;
		}
		$rows = RWGA_Messaging_Analyzer::to_insight_rows(
			$messaging,
			$entity_hash,
			self::current_snapshot_hash()
		);
		return RWGA_DB_UX_Insights::replace_for_entity_source( 'page', $page_id, 'messaging_analyzer', $rows );
	}

	/**
	 * @param int $page_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function get_page_messaging( $page_id ) {
		return self::get_page_context_block( (int) $page_id, 'messaging' );
	}

	/**
	 * @param int $page_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function get_page_ux_intelligence( $page_id ) {
		return self::get_page_context_block( (int) $page_id, 'ux_intelligence' );
	}

	/**
	 * @param int $page_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function get_page_visual_intelligence( $page_id ) {
		return self::get_page_context_block( (int) $page_id, 'visual_intelligence' );
	}

	/**
	 * @param int $page_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function get_page_builder_semantics( $page_id ) {
		return self::get_page_context_block( (int) $page_id, 'builder_semantics' );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_relationship_graph() {
		return class_exists( 'RWGA_Relationship_Graph', false ) ? RWGA_Relationship_Graph::get_graph() : array();
	}

	/**
	 * @param int    $page_id Post ID.
	 * @param string $key     Context JSON key.
	 * @return array<string, mixed>
	 */
	private static function get_page_context_block( $page_id, $key ) {
		$row = self::get_page_context( (int) $page_id );
		if ( ! is_array( $row ) || empty( $row['context_json'] ) ) {
			return array();
		}
		$ctx = is_string( $row['context_json'] ) ? json_decode( $row['context_json'], true ) : $row['context_json'];
		if ( ! is_array( $ctx ) || empty( $ctx[ $key ] ) || ! is_array( $ctx[ $key ] ) ) {
			return array();
		}
		return $ctx[ $key ];
	}

	/**
	 * @param int                  $page_id     Post ID.
	 * @param array<string, mixed> $ux_intel    UX insight output.
	 * @param string               $entity_hash Page entity hash.
	 * @return int Rows written.
	 */
	public static function ingest_ux_insights( $page_id, array $ux_intel, $entity_hash = '' ) {
		$page_id = (int) $page_id;
		if ( $page_id <= 0 || ! class_exists( 'RWGA_UX_Insight_Builder', false ) ) {
			return 0;
		}
		$rows = RWGA_UX_Insight_Builder::to_insight_rows(
			$ux_intel,
			$entity_hash,
			self::current_snapshot_hash()
		);
		return RWGA_DB_UX_Insights::replace_for_entity_source( 'page', $page_id, 'ux_insight_builder', $rows );
	}

	/**
	 * @param int                  $page_id     Post ID.
	 * @param array<string, mixed> $visual      Visual analyzer output.
	 * @param string               $entity_hash Page entity hash.
	 * @return int Rows written.
	 */
	public static function ingest_visual_insights( $page_id, array $visual, $entity_hash = '' ) {
		$page_id = (int) $page_id;
		if ( $page_id <= 0 || ! class_exists( 'RWGA_Visual_Analyzer', false ) ) {
			return 0;
		}
		$rows = RWGA_Visual_Analyzer::to_insight_rows(
			$visual,
			$entity_hash,
			self::current_snapshot_hash()
		);
		return RWGA_DB_UX_Insights::replace_for_entity_source( 'page', $page_id, 'visual_analyzer', $rows );
	}

	/**
	 * Sync entity rows from a site intelligence snapshot.
	 *
	 * @param array<string, mixed>|null $snapshot Snapshot payload.
	 * @return int Entities upserted.
	 */
	public static function refresh_entity_context_from_snapshot( $snapshot = null ) {
		if ( ! is_array( $snapshot ) && function_exists( 'rwgc_build_ai_snapshot' ) ) {
			$snapshot = rwgc_build_ai_snapshot();
		}
		if ( ! is_array( $snapshot ) ) {
			return 0;
		}

		$snapshot_hash = isset( $snapshot['snapshot_hash'] ) ? (string) $snapshot['snapshot_hash'] : '';
		$now           = current_time( 'mysql', true );
		$keep_keys     = array();
		$count         = 0;

		$maps = array(
			'rule'     => isset( $snapshot['rules'] ) && is_array( $snapshot['rules'] ) ? $snapshot['rules'] : array(),
			'variant'  => isset( $snapshot['variants'] ) && is_array( $snapshot['variants'] ) ? $snapshot['variants'] : array(),
			'popup'    => isset( $snapshot['popups'] ) && is_array( $snapshot['popups'] ) ? $snapshot['popups'] : array(),
		);

		if ( isset( $snapshot['geo_optimise']['experiments'] ) && is_array( $snapshot['geo_optimise']['experiments'] ) ) {
			$maps['experiment'] = $snapshot['geo_optimise']['experiments'];
		}
		if ( isset( $snapshot['geo_commerce']['rules'] ) && is_array( $snapshot['geo_commerce']['rules'] ) ) {
			$maps['commerce_rule'] = $snapshot['geo_commerce']['rules'];
		}

		foreach ( $maps as $entity_type => $rows ) {
			foreach ( $rows as $entity_row ) {
				if ( ! is_array( $entity_row ) ) {
					continue;
				}
				$entity_id = self::entity_id_from_row( $entity_type, $entity_row );
				if ( '' === $entity_id ) {
					continue;
				}
				$label = self::entity_label_from_row( $entity_row );
				$hash  = self::hash_json( $entity_row );
				$key   = sanitize_key( $entity_type ) . ':' . $entity_id;
				$keep_keys[] = $key;

				$written = RWGA_DB_Entity_Context::upsert(
					array(
						'entity_type'    => $entity_type,
						'entity_id'      => $entity_id,
						'label'          => $label,
						'context_json'   => $entity_row,
						'snapshot_hash'  => $snapshot_hash,
						'entity_hash'    => $hash,
						'refreshed_at'   => $now,
					)
				);
				if ( $written > 0 ) {
					++$count;
				}
			}
		}

		RWGA_DB_Entity_Context::prune_except( $keep_keys );
		return $count;
	}

	/**
	 * @param int                  $page_id Page ID.
	 * @param array<string, mixed> $payload Builder payload.
	 * @param string               $entity_hash Page entity hash.
	 * @return int Insights written.
	 */
	public static function ingest_structure_insights( $page_id, array $payload, $entity_hash = '' ) {
		$page_id = (int) $page_id;
		if ( $page_id <= 0 ) {
			return 0;
		}
		if ( '' === $entity_hash ) {
			$entity_hash = self::hash_json( $payload );
		}

		$issues = array();
		if ( isset( $payload['detected_issues'] ) && is_array( $payload['detected_issues'] ) ) {
			$issues = $payload['detected_issues'];
		} elseif ( isset( $payload['ux_scores']['detected_issues'] ) && is_array( $payload['ux_scores']['detected_issues'] ) ) {
			$issues = $payload['ux_scores']['detected_issues'];
		}

		$insights = array();
		foreach ( $issues as $issue ) {
			if ( ! is_array( $issue ) ) {
				continue;
			}
			$code = isset( $issue['code'] ) ? sanitize_key( (string) $issue['code'] ) : 'structure_issue';
			$insights[] = array(
				'insight_key'    => $code,
				'finding'        => isset( $issue['message'] ) ? (string) $issue['message'] : $code,
				'severity'       => isset( $issue['severity'] ) ? sanitize_key( (string) $issue['severity'] ) : 'medium',
				'category'       => 'structure',
				'insight_type'   => 'ux_structure',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'snapshot_hash'  => self::current_snapshot_hash(),
				'evidence'       => $issue,
			);
		}

		$ux = isset( $payload['ux_scores'] ) && is_array( $payload['ux_scores'] ) ? $payload['ux_scores'] : array();
		if ( ! empty( $ux['overall_score'] ) ) {
			$insights[] = array(
				'insight_key'    => 'overall_ux_score',
				'finding'        => sprintf(
					/* translators: %d: score 0-100 */
					__( 'Overall UX structure score: %d/100', 'reactwoo-geo-ai' ),
					(int) $ux['overall_score']
				),
				'severity'       => (int) $ux['overall_score'] < 50 ? 'high' : ( (int) $ux['overall_score'] < 70 ? 'medium' : 'low' ),
				'category'       => 'ux',
				'insight_type'   => 'ux_score',
				'source_version' => self::VERSION,
				'entity_hash'    => $entity_hash,
				'scores'         => array(
					'overall_score' => (int) $ux['overall_score'],
					'cta_score'     => isset( $ux['cta_score'] ) ? (int) $ux['cta_score'] : 0,
					'trust_score'   => isset( $ux['trust_score'] ) ? (int) $ux['trust_score'] : 0,
				),
			);
		}

		return RWGA_DB_UX_Insights::replace_for_entity_source( 'page', $page_id, 'ux_structure_scorer', $insights );
	}

	/**
	 * @param int                  $page_id      Page ID.
	 * @param string               $workflow_key Workflow key.
	 * @param array<string, mixed> $result       Workflow result.
	 * @param array<string, mixed> $meta         Metadata.
	 * @return int Insights written.
	 */
	public static function ingest_workflow_findings( $page_id, $workflow_key, array $result, array $meta = array() ) {
		$page_id     = (int) $page_id;
		$entity_type = $page_id > 0 ? 'page' : 'site';
		$entity_id   = $page_id > 0 ? $page_id : 0;

		$findings = array();
		if ( isset( $result['findings'] ) && is_array( $result['findings'] ) ) {
			$findings = $result['findings'];
		}

		$insights = array();
		foreach ( $findings as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}
			$key = isset( $finding['finding_key'] ) ? sanitize_key( (string) $finding['finding_key'] ) : 'finding';
			$insights[] = array(
				'insight_key'    => $key,
				'finding'        => isset( $finding['title'] ) ? (string) $finding['title'] : $key,
				'severity'       => isset( $finding['severity'] ) ? sanitize_key( (string) $finding['severity'] ) : 'medium',
				'category'       => isset( $finding['category'] ) ? sanitize_key( (string) $finding['category'] ) : 'general',
				'insight_type'   => 'workflow_finding',
				'source_version' => self::VERSION,
				'entity_hash'    => isset( $meta['input_hash'] ) ? (string) $meta['input_hash'] : '',
				'snapshot_hash'  => isset( $meta['snapshot_hash'] ) ? (string) $meta['snapshot_hash'] : self::current_snapshot_hash(),
				'evidence'       => array(
					'evidence'             => isset( $finding['evidence'] ) ? (string) $finding['evidence'] : '',
					'recommendation_hint'  => isset( $finding['recommendation_hint'] ) ? (string) $finding['recommendation_hint'] : '',
					'workflow_key'         => sanitize_key( (string) $workflow_key ),
				),
			);
		}

		$source = 'workflow_' . sanitize_key( (string) $workflow_key );
		return RWGA_DB_UX_Insights::replace_for_entity_source( $entity_type, $entity_id, $source, $insights );
	}

	/**
	 * @param array<string, mixed> $row Run row.
	 * @return int Insert id or 0.
	 */
	public static function record_ai_run( array $row ) {
		$result = isset( $row['result'] ) && is_array( $row['result'] ) ? $row['result'] : array();
		unset( $row['result'] );

		if ( empty( $row['result_hash'] ) ) {
			$row['result_hash'] = self::build_run_cache_key(
				isset( $row['workflow_key'] ) ? (string) $row['workflow_key'] : '',
				isset( $row['snapshot_hash'] ) ? (string) $row['snapshot_hash'] : '',
				isset( $row['entity_hash'] ) ? (string) $row['entity_hash'] : '',
				isset( $row['prompt_version'] ) ? (string) $row['prompt_version'] : self::PROMPT_VERSION,
				isset( $row['model'] ) ? (string) $row['model'] : '',
				$result
			);
		}

		$id = RWGA_DB_AI_Runs::insert( $row );
		if ( $id > 0 ) {
			do_action( 'rwga_ai_run_recorded', $id, $row );
		}
		return $id;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function get_site_context() {
		$uuid = function_exists( 'rwga_get_site_uuid' ) ? (string) rwga_get_site_uuid() : '';
		if ( '' === $uuid ) {
			return null;
		}
		return RWGA_DB_Site_Context::get_by_uuid( $uuid );
	}

	/**
	 * @param int $page_id Page ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_page_context( $page_id ) {
		return RWGA_DB_Page_Intelligence::get_by_page_id( (int) $page_id );
	}

	/**
	 * @param int $page_id Page ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_page_insights( $page_id ) {
		return RWGA_DB_UX_Insights::list_for_entity( 'page', (int) $page_id, 50 );
	}

	/**
	 * Full local refresh (site + entities + optional page).
	 *
	 * @param int $page_id Optional page to refresh.
	 * @return array<string, int>
	 */
	public static function refresh_all( $page_id = 0 ) {
		$snapshot = function_exists( 'rwgc_build_ai_snapshot' ) ? rwgc_build_ai_snapshot() : array();
		$out      = array(
			'site'     => self::refresh_site_context( $snapshot ),
			'entities' => self::refresh_entity_context_from_snapshot( $snapshot ),
			'page'     => 0,
		);
		if ( (int) $page_id > 0 ) {
			$out['page'] = self::refresh_page_context( (int) $page_id, true );
		}
		return $out;
	}

	/**
	 * Cache key foundation for insight memory (Phase 14).
	 *
	 * @param string               $workflow_key   Workflow key.
	 * @param string               $snapshot_hash  Snapshot hash.
	 * @param string               $entity_hash    Entity hash.
	 * @param string               $prompt_version Prompt version.
	 * @param string               $model_version  Model id.
	 * @param array<string, mixed> $result         Optional result body.
	 * @return string
	 */
	public static function build_run_cache_key( $workflow_key, $snapshot_hash, $entity_hash, $prompt_version, $model_version, array $result = array() ) {
		$payload = wp_json_encode(
			array(
				'workflow_key'   => sanitize_key( (string) $workflow_key ),
				'snapshot_hash'  => sanitize_text_field( (string) $snapshot_hash ),
				'entity_hash'    => sanitize_text_field( (string) $entity_hash ),
				'prompt_version' => sanitize_text_field( (string) $prompt_version ),
				'model_version'  => sanitize_text_field( (string) $model_version ),
				'result'         => $result,
			)
		);
		return substr( hash( 'sha256', is_string( $payload ) ? $payload : '' ), 0, 64 );
	}

	/**
	 * @param mixed $data JSON-serialisable data.
	 * @return string
	 */
	public static function hash_json( $data ) {
		$json = wp_json_encode( $data );
		return substr( hash( 'sha256', is_string( $json ) ? $json : '' ), 0, 64 );
	}

	/**
	 * @return string
	 */
	public static function current_snapshot_hash() {
		if ( class_exists( 'RWGA_Site_Intelligence_Sync', false ) ) {
			$status = RWGA_Site_Intelligence_Sync::get_status();
			if ( is_array( $status ) && ! empty( $status['last_snapshot_hash'] ) ) {
				return sanitize_text_field( (string) $status['last_snapshot_hash'] );
			}
		}
		if ( function_exists( 'rwgc_get_ai_snapshot_hash' ) ) {
			return sanitize_text_field( (string) rwgc_get_ai_snapshot_hash() );
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return string
	 */
	private static function infer_site_type( array $snapshot ) {
		if ( function_exists( 'rwgc_is_woocommerce_active' ) && rwgc_is_woocommerce_active() ) {
			return 'ecommerce';
		}
		$plugins = isset( $snapshot['plugins'] ) && is_array( $snapshot['plugins'] ) ? $snapshot['plugins'] : array();
		if ( isset( $plugins['elementor'] ) || isset( $plugins['geo_elementor'] ) ) {
			return 'marketing_site';
		}
		return 'content_site';
	}

	/**
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return string
	 */
	private static function infer_primary_goal( array $snapshot ) {
		if ( function_exists( 'rwgc_is_woocommerce_active' ) && rwgc_is_woocommerce_active() ) {
			return 'sales';
		}
		$forms = isset( $snapshot['forms'] ) && is_array( $snapshot['forms'] ) ? $snapshot['forms'] : array();
		if ( count( $forms ) > 0 ) {
			return 'leads';
		}
		return 'awareness';
	}

	/**
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return string
	 */
	private static function infer_conversion_model( array $snapshot ) {
		if ( function_exists( 'rwgc_is_woocommerce_active' ) && rwgc_is_woocommerce_active() ) {
			return 'transaction';
		}
		$events = isset( $snapshot['conversion_events'] ) && is_array( $snapshot['conversion_events'] ) ? $snapshot['conversion_events'] : array();
		return count( $events ) > 0 ? 'event_based' : 'content';
	}

	/**
	 * @param array<int, mixed> $variants Variants.
	 * @param array<int, mixed> $rules    Rules.
	 * @return string
	 */
	private static function infer_localisation_maturity( array $variants, array $rules ) {
		$variant_count = count( $variants );
		if ( $variant_count >= 5 ) {
			return 'advanced';
		}
		if ( $variant_count >= 2 || count( $rules ) >= 3 ) {
			return 'basic';
		}
		return 'none';
	}

	/**
	 * @param array<int, mixed> $experiments Experiments.
	 * @return string
	 */
	private static function infer_optimisation_maturity( array $experiments ) {
		$active = 0;
		foreach ( $experiments as $exp ) {
			if ( is_array( $exp ) && isset( $exp['status'] ) && 'active' === sanitize_key( (string) $exp['status'] ) ) {
				++$active;
			}
		}
		if ( $active >= 2 ) {
			return 'advanced';
		}
		if ( count( $experiments ) > 0 ) {
			return 'basic';
		}
		return 'none';
	}

	/**
	 * @return array<string, string>
	 */
	private static function installed_satellites() {
		$out = array(
			'geocore' => defined( 'RWGC_VERSION' ) ? (string) RWGC_VERSION : '',
			'geo_ai'  => defined( 'RWGA_VERSION' ) ? (string) RWGA_VERSION : '',
		);
		if ( defined( 'RWGCP_VERSION' ) ) {
			$out['geocore_pro'] = (string) RWGCP_VERSION;
		}
		if ( defined( 'RWGO_VERSION' ) ) {
			$out['geo_optimise'] = (string) RWGO_VERSION;
		}
		if ( defined( 'RWGCM_VERSION' ) ) {
			$out['geo_commerce'] = (string) RWGCM_VERSION;
		}
		return array_filter( $out );
	}

	/**
	 * @param array<string, mixed> $payload Builder payload.
	 * @return array<string, string>
	 */
	private static function summarise_page_payload( array $payload ) {
		$hero_text  = '';
		$sections   = isset( $payload['sections'] ) && is_array( $payload['sections'] ) ? $payload['sections'] : array();
		foreach ( $sections as $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$type = isset( $sec['classification']['type'] ) ? (string) $sec['classification']['type'] : '';
			if ( 'hero' === $type && ! empty( $sec['heading'] ) ) {
				$hero_text = (string) $sec['heading'];
				break;
			}
		}

		$cta_label = '';
		$ctas      = isset( $payload['ctas'] ) && is_array( $payload['ctas'] ) ? $payload['ctas'] : array();
		if ( ! empty( $ctas[0]['label'] ) ) {
			$cta_label = (string) $ctas[0]['label'];
		}

		$ux = isset( $payload['ux_scores'] ) && is_array( $payload['ux_scores'] ) ? $payload['ux_scores'] : array();
		$ux_summary = '';
		if ( ! empty( $ux['overall_score'] ) ) {
			$ux_summary = sprintf(
				/* translators: 1: overall score, 2: CTA score, 3: trust score */
				__( 'Structure scores — overall %1$d, CTA %2$d, trust %3$d.', 'reactwoo-geo-ai' ),
				(int) ( $ux['overall_score'] ?? 0 ),
				(int) ( $ux['cta_score'] ?? 0 ),
				(int) ( $ux['trust_score'] ?? 0 )
			);
		}

		$messaging = $hero_text;
		if ( '' === $messaging && ! empty( $payload['page_title'] ) ) {
			$messaging = (string) $payload['page_title'];
		}

		$conversion = $cta_label
			? sprintf(
				/* translators: 1: primary CTA label, 2: CTA count */
				__( 'Primary CTA: "%1$s" (%2$d total).', 'reactwoo-geo-ai' ),
				$cta_label,
				count( $ctas )
			)
			: __( 'No primary CTA detected.', 'reactwoo-geo-ai' );

		return array(
			'messaging'     => $messaging,
			'ux'            => $ux_summary,
			'conversion'    => $conversion,
			'localisation'  => '',
		);
	}

	/**
	 * @param array<string, mixed> $payload Builder payload.
	 * @return string
	 */
	private static function infer_funnel_stage( array $payload ) {
		$page_type = isset( $payload['page_type'] ) ? sanitize_key( (string) $payload['page_type'] ) : '';
		if ( 'landing_page' === $page_type ) {
			return 'consideration';
		}
		$types = self::section_types_from_payload( $payload );
		if ( in_array( 'pricing', $types, true ) ) {
			return 'decision';
		}
		if ( in_array( 'form', $types, true ) ) {
			return 'conversion';
		}
		return 'awareness';
	}

	/**
	 * @param array<string, mixed> $payload Builder payload.
	 * @return array<int, string>
	 */
	private static function section_types_from_payload( array $payload ) {
		$out      = array();
		$sections = isset( $payload['sections'] ) && is_array( $payload['sections'] ) ? $payload['sections'] : array();
		foreach ( $sections as $sec ) {
			if ( ! is_array( $sec ) || empty( $sec['classification']['type'] ) ) {
				continue;
			}
			$out[] = sanitize_key( (string) $sec['classification']['type'] );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param string               $entity_type Entity type.
	 * @param array<string, mixed> $row         Snapshot row.
	 * @return string
	 */
	private static function entity_id_from_row( $entity_type, array $row ) {
		$keys = array( 'id', 'rule_id', 'variant_id', 'popup_id', 'experiment_id', 'key', 'page_id' );
		foreach ( $keys as $key ) {
			if ( isset( $row[ $key ] ) && '' !== (string) $row[ $key ] ) {
				return sanitize_text_field( (string) $row[ $key ] );
			}
		}
		return '';
	}

	/**
	 * @param array<string, mixed> $row Snapshot row.
	 * @return string
	 */
	private static function entity_label_from_row( array $row ) {
		foreach ( array( 'label', 'name', 'title', 'slug' ) as $key ) {
			if ( ! empty( $row[ $key ] ) ) {
				return sanitize_text_field( (string) $row[ $key ] );
			}
		}
		return '';
	}
}
