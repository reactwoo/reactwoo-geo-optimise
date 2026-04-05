<?php
/**
 * Promotion audit records (wp_rwgo_promotions).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists promotion events for support and UI.
 */
class RWGO_Promotion_Log {

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'rwgo_promotions';
	}

	/**
	 * @param array<string, mixed> $row Row.
	 * @return int|\WP_Error
	 */
	public static function insert( array $row ) {
		global $wpdb;
		$table = self::table_name();
		$data  = array(
			'experiment_post_id'   => (int) ( $row['experiment_post_id'] ?? 0 ),
			'source_post_id'       => (int) ( $row['source_post_id'] ?? 0 ),
			'variant_post_id'    => (int) ( $row['variant_post_id'] ?? 0 ),
			'mode'                 => sanitize_key( (string) ( $row['mode'] ?? 'replace_content' ) ),
			'variant_action'       => sanitize_key( (string) ( $row['variant_action'] ?? '' ) ),
			'redirect_id'          => (int) ( $row['redirect_id'] ?? 0 ),
			'acting_user_id'       => (int) ( $row['acting_user_id'] ?? get_current_user_id() ),
			'created_at_gmt'       => gmdate( 'Y-m-d H:i:s' ),
			'source_url_snapshot'  => isset( $row['source_url_snapshot'] ) ? (string) $row['source_url_snapshot'] : '',
			'target_url_snapshot'  => isset( $row['target_url_snapshot'] ) ? (string) $row['target_url_snapshot'] : '',
			'variant_url_snapshot' => isset( $row['variant_url_snapshot'] ) ? (string) $row['variant_url_snapshot'] : '',
			'meta_json'            => '{}',
		);
		if ( isset( $row['meta_json'] ) && is_array( $row['meta_json'] ) ) {
			$data['meta_json'] = wp_json_encode( $row['meta_json'] );
		} elseif ( isset( $row['meta_json'] ) && is_string( $row['meta_json'] ) ) {
			$data['meta_json'] = $row['meta_json'];
		} else {
			$data['meta_json'] = '{}';
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			$table,
			$data,
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $ok ) {
			return new \WP_Error( 'rwgo_promo_log', __( 'Could not save promotion record.', 'reactwoo-geo-optimise' ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $id Promotion row ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
		return $row ? $row : null;
	}

	/**
	 * Delete audit rows for an experiment (e.g. after test deletion).
	 *
	 * @param int $experiment_post_id Experiment CPT ID.
	 * @return int Rows deleted.
	 */
	public static function delete_by_experiment( $experiment_post_id ) {
		global $wpdb;
		$table = self::table_name();
		$eid   = (int) $experiment_post_id;
		if ( $eid <= 0 ) {
			return 0;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->delete( $table, array( 'experiment_post_id' => $eid ), array( '%d' ) );
	}
}
