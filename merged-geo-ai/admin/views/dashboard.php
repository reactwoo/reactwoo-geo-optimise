<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwga_summary            = isset( $rwga_summary ) && is_array( $rwga_summary ) ? $rwga_summary : array();
$rwga_cache              = isset( $rwga_cache ) && is_array( $rwga_cache ) ? $rwga_cache : null;
$rwga_queue_preview      = isset( $rwga_queue_preview ) && is_array( $rwga_queue_preview ) ? $rwga_queue_preview : array();
$rwga_analysis_preview   = isset( $rwga_analysis_preview ) && is_array( $rwga_analysis_preview ) ? $rwga_analysis_preview : array();
$rwgc_nav_current        = isset( $rwgc_nav_current ) ? $rwgc_nav_current : RWGA_Admin::MENU_PARENT;

$lic_ok  = ! empty( $rwga_summary['license_configured'] );
$rest_on = ! empty( $rwga_summary['rest_enabled'] );

$plan_label = __( '—', 'reactwoo-geo-ai' );
$usage_hint = __( 'Refresh usage on the Settings screen.', 'reactwoo-geo-ai' );
if ( null !== $rwga_cache ) {
	if ( class_exists( 'RWGA_Usage', false ) ) {
		$formatted_plan = RWGA_Usage::format_plan_label( $rwga_cache );
		if ( '' !== $formatted_plan ) {
			$plan_label = $formatted_plan;
		}
	} else {
		$tier = isset( $rwga_cache['license_tier'] ) ? (string) $rwga_cache['license_tier'] : '';
		if ( '' !== $tier ) {
			$plan_label = $tier;
		}
	}
	$used  = isset( $rwga_cache['used'] ) ? (int) $rwga_cache['used'] : 0;
	$limit = isset( $rwga_cache['limit'] ) ? (int) $rwga_cache['limit'] : 0;
	if ( $limit > 0 ) {
		/* translators: 1: used tokens, 2: limit */
		$usage_hint = sprintf( __( '%1$d / %2$d tokens this period', 'reactwoo-geo-ai' ), $used, $limit );
	}
}

$drafts_month = apply_filters( 'rwga_dashboard_drafts_month_count', null );
$drafts_month = is_numeric( $drafts_month ) ? (string) (int) $drafts_month : '—';
$awaiting     = apply_filters( 'rwga_dashboard_awaiting_review_count', null );
$awaiting     = is_numeric( $awaiting ) ? (string) (int) $awaiting : '—';

$analyses_url     = admin_url( 'admin.php?page=rwga-analyses' );
$recommendations_url = admin_url( 'admin.php?page=rwga-recommendations' );
$implement_url    = admin_url( 'admin.php?page=rwga-implementation-drafts' );
$license_url      = admin_url( 'admin.php?page=rwga-license' );
$drafts_url       = admin_url( 'admin.php?page=rwga-drafts' );
$advanced_url     = admin_url( 'admin.php?page=rwga-advanced' );

$rwga_ux_site_focus = 'messaging';
if ( class_exists( 'RWGA_Settings', false ) ) {
	$s = RWGA_Settings::get_settings();
	$rwga_ux_site_focus = isset( $s['ux_analysis_focus'] ) ? sanitize_key( (string) $s['ux_analysis_focus'] ) : 'messaging';
	if ( ! in_array( $rwga_ux_site_focus, array( 'messaging', 'layout', 'both' ), true ) ) {
		$rwga_ux_site_focus = 'messaging';
	}
}

?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--overview">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Geo AI', 'reactwoo-geo-ai' ),
			__( 'Turn visitor context into clearer pages: analyse content, review AI suggestions, then apply copy and SEO drafts when you are ready.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo AI', 'reactwoo-geo-ai' ); ?></h1>
		<p class="description"><?php esc_html_e( 'AI-assisted geo content for WordPress.', 'reactwoo-geo-ai' ); ?></p>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>
	<?php RWGA_Admin::render_current_workflow_state(); ?>

	<?php settings_errors( 'rwga_geo_ai' ); ?>
	<?php RWGA_Admin::render_usage_refresh_notices(); ?>

	<?php
	$rwga_sample = isset( $_GET['rwga_sample'] ) ? sanitize_key( wp_unslash( $_GET['rwga_sample'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'ok' === $rwga_sample && ! empty( $_GET['run_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rid = (int) $_GET['run_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				/* translators: %d: analysis run id */
				__( 'Analysis saved as run #%d.', 'reactwoo-geo-ai' ),
				$rid
			)
		);
		echo '</p></div>';
	} elseif ( 'unlicensed' === $rwga_sample ) {
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Add a Geo AI license key to run workflows.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'nopage' === $rwga_sample ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'No published page was found to analyse. Create a page first.', 'reactwoo-geo-ai' ) . '</p></div>';
	} elseif ( 'error' === $rwga_sample && ! empty( $_GET['rwga_err'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg = sanitize_text_field( wp_unslash( rawurldecode( (string) $_GET['rwga_err'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}
	?>

	<?php RWGA_Admin::render_suite_handoff_panel(); ?>

	<?php RWGA_Admin::render_suite_satellite_quick_links(); ?>

	<?php RWGA_Admin::render_ux_review_entry_card( 'dashboard' ); ?>

	<div class="rwga-hero rwga-workflow-launch">
		<h2><?php esc_html_e( 'Start here', 'reactwoo-geo-ai' ); ?></h2>
		<ol class="rwga-steps">
			<li><?php esc_html_e( 'Connect your license in Settings so workflows can run.', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( 'Analyse a key page to capture strengths, gaps, and opportunities.', 'reactwoo-geo-ai' ); ?></li>
			<li><?php esc_html_e( 'Turn recommendations into implementation drafts you can review before publishing.', 'reactwoo-geo-ai' ); ?></li>
		</ol>
	</div>

	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_stat_grid_open();
		RWGC_Admin_UI::render_stat_card(
			__( 'License', 'reactwoo-geo-ai' ),
			$lic_ok ? __( 'Connected', 'reactwoo-geo-ai' ) : __( 'Not set', 'reactwoo-geo-ai' ),
			array(
				'hint' => $lic_ok ? __( 'Key stored for this site', 'reactwoo-geo-ai' ) : __( 'Open Settings to add your key', 'reactwoo-geo-ai' ),
				'tone' => $lic_ok ? 'success' : 'warning',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'Plan / usage', 'reactwoo-geo-ai' ),
			$plan_label,
			array(
				'hint' => $usage_hint,
				'tone' => 'default',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'Drafts this month', 'reactwoo-geo-ai' ),
			$drafts_month,
			array(
				'hint' => __( 'When your site records draft events', 'reactwoo-geo-ai' ),
				'tone' => 'neutral',
			)
		);
		RWGC_Admin_UI::render_stat_card(
			__( 'Awaiting review', 'reactwoo-geo-ai' ),
			$awaiting,
			array(
				'hint' => __( 'Items needing a decision', 'reactwoo-geo-ai' ),
				'tone' => 'neutral',
			)
		);
		RWGC_Admin_UI::render_stat_grid_close();
		?>
	<?php endif; ?>

	<?php
	$rwga_usage_summary = null;
	if ( class_exists( 'RWGA_Usage_Presenter', false ) && null !== $rwga_cache && is_array( $rwga_cache ) ) {
		$rwga_usage_summary = RWGA_Usage_Presenter::build_package_summary( $rwga_cache );
	}
	?>
	<?php if ( is_array( $rwga_usage_summary ) && $lic_ok ) : ?>
	<div class="rwgc-card rwga-usage-package">
		<h2><?php esc_html_e( 'AI token allowance', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php echo esc_html( $rwga_usage_summary['messaging']['headline'] ); ?> <?php echo esc_html( $rwga_usage_summary['messaging']['note'] ); ?></p>
		<?php if ( (int) $rwga_usage_summary['tokens_limit'] <= 0 ) : ?>
			<p class="description"><?php esc_html_e( 'Refresh usage on the Settings screen to load your token cap from the API.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
		<p>
			<strong><?php esc_html_e( 'Assistant tier', 'reactwoo-geo-ai' ); ?>:</strong>
			<?php echo esc_html( strtoupper( (string) $rwga_usage_summary['assistant_tier'] ) ); ?>
			&mdash;
			<?php
			printf(
				/* translators: 1: used tokens, 2: limit */
				esc_html__( '%1$s / %2$s tokens used this period', 'reactwoo-geo-ai' ),
				number_format_i18n( (int) $rwga_usage_summary['tokens_used'] ),
				number_format_i18n( (int) $rwga_usage_summary['tokens_limit'] )
			);
			?>
		</p>
		<div class="rwga-progress" style="background:#e0e0e0;border-radius:4px;height:10px;max-width:420px;margin:8px 0;">
			<div style="width:<?php echo (int) min( 100, $rwga_usage_summary['usage_percent'] ); ?>%;height:10px;border-radius:4px;background:#2271b1;"></div>
		</div>
		<?php endif; ?>
		<?php if ( (int) $rwga_usage_summary['tokens_limit'] > 0 && ! empty( $rwga_usage_summary['soft_quotas'] ) && is_array( $rwga_usage_summary['soft_quotas'] ) ) : ?>
			<h3 class="screen-reader-text"><?php esc_html_e( 'Estimated workflow capacity (guidance)', 'reactwoo-geo-ai' ); ?></h3>
			<ul class="rwga-soft-quotas" style="list-style:disc;margin-left:1.25em;">
				<?php foreach ( $rwga_usage_summary['soft_quotas'] as $row ) : ?>
					<?php
					if ( ! is_array( $row ) ) {
						continue;
					}
					$lab = isset( $row['label'] ) ? (string) $row['label'] : '';
					$tot = isset( $row['estimated_total'] ) ? (int) $row['estimated_total'] : 0;
					if ( '' === $lab ) {
						continue;
					}
					?>
					<li>
						<?php
						printf(
							/* translators: 1: approximate count, 2: workflow label */
							esc_html__( 'About %1$s × %2$s', 'reactwoo-geo-ai' ),
							number_format_i18n( max( 0, $tot ) ),
							esc_html( $lab )
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_section_header(
				__( 'Quick actions', 'reactwoo-geo-ai' ),
				__( 'One place to jump into the workflow.', 'reactwoo-geo-ai' )
			);
		} else {
			echo '<h2>' . esc_html__( 'Quick actions', 'reactwoo-geo-ai' ) . '</h2>';
		}
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_quick_actions(
				array(
					array(
						'url'     => $analyses_url,
						'label'   => __( 'Analyse a page', 'reactwoo-geo-ai' ),
						'primary' => true,
					),
					array(
						'url'   => $recommendations_url,
						'label' => __( 'Recommendations', 'reactwoo-geo-ai' ),
					),
					array(
						'url'   => $implement_url,
						'label' => __( 'Implementation drafts', 'reactwoo-geo-ai' ),
					),
					array(
						'url'   => $drafts_url,
						'label' => __( 'Queue', 'reactwoo-geo-ai' ),
					),
					array(
						'url'   => $license_url,
						'label' => __( 'Settings', 'reactwoo-geo-ai' ),
					),
				)
			);
		}
		?>
	</div>

	<?php if ( current_user_can( RWGA_Capabilities::CAP_RUN_AI ) ) : ?>
	<div class="rwgc-card">
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_section_header(
				__( 'Analyse a page', 'reactwoo-geo-ai' ),
				__( 'Pick a page and what to emphasise. Nothing is published automatically.', 'reactwoo-geo-ai' )
			);
		} else {
			echo '<h2>' . esc_html__( 'Analyse a page', 'reactwoo-geo-ai' ) . '</h2>';
		}
		?>
		<?php if ( class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows() ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-form-grid rwga-sample-ux">
				<input type="hidden" name="action" value="rwga_sample_ux" />
				<?php wp_nonce_field( 'rwga_sample_ux' ); ?>
				<div class="rwgc-field">
					<label class="rwgc-field__label" for="rwga_sample_page_id"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></label>
					<?php
					wp_dropdown_pages(
						array(
							'name'              => 'page_id',
							'id'                => 'rwga_sample_page_id',
							'class'             => 'rwgc-select rwgc-input',
							'show_option_none'    => __( '— Use home or most recently updated page —', 'reactwoo-geo-ai' ),
							'option_none_value'   => '0',
							'sort_column'         => 'post_title',
						)
					);
					?>
					<p class="rwgc-field__hint"><?php esc_html_e( 'We use the page you publish or edit most often when you leave this blank.', 'reactwoo-geo-ai' ); ?></p>
				</div>
				<div class="rwgc-field">
					<label class="rwgc-field__label" for="rwga_sample_analysis_focus"><?php esc_html_e( 'What to look at first', 'reactwoo-geo-ai' ); ?></label>
					<select name="analysis_focus" id="rwga_sample_analysis_focus" class="rwgc-select rwgc-input">
						<option value="messaging" <?php selected( $rwga_ux_site_focus, 'messaging' ); ?>><?php esc_html_e( 'Messaging (copy, CTA, trust)', 'reactwoo-geo-ai' ); ?></option>
						<option value="layout" <?php selected( $rwga_ux_site_focus, 'layout' ); ?>><?php esc_html_e( 'Layout (structure, hierarchy)', 'reactwoo-geo-ai' ); ?></option>
						<option value="both" <?php selected( $rwga_ux_site_focus, 'both' ); ?>><?php esc_html_e( 'Messaging and layout', 'reactwoo-geo-ai' ); ?></option>
					</select>
					<p class="rwgc-field__hint"><?php esc_html_e( 'Messaging-only reviews usually use fewer tokens than a full layout pass.', 'reactwoo-geo-ai' ); ?></p>
				</div>
				<p class="rwgc-actions">
					<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Analyse this page', 'reactwoo-geo-ai' ); ?></button>
				</p>
			</form>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Save a license key in Settings to run analyses.', 'reactwoo-geo-ai' ); ?></p>
			<p><a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( $license_url ); ?>"><?php esc_html_e( 'Open Settings', 'reactwoo-geo-ai' ); ?></a></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Setup checklist', 'reactwoo-geo-ai' ); ?></h2>
		<ul class="rwgc-suite-checklist">
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_checklist_row(
					$lic_ok,
					__( 'License saved for this site', 'reactwoo-geo-ai' ),
					$license_url,
					__( 'Add license', 'reactwoo-geo-ai' )
				);
				RWGC_Admin_UI::render_checklist_row(
					null !== $rwga_cache,
					__( 'Usage snapshot available', 'reactwoo-geo-ai' ),
					$license_url,
					__( 'Refresh usage', 'reactwoo-geo-ai' )
				);
				RWGC_Admin_UI::render_checklist_row(
					$rest_on,
					__( 'Geo Core REST enabled for automated drafts', 'reactwoo-geo-ai' ),
					admin_url( 'admin.php?page=rwgc-settings' ),
					__( 'Geo Core settings', 'reactwoo-geocore' )
				);
			}
			?>
		</ul>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Recent analyses', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( ! empty( $rwga_analysis_preview ) ) : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Run', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Score', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Workflow', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date (UTC)', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_analysis_preview as $row ) : ?>
						<?php
						$pid = isset( $row['page_id'] ) ? (int) $row['page_id'] : 0;
						$pt  = $pid > 0 ? get_the_title( $pid ) : '';
						$rid = isset( $row['id'] ) ? (int) $row['id'] : 0;
						$run_link = add_query_arg(
							array(
								'page'   => 'rwga-analyses',
								'run_id' => $rid,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr>
							<td>
								<?php if ( $rid > 0 ) : ?>
									<a href="<?php echo esc_url( $run_link ); ?>"><?php echo (int) $rid; ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo $pid > 0 && '' !== $pt ? esc_html( $pt ) : '—'; ?></td>
							<td><?php echo isset( $row['score'] ) && null !== $row['score'] ? esc_html( (string) $row['score'] ) : '—'; ?></td>
							<td><?php echo isset( $row['workflow_key'] ) ? esc_html( (string) $row['workflow_key'] ) : '—'; ?></td>
							<td><?php echo isset( $row['created_at'] ) ? esc_html( (string) $row['created_at'] ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $analyses_url ); ?>"><?php esc_html_e( 'View all analyses', 'reactwoo-geo-ai' ); ?></a></p>
		<?php elseif ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
			<?php
			RWGC_Admin_UI::render_empty_state(
				__( 'No analyses yet', 'reactwoo-geo-ai' ),
				__( 'Run your first page analysis to see findings and unlock recommendations.', 'reactwoo-geo-ai' ),
				array(
					array(
						'url'     => $analyses_url,
						'label'   => __( 'Go to Analyse', 'reactwoo-geo-ai' ),
						'primary' => true,
					),
				),
				array( 'dashicon' => 'dashicons-chart-line' )
			);
			?>
		<?php else : ?>
			<p class="rwga-empty-hint"><?php esc_html_e( 'No analyses yet.', 'reactwoo-geo-ai' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="rwgc-card">
		<h2><?php esc_html_e( 'Queue preview', 'reactwoo-geo-ai' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Latest draft jobs from integrations.', 'reactwoo-geo-ai' ); ?></p>
		<?php if ( ! empty( $rwga_queue_preview ) ) : ?>
			<table class="widefat striped rwga-table-comfortable">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Source', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Context', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'reactwoo-geo-ai' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rwga_queue_preview as $row ) : ?>
						<tr>
							<td><?php echo isset( $row['source_label'] ) ? esc_html( (string) $row['source_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['context_label'] ) ? esc_html( (string) $row['context_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['status_label'] ) ? esc_html( (string) $row['status_label'] ) : '—'; ?></td>
							<td><?php echo isset( $row['created_gmt'] ) ? esc_html( (string) $row['created_gmt'] ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p><a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $drafts_url ); ?>"><?php esc_html_e( 'Open full queue', 'reactwoo-geo-ai' ); ?></a></p>
		<?php elseif ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
			<?php
			RWGC_Admin_UI::render_empty_state(
				__( 'Queue is empty', 'reactwoo-geo-ai' ),
				__( 'When draft jobs are created from the editor or integrations, they will show here.', 'reactwoo-geo-ai' ),
				array(
					array(
						'url'   => $implement_url,
						'label' => __( 'Open implementation drafts', 'reactwoo-geo-ai' ),
					),
				),
				array( 'dashicon' => 'dashicons-list-view' )
			);
			?>
		<?php else : ?>
			<p class="rwga-empty-hint"><?php esc_html_e( 'No queued drafts yet.', 'reactwoo-geo-ai' ); ?></p>
		<?php endif; ?>
	</div>

	<details class="rwga-dev-details">
		<summary><?php esc_html_e( 'Connection details', 'reactwoo-geo-ai' ); ?></summary>
		<table class="widefat striped">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Geo Core REST', 'reactwoo-geo-ai' ); ?></th>
					<td><?php echo $rest_on ? esc_html__( 'Enabled', 'reactwoo-geo-ai' ) : esc_html__( 'Disabled', 'reactwoo-geo-ai' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'License', 'reactwoo-geo-ai' ); ?></th>
					<td><?php echo $lic_ok ? esc_html__( 'Key on file', 'reactwoo-geo-ai' ) : esc_html__( 'No key', 'reactwoo-geo-ai' ); ?></td>
				</tr>
			</tbody>
		</table>
		<p><a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $advanced_url ); ?>"><?php esc_html_e( 'Advanced diagnostics', 'reactwoo-geo-ai' ); ?></a></p>
	</details>
</div>
