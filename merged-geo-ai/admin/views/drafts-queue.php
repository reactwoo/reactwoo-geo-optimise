<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwga_queue_rows = isset( $rwga_queue_rows ) && is_array( $rwga_queue_rows ) ? $rwga_queue_rows : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-drafts';

$dash_url     = admin_url( 'admin.php?page=rwga-dashboard' );
$implement_url = admin_url( 'admin.php?page=rwga-implementation-drafts' );
$analyses_url = admin_url( 'admin.php?page=rwga-analyses' );
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--drafts">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Queue', 'reactwoo-geo-ai' ),
			__( 'A holding area for draft jobs from the editor or integrations. Use the main workflow when you are starting out.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Queue', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-card">
		<?php if ( empty( $rwga_queue_rows ) ) : ?>
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_empty_state(
					__( 'Nothing in the queue yet', 'reactwoo-geo-ai' ),
					__( 'When a workflow or integration records a job, it will appear here with any actions your site provides.', 'reactwoo-geo-ai' ),
					array(
						array(
							'url'     => $dash_url,
							'label'   => __( 'Go to dashboard', 'reactwoo-geo-ai' ),
							'primary' => true,
						),
						array(
							'url'   => $analyses_url,
							'label' => __( 'Analyse a page', 'reactwoo-geo-ai' ),
						),
						array(
							'url'   => $implement_url,
							'label' => __( 'Implementation drafts', 'reactwoo-geo-ai' ),
						),
					),
					array( 'dashicon' => 'dashicons-list-view' )
				);
			} else {
				echo '<p class="rwga-empty-hint">' . esc_html__( 'No draft jobs in the queue yet.', 'reactwoo-geo-ai' ) . '</p>';
				echo '<p class="rwgc-actions"><a class="rwgc-btn rwgc-btn--primary" href="' . esc_url( admin_url( 'edit.php?post_type=page' ) ) . '">' . esc_html__( 'Browse pages', 'reactwoo-geo-ai' ) . '</a></p>';
			}
			?>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Source', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Context', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Created', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_queue_rows as $row ) : ?>
						<tr>
							<td><?php echo isset( $row['source_label'] ) ? esc_html( (string) $row['source_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['context_label'] ) ? esc_html( (string) $row['context_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['type_label'] ) ? esc_html( (string) $row['type_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['status_label'] ) ? esc_html( (string) $row['status_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['created_gmt'] ) ? esc_html( (string) $row['created_gmt'] ) : '—'; ?></td>
							<td>
								<?php
								if ( ! empty( $row['actions_html'] ) ) {
									echo wp_kses_post( (string) $row['actions_html'] );
								} else {
									echo '—';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
