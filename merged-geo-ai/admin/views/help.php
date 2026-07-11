<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-help';
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Help', 'reactwoo-geo-ai' ),
			__( 'How Geo AI fits in the ReactWoo Geo suite and how to get value from it quickly.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo AI — Help', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card rwgc-card--highlight">
		<h2><?php esc_html_e( 'What Geo AI is', 'reactwoo-geo-ai' ); ?></h2>
		<p><?php esc_html_e( 'Geo AI helps you analyse pages, turn insights into recommendations, and produce reviewable copy and SEO drafts. It uses your ReactWoo plan; it does not replace Geo Core’s visitor detection.', 'reactwoo-geo-ai' ); ?></p>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Geo Core vs Geo AI', 'reactwoo-geo-ai' ); ?></h2>
		<ul class="rwgc-docs-list">
			<li>
				<strong><?php esc_html_e( 'Geo Core', 'reactwoo-geo-ai' ); ?></strong>
				— <?php esc_html_e( 'Free geolocation engine (e.g. MaxMind), REST building blocks, and suite navigation. No ReactWoo product license required for core geo.', 'reactwoo-geo-ai' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Geo AI (this plugin)', 'reactwoo-geo-ai' ); ?></strong>
				— <?php esc_html_e( 'Licensed AI workflows: analyses, recommendations, drafts, and usage tracked per site.', 'reactwoo-geo-ai' ); ?>
			</li>
		</ul>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Typical workflow', 'reactwoo-geo-ai' ); ?></h2>
		<ol class="rwgc-steps">
			<li><?php esc_html_e( 'Connect your license under Settings.', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( 'Analyse a page from the dashboard.', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( 'Open recommendations, then generate implementation drafts.', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( 'Apply changes in the block editor; use the queue if integrations record jobs.', 'reactwoo-geo-ai' ); ?></li>
		</ol>
		<div class="rwgc-actions rwgc-actions--stack-mobile rwga-help-workflow-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-dashboard' ) ); ?>" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Dashboard', 'reactwoo-geo-ai' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-license' ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Settings', 'reactwoo-geo-ai' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-advanced' ) ); ?>" class="rwgc-btn rwgc-btn--tertiary"><?php esc_html_e( 'Advanced', 'reactwoo-geo-ai' ); ?></a>
		</div>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Troubleshooting', 'reactwoo-geo-ai' ); ?></h2>
		<ul class="rwgc-docs-list">
			<li><?php esc_html_e( 'If workflows fail to run, confirm your license on Settings and that the site can reach the ReactWoo API.', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( 'For automated variant drafts, enable REST in Geo Core → Settings.', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( 'Connection tests and REST URLs live under Advanced.', 'reactwoo-geo-ai' ); ?></li>
		</ul>
	</div>
</div>
