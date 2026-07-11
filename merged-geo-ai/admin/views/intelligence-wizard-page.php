<?php
/**
 * Guided site intelligence wizard.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-intelligence-wizard';
$rwga_steps       = isset( $rwga_steps ) && is_array( $rwga_steps ) ? $rwga_steps : array();
$rwga_progress    = isset( $rwga_progress ) && is_array( $rwga_progress ) ? $rwga_progress : array(
	'completed'    => 0,
	'total'        => 0,
	'percent'      => 0,
	'current_step' => 'license',
);
$rwga_auto_audit  = ! empty( $rwga_auto_audit );
$rwga_setup_url   = wp_nonce_url(
	admin_url( 'admin-post.php?action=rwga_intelligence_wizard_setup' ),
	'rwga_intelligence_wizard_setup'
);
$rwga_audit_only_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=rwga_intelligence_wizard_audit' ),
	'rwga_intelligence_wizard_audit'
);
$rwga_targeted_audits = isset( $rwga_targeted_audits ) && is_array( $rwga_targeted_audits ) ? $rwga_targeted_audits : array();
$rwga_pending_count   = isset( $rwga_pending_count ) ? (int) $rwga_pending_count : 0;
$rwga_sync_status     = isset( $rwga_sync_status ) && is_array( $rwga_sync_status ) ? $rwga_sync_status : array();
$rwga_quota_blocked   = ! empty( $rwga_quota_blocked );
$rwga_has_prior_sync  = ! empty( $rwga_has_prior_sync );
$rwga_quota           = isset( $rwga_quota ) && is_array( $rwga_quota ) ? $rwga_quota : array(
	'used'  => 0,
	'limit' => 0,
	'month' => '',
);
$rwga_toggle_auto_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=rwga_intelligence_wizard_toggle_auto' ),
	'rwga_intelligence_wizard_toggle_auto'
);
$can_run = current_user_can( RWGA_Capabilities::CAP_RUN_AI );
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--intelligence-wizard">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Site intelligence', 'reactwoo-geo-ai' ),
			__( 'One guided path: connect, sync your site snapshot, run an audit, then approve or dismiss suggestions. Nothing changes on your site without your approval.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Site intelligence', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( $rwga_pending_count > 0 ) : ?>
		<div class="notice notice-warning" style="max-width:820px;">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: pending count */
						_n(
							'%d intelligence suggestion is waiting for approve or dismiss.',
							'%d intelligence suggestions are waiting for approve or dismiss.',
							$rwga_pending_count,
							'reactwoo-geo-ai'
						),
						$rwga_pending_count
					)
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-intelligence-actions&status=pending' ) ); ?>"><?php esc_html_e( 'Review pending actions', 'reactwoo-geo-ai' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<?php
	$flash = isset( $_GET['rwga_wizard'] ) ? sanitize_key( wp_unslash( $_GET['rwga_wizard'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'setup_ok' === $flash ) {
		$n = isset( $_GET['rwga_pending'] ) ? (int) $_GET['rwga_pending'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>';
		if ( $n > 0 ) {
			echo esc_html(
				sprintf(
					/* translators: %d: number of pending actions */
					_n(
						'Automated setup complete. Sync and site audit finished — %d suggestion is ready for review in step 5.',
						'Automated setup complete. Sync and site audit finished — %d suggestions are ready for review in step 5.',
						$n,
						'reactwoo-geo-ai'
					),
					$n
				)
			);
		} else {
			esc_html_e( 'Automated setup complete. Sync and site audit finished. No approval-gated suggestions this run.', 'reactwoo-geo-ai' );
		}
		echo '</p></div>';
	} elseif ( 'sync_ok' === $flash ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Site snapshot synced. Continue with Run site audit.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'audit_ok' === $flash ) {
		$n  = isset( $_GET['rwga_pending'] ) ? (int) $_GET['rwga_pending'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wk = isset( $_GET['rwga_wk'] ) ? sanitize_key( wp_unslash( (string) $_GET['rwga_wk'] ) ) : 'site_audit'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$label = __( 'Site audit', 'reactwoo-geo-ai' );
		if ( class_exists( 'RWGA_Workflow_Intelligence_Definitions', false ) ) {
			$defs = RWGA_Workflow_Intelligence_Definitions::get_definitions();
			if ( isset( $defs[ $wk ]['label'] ) ) {
				$label = (string) $defs[ $wk ]['label'];
			}
		}
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			$n > 0
				? sprintf(
					/* translators: 1: audit label, 2: pending count */
					_n( '%1$s complete. %2$d suggestion to review below.', '%1$s complete. %2$d suggestions to review below.', $n, 'reactwoo-geo-ai' ),
					$label,
					$n
				)
				: sprintf(
					/* translators: %s: audit label */
					__( '%s complete. No pending suggestions this run.', 'reactwoo-geo-ai' ),
					$label
				)
		);
		echo '</p></div>';
	} elseif ( 'auto_on' === $flash || 'auto_off' === $flash ) {
		echo '<div class="notice notice-info is-dismissible"><p>';
		echo 'auto_on' === $flash
			? esc_html__( 'Automatic site audit after sync is now on.', 'reactwoo-geo-ai' )
			: esc_html__( 'Automatic site audit after sync is now off.', 'reactwoo-geo-ai' );
		echo '</p></div>';
	} elseif ( 'error' === $flash ) {
		$err = isset( $_GET['rwga_err'] ) ? sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $err ? $err : __( 'Setup could not be completed.', 'reactwoo-geo-ai' ) ) . '</p></div>';
	}
	?>

	<?php if ( $rwga_quota_blocked || ( (int) $rwga_quota['limit'] > 0 && (int) $rwga_quota['used'] > 0 ) ) : ?>
		<div class="notice notice-warning" style="max-width:820px;">
			<p>
				<?php if ( $rwga_quota_blocked ) : ?>
					<strong><?php esc_html_e( 'Snapshot upload quota', 'reactwoo-geo-ai' ); ?></strong>
					<?php
					echo esc_html(
						RWGA_Site_Snapshot_Client::format_snapshot_quota_message(
							array(
								'used'  => (int) $rwga_quota['used'],
								'limit' => (int) $rwga_quota['limit'],
							)
						)
					);
					?>
				<?php elseif ( (int) $rwga_quota['limit'] > 0 ) : ?>
					<strong><?php esc_html_e( 'Snapshot upload quota', 'reactwoo-geo-ai' ); ?></strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: uploads used, 2: monthly limit */
							__( '%1$d of %2$d snapshot uploads used this month.', 'reactwoo-geo-ai' ),
							(int) $rwga_quota['used'],
							(int) $rwga_quota['limit']
						)
					);
					?>
				<?php endif; ?>
				<?php if ( $rwga_has_prior_sync ) : ?>
					<?php esc_html_e( 'Your last snapshot is already in the cloud — use Run site audit only to continue without a new upload.', 'reactwoo-geo-ai' ); ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="rwgc-card rwga-intel-wizard-hero" style="max-width:820px;margin-bottom:1.5rem;">
		<h2><?php esc_html_e( 'Quick start', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Run everything in one go: sync your latest Geo snapshot to the cloud, then run a site audit. You will land on the review step when suggestions are ready.', 'reactwoo-geo-ai' ); ?></p>
		<?php if ( (int) $rwga_progress['total'] > 0 ) : ?>
			<p class="rwga-intel-wizard-progress-meta">
				<strong><?php echo esc_html( (string) (int) $rwga_progress['percent'] ); ?>%</strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: completed steps, 2: total steps */
						__( '%1$d of %2$d steps complete', 'reactwoo-geo-ai' ),
						(int) $rwga_progress['completed'],
						(int) $rwga_progress['total']
					)
				);
				?>
			</p>
			<div class="rwgc-platform-overview__bar" role="progressbar" aria-valuenow="<?php echo esc_attr( (string) (int) $rwga_progress['percent'] ); ?>" aria-valuemin="0" aria-valuemax="100" style="max-width:480px;margin:8px 0 16px;">
				<span class="rwgc-platform-overview__bar-fill" style="width:<?php echo esc_attr( (string) (int) $rwga_progress['percent'] ); ?>%"></span>
			</div>
		<?php endif; ?>
		<p class="rwgc-actions">
			<?php if ( $can_run ) : ?>
				<?php if ( ! $rwga_quota_blocked ) : ?>
					<a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( $rwga_setup_url ); ?>"><?php esc_html_e( 'Run automated setup', 'reactwoo-geo-ai' ); ?></a>
				<?php endif; ?>
				<a class="rwgc-btn rwgc-btn--<?php echo $rwga_quota_blocked ? 'primary' : 'secondary'; ?>" href="<?php echo esc_url( $rwga_audit_only_url ); ?>"><?php esc_html_e( 'Run site audit only', 'reactwoo-geo-ai' ); ?></a>
			<?php endif; ?>
			<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-intelligence-actions&status=pending' ) ); ?>"><?php esc_html_e( 'Pending suggestions', 'reactwoo-geo-ai' ); ?></a>
		</p>
		<p class="description" style="margin-top:12px;">
			<?php if ( $rwga_auto_audit ) : ?>
				<?php esc_html_e( 'Automation: site audit runs automatically after each successful sync.', 'reactwoo-geo-ai' ); ?>
				<a href="<?php echo esc_url( $rwga_toggle_auto_url ); ?>"><?php esc_html_e( 'Turn off', 'reactwoo-geo-ai' ); ?></a>
			<?php else : ?>
				<?php esc_html_e( 'Automation: site audit does not run automatically after sync.', 'reactwoo-geo-ai' ); ?>
				<a href="<?php echo esc_url( $rwga_toggle_auto_url ); ?>"><?php esc_html_e( 'Turn on auto-audit after sync', 'reactwoo-geo-ai' ); ?></a>
			<?php endif; ?>
		</p>
	</div>

	<?php if ( ! empty( $rwga_targeted_audits ) ) : ?>
	<div class="rwgc-card" style="max-width:820px;margin-bottom:1.5rem;">
		<h2><?php esc_html_e( 'Targeted audits', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Run focused intelligence workflows after your snapshot is synced. Each audit may create approval-gated suggestions.', 'reactwoo-geo-ai' ); ?></p>
		<ul class="rwga-intel-wizard-targeted">
			<?php foreach ( $rwga_targeted_audits as $audit_key => $audit_row ) : ?>
				<?php
				$audit_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=rwga_intelligence_wizard_audit&workflow_key=' . rawurlencode( (string) $audit_key ) ),
					'rwga_intelligence_wizard_audit'
				);
				?>
				<li class="rwga-intel-wizard-targeted__item">
					<strong><?php echo esc_html( (string) ( $audit_row['label'] ?? $audit_key ) ); ?></strong>
					<?php if ( ! empty( $audit_row['hint'] ) ) : ?>
						<p class="description"><?php echo esc_html( (string) $audit_row['hint'] ); ?></p>
					<?php endif; ?>
					<?php if ( $can_run ) : ?>
						<p><a class="button button-secondary" href="<?php echo esc_url( $audit_url ); ?>"><?php echo esc_html( sprintf( __( 'Run %s', 'reactwoo-geo-ai' ), (string) ( $audit_row['label'] ?? $audit_key ) ) ); ?></a></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>

	<div class="rwgc-card" style="max-width:820px;">
		<h2><?php esc_html_e( 'Step by step', 'reactwoo-geo-ai' ); ?></h2>
		<ol class="rwga-intel-wizard-steps">
			<?php foreach ( $rwga_steps as $index => $step ) : ?>
				<?php
				if ( ! is_array( $step ) ) {
					continue;
				}
				$done     = ! empty( $step['done'] );
				$optional = ! empty( $step['optional'] );
				$is_current = ! $done && ! $optional && isset( $rwga_progress['current_step'] ) && (string) $step['id'] === (string) $rwga_progress['current_step'];
				$cls      = 'rwga-intel-wizard-step' . ( $done ? ' is-done' : '' ) . ( $is_current ? ' is-current' : '' ) . ( $optional ? ' is-optional' : '' );
				?>
				<li class="<?php echo esc_attr( $cls ); ?>">
					<span class="rwga-intel-wizard-step__mark" aria-hidden="true"><?php echo $done ? '✓' : (string) ( $index + 1 ); ?></span>
					<div class="rwga-intel-wizard-step__body">
						<strong><?php echo esc_html( (string) ( $step['label'] ?? '' ) ); ?></strong>
						<?php if ( $optional ) : ?>
							<span class="description"> — <?php esc_html_e( 'Optional', 'reactwoo-geo-ai' ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $step['hint'] ) ) : ?>
							<p class="description"><?php echo esc_html( (string) $step['hint'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! $done && ! empty( $step['url'] ) && ! empty( $step['cta'] ) ) : ?>
							<p><a class="button<?php echo $is_current ? ' button-primary' : ' button-secondary'; ?>" href="<?php echo esc_url( (string) $step['url'] ); ?>"><?php echo esc_html( (string) $step['cta'] ); ?></a></p>
						<?php elseif ( $done && ! empty( $step['url'] ) && ! empty( $step['cta'] ) && ! empty( $step['optional'] ) ) : ?>
							<p><a class="button button-secondary" href="<?php echo esc_url( (string) $step['url'] ); ?>"><?php echo esc_html( (string) $step['cta'] ); ?></a></p>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</div>
<style>
.rwga-intel-wizard-steps { list-style:none; margin:0; padding:0; }
.rwga-intel-wizard-step { display:flex; gap:12px; padding:14px 0; border-bottom:1px solid #e2e8f0; }
.rwga-intel-wizard-step:last-child { border-bottom:0; }
.rwga-intel-wizard-step__mark { flex:0 0 28px; height:28px; line-height:28px; text-align:center; border-radius:999px; background:#f1f5f9; font-weight:600; }
.rwga-intel-wizard-step.is-done .rwga-intel-wizard-step__mark { background:#dcfce7; color:#166534; }
.rwga-intel-wizard-step.is-current .rwga-intel-wizard-step__mark { background:#dbeafe; color:#1d4ed8; }
.rwga-intel-wizard-step__body { flex:1; min-width:0; }
.rwga-intel-wizard-targeted { list-style:none; margin:0; padding:0; }
.rwga-intel-wizard-targeted__item { padding:12px 0; border-bottom:1px solid #e2e8f0; }
.rwga-intel-wizard-targeted__item:last-child { border-bottom:0; }
</style>
