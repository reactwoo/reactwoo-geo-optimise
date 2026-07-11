<?php
/**
 * Persistence for automation rules.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for rwga_automation_rules.
 */
class RWGA_DB_Automation_Rules {

	/**
	 * @param array<string, mixed> $row Fields.
	 * @return int Insert id or 0.
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$table = RWGA_DB::automation_rules_table();
		$now   = current_time( 'mysql', true );

		$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
		$uid = isset( $row['created_by'] ) ? (int) $row['created_by'] : 0;

		$geo = isset( $row['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $row['geo_target'] ), 0, 2 ) ) : '';

		$cfg = isset( $row['rule_config'] ) ? $row['rule_config'] : array();
		if ( is_array( $cfg ) ) {
			$cfg = wp_json_encode( $cfg );
		}
		if ( ! is_string( $cfg ) || '' === $cfg ) {
			$cfg = '{}';
		}

		$data = array(
			'name'          => isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '',
			'workflow_key'  => isset( $row['workflow_key'] ) ? sanitize_key( (string) $row['workflow_key'] ) : '',
			'trigger_type'  => isset( $row['trigger_type'] ) ? sanitize_key( (string) $row['trigger_type'] ) : 'manual',
			'target_scope'  => isset( $row['target_scope'] ) ? sanitize_key( (string) $row['target_scope'] ) : 'site',
			'page_id'       => $pid > 0 ? $pid : null,
			'geo_target'    => '' !== $geo ? $geo : null,
			'rule_config'   => $cfg,
			'last_run_at'   => null,
			'next_run_at'   => null,
			'status'        => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'active',
			'created_by'    => $uid > 0 ? $uid : null,
			'created_at'    => $now,
			'updated_at'    => $now,
		);

		$formats = array(
			'%s',
			'%s',
			'%s',
			'%s',
			null === $data['page_id'] ? '%s' : '%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			null === $data['created_by'] ? '%s' : '%d',
			'%s',
			'%s',
		);

		if ( null === $data['geo_target'] ) {
			$formats[5] = '%s';
		}
		if ( null === $data['last_run_at'] ) {
			$formats[7] = '%s';
		}
		if ( null === $data['next_run_at'] ) {
			$formats[8] = '%s';
		}

		$ok = $wpdb->insert( $table, $data, $formats );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Replace mutable fields (admin edit form).
	 *
	 * @param int                  $id  Rule ID.
	 * @param array<string, mixed> $row Fields.
	 * @return bool
	 */
	public static function update_rule( $id, array $row ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$table = RWGA_DB::automation_rules_table();
		$now   = current_time( 'mysql', true );

		$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
		$geo = isset( $row['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $row['geo_target'] ), 0, 2 ) ) : '';

		$cfg = isset( $row['rule_config'] ) ? $row['rule_config'] : array();
		if ( is_array( $cfg ) ) {
			$cfg = wp_json_encode( $cfg );
		}
		if ( ! is_string( $cfg ) ) {
			$cfg = '{}';
		}

		$data = array(
			'name'          => isset( $row['name'] ) ? sanitize_text_field( (string) $row['name'] ) : '',
			'workflow_key'  => isset( $row['workflow_key'] ) ? sanitize_key( (string) $row['workflow_key'] ) : '',
			'trigger_type'  => isset( $row['trigger_type'] ) ? sanitize_key( (string) $row['trigger_type'] ) : 'manual',
			'target_scope'  => isset( $row['target_scope'] ) ? sanitize_key( (string) $row['target_scope'] ) : 'site',
			'page_id'       => $pid > 0 ? $pid : null,
			'geo_target'    => '' !== $geo ? $geo : null,
			'rule_config'   => $cfg,
			'status'        => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'active',
			'updated_at'    => $now,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );
		if ( null === $data['page_id'] ) {
			$formats[4] = '%s';
		} else {
			$formats[4] = '%d';
		}
		if ( null === $data['geo_target'] ) {
			$formats[5] = '%s';
		} else {
			$formats[5] = '%s';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		return false !== $wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
	}

	/**
	 * @param int $id Rule ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$table = RWGA_DB::automation_rules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		return false !== $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * @param int    $id   Rule ID.
	 * @param string $last Last run UTC mysql.
	 * @param string $next Next run UTC mysql.
	 * @return bool
	 */
	public static function touch_run( $id, $last, $next ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$table = RWGA_DB::automation_rules_table();
		$now   = current_time( 'mysql', true );
		$data  = array(
			'last_run_at' => $last,
			'next_run_at' => $next,
			'updated_at'  => $now,
		);
		return false !== $wpdb->update( $table, $data, array( 'id' => $id ), array( '%s', '%s', '%s' ), array( '%d' ) );
	}

	/**
	 * @param int $id Rule ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$table = RWGA_DB::automation_rules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @return int
	 */
	public static function count_rows() {
		global $wpdb;
		$table = RWGA_DB::automation_rules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * @param int $per_page Items per page.
	 * @param int $paged    Page number.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_paged( $per_page = 20, $paged = 1 ) {
		global $wpdb;
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$paged    = max( 1, (int) $paged );
		$offset   = ( $paged - 1 ) * $per_page;
		$table    = RWGA_DB::automation_rules_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Active rules with trigger_type `schedule` and no future next_run_at (due now).
	 *
	 * @param int $limit Max rows.
	 * @return array<int>
	 */
	public static function get_due_scheduled_ids( $limit = 5 ) {
		global $wpdb;
		$limit = max( 1, min( 50, (int) $limit ) );
		$table = RWGA_DB::automation_rules_table();
		$now   = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$sql = $wpdb->prepare(
			"SELECT id FROM {$table} WHERE status = %s AND trigger_type = %s AND ( next_run_at IS NULL OR next_run_at <= %s ) ORDER BY next_run_at IS NULL DESC, next_run_at ASC LIMIT %d",
			'active',
			'schedule',
			$now,
			$limit
		);
		$ids = $wpdb->get_col( $sql );
		if ( ! is_array( $ids ) ) {
			return array();
		}
		return array_map( 'intval', $ids );
	}
}
