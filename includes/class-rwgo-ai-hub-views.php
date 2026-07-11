<?php
/**
 * Optimise hub — embedded Geo AI tab renderers.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders merged Geo AI screens inside the Optimise hub.
 */
class RWGO_AI_Hub_Views {

	/**
	 * @return bool
	 */
	public static function can_render() {
		return RWGO_AI_Module::is_ready() || ( RWGO_AI_Module::is_standalone_geo_ai_active() && class_exists( 'RWGA_UX_Reviewer_UI', false ) );
	}

	/**
	 * @param string $tab Hub tab id.
	 * @return void
	 */
	public static function render_tab( $tab ) {
		if ( ! self::can_render() ) {
			self::render_unavailable();
			return;
		}

		switch ( sanitize_key( (string) $tab ) ) {
			case 'recommendations':
				self::render_recommendations();
				break;
			case 'drafts':
				self::render_drafts();
				break;
			case 'history':
				self::render_history();
				break;
			case 'ai-review':
			default:
				self::render_ai_review();
				break;
		}
	}

	/**
	 * @return void
	 */
	private static function render_unavailable() {
		?>
		<div class="rwgo-panel rwgo-optimise-hub__placeholder">
			<h2 class="rwgo-section__title"><?php esc_html_e( 'AI module unavailable', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="rwgo-section__lead"><?php esc_html_e( 'The embedded Geo AI bundle could not be loaded. Ensure Geo Core is active and merged-geo-ai is present in this install.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	public static function render_ai_review() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page_id    = isset( $_GET['page_id'] ) ? (int) $_GET['page_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$product_id = isset( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$variant_id = isset( $_GET['variant_page_id'] ) ? (int) $_GET['variant_page_id'] : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rule_id    = isset( $_GET['rule_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['rule_id'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source     = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( (string) $_GET['source'] ) ) : 'optimise_hub';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$engine     = isset( $_GET['rwga_engine'] ) ? sanitize_key( wp_unslash( (string) $_GET['rwga_engine'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$actions    = isset( $_GET['rwga_actions'] ) ? (int) $_GET['rwga_actions'] : 0;

		$capabilities = function_exists( 'rwgc_get_suite_capability_map' )
			? rwgc_get_suite_capability_map()
			: array();

		$cards = array();
		$uid   = get_current_user_id();
		if ( $uid > 0 ) {
			$cached = get_transient( 'rwga_ux_review_' . $uid );
			if ( is_array( $cached ) ) {
				$cards = $cached;
			}
		}

		RWGA_UX_Reviewer_UI::render_workspace(
			array(
				'source'           => $source,
				'page_id'          => $page_id,
				'product_id'       => $product_id,
				'variant_page_id'  => $variant_id,
				'rule_id'          => $rule_id,
				'engine_source'    => $engine,
				'action_count'     => $actions,
				'capabilities'     => $capabilities,
				'cards'            => $cards,
				'show_inner_nav'   => false,
				'embed'            => true,
				'wrap_class'       => 'rwgo-optimise-hub__ai-review',
				'form_action_page' => RWGO_Optimise_Hub::PAGE_SLUG,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function render_recommendations() {
		if ( ! class_exists( 'RWGA_Admin', false ) ) {
			self::render_unavailable();
			return;
		}
		$rwgo_optimise_hub_embed = true;
		RWGA_Admin::render_intelligence_actions();
	}

	/**
	 * @return void
	 */
	public static function render_drafts() {
		if ( ! class_exists( 'RWGA_Admin', false ) ) {
			self::render_unavailable();
			return;
		}
		$rwgo_optimise_hub_embed = true;
		RWGA_Admin::render_implementation_drafts();
	}

	/**
	 * @return void
	 */
	public static function render_history() {
		if ( ! class_exists( 'RWGA_Admin', false ) ) {
			self::render_unavailable();
			return;
		}

		if ( class_exists( 'RWGO_Optimise_History', false ) ) {
			$rwgo_history_timeline = RWGO_Optimise_History::build_timeline( 25 );
			$timeline_path         = RWGO_PATH . 'admin/views/optimise/history-timeline.php';
			if ( is_readable( $timeline_path ) ) {
				echo '<div class="rwgo-optimise-hub__embed rwgo-optimise-hub__embed--history-timeline">';
				include $timeline_path;
				echo '</div>';
			}
		}

		$rwgo_optimise_hub_embed = true;
		RWGA_Admin::render_analyses();
	}
}
