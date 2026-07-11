<?php
/**
 * CRUD for local intelligence layer tables (v3 Phase 2).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide intelligence context (single row per site UUID).
 */
class RWGA_DB_Site_Context {

	/**
	 * @param string $site_uuid Site UUID.
	 * @return array<string, mixed>|null
	 */
	public static function get_by_uuid( $site_uuid ) {
		global $wpdb;
		$table = RWGA_DB::site_context_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from trusted prefix.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE site_uuid = %s LIMIT 1", sanitize_text_field( (string) $site_uuid ) ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string, mixed> $row Row fields.
	 * @return int Row id or 0.
	 */
	public static function upsert( array $row ) {
		global $wpdb;
		$table = RWGA_DB::site_context_table();
		$now   = current_time( 'mysql', true );
		$uuid  = isset( $row['site_uuid'] ) ? sanitize_text_field( (string) $row['site_uuid'] ) : '';
		if ( '' === $uuid ) {
			return 0;
		}

		$existing = self::get_by_uuid( $uuid );
		$satellites = isset( $row['installed_satellites'] ) ? $row['installed_satellites'] : array();
		if ( is_array( $satellites ) ) {
			$satellites = wp_json_encode( $satellites );
		}
		$context_json = isset( $row['context_json'] ) ? $row['context_json'] : array();
		if ( is_array( $context_json ) ) {
			$context_json = wp_json_encode( $context_json );
		}

		$data = array(
			'site_type'              => isset( $row['site_type'] ) ? sanitize_key( (string) $row['site_type'] ) : '',
			'industry'               => isset( $row['industry'] ) ? sanitize_text_field( (string) $row['industry'] ) : '',
			'primary_goal'           => isset( $row['primary_goal'] ) ? sanitize_key( (string) $row['primary_goal'] ) : '',
			'conversion_model'       => isset( $row['conversion_model'] ) ? sanitize_key( (string) $row['conversion_model'] ) : '',
			'localisation_maturity'  => isset( $row['localisation_maturity'] ) ? sanitize_key( (string) $row['localisation_maturity'] ) : 'none',
			'optimisation_maturity'  => isset( $row['optimisation_maturity'] ) ? sanitize_key( (string) $row['optimisation_maturity'] ) : 'none',
			'installed_satellites'   => is_string( $satellites ) ? $satellites : '[]',
			'intelligence_version'   => isset( $row['intelligence_version'] ) ? sanitize_text_field( (string) $row['intelligence_version'] ) : '',
			'context_json'           => is_string( $context_json ) ? $context_json : '{}',
			'snapshot_hash'          => isset( $row['snapshot_hash'] ) ? sanitize_text_field( (string) $row['snapshot_hash'] ) : null,
			'refreshed_at'           => isset( $row['refreshed_at'] ) ? (string) $row['refreshed_at'] : $now,
			'updated_at'             => $now,
		);

		if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
			return (int) $existing['id'];
		}

		$data['site_uuid']  = $uuid;
		$data['created_at'] = $now;
		$ok = $wpdb->insert( $table, $data );
		return $ok ? (int) $wpdb->insert_id : 0;
	}
}

/**
 * Per-page intelligence summaries.
 */
class RWGA_DB_Page_Intelligence {

	/**
	 * @param int $page_id Post ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_by_page_id( $page_id ) {
		global $wpdb;
		$page_id = (int) $page_id;
		if ( $page_id <= 0 ) {
			return null;
		}
		$table = RWGA_DB::page_context_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from trusted prefix.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE page_id = %d LIMIT 1", $page_id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string, mixed> $row Row fields.
	 * @return int Row id or 0.
	 */
	public static function upsert( array $row ) {
		global $wpdb;
		$table   = RWGA_DB::page_context_table();
		$now     = current_time( 'mysql', true );
		$page_id = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
		if ( $page_id <= 0 ) {
			return 0;
		}

		$context_json = isset( $row['context_json'] ) ? $row['context_json'] : array();
		if ( is_array( $context_json ) ) {
			$context_json = wp_json_encode( $context_json );
		}

		$data = array(
			'page_type'            => isset( $row['page_type'] ) ? sanitize_key( (string) $row['page_type'] ) : '',
			'funnel_stage'         => isset( $row['funnel_stage'] ) ? sanitize_key( (string) $row['funnel_stage'] ) : '',
			'builder_type'         => isset( $row['builder_type'] ) ? sanitize_key( (string) $row['builder_type'] ) : '',
			'messaging_summary'    => isset( $row['messaging_summary'] ) ? wp_kses_post( (string) $row['messaging_summary'] ) : '',
			'ux_summary'           => isset( $row['ux_summary'] ) ? wp_kses_post( (string) $row['ux_summary'] ) : '',
			'conversion_summary'   => isset( $row['conversion_summary'] ) ? wp_kses_post( (string) $row['conversion_summary'] ) : '',
			'localisation_summary' => isset( $row['localisation_summary'] ) ? wp_kses_post( (string) $row['localisation_summary'] ) : '',
			'context_json'         => is_string( $context_json ) ? $context_json : '{}',
			'entity_hash'          => isset( $row['entity_hash'] ) ? sanitize_text_field( (string) $row['entity_hash'] ) : null,
			'intelligence_version' => isset( $row['intelligence_version'] ) ? sanitize_text_field( (string) $row['intelligence_version'] ) : '',
			'refreshed_at'         => isset( $row['refreshed_at'] ) ? (string) $row['refreshed_at'] : $now,
			'updated_at'           => $now,
		);

		$existing = self::get_by_page_id( $page_id );
		if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
			return (int) $existing['id'];
		}

		$data['page_id']    = $page_id;
		$data['created_at'] = $now;
		$ok = $wpdb->insert( $table, $data );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_recent( $limit = 100 ) {
		global $wpdb;
		$table = RWGA_DB::page_context_table();
		$limit = max( 1, min( 500, (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from trusted prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY refreshed_at DESC, id DESC LIMIT %d", $limit ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}

/**
 * Geo entity intelligence (rules, variants, popups, experiments).
 */
class RWGA_DB_Entity_Context {

	/**
	 * @param string $entity_type Entity type slug.
	 * @param string $entity_id   Entity id.
	 * @return array<string, mixed>|null
	 */
	public static function get( $entity_type, $entity_id ) {
		global $wpdb;
		$entity_type = sanitize_key( (string) $entity_type );
		$entity_id   = sanitize_text_field( (string) $entity_id );
		if ( '' === $entity_type || '' === $entity_id ) {
			return null;
		}
		$table = RWGA_DB::entity_context_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from trusted prefix.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE entity_type = %s AND entity_id = %s LIMIT 1", $entity_type, $entity_id ),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param array<string, mixed> $row Row fields.
	 * @return int Row id or 0.
	 */
	public static function upsert( array $row ) {
		global $wpdb;
		$table = RWGA_DB::entity_context_table();
		$now   = current_time( 'mysql', true );

		$entity_type = isset( $row['entity_type'] ) ? sanitize_key( (string) $row['entity_type'] ) : '';
		$entity_id   = isset( $row['entity_id'] ) ? sanitize_text_field( (string) $row['entity_id'] ) : '';
		if ( '' === $entity_type || '' === $entity_id ) {
			return 0;
		}

		$context_json = isset( $row['context_json'] ) ? $row['context_json'] : array();
		if ( is_array( $context_json ) ) {
			$context_json = wp_json_encode( $context_json );
		}

		$data = array(
			'label'          => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
			'context_json'   => is_string( $context_json ) ? $context_json : '{}',
			'snapshot_hash'  => isset( $row['snapshot_hash'] ) ? sanitize_text_field( (string) $row['snapshot_hash'] ) : null,
			'entity_hash'    => isset( $row['entity_hash'] ) ? sanitize_text_field( (string) $row['entity_hash'] ) : null,
			'refreshed_at'   => isset( $row['refreshed_at'] ) ? (string) $row['refreshed_at'] : $now,
			'updated_at'     => $now,
		);

		$existing = self::get( $entity_type, $entity_id );
		if ( is_array( $existing ) && ! empty( $existing['id'] ) ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
			return (int) $existing['id'];
		}

		$data['entity_type'] = $entity_type;
		$data['entity_id']   = $entity_id;
		$data['created_at']  = $now;
		$ok = $wpdb->insert( $table, $data );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Remove entity rows not in the keep list (after snapshot refresh).
	 *
	 * @param array<int, string> $keep_keys Keys as "type:id".
	 * @return int Rows deleted.
	 */
	public static function prune_except( array $keep_keys ) {
		global $wpdb;
		$table = RWGA_DB::entity_context_table();
		$keep  = array();
		foreach ( $keep_keys as $key ) {
			$key = sanitize_text_field( (string) $key );
			if ( '' !== $key ) {
				$keep[ $key ] = true;
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from trusted prefix.
		$rows = $wpdb->get_results( "SELECT id, entity_type, entity_id FROM {$table}", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = sanitize_key( (string) ( $row['entity_type'] ?? '' ) ) . ':' . sanitize_text_field( (string) ( $row['entity_id'] ?? '' ) );
			if ( isset( $keep[ $key ] ) ) {
				continue;
			}
			$wpdb->delete( $table, array( 'id' => (int) $row['id'] ), array( '%d' ) );
			++$deleted;
		}
		return $deleted;
	}
}

/**
 * Reusable UX findings.
 */
class RWGA_DB_UX_Insights {

	/**
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id   Entity id.
	 * @param string $source      Source slug to replace.
	 * @param array<int, array<string, mixed>> $insights Insight rows.
	 * @return int Rows written.
	 */
	public static function replace_for_entity_source( $entity_type, $entity_id, $source, array $insights ) {
		global $wpdb;
		$table       = RWGA_DB::ux_insights_table();
		$entity_type = sanitize_key( (string) $entity_type );
		$entity_id   = max( 0, (int) $entity_id );
		$source      = sanitize_key( (string) $source );
		$now         = current_time( 'mysql', true );

		$wpdb->delete(
			$table,
			array(
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'source'      => $source,
			),
			array( '%s', '%d', '%s' )
		);

		$count = 0;
		foreach ( $insights as $insight ) {
			if ( ! is_array( $insight ) ) {
				continue;
			}
			$scores   = isset( $insight['scores_json'] ) ? $insight['scores_json'] : ( isset( $insight['scores'] ) ? $insight['scores'] : array() );
			$evidence = isset( $insight['evidence_json'] ) ? $insight['evidence_json'] : ( isset( $insight['evidence'] ) ? $insight['evidence'] : array() );
			if ( is_array( $scores ) ) {
				$scores = wp_json_encode( $scores );
			}
			if ( is_array( $evidence ) ) {
				$evidence = wp_json_encode( $evidence );
			}

			$ok = $wpdb->insert(
				$table,
				array(
					'entity_type'      => $entity_type,
					'entity_id'        => $entity_id,
					'insight_key'      => isset( $insight['insight_key'] ) ? sanitize_key( (string) $insight['insight_key'] ) : '',
					'finding'          => isset( $insight['finding'] ) ? wp_kses_post( (string) $insight['finding'] ) : '',
					'severity'         => isset( $insight['severity'] ) ? sanitize_key( (string) $insight['severity'] ) : 'medium',
					'category'         => isset( $insight['category'] ) ? sanitize_key( (string) $insight['category'] ) : '',
					'insight_type'     => isset( $insight['insight_type'] ) ? sanitize_key( (string) $insight['insight_type'] ) : '',
					'scores_json'      => is_string( $scores ) ? $scores : '{}',
					'evidence_json'    => is_string( $evidence ) ? $evidence : '{}',
					'source'           => $source,
					'source_version'   => isset( $insight['source_version'] ) ? sanitize_text_field( (string) $insight['source_version'] ) : '',
					'snapshot_hash'    => isset( $insight['snapshot_hash'] ) ? sanitize_text_field( (string) $insight['snapshot_hash'] ) : null,
					'entity_hash'      => isset( $insight['entity_hash'] ) ? sanitize_text_field( (string) $insight['entity_hash'] ) : null,
					'status'           => 'active',
					'created_at'       => $now,
					'updated_at'       => $now,
				)
			);
			if ( $ok ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * @param string $entity_type Entity type.
	 * @param int    $entity_id   Entity id.
	 * @param int    $limit       Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_for_entity( $entity_type, $entity_id, $limit = 50 ) {
		global $wpdb;
		$table       = RWGA_DB::ux_insights_table();
		$entity_type = sanitize_key( (string) $entity_type );
		$entity_id   = max( 0, (int) $entity_id );
		$limit       = max( 1, min( 100, (int) $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from trusted prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE entity_type = %s AND entity_id = %d AND status = 'active' ORDER BY id DESC LIMIT %d",
				$entity_type,
				$entity_id,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_active( $limit = 200 ) {
		global $wpdb;
		$table = RWGA_DB::ux_insights_table();
		$limit = max( 1, min( 500, (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from trusted prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'active' ORDER BY id DESC LIMIT %d", $limit ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}

/**
 * AI workflow run telemetry for insight memory (Phase 14 foundation).
 */
class RWGA_DB_AI_Runs {

	/**
	 * @param array<string, mixed> $row Row fields.
	 * @return int Insert id or 0.
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$table = RWGA_DB::ai_runs_table();
		$now   = current_time( 'mysql', true );

		$data = array(
			'workflow_key'      => isset( $row['workflow_key'] ) ? sanitize_key( (string) $row['workflow_key'] ) : '',
			'model'             => isset( $row['model'] ) ? sanitize_text_field( (string) $row['model'] ) : '',
			'provider'          => isset( $row['provider'] ) ? sanitize_key( (string) $row['provider'] ) : '',
			'prompt_version'    => isset( $row['prompt_version'] ) ? sanitize_text_field( (string) $row['prompt_version'] ) : '',
			'duration_ms'       => isset( $row['duration_ms'] ) ? max( 0, (int) $row['duration_ms'] ) : null,
			'prompt_tokens'     => isset( $row['prompt_tokens'] ) ? max( 0, (int) $row['prompt_tokens'] ) : null,
			'completion_tokens' => isset( $row['completion_tokens'] ) ? max( 0, (int) $row['completion_tokens'] ) : null,
			'total_tokens'      => isset( $row['total_tokens'] ) ? max( 0, (int) $row['total_tokens'] ) : null,
			'result_hash'       => isset( $row['result_hash'] ) ? sanitize_text_field( (string) $row['result_hash'] ) : null,
			'cache_hit'         => ! empty( $row['cache_hit'] ) ? 1 : 0,
			'entity_type'       => isset( $row['entity_type'] ) && '' !== (string) $row['entity_type'] ? sanitize_key( (string) $row['entity_type'] ) : null,
			'entity_id'           => isset( $row['entity_id'] ) && (int) $row['entity_id'] > 0 ? (int) $row['entity_id'] : null,
			'page_id'             => isset( $row['page_id'] ) && (int) $row['page_id'] > 0 ? (int) $row['page_id'] : null,
			'snapshot_hash'       => isset( $row['snapshot_hash'] ) && '' !== (string) $row['snapshot_hash'] ? sanitize_text_field( (string) $row['snapshot_hash'] ) : null,
			'remote_run_id'       => isset( $row['remote_run_id'] ) && '' !== (string) $row['remote_run_id'] ? sanitize_text_field( (string) $row['remote_run_id'] ) : null,
			'analysis_run_id'     => isset( $row['analysis_run_id'] ) && (int) $row['analysis_run_id'] > 0 ? (int) $row['analysis_run_id'] : null,
			'status'              => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'complete',
			'result_summary'      => isset( $row['result_summary'] ) ? sanitize_textarea_field( (string) $row['result_summary'] ) : null,
			'created_at'          => $now,
		);

		$ok = $wpdb->insert( $table, $data );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @param string $result_hash Result fingerprint.
	 * @param string $workflow_key Workflow key.
	 * @return array<string, mixed>|null
	 */
	public static function find_by_result_hash( $result_hash, $workflow_key ) {
		global $wpdb;
		$result_hash  = sanitize_text_field( (string) $result_hash );
		$workflow_key = sanitize_key( (string) $workflow_key );
		if ( '' === $result_hash || '' === $workflow_key ) {
			return null;
		}
		$table = RWGA_DB::ai_runs_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from trusted prefix.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE result_hash = %s AND workflow_key = %s ORDER BY id DESC LIMIT 1",
				$result_hash,
				$workflow_key
			),
			ARRAY_A
		);
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_recent( $limit = 20 ) {
		global $wpdb;
		$table = RWGA_DB::ai_runs_table();
		$limit = max( 1, min( 100, (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from trusted prefix.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}
