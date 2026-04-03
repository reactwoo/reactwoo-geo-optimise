<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Geo Optimise', 'reactwoo-geo-optimise' ); ?></h1>
	<?php if ( ! empty( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Counters reset.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>

	<p><?php esc_html_e( 'This plugin listens to Geo Core events and exposes hooks for experiments and CRO. Counters are diagnostic only.', 'reactwoo-geo-optimise' ); ?></p>

	<table class="widefat striped" style="max-width:480px">
		<tbody>
			<tr><th><?php esc_html_e( 'rwgc_geo_event (received)', 'reactwoo-geo-optimise' ); ?></th><td><?php echo esc_html( (string) $geo_events ); ?></td></tr>
			<tr><th><?php esc_html_e( 'rwgc_route_variant_resolved', 'reactwoo-geo-optimise' ); ?></th><td><?php echo esc_html( (string) $route_hits ); ?></td></tr>
			<tr><th><?php esc_html_e( 'New experiment assignments (rwgo_get_variant)', 'reactwoo-geo-optimise' ); ?></th><td><?php echo esc_html( (string) ( isset( $assign_n ) ? $assign_n : 0 ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Assignments per route resolved', 'reactwoo-geo-optimise' ); ?></th><td><?php echo isset( $assign_per_route ) && '' !== (string) $assign_per_route ? esc_html( (string) $assign_per_route ) : esc_html__( '—', 'reactwoo-geo-optimise' ); ?></td></tr>
			<tr><th><?php esc_html_e( 'CSV exports (lifetime)', 'reactwoo-geo-optimise' ); ?></th><td><?php echo esc_html( (string) ( isset( $csv_export_count ) ? (int) $csv_export_count : 0 ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Last CSV export (UTC)', 'reactwoo-geo-optimise' ); ?></th><td><?php echo isset( $last_csv_export_gmt ) && '' !== (string) $last_csv_export_gmt ? esc_html( (string) $last_csv_export_gmt ) : esc_html__( '—', 'reactwoo-geo-optimise' ); ?></td></tr>
		</tbody>
	</table>

	<p>
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
	<p class="description"><?php esc_html_e( 'Export includes plugin version, counters, site URL, UTC timestamp, assignment_per_route_resolved when routes exist, and one CSV row per experiment variant (keys experiment_variant.{slug}.{variant}). Default filename includes the site host; override with the rwgo_export_csv_filename filter. Extend the snapshot with the rwgo_stats_snapshot filter.', 'reactwoo-geo-optimise' ); ?></p>

	<h2><?php esc_html_e( 'Assignment distribution (server)', 'reactwoo-geo-optimise' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Counts each first-time rwgo_get_variant assignment (not page views). Resets with “Reset counters”.', 'reactwoo-geo-optimise' ); ?></p>
	<?php
	$exp_dist = isset( $exp_dist ) && is_array( $exp_dist ) ? $exp_dist : array();
	$rows     = array();
	foreach ( $exp_dist as $exp_key => $variants ) {
		if ( ! is_string( $exp_key ) || ! is_array( $variants ) ) {
			continue;
		}
		foreach ( $variants as $vk => $cnt ) {
			if ( ! is_string( $vk ) && ! is_numeric( $vk ) ) {
				continue;
			}
			$rows[] = array(
				'exp'   => $exp_key,
				'var'   => (string) $vk,
				'count' => (int) $cnt,
			);
		}
	}
	usort(
		$rows,
		static function ( $a, $b ) {
			$c = strcmp( $a['exp'], $b['exp'] );
			return 0 !== $c ? $c : strcmp( $a['var'], $b['var'] );
		}
	);
	?>
	<?php if ( ! empty( $rows ) ) : ?>
		<table class="widefat striped" style="max-width:640px">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Experiment', 'reactwoo-geo-optimise' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Variant', 'reactwoo-geo-optimise' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Assignments', 'reactwoo-geo-optimise' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><code><?php echo esc_html( $r['exp'] ); ?></code></td>
						<td><code><?php echo esc_html( $r['var'] ); ?></code></td>
						<td><?php echo esc_html( (string) $r['count'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'No experiment assignments recorded yet.', 'reactwoo-geo-optimise' ); ?></p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'REST discovery', 'reactwoo-geo-optimise' ); ?></h2>
	<?php if ( is_string( $capabilities_url ) && '' !== $capabilities_url ) : ?>
		<p><code><?php echo esc_html( $capabilities_url ); ?></code></p>
	<?php else : ?>
		<p><?php esc_html_e( 'Enable REST in Geo Core → Settings.', 'reactwoo-geo-optimise' ); ?></p>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Experiments (PHP)', 'reactwoo-geo-optimise' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Sticky assignment (30-day cookie): rwgo_get_variant( "my_test", array( "A", "B" ) ) or weighted: rwgo_get_variant( "my_test", array( "A", "B" ), array( 70, 30 ) ). Action rwgo_variant_assigned fires on first assignment.', 'reactwoo-geo-optimise' ); ?></p>

	<h2><?php esc_html_e( 'Hooks', 'reactwoo-geo-optimise' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Subscribe to rwgo_geo_event and rwgo_route_variant_resolved in your code.', 'reactwoo-geo-optimise' ); ?></p>
</div>
