<?php
/**
 * Intelligence Centre — local graph, knowledge, context preview, payload audit.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_nav_current      = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-intelligence-centre';
$rwga_site_context     = isset( $rwga_site_context ) && is_array( $rwga_site_context ) ? $rwga_site_context : array();
$rwga_graph            = isset( $rwga_graph ) && is_array( $rwga_graph ) ? $rwga_graph : array();
$rwga_knowledge        = isset( $rwga_knowledge ) && is_array( $rwga_knowledge ) ? $rwga_knowledge : array();
$rwga_context_preview  = isset( $rwga_context_preview ) && is_array( $rwga_context_preview ) ? $rwga_context_preview : array();
$rwga_recent_runs      = isset( $rwga_recent_runs ) && is_array( $rwga_recent_runs ) ? $rwga_recent_runs : array();
$rwga_preview_workflow = isset( $rwga_preview_workflow ) ? sanitize_key( (string) $rwga_preview_workflow ) : 'ux_analysis';
$rwga_preview_page_id  = isset( $rwga_preview_page_id ) ? (int) $rwga_preview_page_id : 0;
$base_url              = admin_url( 'admin.php?page=rwga-intelligence-centre' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--intelligence-centre">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Intelligence Centre', 'reactwoo-geo-ai' ),
			__( 'Local intelligence graph, UX benchmarks, workflow context preview, and payload audit.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Intelligence Centre', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin:1rem 0;">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Site context', 'reactwoo-geo-ai' ); ?></h2>
			<?php if ( empty( $rwga_site_context ) ) : ?>
				<p class="description"><?php esc_html_e( 'No local site context yet. Save a page or sync site intelligence.', 'reactwoo-geo-ai' ); ?></p>
			<?php else : ?>
				<ul>
					<li><strong><?php esc_html_e( 'Industry', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) ( $rwga_site_context['industry'] ?? '—' ) ); ?></li>
					<li><strong><?php esc_html_e( 'Intelligence version', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) ( $rwga_site_context['intelligence_version'] ?? '' ) ); ?></li>
					<li><strong><?php esc_html_e( 'Refreshed', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) ( $rwga_site_context['refreshed_at'] ?? '' ) ); ?></li>
				</ul>
			<?php endif; ?>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Relationship graph', 'reactwoo-geo-ai' ); ?></h2>
			<?php
			$counts = isset( $rwga_graph['counts'] ) && is_array( $rwga_graph['counts'] ) ? $rwga_graph['counts'] : array();
			if ( empty( $counts ) ) :
				?>
				<p class="description"><?php esc_html_e( 'Graph not built yet.', 'reactwoo-geo-ai' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $counts as $key => $val ) : ?>
						<li><strong><?php echo esc_html( (string) $key ); ?>:</strong> <?php echo esc_html( (string) $val ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'UX knowledge', 'reactwoo-geo-ai' ); ?></h2>
			<?php
			$benchmarks = isset( $rwga_knowledge['benchmarks'] ) && is_array( $rwga_knowledge['benchmarks'] ) ? $rwga_knowledge['benchmarks'] : array();
			if ( empty( $benchmarks ) ) :
				?>
				<p class="description"><?php esc_html_e( 'No benchmark rows matched.', 'reactwoo-geo-ai' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( array_slice( $benchmarks, 0, 5 ) as $row ) : ?>
						<?php if ( ! is_array( $row ) ) { continue; } ?>
						<li><?php echo esc_html( (string) ( $row['finding'] ?? '' ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>

	<div class="rwgc-card" style="margin-bottom:1rem;">
		<h2><?php esc_html_e( 'Context preview', 'reactwoo-geo-ai' ); ?></h2>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="rwga-intelligence-centre" />
			<p>
				<label for="rwga_preview_workflow"><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></label>
				<select name="rwga_preview_workflow" id="rwga_preview_workflow">
					<option value="ux_analysis" <?php selected( $rwga_preview_workflow, 'ux_analysis' ); ?>><?php esc_html_e( 'UX analysis', 'reactwoo-geo-ai' ); ?></option>
					<option value="ux_recommend" <?php selected( $rwga_preview_workflow, 'ux_recommend' ); ?>><?php esc_html_e( 'UX recommend', 'reactwoo-geo-ai' ); ?></option>
					<option value="site_audit" <?php selected( $rwga_preview_workflow, 'site_audit' ); ?>><?php esc_html_e( 'Site audit', 'reactwoo-geo-ai' ); ?></option>
				</select>
				<label for="rwga_preview_page_id" style="margin-left:1rem;"><?php esc_html_e( 'Page ID', 'reactwoo-geo-ai' ); ?></label>
				<input type="number" min="0" name="rwga_preview_page_id" id="rwga_preview_page_id" value="<?php echo esc_attr( (string) $rwga_preview_page_id ); ?>" />
				<?php submit_button( __( 'Preview', 'reactwoo-geo-ai' ), 'secondary', '', false ); ?>
			</p>
		</form>
		<?php if ( ! empty( $rwga_context_preview ) ) : ?>
			<details open>
				<summary><?php esc_html_e( 'Remote-ready bundle', 'reactwoo-geo-ai' ); ?></summary>
				<pre style="max-height:320px;overflow:auto;background:#f6f7f7;padding:12px;"><?php echo esc_html( wp_json_encode( $rwga_context_preview['remote_ready'] ?? array(), JSON_PRETTY_PRINT ) ); ?></pre>
			</details>
			<?php if ( ! empty( $rwga_context_preview['_payload_audit']['excluded_keys'] ) ) : ?>
				<p><strong><?php esc_html_e( 'Payload audit — excluded keys', 'reactwoo-geo-ai' ); ?>:</strong>
					<?php echo esc_html( implode( ', ', (array) $rwga_context_preview['_payload_audit']['excluded_keys'] ) ); ?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Recent AI runs', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( empty( $rwga_recent_runs ) ) : ?>
			<p class="description"><?php esc_html_e( 'No AI runs recorded yet.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></th>
						<th><?php esc_html_e( 'Model', 'reactwoo-geo-ai' ); ?></th>
						<th><?php esc_html_e( 'Cache', 'reactwoo-geo-ai' ); ?></th>
						<th><?php esc_html_e( 'When', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_recent_runs as $run ) : ?>
						<?php if ( ! is_array( $run ) ) { continue; } ?>
						<tr>
							<td><?php echo esc_html( (string) ( $run['workflow_key'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $run['model'] ?? '' ) ); ?></td>
							<td><?php echo ! empty( $run['cache_hit'] ) ? esc_html__( 'Hit', 'reactwoo-geo-ai' ) : esc_html__( 'Miss', 'reactwoo-geo-ai' ); ?></td>
							<td><?php echo esc_html( (string) ( $run['created_at'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
