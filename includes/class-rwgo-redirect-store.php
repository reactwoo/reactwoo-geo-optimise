<?php
/**
 * Managed redirects (persisted paths → target URLs; survives trash/delete of source post when path known).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Promotion and legacy URL redirects.
 */
class RWGO_Redirect_Store {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_request' ), 0 );
	}

	/**
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'rwgo_redirects';
	}

	/**
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$t = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $t === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) );
	}

	/**
	 * Normalize path from post ID, full URL, or raw path segment.
	 *
	 * @param int|string $post_or_url Post ID, full URL, or path starting with /.
	 * @return string
	 */
	public static function path_from_post_or_url( $post_or_url ) {
		if ( is_numeric( $post_or_url ) ) {
			$url = get_permalink( (int) $post_or_url );
			$path = $url ? wp_parse_url( $url, PHP_URL_PATH ) : '/';
			return is_string( $path ) && '' !== $path ? $path : '/';
		}
		$s = (string) $post_or_url;
		if ( '' === $s ) {
			return '/';
		}
		if ( 0 === strpos( $s, 'http://' ) || 0 === strpos( $s, 'https://' ) || 0 === strpos( $s, '//' ) ) {
			$path = wp_parse_url( $s, PHP_URL_PATH );
			return is_string( $path ) && '' !== $path ? $path : '/';
		}
		return '/' === $s[0] ? $s : '/' . ltrim( $s, '/' );
	}

	/**
	 * Current request path (matches permalink path style).
	 *
	 * @return string
	 */
	public static function current_request_path() {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '/';
		}
		$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
		return is_string( $path ) && '' !== $path ? $path : '/';
	}

	/**
	 * @param array<string, mixed> $row Row.
	 * @return int|\WP_Error Insert ID or error.
	 */
	public static function insert_rule( array $row ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return new \WP_Error( 'rwgo_redirect_no_table', __( 'Redirect storage is not ready. Save Geo Optimise settings or re-activate the plugin.', 'reactwoo-geo-optimise' ) );
		}
		$table = self::table_name();
		$defaults = array(
			'source_path'         => '',
			'target_url'          => '',
			'source_post_id'      => 0,
			'target_post_id'      => 0,
			'experiment_post_id'  => 0,
			'promotion_event_id'    => 0,
			'active'              => 1,
			'created_at_gmt'      => gmdate( 'Y-m-d H:i:s' ),
			'created_by'          => get_current_user_id(),
			'meta_json'           => null,
		);
		$row = array_merge( $defaults, $row );
		$path = self::path_from_post_or_url( $row['source_path'] );
		if ( '/' === $path || '' === $path ) {
			return new \WP_Error( 'rwgo_redirect_path', __( 'Invalid redirect source path.', 'reactwoo-geo-optimise' ) );
		}
		$target = esc_url_raw( (string) $row['target_url'] );
		if ( '' === $target ) {
			return new \WP_Error( 'rwgo_redirect_target', __( 'Invalid redirect target.', 'reactwoo-geo-optimise' ) );
		}
		$meta = $row['meta_json'];
		if ( is_array( $meta ) ) {
			$meta = wp_json_encode( $meta );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			$table,
			array(
				'source_path'         => $path,
				'target_url'          => $target,
				'source_post_id'      => (int) $row['source_post_id'],
				'target_post_id'      => (int) $row['target_post_id'],
				'experiment_post_id'  => (int) $row['experiment_post_id'],
				'promotion_event_id'  => (int) $row['promotion_event_id'],
				'active'              => (int) ( ! empty( $row['active'] ) ? 1 : 0 ),
				'created_at_gmt'      => $row['created_at_gmt'],
				'created_by'          => (int) $row['created_by'],
				'meta_json'           => is_string( $meta ) && '' !== $meta ? $meta : '{}',
			),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s' )
		);
		if ( false === $ok ) {
			return new \WP_Error( 'rwgo_redirect_db', __( 'Could not save redirect rule.', 'reactwoo-geo-optimise' ) );
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $id Rule ID.
	 * @param int $active 1 or 0.
	 * @return bool
	 */
	public static function set_active( $id, $active ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			$table,
			array( 'active' => $active ? 1 : 0 ),
			array( 'id' => (int) $id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * @param int $id Rule ID.
	 * @return bool
	 */
	public static function delete_rule( $id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * @param int $id Rule ID.
	 * @return object|null
	 */
	public static function get_rule( $id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
		return $row ? $row : null;
	}

	/**
	 * Rules for an experiment (admin UI).
	 *
	 * @param int $experiment_post_id Experiment CPT ID.
	 * @return array<int, object>
	 */
	public static function get_rules_for_experiment( $experiment_post_id ) {
		global $wpdb;
		$table = self::table_name();
		$eid   = (int) $experiment_post_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE experiment_post_id = %d ORDER BY id DESC", $eid ) );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return void
	 */
	public static function maybe_redirect_request() {
		if ( ! self::table_exists() ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( is_user_logged_in() && isset( $_GET['preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$path = self::current_request_path();
		$row  = self::find_active_by_path( $path );
		if ( ! $row ) {
			return;
		}
		$target = isset( $row->target_url ) ? (string) $row->target_url : '';
		if ( '' === $target ) {
			return;
		}
		$target = apply_filters( 'rwgo_redirect_target_url', $target, $row );
		if ( ! is_string( $target ) || '' === $target ) {
			return;
		}
		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * @param string $path Request path.
	 * @return object|null
	 */
	public static function find_active_by_path( $path ) {
		global $wpdb;
		$table = self::table_name();
		$path  = is_string( $path ) ? $path : '/';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE active = 1 AND source_path = %s LIMIT 1",
				$path
			)
		);
		if ( $row ) {
			return $row;
		}
		// Trailing slash variant.
		$alt = untrailingslashit( $path );
		if ( $alt !== $path && '' !== $alt ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE active = 1 AND source_path = %s LIMIT 1",
					$alt
				)
			);
			if ( $row ) {
				return $row;
			}
		}
		$trail = trailingslashit( $path );
		if ( $trail !== $path ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE active = 1 AND source_path = %s LIMIT 1",
					$trail
				)
			);
		}
		return $row ? $row : null;
	}

	/**
	 * @param int $promotion_event_id Promotion row ID.
	 * @param int $redirect_id      Redirect rule ID.
	 * @return void
	 */
	public static function link_promotion_event( $promotion_event_id, $redirect_id ) {
		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array( 'promotion_event_id' => (int) $promotion_event_id ),
			array( 'id' => (int) $redirect_id ),
			array( '%d' ),
			array( '%d' )
		);
	}
}
