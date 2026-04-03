<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-help';
?>
<div class="wrap rwgc-wrap rwgo-wrap">
	<h1><?php esc_html_e( 'Geo Optimise — help', 'reactwoo-geo-optimise' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Experiments and diagnostics on top of Geo Core events — not a replacement for Geo Core settings.', 'reactwoo-geo-optimise' ); ?></p>
	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card rwgc-card--highlight">
		<h2><?php esc_html_e( 'What Geo Optimise does', 'reactwoo-geo-optimise' ); ?></h2>
		<p><?php esc_html_e( 'It listens to Geo Core routing/geo events, counts assignments for experiments (rwgo_get_variant), and lets you export a diagnostic snapshot. It does not configure MaxMind or IP databases — that stays in Geo Core.', 'reactwoo-geo-optimise' ); ?></p>
	</div>

	<div class="rwgc-grid">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Merchants & PMs', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'Use the Overview counters to see whether events are firing. Reset clears local counters; export downloads a CSV for support or analysis.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Developers', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'Use rwgo_get_variant() for sticky A/B style assignments and subscribe to rwgo_geo_event / rwgo_route_variant_resolved. See Overview → Technical details for hook names.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
	</div>
</div>
