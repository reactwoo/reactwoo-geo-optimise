<?php
/**
 * Persistence for approval-gated intelligence workflow actions.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for rwga_intelligence_actions.
 */
class RWGA_DB_Intelligence_Actions {

	/**
	 * @param array<string, mixed> $row Fields.
	 * @return int Insert id or 0.
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$table = RWGA_DB::intelligence_actions_table();
		$now   = current_time( 'mysql', true );

		$rid = isset( $row['recommendation_id'] ) ? (int) $row['recommendation_id'] : 0;
		$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
		$uid = isset( $row['created_by'] ) ? (int) $row['created_by'] : 0;

		$action_json = isset( $row['action_json'] ) ? $row['action_json'] : array();
		if ( is_array( $action_json ) ) {
			$action_json = wp_json_encode( $action_json );
		}
		if ( ! is_string( $action_json ) || '' === $action_json ) {
			$action_json = '{}';
		}

		$data = array(
			'workflow_key'      => isset( $row['workflow_key'] ) ? sanitize_key( (string) $row['workflow_key'] ) : '',
			'recommendation_id' => $rid > 0 ? $rid : null,
			'action_type'       => isset( $row['action_type'] ) ? sanitize_key( (string) $row['action_type'] ) : '',
			'label'             => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
			'action_json'       => $action_json,
			'entity_type'       => isset( $row['entity_type'] ) && '' !== (string) $row['entity_type'] ? sanitize_key( (string) $row['entity_type'] ) : null,
			'entity_id'         => isset( $row['entity_id'] ) && '' !== (string) $row['entity_id'] ? sanitize_text_field( (string) $row['entity_id'] ) : null,
			'page_id'           => $pid > 0 ? $pid : null,
			'snapshot_hash'     => isset( $row['snapshot_hash'] ) && '' !== (string) $row['snapshot_hash'] ? sanitize_text_field( (string) $row['snapshot_hash'] ) : null,
			'status'            => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'pending',
			'requires_approval' => 1,
			'approved_by'       => null,
			'approved_at'       => null,
			'applied_at'        => null,
			'apply_result_json' => null,
			'created_by'        => $uid > 0 ? $uid : null,
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		$formats = array(
			'%s',
			null === $data['recommendation_id'] ? '%s' : '%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			null === $data['page_id'] ? '%s' : '%d',
			'%s',
			'%s',
			'%d',
			'%s',
			'%s',
			'%s',
			'%s',
			null === $data['created_by'] ? '%s' : '%d',
			'%s',
			'%s',
		);

		$ok = $wpdb->insert( $table, $data, $formats );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @param int $id Action ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$table = RWGA_DB::intelligence_actions_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param int    $per_page Items per page.
	 * @param int    $paged Page number.
	 * @param string $workflow_key Optional filter.
	 * @param array<string, mixed> $filters Optional filters (status).
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_paged( $per_page = 20, $paged = 1, $workflow_key = '', array $filters = array() ) {
		global $wpdb;
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$paged    = max( 1, (int) $paged );
		$offset   = ( $paged - 1 ) * $per_page;
		$table    = RWGA_DB::intelligence_actions_table();
		$wk       = sanitize_key( (string) $workflow_key );

		$where = array();
		$args  = array();
		if ( '' !== $wk ) {
			$where[] = 'workflow_key = %s';
			$args[]  = $wk;
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = sanitize_key( (string) $filters['status'] );
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
	 * @param string $workflow_key Optional filter.
	 * @param array<string, mixed> $filters Optional filters.
	 * @return int
	 */
	public static function count_rows( $workflow_key = '', array $filters = array() ) {
		global $wpdb;
		$table = RWGA_DB::intelligence_actions_table();
		$wk    = sanitize_key( (string) $workflow_key );

		$where = array();
		$args  = array();
		if ( '' !== $wk ) {
			$where[] = 'workflow_key = %s';
			$args[]  = $wk;
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = sanitize_key( (string) $filters['status'] );
		}
		$sql = "SELECT COUNT(*) FROM {$table}";
		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		return (int) ( empty( $args ) ? $wpdb->get_var( $sql ) : $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) );
	}

	/**
	 * @param int                  $id Action ID.
	 * @param string               $status New status.
	 * @param array<string, mixed> $extra Optional fields (approved_by, apply_result_json).
	 * @return bool
	 */
	public static function update_status( $id, $status, array $extra = array() ) {
		global $wpdb;
		$id     = (int) $id;
		$status = sanitize_key( (string) $status );
		if ( $id <= 0 || '' === $status ) {
			return false;
		}

		$now  = current_time( 'mysql', true );
		$data = array(
			'status'     => $status,
			'updated_at' => $now,
		);
		$formats = array( '%s', '%s' );

		if ( isset( $extra['approved_by'] ) ) {
			$uid = (int) $extra['approved_by'];
			$data['approved_by'] = $uid > 0 ? $uid : null;
			$data['approved_at'] = $now;
			$formats[] = null === $data['approved_by'] ? '%s' : '%d';
			$formats[] = '%s';
		}
		if ( isset( $extra['apply_result_json'] ) ) {
			$result = $extra['apply_result_json'];
			if ( is_array( $result ) ) {
				$result = wp_json_encode( $result );
			}
			$data['apply_result_json'] = is_string( $result ) ? $result : null;
			$data['applied_at']        = $now;
			$formats[] = '%s';
			$formats[] = '%s';
		}

		$table = RWGA_DB::intelligence_actions_table();
		$ok    = $wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
		return false !== $ok;
	}
}
