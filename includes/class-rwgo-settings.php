<?php
/**
 * ReactWoo API URL + product license for Geo Optimise (commercial satellite — not stored in Geo Core).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings for ReactWoo Geo Optimise.
 */
class RWGO_Settings {

	const OPTION_KEY = 'rwgo_settings';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'update_option_' . self::OPTION_KEY, array( __CLASS__, 'maybe_clear_jwt_on_change' ), 10, 2 );
		// options.php defaults to manage_options; allow same caps as RWGO_Admin::required_capability() (e.g. shop managers).
		add_filter( 'option_page_capability_rwgo_license_group', array( __CLASS__, 'filter_option_page_capability' ) );
	}

	/**
	 * Settings API / options.php capability for rwgo_license_group (must match add_menu_page cap).
	 *
	 * @param string $cap Default manage_options.
	 * @return string
	 */
	public static function filter_option_page_capability( $cap ) {
		if ( class_exists( 'RWGO_Admin', false ) ) {
			return RWGO_Admin::required_capability();
		}
		return $cap;
	}

	/**
	 * Legacy no-op: Geo Optimise now owns its own platform client and does not register shared license filters.
	 *
	 * @return void
	 */
	public static function register_platform_filters() {
		return;
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
		$cap = 'manage_options';
		if ( class_exists( 'RWGO_Admin', false ) ) {
			$cap = RWGO_Admin::required_capability();
		}
		register_setting(
			'rwgo_license_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
				'capability'        => $cap,
			)
		);
	}

	/**
	 * @param string $key Default from Core.
	 * @return string
	 */
	public static function filter_license_key( $key ) {
		$s = self::get_settings();
		if ( is_array( $s ) && isset( $s['reactwoo_license_key'] ) ) {
			$k = trim( (string) $s['reactwoo_license_key'] );
			if ( '' !== $k ) {
				return $k;
			}
		}
		if ( class_exists( 'RWGA_Settings', false ) ) {
			$ai = get_option( RWGA_Settings::OPTION_KEY, array() );
			if ( is_array( $ai ) && ! empty( $ai['reactwoo_license_key'] ) ) {
				return trim( (string) $ai['reactwoo_license_key'] );
			}
		}
		if ( class_exists( 'RWGC_Settings', false ) ) {
			$raw = get_option( RWGC_Settings::OPTION_KEY, array() );
			if ( is_array( $raw ) && ! empty( $raw['reactwoo_license_key'] ) ) {
				return trim( (string) $raw['reactwoo_license_key'] );
			}
		}
		return (string) $key;
	}

	/**
	 * @param string $base Default URL.
	 * @return string
	 */
	public static function filter_api_base( $base ) {
		if ( defined( 'RWGO_REACTWOO_API_BASE' ) && is_string( RWGO_REACTWOO_API_BASE ) ) {
			$c = trim( (string) RWGO_REACTWOO_API_BASE );
			if ( '' !== $c && wp_http_validate_url( $c ) ) {
				return untrailingslashit( esc_url_raw( $c ) );
			}
		}
		$via_filter = apply_filters( 'rwgo_reactwoo_api_base', null );
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
		if ( class_exists( 'RWGA_Settings', false ) ) {
			$ai = get_option( RWGA_Settings::OPTION_KEY, array() );
			if ( is_array( $ai ) && ! empty( $ai['reactwoo_api_base'] ) ) {
				$u = esc_url_raw( trim( (string) $ai['reactwoo_api_base'] ) );
				if ( $u && wp_http_validate_url( $u ) ) {
					return untrailingslashit( $u );
				}
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
	 * @return void
	 */
	public static function clear_license_key() {
		$s                           = self::get_settings();
		$s['reactwoo_license_key'] = '';
		update_option( self::OPTION_KEY, $s );
		if ( class_exists( 'RWGO_Platform_Client', false ) ) {
			RWGO_Platform_Client::clear_token_cache();
		}
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
			if ( class_exists( 'RWGO_Platform_Client', false ) ) {
				RWGO_Platform_Client::clear_token_cache();
			}
		}
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
			return new WP_Error( 'rwgo_bad_import_source', __( 'Unknown import source.', 'reactwoo-geo-optimise' ) );
		}

		$raw = get_option( $map[ $source ]['option_key'], array() );
		if ( ! is_array( $raw ) || empty( $raw['reactwoo_license_key'] ) ) {
			return new WP_Error( 'rwgo_import_missing_key', __( 'The selected source does not have a saved license key.', 'reactwoo-geo-optimise' ) );
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
		if ( class_exists( 'RWGO_Platform_Client', false ) ) {
			RWGO_Platform_Client::clear_token_cache();
		}
		delete_option( 'rwgo_license_last_check' );
		return true;
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private static function get_manual_import_source_map() {
		return array(
			'geo_ai' => array(
				'label'      => __( 'Geo AI', 'reactwoo-geo-optimise' ),
				'option_key' => 'rwga_settings',
			),
			'geo_commerce' => array(
				'label'      => __( 'Geo Commerce', 'reactwoo-geo-optimise' ),
				'option_key' => 'rwgcm_settings',
			),
			'geo_core_legacy' => array(
				'label'      => __( 'Geo Core (legacy)', 'reactwoo-geo-optimise' ),
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
		$prev         = get_option( self::OPTION_KEY, array() );
		$prev         = is_array( $prev ) ? $prev : array();
		$out          = array_merge( $defaults, $prev );
		$scope        = isset( $settings['rwgo_form_scope'] ) ? sanitize_key( (string) $settings['rwgo_form_scope'] ) : 'license';
		$prev_license = isset( $prev['reactwoo_license_key'] ) ? (string) $prev['reactwoo_license_key'] : '';

		if ( isset( $prev['reactwoo_api_base'] ) ) {
			$out['reactwoo_api_base'] = (string) $prev['reactwoo_api_base'];
		}

		$new_license = isset( $settings['reactwoo_license_key'] ) ? sanitize_text_field( (string) $settings['reactwoo_license_key'] ) : '';
		if ( 'license' === $scope ) {
			$out['reactwoo_license_key'] = ( '' !== $new_license ) ? $new_license : $prev_license;
		}

		if ( 'optimisation' === $scope ) {
			$modes = array( 'recommended', 'page_builder', 'flexible', 'manual' );
			$bm    = isset( $settings['builder_mode'] ) ? sanitize_key( (string) $settings['builder_mode'] ) : 'recommended';
			$out['builder_mode']               = in_array( $bm, $modes, true ) ? $bm : 'recommended';
			$out['mixed_site_support']         = ! empty( $settings['mixed_site_support'] );
			$out['enable_woocommerce_goal_hooks'] = ! empty( $settings['enable_woocommerce_goal_hooks'] );
			$out['enable_dom_fallback']        = ! empty( $settings['enable_dom_fallback'] );
			$out['require_goal_confirm_publish'] = ! empty( $settings['require_goal_confirm_publish'] );
			$out['strict_binding_mode']        = ! empty( $settings['strict_binding_mode'] );
		}

		return $out;
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_defaults() {
		return array(
			'reactwoo_api_base'               => 'https://api.reactwoo.com',
			'reactwoo_license_key'            => '',
			'builder_mode'                    => 'recommended',
			'mixed_site_support'              => false,
			'enable_woocommerce_goal_hooks'   => true,
			'enable_dom_fallback'             => true,
			'require_goal_confirm_publish'    => false,
			'strict_binding_mode'             => false,
		);
	}

	/**
	 * Site builder / detection mode (user-facing presets map here).
	 *
	 * @return string recommended|page_builder|flexible|manual
	 */
	public static function get_builder_mode() {
		$s = self::get_settings();
		$m = isset( $s['builder_mode'] ) ? sanitize_key( (string) $s['builder_mode'] ) : 'recommended';
		$ok = array( 'recommended', 'page_builder', 'flexible', 'manual' );
		return in_array( $m, $ok, true ) ? $m : 'recommended';
	}

	/**
	 * Fire WooCommerce server-side goal hooks (add to cart, checkout, thank you).
	 *
	 * @return bool
	 */
	public static function woocommerce_goal_hooks_enabled() {
		$s = self::get_settings();
		if ( ! array_key_exists( 'enable_woocommerce_goal_hooks', $s ) ) {
			return true;
		}
		return (bool) $s['enable_woocommerce_goal_hooks'];
	}

	/**
	 * Prefer DOM / rendered scan when builder metadata is thin.
	 *
	 * @return bool
	 */
	public static function dom_fallback_enabled() {
		$s = self::get_settings();
		if ( ! array_key_exists( 'enable_dom_fallback', $s ) ) {
			return true;
		}
		return (bool) $s['enable_dom_fallback'];
	}

	/**
	 * Extra checkbox on Create Test before Publish is allowed.
	 *
	 * @return bool
	 */
	public static function require_goal_confirm_publish() {
		$s = self::get_settings();
		return ! empty( $s['require_goal_confirm_publish'] );
	}

	/**
	 * Front-end tracking prefers explicit fingerprints / selectors over loose matches.
	 *
	 * @return bool
	 */
	public static function strict_binding_mode_enabled() {
		$s = self::get_settings();
		return ! empty( $s['strict_binding_mode'] );
	}
}
