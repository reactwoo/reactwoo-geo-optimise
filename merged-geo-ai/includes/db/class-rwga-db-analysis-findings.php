<?php
/**
 * Persistence for analysis findings.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD for rwga_analysis_findings.
 */
class RWGA_DB_Analysis_Findings {

	/**
	 * Replace findings for a run (delete existing + insert).
	 *
	 * @param int                               $analysis_run_id Run ID.
	 * @param array<int, array<string, mixed>> $findings Finding rows.
	 * @return void
	 */
	public static function replace_for_run( $analysis_run_id, array $findings ) {
		global $wpdb;
		$analysis_run_id = (int) $analysis_run_id;
		if ( $analysis_run_id <= 0 ) {
			return;
		}
		$table = RWGA_DB::analysis_findings_table();
		$wpdb->delete( $table, array( 'analysis_run_id' => $analysis_run_id ), array( '%d' ) );

		$order = 0;
		foreach ( $findings as $f ) {
			++$order;
			if ( ! is_array( $f ) ) {
				continue;
			}
			$now = current_time( 'mysql', true );

			$conf = null;
			if ( isset( $f['confidence'] ) && null !== $f['confidence'] && '' !== $f['confidence'] ) {
				$conf = (float) $f['confidence'];
			}

			$data = array(
				'analysis_run_id'     => $analysis_run_id,
				'finding_key'         => isset( $f['finding_key'] ) ? sanitize_key( (string) $f['finding_key'] ) : '',
				'category'            => isset( $f['category'] ) ? sanitize_key( (string) $f['category'] ) : 'general',
				'severity'            => isset( $f['severity'] ) ? sanitize_key( (string) $f['severity'] ) : 'medium',
				'confidence'          => $conf,
				'title'               => isset( $f['title'] ) ? sanitize_text_field( (string) $f['title'] ) : '',
				'evidence'            => isset( $f['evidence'] ) ? wp_kses_post( (string) $f['evidence'] ) : null,
				'recommendation_hint' => isset( $f['recommendation_hint'] ) ? wp_kses_post( (string) $f['recommendation_hint'] ) : null,
				'impact_estimate'     => isset( $f['impact_estimate'] ) ? sanitize_text_field( (string) $f['impact_estimate'] ) : null,
				'sort_order'          => $order,
				'created_at'          => $now,
			);

			$formats = array( '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d', '%s' );
			if ( null === $conf ) {
				$data['confidence'] = null;
				$formats[4]         = '%s';
			}

			$wpdb->insert( $table, $data, $formats );
		}
	}

	/**
	 * @param int $analysis_run_id Run ID.
	 * @return array<int, array<string, mixed>>
	 */
	public static function list_for_run( $analysis_run_id ) {
		global $wpdb;
		$analysis_run_id = (int) $analysis_run_id;
		if ( $analysis_run_id <= 0 ) {
			return array();
		}
		$table = RWGA_DB::analysis_findings_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name trusted.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE analysis_run_id = %d ORDER BY sort_order ASC, id ASC",
				$analysis_run_id
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete all findings for one analysis run.
	 *
	 * @param int $analysis_run_id Run ID.
	 * @return bool
	 */
	public static function delete_for_run( $analysis_run_id ) {
		global $wpdb;
		$analysis_run_id = (int) $analysis_run_id;
		if ( $analysis_run_id <= 0 ) {
			return false;
		}
		$table = RWGA_DB::analysis_findings_table();
		return false !== $wpdb->delete( $table, array( 'analysis_run_id' => $analysis_run_id ), array( '%d' ) );
	}
}
