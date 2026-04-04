<?php
/**
 * Persistent goal events (custom table) + hooks.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DB storage for experiment-scoped events.
 */
class RWGO_Event_Store {

	const DB_VERSION = '1.0.0';
	const OPTION_KEY = 'rwgo_events_db_version';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwgo_goal_fired', array( __CLASS__, 'on_goal_fired' ), 10, 1 );
	}

	/**
	 * @return void
	 */
	public static function activate() {
		global $wpdb;
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_instance_id varchar(64) NOT NULL,
			experiment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			experiment_key varchar(191) NOT NULL DEFAULT '',
			variant_id varchar(64) NOT NULL DEFAULT '',
			goal_id varchar(64) NOT NULL DEFAULT '',
			handler_id varchar(64) NOT NULL DEFAULT '',
			page_context_id bigint(20) unsigned NOT NULL DEFAULT 0,
			page_variant_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event_type varchar(32) NOT NULL DEFAULT '',
			element_fingerprint varchar(191) NOT NULL DEFAULT '',
			visitor_assignment_hash varchar(64) NOT NULL DEFAULT '',
			session_hash varchar(64) NOT NULL DEFAULT '',
			country_code char(2) NOT NULL DEFAULT '',
			device_type varchar(32) NOT NULL DEFAULT '',
			created_at_gmt datetime NOT NULL,
			meta_json longtext NULL,
			PRIMARY KEY (id),
			KEY experiment_key_created (experiment_key, created_at_gmt),
			KEY goal_handler (goal_id, handler_id),
			KEY event_instance (event_instance_id)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::OPTION_KEY, self::DB_VERSION, false );
	}

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'rwgo_events';
	}

	/**
	 * @param array<string, mixed> $payload From RWGO_Event_Payload::normalize_goal_fired.
	 * @return void
	 */
	public static function on_goal_fired( $payload ) {
		if ( ! is_array( $payload ) ) {
			return;
		}
		$eid = isset( $payload['event_instance_id'] ) ? substr( sanitize_text_field( (string) $payload['event_instance_id'] ), 0, 64 ) : '';
		if ( '' !== $eid && self::has_event_instance_id( $eid ) ) {
			return;
		}
		global $wpdb;
		$table = self::table_name();
		$wpdb->insert(
			$table,
			array(
				'event_instance_id'      => substr( (string) ( $payload['event_instance_id'] ?? '' ), 0, 64 ),
				'experiment_id'          => (int) ( $payload['experiment_id'] ?? 0 ),
				'experiment_key'         => substr( (string) ( $payload['experiment_key'] ?? '' ), 0, 191 ),
				'variant_id'             => substr( (string) ( $payload['variant_id'] ?? '' ), 0, 64 ),
				'goal_id'                => substr( (string) ( $payload['goal_id'] ?? '' ), 0, 64 ),
				'handler_id'             => substr( (string) ( $payload['handler_id'] ?? '' ), 0, 64 ),
				'page_context_id'        => (int) ( $payload['page_context_id'] ?? 0 ),
				'page_variant_post_id'   => (int) ( $payload['page_variant_post_id'] ?? 0 ),
				'event_type'             => substr( (string) ( $payload['event_type'] ?? '' ), 0, 32 ),
				'element_fingerprint'    => substr( (string) ( $payload['element_fingerprint'] ?? '' ), 0, 191 ),
				'visitor_assignment_hash'=> '',
				'session_hash'           => '',
				'country_code'           => substr( (string) ( $payload['country'] ?? '' ), 0, 2 ),
				'device_type'            => substr( (string) ( $payload['device_type'] ?? '' ), 0, 32 ),
				'created_at_gmt'         => gmdate( 'Y-m-d H:i:s' ),
				'meta_json'              => wp_json_encode( $payload ),
			),
			array(
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
		$insert_id = (int) $wpdb->insert_id;
		/**
		 * After a goal row is stored.
		 *
		 * @param int                  $insert_id New row ID.
		 * @param array<string, mixed> $payload   Payload.
		 */
		do_action( 'rwgo_goal_event_recorded', $insert_id, $payload );
	}

	/**
	 * @param string $event_instance_id Event instance id.
	 * @return bool
	 */
	public static function has_event_instance_id( $event_instance_id ) {
		global $wpdb;
		$table = self::table_name();
		$id    = substr( sanitize_text_field( (string) $event_instance_id ), 0, 64 );
		if ( '' === $id ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$sql = $wpdb->prepare( "SELECT 1 FROM {$table} WHERE event_instance_id = %s LIMIT 1", $id );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (bool) $wpdb->get_var( $sql );
	}

	/**
	 * Row counts per experiment key (quick report).
	 *
	 * @param string $experiment_key Key.
	 * @return int
	 */
	public static function count_by_experiment_key( $experiment_key ) {
		global $wpdb;
		$table = self::table_name();
		$key   = sanitize_text_field( (string) $experiment_key );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name escaped.
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE experiment_key = %s", $key );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic table.
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Total stored goal events (all experiments).
	 *
	 * @return int
	 */
	public static function count_total() {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix.
		$sql = "SELECT COUNT(*) FROM {$table}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Goal completions per variant for winner reporting (primary goal conversion rate).
	 *
	 * @param string $experiment_key Experiment key.
	 * @param string $goal_id        Primary goal id.
	 * @return array<string, int> variant_id => count.
	 */
	public static function count_goal_completions_by_variant( $experiment_key, $goal_id ) {
		global $wpdb;
		$table = self::table_name();
		$key   = sanitize_text_field( (string) $experiment_key );
		$gid   = sanitize_text_field( (string) $goal_id );
		if ( '' === $key || '' === $gid ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$sql = $wpdb->prepare(
			"SELECT variant_id, COUNT(*) AS c FROM {$table} WHERE experiment_key = %s AND goal_id = %s AND event_type = %s GROUP BY variant_id",
			$key,
			$gid,
			'goal_fired'
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $results ) ) {
			return array();
		}
		$out = array();
		foreach ( $results as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['variant_id'], $row['c'] ) ) {
				continue;
			}
			$out[ sanitize_key( (string) $row['variant_id'] ) ] = (int) $row['c'];
		}
		return $out;
	}
}
