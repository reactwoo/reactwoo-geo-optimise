<?php
/**
 * Single analysis run detail.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwga_run     = isset( $rwga_run ) && is_array( $rwga_run ) ? $rwga_run : array();
$rwga_findings = isset( $rwga_findings ) && is_array( $rwga_findings ) ? $rwga_findings : array();
$rwga_recommendations = isset( $rwga_recommendations ) && is_array( $rwga_recommendations ) ? $rwga_recommendations : array();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-analyses';

$run_id = isset( $rwga_run['id'] ) ? (int) $rwga_run['id'] : 0;
$list_url = admin_url( 'admin.php?page=rwga-analyses' );

$severity_class = array(
	'high'   => 'rwga-severity--high',
	'medium' => 'rwga-severity--medium',
	'low'    => 'rwga-severity--low',
);
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--analysis-detail">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		$run_page_title = '';
		$run_pid_header  = isset( $rwga_run['page_id'] ) ? (int) $rwga_run['page_id'] : 0;
		if ( $run_pid_header > 0 ) {
			$run_page_title = get_the_title( $run_pid_header );
		}
		RWGC_Admin_UI::render_page_header(
			sprintf(
				/* translators: %d: analysis run id */
				__( 'Analysis #%d', 'reactwoo-geo-ai' ),
				$run_id
			),
			'' !== $run_page_title
				? sprintf(
					/* translators: %s: page title */
					__( 'Results for “%s”. Review findings, then generate recommendations when you are ready.', 'reactwoo-geo-ai' ),
					$run_page_title
				)
				: __( 'Review the summary and findings for this run.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php echo esc_html( sprintf( __( 'Analysis #%d', 'reactwoo-geo-ai' ), $run_id ) ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php RWGA_Admin::render_current_workflow_state(); ?>

	<?php
	$rwga_rec_err = isset( $_GET['rwga_rec'] ) ? sanitize_key( wp_unslash( $_GET['rwga_rec'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'error' === $rwga_rec_err && ! empty( $_GET['rwga_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	} elseif ( 'noflow' === $rwga_rec_err ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Recommendation workflow is not available.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'bad' === $rwga_rec_err ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not start recommendations: missing analysis run. Open a report from the report library and try again.', 'reactwoo-geo-ai' ) . '</p></div>';
	}
	$rwga_impl_flag = isset( $_GET['rwga_impl'] ) ? sanitize_key( wp_unslash( $_GET['rwga_impl'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'norecs' === $rwga_impl_flag ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Implementation drafts need saved recommendations for this analysis. Generate a recommendation report first (recommendations may have been deleted).', 'reactwoo-geo-ai' ) . '</p></div>';
	}
	?>

	<div class="rwgc-actions rwgc-actions--stack-mobile" style="margin-bottom: 16px;">
		<a href="<?php echo esc_url( $list_url ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( '← All analyses', 'reactwoo-geo-ai' ); ?></a>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'rwga-recommendations', 'analysis_run' => $run_id ), admin_url( 'admin.php' ) ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'View recommendations for this run', 'reactwoo-geo-ai' ); ?></a>
		<?php
		$pid = isset( $rwga_run['page_id'] ) ? (int) $rwga_run['page_id'] : 0;
		if ( $pid > 0 && current_user_can( 'edit_post', $pid ) ) {
			$edit = get_edit_post_link( $pid, 'raw' );
			if ( is_string( $edit ) && '' !== $edit ) {
				echo '<a class="rwgc-btn rwgc-btn--primary" href="' . esc_url( $edit ) . '">' . esc_html__( 'Edit page', 'reactwoo-geo-ai' ) . '</a>';
			}
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
			<input type="hidden" name="action" value="rwga_analysis_delete" />
			<input type="hidden" name="run_id" value="<?php echo (int) $run_id; ?>" />
			<?php wp_nonce_field( 'rwga_analysis_delete' ); ?>
			<button type="submit" class="rwgc-btn rwgc-btn--tertiary" onclick="return confirm('<?php echo esc_js( __( 'Delete this analysis and all linked recommendations/drafts?', 'reactwoo-geo-ai' ) ); ?>');"><?php esc_html_e( 'Delete analysis', 'reactwoo-geo-ai' ); ?></button>
		</form>
	</div>

	<div class="rwgc-card rwga-analysis-meta">
		<h2><?php esc_html_e( 'Analysis report', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( ! empty( $rwga_run['report_html'] ) ) : ?>
			<div class="rwga-report-html"><?php echo wp_kses_post( (string) $rwga_run['report_html'] ); ?></div>
		<?php endif; ?>
		<h3><?php esc_html_e( 'At a glance', 'reactwoo-geo-ai' ); ?></h3>
		<dl class="rwga-license-dl">
			<dt><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></dt>
			<dd>
				<?php
				$sum_pid = isset( $rwga_run['page_id'] ) ? (int) $rwga_run['page_id'] : 0;
				if ( $sum_pid > 0 ) {
					$pt = get_the_title( $sum_pid );
					echo '' !== $pt ? esc_html( $pt ) : '—';
					echo ' ';
					echo '<span class="description">(ID ' . (int) $sum_pid . ')</span>';
				} else {
					echo '—';
				}
				?>
			</dd>
			<dt><?php esc_html_e( 'Score', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_run['score'] ) && null !== $rwga_run['score'] ? esc_html( (string) $rwga_run['score'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Confidence', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_run['confidence'] ) && null !== $rwga_run['confidence'] ? esc_html( (string) $rwga_run['confidence'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></dt>
			<dd><code><?php echo isset( $rwga_run['workflow_key'] ) ? esc_html( (string) $rwga_run['workflow_key'] ) : '—'; ?></code></dd>
			<dt><?php esc_html_e( 'Target country', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_run['geo_target'] ) && $rwga_run['geo_target'] ? esc_html( (string) $rwga_run['geo_target'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Device', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_run['device_type'] ) && $rwga_run['device_type'] ? esc_html( (string) $rwga_run['device_type'] ) : '—'; ?></dd>
			<dt><?php esc_html_e( 'Created (UTC)', 'reactwoo-geo-ai' ); ?></dt>
			<dd><?php echo isset( $rwga_run['created_at'] ) ? esc_html( (string) $rwga_run['created_at'] ) : '—'; ?></dd>
		</dl>
		<?php if ( empty( $rwga_run['report_html'] ) && ! empty( $rwga_run['summary'] ) ) : ?>
			<h3><?php esc_html_e( 'Overview', 'reactwoo-geo-ai' ); ?></h3>
			<p><?php echo nl2br( esc_html( (string) $rwga_run['summary'] ) ); ?></p>
		<?php endif; ?>
		<details class="rwga-dev-details">
			<summary><?php esc_html_e( 'Technical details', 'reactwoo-geo-ai' ); ?></summary>
			<dl class="rwga-license-dl">
				<dt><?php esc_html_e( 'Agent', 'reactwoo-geo-ai' ); ?></dt>
				<dd><code><?php echo isset( $rwga_run['agent_key'] ) ? esc_html( (string) $rwga_run['agent_key'] ) : '—'; ?></code></dd>
				<dt><?php esc_html_e( 'Result schema', 'reactwoo-geo-ai' ); ?></dt>
				<dd><?php echo isset( $rwga_run['result_schema_version'] ) ? esc_html( (string) $rwga_run['result_schema_version'] ) : '—'; ?></dd>
			</dl>
		</details>
	</div>

	<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card rwga-next-step" id="rwga-generate-recommendations">
		<h2><?php esc_html_e( 'Next step: Generate recommendations', 'reactwoo-geo-ai' ); ?></h2>
		<p><?php esc_html_e( 'Select which areas you want to improve based on this analysis.', 'reactwoo-geo-ai' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-form-grid">
			<input type="hidden" name="action" value="rwga_generate_recommendation_report" />
			<input type="hidden" name="analysis_run_id" value="<?php echo (int) $run_id; ?>" />
			<?php wp_nonce_field( 'rwga_recommend_ux' ); ?>
			<label><input type="checkbox" name="categories[]" value="copy" checked /> <?php esc_html_e( 'Copy & Messaging', 'reactwoo-geo-ai' ); ?></label>
			<label><input type="checkbox" name="categories[]" value="cta" checked /> <?php esc_html_e( 'Calls to Action', 'reactwoo-geo-ai' ); ?></label>
			<label><input type="checkbox" name="categories[]" value="trust" /> <?php esc_html_e( 'Trust & Credibility', 'reactwoo-geo-ai' ); ?></label>
			<label><input type="checkbox" name="categories[]" value="layout" /> <?php esc_html_e( 'Layout & Structure', 'reactwoo-geo-ai' ); ?></label>
			<label><input type="checkbox" name="categories[]" value="seo" /> <?php esc_html_e( 'SEO', 'reactwoo-geo-ai' ); ?></label>
			<label><input type="checkbox" name="categories[]" value="geo" /> <?php esc_html_e( 'Geo Optimisation', 'reactwoo-geo-ai' ); ?></label>
			<div class="rwgc-field">
				<label class="rwgc-field__label" for="rwga_business_goal"><?php esc_html_e( 'Business goal (optional)', 'reactwoo-geo-ai' ); ?></label>
				<input type="text" class="rwgc-input regular-text" name="business_goal" id="rwga_business_goal" />
			</div>
			<p class="rwgc-actions">
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Generate recommendation report', 'reactwoo-geo-ai' ); ?></button>
			</p>
		</form>
	</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Findings', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( empty( $rwga_findings ) ) : ?>
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_empty_state(
					__( 'No findings for this run', 'reactwoo-geo-ai' ),
					__( 'The engine may still have saved a summary above. You can generate recommendations from the analysis anyway.', 'reactwoo-geo-ai' ),
					array(),
					array( 'dashicon' => 'dashicons-info' )
				);
			} else {
				echo '<p class="description">' . esc_html__( 'No findings stored for this run.', 'reactwoo-geo-ai' ) . '</p>';
			}
			?>
		<?php else : ?>
			<ul class="rwga-findings-list">
				<?php foreach ( $rwga_findings as $f ) : ?>
					<?php
					$sev = isset( $f['severity'] ) ? sanitize_key( (string) $f['severity'] ) : 'medium';
					$sc  = isset( $severity_class[ $sev ] ) ? $severity_class[ $sev ] : '';
					$sev_label = ucfirst( $sev );
					?>
					<li class="rwga-finding-card">
						<div class="rwga-finding-card__head">
							<span class="rwga-severity <?php echo esc_attr( $sc ); ?>"><?php echo esc_html( $sev_label ); ?></span>
							<strong><?php echo isset( $f['title'] ) ? esc_html( (string) $f['title'] ) : ''; ?></strong>
							<?php if ( ! empty( $f['category'] ) ) : ?>
								<span class="description"><?php echo esc_html( (string) $f['category'] ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $f['evidence'] ) ) : ?>
							<p class="rwga-finding-card__evidence"><?php echo esc_html( (string) $f['evidence'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $f['recommendation_hint'] ) ) : ?>
							<p class="rwga-finding-card__hint"><em><?php esc_html_e( 'Hint:', 'reactwoo-geo-ai' ); ?></em> <?php echo esc_html( (string) $f['recommendation_hint'] ); ?></p>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Recommendations for this run', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( empty( $rwga_recommendations ) ) : ?>
			<p class="description"><?php esc_html_e( 'No recommendations generated yet for this analysis.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<p><a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rwga-recommendations', 'analysis_run' => $run_id, 'view' => 'report', 'journey' => 1 ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Open recommendation report', 'reactwoo-geo-ai' ); ?></a></p>
			<details class="rwga-dev-details">
				<summary><?php esc_html_e( 'View recommendation records', 'reactwoo-geo-ai' ); ?></summary>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Title', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Priority', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_recommendations as $rec ) : ?>
						<?php
						$rec_id  = isset( $rec['id'] ) ? (int) $rec['id'] : 0;
						$rec_geo = isset( $rec['geo_target'] ) ? (string) $rec['geo_target'] : '';
						$rec_pid = isset( $rec['page_id'] ) ? (int) $rec['page_id'] : 0;
						$rec_url = add_query_arg(
							array(
								'page'   => 'rwga-recommendations',
								'rec_id' => $rec_id,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td><a href="<?php echo esc_url( $rec_url ); ?>"><strong><?php echo isset( $rec['title'] ) ? esc_html( (string) $rec['title'] ) : ''; ?></strong></a></td>
							<td><?php echo isset( $rec['priority_level'] ) ? esc_html( (string) $rec['priority_level'] ) : '—'; ?></td>
							<td><?php echo isset( $rec['status'] ) ? esc_html( (string) $rec['status'] ) : '—'; ?></td>
							<td>
								<a class="rwgc-btn rwgc-btn--sm rwgc-btn--secondary" href="<?php echo esc_url( $rec_url ); ?>"><?php esc_html_e( 'View recommendation', 'reactwoo-geo-ai' ); ?></a>
								<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) && class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:6px;">
										<input type="hidden" name="action" value="rwga_copy_implement" />
										<input type="hidden" name="recommendation_id" value="<?php echo (int) $rec_id; ?>" />
										<?php if ( $rec_pid > 0 ) : ?><input type="hidden" name="page_id" value="<?php echo (int) $rec_pid; ?>" /><?php endif; ?>
										<?php if ( '' !== $rec_geo ) : ?><input type="hidden" name="geo_target" value="<?php echo esc_attr( $rec_geo ); ?>" /><?php endif; ?>
										<?php wp_nonce_field( 'rwga_copy_implement' ); ?>
										<button type="submit" class="rwgc-btn rwgc-btn--sm rwgc-btn--primary"><?php esc_html_e( 'Generate copy', 'reactwoo-geo-ai' ); ?></button>
									</form>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</details>
		<?php endif; ?>
	</div>

	<?php if ( class_exists( 'RWGA_License', false ) && ! RWGA_License::can_run_workflows() ) : ?>
	<div class="rwgc-card">
		<p class="description"><?php esc_html_e( 'Add a Geo AI license key in Settings to generate recommendations from this analysis.', 'reactwoo-geo-ai' ); ?></p>
		<p><a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-license' ) ); ?>"><?php esc_html_e( 'Open Settings', 'reactwoo-geo-ai' ); ?></a></p>
	</div>
	<?php endif; ?>
</div>
