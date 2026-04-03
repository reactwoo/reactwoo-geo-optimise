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
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 26 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_rwgo_reset_counts', array( __CLASS__, 'handle_reset_counts' ) );
		add_action( 'admin_post_rwgo_export_stats', array( __CLASS__, 'handle_export_stats' ) );
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
		);
	}

	/**
	 * Summary card on Geo Core dashboard.
	 *
	 * @return void
	 */
	public static function render_geo_core_summary_card() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$data = self::get_view_data();
		$url  = admin_url( 'admin.php?page=' . self::MENU_PARENT );
		$nexp = isset( $data['active_experiment_count'] ) ? (int) $data['active_experiment_count'] : 0;
		$asg  = isset( $data['total_variant_assignments'] ) ? (int) $data['total_variant_assignments'] : 0;
		?>
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Geo Optimise', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Experiments and variant assignments on top of Geo Core — open Results for split counts.', 'reactwoo-geo-optimise' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Experiments (keys)', 'reactwoo-geo-optimise' ); ?>:</strong> <?php echo esc_html( (string) $nexp ); ?></li>
				<li><strong><?php esc_html_e( 'Variant assignments', 'reactwoo-geo-optimise' ); ?>:</strong> <?php echo esc_html( (string) $asg ); ?></li>
			</ul>
			<p>
				<a href="<?php echo esc_url( $url ); ?>" class="button button-primary"><?php esc_html_e( 'Open Geo Optimise', 'reactwoo-geo-optimise' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-results' ) ); ?>" class="button"><?php esc_html_e( 'Results', 'reactwoo-geo-optimise' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * @param string $current Current page slug.
	 * @return void
	 */
	public static function render_inner_nav( $current ) {
		$items = array(
			self::MENU_PARENT    => __( 'Overview', 'reactwoo-geo-optimise' ),
			'rwgo-experiments'   => __( 'Experiments', 'reactwoo-geo-optimise' ),
			'rwgo-results'       => __( 'Results', 'reactwoo-geo-optimise' ),
			'rwgo-diagnostics'   => __( 'Events & diagnostics', 'reactwoo-geo-optimise' ),
			'rwgo-help'          => __( 'Help', 'reactwoo-geo-optimise' ),
		);
		echo '<nav class="rwgc-inner-nav" aria-label="' . esc_attr__( 'Geo Optimise section navigation', 'reactwoo-geo-optimise' ) . '">';
		foreach ( $items as $slug => $label ) {
			$class = 'rwgc-inner-nav__link' . ( $slug === $current ? ' is-active' : '' );
			echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
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
		add_menu_page(
			__( 'Geo Optimise', 'reactwoo-geo-optimise' ),
			__( 'Geo Optimise', 'reactwoo-geo-optimise' ),
			'manage_options',
			self::MENU_PARENT,
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-chart-line',
			59
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Overview', 'reactwoo-geo-optimise' ),
			__( 'Overview', 'reactwoo-geo-optimise' ),
			'manage_options',
			self::MENU_PARENT,
			array( __CLASS__, 'render_dashboard' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Experiments', 'reactwoo-geo-optimise' ),
			__( 'Experiments', 'reactwoo-geo-optimise' ),
			'manage_options',
			'rwgo-experiments',
			array( __CLASS__, 'render_experiments' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Results', 'reactwoo-geo-optimise' ),
			__( 'Results', 'reactwoo-geo-optimise' ),
			'manage_options',
			'rwgo-results',
			array( __CLASS__, 'render_results' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Events & diagnostics', 'reactwoo-geo-optimise' ),
			__( 'Events & diagnostics', 'reactwoo-geo-optimise' ),
			'manage_options',
			'rwgo-diagnostics',
			array( __CLASS__, 'render_diagnostics' )
		);

		add_submenu_page(
			self::MENU_PARENT,
			__( 'Geo Optimise — Help', 'reactwoo-geo-optimise' ),
			__( 'Help', 'reactwoo-geo-optimise' ),
			'manage_options',
			'rwgo-help',
			array( __CLASS__, 'render_help' )
		);
	}

	/**
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
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
	public static function render_experiments() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rwgc_nav_current = 'rwgo-experiments';
		include RWGO_PATH . 'admin/views/experiments.php';
	}

	/**
	 * @return void
	 */
	public static function render_results() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$data = self::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current = 'rwgo-results';
		include RWGO_PATH . 'admin/views/results.php';
	}

	/**
	 * @return void
	 */
	public static function render_diagnostics() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$data = self::get_view_data();
		foreach ( $data as $k => $v ) {
			${$k} = $v;
		}
		$rwgc_nav_current = 'rwgo-diagnostics';
		include RWGO_PATH . 'admin/views/diagnostics.php';
	}

	/**
	 * @return void
	 */
	public static function render_help() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$rwgc_nav_current = 'rwgo-help';
		include RWGO_PATH . 'admin/views/help.php';
	}

	/**
	 * @return void
	 */
	public static function handle_reset_counts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_reset_counts' );
		delete_option( 'rwgo_geo_event_count' );
		delete_option( 'rwgo_route_resolved_count' );
		delete_option( 'rwgo_assignment_count' );
		delete_option( 'rwgo_experiment_variant_counts' );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgo-diagnostics&reset=1' ) );
		exit;
	}

	/**
	 * Download CSV of current snapshot (UTF-8 BOM for Excel).
	 *
	 * @return void
	 */
	public static function handle_export_stats() {
		if ( ! current_user_can( 'manage_options' ) ) {
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
