<?php
/**
 * Automation rules — list, add, edit, run (dispatches workflows when configured).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rows            = isset( $rwga_rows ) && is_array( $rwga_rows ) ? $rwga_rows : array();
$rwga_pagination      = isset( $rwga_pagination ) && is_array( $rwga_pagination ) ? $rwga_pagination : array(
	'total'   => 0,
	'pages'   => 1,
	'current' => 1,
);
$rwga_workflow_keys   = isset( $rwga_workflow_keys ) && is_array( $rwga_workflow_keys ) ? $rwga_workflow_keys : array();
$rwga_edit_rule       = isset( $rwga_edit_rule ) && is_array( $rwga_edit_rule ) ? $rwga_edit_rule : null;
$rwgc_nav_current     = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-automation';

$can_manage = current_user_can( RWGA_Capabilities::CAP_MANAGE_AUTOMATIONS ) || current_user_can( 'manage_options' );
$can_run    = current_user_can( RWGA_Capabilities::CAP_RUN_AI )
	&& class_exists( 'RWGA_License', false )
	&& RWGA_License::can_run_workflows();

$list_url = admin_url( 'admin.php?page=rwga-automation' );

$edit_notes               = '';
$rwga_auto_page_url       = '';
$rwga_auto_competitor     = '';
$rwga_auto_analysis_focus = 'inherit';
if ( is_array( $rwga_edit_rule ) && isset( $rwga_edit_rule['rule_config'] ) ) {
	$rc  = $rwga_edit_rule['rule_config'];
	$dec = null;
	if ( is_string( $rc ) && '' !== $rc ) {
		$dec = json_decode( $rc, true );
	} elseif ( is_array( $rc ) ) {
		$dec = $rc;
	}
	if ( is_array( $dec ) ) {
		if ( isset( $dec['notes'] ) ) {
			$edit_notes = (string) $dec['notes'];
		}
		if ( isset( $dec['page_url'] ) ) {
			$rwga_auto_page_url = (string) $dec['page_url'];
		}
		if ( isset( $dec['competitor_url'] ) ) {
			$rwga_auto_competitor = (string) $dec['competitor_url'];
		}
		if ( isset( $dec['analysis_focus'] ) ) {
			$af = sanitize_key( (string) $dec['analysis_focus'] );
			if ( in_array( $af, array( 'inherit', 'messaging', 'layout', 'both' ), true ) ) {
				$rwga_auto_analysis_focus = $af;
			}
		}
	}
}
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--automation">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Automation', 'reactwoo-geo-ai' ),
			__( 'Run analyses or research on a schedule, or trigger a rule manually when you are ready.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Automation rules', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php
	$rwga_auto = isset( $_GET['rwga_auto'] ) ? sanitize_key( wp_unslash( $_GET['rwga_auto'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'saved' === $rwga_auto ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule saved.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'deleted' === $rwga_auto ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule deleted.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'ran' === $rwga_auto ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Rule run completed.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'unlicensed' === $rwga_auto ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to run rules.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'bad' === $rwga_auto || 'fail' === $rwga_auto ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not complete that action.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'runerr' === $rwga_auto && ! empty( $_GET['rwga_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	?>

	<?php if ( $can_manage ) : ?>
		<?php if ( is_array( $rwga_edit_rule ) ) : ?>
			<div class="rwgc-card">
				<h2><?php esc_html_e( 'Edit rule', 'reactwoo-geo-ai' ); ?></h2>
				<p class="rwgc-actions"><a class="rwgc-btn rwgc-btn--tertiary" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Cancel edit', 'reactwoo-geo-ai' ); ?></a></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rwga_automation_rule_save" />
					<input type="hidden" name="rule_id" value="<?php echo isset( $rwga_edit_rule['id'] ) ? (int) $rwga_edit_rule['id'] : 0; ?>" />
					<?php wp_nonce_field( 'rwga_automation_rule_save' ); ?>
					<?php
					rwga_render_automation_rule_fields(
						$rwga_edit_rule,
						$edit_notes,
						$rwga_workflow_keys,
						$rwga_auto_page_url,
						$rwga_auto_competitor,
						$rwga_auto_analysis_focus
					);
					?>
					<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Update rule', 'reactwoo-geo-ai' ); ?></button>
				</form>
			</div>
		<?php else : ?>
			<div class="rwgc-card" id="rwga-ar-top">
				<h2><?php esc_html_e( 'Add rule', 'reactwoo-geo-ai' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Choose a workflow, scope, and optional page or country. Advanced shows API details.', 'reactwoo-geo-ai' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="rwga_automation_rule_save" />
					<input type="hidden" name="rule_id" value="0" />
					<?php wp_nonce_field( 'rwga_automation_rule_save' ); ?>
					<?php
					rwga_render_automation_rule_fields(
						array(
							'name'          => '',
							'workflow_key'  => '',
							'trigger_type'  => 'manual',
							'target_scope'  => 'site',
							'page_id'       => '',
							'geo_target'    => '',
							'status'        => 'active',
						),
						'',
						$rwga_workflow_keys,
						'',
						'',
						'inherit'
					);
					?>
					<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Create rule', 'reactwoo-geo-ai' ); ?></button>
				</form>
			</div>
		<?php endif; ?>
	<?php else : ?>
		<div class="rwgc-card">
			<p class="description"><?php esc_html_e( 'You need permission to create or edit automation rules.', 'reactwoo-geo-ai' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Rules', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( empty( $rwga_rows ) ) : ?>
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) && $can_manage ) {
				RWGC_Admin_UI::render_empty_state(
					__( 'No automation rules yet', 'reactwoo-geo-ai' ),
					__( 'Create a rule to run a workflow on a schedule or when you click Run.', 'reactwoo-geo-ai' ),
					array(
						array(
							'url'     => '#rwga-ar-top',
							'label'   => __( 'Create your first rule', 'reactwoo-geo-ai' ),
							'primary' => true,
						),
					),
					array( 'dashicon' => 'dashicons-controls-repeat' )
				);
			} else {
				echo '<p class="description">' . esc_html__( 'No rules yet.', 'reactwoo-geo-ai' ) . '</p>';
			}
			?>
		<?php else : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Name', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Trigger', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last run (UTC)', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_rows as $row ) : ?>
						<?php
						$rid = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$edit = add_query_arg( array( 'page' => 'rwga-automation', 'rule_id' => $rid ), admin_url( 'admin.php' ) );
						?>
						<tr>
							<td><?php echo (int) $rid; ?></td>
							<td>
								<?php if ( $can_manage ) : ?>
									<a href="<?php echo esc_url( $edit ); ?>"><?php echo isset( $row['name'] ) ? esc_html( (string) $row['name'] ) : ''; ?></a>
								<?php else : ?>
									<?php echo isset( $row['name'] ) ? esc_html( (string) $row['name'] ) : ''; ?>
								<?php endif; ?>
							</td>
							<td><code><?php echo isset( $row['workflow_key'] ) ? esc_html( (string) $row['workflow_key'] ) : ''; ?></code></td>
							<td><?php echo isset( $row['trigger_type'] ) ? esc_html( (string) $row['trigger_type'] ) : ''; ?></td>
							<td><?php echo isset( $row['status'] ) ? esc_html( (string) $row['status'] ) : ''; ?></td>
							<td><?php echo isset( $row['last_run_at'] ) && $row['last_run_at'] ? esc_html( (string) $row['last_run_at'] ) : '—'; ?></td>
							<td>
								<?php if ( $can_run && isset( $row['status'] ) && 'active' === $row['status'] ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<input type="hidden" name="action" value="rwga_automation_rule_run" />
										<input type="hidden" name="rule_id" value="<?php echo (int) $rid; ?>" />
										<?php wp_nonce_field( 'rwga_automation_rule_run' ); ?>
										<button type="submit" class="rwgc-btn rwgc-btn--secondary rwgc-btn--sm"><?php esc_html_e( 'Run', 'reactwoo-geo-ai' ); ?></button>
									</form>
								<?php endif; ?>
								<?php if ( $can_manage ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'reactwoo-geo-ai' ) ); ?>');">
										<input type="hidden" name="action" value="rwga_automation_rule_delete" />
										<input type="hidden" name="rule_id" value="<?php echo (int) $rid; ?>" />
										<?php wp_nonce_field( 'rwga_automation_rule_delete' ); ?>
										<button type="submit" class="rwgc-btn rwgc-btn--danger rwgc-btn--sm"><?php esc_html_e( 'Delete', 'reactwoo-geo-ai' ); ?></button>
									</form>
								<?php endif; ?>
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
</div>
