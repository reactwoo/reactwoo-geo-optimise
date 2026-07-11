<?php
/**
 * Persistence for competitor research rows.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for rwga_competitor_research.
 */
class RWGA_DB_Competitor_Research {

	/**
	 * @param array<string, mixed> $row Fields.
	 * @return int Insert id or 0.
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$table = RWGA_DB::competitor_research_table();
		$now   = current_time( 'mysql', true );

		$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
		$uid = isset( $row['created_by'] ) ? (int) $row['created_by'] : 0;

		$geo = isset( $row['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $row['geo_target'] ), 0, 2 ) ) : '';

		$url = isset( $row['competitor_url'] ) ? esc_url_raw( (string) $row['competitor_url'] ) : '';
		if ( '' === $url ) {
			return 0;
		}

		$data = array(
			'page_id'         => $pid > 0 ? $pid : null,
			'competitor_url'  => $url,
			'page_type'       => isset( $row['page_type'] ) ? sanitize_key( (string) $row['page_type'] ) : '',
			'geo_target'      => '' !== $geo ? $geo : null,
			'workflow_key'    => isset( $row['workflow_key'] ) ? sanitize_key( (string) $row['workflow_key'] ) : '',
			'summary'         => isset( $row['summary'] ) ? wp_kses_post( (string) $row['summary'] ) : '',
			'strengths'       => isset( $row['strengths'] ) ? wp_kses_post( (string) $row['strengths'] ) : '',
			'weaknesses'      => isset( $row['weaknesses'] ) ? wp_kses_post( (string) $row['weaknesses'] ) : '',
			'patterns'        => isset( $row['patterns'] ) ? wp_kses_post( (string) $row['patterns'] ) : '',
			'opportunities'   => isset( $row['opportunities'] ) ? wp_kses_post( (string) $row['opportunities'] ) : '',
			'status'          => isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'complete',
			'created_by'      => $uid > 0 ? $uid : null,
			'created_at'      => $now,
			'updated_at'      => $now,
		);

		$formats = array(
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
			null === $data['created_by'] ? '%s' : '%d',
			'%s',
			'%s',
		);

		if ( null === $data['geo_target'] ) {
			$formats[3] = '%s';
		}

		$ok = $wpdb->insert( $table, $data, $formats );
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @param int $id Row ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$id = (int) $id;
		if ( $id <= 0 ) {
			return null;
		}
		$table = RWGA_DB::competitor_research_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * @param int $page_id Optional filter.
	 * @return int
	 */
	public static function count_rows( $page_id = 0 ) {
		global $wpdb;
		$table   = RWGA_DB::competitor_research_table();
		$page_id = (int) $page_id;
		if ( $page_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE page_id = %d", $page_id ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * @param int $per_page Items per page.
	 * @param int $paged    Page number.
	 * @param int $page_id  Optional filter.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_paged( $per_page = 20, $paged = 1, $page_id = 0 ) {
		global $wpdb;
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$paged    = max( 1, (int) $paged );
		$offset   = ( $paged - 1 ) * $per_page;
		$table    = RWGA_DB::competitor_research_table();
		$page_id  = (int) $page_id;

		if ( $page_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE page_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$page_id,
					$per_page,
					$offset
				),
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A );
		}
		return is_array( $rows ) ? $rows : array();
	}
}
