<?php
/**
 * Developer — implementation reference, diagnostics, support (tabbed).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-developer';
$rwgo_experiments = isset( $rwgo_experiments ) && is_array( $rwgo_experiments ) ? $rwgo_experiments : array();

$allowed_tabs = array( 'developer', 'diagnostics', 'support' );
$rwgo_tab     = isset( $_GET['rwgo_tab'] ) ? sanitize_key( wp_unslash( $_GET['rwgo_tab'] ) ) : 'developer'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $rwgo_tab, $allowed_tabs, true ) ) {
	$rwgo_tab = 'developer';
}

$tabs = array(
	'developer'   => __( 'Developer & code', 'reactwoo-geo-optimise' ),
	'diagnostics' => __( 'Diagnostics & export', 'reactwoo-geo-optimise' ),
	'support'     => __( 'Support & troubleshooting', 'reactwoo-geo-optimise' ),
);
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--developer">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Developer', 'reactwoo-geo-optimise' ),
			__( 'Code integration, raw diagnostics, and technical troubleshooting — keep the main test flow outcome-led.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Developer', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgo-orient rwgo-card">
		<h2><?php esc_html_e( 'Who this is for', 'reactwoo-geo-optimise' ); ?></h2>
		<p><?php esc_html_e( 'Developers, agencies wiring custom themes, and support staff debugging integrations. Normal page tests created in wp-admin do not require this section.', 'reactwoo-geo-optimise' ); ?></p>
	</div>

	<h2 class="nav-tab-wrapper rwgo-tools-nav" role="tablist">
		<?php foreach ( $tabs as $slug => $label ) : ?>
			<a href="<?php echo esc_url( RWGO_Admin::developer_url( $slug ) ); ?>"
				class="nav-tab<?php echo $rwgo_tab === $slug ? ' nav-tab-active' : ''; ?>"
				<?php echo $rwgo_tab === $slug ? ' aria-current="true"' : ''; ?>>
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<div class="rwgo-tools-tab-panel" role="tabpanel">
		<?php
		switch ( $rwgo_tab ) {
			case 'diagnostics':
				?>
				<div class="rwgc-card rwgc-card--highlight">
					<h2><?php esc_html_e( 'Diagnostics & export', 'reactwoo-geo-optimise' ); ?></h2>
					<p><?php esc_html_e( 'Raw counters and exports are for debugging and support. Read performance in Reports using goal conversion rates.', 'reactwoo-geo-optimise' ); ?></p>
				</div>
				<?php
				include RWGO_PATH . 'admin/views/partials/diagnostics-inner.php';
				break;
			case 'support':
				include RWGO_PATH . 'admin/views/partials/tools-section-support.php';
				break;
			case 'developer':
			default:
				include RWGO_PATH . 'admin/views/partials/tools-section-developer.php';
				break;
		}
		?>
	</div>
</div>
