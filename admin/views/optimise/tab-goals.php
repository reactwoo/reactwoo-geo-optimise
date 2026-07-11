<?php
/**
 * Goals tab — guidance + links (per-test goals remain on Create/Edit Test).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$help_goals_url = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::help_url( 'rwgo-help-builder-goals' ) : '';
$create_url     = admin_url( 'admin.php?page=rwgo-create-test' );
$tracking_url   = class_exists( 'RWGO_Admin', false ) ? RWGO_Admin::tracking_tools_url() : '';
?>
<div class="rwgo-panel">
	<h2 class="rwgo-section__title"><?php esc_html_e( 'Conversion goals', 'reactwoo-geo-optimise' ); ?></h2>
	<p class="rwgo-section__lead">
		<?php esc_html_e( 'Goals are defined per experiment — on the page builder (Elementor or Gutenberg), as destination URLs, form submits, REST events, or WooCommerce hooks. This tab centralises guidance; assignment and reporting stay on Experiments and Reports.', 'reactwoo-geo-optimise' ); ?>
	</p>

	<ul class="rwgo-onboarding-steps">
		<li>
			<span class="rwgo-step-num" aria-hidden="true">1</span>
			<?php esc_html_e( 'Mark goals on Control and Variant B in the builder.', 'reactwoo-geo-optimise' ); ?>
		</li>
		<li>
			<span class="rwgo-step-num" aria-hidden="true">2</span>
			<?php esc_html_e( 'Map which physical goal counts as success when creating or editing a test.', 'reactwoo-geo-optimise' ); ?>
		</li>
		<li>
			<span class="rwgo-step-num" aria-hidden="true">3</span>
			<?php esc_html_e( 'Confirm measurement with Tracking tools and Reports.', 'reactwoo-geo-optimise' ); ?>
		</li>
	</ul>

	<p class="rwgo-cta-row">
		<a class="button button-primary" href="<?php echo esc_url( $create_url ); ?>"><?php esc_html_e( 'Create test', 'reactwoo-geo-optimise' ); ?></a>
		<?php if ( '' !== $help_goals_url ) : ?>
			<a class="button button-secondary" href="<?php echo esc_url( $help_goals_url ); ?>"><?php esc_html_e( 'Builder goals guide', 'reactwoo-geo-optimise' ); ?></a>
		<?php endif; ?>
		<?php if ( '' !== $tracking_url ) : ?>
			<a class="button button-secondary" href="<?php echo esc_url( $tracking_url ); ?>"><?php esc_html_e( 'Tracking tools', 'reactwoo-geo-optimise' ); ?></a>
		<?php endif; ?>
	</p>
</div>
