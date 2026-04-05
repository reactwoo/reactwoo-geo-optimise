<?php
/**
 * Tracking Tools — GTM / GA4 / dataLayer agency handoff.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-tracking-tools';
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--tracking-tools">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Tracking Tools', 'reactwoo-geo-optimise' ),
			__( 'Agency-friendly GTM and dataLayer handoff for Geo Optimise tests.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Tracking Tools', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgo-stack">
		<section class="rwgo-panel rwgo-panel--hero rwgo-tracking-orient" aria-labelledby="rwgo-tracking-orient-title">
			<h2 id="rwgo-tracking-orient-title" class="rwgo-section__title"><?php esc_html_e( 'Tracking & agency handoff', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="rwgo-section__lead"><?php esc_html_e( 'Use this section when handing measurement to an agency or implementing reporting in Google Tag Manager. You do not need this for a basic test unless you want external analytics reporting.', 'reactwoo-geo-optimise' ); ?></p>
			<p class="rwgo-setting-row__hint"><?php esc_html_e( 'Need PHP hooks, raw counters, or CSV export?', 'reactwoo-geo-optimise' ); ?>
				<a href="<?php echo esc_url( RWGO_Admin::developer_url( 'developer' ) ); ?>"><?php esc_html_e( 'Open Developer', 'reactwoo-geo-optimise' ); ?></a></p>
		</section>

		<?php require RWGO_PATH . 'admin/views/partials/gtm-quick-setup.php'; ?>

		<details class="rwgo-panel rwgo-tracking-technical-details">
			<summary class="rwgo-tracking-technical-details__summary"><?php esc_html_e( 'Technical details & generated snippets', 'reactwoo-geo-optimise' ); ?></summary>
			<div class="rwgo-tracking-technical-details__body">
				<?php include RWGO_PATH . 'admin/views/partials/tools-section-tracking-advanced.php'; ?>
			</div>
		</details>
	</div>
</div>
