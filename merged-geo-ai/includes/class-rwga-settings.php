<?php
/**
 * ReactWoo API URL + product license for Geo AI (commercial satellite — not stored in Geo Core).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for ReactWoo Geo AI.
 */
class RWGA_Settings {

	const OPTION_KEY = 'rwga_settings';

	/**
	 * Separate option so disconnect survives `rwga_settings` merges / boolean quirks in wp_options.
	 *
	 * @var string
	 */
	const OPTION_BLOCK_CORE_LICENSE_BRIDGE = 'rwga_block_core_license_bridge';

	/**
	 * In-memory snapshot of `rwga_settings` read directly from the DB (bypasses object cache).
	 *
	 * @var array<string, mixed>|null
	 */
	private static $db_snapshot_rwga_settings = null;

	/**
	 * Whether {@see self::$db_snapshot_rwga_settings} has been loaded this request.
	 *
	 * @var bool
	 */
	private static $db_snapshot_rwga_settings_ready = false;

	/**
	 * Memo for bridge option read from DB (bypasses object cache).
	 *
	 * @var int|null
	 */
	private static $db_snapshot_bridge = null;

	/**
	 * Whether {@see self::$db_snapshot_bridge} has been resolved this request.
	 *
	 * @var bool
	 */
	private static $db_snapshot_bridge_ready = false;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_reset_snapshots_after_license_redirect' ), 0 );
		add_action( 'load-rwgc-dashboard_page_rwga-license', array( __CLASS__, 'reset_db_option_snapshots' ), 1 );
		add_action( 'load-rwgc-dashboard_page_rwga-advanced', array( __CLASS__, 'reset_db_option_snapshots' ), 1 );
		add_action( 'update_option_' . self::OPTION_KEY, array( __CLASS__, 'maybe_clear_jwt_on_change' ), 10, 2 );
		add_action( 'update_option_' . self::OPTION_KEY, array( __CLASS__, 'maybe_refresh_usage_after_license_change' ), 20, 2 );
		add_action( 'update_option_' . self::OPTION_KEY, array( __CLASS__, 'reset_db_option_snapshots' ), 0 );
		add_action( 'update_option_' . self::OPTION_BLOCK_CORE_LICENSE_BRIDGE, array( __CLASS__, 'reset_db_option_snapshots' ), 0 );
		add_action( 'delete_option_' . self::OPTION_KEY, array( __CLASS__, 'reset_db_option_snapshots' ), 0 );
		add_action( 'delete_option_' . self::OPTION_BLOCK_CORE_LICENSE_BRIDGE, array( __CLASS__, 'reset_db_option_snapshots' ), 0 );
		add_filter( 'wp_redirect', array( __CLASS__, 'filter_options_save_redirect' ), 5, 2 );
		add_filter( 'rwgc_auth_login_body', array( __CLASS__, 'filter_auth_login_body' ), 10, 3 );
	}

	/**
	 * After Disconnect redirect (`rwga_disconnected`) or import redirect, bust memos before the Settings screen reads options
	 * (avoids “Connected” until second click when object cache / load hook order left a stale memo).
	 *
	 * @return void
	 */
	public static function maybe_reset_snapshots_after_license_redirect() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'rwga-license' !== $page && 'rwga-advanced' !== $page ) {
			return;
		}
		if ( empty( $_GET['rwga_disconnected'] ) && empty( $_GET['rwga_imported'] ) && empty( $_GET['rwga_import_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		self::reset_db_option_snapshots();
		if ( class_exists( 'RWGA_Platform_Client', false ) ) {
			RWGA_Platform_Client::clear_token_cache();
		}
	}

	/**
	 * Clear memoized DB reads so the next lookup reflects a just-updated option row.
	 *
	 * @return void
	 */
	public static function reset_db_option_snapshots() {
		self::$db_snapshot_rwga_settings       = null;
		self::$db_snapshot_rwga_settings_ready = false;
		self::$db_snapshot_bridge              = null;
		self::$db_snapshot_bridge_ready        = false;
	}

	/**
	 * Read `rwga_settings` from the database (avoids stale wp_options object cache after disconnect).
	 *
	 * @return array<string, mixed>
	 */
	public static function get_stored_settings_from_db() {
		if ( self::$db_snapshot_rwga_settings_ready ) {
			return is_array( self::$db_snapshot_rwga_settings ) ? self::$db_snapshot_rwga_settings : array();
		}
		self::$db_snapshot_rwga_settings_ready = true;
		global $wpdb;
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPTION_KEY ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( null === $raw || '' === $raw ) {
			self::$db_snapshot_rwga_settings = array();
			return array();
		}
		$data = maybe_unserialize( $raw );
		self::$db_snapshot_rwga_settings = is_array( $data ) ? $data : array();
		return self::$db_snapshot_rwga_settings;
	}

	/**
	 * License key as stored in the DB (authoritative for UI + platform client when object cache lags).
	 *
	 * @return string
	 */
	public static function get_saved_license_key() {
		$stored = self::get_stored_settings_from_db();
		$k      = isset( $stored['reactwoo_license_key'] ) ? trim( (string) $stored['reactwoo_license_key'] ) : '';
		if ( '' !== $k ) {
			return $k;
		}
		if ( defined( 'RWGO_AI_EMBEDDED' ) && RWGO_AI_EMBEDDED && class_exists( 'RWGO_Settings', false ) ) {
			$opt = RWGO_Settings::get_settings();
			if ( is_array( $opt ) && ! empty( $opt['reactwoo_license_key'] ) ) {
				return trim( (string) $opt['reactwoo_license_key'] );
			}
		}
		return '';
	}

	/**
	 * Bridge flag from DB (authoritative when object cache lags).
	 *
	 * @return int 0 or 1
	 */
	private static function get_bridge_flag_from_db() {
		if ( self::$db_snapshot_bridge_ready ) {
			return (int) self::$db_snapshot_bridge;
		}
		self::$db_snapshot_bridge_ready = true;
		global $wpdb;
		$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPTION_BLOCK_CORE_LICENSE_BRIDGE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( null === $raw || '' === $raw ) {
			self::$db_snapshot_bridge = 0;
			return 0;
		}
		$v                        = maybe_unserialize( $raw );
		self::$db_snapshot_bridge = (int) $v;
		return (int) self::$db_snapshot_bridge;
	}

	/**
	 * Legacy no-op: Geo AI now owns its own platform client and does not register shared license filters.
	 *
	 * @return void
	 */
	public static function register_platform_filters() {
		return;
	}

	/**
	 * When this site uses Geo AI’s license key, tell the API which catalog product the token is for.
	 *
	 * @param array<string, string> $body    Login JSON body.
	 * @param string                $license Effective license key.
	 * @param string                $domain  Site host.
	 * @return array<string, string>
	 */
	public static function filter_auth_login_body( $body, $license, $domain ) {
		unset( $domain );
		$our = self::get_saved_license_key();
		if ( '' === $our || trim( (string) $license ) !== $our ) {
			return $body;
		}
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		$slug = 'reactwoo-geo-ai';
		if ( defined( 'RWGO_AI_EMBEDDED' ) && RWGO_AI_EMBEDDED && class_exists( 'RWGO_Platform_Client', false ) ) {
			$slug = RWGO_Platform_Client::PRODUCT_SLUG;
		}
		$body['product_slug']  = $slug;
		$body['catalog_slug'] = $slug;
		return $body;
	}

	/**
	 * Legacy no-op: automatic cross-plugin license migration has been removed.
	 *
	 * @return void
	 */
	public static function maybe_migrate_from_geo_core() {
		return;
	}

	/**
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'rwga_license_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Final effective license for the ReactWoo API (runs after Optimise / Commerce filters).
	 *
	 * @param string $key Value built by earlier filters (may include Geo Core via other plugins).
	 * @return string
	 */
	public static function filter_license_key_final( $key ) {
		$rwga_k = self::get_saved_license_key();
		if ( '' !== $rwga_k ) {
			return $rwga_k;
		}
		// Geo AI → Disconnect: no own key — do not keep using Core / Optimise / Commerce fallback keys
		// (those plugins often store a migrated copy of the same key, which defeated earlier strip logic).
		if ( self::is_geo_ai_license_disconnected() ) {
			return '';
		}
		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Whether Geo AI itself has a saved license key.
	 * The License screen should reflect Geo AI's own saved product key, not a shared fallback key.
	 *
	 * Uses {@see RWGA_Platform_Client::is_configured()} when available so this matches the DB-backed
	 * key used for login — the static memo in {@see get_saved_license_key()} can lag and wrongly
	 * return empty, which previously made “Refresh usage” clear license state as if disconnected.
	 *
	 * @return bool
	 */
	public static function is_license_configured_for_geo_ai_ui() {
		if ( self::is_geo_ai_license_disconnected() ) {
			return false;
		}
		if ( class_exists( 'RWGA_Platform_Client', false ) ) {
			return RWGA_Platform_Client::is_configured();
		}
		return '' !== self::get_saved_license_key();
	}

	/**
	 * Public guard used by the platform client / UI to force "disconnected" while bridge-block is active.
	 *
	 * @return bool
	 */
	public static function is_explicitly_disconnected() {
		return self::is_geo_ai_license_disconnected();
	}

	/**
	 * Geo AI → Disconnect: stop using Geo Core’s key when Geo AI’s own field is empty.
	 *
	 * @return bool
	 */
	private static function is_geo_ai_license_disconnected() {
		if ( 1 === self::get_bridge_flag_from_db() ) {
			return true;
		}
		$raw = self::get_stored_settings_from_db();
		if ( is_array( $raw ) && array_key_exists( 'reactwoo_license_use_core_fallback', $raw ) ) {
			$v = $raw['reactwoo_license_use_core_fallback'];
			return false === $v || 0 === $v || '0' === $v;
		}
		return false;
	}

	/**
	 * @param string $base Default URL.
	 * @return string
	 */
	public static function filter_api_base( $base ) {
		if ( defined( 'RWGA_REACTWOO_API_BASE' ) && is_string( RWGA_REACTWOO_API_BASE ) ) {
			$c = trim( (string) RWGA_REACTWOO_API_BASE );
			if ( '' !== $c && wp_http_validate_url( $c ) ) {
				return untrailingslashit( esc_url_raw( $c ) );
			}
		}

		$via_filter = apply_filters( 'rwga_reactwoo_api_base', null );
		if ( is_string( $via_filter ) ) {
			$u = esc_url_raw( trim( $via_filter ) );
			if ( $u && wp_http_validate_url( $u ) ) {
				return untrailingslashit( $u );
			}
		}

		$s = self::get_settings();
		if ( is_array( $s ) && ! empty( $s['reactwoo_api_base'] ) ) {
			$u = esc_url_raw( trim( (string) $s['reactwoo_api_base'] ) );
			if ( $u && wp_http_validate_url( $u ) ) {
				return untrailingslashit( $u );
			}
		}
		if ( class_exists( 'RWGC_Settings', false ) ) {
			$raw = get_option( RWGC_Settings::OPTION_KEY, array() );
			if ( is_array( $raw ) && ! empty( $raw['reactwoo_api_base'] ) ) {
				$u = esc_url_raw( trim( (string) $raw['reactwoo_api_base'] ) );
				if ( $u && wp_http_validate_url( $u ) ) {
					return untrailingslashit( $u );
				}
			}
		}
		$def = is_string( $base ) && '' !== trim( $base ) ? trim( $base ) : 'https://api.reactwoo.com';
		return untrailingslashit( $def );
	}

	/**
	 * Whether the API base field may be shown and saved (Advanced / support).
	 *
	 * @return bool
	 */
	public static function can_edit_api_base_field() {
		if ( defined( 'RWGA_REACTWOO_API_BASE' ) && is_string( RWGA_REACTWOO_API_BASE ) && '' !== trim( (string) RWGA_REACTWOO_API_BASE ) ) {
			return false;
		}
		if ( defined( 'RWGA_SHOW_API_BASE_UI' ) && RWGA_SHOW_API_BASE_UI ) {
			return true;
		}
		return (bool) apply_filters( 'rwga_show_api_base_field', false );
	}

	/**
	 * Clear saved license key (disconnect).
	 *
	 * @return void
	 */
	public static function clear_license_key() {
		if ( class_exists( 'RWGA_License_State', false ) ) {
			RWGA_License_State::log_debug( 'disconnect_before', RWGA_License_State::get_snapshot() );
		}
		self::reset_db_option_snapshots();
		$raw = self::get_stored_settings_from_db();
		$raw = is_array( $raw ) ? $raw : array();
		// Write empty key directly so merge/sanitize paths cannot leave a stale key in object cache.
		$raw['reactwoo_license_key']               = '';
		$raw['reactwoo_license_use_core_fallback'] = false;
		update_option( self::OPTION_KEY, $raw, true );
		update_option( self::OPTION_BLOCK_CORE_LICENSE_BRIDGE, 1, true );
		if ( class_exists( 'RWGA_License_State', false ) ) {
			RWGA_License_State::clear_all( 'disconnect' );
		} else {
			delete_option( 'rwga_assistant_usage_cache' );
		}
		wp_cache_delete( self::OPTION_KEY, 'options' );
		wp_cache_delete( self::OPTION_BLOCK_CORE_LICENSE_BRIDGE, 'options' );
		wp_cache_delete( 'rwga_assistant_usage_cache', 'options' );
		wp_cache_delete( 'rwga_license_state', 'options' );
		// Autoloaded options are often served from the `alloptions` cache; clear it so the next read sees the empty key immediately.
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		if ( function_exists( 'wp_cache_flush_group' ) && function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'options' );
		}
		self::reset_db_option_snapshots();
		if ( class_exists( 'RWGA_Platform_Client', false ) ) {
			RWGA_Platform_Client::clear_token_cache();
		}
		self::bust_plugin_update_check_cache();
	}

	/**
	 * Clear WordPress’s cached plugin update metadata so the next load re-calls the ReactWoo updates API.
	 *
	 * Without this, {@see RWGC_Satellite_Updater} may keep a stale “no update” result for many hours after CI publishes.
	 *
	 * @return void
	 */
	public static function bust_plugin_update_check_cache() {
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * @param mixed $old_value Previous option.
	 * @param mixed $value     New option.
	 * @return void
	 */
	public static function maybe_clear_jwt_on_change( $old_value, $value ) {
		$old = is_array( $old_value ) ? $old_value : array();
		$val = is_array( $value ) ? $value : array();
		$o_k = isset( $old['reactwoo_license_key'] ) ? (string) $old['reactwoo_license_key'] : '';
		$n_k = isset( $val['reactwoo_license_key'] ) ? (string) $val['reactwoo_license_key'] : '';
		$o_b = isset( $old['reactwoo_api_base'] ) ? (string) $old['reactwoo_api_base'] : '';
		$n_b = isset( $val['reactwoo_api_base'] ) ? (string) $val['reactwoo_api_base'] : '';
		if ( $o_k !== $n_k || $o_b !== $n_b ) {
			if ( class_exists( 'RWGA_Platform_Client', false ) ) {
				RWGA_Platform_Client::clear_token_cache();
			}
			self::bust_plugin_update_check_cache();
		}
	}

	/**
	 * After the license key value changes, refresh cached plan/tier from the API so Settings shows the right tier.
	 *
	 * @param mixed $old_value Previous option.
	 * @param mixed $value     New option.
	 * @return void
	 */
	public static function maybe_refresh_usage_after_license_change( $old_value, $value ) {
		$old = is_array( $old_value ) ? $old_value : array();
		$val = is_array( $value ) ? $value : array();
		$o_k = isset( $old['reactwoo_license_key'] ) ? trim( (string) $old['reactwoo_license_key'] ) : '';
		$n_k = isset( $val['reactwoo_license_key'] ) ? trim( (string) $val['reactwoo_license_key'] ) : '';
		if ( $o_k === $n_k || '' === $n_k ) {
			return;
		}
		if ( ! class_exists( 'RWGA_Platform_Client', false ) || ! class_exists( 'RWGA_Usage', false ) ) {
			return;
		}
		if ( class_exists( 'RWGA_License_State', false ) ) {
			RWGA_License_State::log_debug( 'license_key_change_refresh_before', RWGA_License_State::get_snapshot() );
		}
		RWGA_Platform_Client::clear_token_cache();
		$result = RWGA_Platform_Client::get_usage();
		if ( is_wp_error( $result ) ) {
			if ( class_exists( 'RWGA_License_State', false ) ) {
				RWGA_License_State::log_debug( 'license_key_change_refresh_error', array( 'message' => $result->get_error_message() ) );
			}
			return;
		}
		$http = isset( $result['http_code'] ) ? (int) $result['http_code'] : 0;
		$body = isset( $result['body'] ) ? $result['body'] : null;
		if ( is_array( $body ) ) {
			RWGA_Usage::save_from_api_body( $body, $http );
		}
		if ( class_exists( 'RWGA_License_State', false ) ) {
			RWGA_License_State::log_debug( 'license_key_change_refresh_after', RWGA_License_State::get_snapshot() );
		}
		if ( class_exists( 'RWGA_Site_Intelligence_Sync', false ) ) {
			RWGA_Site_Intelligence_Sync::maybe_retry_sync_when_ready();
		}
	}

	/**
	 * options.php redirects to wp_get_referer(), which can be wrong when multiple admin tabs share one option group.
	 * Send the user back to License vs Advanced based on the submitted form scope.
	 *
	 * @param string $location Redirect URL.
	 * @param int    $status   HTTP status.
	 * @return string
	 */
	public static function filter_options_save_redirect( $location, $status ) {
		unset( $status );
		if ( empty( $_POST['option_page'] ) || 'rwga_license_group' !== $_POST['option_page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $location;
		}
		$key   = self::OPTION_KEY;
		$scope = 'license';
		if ( isset( $_POST[ $key ]['rwga_form_scope'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$scope = sanitize_key( (string) wp_unslash( $_POST[ $key ]['rwga_form_scope'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		$page = 'advanced' === $scope ? 'rwga-advanced' : 'rwga-license';
		$url  = admin_url( 'admin.php?page=' . $page );
		return add_query_arg( 'settings-updated', 'true', $url );
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_manual_import_sources() {
		$sources = array();
		foreach ( self::get_manual_import_source_map() as $source => $cfg ) {
			$raw = get_option( $cfg['option_key'], array() );
			if ( is_array( $raw ) && ! empty( $raw['reactwoo_license_key'] ) ) {
				$sources[ $source ] = (string) $cfg['label'];
			}
		}
		return $sources;
	}

	/**
	 * @param string $source Source key.
	 * @return true|\WP_Error
	 */
	public static function import_license_from_source( $source ) {
		$map = self::get_manual_import_source_map();
		if ( ! isset( $map[ $source ] ) ) {
			return new WP_Error( 'rwga_bad_import_source', __( 'Unknown import source.', 'reactwoo-geo-ai' ) );
		}

		$raw = get_option( $map[ $source ]['option_key'], array() );
		if ( ! is_array( $raw ) || empty( $raw['reactwoo_license_key'] ) ) {
			return new WP_Error( 'rwga_import_missing_key', __( 'The selected source does not have a saved license key.', 'reactwoo-geo-ai' ) );
		}

		$settings                         = self::get_settings();
		$settings['reactwoo_license_key'] = sanitize_text_field( (string) $raw['reactwoo_license_key'] );
		if ( ! empty( $raw['reactwoo_api_base'] ) ) {
			$base = esc_url_raw( trim( (string) $raw['reactwoo_api_base'] ) );
			if ( $base && wp_http_validate_url( $base ) ) {
				$settings['reactwoo_api_base'] = untrailingslashit( $base );
			}
		}

		update_option( self::OPTION_KEY, self::sanitize_settings( $settings ) );
		if ( class_exists( 'RWGA_License_State', false ) ) {
			RWGA_License_State::clear_all( 'import_license' );
		} else {
			delete_option( 'rwga_assistant_usage_cache' );
		}
		delete_option( self::OPTION_BLOCK_CORE_LICENSE_BRIDGE );
		if ( class_exists( 'RWGA_Platform_Client', false ) ) {
			RWGA_Platform_Client::clear_token_cache();
		}
		return true;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private static function get_manual_import_source_map() {
		return array(
			'geo_optimise' => array(
				'label'      => __( 'Geo Optimise', 'reactwoo-geo-ai' ),
				'option_key' => 'rwgo_settings',
			),
			'geo_commerce' => array(
				'label'      => __( 'Geo Commerce', 'reactwoo-geo-ai' ),
				'option_key' => 'rwgcm_settings',
			),
			'geo_core_legacy' => array(
				'label'      => __( 'Geo Core (legacy)', 'reactwoo-geo-ai' ),
				'option_key' => 'rwgc_settings',
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		$stored   = get_option( self::OPTION_KEY, array() );
		$defaults = self::get_defaults();
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( $defaults, $stored );
	}

	/**
	 * @param array $input Raw.
	 * @return array<string, mixed>
	 */
	public static function sanitize_settings( $input ) {
		$defaults     = self::get_defaults();
		$settings     = is_array( $input ) ? $input : array();
		$prev         = self::get_stored_settings_from_db();
		$prev         = is_array( $prev ) ? $prev : array();
		$out          = array_merge( $defaults, $prev );
		$scope        = isset( $settings['rwga_form_scope'] ) ? sanitize_key( (string) $settings['rwga_form_scope'] ) : 'license';
		$prev_license = isset( $prev['reactwoo_license_key'] ) ? (string) $prev['reactwoo_license_key'] : '';

		if ( 'advanced' === $scope && self::can_edit_api_base_field() ) {
			$base = isset( $settings['reactwoo_api_base'] ) ? trim( (string) $settings['reactwoo_api_base'] ) : '';
			$base = esc_url_raw( $base );
			$out['reactwoo_api_base'] = ( $base && wp_http_validate_url( $base ) )
				? untrailingslashit( $base )
				: ( isset( $prev['reactwoo_api_base'] ) ? (string) $prev['reactwoo_api_base'] : $defaults['reactwoo_api_base'] );
		} else {
			if ( isset( $prev['reactwoo_api_base'] ) ) {
				$out['reactwoo_api_base'] = (string) $prev['reactwoo_api_base'];
			}
		}

		if ( 'advanced' === $scope && isset( $settings['workflow_engine'] ) ) {
			$allowed = array( 'local', 'remote', 'remote_fallback' );
			$w       = sanitize_key( (string) $settings['workflow_engine'] );
			$out['workflow_engine'] = in_array( $w, $allowed, true ) ? $w : ( isset( $prev['workflow_engine'] ) ? (string) $prev['workflow_engine'] : $defaults['workflow_engine'] );
		}

		if ( 'advanced' === $scope && isset( $settings['ux_analysis_focus'] ) ) {
			$allowed = array( 'messaging', 'layout', 'both' );
			$f       = sanitize_key( (string) $settings['ux_analysis_focus'] );
			$out['ux_analysis_focus'] = in_array( $f, $allowed, true ) ? $f : ( isset( $prev['ux_analysis_focus'] ) ? (string) $prev['ux_analysis_focus'] : $defaults['ux_analysis_focus'] );
		}
		if ( 'advanced' === $scope && isset( $settings['guided_mode_enabled'] ) ) {
			$out['guided_mode_enabled'] = (bool) $settings['guided_mode_enabled'];
		}

		$new_license = isset( $settings['reactwoo_license_key'] ) ? sanitize_text_field( (string) $settings['reactwoo_license_key'] ) : '';
		$bridge_on   = ( 1 === self::get_bridge_flag_from_db() );
		if ( 'license' === $scope || 'advanced' === $scope ) {
			$new_trim = trim( (string) $new_license );
			$prev_trim = trim( (string) $prev_license );
			/*
			 * Empty license field on options.php (License or Advanced) is “leave blank to keep”, NOT disconnect.
			 * Removing the key always goes through {@see clear_license_key()} (admin-post.php Disconnect), never this sanitizer.
			 *
			 * Empty submission: keep previous key only when there was a key to keep and we are not in explicit disconnect state
			 * (bridge flag set after Disconnect, or already disconnected).
			 */
			if ( '' === $new_trim ) {
				if ( $bridge_on || '' === $prev_trim ) {
					$out['reactwoo_license_key'] = '';
				} else {
					$out['reactwoo_license_key'] = $prev_license;
				}
			} else {
				$out['reactwoo_license_key'] = $new_license;
			}
			if ( '' !== trim( (string) $out['reactwoo_license_key'] ) ) {
				$out['reactwoo_license_use_core_fallback'] = true;
				delete_option( self::OPTION_BLOCK_CORE_LICENSE_BRIDGE );
			}
		}

		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_defaults() {
		return array(
			'reactwoo_api_base'                       => 'https://api.reactwoo.com',
			'reactwoo_license_key'                    => '',
			/** When true (default), empty Geo AI key may use Geo Core’s license. False after Disconnect. */
			'reactwoo_license_use_core_fallback'     => true,
			'workflow_engine'                        => 'local',
			'ux_analysis_focus'                      => 'messaging',
			'guided_mode_enabled'                    => true,
			'auto_site_audit_after_sync'             => true,
		);
	}
}
