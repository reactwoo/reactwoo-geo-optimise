<?php
/**
 * Intelligence workflow actions awaiting approval.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_rows              = isset( $rwga_rows ) && is_array( $rwga_rows ) ? $rwga_rows : array();
$rwga_pagination        = isset( $rwga_pagination ) && is_array( $rwga_pagination ) ? $rwga_pagination : array(
	'total'   => 0,
	'pages'   => 1,
	'current' => 1,
);
$rwga_filter_status     = isset( $rwga_filter_status ) ? (string) $rwga_filter_status : 'pending';
$rwga_filter_workflow   = isset( $rwga_filter_workflow ) ? (string) $rwga_filter_workflow : '';
$rwgc_nav_current       = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-intelligence-actions';

$list_url = ( defined( 'RWGO_AI_EMBEDDED' ) && RWGO_AI_EMBEDDED && class_exists( 'RWGO_Optimise_Hub', false ) )
	? RWGO_Optimise_Hub::tab_url( 'recommendations' )
	: admin_url( 'admin.php?page=rwga-intelligence-actions' );
$can_run  = current_user_can( RWGA_Capabilities::CAP_RUN_AI )
	&& class_exists( 'RWGA_License', false )
	&& RWGA_License::can_run_workflows();
$rwgo_hub_embed = ! empty( $rwgo_optimise_hub_embed );
?>
<?php if ( ! $rwgo_hub_embed ) : ?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--intelligence-actions">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Intelligence actions', 'reactwoo-geo-ai' ),
			__( 'Review AI-suggested site changes. Nothing is applied until you approve an action here.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Intelligence actions', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php if ( ! $rwgo_hub_embed ) : ?>
		<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php endif; ?>
<?php else : ?>
<div class="rwgo-optimise-hub__embed rwgo-optimise-hub__embed--recommendations">
<?php endif; ?>

	<?php
	$flash = isset( $_GET['rwga_act'] ) ? sanitize_key( wp_unslash( $_GET['rwga_act'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$intel_flash = isset( $_GET['rwga_intel'] ) ? sanitize_key( wp_unslash( $_GET['rwga_intel'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$wizard_flash = isset( $_GET['rwga_wizard'] ) ? sanitize_key( wp_unslash( $_GET['rwga_wizard'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( in_array( $wizard_flash, array( 'setup_ok', 'audit_ok' ), true ) ) {
		$n = isset( $_GET['rwga_pending'] ) ? (int) $_GET['rwga_pending'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			$n > 0
				? sprintf(
					/* translators: %d: pending suggestions */
					_n( '%d suggestion below needs your approve or dismiss decision.', '%d suggestions below need your approve or dismiss decision.', $n, 'reactwoo-geo-ai' ),
					$n
				)
				: __( 'Audit complete. No pending suggestions right now.', 'reactwoo-geo-ai' )
		);
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=rwga-intelligence-wizard' ) ) . '">' . esc_html__( 'Back to site intelligence guide', 'reactwoo-geo-ai' ) . '</a>';
		echo '</p></div>';
	} elseif ( 'ran' === $intel_flash ) {
		$wf_key = isset( $_GET['rwga_wf'] ) ? sanitize_key( wp_unslash( $_GET['rwga_wf'] ) ) : 'site_audit'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$n      = isset( $_GET['rwga_actions'] ) ? (int) $_GET['rwga_actions'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: 1: workflow key, 2: number of pending actions */
				_n(
					'Intelligence workflow “%1$s” completed. %2$d pending action is ready for review below.',
					'Intelligence workflow “%1$s” completed. %2$d pending actions are ready for review below.',
					$n,
					'reactwoo-geo-ai'
				),
				$wf_key,
				$n
			)
		);
		echo '</p></div>';
	} elseif ( 'applied' === $flash ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Intelligence action applied.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'dismissed' === $flash ) {
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Intelligence action dismissed.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'error' === $flash ) {
		$err = isset( $_GET['rwga_err'] ) ? sanitize_text_field( wp_unslash( $_GET['rwga_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $err ? $err : __( 'Action failed.', 'reactwoo-geo-ai' ) ) . '</p></div>';
	}
	?>

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="rwga-filters" style="margin:1em 0;">
		<input type="hidden" name="page" value="rwga-intelligence-actions" />
		<label>
			<?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?>
			<select name="status">
				<?php
				foreach ( array( 'pending', 'applied', 'dismissed', '' ) as $st ) {
					$label = '' === $st ? __( 'All', 'reactwoo-geo-ai' ) : ucfirst( $st );
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $st ),
						selected( $rwga_filter_status, $st, false ),
						esc_html( $label )
					);
				}
				?>
			</select>
		</label>
		<label style="margin-left:1em;">
			<?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?>
			<input type="text" name="workflow_key" value="<?php echo esc_attr( $rwga_filter_workflow ); ?>" placeholder="<?php esc_attr_e( 'e.g. site_audit', 'reactwoo-geo-ai' ); ?>" />
		</label>
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'reactwoo-geo-ai' ); ?></button>
	</form>

	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'reactwoo-geo-ai' ); ?></th>
				<th><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></th>
				<th><?php esc_html_e( 'Action', 'reactwoo-geo-ai' ); ?></th>
				<th><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
				<th><?php esc_html_e( 'Created', 'reactwoo-geo-ai' ); ?></th>
				<th><?php esc_html_e( 'Operations', 'reactwoo-geo-ai' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rwga_rows ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No intelligence actions found. Run a site intelligence workflow to generate suggestions.', 'reactwoo-geo-ai' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $rwga_rows as $row ) : ?>
					<?php
					$id     = isset( $row['id'] ) ? (int) $row['id'] : 0;
					$label  = isset( $row['label'] ) ? (string) $row['label'] : '';
					$wk     = isset( $row['workflow_key'] ) ? (string) $row['workflow_key'] : '';
					$type   = isset( $row['action_type'] ) ? (string) $row['action_type'] : '';
					$status = isset( $row['status'] ) ? (string) $row['status'] : '';
					$created = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';
					?>
					<tr>
						<td><?php echo esc_html( (string) $id ); ?></td>
						<td><code><?php echo esc_html( $wk ); ?></code></td>
						<td>
							<strong><?php echo esc_html( $label ); ?></strong>
							<?php if ( '' !== $type ) : ?>
								<br /><span class="description"><code><?php echo esc_html( $type ); ?></code></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $status ); ?></td>
						<td><?php echo esc_html( $created ); ?></td>
						<td>
							<?php
							$rwga_opt_url = '';
							if ( class_exists( 'RWGA_Intelligence_Optimise_Handoff', false )
								&& RWGA_Intelligence_Optimise_Handoff::is_available()
								&& RWGA_Intelligence_Optimise_Handoff::supports_workflow( $wk ) ) {
								$opt_handoff = RWGA_Intelligence_Optimise_Handoff::build_from_action_row( $row );
								if ( ! is_wp_error( $opt_handoff ) ) {
									$rwga_opt_url = (string) $opt_handoff;
								}
							}
							?>
							<?php if ( '' !== $rwga_opt_url ) : ?>
								<a class="button button-secondary" href="<?php echo esc_url( $rwga_opt_url ); ?>"><?php esc_html_e( 'Create Optimise test', 'reactwoo-geo-ai' ); ?></a>
							<?php endif; ?>
							<?php if ( 'pending' === $status && $can_run ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="rwga_intelligence_action_apply" />
									<input type="hidden" name="action_id" value="<?php echo esc_attr( (string) $id ); ?>" />
									<?php wp_nonce_field( 'rwga_intelligence_action_apply' ); ?>
									<button type="submit" class="button button-primary"><?php esc_html_e( 'Approve & apply', 'reactwoo-geo-ai' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:4px;">
									<input type="hidden" name="action" value="rwga_intelligence_action_dismiss" />
									<input type="hidden" name="action_id" value="<?php echo esc_attr( (string) $id ); ?>" />
									<?php wp_nonce_field( 'rwga_intelligence_action_dismiss' ); ?>
									<button type="submit" class="button"><?php esc_html_e( 'Dismiss', 'reactwoo-geo-ai' ); ?></button>
								</form>
							<?php elseif ( 'pending' === $status ) : ?>
								<span class="description"><?php esc_html_e( 'License required to apply.', 'reactwoo-geo-ai' ); ?></span>
							<?php else : ?>
								<span class="description">—</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( ! empty( $rwga_pagination['pages'] ) && (int) $rwga_pagination['pages'] > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%', $list_url ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => (int) $rwga_pagination['pages'],
							'current'   => (int) $rwga_pagination['current'],
						)
					)
				);
				?>
			</div>
		</div>
	<?php endif; ?>
<?php if ( ! $rwgo_hub_embed ) : ?>
</div>
<?php else : ?>
</div>
<?php endif; ?>
