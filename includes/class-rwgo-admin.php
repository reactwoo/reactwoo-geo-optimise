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
	 * Product Help screen (optional URL fragment for in-page anchors).
	 *
	 * @param string $fragment Hash target without leading #, e.g. rwgo-help-builder-goals.
	 * @return string
	 */
	public static function help_url( $fragment = '' ) {
		$url = admin_url( 'admin.php?page=rwgo-help' );
		$fragment = is_string( $fragment ) ? trim( $fragment ) : '';
		if ( '' !== $fragment ) {
			$url .= '#' . ltrim( $fragment, '#' );
		}
		return $url;
	}

	/**
	 * Edit Test screen (managed experiment post).
	 *
	 * @param int    $experiment_id  Experiment CPT post ID.
	 * @param string $return_context Optional: tests|reports — preserved as ?rwgo_return= for action redirects.
	 * @return string
	 */
	public static function edit_test_url( $experiment_id, $return_context = '' ) {
		$url             = admin_url( 'admin.php?page=rwgo-edit-test&rwgo_experiment_id=' . absint( $experiment_id ) );
		$return_context  = sanitize_key( (string) $return_context );
		$allowed_returns = array( 'tests', 'reports' );
		if ( in_array( $return_context, $allowed_returns, true ) ) {
			$url = add_query_arg( 'rwgo_return', $return_context, $url );
		}
		return $url;
	}

	/**
	 * Default redirect URL for variant admin-post actions (promote/detach/regen) when rendering Edit Test.
	 * Uses ?rwgo_return= from the current request so POST handlers can bounce back to Tests or Reports.
	 *
	 * @param int $experiment_id Experiment CPT post ID.
	 * @return string
	 */
	/**
	 * Promote Winner wizard (Mode A + post-promotion guidance).
	 *
	 * @param int    $experiment_id  Experiment CPT ID.
	 * @param string $return_context Optional tests|reports.
	 * @return string
	 */
	public static function promote_winner_url( $experiment_id, $return_context = '' ) {
		$url            = admin_url( 'admin.php?page=rwgo-promote-winner&rwgo_experiment_id=' . absint( $experiment_id ) );
		$return_context = sanitize_key( (string) $return_context );
		if ( in_array( $return_context, array( 'tests', 'reports' ), true ) ) {
			$url = add_query_arg( 'rwgo_return', $return_context, $url );
		}
		return $url;
	}

	/**
	 * @param int $experiment_id Experiment CPT post ID.
	 * @return string
	 */
	public static function edit_test_action_fallback_url( $experiment_id ) {
		$experiment_id = absint( $experiment_id );
		if ( $experiment_id <= 0 ) {
			return admin_url( 'admin.php?page=rwgo-tests' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only UI context.
		$ret = isset( $_GET['rwgo_return'] ) ? sanitize_key( wp_unslash( $_GET['rwgo_return'] ) ) : '';
		if ( 'tests' === $ret ) {
			return admin_url( 'admin.php?page=rwgo-tests' );
		}
		if ( 'reports' === $ret ) {
			return admin_url( 'admin.php?page=rwgo-reports' );
		}
		return self::edit_test_url( $experiment_id, $ret );
	}

	/**
	 * Redirect target after saving Edit Test form (preserves rwgo_return_context from POST).
	 *
	 * @param int   $experiment_id Experiment CPT post ID.
	 * @param array $extra_query   Extra query args (e.g. rwgo_saved, rwgo_error).
	 * @return string
	 */
	public static function edit_test_redirect_after_save( $experiment_id, array $extra_query = array() ) {
		$experiment_id = absint( $experiment_id );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- paired with rwgo_update_test nonce.
		$ret = isset( $_POST['rwgo_return_context'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_return_context'] ) ) : '';
		if ( ! in_array( $ret, array( 'tests', 'reports' ), true ) ) {
			$ret = '';
		}
		$url = self::edit_test_url( $experiment_id, $ret );
		if ( ! empty( $extra_query ) ) {
			$url = add_query_arg( $extra_query, $url );
		}
		return $url;
	}

	/**
	 * Optional POST redirect target for admin-post handlers (validated with wp_validate_redirect).
	 *
	 * @param string $fallback_url Default URL if POST is absent or unsafe.
	 * @return string
	 */
	public static function safe_admin_redirect_target( $fallback_url ) {
		$fallback = (string) $fallback_url;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- admin-post handler verifies nonce separately.
		if ( empty( $_POST['rwgo_redirect_to'] ) || ! is_string( $_POST['rwgo_redirect_to'] ) ) {
			return $fallback;
		}
		$redirect = esc_url_raw( wp_unslash( $_POST['rwgo_redirect_to'] ) );
		return wp_validate_redirect( $redirect, $fallback );
	}

	/**
	 * Direct wp-admin URL to edit a page — for Elementor tests, open the Elementor builder for that post ID.
	 *
	 * @param int    $post_id   Page/post ID.
	 * @param string $test_type Experiment test_type from config.
	 * @return string Empty if user cannot edit.
	 */
	public static function post_builder_edit_url( $post_id, $test_type = '' ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return '';
		}
		$test_type = sanitize_key( (string) $test_type );
		if ( 'elementor_page' === $test_type && class_exists( '\Elementor\Plugin', false ) ) {
			$plugin = \Elementor\Plugin::$instance;
			if ( $plugin && isset( $plugin->documents ) && is_object( $plugin->documents ) ) {
				$doc = $plugin->documents->get( $post_id );
				if ( $doc && method_exists( $doc, 'get_edit_url' ) ) {
					$url = $doc->get_edit_url();
					if ( is_string( $url ) && $url ) {
						return $url;
					}
				}
			}
			return admin_url( 'post.php?post=' . $post_id . '&action=elementor' );
		}
		$link = get_edit_post_link( $post_id, 'raw' );
		return is_string( $link ) ? $link : '';
	}

	/**
	 * Public/front-end URL for viewing a Control or Variant page from wp-admin without experiment swapping.
	 *
	 * Adds a lightweight bypass query arg for privileged users so "View Control" always opens the real
	 * Control page instead of redirecting through the active test assignment.
	 *
	 * @param int $post_id Page/post ID.
	 * @return string
	 */
	public static function post_public_view_url( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return '';
		}
		$url = get_permalink( $post_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}
		if ( current_user_can( self::required_capability() ) ) {
			$url = add_query_arg( 'rwgo_no_swap', '1', $url );
		}
		return $url;
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
		add_action( 'admin_head', array( __CLASS__, 'hide_edit_test_submenu_css' ) );
		add_action( 'admin_head', array( __CLASS__, 'hide_promote_winner_submenu_css' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_license_actions' ) );
		add_action( 'admin_post_rwgo_test_license', array( __CLASS__, 'handle_test_license' ) );
		add_action( 'admin_post_rwgo_reset_counts', array( __CLASS__, 'handle_reset_counts' ) );
		add_action( 'admin_post_rwgo_export_stats', array( __CLASS__, 'handle_export_stats' ) );
		add_action( 'admin_post_rwgo_pause_test', array( __CLASS__, 'handle_pause_test' ) );
		add_action( 'admin_post_rwgo_resume_test', array( __CLASS__, 'handle_resume_test' ) );
		add_action( 'admin_post_rwgo_end_test', array( __CLASS__, 'handle_end_test' ) );
		add_action( 'admin_post_rwgo_duplicate_test', array( __CLASS__, 'handle_duplicate_test' ) );
		add_action( 'admin_post_rwgo_promote_variant', array( __CLASS__, 'handle_promote_variant' ) );
		add_action( 'admin_post_rwgo_redirect_rule_toggle', array( __CLASS__, 'handle_redirect_rule_toggle' ) );
		add_action( 'admin_post_rwgo_redirect_rule_delete', array( __CLASS__, 'handle_redirect_rule_delete' ) );
		add_action( 'admin_post_rwgo_detach_variant', array( __CLASS__, 'handle_detach_variant' ) );
		add_action( 'admin_post_rwgo_regenerate_variant', array( __CLASS__, 'handle_regenerate_variant' ) );
		add_action( 'admin_post_rwgo_delete_test', array( __CLASS__, 'handle_delete_test' ) );
		add_action( 'admin_post_rwgo_resync_page_bindings', array( __CLASS__, 'handle_resync_page_bindings' ) );
		add_action( 'admin_post_rwgo_resync_goal_physical_ids', array( __CLASS__, 'handle_resync_goal_physical_ids' ) );
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
		$exp_served = isset( $snapshot['experiment_variant_served'] ) && is_array( $snapshot['experiment_variant_served'] ) ? $snapshot['experiment_variant_served'] : array();
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
			'exp_served'                => $exp_served,
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
		if ( false !== strpos( $hook, 'rwgo-create-test' ) || false !== strpos( $hook, 'rwgo-edit-test' ) ) {
			wp_enqueue_script(
				'rwgo-test-form-goals',
				RWGO_URL . 'admin/js/rwgo-test-form-goals.js',
				array(),
				RWGO_VERSION,
				true
			);
			wp_localize_script(
				'rwgo-test-form-goals',
				'rwgoTestFormGoalsI18n',
				array(
					'pickSource'       => __( 'Select a source page first (and Variant B if needed).', 'reactwoo-geo-optimise' ),
					'pickGoal'         => __( '— Choose a goal —', 'reactwoo-geo-optimise' ),
					'noneFound'        => __( 'No defined goals were found yet. Edit the relevant page or variant in Elementor or Gutenberg and enable a Geo Optimise goal on the CTA, form, checkbox, or destination page you want to measure.', 'reactwoo-geo-optimise' ),
					'loadFailed'       => __( 'Could not load defined goals.', 'reactwoo-geo-optimise' ),
					'varBAfterPublish' => __( 'After this test exists, edit it to choose Variant B’s goal, or pick “existing” Variant B above.', 'reactwoo-geo-optimise' ),
				)
			);
		}
		if ( false !== strpos( $hook, 'rwgo-tracking-tools' ) || false !== strpos( $hook, 'rwgo-tests' ) ) {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_script(
				'rwgo-admin-gtm',
				RWGO_URL . 'admin/js/rwgo-admin-gtm.js',
				array(),
				RWGO_VERSION,
				true
			);
			wp_localize_script(
				'rwgo-admin-gtm',
				'rwgoAdminGtm',
				array(
					'copied'     => __( 'Copied', 'reactwoo-geo-optimise' ),
					'copyFailed' => __( 'Could not copy', 'reactwoo-geo-optimise' ),
					'copyLabel'  => __( 'Copy', 'reactwoo-geo-optimise' ),
					'copyAll'    => __( 'Copy all', 'reactwoo-geo-optimise' ),
				)
			);
		}
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

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Promote Winner', 'reactwoo-geo-optimise' ),
			' ',
			$cap,
			'rwgo-promote-winner',
			array( __CLASS__, 'render_promote_winner' )
		);
	}

	/**
	 * Hide Promote Winner from the left admin submenu (screen is linked from Reports / Tests only).
	 *
	 * @return void
	 */
	public static function hide_promote_winner_submenu_css() {
		if ( ! self::can_manage() ) {
			return;
		}
		?>
		<style id="rwgo-hide-promote-winner-submenu">
		#toplevel_page_rwgo-dashboard .wp-submenu li:has(> a[href*="page=rwgo-promote-winner"]) {
			display: none !important;
		}
		#toplevel_page_rwgo-dashboard .wp-submenu a[href*="page=rwgo-promote-winner"] {
			display: none !important;
		}
		</style>
		<?php
	}

	/**
	 * Hide Edit Test from the left admin submenu without remove_submenu_page().
	 * WordPress remove_submenu_page() calls remove_all_actions() on the page hook, which
	 * unregisters render_edit_test and breaks admin.php?page=rwgo-edit-test (redirect after create).
	 *
	 * @return void
	 */
	public static function hide_edit_test_submenu_css() {
		if ( ! self::can_manage() ) {
			return;
		}
		?>
		<style id="rwgo-hide-edit-test-submenu">
		#toplevel_page_rwgo-dashboard .wp-submenu li:has(> a[href*="page=rwgo-edit-test"]) {
			display: none !important;
		}
		#toplevel_page_rwgo-dashboard .wp-submenu a[href*="page=rwgo-edit-test"] {
			display: none !important;
		}
		</style>
		<?php
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
	 * Promote Winner wizard (Mode A + success checklist).
	 *
	 * @return void
	 */
	public static function render_promote_winner() {
		if ( ! self::can_manage() ) {
			return;
		}
		$data = self::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current = 'rwgo-tests';
		include RWGO_PATH . 'admin/views/promote-winner.php';
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
	/**
	 * Re-resolve control/variant page IDs from stored slugs/paths for all tests (after import/staging).
	 *
	 * @return void
	 */
	public static function handle_resync_page_bindings() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_resync_page_bindings' );
		$stats = class_exists( 'RWGO_Experiment_Repository', false )
			? RWGO_Experiment_Repository::resync_all_page_bindings()
			: array(
				'scanned'          => 0,
				'updated'          => 0,
				'source_repaired'  => 0,
				'variant_repaired' => 0,
				'forced_frontpage' => 0,
			);
		$url = add_query_arg(
			array(
				'rwgo_resynced'    => (string) (int) ( $stats['updated'] ?? 0 ),
				'rwgo_rs_scanned'  => (string) (int) ( $stats['scanned'] ?? 0 ),
				'rwgo_rs_src'      => (string) (int) ( $stats['source_repaired'] ?? 0 ),
				'rwgo_rs_var'      => (string) (int) ( $stats['variant_repaired'] ?? 0 ),
				'rwgo_rs_forced'   => (string) (int) ( $stats['forced_frontpage'] ?? 0 ),
			),
			self::developer_url( 'developer' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Re-read Elementor/Gutenberg JSON on Control + Variant B and update saved goal_id / handler_id (after widget replace, import, etc.).
	 *
	 * @return void
	 */
	public static function handle_resync_goal_physical_ids() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_resync_goal_physical_ids' );
		$stats = class_exists( 'RWGO_Experiment_Repository', false )
			? RWGO_Experiment_Repository::resync_all_defined_goal_physical_ids()
			: array(
				'scanned'       => 0,
				'updated'       => 0,
				'goals_patched' => 0,
			);
		$url = add_query_arg(
			array(
				'rwgo_goal_resync' => (string) (int) ( $stats['updated'] ?? 0 ),
				'rwgo_gr_scanned'  => (string) (int) ( $stats['scanned'] ?? 0 ),
				'rwgo_gr_patched'  => (string) (int) ( $stats['goals_patched'] ?? 0 ),
			),
			self::developer_url( 'developer' )
		);
		wp_safe_redirect( $url );
		exit;
	}

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
		$new_dup = RWGO_Page_Duplicator::duplicate_page( $source );
		if ( is_wp_error( $new_dup ) ) {
			$e = RWGO_Page_Duplicator::duplicate_redirect_error_arg( $new_dup );
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_error=' . rawurlencode( $e ) ) );
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
	 * Copy Variant B document into Control and complete the test.
	 *
	 * @return void
	 */
	public static function handle_promote_variant() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_promote_variant' );
		$exp_id = isset( $_POST['rwgo_experiment_id'] ) ? (int) $_POST['rwgo_experiment_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $exp_id <= 0 || ! class_exists( 'RWGO_Promotion_Service', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-reports&rwgo_error=promote' ) );
			exit;
		}
		$va = isset( $_POST['rwgo_variant_disposal'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_variant_disposal'] ) ) : RWGO_Promotion_Service::VARIANT_ARCHIVE_REDIRECT; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$allowed_va = array(
			RWGO_Promotion_Service::VARIANT_ARCHIVE_REDIRECT,
			RWGO_Promotion_Service::VARIANT_ARCHIVE_NO_REDIRECT,
			RWGO_Promotion_Service::VARIANT_TRASH_REDIRECT,
			RWGO_Promotion_Service::VARIANT_LEAVE,
		);
		if ( ! in_array( $va, $allowed_va, true ) ) {
			$va = RWGO_Promotion_Service::VARIANT_ARCHIVE_REDIRECT;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$copy_title = ! isset( $_POST['rwgo_copy_post_title'] ) || (int) $_POST['rwgo_copy_post_title'] === 1;
		$fallback   = self::promote_winner_url( $exp_id );
		$target     = self::safe_admin_redirect_target( $fallback );
		$r          = RWGO_Promotion_Service::run(
			$exp_id,
			array(
				'mode'            => RWGO_Promotion_Service::MODE_REPLACE_CONTENT,
				'variant_action'  => $va,
				'copy_post_title' => $copy_title,
			)
		);
		if ( is_wp_error( $r ) ) {
			wp_safe_redirect( add_query_arg( 'rwgo_error', 'promote', $target ) );
			exit;
		}
		$done = isset( $r['promotion_log_id'] ) ? (int) $r['promotion_log_id'] : 0;
		$target = add_query_arg( 'rwgo_promotion_done', $done, $target );
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Enable/disable a managed redirect rule.
	 *
	 * @return void
	 */
	public static function handle_redirect_rule_toggle() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_redirect_rule_toggle' );
		$id = isset( $_POST['rwgo_redirect_id'] ) ? (int) $_POST['rwgo_redirect_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $id <= 0 || ! class_exists( 'RWGO_Redirect_Store', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests' ) );
			exit;
		}
		$row = RWGO_Redirect_Store::get_rule( $id );
		if ( ! $row ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests' ) );
			exit;
		}
		$new_active = empty( $row->active ) ? 1 : 0;
		RWGO_Redirect_Store::set_active( $id, $new_active );
		wp_safe_redirect( self::safe_admin_redirect_target( admin_url( 'admin.php?page=rwgo-tests' ) ) );
		exit;
	}

	/**
	 * Delete a managed redirect rule.
	 *
	 * @return void
	 */
	public static function handle_redirect_rule_delete() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_redirect_rule_delete' );
		$id = isset( $_POST['rwgo_redirect_id'] ) ? (int) $_POST['rwgo_redirect_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $id <= 0 || ! class_exists( 'RWGO_Redirect_Store', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests' ) );
			exit;
		}
		RWGO_Redirect_Store::delete_rule( $id );
		wp_safe_redirect( self::safe_admin_redirect_target( admin_url( 'admin.php?page=rwgo-tests' ) ) );
		exit;
	}

	/**
	 * Remove Variant B from the test; optionally move the page to Trash or permanently delete it.
	 *
	 * @return void
	 */
	public static function handle_detach_variant() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_detach_variant' );
		$exp_id = isset( $_POST['rwgo_experiment_id'] ) ? (int) $_POST['rwgo_experiment_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$mode = isset( $_POST['rwgo_detach_mode'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_detach_mode'] ) ) : 'keep'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $exp_id <= 0 || ! class_exists( 'RWGO_Variant_Lifecycle', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_error=detach' ) );
			exit;
		}
		$page_mode = 'none';
		if ( 'trash' === $mode ) {
			$page_mode = 'trash';
		} elseif ( 'delete' === $mode ) {
			$page_mode = 'delete';
		}
		$r = RWGO_Variant_Lifecycle::detach_variant_b( $exp_id, $page_mode );
		$url         = self::safe_admin_redirect_target( self::edit_test_url( $exp_id ) );
		if ( is_wp_error( $r ) ) {
			wp_safe_redirect( add_query_arg( 'rwgo_error', 'detach', $url ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'rwgo_detached', '1', $url ) );
		exit;
	}

	/**
	 * Replace Variant B with a fresh duplicate of Control.
	 *
	 * @return void
	 */
	public static function handle_regenerate_variant() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_regenerate_variant' );
		$exp_id = isset( $_POST['rwgo_experiment_id'] ) ? (int) $_POST['rwgo_experiment_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $exp_id <= 0 || ! class_exists( 'RWGO_Variant_Lifecycle', false ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_error=regen' ) );
			exit;
		}
		$r   = RWGO_Variant_Lifecycle::regenerate_variant_b( $exp_id );
		$url = self::safe_admin_redirect_target( self::edit_test_url( $exp_id ) );
		if ( is_wp_error( $r ) ) {
			$regen_err = 'regen';
			if ( class_exists( 'RWGO_Page_Duplicator', false ) && 'dup_invalid' === RWGO_Page_Duplicator::duplicate_redirect_error_arg( $r ) ) {
				$regen_err = 'regen_invalid';
			}
			wp_safe_redirect( add_query_arg( 'rwgo_error', $regen_err, $url ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'rwgo_regenerated', '1', $url ) );
		exit;
	}

	/**
	 * Permanently delete an experiment and optional Variant B page; clears redirects and promotion log rows.
	 *
	 * @return void
	 */
	public static function handle_delete_test() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_delete_test' );
		$exp_id = isset( $_POST['rwgo_experiment_id'] ) ? (int) $_POST['rwgo_experiment_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$delete_variant_page = ! empty( $_POST['rwgo_delete_variant_pages'] );
		$fallback            = admin_url( 'admin.php?page=rwgo-tests' );
		if ( $exp_id <= 0 || ! class_exists( 'RWGO_Experiment_Repository', false ) ) {
			wp_safe_redirect( add_query_arg( 'rwgo_error', 'delete', $fallback ) );
			exit;
		}
		$post = get_post( $exp_id );
		if ( ! $post instanceof \WP_Post || RWGO_Experiment_CPT::POST_TYPE !== $post->post_type ) {
			wp_safe_redirect( add_query_arg( 'rwgo_error', 'delete', $fallback ) );
			exit;
		}
		if ( ! current_user_can( 'delete_post', $exp_id ) ) {
			wp_die( esc_html__( 'You do not have permission to delete this test.', 'reactwoo-geo-optimise' ) );
		}
		$cfg      = RWGO_Experiment_Repository::get_config( $exp_id );
		$src_id   = (int) ( $cfg['source_page_id'] ?? 0 );
		$var_b_id = 0;
		if ( ! empty( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
			foreach ( $cfg['variants'] as $row ) {
				if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
					$var_b_id = (int) ( $row['page_id'] ?? 0 );
					break;
				}
			}
		}
		if ( class_exists( 'RWGO_Redirect_Store', false ) ) {
			RWGO_Redirect_Store::delete_rules_for_experiment( $exp_id );
		}
		if ( class_exists( 'RWGO_Promotion_Log', false ) ) {
			RWGO_Promotion_Log::delete_by_experiment( $exp_id );
		}
		$deleted_v_page = false;
		if ( $delete_variant_page && $var_b_id > 0 && $var_b_id !== $src_id && current_user_can( 'delete_post', $var_b_id ) ) {
			$deleted_v_page = (bool) wp_delete_post( $var_b_id, true );
		}
		/**
		 * Fires after redirect/promotion cleanup and before the experiment post is deleted.
		 *
		 * @param int   $experiment_post_id Experiment CPT ID.
		 * @param array $context {
		 *     @type bool $deleted_variant_b_page Whether Variant B page was force-deleted.
		 * }
		 */
		do_action(
			'rwgo_test_deleted',
			$exp_id,
			array(
				'deleted_variant_b_page' => $deleted_v_page,
				'variant_b_post_id'      => $var_b_id,
			)
		);
		wp_delete_post( $exp_id, true );
		wp_safe_redirect( add_query_arg( 'rwgo_deleted', '1', $fallback ) );
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
