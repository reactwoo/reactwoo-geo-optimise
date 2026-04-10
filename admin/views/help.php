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
<div class="wrap rwgc-wrap rwgc-suite rwgo-wrap rwgo-wrap--help">
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
			<p><?php esc_html_e( 'Use Create Test, pick what you are testing, choose Control (source) and how Variant B is created, set audience and goal, then publish.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Test visitors by country', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'In Create Test, limit who enters the test by country when that fits your plan. Geo Core provides the visitor’s country when available.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Two versions: Control (A) and Variant B', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'Control uses your source page. Variant B can be a duplicate, an existing page, or a new draft. After publishing, open each version from Tests → Edit Control / Edit Variant B.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-tests' ) ); ?>"><?php esc_html_e( 'Open Tests', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Connect Google Analytics or Tag Manager', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'For conversion tracking beyond visitor counts, open Tracking Tools and copy the snippets into your tag setup. This is optional for basic tests.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( RWGO_Admin::tracking_tools_url() ); ?>"><?php esc_html_e( 'Open Tracking Tools', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Read reports', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'Reports show which variant leads on total conversions across your mapped success goals, plus a per-target breakdown. Revenue and ecommerce detail may still live in your analytics or store.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-reports' ) ); ?>"><?php esc_html_e( 'Open Reports', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
		<div id="rwgo-help-builder-goals" class="rwgc-card rwgc-card--highlight">
			<h2><?php esc_html_e( 'Builder goals (Gutenberg & Elementor)', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'For “defined” goals in a test, mark measurable CTAs in the editor: in Gutenberg, open a supported block and use the Geo Optimise panel in the block sidebar; for whole-page conversion destinations, use the Geo Optimise — destination goal panel in the document sidebar. In Elementor, use Advanced → Geo Optimise — goal on widgets, or the page Settings → Geo Optimise — destination goal section.', 'reactwoo-geo-optimise' ); ?></p>
			<p><?php esc_html_e( 'Geo Optimise goals are separate from GeoElementor geo routing — both can be active on the same site.', 'reactwoo-geo-optimise' ); ?></p>
			<p>
				<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-tests' ) ); ?>"><?php esc_html_e( 'Open Tests', 'reactwoo-geo-optimise' ); ?></a>
				<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( RWGO_Admin::developer_url( 'support' ) ); ?>"><?php esc_html_e( 'Support & troubleshooting', 'reactwoo-geo-optimise' ); ?></a>
			</p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Developers & custom integrations', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'PHP helpers, diagnostics, and hook reference live under Developer — they are not required for normal tests.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( RWGO_Admin::developer_url( 'developer' ) ); ?>"><?php esc_html_e( 'Open Developer', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
	</div>
</div>
