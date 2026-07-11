<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-advanced';

$variant_draft_url     = isset( $variant_draft_url ) && is_string( $variant_draft_url ) ? $variant_draft_url : '';
$rest_capabilities_url = isset( $rest_capabilities_url ) && is_string( $rest_capabilities_url ) ? $rest_capabilities_url : '';
$rest_location_url     = isset( $rest_location_url ) && is_string( $rest_location_url ) ? $rest_location_url : '';
$rest_v1_base          = isset( $rest_v1_base ) && is_string( $rest_v1_base ) ? $rest_v1_base : '';
$rwga_summary          = isset( $rwga_summary ) && is_array( $rwga_summary ) ? $rwga_summary : array();

$settings = RWGA_Settings::get_settings();
$can_api  = class_exists( 'RWGA_Settings', false ) && RWGA_Settings::can_edit_api_base_field();
$lic_ok   = class_exists( 'RWGA_Settings', false ) && RWGA_Settings::is_license_configured_for_geo_ai_ui();

$ai_health_url = wp_nonce_url( admin_url( 'admin.php?page=rwga-advanced&rwga_action=ai_health' ), 'rwga_dash_ai_health' );
$ai_usage_url  = wp_nonce_url( admin_url( 'admin.php?page=rwga-advanced&rwga_action=ai_usage' ), 'rwga_dash_ai_usage' );
$rest_smoke_url = wp_nonce_url( admin_url( 'admin.php?page=rwga-advanced&rwga_action=rest_post_smoke' ), 'rwga_dash_rest_post_smoke' );

$resolved_base = '';
if ( class_exists( 'RWGA_Platform_Client', false ) ) {
	$resolved_base = RWGA_Platform_Client::get_api_base();
} elseif ( ! empty( $rwga_summary['api_base'] ) ) {
	$resolved_base = (string) $rwga_summary['api_base'];
}

?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--advanced">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Advanced', 'reactwoo-geo-ai' ),
			__( 'Workflow engine, optional API host, connection checks, and REST references for developers and support.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo AI — Advanced', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php settings_errors( 'rwga_geo_ai' ); ?>
	<?php RWGA_Admin::render_usage_refresh_notices(); ?>

	<?php if ( ! empty( $_GET['rwga_disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'License key removed from this site.', 'reactwoo-geo-ai' ); ?></p></div>
	<?php endif; ?>

	<?php
	$workflow_engine = isset( $settings['workflow_engine'] ) ? sanitize_key( (string) $settings['workflow_engine'] ) : 'local';
	if ( ! in_array( $workflow_engine, array( 'local', 'remote', 'remote_fallback' ), true ) ) {
		$workflow_engine = 'local';
	}
	$ux_analysis_focus = isset( $settings['ux_analysis_focus'] ) ? sanitize_key( (string) $settings['ux_analysis_focus'] ) : 'messaging';
	if ( ! in_array( $ux_analysis_focus, array( 'messaging', 'layout', 'both' ), true ) ) {
		$ux_analysis_focus = 'messaging';
	}
	?>

	<div class="rwgc-card" style="max-width: 720px;">
		<p>
			<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-page-context-debug' ) ); ?>">
				<?php esc_html_e( 'Open page context inspector', 'reactwoo-geo-ai' ); ?>
			</a>
			<span class="description" style="display:block;margin-top:8px;"><?php esc_html_e( 'Debug Elementor/Gutenberg parsing, UX scores, and the AI payload sent to workflows.', 'reactwoo-geo-ai' ); ?></span>
		</p>
	</div>

	<div class="rwgc-card" style="max-width: 720px;">
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_section_header(
				__( 'Workflow engine', 'reactwoo-geo-ai' ),
				__( 'Choose how page UX analyses run: local preview, remote API, or remote with local fallback. Site intelligence audits (Cloud intelligence) always use the remote API when execution mode is Remote or Remote with fallback.', 'reactwoo-geo-ai' )
			);
		} else {
			echo '<h2>' . esc_html__( 'Workflow engine', 'reactwoo-geo-ai' ) . '</h2>';
		}
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'rwga_license_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( RWGA_Settings::OPTION_KEY ); ?>[rwga_form_scope]" value="advanced" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rwga_workflow_engine"><?php esc_html_e( 'Execution mode', 'reactwoo-geo-ai' ); ?></label></th>
					<td>
						<select id="rwga_workflow_engine" name="<?php echo esc_attr( RWGA_Settings::OPTION_KEY ); ?>[workflow_engine]">
							<option value="local" <?php selected( $workflow_engine, 'local' ); ?>><?php esc_html_e( 'Local (stub)', 'reactwoo-geo-ai' ); ?></option>
							<option value="remote" <?php selected( $workflow_engine, 'remote' ); ?>><?php esc_html_e( 'Remote (API only)', 'reactwoo-geo-ai' ); ?></option>
							<option value="remote_fallback" <?php selected( $workflow_engine, 'remote_fallback' ); ?>><?php esc_html_e( 'Remote with local fallback', 'reactwoo-geo-ai' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rwga_ux_analysis_focus"><?php esc_html_e( 'Default UX analysis focus', 'reactwoo-geo-ai' ); ?></label></th>
					<td>
						<select id="rwga_ux_analysis_focus" name="<?php echo esc_attr( RWGA_Settings::OPTION_KEY ); ?>[ux_analysis_focus]">
							<option value="messaging" <?php selected( $ux_analysis_focus, 'messaging' ); ?>><?php esc_html_e( 'Messaging (copy, CTA, trust)', 'reactwoo-geo-ai' ); ?></option>
							<option value="layout" <?php selected( $ux_analysis_focus, 'layout' ); ?>><?php esc_html_e( 'Layout (structure, hierarchy)', 'reactwoo-geo-ai' ); ?></option>
							<option value="both" <?php selected( $ux_analysis_focus, 'both' ); ?>><?php esc_html_e( 'Messaging + layout', 'reactwoo-geo-ai' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Used when a run does not specify a focus (e.g. some API clients) and as the default on the Overview sample form. Messaging-only prompts are narrower and usually consume fewer tokens on the API; layout and combined scans ask the model for more structure detail and typically use more output tokens.', 'reactwoo-geo-ai' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save workflow settings', 'reactwoo-geo-ai' ) ); ?>
		</form>
	</div>

	<?php if ( $can_api ) : ?>
		<div class="rwgc-card" style="max-width: 720px;">
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_section_header(
					__( 'API endpoint (optional)', 'reactwoo-geo-ai' ),
					__( 'Override the default ReactWoo API base only when directed by support or your deployment.', 'reactwoo-geo-ai' )
				);
			} else {
				echo '<h2>' . esc_html__( 'API endpoint (optional)', 'reactwoo-geo-ai' ) . '</h2>';
				echo '<p class="description">' . esc_html__( 'Override the default ReactWoo API base only when directed by support or your deployment.', 'reactwoo-geo-ai' ) . '</p>';
			}
			?>
			<form method="post" action="options.php">
				<?php settings_fields( 'rwga_license_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( RWGA_Settings::OPTION_KEY ); ?>[rwga_form_scope]" value="advanced" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rwga_reactwoo_api_base_adv"><?php esc_html_e( 'API base URL', 'reactwoo-geo-ai' ); ?></label></th>
						<td>
							<input type="url" id="rwga_reactwoo_api_base_adv" name="<?php echo esc_attr( RWGA_Settings::OPTION_KEY ); ?>[reactwoo_api_base]" value="<?php echo esc_attr( isset( $settings['reactwoo_api_base'] ) ? $settings['reactwoo_api_base'] : 'https://api.reactwoo.com' ); ?>" class="regular-text code" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="rwga_reactwoo_license_key_adv"><?php esc_html_e( 'License key', 'reactwoo-geo-ai' ); ?></label></th>
						<td>
							<input type="password" id="rwga_reactwoo_license_key_adv" name="<?php echo esc_attr( RWGA_Settings::OPTION_KEY ); ?>[reactwoo_license_key]" value="" class="regular-text" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Leave blank to keep the current key.', 'reactwoo-geo-ai' ); ?></p>
							<p class="description" style="margin-top:6px;">
								<strong><?php esc_html_e( 'To remove the key from this site:', 'reactwoo-geo-ai' ); ?></strong>
								<?php esc_html_e( 'use Disconnect below. Saving with an empty field keeps the saved key — it does not disconnect.', 'reactwoo-geo-ai' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save advanced settings', 'reactwoo-geo-ai' ) ); ?>
			</form>
			<?php if ( $lic_ok ) : ?>
				<p class="description" style="margin-top:12px;"><?php esc_html_e( 'Disconnect clears the license, bridge state, and usage snapshot in one step (same as License tab).', 'reactwoo-geo-ai' ); ?></p>
				<p class="rwgc-actions" style="margin-top:8px;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwga-license-disconnect-form" onsubmit="if(!window.confirm(<?php echo esc_js( __( 'Remove the license key from this site?', 'reactwoo-geo-ai' ) ); ?>)){return false;}var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;}return true;">
						<?php wp_nonce_field( 'rwga_clear_license' ); ?>
						<input type="hidden" name="action" value="rwga_clear_geo_ai_license" />
						<input type="hidden" name="rwga_disconnect_redirect" value="advanced" />
						<button type="submit" class="rwgc-btn rwgc-btn--danger"><?php esc_html_e( 'Disconnect', 'reactwoo-geo-ai' ); ?></button>
					</form>
				</p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="rwgc-card">
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_section_header(
					__( 'API endpoint', 'reactwoo-geo-ai' ),
					__( 'The ReactWoo API host is managed automatically. Overrides are available only when enabled (constant, filter, or support mode).', 'reactwoo-geo-ai' )
				);
			} else {
				echo '<h2>' . esc_html__( 'API endpoint', 'reactwoo-geo-ai' ) . '</h2>';
				echo '<p class="description">' . esc_html__( 'The ReactWoo API host is managed automatically. Overrides are available only when enabled (constant, filter, or support mode).', 'reactwoo-geo-ai' ) . '</p>';
			}
			?>
			<?php if ( '' !== $resolved_base ) : ?>
				<p><code class="rwga-code-inline"><?php echo esc_html( $resolved_base ); ?></code></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="rwgc-card">
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_section_header(
				__( 'Connection checks', 'reactwoo-geo-ai' ),
				__( 'Use these when troubleshooting; responses may be technical.', 'reactwoo-geo-ai' )
			);
		} else {
			echo '<h2>' . esc_html__( 'Connection checks', 'reactwoo-geo-ai' ) . '</h2>';
			echo '<p class="description">' . esc_html__( 'Use these when troubleshooting; responses may be technical.', 'reactwoo-geo-ai' ) . '</p>';
		}
		?>
		<p class="rwgc-actions">
			<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $ai_health_url ); ?>"><?php esc_html_e( 'Check AI connection', 'reactwoo-geo-ai' ); ?></a>
			<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $ai_usage_url ); ?>"><?php esc_html_e( 'Refresh usage', 'reactwoo-geo-ai' ); ?></a>
		</p>
		<h3><?php esc_html_e( 'Variant route (local validation)', 'reactwoo-geo-ai' ); ?></h3>
		<p class="description"><?php esc_html_e( 'POST without page_id — expect HTTP 400 before any outbound AI call.', 'reactwoo-geo-ai' ); ?></p>
		<p class="rwgc-actions">
			<a class="rwgc-btn rwgc-btn--tertiary" href="<?php echo esc_url( $rest_smoke_url ); ?>"><?php esc_html_e( 'Validate variant route', 'reactwoo-geo-ai' ); ?></a>
		</p>
	</div>

	<div class="rwgc-card">
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_section_header(
				__( 'REST URLs', 'reactwoo-geo-ai' ),
				__( 'Quick links to capabilities JSON, visitor REST location, and the REST API v1 base.', 'reactwoo-geo-ai' )
			);
		} else {
			echo '<h2>' . esc_html__( 'REST URLs', 'reactwoo-geo-ai' ) . '</h2>';
		}
		?>
		<table class="widefat striped rwga-table-comfortable">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Capabilities JSON', 'reactwoo-geo-ai' ); ?></th>
					<td>
						<?php if ( '' !== $rest_capabilities_url ) : ?>
							<a class="rwgc-btn rwgc-btn--tertiary" href="<?php echo esc_url( $rest_capabilities_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open', 'reactwoo-geo-ai' ); ?></a>
							<code class="rwga-code-block"><?php echo esc_html( $rest_capabilities_url ); ?></code>
						<?php else : ?>
							<?php esc_html_e( '—', 'reactwoo-geo-ai' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'REST location (visitor)', 'reactwoo-geo-ai' ); ?></th>
					<td>
						<?php if ( '' !== $rest_location_url ) : ?>
							<a class="rwgc-btn rwgc-btn--tertiary" href="<?php echo esc_url( $rest_location_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open', 'reactwoo-geo-ai' ); ?></a>
							<code class="rwga-code-block"><?php echo esc_html( $rest_location_url ); ?></code>
						<?php else : ?>
							<?php esc_html_e( '—', 'reactwoo-geo-ai' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'REST API v1 base', 'reactwoo-geo-ai' ); ?></th>
					<td>
						<?php if ( '' !== $rest_v1_base ) : ?>
							<code class="rwga-code-block"><?php echo esc_html( $rest_v1_base ); ?></code>
						<?php else : ?>
							<?php esc_html_e( '—', 'reactwoo-geo-ai' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<p class="rwgc-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-tools' ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Geo Core → Tools', 'reactwoo-geo-ai' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgc-usage' ) ); ?>" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Geo Core → Usage', 'reactwoo-geo-ai' ); ?></a>
		</p>
	</div>

	<div class="rwgc-card">
		<?php
		if ( class_exists( 'RWGC_Admin_UI', false ) ) {
			RWGC_Admin_UI::render_section_header(
				__( 'Variant draft endpoint', 'reactwoo-geo-ai' ),
				__( 'POST with a user that can edit pages. Body includes page_id and optional instructions.', 'reactwoo-geo-ai' )
			);
		} else {
			echo '<h3>' . esc_html__( 'Variant draft endpoint', 'reactwoo-geo-ai' ) . '</h3>';
			echo '<p class="description">' . esc_html__( 'POST with a user that can edit pages. Body includes page_id and optional instructions.', 'reactwoo-geo-ai' ) . '</p>';
		}
		?>
		<?php if ( is_string( $variant_draft_url ) && '' !== $variant_draft_url ) : ?>
			<p><code class="rwga-code-block"><?php echo esc_html( $variant_draft_url ); ?></code></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Enable REST in Geo Core → Settings to expose this route.', 'reactwoo-geo-ai' ); ?></p>
		<?php endif; ?>
		<h3><?php esc_html_e( 'Hooks', 'reactwoo-geo-ai' ); ?></h3>
		<p class="description"><?php esc_html_e( 'rwgc_ai_variant_draft_payload, rwgc_ai_variant_draft_response; filter rwga_stats_snapshot; rwga_usage_display_rows; rwga_draft_queue_rows.', 'reactwoo-geo-ai' ); ?></p>
	</div>
</div>
