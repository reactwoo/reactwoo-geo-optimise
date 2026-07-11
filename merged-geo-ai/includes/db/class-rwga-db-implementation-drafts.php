<?php
/**
 * Persistence for implementation drafts (copy, SEO assets, etc.).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for rwga_implementation_drafts.
 */
class RWGA_DB_Implementation_Drafts {

	/**
	 * Insert one draft row. draft_payload must be JSON-encoded string or array (encoded here).
	 *
	 * @param array<string, mixed> $row Fields.
	 * @return int Insert id or 0.
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$table = RWGA_DB::implementation_drafts_table();
		$now   = current_time( 'mysql', true );

		$rid = isset( $row['recommendation_id'] ) ? (int) $row['recommendation_id'] : 0;
		$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
		$uid = isset( $row['created_by'] ) ? (int) $row['created_by'] : 0;

		$geo = isset( $row['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $row['geo_target'] ), 0, 2 ) ) : '';

		$payload = isset( $row['draft_payload'] ) ? $row['draft_payload'] : array();
		if ( is_array( $payload ) ) {
			$payload = wp_json_encode( $payload );
		}
		if ( ! is_string( $payload ) ) {
			$payload = '{}';
		}

		$input_ctx = isset( $row['input_context'] ) ? (string) $row['input_context'] : '';
		$diff        = isset( $row['diff_payload'] ) ? $row['diff_payload'] : null;
		if ( is_array( $diff ) ) {
			$diff = wp_json_encode( $diff );
		}

		$data = array(
			'recommendation_id' => $rid > 0 ? $rid : null,
			'workflow_key'      => isset( $row['workflow_key'] ) ? sanitize_key( (string) $row['workflow_key'] ) : '',
			'draft_type'        => isset( $row['draft_type'] ) ? sanitize_key( (string) $row['draft_type'] ) : 'copy',
			'page_id'           => $pid > 0 ? $pid : null,
			'geo_target'        => '' !== $geo ? $geo : null,
			'title'             => isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '',
			'input_context'     => $input_ctx !== '' ? $input_ctx : null,
			'draft_payload'     => $payload,
			'report_html'       => isset( $row['report_html'] ) ? wp_kses_post( (string) $row['report_html'] ) : null,
			'implementation_route' => isset( $row['implementation_route'] ) ? sanitize_key( (string) $row['implementation_route'] ) : null,
			'variant_page_id'   => isset( $row['variant_page_id'] ) ? ( (int) $row['variant_page_id'] > 0 ? (int) $row['variant_page_id'] : null ) : null,
			'geo_optimise_id'   => isset( $row['geo_optimise_id'] ) ? ( (int) $row['geo_optimise_id'] > 0 ? (int) $row['geo_optimise_id'] : null ) : null,
			'diff_payload'      => is_string( $diff ) && '' !== $diff ? $diff : null,
			'status'            => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'draft',
			'applied_at'        => null,
			'created_by'        => $uid > 0 ? $uid : null,
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		$formats = array(
			null === $data['recommendation_id'] ? '%s' : '%d',
			'%s',
			'%s',
			null === $data['page_id'] ? '%s' : '%d',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
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
			$formats[4] = '%s';
		}
		if ( null === $data['input_context'] ) {
			$formats[6] = '%s';
		}
		if ( null === $data['diff_payload'] ) {
			$formats[12] = '%s';
		}
		if ( null === $data['applied_at'] ) {
			$formats[14] = '%s';
		}

		$ok = $wpdb->insert( $table, $data, $formats );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @param int $id Draft ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$table = RWGA_DB::implementation_drafts_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Delete a single draft row.
	 *
	 * @param int $id Draft ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return false;
		}
		$table = RWGA_DB::implementation_drafts_table();
		return false !== $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * @param int $recommendation_id Recommendation ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_for_recommendation( $recommendation_id ) {
		global $wpdb;
		$recommendation_id = (int) $recommendation_id;
		if ( $recommendation_id <= 0 ) {
			return array();
		}
		$table = RWGA_DB::implementation_drafts_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE recommendation_id = %d ORDER BY id ASC", $recommendation_id ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int    $recommendation_id Optional filter.
	 * @param string $workflow_key      Optional filter (e.g. copy_implement, seo_implement).
	 * @return int
	 */
	public static function count_rows( $recommendation_id = 0, $workflow_key = '', array $filters = array() ) {
		global $wpdb;
		$table             = RWGA_DB::implementation_drafts_table();
		$recommendation_id = (int) $recommendation_id;
		$wk                = sanitize_key( (string) $workflow_key );

		$where = array();
		$args  = array();
		if ( $recommendation_id > 0 && '' !== $wk ) {
			$where[] = 'recommendation_id = %d';
			$args[]  = $recommendation_id;
			$where[] = 'workflow_key = %s';
			$args[]  = $wk;
		} elseif ( $recommendation_id > 0 ) {
			$where[] = 'recommendation_id = %d';
			$args[]  = $recommendation_id;
		} elseif ( '' !== $wk ) {
			$where[] = 'workflow_key = %s';
			$args[]  = $wk;
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = sanitize_key( (string) $filters['status'] );
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
		return (int) ( empty( $args ) ? $wpdb->get_var( $sql ) : $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) );
	}

	/**
	 * @param int    $per_page          Items per page.
	 * @param int    $paged             Page number.
	 * @param int    $recommendation_id Optional filter.
	 * @param string $workflow_key      Optional filter.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_paged( $per_page = 20, $paged = 1, $recommendation_id = 0, $workflow_key = '', array $filters = array() ) {
		global $wpdb;
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$paged    = max( 1, (int) $paged );
		$offset   = ( $paged - 1 ) * $per_page;
		$table    = RWGA_DB::implementation_drafts_table();
		$recommendation_id = (int) $recommendation_id;
		$wk                  = sanitize_key( (string) $workflow_key );

		$where = array();
		$args  = array();
		if ( $recommendation_id > 0 ) {
			$where[] = 'recommendation_id = %d';
			$args[]  = $recommendation_id;
		}
		if ( '' !== $wk ) {
			$where[] = 'workflow_key = %s';
			$args[]  = $wk;
		}
		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = sanitize_key( (string) $filters['status'] );
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
	 * Delete drafts for a set of recommendation IDs.
	 *
	 * @param array<int, int> $recommendation_ids Recommendation IDs.
	 * @return bool
	 */
	public static function delete_for_recommendations( array $recommendation_ids ) {
		global $wpdb;
		$ids = array_values(
			array_filter(
				array_map( 'intval', $recommendation_ids ),
				static function ( $v ) {
					return $v > 0;
				}
			)
		);
		if ( empty( $ids ) ) {
			return false;
		}
		$table        = RWGA_DB::implementation_drafts_table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = "DELETE FROM {$table} WHERE recommendation_id IN ({$placeholders})"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$prepared     = $wpdb->prepare( $sql, $ids );
		if ( ! is_string( $prepared ) || '' === $prepared ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
		return false !== $wpdb->query( $prepared );
	}

	/**
	 * Set implementation route for recommendation drafts.
	 *
	 * @param int    $recommendation_id Recommendation ID.
	 * @param string $route Route key.
	 * @return bool
	 */
	public static function set_route_for_recommendation( $recommendation_id, $route ) {
		global $wpdb;
		$recommendation_id = (int) $recommendation_id;
		$route             = sanitize_key( (string) $route );
		if ( $recommendation_id <= 0 || '' === $route ) {
			return false;
		}
		$table = RWGA_DB::implementation_drafts_table();
		$ok    = $wpdb->update(
			$table,
			array(
				'implementation_route' => $route,
				'updated_at'           => current_time( 'mysql', true ),
			),
			array( 'recommendation_id' => $recommendation_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		return false !== $ok;
	}
}
