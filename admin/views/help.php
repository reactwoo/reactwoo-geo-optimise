<?php
/**
 * Help — product topics (non-technical).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-help';
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--help">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Help', 'reactwoo-geo-optimise' ),
			__( 'How to run tests and read results — Geo Core still handles country detection and site-wide settings.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Help', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>
	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-grid">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Create your first page test', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'Use Create Test, pick the page you want to compare, choose audience and goal, then publish. You can edit each version in the block editor or Elementor like any other page.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Test visitors by country', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'In Create Test, limit who enters the test by country when that fits your plan. Geo Core provides the visitor’s country when available.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Two versions of the same page', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'We duplicate your page so you can change headlines, layout, or images without touching code. Open each version from the Tests list.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Connect Google Analytics or Tag Manager', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'For conversion tracking beyond visitor counts, open Tracking Tools and copy the snippets into your tag setup.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( RWGO_Admin::tracking_tools_url() ); ?>"><?php esc_html_e( 'Open Tracking Tools', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Read reports', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'Reports focus on your primary goal conversion rate per variant. Revenue and ecommerce detail may still live in your analytics or store.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Developers & custom integrations', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'PHP helpers, diagnostics, and hook reference live under Developer — they are not required for normal tests.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( RWGO_Admin::developer_url( 'developer' ) ); ?>"><?php esc_html_e( 'Open Developer', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
	</div>
</div>
