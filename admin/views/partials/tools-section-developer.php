<?php
/**
 * Tools tab: PHP, hooks, repository — with usage guidance.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="rwgc-card rwgc-card--highlight">
	<h2><?php esc_html_e( 'Who this is for', 'reactwoo-geo-optimise' ); ?></h2>
	<p><?php esc_html_e( 'Developers, agencies wiring custom themes, and support staff debugging integrations. Normal page tests created in wp-admin do not require this section.', 'reactwoo-geo-optimise' ); ?></p>
</div>

<div class="rwgc-card">
	<h2><?php esc_html_e( 'Manual PHP variant resolution', 'reactwoo-geo-optimise' ); ?></h2>
	<p><?php esc_html_e( 'Use this only when you implement a custom template or bespoke PHP outside the wizard — for example, conditional output in a theme file.', 'reactwoo-geo-optimise' ); ?></p>
	<p><strong><?php esc_html_e( 'Use when:', 'reactwoo-geo-optimise' ); ?></strong> <?php esc_html_e( 'building a custom template, rendering content conditionally in PHP, or bypassing automatic page-level routing.', 'reactwoo-geo-optimise' ); ?></p>
	<p><strong><?php esc_html_e( 'Do not use when:', 'reactwoo-geo-optimise' ); ?></strong> <?php esc_html_e( 'creating standard page tests in wp-admin, using Create Test, or testing duplicated Elementor / Gutenberg pages.', 'reactwoo-geo-optimise' ); ?></p>
	<pre class="rwgo-code-block"><code>$variant = rwgo_get_variant( 'your_experiment_key', array( 'control', 'var_b' ) );
if ( 'var_b' === $variant ) {
	// ...
}</code></pre>
	<p class="description"><?php esc_html_e( 'Replace the first argument with your experiment’s key. Keys for managed tests are stored on the test record; use Diagnostics & export or the REST tools if you need to look them up.', 'reactwoo-geo-optimise' ); ?></p>
</div>

<div class="rwgc-card">
	<h2><?php esc_html_e( 'Experiment data in custom code', 'reactwoo-geo-optimise' ); ?></h2>
	<p><?php esc_html_e( 'For plugins or MU code that must read saved test configuration:', 'reactwoo-geo-optimise' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'RWGO_Experiment_Repository::get_config( $experiment_post_id ) — returns the stored JSON config for a test.', 'reactwoo-geo-optimise' ); ?></li>
		<li><?php esc_html_e( 'RWGO_Experiment_Service::page_id_for_variant( $config, $variant_id ) — maps a variant id to a WordPress post ID.', 'reactwoo-geo-optimise' ); ?></li>
	</ul>
	<p class="description"><?php esc_html_e( 'Prefer the wizard and runtime for standard page tests; this is for extensions and automation.', 'reactwoo-geo-optimise' ); ?></p>
</div>

<div class="rwgc-card">
	<h2><?php esc_html_e( 'Actions and filters', 'reactwoo-geo-optimise' ); ?></h2>
	<p><?php esc_html_e( 'Subscribe in PHP when you need to react to assignments, Geo Core events, or recorded goal events.', 'reactwoo-geo-optimise' ); ?></p>
	<table class="widefat striped rwgo-table-comfortable">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Name', 'reactwoo-geo-optimise' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Type', 'reactwoo-geo-optimise' ); ?></th>
				<th scope="col"><?php esc_html_e( 'When to use', 'reactwoo-geo-optimise' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><code>rwgo_variant_assigned</code></td>
				<td><?php esc_html_e( 'Action', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'After a visitor is assigned a variant for an experiment key.', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
			<tr>
				<td><code>rwgo_geo_event</code></td>
				<td><?php esc_html_e( 'Action', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'When Geo Core forwards a geo-related event to Geo Optimise (integration diagnostics).', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
			<tr>
				<td><code>rwgo_route_variant_resolved</code></td>
				<td><?php esc_html_e( 'Action', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'When Geo Core resolves a route variant for a request.', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
			<tr>
				<td><code>rwgo_goal_fired</code></td>
				<td><?php esc_html_e( 'Action', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'When a goal payload is passed to PHP (server-side tracking).', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
			<tr>
				<td><code>rwgo_goal_event_recorded</code></td>
				<td><?php esc_html_e( 'Action', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'After a row is stored in the goal events table.', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
			<tr>
				<td><code>rwgo_goal_event_payload</code></td>
				<td><?php esc_html_e( 'Filter', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'Mutate a goal event payload before storage.', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
			<tr>
				<td><code>rwgo_builder_detection_with_settings</code></td>
				<td><?php esc_html_e( 'Filter', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'Adjust builder detection after site Settings (Recommended / Flexible / Manual) are applied.', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
			<tr>
				<td><code>rwgo_woocommerce_goal_hooks_enabled</code></td>
				<td><?php esc_html_e( 'Filter', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'Return false to disable WooCommerce server-side goal hooks regardless of Settings.', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
			<tr>
				<td><code>rwgo_woocommerce_goal_payload</code></td>
				<td><?php esc_html_e( 'Filter', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'Mutate commerce-originated goal payloads (add to cart, checkout, thank you) before rwgo_goal_fired.', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
			<tr>
				<td><code>rwgo_rest_client_goal_payload</code></td>
				<td><?php esc_html_e( 'Filter', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'Mutate payloads from the browser REST endpoint before rwgo_goal_fired.', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
			<tr>
				<td><code>rwgo_allow_client_goal_rest</code></td>
				<td><?php esc_html_e( 'Filter', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'Return false to block POST /rwgo/v1/goal (dataLayer still works).', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
			<tr>
				<td><code>rwgo_persist_client_goals</code></td>
				<td><?php esc_html_e( 'Filter', 'reactwoo-geo-optimise' ); ?></td>
				<td><?php esc_html_e( 'Return false to stop the tracking script from calling the REST endpoint (defaults true).', 'reactwoo-geo-optimise' ); ?></td>
			</tr>
		</tbody>
	</table>
</div>

<div class="rwgc-card">
	<h2><?php esc_html_e( 'Browser REST endpoint', 'reactwoo-geo-optimise' ); ?></h2>
	<p><code><?php echo esc_html( function_exists( 'rest_url' ) ? rest_url( 'rwgo/v1/goal' ) : '/wp-json/rwgo/v1/goal' ); ?></code></p>
	<p class="description"><?php esc_html_e( 'HTTP POST, JSON body includes a nonce (localized with rwgoTracking), experiment_key, goal_id, handler_id, variant_id, and optional fields. The server checks the active experiment and goal handlers, then stores a goal_fired row. Disable via the rwgo_persist_client_goals filter.', 'reactwoo-geo-optimise' ); ?></p>
</div>

<div class="rwgc-card">
	<h2><?php esc_html_e( 'WooCommerce server-side goals', 'reactwoo-geo-optimise' ); ?></h2>
	<p><?php esc_html_e( 'When WooCommerce is active and “WooCommerce server goals” is enabled under Settings, Geo Optimise listens for:', 'reactwoo-geo-optimise' ); ?></p>
	<ul>
		<li><code>woocommerce_add_to_cart</code> — <?php esc_html_e( 'matches active tests that include the product page; requires an existing cookie assignment.', 'reactwoo-geo-optimise' ); ?></li>
		<li><code>woocommerce_before_checkout_form</code> — <?php esc_html_e( 'begin checkout goals, once per experiment per checkout session.', 'reactwoo-geo-optimise' ); ?></li>
		<li><code>woocommerce_thankyou</code> — <?php esc_html_e( 'purchase goals, once per order per experiment (order meta dedupes).', 'reactwoo-geo-optimise' ); ?></li>
	</ul>
	<p class="description"><?php esc_html_e( 'Events are emitted as the rwgo_goal_fired action and stored like other goal rows. Disable in Settings or via the rwgo_woocommerce_goal_hooks_enabled filter for custom stacks.', 'reactwoo-geo-optimise' ); ?></p>
</div>

<div class="rwgc-card">
	<h2><?php esc_html_e( 'CSV export snapshot', 'reactwoo-geo-optimise' ); ?></h2>
	<p><?php esc_html_e( 'The export from Diagnostics & export includes plugin version, counters, site URL, UTC timestamp, and flattened assignment keys. Use it for support tickets, not for day-to-day reporting.', 'reactwoo-geo-optimise' ); ?></p>
	<p class="description"><?php esc_html_e( 'Filters: rwgo_export_csv_filename, rwgo_stats_snapshot.', 'reactwoo-geo-optimise' ); ?></p>
</div>
