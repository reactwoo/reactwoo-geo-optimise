<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-results';
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--results">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Results', 'reactwoo-geo-optimise' ),
			__( 'Server-side counts of first-time variant assignments. This is not revenue or conversion tracking — pair with your analytics stack for business metrics.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Results', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card rwgc-card--full">
		<h2><?php esc_html_e( 'Assignment distribution', 'reactwoo-geo-optimise' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Resets when you use “Reset counters” on Events &amp; diagnostics.', 'reactwoo-geo-optimise' ); ?></p>
		<?php include RWGO_PATH . 'admin/views/partials/rwgo-assignment-table.php'; ?>
	</div>
</div>
