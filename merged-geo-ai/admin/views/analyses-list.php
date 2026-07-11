<?php
/**
 * Analyses list screen.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rows       = isset( $rwga_rows ) && is_array( $rwga_rows ) ? $rwga_rows : array();
$rwga_pagination = isset( $rwga_pagination ) && is_array( $rwga_pagination ) ? $rwga_pagination : array(
	'total'   => 0,
	'pages'   => 1,
	'current' => 1,
);
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-analyses';
$rwga_filters    = isset( $rwga_filters ) && is_array( $rwga_filters ) ? $rwga_filters : array();

$list_url = ( defined( 'RWGO_AI_EMBEDDED' ) && RWGO_AI_EMBEDDED && class_exists( 'RWGO_Optimise_Hub', false ) )
	? RWGO_Optimise_Hub::tab_url( 'history' )
	: admin_url( 'admin.php?page=rwga-analyses' );
$rwgo_hub_embed = ! empty( $rwgo_optimise_hub_embed );
?>
<?php if ( ! $rwgo_hub_embed ) : ?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--analyses">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Report library', 'reactwoo-geo-ai' ),
			__( 'Browse completed analysis reports and reopen any run to continue generating recommendations.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Analyses', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php if ( ! $rwgo_hub_embed ) : ?>
		<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php endif; ?>
<?php else : ?>
<div class="rwgo-optimise-hub__embed rwgo-optimise-hub__embed--history">
<?php endif; ?>
	<?php RWGA_Admin::render_current_workflow_state(); ?>

	<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) : ?>
	<div class="rwgc-card">
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_button_row(
				array(
					array(
						'url'     => admin_url( 'admin.php?page=' . RWGA_Admin::MENU_PARENT ),
						'label'   => __( 'Analyse a page', 'reactwoo-geo-ai' ),
						'variant' => 'primary',
					),
				)
			);
		}
		?>
	</div>
	<?php endif; ?>

	<?php if ( isset( $_GET['rwga_sample'] ) && 'ok' === sanitize_key( wp_unslash( $_GET['rwga_sample'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Analysis complete. Open the report to generate recommendations.', 'reactwoo-geo-ai' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwga_analysis'] ) && 'deleted' === sanitize_key( wp_unslash( $_GET['rwga_analysis'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Analysis and linked recommendations/drafts were deleted.', 'reactwoo-geo-ai' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-card">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rwga-rec-filter">
			<input type="hidden" name="page" value="rwga-analyses" />
			<select name="asset_type" class="rwgc-select rwgc-input">
				<option value=""><?php esc_html_e( 'All assets', 'reactwoo-geo-ai' ); ?></option>
				<option value="page" <?php selected( 'page', isset( $rwga_filters['asset_type'] ) ? $rwga_filters['asset_type'] : '' ); ?>><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></option>
				<option value="product" <?php selected( 'product', isset( $rwga_filters['asset_type'] ) ? $rwga_filters['asset_type'] : '' ); ?>><?php esc_html_e( 'Product', 'reactwoo-geo-ai' ); ?></option>
				<option value="variant" <?php selected( 'variant', isset( $rwga_filters['asset_type'] ) ? $rwga_filters['asset_type'] : '' ); ?>><?php esc_html_e( 'Variant', 'reactwoo-geo-ai' ); ?></option>
				<option value="competitor" <?php selected( 'competitor', isset( $rwga_filters['asset_type'] ) ? $rwga_filters['asset_type'] : '' ); ?>><?php esc_html_e( 'Competitor', 'reactwoo-geo-ai' ); ?></option>
			</select>
			<select name="lifecycle_status" class="rwgc-select rwgc-input">
				<option value=""><?php esc_html_e( 'All statuses', 'reactwoo-geo-ai' ); ?></option>
				<option value="analysed" <?php selected( 'analysed', isset( $rwga_filters['lifecycle_status'] ) ? $rwga_filters['lifecycle_status'] : '' ); ?>><?php esc_html_e( 'Analysed', 'reactwoo-geo-ai' ); ?></option>
				<option value="recommendations_generated" <?php selected( 'recommendations_generated', isset( $rwga_filters['lifecycle_status'] ) ? $rwga_filters['lifecycle_status'] : '' ); ?>><?php esc_html_e( 'Recommendations generated', 'reactwoo-geo-ai' ); ?></option>
			</select>
			<input type="date" name="from_date" value="<?php echo esc_attr( isset( $rwga_filters['from_date'] ) ? (string) $rwga_filters['from_date'] : '' ); ?>" />
			<input type="date" name="to_date" value="<?php echo esc_attr( isset( $rwga_filters['to_date'] ) ? (string) $rwga_filters['to_date'] : '' ); ?>" />
			<button type="submit" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Filter', 'reactwoo-geo-ai' ); ?></button>
		</form>
		<?php if ( empty( $rwga_rows ) ) : ?>
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_empty_state(
					__( 'No analyses yet', 'reactwoo-geo-ai' ),
					__( 'Start from the dashboard: pick a page and run an analysis.', 'reactwoo-geo-ai' ),
					array(
						array(
							'url'     => admin_url( 'admin.php?page=' . RWGA_Admin::MENU_PARENT ),
							'label'   => __( 'Start workflow', 'reactwoo-geo-ai' ),
							'primary' => true,
						),
					),
					array( 'dashicon' => 'dashicons-visibility' )
				);
			} else {
				echo '<p class="description">' . esc_html__( 'No analyses yet.', 'reactwoo-geo-ai' ) . '</p>';
			}
			?>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Score', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Lifecycle', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Country', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date (UTC)', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_rows as $row ) : ?>
						<?php
						$rid = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
						$pt  = $pid > 0 ? get_the_title( $pid ) : '';
						$detail_url = add_query_arg(
							array(
								'page'   => 'rwga-analyses',
								'run_id' => $rid,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td><?php echo (int) $rid; ?></td>
							<td>
								<?php if ( $pid > 0 && '' !== $pt ) : ?>
									<strong><?php echo esc_html( $pt ); ?></strong>
									<?php if ( current_user_can( 'edit_post', $pid ) ) : ?>
										<br /><a href="<?php echo esc_url( get_edit_post_link( $pid, 'raw' ) ); ?>"><?php esc_html_e( 'Edit', 'reactwoo-geo-ai' ); ?></a>
									<?php endif; ?>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo isset( $row['score'] ) && null !== $row['score'] ? esc_html( (string) $row['score'] ) : '—'; ?></td>
							<td><code><?php echo isset( $row['workflow_key'] ) ? esc_html( (string) $row['workflow_key'] ) : '—'; ?></code></td>
							<td><?php echo ! empty( $row['lifecycle_status'] ) ? esc_html( (string) $row['lifecycle_status'] ) : esc_html__( 'analysed', 'reactwoo-geo-ai' ); ?></td>
							<td><?php echo isset( $row['geo_target'] ) && $row['geo_target'] ? esc_html( (string) $row['geo_target'] ) : '—'; ?></td>
							<td><?php echo isset( $row['created_at'] ) ? esc_html( (string) $row['created_at'] ) : '—'; ?></td>
							<td>
								<a class="rwgc-btn rwgc-btn--sm rwgc-btn--secondary" href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'Open report', 'reactwoo-geo-ai' ); ?></a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:6px;">
									<input type="hidden" name="action" value="rwga_analysis_delete" />
									<input type="hidden" name="run_id" value="<?php echo (int) $rid; ?>" />
									<?php wp_nonce_field( 'rwga_analysis_delete' ); ?>
									<button type="submit" class="rwgc-btn rwgc-btn--sm rwgc-btn--tertiary" onclick="return confirm('<?php echo esc_js( __( 'Delete this analysis and all linked recommendations/drafts?', 'reactwoo-geo-ai' ) ); ?>');"><?php esc_html_e( 'Delete', 'reactwoo-geo-ai' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php
			$total_pages = isset( $rwga_pagination['pages'] ) ? (int) $rwga_pagination['pages'] : 1;
			$current     = isset( $rwga_pagination['current'] ) ? (int) $rwga_pagination['current'] : 1;
			if ( $total_pages > 1 ) {
				$base = esc_url_raw( add_query_arg( 'paged', '%#%', $list_url ) );
				echo '<div class="tablenav"><div class="tablenav-pages">';
				echo paginate_links(
					array(
						'base'      => $base,
						'format'    => '',
						'prev_text' => __( '&laquo;', 'reactwoo-geo-ai' ),
						'next_text' => __( '&raquo;', 'reactwoo-geo-ai' ),
						'total'     => $total_pages,
						'current'   => $current,
					)
				);
				echo '</div></div>';
			}
			?>
		<?php endif; ?>
	</div>
<?php if ( ! $rwgo_hub_embed ) : ?>
</div>
<?php else : ?>
</div>
<?php endif; ?>
