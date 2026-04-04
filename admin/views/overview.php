<?php
/**
 * Dashboard (Overview).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current     = isset( $rwgc_nav_current ) ? $rwgc_nav_current : RWGO_Admin::MENU_PARENT;
$managed_tests_total   = isset( $managed_tests_total ) ? (int) $managed_tests_total : 0;
$active_managed_tests = isset( $active_managed_tests ) ? (int) $active_managed_tests : 0;
$goal_events_total    = isset( $goal_events_total ) ? (int) $goal_events_total : 0;
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--overview">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Geo Optimise', 'reactwoo-geo-optimise' ),
			__( 'Create page tests, compare variants, and see which version is leading — without editing code.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Optimise', 'reactwoo-geo-optimise' ); ?></h1>
		<p class="description"><?php esc_html_e( 'A/B tests and reports on top of Geo Core.', 'reactwoo-geo-optimise' ); ?></p>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php RWGO_Admin::render_suite_handoff_panel(); ?>

	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<div class="rwgo-dashboard__stats">
			<?php
			RWGC_Admin_UI::render_stat_grid_open();
			RWGC_Admin_UI::render_stat_card(
				__( 'Active tests', 'reactwoo-geo-optimise' ),
				(string) (int) $active_managed_tests,
				array(
					'hint' => __( 'Tests currently set to run', 'reactwoo-geo-optimise' ),
					'tone' => ( $active_managed_tests > 0 ) ? 'success' : 'neutral',
				)
			);
			RWGC_Admin_UI::render_stat_card(
				__( 'Visitors assigned', 'reactwoo-geo-optimise' ),
				(string) (int) ( $total_variant_assignments ?? 0 ),
				array(
					'hint' => __( 'Visitors who were assigned to a version in any test', 'reactwoo-geo-optimise' ),
					'tone' => 'default',
				)
			);
			RWGC_Admin_UI::render_stat_card(
				__( 'Goals recorded', 'reactwoo-geo-optimise' ),
				(string) (int) $goal_events_total,
				array(
					'hint' => __( 'Goal events recorded when measurement is connected', 'reactwoo-geo-optimise' ),
					'tone' => 'neutral',
				)
			);
			RWGC_Admin_UI::render_stat_grid_close();
			?>
		</div>
	<?php endif; ?>

	<?php if ( 0 === $managed_tests_total ) : ?>
		<div class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Welcome — run your first page test', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'Choose a page, create a second version to compare, set the audience and goal, then publish.', 'reactwoo-geo-optimise' ); ?></p>
			<ol class="rwgo-onboarding-steps" aria-label="<?php esc_attr_e( 'Getting started', 'reactwoo-geo-optimise' ); ?>">
				<li><span class="rwgo-step-num" aria-hidden="true">1</span> <?php esc_html_e( 'Create your first test', 'reactwoo-geo-optimise' ); ?></li>
				<li><span class="rwgo-step-num" aria-hidden="true">2</span> <?php esc_html_e( 'Define a goal', 'reactwoo-geo-optimise' ); ?></li>
				<li><span class="rwgo-step-num" aria-hidden="true">3</span> <?php esc_html_e( 'Edit your variant', 'reactwoo-geo-optimise' ); ?></li>
				<li><span class="rwgo-step-num" aria-hidden="true">4</span> <?php esc_html_e( 'View results', 'reactwoo-geo-optimise' ); ?></li>
			</ol>
			<p class="rwgo-cta-row">
				<a class="button button-primary button-large" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-tests' ) ); ?>"><?php esc_html_e( 'View tests', 'reactwoo-geo-optimise' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-reports' ) ); ?>"><?php esc_html_e( 'View reports', 'reactwoo-geo-optimise' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<div class="rwgo-hero">
			<h2><?php esc_html_e( 'Next steps', 'reactwoo-geo-optimise' ); ?></h2>
			<ol class="rwgo-steps">
				<li><?php esc_html_e( 'Review open tests on the Tests screen — pause or end when you are done.', 'reactwoo-geo-optimise' ); ?></li>
				<li><?php esc_html_e( 'Open Reports for traffic split and which variant is leading.', 'reactwoo-geo-optimise' ); ?></li>
				<li><?php esc_html_e( 'Use Tracking Tools only when you need GA4, GTM, or a data layer — not required for basic tests.', 'reactwoo-geo-optimise' ); ?></li>
			</ol>
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_quick_actions(
					array(
						array(
							'url'     => admin_url( 'admin.php?page=rwgo-create-test' ),
							'label'   => __( 'Create Test', 'reactwoo-geo-optimise' ),
							'primary' => true,
						),
						array(
							'url'   => admin_url( 'admin.php?page=rwgo-tests' ),
							'label' => __( 'View tests', 'reactwoo-geo-optimise' ),
						),
						array(
							'url'   => admin_url( 'admin.php?page=rwgo-reports' ),
							'label' => __( 'View reports', 'reactwoo-geo-optimise' ),
						),
						array(
							'url'   => RWGO_Admin::tracking_tools_url(),
							'label' => __( 'Tracking Tools', 'reactwoo-geo-optimise' ),
						),
					)
				);
			}
			?>
		</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'At a glance', 'reactwoo-geo-optimise' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %d: number of saved tests */
				esc_html__( 'You have %d saved test(s). Open Tests for the full list or Reports for results.', 'reactwoo-geo-optimise' ),
				(int) $managed_tests_total
			);
			?>
		</p>
		<p class="rwui-cta-row">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-tests' ) ); ?>"><?php esc_html_e( 'View tests', 'reactwoo-geo-optimise' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-reports' ) ); ?>"><?php esc_html_e( 'View reports', 'reactwoo-geo-optimise' ); ?></a>
		</p>
	</div>
</div>
