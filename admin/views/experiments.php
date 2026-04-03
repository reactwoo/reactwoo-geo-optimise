<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-experiments';
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--experiments">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Experiments', 'reactwoo-geo-optimise' ),
			__( 'Sticky assignments are created in PHP — this screen explains how to wire tests; counts show up under Results.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Experiments', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php RWGO_Admin::render_suite_handoff_panel(); ?>

	<div class="rwgc-card rwgc-card--highlight">
		<h2><?php esc_html_e( 'How assignments work', 'reactwoo-geo-optimise' ); ?></h2>
		<p><?php esc_html_e( 'Call rwgo_get_variant() with an experiment key and variant labels. The first visit stores a sticky cookie (~30 days); we count first-time assignments per experiment/variant on the server.', 'reactwoo-geo-optimise' ); ?></p>
		<pre class="rwgo-code-block"><code>rwgo_get_variant( 'homepage_hero', array( 'A', 'B' ) );
rwgo_get_variant( 'checkout_trust', array( 'A', 'B' ), array( 50, 50 ) );</code></pre>
		<p class="description"><?php esc_html_e( 'Action rwgo_variant_assigned runs when a visitor receives their first assignment for that experiment.', 'reactwoo-geo-optimise' ); ?></p>
	</div>

	<div class="rwgc-grid">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Promote a winner', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'Compare variant counts under Results, then ship the winning template or flag in your theme/plugin — Geo Optimise does not auto-switch production content.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Paused tests', 'reactwoo-geo-optimise' ); ?></h2>
			<p><?php esc_html_e( 'Stop calling rwgo_get_variant for an experiment key or always return a single variant; existing cookies may still apply until they expire.', 'reactwoo-geo-optimise' ); ?></p>
		</div>
	</div>

	<p>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-results' ) ); ?>"><?php esc_html_e( 'View assignment results', 'reactwoo-geo-optimise' ); ?></a>
	</p>
</div>
