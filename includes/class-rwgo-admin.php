<?php
/**
 * Geo Optimise — wp-admin (top-level menu; summary on Geo Core dashboard).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for ReactWoo Geo Optimise.
 */
class RWGO_Admin {

	/**
	 * Parent admin page slug.
	 */
	const MENU_PARENT = 'rwgo-dashboard';

	/**
	 * Tracking Tools — GTM / GA4 / dataLayer (operator + agency).
	 *
	 * @return string
	 */
	public static function tracking_tools_url() {
		return admin_url( 'admin.php?page=rwgo-tracking-tools' );
	}

	/**
	 * Developer, diagnostics, support tabs.
	 *
	 * @param string $tab developer|diagnostics|support.
	 * @return string
	 */
	public static function developer_url( $tab = 'developer' ) {
		$tab = sanitize_key( (string) $tab );
		$ok  = array( 'developer', 'diagnostics', 'support' );
		if ( ! in_array( $tab, $ok, true ) ) {
			$tab = 'developer';
		}
		return admin_url( 'admin.php?page=rwgo-developer&rwgo_tab=' . $tab );
	}

	/**
	 * Back-compat: maps old tab ids to the split screens.
	 *
	 * @param string $tab tracking|developer|diagnostics|support.
	 * @return string
	 */
	public static function tools_url( $tab = 'tracking' ) {
		$tab = sanitize_key( (string) $tab );
		if ( 'tracking' === $tab ) {
			return self::tracking_tools_url();
		}
		return self::developer_url( $tab );
	}

	/**
	 * Edit Test screen (managed experiment post).
	 *
	 * @param int $experiment_id Experiment CPT post ID.
	 * @return string
	 */
	public static function edit_test_url( $experiment_id ) {
		return admin_url( 'admin.php?page=rwgo-edit-test&rwgo_experiment_id=' . absint( $experiment_id ) );
	}

	/**
	 * Primitive capability for add_menu_page / add_submenu_page (matches Geo Elementor: admins + WooCommerce shop managers).
	 *
	 * @return string
	 */
	public static function required_capability() {
		$default_cap = 'manage_options';
		if ( ! current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' ) ) {
			$default_cap = 'manage_woocommerce';
		}
		$capability = apply_filters( 'rwgo_required_capability', $default_cap );
		if ( ! is_string( $capability ) || '' === $capability ) {
			$capability = $default_cap;
		}
		if ( ! current_user_can( $capability ) && current_user_can( 'manage_options' ) ) {
			$capability = 'manage_options';
		}
		return $capability;
	}

	/**
	 * Whether the current user may use Geo Optimise wp-admin screens and actions.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::required_capability() );
	}

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_legacy_tools' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 26 );
		add_action( 'admin_menu', array( __CLASS__, 'hide_edit_test_submenu' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_license_actions' ) );
		add_action( 'admin_post_rwgo_test_license', array( __CLASS__, 'handle_test_license' ) );
		add_action( 'admin_post_rwgo_reset_counts', array( __CLASS__, 'handle_reset_counts' ) );
		add_action( 'admin_post_rwgo_export_stats', array( __CLASS__, 'handle_export_stats' ) );
		add_action( 'admin_post_rwgo_pause_test', array( __CLASS__, 'handle_pause_test' ) );
		add_action( 'admin_post_rwgo_resume_test', array( __CLASS__, 'handle_resume_test' ) );
		add_action( 'admin_post_rwgo_end_test', array( __CLASS__, 'handle_end_test' ) );
		add_action( 'admin_post_rwgo_duplicate_test', array( __CLASS__, 'handle_duplicate_test' ) );
		add_action( 'rwgc_dashboard_satellite_panels', array( __CLASS__, 'render_geo_core_summary_card' ) );
	}

	/**
	 * Snapshot + derived rows for views.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_view_data() {
		$snapshot = class_exists( 'RWGO_Stats', false ) ? RWGO_Stats::get_snapshot() : array();
		$geo_events = isset( $snapshot['geo_event_count'] ) ? (int) $snapshot['geo_event_count'] : 0;
		$route_hits = isset( $snapshot['route_resolved_count'] ) ? (int) $snapshot['route_resolved_count'] : 0;
		$assign_n   = isset( $snapshot['assignment_count'] ) ? (int) $snapshot['assignment_count'] : 0;
		$exp_dist   = isset( $snapshot['experiment_variant_counts'] ) && is_array( $snapshot['experiment_variant_counts'] ) ? $snapshot['experiment_variant_counts'] : array();
		$csv_export_count    = isset( $snapshot['csv_export_count'] ) ? (int) $snapshot['csv_export_count'] : 0;
		$last_csv_export_gmt = isset( $snapshot['last_csv_export_gmt'] ) ? (string) $snapshot['last_csv_export_gmt'] : '';
		$assign_per_route    = isset( $snapshot['assignment_per_route_resolved'] ) ? $snapshot['assignment_per_route_resolved'] : '';
		$capabilities_url    = function_exists( 'rwgc_get_rest_capabilities_url' ) ? rwgc_get_rest_capabilities_url() : '';

		$assignment_rows = array();
		foreach ( $exp_dist as $exp_key => $variants ) {
			if ( ! is_string( $exp_key ) || ! is_array( $variants ) ) {
				continue;
			}
			foreach ( $variants as $vk => $cnt ) {
				if ( ! is_string( $vk ) && ! is_numeric( $vk ) ) {
					continue;
				}
				$assignment_rows[] = array(
					'exp'   => $exp_key,
					'var'   => (string) $vk,
					'count' => (int) $cnt,
				);
			}
		}
		usort(
			$assignment_rows,
			static function ( $a, $b ) {
				$c = strcmp( $a['exp'], $b['exp'] );
				return 0 !== $c ? $c : strcmp( $a['var'], $b['var'] );
			}
		);

		$active_experiment_count   = count( array_keys( $exp_dist ) );
		$total_variant_assignments = 0;
		foreach ( $assignment_rows as $r ) {
			$total_variant_assignments += (int) $r['count'];
		}

		$managed_tests_total     = class_exists( 'RWGO_Experiment_Repository', false ) ? RWGO_Experiment_Repository::count_all() : 0;
		$active_managed_tests      = class_exists( 'RWGO_Experiment_Repository', false ) ? RWGO_Experiment_Repository::count_by_status( 'active' ) : 0;
		$goal_events_total         = class_exists( 'RWGO_Event_Store', false ) ? RWGO_Event_Store::count_total() : 0;

		return array(
			'snapshot'                  => $snapshot,
			'geo_events'                => $geo_events,
			'route_hits'                => $route_hits,
			'assign_n'                  => $assign_n,
			'exp_dist'                  => $exp_dist,
			'assignment_rows'           => $assignment_rows,
			'csv_export_count'          => $csv_export_count,
			'last_csv_export_gmt'       => $last_csv_export_gmt,
			'assign_per_route'          => $assign_per_route,
			'capabilities_url'          => $capabilities_url,
			'active_experiment_count'   => $active_experiment_count,
			'total_variant_assignments' => $total_variant_assignments,
			'managed_tests_total'       => $managed_tests_total,
			'active_managed_tests'      => $active_managed_tests,
			'goal_events_total'         => $goal_events_total,
		);
	}

	/**
	 * Summary card on Geo Core dashboard.
	 *
	 * @return void
	 */
	public static function render_geo_core_summary_card() {
		if ( ! self::can_manage() ) {
			return;
		}
		$data = self::get_view_data();
		$url  = admin_url( 'admin.php?page=' . self::MENU_PARENT );
		$nexp = isset( $data['active_managed_tests'] ) ? (int) $data['active_managed_tests'] : 0;
		$asg  = isset( $data['total_variant_assignments'] ) ? (int) $data['total_variant_assignments'] : 0;
		?>
		<div class="rwgc-addon-card">
			<div class="rwgc-addon-card__header">
				<div class="rwgc-addon-card__icon" aria-hidden="true"><span class="dashicons dashicons-chart-area"></span></div>
				<div class="rwgc-addon-card__heading">
					<h3><?php esc_html_e( 'Geo Optimise', 'reactwoo-geo-optimise' ); ?></h3>
					<p><?php esc_html_e( 'Create page tests, review traffic split, and connect measurement — without editing PHP.', 'reactwoo-geo-optimise' ); ?></p>
				</div>
			</div>
			<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
			<div class="rwgc-addon-card__meta">
				<?php
				RWGC_Admin_UI::render_pill(
					sprintf(
						/* translators: %d: active tests */
						__( 'Active tests: %d', 'reactwoo-geo-optimise' ),
						$nexp
					),
					'neutral'
				);
				RWGC_Admin_UI::render_pill(
					sprintf(
						/* translators: %d: assignment count */
						__( 'Visitors assigned: %d', 'reactwoo-geo-optimise' ),
						$asg
					),
					'neutral'
				);
				?>
			</div>
			<?php endif; ?>
			<div class="rwgc-addon-card__actions">
				<a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Open Geo Optimise', 'reactwoo-geo-optimise' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-license' ) ); ?>" class="button"><?php esc_html_e( 'License', 'reactwoo-geo-optimise' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-reports' ) ); ?>" class="button"><?php esc_html_e( 'Reports', 'reactwoo-geo-optimise' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * @param string $current Current page slug.
	 * @return void
	 */
	public static function render_inner_nav( $current ) {
		$items = array(
			self::MENU_PARENT     => __( 'Dashboard', 'reactwoo-geo-optimise' ),
			'rwgo-create-test'    => __( 'Create Test', 'reactwoo-geo-optimise' ),
			'rwgo-tests'          => __( 'Tests', 'reactwoo-geo-optimise' ),
			'rwgo-reports'        => __( 'Reports', 'reactwoo-geo-optimise' ),
			'rwgo-tracking-tools' => __( 'Tracking Tools', 'reactwoo-geo-optimise' ),
			'rwgo-developer'      => __( 'Developer', 'reactwoo-geo-optimise' ),
			'rwgo-help'           => __( 'Help', 'reactwoo-geo-optimise' ),
			'rwgo-settings'       => __( 'Settings', 'reactwoo-geo-optimise' ),
			'rwgo-license'        => __( 'License', 'reactwoo-geo-optimise' ),
		);
		echo '<nav class="rwgc-inner-nav rwgo-inner-nav" aria-label="' . esc_attr__( 'Geo Optimise section navigation', 'reactwoo-geo-optimise' ) . '">';
		foreach ( $items as $slug => $label ) {
			$class = 'rwgc-inner-nav__link' . ( $slug === $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Context banner when opened from Geo Suite workflow (GET handoff params from Geo Core).
	 *
	 * @return void
	 */
	public static function render_suite_handoff_panel() {
		if ( ! self::can_manage() ) {
			return;
		}
		if ( ! function_exists( 'rwgc_get_suite_handoff_request_context' ) ) {
			return;
		}
		$ctx = rwgc_get_suite_handoff_request_context();
		if ( empty( $ctx['active'] ) ) {
			return;
		}
		$clean_url = remove_query_arg( array( 'rwgc_handoff', 'rwgc_from', 'rwgc_launcher', 'rwgc_variant_page_id' ) );
		$launcher_labels = array(
			'experiment'     => __( 'Geo split test', 'reactwoo-geo-optimise' ),
			'ai_draft'       => __( 'AI draft', 'reactwoo-geo-optimise' ),
			'create_variant' => __( 'Create page version', 'reactwoo-geo-optimise' ),
			'commerce_rule'  => __( 'Commerce rule', 'reactwoo-geo-optimise' ),
		);
		$launcher = isset( $ctx['launcher'] ) ? (string) $ctx['launcher'] : '';
		$launcher_note = '';
		if ( '' !== $launcher ) {
			$launcher_note = isset( $launcher_labels[ $launcher ] ) ? $launcher_labels[ $launcher ] : $launcher;
		}
		$vid = isset( $ctx['variant_page_id'] ) ? (int) $ctx['variant_page_id'] : 0;
		$page = null;
		if ( $vid > 0 ) {
			$p = get_post( $vid );
			if ( $p instanceof \WP_Post && 'page' === $p->post_type && current_user_can( 'edit_page', $p->ID ) ) {
				$page = $p;
			}
		}
		?>
		<div class="rwgc-card rwgc-card--highlight rwgo-suite-handoff" role="region" aria-label="<?php echo esc_attr__( 'Geo Suite handoff', 'reactwoo-geo-optimise' ); ?>">
			<h2><?php esc_html_e( 'Opened from Geo Suite', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="description">
				<?php
				if ( isset( $ctx['from'] ) && 'suite' === $ctx['from'] ) {
					esc_html_e( 'You arrived from Suite Home or Getting Started. Continue with Create Test, then open Reports.', 'reactwoo-geo-optimise' );
				} else {
					esc_html_e( 'Geo Core linked you here to continue your workflow.', 'reactwoo-geo-optimise' );
				}
				if ( '' !== $launcher_note ) {
					echo ' ';
					echo esc_html(
						sprintf(
							/* translators: %s: workflow label */
							__( 'Workflow: %s', 'reactwoo-geo-optimise' ),
							$launcher_note
						)
					);
				}
				?>
			</p>
			<?php if ( $page instanceof \WP_Post ) : ?>
				<p>
					<strong><?php echo esc_html( get_the_title( $page ) ); ?></strong>
					<?php
					$edit_url = get_edit_post_link( $page->ID, 'raw' );
					if ( is_string( $edit_url ) && '' !== $edit_url ) {
						echo ' ';
						echo '<a class="button button-secondary" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Open page in editor', 'reactwoo-geo-optimise' ) . '</a>';
					}
					?>
				</p>
				<p class="description"><?php esc_html_e( 'Publish a page test from Create Test to route visitors automatically. For custom PHP integrations, use Developer → Developer & code.', 'reactwoo-geo-optimise' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></a></p>
			<?php elseif ( $vid > 0 ) : ?>
				<p class="description"><?php esc_html_e( 'The linked page could not be loaded or you do not have permission to edit it.', 'reactwoo-geo-optimise' ); ?></p>
			<?php endif; ?>
			<p><a class="button-link" href="<?php echo esc_url( $clean_url ); ?>"><?php esc_html_e( 'Dismiss banner', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'rwgo-' ) === false && strpos( $hook, self::MENU_PARENT ) === false ) {
			return;
		}
		$deps = array();
		if ( defined( 'RWGC_URL' ) && defined( 'RWGC_VERSION' ) ) {
			wp_enqueue_style(
				'rwgc-admin',
				RWGC_URL . 'admin/css/admin.css',
				array(),
				RWGC_VERSION
			);
			$deps[] = 'rwgc-admin';
			wp_enqueue_style(
				'rwgc-suite',
				RWGC_URL . 'admin/css/rwgc-suite.css',
				array( 'rwgc-admin' ),
				RWGC_VERSION
			);
			$deps[] = 'rwgc-suite';
		}
		wp_enqueue_style(
			'rwgo-admin',
			RWGO_URL . 'admin/css/rwgo-admin.css',
			$deps,
			RWGO_VERSION
		);
	}

	/**
	 * @return void
	 */
	public static function register_menu() {
		$cap = self::required_capability();

		add_menu_page(
			__( 'Geo Optimise', 'reactwoo-geo-optimise' ),
			__( 'Geo Optimise', 'reactwoo-geo-optimise' ),
			$cap,
			self::MENU_PARENT,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-chart-line',
			59
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Dashboard', 'reactwoo-geo-optimise' ),
			__( 'Dashboard', 'reactwoo-geo-optimise' ),
			$cap,
			self::MENU_PARENT,
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Create Test', 'reactwoo-geo-optimise' ),
			__( 'Create Test', 'reactwoo-geo-optimise' ),
			$cap,
			'rwgo-create-test',
			array( __CLASS__, 'render_create_test' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Tests', 'reactwoo-geo-optimise' ),
			__( 'Tests', 'reactwoo-geo-optimise' ),
			$cap,
			'rwgo-tests',
			array( __CLASS__, 'render_tests' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Reports', 'reactwoo-geo-optimise' ),
			__( 'Reports', 'reactwoo-geo-optimise' ),
			$cap,
			'rwgo-reports',
			array( __CLASS__, 'render_reports' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Tracking Tools', 'reactwoo-geo-optimise' ),
			__( 'Tracking Tools', 'reactwoo-geo-optimise' ),
			$cap,
			'rwgo-tracking-tools',
			array( __CLASS__, 'render_tracking_tools' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Developer', 'reactwoo-geo-optimise' ),
			__( 'Developer', 'reactwoo-geo-optimise' ),
			$cap,
			'rwgo-developer',
			array( __CLASS__, 'render_developer' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Optimise — Help', 'reactwoo-geo-optimise' ),
			__( 'Help', 'reactwoo-geo-optimise' ),
			$cap,
			'rwgo-help',
			array( __CLASS__, 'render_help' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Optimise — Settings', 'reactwoo-geo-optimise' ),
			__( 'Settings', 'reactwoo-geo-optimise' ),
			$cap,
			'rwgo-settings',
			array( __CLASS__, 'render_settings' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Optimise — License', 'reactwoo-geo-optimise' ),
			__( 'License', 'reactwoo-geo-optimise' ),
			$cap,
			'rwgo-license',
			array( __CLASS__, 'render_license' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Edit Test', 'reactwoo-geo-optimise' ),
			' ',
			$cap,
			'rwgo-edit-test',
			array( __CLASS__, 'render_edit_test' )
		);
	}

	/**
	 * Hide Edit Test from the left admin submenu (reachable via Tests list and direct URL).
	 *
	 * @return void
	 */
	public static function hide_edit_test_submenu() {
		remove_submenu_page( self::MENU_PARENT, 'rwgo-edit-test' );
	}

	/**
	 * Try JWT login against ReactWoo API (validates stored license key).
	 *
	 * @return void
	 */
	public static function handle_test_license() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_test_license' );
		if ( class_exists( 'RWGC_Platform_Client', false ) ) {
			RWGC_Platform_Client::clear_token_cache();
			$tok = RWGC_Platform_Client::get_access_token();
			if ( is_wp_error( $tok ) ) {
				update_option( 'rwgo_license_last_check', array( 'ok' => false, 'time' => gmdate( 'c' ), 'error' => $tok->get_error_message() ), false );
				wp_safe_redirect( admin_url( 'admin.php?page=rwgo-license&rwgo_license_test=0' ) );
				exit;
			}
			update_option( 'rwgo_license_last_check', array( 'ok' => true, 'time' => gmdate( 'c' ) ), false );
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-license&rwgo_license_test=1' ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rwgo-license&rwgo_license_test=na' ) );
		exit;
	}

	/**
	 * License screen GET actions (disconnect).
	 *
	 * @return void
	 */
	public static function handle_license_actions() {
		if ( ! is_admin() || ! self::can_manage() ) {
			return;
		}
		if ( empty( $_GET['page'] ) || 'rwgo-license' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( empty( $_GET['rwgo_action'] ) || 'clear_license' !== $_GET['rwgo_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'rwgo_clear_license' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( class_exists( 'RWGO_Settings', false ) ) {
			RWGO_Settings::clear_license_key();
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rwgo-license&rwgo_disconnected=1' ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function render_settings() {
		if ( ! self::can_manage() ) {
			return;
		}
		$rwgc_nav_current = 'rwgo-settings';
		include RWGO_PATH . 'admin/views/settings-optimisation.php';
	}

	/**
	 * @return void
	 */
	public static function render_license() {
		if ( ! self::can_manage() ) {
			return;
		}
		$rwgc_nav_current = 'rwgo-license';
		include RWGO_PATH . 'admin/views/license-settings.php';
	}

	/**
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! self::can_manage() ) {
			return;
		}
		$data = self::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current = self::MENU_PARENT;
		include RWGO_PATH . 'admin/views/overview.php';
	}

	/**
	 * @return void
	 */
	public static function render_create_test() {
		if ( ! self::can_manage() ) {
			return;
		}
		$data = self::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current = 'rwgo-create-test';
		include RWGO_PATH . 'admin/views/wizard-create-test.php';
	}

	/**
	 * Edit Test — update settings for a managed experiment.
	 *
	 * @return void
	 */
	public static function render_edit_test() {
		if ( ! self::can_manage() ) {
			return;
		}
		$data = self::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current = 'rwgo-tests';
		include RWGO_PATH . 'admin/views/edit-test.php';
	}

	/**
	 * @return void
	 */
	public static function render_tests() {
		if ( ! self::can_manage() ) {
			return;
		}
		$data = self::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current = 'rwgo-tests';
		$rwgo_experiments   = class_exists( 'RWGO_Experiment_Repository', false )
			? RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 300 ) )
			: array();
		include RWGO_PATH . 'admin/views/tests-list.php';
	}

	/**
	 * @return void
	 */
	public static function render_reports() {
		if ( ! self::can_manage() ) {
			return;
		}
		$data = self::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current = 'rwgo-reports';
		$rwgo_experiments = class_exists( 'RWGO_Experiment_Repository', false )
			? RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 300 ) )
			: array();
		include RWGO_PATH . 'admin/views/reports.php';
	}

	/**
	 * Legacy ?page=rwgo-tools links → split screens.
	 *
	 * @return void
	 */
	public static function maybe_redirect_legacy_tools() {
		if ( ! is_admin() || ! self::can_manage() ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- query arg only.
		if ( empty( $_GET['page'] ) || 'rwgo-tools' !== $_GET['page'] ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['rwgo_tab'] ) ? sanitize_key( wp_unslash( $_GET['rwgo_tab'] ) ) : 'tracking';
		if ( 'tracking' === $tab ) {
			wp_safe_redirect( self::tracking_tools_url() );
		} else {
			wp_safe_redirect( self::developer_url( $tab ) );
		}
		exit;
	}

	/**
	 * Tracking Tools — measurement snippets.
	 *
	 * @return void
	 */
	public static function render_tracking_tools() {
		if ( ! self::can_manage() ) {
			return;
		}
		$data = self::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current = 'rwgo-tracking-tools';
		$rwgo_experiments = class_exists( 'RWGO_Experiment_Repository', false )
			? RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 300 ) )
			: array();
		include RWGO_PATH . 'admin/views/tracking-tools.php';
	}

	/**
	 * Developer — code reference, diagnostics, support.
	 *
	 * @return void
	 */
	public static function render_developer() {
		if ( ! self::can_manage() ) {
			return;
		}
		$data = self::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current = 'rwgo-developer';
		$rwgo_experiments = class_exists( 'RWGO_Experiment_Repository', false )
			? RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 300 ) )
			: array();
		include RWGO_PATH . 'admin/views/developer.php';
	}

	/**
	 * @return void
	 */
	public static function render_help() {
		if ( ! self::can_manage() ) {
			return;
		}
		$rwgc_nav_current = 'rwgo-help';
		include RWGO_PATH . 'admin/views/help.php';
	}

	/**
	 * @return void
	 */
	public static function handle_reset_counts() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_reset_counts' );
		delete_option( 'rwgo_geo_event_count' );
		delete_option( 'rwgo_route_resolved_count' );
		delete_option( 'rwgo_assignment_count' );
		delete_option( 'rwgo_experiment_variant_counts' );
		wp_safe_redirect( add_query_arg( 'reset', '1', self::developer_url( 'diagnostics' ) ) );
		exit;
	}

	/**
	 * @return void
	 */
	public static function handle_pause_test() {
		self::mutate_test_status( 'paused', 'rwgo_pause_test' );
	}

	/**
	 * @return void
	 */
	public static function handle_resume_test() {
		self::mutate_test_status( 'active', 'rwgo_resume_test' );
	}

	/**
	 * @return void
	 */
	public static function handle_end_test() {
		self::mutate_test_status( 'completed', 'rwgo_end_test' );
	}

	/**
	 * @param string $status New status.
	 * @param string $nonce_action Nonce action.
	 * @return void
	 */
	private static function mutate_test_status( $status, $nonce_action ) {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( $nonce_action );
		$exp_id = isset( $_POST['rwgo_experiment_id'] ) ? (int) $_POST['rwgo_experiment_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $exp_id <= 0 || ! class_exists( 'RWGO_Experiment_Repository', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_error=1' ) );
			exit;
		}
		$post = get_post( $exp_id );
		if ( ! $post instanceof \WP_Post || RWGO_Experiment_CPT::POST_TYPE !== $post->post_type ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_error=1' ) );
			exit;
		}
		RWGO_Experiment_Repository::save_config( $exp_id, array( 'status' => $status ) );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_updated=1' ) );
		exit;
	}

	/**
	 * Clone experiment post and variant page; new draft-ready test.
	 *
	 * @return void
	 */
	public static function handle_duplicate_test() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_duplicate_test' );
		$exp_id = isset( $_POST['rwgo_experiment_id'] ) ? (int) $_POST['rwgo_experiment_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $exp_id <= 0 || ! class_exists( 'RWGO_Experiment_Repository', false ) || ! class_exists( 'RWGO_Page_Duplicator', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_error=1' ) );
			exit;
		}
		$src_post = get_post( $exp_id );
		if ( ! $src_post instanceof \WP_Post || RWGO_Experiment_CPT::POST_TYPE !== $src_post->post_type ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_error=1' ) );
			exit;
		}
		$cfg = RWGO_Experiment_Repository::get_config( $exp_id );
		$source = (int) ( $cfg['source_page_id'] ?? 0 );
		if ( $source <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_error=1' ) );
			exit;
		}
		$new_dup = RWGO_Page_Duplicator::duplicate( $source );
		if ( is_wp_error( $new_dup ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_error=dup' ) );
			exit;
		}
		$new_key = RWGO_Experiment_Service::generate_experiment_key( $source );
		$new_cfg = $cfg;
		$new_cfg['experiment_key']  = $new_key;
		$new_cfg['status']          = 'draft';
		$new_cfg['variants']        = RWGO_Experiment_Service::default_variants( $source, (int) $new_dup );
		$new_cfg['updated_gmt']     = gmdate( 'c' );
		$new_id = wp_insert_post(
			array(
				'post_type'   => RWGO_Experiment_CPT::POST_TYPE,
				'post_status' => 'draft',
				/* translators: %s: original title */
				'post_title'  => sprintf( __( '%s (copy)', 'reactwoo-geo-optimise' ), get_the_title( $src_post ) ),
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_error=save' ) );
			exit;
		}
		RWGO_Experiment_Repository::save_config( (int) $new_id, $new_cfg );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_duplicated=1' ) );
		exit;
	}

	/**
	 * Download CSV of current snapshot (UTF-8 BOM for Excel).
	 *
	 * @return void
	 */
	public static function handle_export_stats() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_export_stats' );
		if ( ! class_exists( 'RWGO_Stats', false ) ) {
			wp_die( esc_html__( 'Stats unavailable.', 'reactwoo-geo-optimise' ) );
		}
		$prev_exports = (int) get_option( 'rwgo_csv_export_count', 0 );
		update_option( 'rwgo_csv_export_count', $prev_exports + 1, false );
		update_option( 'rwgo_last_csv_export_gmt', gmdate( 'c' ), false );

		$snapshot = RWGO_Stats::get_snapshot();
		if ( ! is_array( $snapshot ) ) {
			$snapshot = array();
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			$host = 'site';
		} else {
			$host = sanitize_file_name( str_replace( ':', '-', $host ) );
		}
		$filename = 'geo-optimise-stats-' . $host . '-' . gmdate( 'Y-m-d' ) . '.csv';
		/**
		 * Filters the CSV export filename (Geo Optimise → Export CSV).
		 *
		 * @param string $filename Default filename (ASCII-safe).
		 * @param array  $snapshot Snapshot passed to flatten (same as dashboard).
		 */
		$filename = sanitize_file_name( basename( (string) apply_filters( 'rwgo_export_csv_filename', $filename, $snapshot ) ) );
		if ( '' === $filename ) {
			$filename = 'geo-optimise-stats-' . $host . '-' . gmdate( 'Y-m-d' ) . '.csv';
		}
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Could not write export.', 'reactwoo-geo-optimise' ) );
		}
		fprintf( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'key', 'value' ) );
		$flat = RWGO_Stats::flatten_for_csv( $snapshot );
		foreach ( $flat as $key => $cell ) {
			fputcsv( $out, array( $key, $cell ) );
		}
		fclose( $out );
		exit;
	}
}
