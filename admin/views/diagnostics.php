<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-diagnostics';
$capabilities_url = isset( $capabilities_url ) && is_string( $capabilities_url ) ? $capabilities_url : '';
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--diagnostics">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Events & diagnostics', 'reactwoo-geo-optimise' ),
			__( 'Raw counters, exports, and integration links — for operators and developers.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Events &amp; diagnostics', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( ! empty( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Counters reset.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Raw counters', 'reactwoo-geo-optimise' ); ?></h2>
		<table class="widefat striped rwgo-table-comfortable">
			<tbody>
				<tr><th><?php esc_html_e( 'rwgc_geo_event (received)', 'reactwoo-geo-optimise' ); ?></th><td><?php echo esc_html( (string) ( $geo_events ?? 0 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'rwgc_route_variant_resolved', 'reactwoo-geo-optimise' ); ?></th><td><?php echo esc_html( (string) ( $route_hits ?? 0 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'New experiment assignments (rwgo_get_variant)', 'reactwoo-geo-optimise' ); ?></th><td><?php echo esc_html( (string) ( $assign_n ?? 0 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Assignments per route resolved', 'reactwoo-geo-optimise' ); ?></th><td><?php echo isset( $assign_per_route ) && '' !== (string) $assign_per_route ? esc_html( (string) $assign_per_route ) : esc_html__( '—', 'reactwoo-geo-optimise' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'CSV exports (lifetime)', 'reactwoo-geo-optimise' ); ?></th><td><?php echo esc_html( (string) (int) ( $csv_export_count ?? 0 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Last CSV export (UTC)', 'reactwoo-geo-optimise' ); ?></th><td><?php echo isset( $last_csv_export_gmt ) && '' !== (string) $last_csv_export_gmt ? esc_html( (string) $last_csv_export_gmt ) : esc_html__( '—', 'reactwoo-geo-optimise' ); ?></td></tr>
			</tbody>
		</table>

		<p class="rwui-cta-row">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
				<input type="hidden" name="action" value="rwgo_reset_counts" />
				<?php wp_nonce_field( 'rwgo_reset_counts' ); ?>
				<?php submit_button( __( 'Reset counters', 'reactwoo-geo-optimise' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
				<input type="hidden" name="action" value="rwgo_export_stats" />
				<?php wp_nonce_field( 'rwgo_export_stats' ); ?>
				<?php submit_button( __( 'Export CSV snapshot', 'reactwoo-geo-optimise' ), 'secondary', 'submit', false ); ?>
			</form>
		</p>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'REST discovery', 'reactwoo-geo-optimise' ); ?></h2>
		<?php if ( is_string( $capabilities_url ) && '' !== $capabilities_url ) : ?>
			<p><a href="<?php echo esc_url( $capabilities_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open capabilities JSON', 'reactwoo-geo-optimise' ); ?></a></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Enable REST in Geo Core → Settings.', 'reactwoo-geo-optimise' ); ?></p>
		<?php endif; ?>
	</div>

	<details class="rwgo-dev-details">
		<summary><?php esc_html_e( 'Technical details', 'reactwoo-geo-optimise' ); ?></summary>
		<p class="description"><?php esc_html_e( 'Export includes plugin version, counters, site URL, UTC timestamp, assignment_per_route_resolved when routes exist, and one CSV row per experiment variant. Filters: rwgo_export_csv_filename, rwgo_stats_snapshot.', 'reactwoo-geo-optimise' ); ?></p>
		<h3><?php esc_html_e( 'Hooks', 'reactwoo-geo-optimise' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Subscribe to rwgo_geo_event and rwgo_route_variant_resolved.', 'reactwoo-geo-optimise' ); ?></p>
	</details>
</div>
