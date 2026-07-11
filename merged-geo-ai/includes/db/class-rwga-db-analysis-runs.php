<?php
/**
 * Persistence for analysis runs.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for rwga_analysis_runs.
 */
class RWGA_DB_Analysis_Runs {

	/**
	 * Insert a run row.
	 *
	 * @param array<string, mixed> $row Row fields.
	 * @return int Insert ID or 0.
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$table = RWGA_DB::analysis_runs_table();
		$now   = current_time( 'mysql', true );

		$page_id = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
		$uid     = isset( $row['created_by'] ) ? (int) $row['created_by'] : 0;

		$data = array(
			'site_id'               => isset( $row['site_id'] ) ? (string) $row['site_id'] : '',
			'workflow_key'          => isset( $row['workflow_key'] ) ? sanitize_key( (string) $row['workflow_key'] ) : '',
			'agent_key'             => isset( $row['agent_key'] ) ? sanitize_key( (string) $row['agent_key'] ) : '',
			'page_type'             => isset( $row['page_type'] ) ? sanitize_text_field( (string) $row['page_type'] ) : '',
			'asset_type'            => isset( $row['asset_type'] ) ? sanitize_key( (string) $row['asset_type'] ) : 'page',
			'analysis_focus'        => isset( $row['analysis_focus'] ) ? sanitize_key( (string) $row['analysis_focus'] ) : 'messaging',
			'status'                => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'complete',
			'lifecycle_status'      => isset( $row['lifecycle_status'] ) ? sanitize_key( (string) $row['lifecycle_status'] ) : 'analysed',
			'result_schema_version' => isset( $row['result_schema_version'] ) ? sanitize_text_field( (string) $row['result_schema_version'] ) : '1.0.0',
			'created_at'            => isset( $row['created_at'] ) ? (string) $row['created_at'] : $now,
			'updated_at'            => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( $page_id > 0 ) {
			$data['page_id'] = $page_id;
			$formats[]       = '%d';
		} else {
			$data['page_id'] = null;
			$formats[]       = '%s';
		}

		$data['page_url'] = isset( $row['page_url'] ) ? esc_url_raw( (string) $row['page_url'] ) : null;
		$formats[]        = '%s';

		$asset_id = isset( $row['asset_id'] ) ? (int) $row['asset_id'] : 0;
		if ( $asset_id > 0 ) {
			$data['asset_id'] = $asset_id;
			$formats[]        = '%d';
		} else {
			$data['asset_id'] = null;
			$formats[]        = '%s';
		}

		$geo = isset( $row['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $row['geo_target'] ), 0, 2 ) ) : '';
		$data['geo_target'] = '' !== $geo ? $geo : null;
		$formats[]          = '%s';

		$dev = isset( $row['device_type'] ) ? sanitize_key( (string) $row['device_type'] ) : '';
		$data['device_type'] = '' !== $dev ? $dev : null;
		$formats[]           = '%s';

		if ( isset( $row['score'] ) && null !== $row['score'] && '' !== $row['score'] ) {
			$data['score'] = (float) $row['score'];
			$formats[]     = '%f';
		} else {
			$data['score'] = null;
			$formats[]     = '%s';
		}

		if ( isset( $row['confidence'] ) && null !== $row['confidence'] && '' !== $row['confidence'] ) {
			$data['confidence'] = (float) $row['confidence'];
			$formats[]          = '%f';
		} else {
			$data['confidence'] = null;
			$formats[]          = '%s';
		}

		$data['summary'] = isset( $row['summary'] ) ? wp_kses_post( (string) $row['summary'] ) : null;
		$formats[]       = '%s';

		$data['report_html'] = isset( $row['report_html'] ) ? wp_kses_post( (string) $row['report_html'] ) : null;
		$formats[]           = '%s';

		$data['input_hash'] = isset( $row['input_hash'] ) ? sanitize_text_field( (string) $row['input_hash'] ) : null;
		$formats[]          = '%s';

		$data['remote_run_id'] = isset( $row['remote_run_id'] ) ? sanitize_text_field( (string) $row['remote_run_id'] ) : null;
		$formats[]             = '%s';

		if ( $uid > 0 ) {
			$data['created_by'] = $uid;
			$formats[]          = '%d';
		} else {
			$data['created_by'] = null;
			$formats[]          = '%s';
		}

		$ok = $wpdb->insert( $table, $data, $formats );
		if ( ! $ok ) {
			return 0;
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $id Run ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$table = RWGA_DB::analysis_runs_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Recent runs for admin preview.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_recent( $limit = 10 ) {
		global $wpdb;
		$limit = max( 1, min( 50, (int) $limit ) );
		$table = RWGA_DB::analysis_runs_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Total rows for pagination.
	 *
	 * @return int
	 */
	public static function count_rows( array $filters = array() ) {
		global $wpdb;
		$table = RWGA_DB::analysis_runs_table();
		$where = array();
		$args  = array();
		if ( ! empty( $filters['asset_type'] ) ) {
			$where[] = 'asset_type = %s';
			$args[]  = sanitize_key( (string) $filters['asset_type'] );
		}
		if ( ! empty( $filters['lifecycle_status'] ) ) {
			$where[] = 'lifecycle_status = %s';
			$args[]  = sanitize_key( (string) $filters['lifecycle_status'] );
		}
		if ( ! empty( $filters['from_date'] ) ) {
			$where[] = 'DATE(created_at) >= %s';
			$args[]  = sanitize_text_field( (string) $filters['from_date'] );
		}
		if ( ! empty( $filters['to_date'] ) ) {
			$where[] = 'DATE(created_at) <= %s';
			$args[]  = sanitize_text_field( (string) $filters['to_date'] );
		}
		$sql = "SELECT COUNT(*) FROM {$table}";
		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$c = empty( $args ) ? $wpdb->get_var( $sql ) : $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
		return (int) $c;
	}

	/**
	 * Paginated list (newest first).
	 *
	 * @param int $per_page Items per page.
	 * @param int $paged    Page number (1-based).
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_paged( $per_page = 20, $paged = 1, array $filters = array() ) {
		global $wpdb;
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$paged    = max( 1, (int) $paged );
		$offset   = ( $paged - 1 ) * $per_page;
		$table    = RWGA_DB::analysis_runs_table();
		$where = array();
		$args  = array();
		if ( ! empty( $filters['asset_type'] ) ) {
			$where[] = 'asset_type = %s';
			$args[]  = sanitize_key( (string) $filters['asset_type'] );
		}
		if ( ! empty( $filters['lifecycle_status'] ) ) {
			$where[] = 'lifecycle_status = %s';
			$args[]  = sanitize_key( (string) $filters['lifecycle_status'] );
		}
		if ( ! empty( $filters['from_date'] ) ) {
			$where[] = 'DATE(created_at) >= %s';
			$args[]  = sanitize_text_field( (string) $filters['from_date'] );
		}
		if ( ! empty( $filters['to_date'] ) ) {
			$where[] = 'DATE(created_at) <= %s';
			$args[]  = sanitize_text_field( (string) $filters['to_date'] );
		}
		$sql = "SELECT * FROM {$table}";
		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql   .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
		$args[] = $per_page;
		$args[] = $offset;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Update lifecycle status.
	 *
	 * @param int    $id Run id.
	 * @param string $status Status value.
	 * @return bool
	 */
	public static function set_lifecycle_status( $id, $status ) {
		global $wpdb;
		$id     = (int) $id;
		$status = sanitize_key( (string) $status );
		if ( $id <= 0 || '' === $status ) {
			return false;
		}
		$table = RWGA_DB::analysis_runs_table();
		$ok    = $wpdb->update( $table, array( 'lifecycle_status' => $status, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ), array( '%s', '%s' ), array( '%d' ) );
		return false !== $ok;
	}

	/**
	 * Delete one analysis run.
	 *
	 * @param int $id Run ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$table = RWGA_DB::analysis_runs_table();
		return false !== $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}
}
