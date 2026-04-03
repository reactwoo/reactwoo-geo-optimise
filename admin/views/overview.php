<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : RWGO_Admin::MENU_PARENT;
$assignment_rows = isset( $assignment_rows ) && is_array( $assignment_rows ) ? $assignment_rows : array();
$preview_rows    = array_slice( $assignment_rows, 0, 5 );
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--overview">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Geo Optimise', 'reactwoo-geo-optimise' ),
			__( 'Run A/B-style experiments on top of Geo Core, then read results from server-side assignment counts — not WooCommerce order totals.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Optimise', 'reactwoo-geo-optimise' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Experiments and diagnostics on top of Geo Core.', 'reactwoo-geo-optimise' ); ?></p>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php RWGO_Admin::render_suite_handoff_panel(); ?>

	<?php if ( ! empty( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Counters reset.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>

	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_stat_grid_open();
		RWGC_Admin_UI::render_stat_card(
			__( 'Experiments (keys)', 'reactwoo-geo-optimise' ),
			(string) (int) ( $active_experiment_count ?? 0 ),
			array(
				'hint' => __( 'Distinct experiment slugs with assignment data', 'reactwoo-geo-optimise' ),
				'tone' => ( (int) ( $active_experiment_count ?? 0 ) > 0 ) ? 'success' : 'neutral',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'Variant assignments', 'reactwoo-geo-optimise' ),
			(string) (int) ( $total_variant_assignments ?? 0 ),
			array(
				'hint' => __( 'Sum of first-time sticky assignments', 'reactwoo-geo-optimise' ),
				'tone' => 'default',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'Routing events', 'reactwoo-geo-optimise' ),
			(string) (int) ( $route_hits ?? 0 ),
			array(
				'hint' => __( 'Geo Core route resolutions seen here', 'reactwoo-geo-optimise' ),
				'tone' => 'neutral',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'Geo events', 'reactwoo-geo-optimise' ),
			(string) (int) ( $geo_events ?? 0 ),
			array(
				'hint' => __( 'Diagnostic counter — see Events &amp; diagnostics for detail', 'reactwoo-geo-optimise' ),
				'tone' => 'neutral',
			)
		);
		RWGC_Admin_UI::render_stat_grid_close();
		?>
	<?php endif; ?>

	<div class="rwgo-hero">
		<h2><?php esc_html_e( 'What to do next', 'reactwoo-geo-optimise' ); ?></h2>
		<ol class="rwgo-steps">
			<li><?php esc_html_e( 'Define experiment keys in PHP with rwgo_get_variant() (see Experiments).', 'reactwoo-geo-optimise' ); ?></li>
			<li><?php esc_html_e( 'Review the Results table to see how variants are splitting.', 'reactwoo-geo-optimise' ); ?></li>
			<li><?php esc_html_e( 'Use Events &amp; diagnostics for raw counters, CSV export, or support.', 'reactwoo-geo-optimise' ); ?></li>
		</ol>
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_quick_actions(
				array(
					array(
						'url'     => admin_url( 'admin.php?page=rwgo-experiments' ),
						'label'   => __( 'How experiments work', 'reactwoo-geo-optimise' ),
						'primary' => true,
					),
					array(
						'url'   => admin_url( 'admin.php?page=rwgo-results' ),
						'label' => __( 'View results', 'reactwoo-geo-optimise' ),
					),
					array(
						'url'   => admin_url( 'admin.php?page=rwgo-diagnostics' ),
						'label' => __( 'Export / diagnostics', 'reactwoo-geo-optimise' ),
					),
					array(
						'url'   => admin_url( 'admin.php?page=rwgo-help' ),
						'label' => __( 'Help', 'reactwoo-geo-optimise' ),
					),
				)
			);
		}
		?>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Results preview', 'reactwoo-geo-optimise' ); ?></h2>
		<p class="description"><?php esc_html_e( 'First rows of the assignment table — open Results for the full list.', 'reactwoo-geo-optimise' ); ?></p>
		<?php
		$assignment_rows = $preview_rows;
		include RWGO_PATH . 'admin/views/partials/rwgo-assignment-table.php';
		?>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-results' ) ); ?>"><?php esc_html_e( 'Open full results', 'reactwoo-geo-optimise' ); ?></a></p>
	</div>
</div>
