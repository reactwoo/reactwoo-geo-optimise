<?php
/**
 * Geo Optimise — merged product hub (tabs shell for AI + experiments).
 *
 * Phase 3: AI tabs render embedded Geo AI (merged-geo-ai bundle).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tabbed Optimise admin hub (`admin.php?page=rwgo-optimise&tab=…`).
 */
class RWGO_Optimise_Hub {

	/**
	 * Primary hub page slug.
	 */
	const PAGE_SLUG = 'rwgo-optimise';

	/**
	 * Query arg for active tab.
	 */
	const TAB_QUERY = 'tab';

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function tab_definitions() {
		$tabs = array(
			'ai-review'       => array(
				'label'       => __( 'AI Review', 'reactwoo-geo-optimise' ),
				'description' => __( 'Chat-first review of pages, variants, and rules for conversion opportunities.', 'reactwoo-geo-optimise' ),
				'order'       => 10,
				'phase'       => 'ai',
			),
			'recommendations' => array(
				'label'       => __( 'Recommendations', 'reactwoo-geo-optimise' ),
				'description' => __( 'Prioritised findings queue with actions to draft, test, or dismiss.', 'reactwoo-geo-optimise' ),
				'order'       => 20,
				'phase'       => 'ai',
			),
			'drafts'          => array(
				'label'       => __( 'Drafts', 'reactwoo-geo-optimise' ),
				'description' => __( 'Approval-gated copy, SEO, and variant drafts from recommendations.', 'reactwoo-geo-optimise' ),
				'order'       => 30,
				'phase'       => 'ai',
			),
			'experiments'     => array(
				'label'       => __( 'Experiments', 'reactwoo-geo-optimise' ),
				'description' => __( 'Create and manage A/B tests, variants, and assignment.', 'reactwoo-geo-optimise' ),
				'order'       => 40,
				'phase'       => 'optimise',
			),
			'goals'           => array(
				'label'       => __( 'Goals', 'reactwoo-geo-optimise' ),
				'description' => __( 'Conversion goals, builder-defined markers, and REST events.', 'reactwoo-geo-optimise' ),
				'order'       => 50,
				'phase'       => 'optimise',
			),
			'reports'         => array(
				'label'       => __( 'Reports', 'reactwoo-geo-optimise' ),
				'description' => __( 'Experiment outcomes, variant performance, and winner promotion.', 'reactwoo-geo-optimise' ),
				'order'       => 60,
				'phase'       => 'optimise',
			),
			'history'         => array(
				'label'       => __( 'History', 'reactwoo-geo-optimise' ),
				'description' => __( 'AI review runs and experiment lifecycle in one timeline.', 'reactwoo-geo-optimise' ),
				'order'       => 70,
				'phase'       => 'merged',
			),
			'settings'        => array(
				'label'       => __( 'Settings', 'reactwoo-geo-optimise' ),
				'description' => __( 'Licence, measurement defaults, tracking tools, and developer options.', 'reactwoo-geo-optimise' ),
				'order'       => 80,
				'phase'       => 'optimise',
			),
		);

		/**
		 * Filter Optimise hub tab definitions.
		 *
		 * @param array<string, array<string, mixed>> $tabs Tab id => meta.
		 */
		return apply_filters( 'rwgo_optimise_hub_tabs', $tabs );
	}

	/**
	 * @return string Default tab id.
	 */
	public static function default_tab() {
		/**
		 * Default tab when `tab` query arg is missing.
		 *
		 * @param string $tab Tab id.
		 */
		return (string) apply_filters( 'rwgo_optimise_hub_default_tab', 'ai-review' );
	}

	/**
	 * @param string $tab Tab id.
	 * @return string
	 */
	public static function sanitize_tab( $tab ) {
		$tab  = sanitize_key( (string) $tab );
		$tabs = self::tab_definitions();
		if ( isset( $tabs[ $tab ] ) ) {
			return $tab;
		}
		return self::default_tab();
	}

	/**
	 * @return string
	 */
	public static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab navigation only.
		$raw = isset( $_GET[ self::TAB_QUERY ] ) ? wp_unslash( (string) $_GET[ self::TAB_QUERY ] ) : '';
		if ( '' === $raw ) {
			return self::default_tab();
		}
		return self::sanitize_tab( $raw );
	}

	/**
	 * @param string $tab     Tab id.
	 * @param array  $extra   Extra query args.
	 * @return string
	 */
	public static function tab_url( $tab, array $extra = array() ) {
		$tab = self::sanitize_tab( $tab );
		$args = array_merge(
			array(
				'page'        => self::PAGE_SLUG,
				self::TAB_QUERY => $tab,
			),
			$extra
		);
		return admin_url( 'admin.php?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) );
	}

	/**
	 * @return void
	 */
	public static function render_tab_nav( $current_tab ) {
		$current_tab = self::sanitize_tab( $current_tab );
		$tabs        = self::tab_definitions();
		uasort(
			$tabs,
			static function ( $a, $b ) {
				return (int) ( $a['order'] ?? 100 ) <=> (int) ( $b['order'] ?? 100 );
			}
		);

		echo '<nav class="rwgc-inner-nav rwgo-inner-nav rwgo-optimise-hub__tabs" aria-label="' . esc_attr__( 'Optimise section tabs', 'reactwoo-geo-optimise' ) . '">';
		foreach ( $tabs as $id => $meta ) {
			$label = isset( $meta['label'] ) ? (string) $meta['label'] : $id;
			$class = 'rwgc-inner-nav__link' . ( $id === $current_tab ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( self::tab_url( $id ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * @param string $tab Tab id.
	 * @return void
	 */
	public static function render_tab_content( $tab ) {
		$tab = self::sanitize_tab( $tab );
		switch ( $tab ) {
			case 'experiments':
				self::render_experiments_tab();
				break;
			case 'goals':
				include RWGO_PATH . 'admin/views/optimise/tab-goals.php';
				break;
			case 'reports':
				self::render_reports_tab();
				break;
			case 'settings':
				self::render_settings_tab();
				break;
			case 'history':
				if ( class_exists( 'RWGO_AI_Hub_Views', false ) ) {
					RWGO_AI_Hub_Views::render_tab( 'history' );
				} else {
					include RWGO_PATH . 'admin/views/optimise/tab-history.php';
				}
				break;
			case 'recommendations':
				if ( class_exists( 'RWGO_AI_Hub_Views', false ) ) {
					RWGO_AI_Hub_Views::render_tab( 'recommendations' );
				} else {
					self::render_ai_placeholder(
						'recommendations',
						__( 'Recommendations', 'reactwoo-geo-optimise' ),
						__( 'Prioritised findings with actions to create drafts, variants, or A/B tests will appear here after the Geo AI module is merged into Optimise.', 'reactwoo-geo-optimise' )
					);
				}
				break;
			case 'drafts':
				if ( class_exists( 'RWGO_AI_Hub_Views', false ) ) {
					RWGO_AI_Hub_Views::render_tab( 'drafts' );
				} else {
					self::render_ai_placeholder(
						'drafts',
						__( 'Drafts', 'reactwoo-geo-optimise' ),
						__( 'Approval-gated copy, SEO, and variant drafts will be managed here after the Geo AI module is merged into Optimise.', 'reactwoo-geo-optimise' )
					);
				}
				break;
			case 'ai-review':
			default:
				if ( class_exists( 'RWGO_AI_Hub_Views', false ) ) {
					RWGO_AI_Hub_Views::render_tab( 'ai-review' );
				} else {
					self::render_ai_placeholder(
						'ai-review',
						__( 'AI Review', 'reactwoo-geo-optimise' ),
						__( 'What would you like to optimise? The chat-first AI review workspace (scoped audits, recommendations, and handoffs to experiments) will load here after the Geo AI module is merged into Optimise.', 'reactwoo-geo-optimise' )
					);
				}
				break;
		}
	}

	/**
	 * @param string $tab_id  Tab slug for styling.
	 * @param string $title   Panel title.
	 * @param string $body    Lead copy.
	 * @return void
	 */
	public static function render_ai_placeholder( $tab_id, $title, $body ) {
		$rwgo_optimise_placeholder_tab = sanitize_key( (string) $tab_id );
		$rwgo_optimise_placeholder_title = (string) $title;
		$rwgo_optimise_placeholder_body  = (string) $body;
		include RWGO_PATH . 'admin/views/optimise/tab-ai-placeholder.php';
	}

	/**
	 * @return void
	 */
	private static function render_experiments_tab() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			return;
		}
		$data = RWGO_Admin::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current         = 'rwgo-optimise';
		$rwgo_optimise_hub_embed  = true;
		$rwgo_experiments         = class_exists( 'RWGO_Experiment_Repository', false )
			? RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 300 ) )
			: array();
		include RWGO_PATH . 'admin/views/tests-list.php';
	}

	/**
	 * @return void
	 */
	private static function render_reports_tab() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			return;
		}
		$data = RWGO_Admin::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current        = 'rwgo-optimise';
		$rwgo_optimise_hub_embed = true;
		$rwgo_experiments        = class_exists( 'RWGO_Experiment_Repository', false )
			? RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 300 ) )
			: array();
		include RWGO_PATH . 'admin/views/reports.php';
	}

	/**
	 * @return void
	 */
	private static function render_settings_tab() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			return;
		}
		include RWGO_PATH . 'admin/views/optimise/tab-settings.php';
	}
}
