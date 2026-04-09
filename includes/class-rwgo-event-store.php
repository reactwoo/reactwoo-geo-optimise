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
		$inserted = $wpdb->insert(
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
		if ( false === $inserted ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[RWGO EventStore] DB insert failed: ' . $wpdb->last_error . ' (is wp_rwgo_events table missing? Run plugin activation or check DB permissions.)' );
			}
			return;
		}
		$insert_id = (int) $wpdb->insert_id;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $insert_id > 0 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				'[RWGO EventStore] row_id=' . (string) $insert_id . ' experiment=' . (string) ( $payload['experiment_key'] ?? '' ) . ' goal=' . (string) ( $payload['goal_id'] ?? '' )
			);
		}
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
	 * Goal completions per variant for a single stored goal_id (legacy helpers).
	 *
	 * @param string $experiment_key Experiment key.
	 * @param string $goal_id        Goal id.
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

	/**
	 * Total conversions per variant: sum of events matching any configured (goal_id, handler_id) pair for that variant.
	 *
	 * @param string               $experiment_key Key.
	 * @param array<string, mixed> $config         Experiment config.
	 * @param list<string>|null    $variant_slugs  If set, count only these variant ids (e.g. union of config + stats); otherwise config variants only.
	 * @return array<string, int> variant_id => count.
	 */
	public static function count_total_conversions_by_variant( $experiment_key, array $config, $variant_slugs = null ) {
		global $wpdb;
		$table = self::table_name();
		$key   = sanitize_text_field( (string) $experiment_key );
		if ( '' === $key ) {
			return array();
		}
		$slugs_to_count = array();
		if ( is_array( $variant_slugs ) && ! empty( $variant_slugs ) ) {
			foreach ( $variant_slugs as $vs ) {
				$sk = sanitize_key( (string) $vs );
				if ( '' !== $sk ) {
					$slugs_to_count[] = $sk;
				}
			}
			$slugs_to_count = array_values( array_unique( $slugs_to_count ) );
		} else {
			$variants_cfg = isset( $config['variants'] ) && is_array( $config['variants'] ) ? $config['variants'] : array();
			foreach ( $variants_cfg as $row ) {
				if ( ! is_array( $row ) || empty( $row['variant_id'] ) ) {
					continue;
				}
				$slugs_to_count[] = sanitize_key( (string) $row['variant_id'] );
			}
		}
		$out = array();
		foreach ( $slugs_to_count as $slug ) {
			$pairs = class_exists( 'RWGO_Experiment_Measurements', false )
				? RWGO_Experiment_Measurements::stored_pairs_for_variant( $config, $slug )
				: array();
			if ( empty( $pairs ) && class_exists( 'RWGO_Experiment_Measurements', false ) ) {
				$pairs = RWGO_Experiment_Measurements::stored_pairs_all_goals( $config );
			}
			if ( empty( $pairs ) ) {
				$out[ $slug ] = 0;
				continue;
			}
			$where = array( 'experiment_key = %s', 'variant_id = %s', 'event_type = %s' );
			$args  = array( $key, $slug, 'goal_fired' );
			$ors   = array();
			foreach ( $pairs as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				$g = sanitize_key( (string) ( $p['goal_id'] ?? '' ) );
				$h = sanitize_key( (string) ( $p['handler_id'] ?? '' ) );
				if ( '' === $g || '' === $h ) {
					continue;
				}
				$ors[] = '(goal_id = %s AND handler_id = %s)';
				$args[] = $g;
				$args[] = $h;
			}
			if ( empty( $ors ) ) {
				$out[ $slug ] = 0;
				continue;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
			$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode( ' AND ', $where ) . ' AND (' . implode( ' OR ', $ors ) . ')';
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders match $args.
			$prepared = $wpdb->prepare( $sql, $args );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$cnt = (int) $wpdb->get_var( $prepared );
			$out[ $slug ] = $cnt;
		}
		return $out;
	}

	/**
	 * Raw counts per variant × goal_id × handler_id for breakdown (labels applied in PHP).
	 *
	 * @param string $experiment_key Key.
	 * @return list<array{variant_id: string, goal_id: string, handler_id: string, c: int}>
	 */
	public static function count_breakdown_by_variant_goal_handler( $experiment_key ) {
		global $wpdb;
		$table = self::table_name();
		$key   = sanitize_text_field( (string) $experiment_key );
		if ( '' === $key ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$sql = $wpdb->prepare(
			"SELECT variant_id, goal_id, handler_id, COUNT(*) AS c FROM {$table} WHERE experiment_key = %s AND event_type = %s GROUP BY variant_id, goal_id, handler_id",
			$key,
			'goal_fired'
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $results ) ) {
			return array();
		}
		$out = array();
		foreach ( $results as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$out[] = array(
				'variant_id' => sanitize_key( (string) ( $row['variant_id'] ?? '' ) ),
				'goal_id'    => sanitize_key( (string) ( $row['goal_id'] ?? '' ) ),
				'handler_id' => sanitize_key( (string) ( $row['handler_id'] ?? '' ) ),
				'c'          => (int) ( $row['c'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Raw stored goal events for an experiment, including decoded meta payload.
	 *
	 * @param string $experiment_key Key.
	 * @return list<array{variant_id: string, goal_id: string, handler_id: string, meta: array<string, mixed>}>
	 */
	public static function list_goal_event_rows( $experiment_key ) {
		global $wpdb;
		$table = self::table_name();
		$key   = sanitize_text_field( (string) $experiment_key );
		if ( '' === $key ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		$sql = $wpdb->prepare(
			"SELECT variant_id, goal_id, handler_id, meta_json FROM {$table} WHERE experiment_key = %s AND event_type = %s ORDER BY id ASC",
			$key,
			'goal_fired'
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $results ) ) {
			return array();
		}
		$out = array();
		foreach ( $results as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$meta = array();
			if ( ! empty( $row['meta_json'] ) && is_string( $row['meta_json'] ) ) {
				$decoded = json_decode( $row['meta_json'], true );
				if ( is_array( $decoded ) ) {
					$meta = $decoded;
				}
			}
			$out[] = array(
				'variant_id' => sanitize_key( (string) ( $row['variant_id'] ?? '' ) ),
				'goal_id'    => sanitize_key( (string) ( $row['goal_id'] ?? '' ) ),
				'handler_id' => sanitize_key( (string) ( $row['handler_id'] ?? '' ) ),
				'meta'       => $meta,
			);
		}
		return $out;
	}
}
