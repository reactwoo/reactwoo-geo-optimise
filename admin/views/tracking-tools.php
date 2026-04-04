<?php
/**
 * Tracking Tools — GTM / GA4 / dataLayer (hierarchical layout).
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
			__( 'Connect Tag Manager, GA4, or your data layer — optional for validating and extending measurement.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Tracking Tools', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgo-stack">
		<section class="rwgo-panel rwgo-panel--hero" aria-labelledby="rwgo-tracking-orient-title">
			<h2 id="rwgo-tracking-orient-title" class="rwgo-section__title"><?php esc_html_e( 'Optional — you usually do not need this', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="rwgo-section__lead"><?php esc_html_e( 'You do not need Tracking Tools to run a basic page test. Use this area when you are connecting Google Tag Manager, GA4, or a data layer to validate or extend measurement.', 'reactwoo-geo-optimise' ); ?></p>
			<p class="rwgo-setting-row__hint"><?php esc_html_e( 'Need PHP hooks, raw counters, or CSV export?', 'reactwoo-geo-optimise' ); ?>
				<a href="<?php echo esc_url( RWGO_Admin::developer_url( 'developer' ) ); ?>"><?php esc_html_e( 'Open Developer', 'reactwoo-geo-optimise' ); ?></a></p>
		</section>

		<div class="rwgo-tools-tab-panel" role="region" aria-label="<?php esc_attr_e( 'Tracking guidance and snippets', 'reactwoo-geo-optimise' ); ?>">
			<?php include RWGO_PATH . 'admin/views/partials/tools-section-tracking.php'; ?>
		</div>
	</div>
</div>
