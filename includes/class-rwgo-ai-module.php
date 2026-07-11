<?php
/**
 * Embedded Geo AI module loader (merged from reactwoo-geo-ai).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots Geo AI code inside Geo Optimise when standalone Geo AI is inactive.
 */
class RWGO_AI_Module {

	/**
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * @return void
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}

		if ( self::is_standalone_geo_ai_active() ) {
			add_action( 'admin_notices', array( __CLASS__, 'render_standalone_conflict_notice' ) );
			self::$booted = true;
			return;
		}

		if ( ! self::is_geo_core_active() ) {
			return;
		}

		self::define_constants();
		self::bootstrap_i18n();

		require_once RWGA_PATH . 'includes/class-rwga-plugin.php';
		RWGA_Plugin::instance()->boot_embedded();

		self::$booted = true;
	}

	/**
	 * @return bool
	 */
	public static function is_ready() {
		return class_exists( 'RWGA_UX_Reviewer_UI', false );
	}

	/**
	 * Merged AI is served from the Optimise hub (not standalone Geo AI menus).
	 *
	 * @return bool
	 */
	public static function uses_optimise_hub() {
		return self::is_ready() && ! self::is_standalone_geo_ai_active();
	}

	/**
	 * @return bool
	 */
	public static function is_standalone_geo_ai_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( 'reactwoo-geo-ai/reactwoo-geo-ai.php' );
	}

	/**
	 * @return bool
	 */
	public static function is_geo_core_active() {
		if ( function_exists( 'rwgc_is_geo_core_active' ) ) {
			return (bool) rwgc_is_geo_core_active();
		}
		return class_exists( 'RWGC_Plugin', false );
	}

	/**
	 * @return void
	 */
	private static function define_constants() {
		if ( ! defined( 'RWGO_AI_EMBEDDED' ) ) {
			define( 'RWGO_AI_EMBEDDED', true );
		}
		if ( ! defined( 'RWGA_VERSION' ) ) {
			define( 'RWGA_VERSION', '0.4.142' );
		}
		if ( ! defined( 'RWGA_FILE' ) ) {
			define( 'RWGA_FILE', RWGO_PATH . 'merged-geo-ai/module.php' );
		}
		if ( ! defined( 'RWGA_PATH' ) ) {
			define( 'RWGA_PATH', trailingslashit( RWGO_PATH . 'merged-geo-ai' ) );
		}
		if ( ! defined( 'RWGA_URL' ) ) {
			define( 'RWGA_URL', trailingslashit( RWGO_URL . 'merged-geo-ai' ) );
		}
	}

	/**
	 * @return void
	 */
	private static function bootstrap_i18n() {
		if ( class_exists( 'RWGC_I18n', false ) ) {
			RWGC_I18n::bootstrap( RWGA_FILE, 'reactwoo-geo-ai' );
		}
	}

	/**
	 * Ensure rwga_* tables exist when Optimise activates without standalone Geo AI.
	 *
	 * @return void
	 */
	public static function maybe_install_tables() {
		if ( self::is_standalone_geo_ai_active() ) {
			return;
		}
		self::define_constants();
		require_once RWGA_PATH . 'includes/class-rwga-install.php';
		RWGA_Install::activate();
	}

	/**
	 * @return void
	 */
	public static function render_standalone_conflict_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '';
		if ( 'rwgo-optimise' !== $page && 0 !== strpos( $page, 'rwgo-' ) ) {
			return;
		}

		$deactivate = wp_nonce_url(
			admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( 'reactwoo-geo-ai/reactwoo-geo-ai.php' ) ),
			'deactivate-plugin_reactwoo-geo-ai/reactwoo-geo-ai.php'
		);

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Geo AI is now included in Geo Optimise. Deactivate the standalone ReactWoo Geo AI plugin to avoid duplicate hooks.', 'reactwoo-geo-optimise' );
		echo ' <a class="button button-primary" href="' . esc_url( $deactivate ) . '">' . esc_html__( 'Deactivate Geo AI', 'reactwoo-geo-optimise' ) . '</a>';
		echo '</p></div>';
	}
}
