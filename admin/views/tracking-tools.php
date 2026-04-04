<?php
/**
 * Tracking Tools — GTM / GA4 / dataLayer (main journey stays out of raw payloads).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-tracking-tools';
$rwgo_experiments = isset( $rwgo_experiments ) && is_array( $rwgo_experiments ) ? $rwgo_experiments : array();
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

	<p class="description">
		<?php esc_html_e( 'Need PHP hooks, raw counters, or CSV export?', 'reactwoo-geo-optimise' ); ?>
		<a href="<?php echo esc_url( RWGO_Admin::developer_url( 'developer' ) ); ?>"><?php esc_html_e( 'Open Developer', 'reactwoo-geo-optimise' ); ?></a>
	</p>

	<div class="rwgo-tools-tab-panel" role="region">
		<?php include RWGO_PATH . 'admin/views/partials/tools-section-tracking.php'; ?>
	</div>
</div>
