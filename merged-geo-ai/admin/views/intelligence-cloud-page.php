<?php
/**
 * Cloud intelligence run history and relationship graph preview.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_nav_current    = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-intelligence-cloud';
$rwga_cloud_site_id  = isset( $rwga_cloud_site_id ) ? (string) $rwga_cloud_site_id : '';
$rwga_runs           = isset( $rwga_runs ) && is_array( $rwga_runs ) ? $rwga_runs : array();
$rwga_run_detail     = isset( $rwga_run_detail ) && is_array( $rwga_run_detail ) ? $rwga_run_detail : null;
$rwga_graph          = isset( $rwga_graph ) && is_array( $rwga_graph ) ? $rwga_graph : null;
$rwga_cloud_error    = isset( $rwga_cloud_error ) ? (string) $rwga_cloud_error : '';
$rwga_selected_run   = isset( $rwga_selected_run ) ? sanitize_text_field( (string) $rwga_selected_run ) : '';

$base_url = admin_url( 'admin.php?page=rwga-intelligence-cloud' );
$sync_url = wp_nonce_url(
	admin_url( 'admin.php?page=rwga-license&rwga_action=sync_snapshot' ),
	'rwga_sync_snapshot'
);
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--intelligence-cloud">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Cloud intelligence', 'reactwoo-geo-ai' ),
			__( 'Recent intelligence workflow runs and a relationship graph from your synced site snapshot.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Cloud intelligence', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card" style="max-width:720px;margin-bottom:1rem;">
		<p class="description">
			<?php esc_html_e( 'New to site intelligence?', 'reactwoo-geo-ai' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-intelligence-wizard' ) ); ?>"><?php esc_html_e( 'Open the step-by-step guide', 'reactwoo-geo-ai' ); ?></a>
		</p>
	</div>

	<?php
	$intel_flash = isset( $_GET['rwga_intel'] ) ? sanitize_key( wp_unslash( $_GET['rwga_intel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ran' === $intel_flash ) {
		$wf_key = isset( $_GET['rwga_wf'] ) ? sanitize_key( wp_unslash( $_GET['rwga_wf'] ) ) : 'site_audit'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$n      = isset( $_GET['rwga_actions'] ) ? (int) $_GET['rwga_actions'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>';
		if ( $n > 0 ) {
			echo esc_html(
				sprintf(
					/* translators: 1: workflow key, 2: number of pending actions */
					__( 'Intelligence workflow “%1$s” completed. %2$d pending action(s) are ready for review.', 'reactwoo-geo-ai' ),
					$wf_key,
					$n
				)
			);
		} else {
			echo esc_html(
				sprintf(
					/* translators: %s: workflow key */
					__( 'Intelligence workflow “%s” completed. No approval-gated actions were suggested this run.', 'reactwoo-geo-ai' ),
					$wf_key
				)
			);
		}
		echo '</p></div>';
	} elseif ( 'error' === $intel_flash ) {
		$err = isset( $_GET['rwga_err'] ) ? sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $err ? $err : __( 'Intelligence workflow failed.', 'reactwoo-geo-ai' ) ) . '</p></div>';
	}
	?>

	<?php if ( '' !== $rwga_cloud_error ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $rwga_cloud_error ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-card" style="max-width: 720px; margin-bottom: 1.5rem;">
		<p>
			<strong><?php esc_html_e( 'Cloud site id', 'reactwoo-geo-ai' ); ?>:</strong>
			<?php echo $rwga_cloud_site_id ? '<code>' . esc_html( $rwga_cloud_site_id ) . '</code>' : esc_html__( 'Not registered yet', 'reactwoo-geo-ai' ); ?>
		</p>
		<p class="rwgc-actions">
			<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $sync_url ); ?>"><?php esc_html_e( 'Sync site intelligence', 'reactwoo-geo-ai' ); ?></a>
			<?php if ( '' !== $rwga_cloud_site_id ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
					<input type="hidden" name="action" value="rwga_run_intelligence_workflow" />
					<input type="hidden" name="workflow_key" value="site_audit" />
					<input type="hidden" name="redirect_page" value="rwga-intelligence-cloud" />
					<?php wp_nonce_field( 'rwga_run_intelligence_workflow' ); ?>
					<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Run site audit', 'reactwoo-geo-ai' ); ?></button>
				</form>
			<?php endif; ?>
			<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-intelligence-actions' ) ); ?>"><?php esc_html_e( 'Pending actions', 'reactwoo-geo-ai' ); ?></a>
			<?php if ( class_exists( 'RWGC_Admin_UI', false ) && RWGC_Admin_UI::is_plugin_active( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' ) ) : ?>
				<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-dashboard' ) ); ?>"><?php esc_html_e( 'Geo Optimise', 'reactwoo-geo-ai' ); ?></a>
			<?php endif; ?>
		</p>
	</div>

	<?php if ( $rwga_graph && isset( $rwga_graph['graph'] ) && is_array( $rwga_graph['graph'] ) ) : ?>
		<?php
		$graph = $rwga_graph['graph'];
		$counts = isset( $graph['counts'] ) && is_array( $graph['counts'] ) ? $graph['counts'] : array();
		$nodes  = isset( $graph['nodes'] ) && is_array( $graph['nodes'] ) ? $graph['nodes'] : array();
		$edges  = isset( $graph['edges'] ) && is_array( $graph['edges'] ) ? $graph['edges'] : array();
		$rwga_target_edges = array();
		$rwga_core_edges   = array();
		foreach ( $edges as $edge ) {
			if ( ! is_array( $edge ) ) {
				continue;
			}
			$etype = isset( $edge['type'] ) ? (string) $edge['type'] : '';
			if ( 0 === strpos( $etype, 'targets_' ) ) {
				$rwga_target_edges[] = $edge;
			} else {
				$rwga_core_edges[] = $edge;
			}
		}
		?>
		<div class="rwgc-card rwga-intel-graph-card" style="margin-bottom: 1.5rem;">
			<h2><?php esc_html_e( 'Relationship graph', 'reactwoo-geo-ai' ); ?></h2>
			<p class="description">
				<?php
				printf(
					/* translators: 1: snapshot hash prefix, 2: node count, 3: edge count */
					esc_html__( 'From snapshot %1$s — %2$d nodes, %3$d edges (rules: %4$d, variants: %5$d, popups: %6$d, campaigns: %7$d, audiences: %8$d, profiles: %9$d, experiments: %10$d, commerce rules: %11$d, targeting links: %12$d).', 'reactwoo-geo-ai' ),
					esc_html( isset( $graph['snapshot_hash'] ) ? substr( (string) $graph['snapshot_hash'], 0, 12 ) . '…' : '—' ),
					(int) ( $counts['nodes'] ?? count( $nodes ) ),
					(int) ( $counts['edges'] ?? count( $edges ) ),
					(int) ( $counts['rules'] ?? 0 ),
					(int) ( $counts['variants'] ?? 0 ),
					(int) ( $counts['popups'] ?? 0 ),
					(int) ( $counts['campaigns'] ?? 0 ),
					(int) ( $counts['audiences'] ?? 0 ),
					(int) ( $counts['profiles'] ?? 0 ),
					(int) ( $counts['experiments'] ?? 0 ),
					(int) ( $counts['commerce_rules'] ?? 0 ),
					(int) ( $counts['targeting_edges'] ?? count( $rwga_target_edges ) )
				);
				?>
			</p>
			<?php if ( ! empty( $rwga_target_edges ) ) : ?>
				<h3><?php esc_html_e( 'Pro targeting links', 'reactwoo-geo-ai' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Portable rules linked to synced Google Ads campaigns or GA4 audiences.', 'reactwoo-geo-ai' ); ?></p>
				<table class="widefat striped rwga-intel-graph-table rwga-intel-graph-table--targeting">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Type', 'reactwoo-geo-ai' ); ?></th>
							<th><?php esc_html_e( 'From', 'reactwoo-geo-ai' ); ?></th>
							<th><?php esc_html_e( 'To', 'reactwoo-geo-ai' ); ?></th>
							<th><?php esc_html_e( 'Note', 'reactwoo-geo-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $rwga_target_edges, 0, 25 ) as $edge ) : ?>
							<?php
							$meta = isset( $edge['meta'] ) && is_array( $edge['meta'] ) ? $edge['meta'] : array();
							$note = isset( $meta['condition_type'] ) ? (string) $meta['condition_type'] : '';
							?>
							<tr>
								<td><code><?php echo esc_html( isset( $edge['type'] ) ? (string) $edge['type'] : '' ); ?></code></td>
								<td><?php echo esc_html( isset( $edge['from'] ) ? (string) $edge['from'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $edge['to'] ) ? (string) $edge['to'] : '' ); ?></td>
								<td><?php echo esc_html( $note ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php if ( ! empty( $rwga_core_edges ) ) : ?>
				<h3><?php esc_html_e( 'Core & satellite relationships', 'reactwoo-geo-ai' ); ?></h3>
				<table class="widefat striped rwga-intel-graph-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Type', 'reactwoo-geo-ai' ); ?></th>
							<th><?php esc_html_e( 'From', 'reactwoo-geo-ai' ); ?></th>
							<th><?php esc_html_e( 'To', 'reactwoo-geo-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $rwga_core_edges, 0, 40 ) as $edge ) : ?>
							<?php if ( ! is_array( $edge ) ) { continue; } ?>
							<tr>
								<td><code><?php echo esc_html( isset( $edge['type'] ) ? (string) $edge['type'] : '' ); ?></code></td>
								<td><?php echo esc_html( isset( $edge['from'] ) ? (string) $edge['from'] : '' ); ?></td>
								<td><?php echo esc_html( isset( $edge['to'] ) ? (string) $edge['to'] : '' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( count( $rwga_core_edges ) > 40 ) : ?>
					<p class="description"><?php esc_html_e( 'Showing first 40 core relationships.', 'reactwoo-geo-ai' ); ?></p>
				<?php endif; ?>
			<?php elseif ( empty( $rwga_target_edges ) ) : ?>
				<p><?php esc_html_e( 'No relationship edges in the latest snapshot.', 'reactwoo-geo-ai' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Recent cloud runs', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( empty( $rwga_runs ) ) : ?>
			<p><?php esc_html_e( 'No intelligence runs stored yet. Run a site intelligence workflow (remote mode) after syncing.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'When', 'reactwoo-geo-ai' ); ?></th>
						<th><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></th>
						<th><?php esc_html_e( 'Provider', 'reactwoo-geo-ai' ); ?></th>
						<th><?php esc_html_e( 'Findings', 'reactwoo-geo-ai' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'reactwoo-geo-ai' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_runs as $run ) : ?>
						<?php
						if ( ! is_array( $run ) ) {
							continue;
						}
						$run_id = isset( $run['run_id'] ) ? (string) $run['run_id'] : '';
						$detail_url = add_query_arg( 'run_id', rawurlencode( $run_id ), $base_url );
						?>
						<tr<?php echo $run_id && $run_id === $rwga_selected_run ? ' class="rwga-row--selected"' : ''; ?>>
							<td><?php echo esc_html( isset( $run['created_at'] ) ? (string) $run['created_at'] : '' ); ?></td>
							<td><code><?php echo esc_html( isset( $run['workflow_key'] ) ? (string) $run['workflow_key'] : '' ); ?></code></td>
							<td><?php echo esc_html( isset( $run['provider'] ) ? (string) $run['provider'] : '' ); ?><?php echo ! empty( $run['cache_hit'] ) ? ' <span class="description">(' . esc_html__( 'cached', 'reactwoo-geo-ai' ) . ')</span>' : ''; ?></td>
							<td><?php echo esc_html( (string) (int) ( $run['findings_count'] ?? 0 ) ); ?></td>
							<td><?php echo esc_html( (string) (int) ( $run['actions_count'] ?? 0 ) ); ?></td>
							<td><a href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'View', 'reactwoo-geo-ai' ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<?php if ( $rwga_run_detail ) : ?>
		<?php
		$rwga_run_workflow = isset( $rwga_run_detail['workflow_key'] ) ? sanitize_key( (string) $rwga_run_detail['workflow_key'] ) : '';
		$rwga_optimise_url = '';
		if ( class_exists( 'RWGA_Intelligence_Optimise_Handoff', false )
			&& RWGA_Intelligence_Optimise_Handoff::is_available()
			&& RWGA_Intelligence_Optimise_Handoff::supports_workflow( $rwga_run_workflow ) ) {
			$handoff = RWGA_Intelligence_Optimise_Handoff::build_from_cloud_run( $rwga_run_detail );
			if ( ! is_wp_error( $handoff ) ) {
				$rwga_optimise_url = (string) $handoff;
			}
		}
		?>
		<div class="rwgc-card" style="margin-top: 1.5rem;">
			<h2><?php esc_html_e( 'Run detail', 'reactwoo-geo-ai' ); ?></h2>
			<?php if ( ! empty( $rwga_run_detail['summary'] ) ) : ?>
				<p><strong><?php esc_html_e( 'Summary', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) $rwga_run_detail['summary'] ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $rwga_optimise_url ) : ?>
				<p class="rwgc-actions">
					<a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( $rwga_optimise_url ); ?>"><?php esc_html_e( 'Create Geo Optimise test', 'reactwoo-geo-ai' ); ?></a>
					<span class="description"><?php esc_html_e( 'Opens Create Test with fields prefilled from this intelligence run. You still publish the experiment manually.', 'reactwoo-geo-ai' ); ?></span>
				</p>
			<?php endif; ?>
			<?php
			$result = isset( $rwga_run_detail['result'] ) && is_array( $rwga_run_detail['result'] )
				? $rwga_run_detail['result']
				: $rwga_run_detail;
			$findings = isset( $result['findings'] ) && is_array( $result['findings'] ) ? $result['findings'] : array();
			$recs     = isset( $result['recommendations'] ) && is_array( $result['recommendations'] ) ? $result['recommendations'] : array();
			?>
			<?php if ( ! empty( $findings ) ) : ?>
				<h3><?php esc_html_e( 'Findings', 'reactwoo-geo-ai' ); ?></h3>
				<ul class="rwga-intel-findings-list">
					<?php foreach ( $findings as $finding ) : ?>
						<?php if ( ! is_array( $finding ) ) { continue; } ?>
						<li>
							<strong><?php echo esc_html( isset( $finding['title'] ) ? (string) $finding['title'] : '' ); ?></strong>
							<?php if ( ! empty( $finding['detail'] ) ) : ?>
								— <?php echo esc_html( (string) $finding['detail'] ); ?>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $recs ) ) : ?>
				<h3><?php esc_html_e( 'Recommendations', 'reactwoo-geo-ai' ); ?></h3>
				<ul>
					<?php foreach ( $recs as $rec ) : ?>
						<?php if ( ! is_array( $rec ) ) { continue; } ?>
						<li><?php echo esc_html( isset( $rec['title'] ) ? (string) $rec['title'] : '' ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p><a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Back to list', 'reactwoo-geo-ai' ); ?></a></p>
		</div>
	<?php endif; ?>
</div>
