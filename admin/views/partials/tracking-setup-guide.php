<?php
/**
 * Tracking Setup Guide — step cards (default view).
 *
 * Expects: $rwgo_setup, $rwgo_experiments.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgo_setup       = isset( $rwgo_setup ) && is_array( $rwgo_setup ) ? $rwgo_setup : array();
$rwgo_experiments = isset( $rwgo_experiments ) && is_array( $rwgo_experiments ) ? $rwgo_experiments : array();

$connected   = ! empty( $rwgo_setup['connected'] );
$target      = isset( $rwgo_setup['target'] ) && is_array( $rwgo_setup['target'] ) ? $rwgo_setup['target'] : array();
$discovery   = isset( $rwgo_setup['discovery'] ) && is_array( $rwgo_setup['discovery'] ) ? $rwgo_setup['discovery'] : array();
$counts      = isset( $rwgo_setup['asset_counts'] ) && is_array( $rwgo_setup['asset_counts'] ) ? $rwgo_setup['asset_counts'] : array();
$mode        = isset( $rwgo_setup['mode'] ) ? (string) $rwgo_setup['mode'] : 'simple';
$primary     = isset( $rwgo_setup['primary'] ) && is_array( $rwgo_setup['primary'] ) ? $rwgo_setup['primary'] : null;
$push_exp_id = $primary && isset( $primary['post'] ) && $primary['post'] instanceof WP_Post ? (int) $primary['post']->ID : 0;
$can_push    = $connected && ! empty( $rwgo_setup['has_account'] ) && ! empty( $rwgo_setup['has_container'] ) && $push_exp_id > 0;
$last_push   = isset( $rwgo_setup['last_push'] ) && is_array( $rwgo_setup['last_push'] ) ? $rwgo_setup['last_push'] : array();

$copy_all_top = '';
$vars_plain   = class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::variables_plain() : '';
$ga4_txt      = class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::ga4_mapping_plain() : '';
if ( class_exists( 'RWGO_GTM_Handoff', false ) ) {
	$ex_js = RWGO_GTM_Handoff::generic_example_datalayer_js();
	if ( $primary && isset( $primary['post'], $primary['cfg'] ) && RWGO_GTM_Handoff::is_gtm_ready( $primary['cfg'] ) ) {
		$pair = RWGO_GTM_Handoff::primary_goal_handler_pair( $primary['cfg'] );
		if ( $pair ) {
			$obj   = RWGO_GTM_Handoff::build_datalayer_example_object( $primary['post'], $primary['cfg'], 'var_b', $pair['goal'], $pair['handler'] );
			$ex_js = "window.dataLayer = window.dataLayer || [];\nwindow.dataLayer.push(" . wp_json_encode( $obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . ');';
		}
	}
	$copy_all_top = RWGO_GTM_Handoff::copy_all_simple_pack( $ex_js );
}
?>
<textarea id="rwgo-gtm-copy-all-pack" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $copy_all_top ); ?></textarea>
<textarea id="rwgo-gtm-store-vars" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $vars_plain ); ?></textarea>
<textarea id="rwgo-gtm-store-ga4" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $ga4_txt ); ?></textarea>

<div class="rwgo-tracking-steps">

	<article class="rwgo-panel rwgo-tracking-step" id="rwgo-step-connect">
		<header class="rwgo-tracking-step__head">
			<h3 class="rwgo-tracking-step__title"><?php esc_html_e( '1. Connect Google Tag Manager', 'reactwoo-geo-optimise' ); ?></h3>
			<span class="rwgo-tracking-badge rwgo-tracking-badge--<?php echo $connected ? 'ok' : 'action'; ?>"><?php echo $connected ? esc_html__( 'Connected', 'reactwoo-geo-optimise' ) : esc_html__( 'Not connected', 'reactwoo-geo-optimise' ); ?></span>
		</header>
		<p class="rwgo-tracking-step__hint"><?php esc_html_e( 'OAuth tokens stay on React Cloud. Push creates draft workspace entities — it does not publish the container.', 'reactwoo-geo-optimise' ); ?></p>
		<div class="rwgo-btn-row rwgo-btn-row--wrap">
			<?php if ( ! $connected ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
					<?php wp_nonce_field( 'rwgo_gtm_connect' ); ?>
					<input type="hidden" name="action" value="rwgo_gtm_connect" />
					<button type="submit" class="button button-primary rwgo-btn rwgo-btn--primary"><?php esc_html_e( 'Connect Google Tag Manager', 'reactwoo-geo-optimise' ); ?></button>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
					<?php wp_nonce_field( 'rwgo_gtm_disconnect' ); ?>
					<input type="hidden" name="action" value="rwgo_gtm_disconnect" />
					<button type="submit" class="button rwgo-btn rwgo-btn--secondary"><?php esc_html_e( 'Disconnect', 'reactwoo-geo-optimise' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php if ( $connected ) : ?>
			<?php if ( ! empty( $discovery['error'] ) ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( (string) $discovery['error'] ); ?></p></div>
			<?php endif; ?>
			<ul class="rwgo-tracking-step__meta">
				<li><strong><?php esc_html_e( 'Account:', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( '' !== (string) ( $rwgo_setup['account_label'] ?? '' ) ? (string) $rwgo_setup['account_label'] : '—' ); ?></li>
				<li><strong><?php esc_html_e( 'Container:', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( '' !== (string) ( $rwgo_setup['container_label'] ?? '' ) ? (string) $rwgo_setup['container_label'] : '—' ); ?></li>
				<li><strong><?php esc_html_e( 'Workspace:', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( '' !== (string) ( $rwgo_setup['workspace_label'] ?? '' ) ? (string) $rwgo_setup['workspace_label'] : '—' ); ?></li>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-gtm-target-form" id="rwgo-gtm-target-form" data-rwgo-gtm-picker="1">
				<?php wp_nonce_field( 'rwgo_gtm_save_target' ); ?>
				<input type="hidden" name="action" value="rwgo_gtm_save_target" />
				<div class="rwgo-field">
					<label class="rwgo-field__label" for="rwgo_gtm_account_id"><?php esc_html_e( 'GTM account', 'reactwoo-geo-optimise' ); ?></label>
					<select class="rwgo-input" name="rwgo_gtm_account_id" id="rwgo_gtm_account_id" required>
						<option value=""><?php esc_html_e( '— Select account —', 'reactwoo-geo-optimise' ); ?></option>
						<?php foreach ( (array) ( $discovery['accounts'] ?? array() ) as $acc_row ) : ?>
							<?php if ( ! is_array( $acc_row ) || empty( $acc_row['id'] ) ) { continue; } ?>
							<option value="<?php echo esc_attr( (string) $acc_row['id'] ); ?>" <?php selected( (string) ( $target['account_id'] ?? '' ), (string) $acc_row['id'] ); ?>><?php echo esc_html( (string) ( $acc_row['label'] ?? $acc_row['id'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="rwgo-field">
					<label class="rwgo-field__label" for="rwgo_gtm_container_id"><?php esc_html_e( 'Container', 'reactwoo-geo-optimise' ); ?></label>
					<select class="rwgo-input" name="rwgo_gtm_container_id" id="rwgo_gtm_container_id" required <?php echo empty( $target['account_id'] ) ? 'disabled' : ''; ?>>
						<option value=""><?php esc_html_e( '— Select container —', 'reactwoo-geo-optimise' ); ?></option>
						<?php foreach ( (array) ( $discovery['containers'] ?? array() ) as $c_row ) : ?>
							<?php if ( ! is_array( $c_row ) || empty( $c_row['id'] ) ) { continue; } ?>
							<option value="<?php echo esc_attr( (string) $c_row['id'] ); ?>" <?php selected( (string) ( $target['container_id'] ?? '' ), (string) $c_row['id'] ); ?>><?php echo esc_html( (string) ( $c_row['label'] ?? $c_row['id'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="rwgo-field">
					<label class="rwgo-field__label" for="rwgo_gtm_workspace_id"><?php esc_html_e( 'Workspace', 'reactwoo-geo-optimise' ); ?></label>
					<select class="rwgo-input" name="rwgo_gtm_workspace_id" id="rwgo_gtm_workspace_id" <?php echo empty( $target['container_id'] ) ? 'disabled' : ''; ?>>
						<option value=""><?php esc_html_e( '— Default workspace —', 'reactwoo-geo-optimise' ); ?></option>
						<?php foreach ( (array) ( $discovery['workspaces'] ?? array() ) as $w_row ) : ?>
							<?php if ( ! is_array( $w_row ) || empty( $w_row['id'] ) ) { continue; } ?>
							<option value="<?php echo esc_attr( (string) $w_row['id'] ); ?>" <?php selected( (string) ( $target['workspace_id'] ?? '' ), (string) $w_row['id'] ); ?>><?php echo esc_html( (string) ( $w_row['label'] ?? $w_row['id'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="rwgo-field">
					<label class="rwgo-field__label" for="rwgo_gtm_measurement_id"><?php esc_html_e( 'GA4 measurement ID', 'reactwoo-geo-optimise' ); ?></label>
					<?php
					$ga_streams   = isset( $rwgo_setup['ga_streams'] ) && is_array( $rwgo_setup['ga_streams'] ) ? $rwgo_setup['ga_streams'] : array();
					$ga_connected = ! empty( $rwgo_setup['ga_connected'] );
					$ga_pro_url   = isset( $rwgo_setup['ga_pro_url'] ) ? (string) $rwgo_setup['ga_pro_url'] : admin_url( 'admin.php?page=rwgcp-google-analytics' );
					$cur_mid      = (string) ( $target['measurement_id'] ?? '' );
					$known_ids    = array();
					foreach ( $ga_streams as $gs ) {
						if ( is_array( $gs ) && ! empty( $gs['measurement_id'] ) ) {
							$known_ids[] = (string) $gs['measurement_id'];
						}
					}
					$manual_mode = ( '' !== $cur_mid && ! in_array( $cur_mid, $known_ids, true ) );
					?>
					<?php if ( ! $ga_connected ) : ?>
						<p class="description"><?php echo esc_html( ! empty( $rwgo_setup['ga_message'] ) ? (string) $rwgo_setup['ga_message'] : __( 'Connect Google Analytics in GeoCore Pro Targeting to load measurement IDs from your property.', 'reactwoo-geo-optimise' ) ); ?></p>
						<p class="rwgo-btn-row">
							<a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( $ga_pro_url ); ?>"><?php esc_html_e( 'Connect Google Analytics', 'reactwoo-geo-optimise' ); ?></a>
						</p>
						<input class="rwgo-input regular-text" type="text" name="rwgo_gtm_measurement_id" id="rwgo_gtm_measurement_id" value="<?php echo esc_attr( $cur_mid ); ?>" placeholder="G-XXXXXXXX" />
						<p class="description"><?php esc_html_e( 'Or enter a measurement ID manually if you already know it.', 'reactwoo-geo-optimise' ); ?></p>
					<?php elseif ( ! empty( $ga_streams ) ) : ?>
						<select class="rwgo-input" name="rwgo_gtm_measurement_id" id="rwgo_gtm_measurement_id">
							<option value=""><?php esc_html_e( '— Select measurement ID —', 'reactwoo-geo-optimise' ); ?></option>
							<?php foreach ( $ga_streams as $gs ) : ?>
								<?php
								if ( ! is_array( $gs ) || empty( $gs['measurement_id'] ) ) {
									continue;
								}
								$mid   = (string) $gs['measurement_id'];
								$label = $mid;
								$pdn   = isset( $gs['property_display_name'] ) ? (string) $gs['property_display_name'] : '';
								$sdn   = isset( $gs['display_name'] ) ? (string) $gs['display_name'] : '';
								if ( '' !== $pdn && '' !== $sdn ) {
									$label = $pdn . ' — ' . $sdn . ' (' . $mid . ')';
								} elseif ( '' !== $pdn ) {
									$label = $pdn . ' (' . $mid . ')';
								} elseif ( '' !== $sdn ) {
									$label = $sdn . ' (' . $mid . ')';
								}
								if ( ! empty( $gs['from_selected'] ) ) {
									$label .= ' — ' . __( 'Targeting default', 'reactwoo-geo-optimise' );
								}
								?>
								<option value="<?php echo esc_attr( $mid ); ?>" <?php selected( $cur_mid, $mid ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
							<?php if ( $manual_mode ) : ?>
								<option value="<?php echo esc_attr( $cur_mid ); ?>" selected><?php echo esc_html( $cur_mid . ' — ' . __( 'custom', 'reactwoo-geo-optimise' ) ); ?></option>
							<?php endif; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Loaded from your connected GA4 property (Targeting). Required to create GA4 event tags in GTM.', 'reactwoo-geo-optimise' ); ?>
							<a href="<?php echo esc_url( $ga_pro_url ); ?>"><?php esc_html_e( 'Manage GA4 in Targeting', 'reactwoo-geo-optimise' ); ?></a>
						</p>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'GA4 is connected, but no web-stream measurement IDs were found. Choose a default property in Targeting, or enter G-XXXX manually.', 'reactwoo-geo-optimise' ); ?>
							<a href="<?php echo esc_url( $ga_pro_url ); ?>"><?php esc_html_e( 'Open Google Analytics', 'reactwoo-geo-optimise' ); ?></a>
						</p>
						<input class="rwgo-input regular-text" type="text" name="rwgo_gtm_measurement_id" id="rwgo_gtm_measurement_id" value="<?php echo esc_attr( $cur_mid ); ?>" placeholder="G-XXXXXXXX" />
					<?php endif; ?>
				</div>
				<p class="rwgo-cta-row">
					<button type="button" class="button rwgo-btn rwgo-btn--secondary" id="rwgo-gtm-refresh-accounts"><?php esc_html_e( 'Refresh status', 'reactwoo-geo-optimise' ); ?></button>
					<button type="submit" class="button button-primary rwgo-btn rwgo-btn--primary"><?php esc_html_e( 'Save GTM target', 'reactwoo-geo-optimise' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	</article>

	<article class="rwgo-panel rwgo-tracking-step" id="rwgo-step-mode">
		<header class="rwgo-tracking-step__head">
			<h3 class="rwgo-tracking-step__title"><?php esc_html_e( '2. Choose tracking mode', 'reactwoo-geo-optimise' ); ?></h3>
			<span class="rwgo-tracking-badge rwgo-tracking-badge--ok"><?php echo 'advanced' === $mode ? esc_html__( 'Advanced', 'reactwoo-geo-optimise' ) : esc_html__( 'Simple', 'reactwoo-geo-optimise' ); ?></span>
		</header>
		<p class="rwgo-tracking-step__hint"><?php esc_html_e( 'Simple tracking records experiment viewed, variant assigned, and goal completed via shared events.', 'reactwoo-geo-optimise' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-tracking-mode-form">
			<?php wp_nonce_field( 'rwgo_tracking_mode' ); ?>
			<input type="hidden" name="action" value="rwgo_tracking_mode" />
			<div class="rwgo-btn-row rwgo-btn-row--wrap">
				<button type="submit" name="rwgo_tracking_mode" value="simple" class="button rwgo-btn <?php echo 'simple' === $mode ? 'button-primary rwgo-btn--primary' : 'rwgo-btn--secondary'; ?>"><?php esc_html_e( 'Simple', 'reactwoo-geo-optimise' ); ?></button>
				<button type="submit" name="rwgo_tracking_mode" value="advanced" class="button rwgo-btn <?php echo 'advanced' === $mode ? 'button-primary rwgo-btn--primary' : 'rwgo-btn--secondary'; ?>"><?php esc_html_e( 'Advanced', 'reactwoo-geo-optimise' ); ?></button>
			</div>
		</form>
		<?php if ( 'advanced' === $mode ) : ?>
			<p class="rwgo-tracking-step__hint"><?php esc_html_e( 'Advanced mode keeps the same event names. Open Technical Reference for full variable tables, GA4 mappings, and raw payloads.', 'reactwoo-geo-optimise' ); ?>
				<button type="button" class="button-link rwgo-tracking-view-btn" data-rwgo-tracking-view-btn="reference"><?php esc_html_e( 'Open Technical Reference', 'reactwoo-geo-optimise' ); ?></button>
			</p>
		<?php endif; ?>
	</article>

	<article class="rwgo-panel rwgo-tracking-step" id="rwgo-step-publish">
		<header class="rwgo-tracking-step__head">
			<h3 class="rwgo-tracking-step__title"><?php esc_html_e( '3. Publish recommended GTM assets', 'reactwoo-geo-optimise' ); ?></h3>
			<span class="rwgo-tracking-badge rwgo-tracking-badge--<?php echo ! empty( $rwgo_setup['assets_pushed'] ) ? 'ok' : 'action'; ?>"><?php echo ! empty( $rwgo_setup['assets_pushed'] ) ? esc_html__( 'Draft pushed', 'reactwoo-geo-optimise' ) : esc_html__( 'Not published', 'reactwoo-geo-optimise' ); ?></span>
		</header>
		<p class="rwgo-tracking-step__hint">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: trigger count 2: variable count 3: tag count */
					__( 'Recommended: %1$d trigger(s) · %2$d variable(s) · %3$d GA4 event tag(s)', 'reactwoo-geo-optimise' ),
					(int) ( $counts['triggers'] ?? 0 ),
					(int) ( $counts['variables'] ?? 0 ),
					(int) ( $counts['tags'] ?? 0 )
				)
			);
			?>
		</p>
		<?php if ( ! empty( $last_push['at'] ) ) : ?>
			<p class="rwgo-tracking-step__meta-line">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: datetime 2: experiment title */
						__( 'Last draft push: %1$s%2$s', 'reactwoo-geo-optimise' ),
						wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last_push['at'] ),
						! empty( $last_push['experiment_title'] ) ? ' — ' . (string) $last_push['experiment_title'] : ''
					)
				);
				?>
			</p>
		<?php endif; ?>
		<div class="rwgo-btn-row rwgo-btn-row--wrap">
			<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-copy-all-pack"><?php esc_html_e( 'Copy setup summary', 'reactwoo-geo-optimise' ); ?></button>
			<?php if ( $can_push ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
					<?php wp_nonce_field( 'rwgo_gtm_push_' . $push_exp_id ); ?>
					<input type="hidden" name="action" value="rwgo_gtm_push" />
					<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $push_exp_id; ?>" />
					<input type="hidden" name="rwgo_gtm_dry_run" value="1" />
					<button type="submit" class="button rwgo-btn rwgo-btn--secondary"><?php esc_html_e( 'Preview changes', 'reactwoo-geo-optimise' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form rwgo-gtm-push-form" data-rwgo-gtm-busy="<?php echo esc_attr__( 'Pushing to GTM… please wait (this can take up to a couple of minutes).', 'reactwoo-geo-optimise' ); ?>" onsubmit="if(!confirm('<?php echo esc_js( __( 'Create GTM workspace entities for this test? The container will not be published.', 'reactwoo-geo-optimise' ) ); ?>')){return false;} var b=this.querySelector('[type=submit]'); if(b){b.disabled=true; b.textContent=this.getAttribute('data-rwgo-gtm-busy')||'Pushing…';} return true;">
					<?php wp_nonce_field( 'rwgo_gtm_push_' . $push_exp_id ); ?>
					<input type="hidden" name="action" value="rwgo_gtm_push" />
					<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $push_exp_id; ?>" />
					<button type="submit" class="button button-primary rwgo-btn rwgo-btn--primary"><?php esc_html_e( 'Publish to GTM workspace', 'reactwoo-geo-optimise' ); ?></button>
				</form>
			<?php elseif ( ! $connected || empty( $rwgo_setup['has_container'] ) ) : ?>
				<p class="rwgo-tracking-step__hint"><?php esc_html_e( 'Connect GTM and save a container first.', 'reactwoo-geo-optimise' ); ?></p>
			<?php else : ?>
				<p class="rwgo-tracking-step__hint"><?php esc_html_e( 'Create a test with goals before pushing assets.', 'reactwoo-geo-optimise' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<p class="rwgo-tracking-step__hint">
			<button type="button" class="button-link rwgo-tracking-view-btn" data-rwgo-tracking-view-btn="reference"><?php esc_html_e( 'View technical details', 'reactwoo-geo-optimise' ); ?></button>
		</p>
	</article>

	<article class="rwgo-panel rwgo-tracking-step" id="rwgo-step-verify">
		<header class="rwgo-tracking-step__head">
			<h3 class="rwgo-tracking-step__title"><?php esc_html_e( '4. Verify tracking', 'reactwoo-geo-optimise' ); ?></h3>
			<span class="rwgo-tracking-badge rwgo-tracking-badge--<?php echo ! empty( $rwgo_setup['preflight_ready'] ) ? 'ok' : 'action'; ?>"><?php echo ! empty( $rwgo_setup['preflight_ready'] ) ? esc_html__( 'Ready', 'reactwoo-geo-optimise' ) : esc_html__( 'Not verified', 'reactwoo-geo-optimise' ); ?></span>
		</header>
		<p class="rwgo-tracking-step__hint"><?php esc_html_e( 'Open a test page and trigger the goal. Confirm events in Reports and in GTM Preview / GA4 DebugView.', 'reactwoo-geo-optimise' ); ?></p>
		<div class="rwgo-btn-row rwgo-btn-row--wrap">
			<?php if ( ! empty( $rwgo_setup['preview_url'] ) ) : ?>
				<a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( (string) $rwgo_setup['preview_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open preview page', 'reactwoo-geo-optimise' ); ?></a>
			<?php endif; ?>
			<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-reports' ) ); ?>"><?php esc_html_e( 'Check latest events', 'reactwoo-geo-optimise' ); ?></a>
			<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-copy-all-pack"><?php esc_html_e( 'Copy debug payload', 'reactwoo-geo-optimise' ); ?></button>
		</div>
	</article>

	<article class="rwgo-panel rwgo-tracking-step" id="rwgo-step-handoff">
		<header class="rwgo-tracking-step__head">
			<h3 class="rwgo-tracking-step__title"><?php esc_html_e( '5. Test-specific handoff', 'reactwoo-geo-optimise' ); ?></h3>
			<?php if ( $primary && isset( $primary['post'] ) ) : ?>
				<?php
				$ready_badge = ( class_exists( 'RWGO_GTM_Handoff', false ) && RWGO_GTM_Handoff::is_gtm_ready( $primary['cfg'] ) );
				?>
				<span class="rwgo-tracking-badge rwgo-tracking-badge--<?php echo $ready_badge ? 'ok' : 'action'; ?>"><?php echo $ready_badge ? esc_html__( 'Ready', 'reactwoo-geo-optimise' ) : esc_html__( 'Needs action', 'reactwoo-geo-optimise' ); ?></span>
			<?php else : ?>
				<span class="rwgo-tracking-badge rwgo-tracking-badge--action"><?php esc_html_e( 'No tests', 'reactwoo-geo-optimise' ); ?></span>
			<?php endif; ?>
		</header>
		<?php if ( ! $primary || ! isset( $primary['post'] ) ) : ?>
			<p class="rwgo-tracking-step__hint"><?php esc_html_e( 'Create a test first — handoff appears here for each experiment.', 'reactwoo-geo-optimise' ); ?></p>
			<p><a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></a></p>
		<?php else : ?>
			<?php
			$exp_post = $primary['post'];
			$cfg      = $primary['cfg'];
			$glab     = class_exists( 'RWGO_Goal_Service', false ) ? RWGO_Goal_Service::get_primary_goal_label( $cfg ) : '—';
			$bld      = class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::builder_slug_for_datalayer( $cfg ) : '—';
			$key      = isset( $cfg['experiment_key'] ) ? (string) $cfg['experiment_key'] : '';
			$src      = (int) ( $cfg['source_page_id'] ?? 0 );
			$var_b    = 0;
			if ( ! empty( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
				foreach ( $cfg['variants'] as $row ) {
					if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
						$var_b = (int) ( $row['page_id'] ?? 0 );
						break;
					}
				}
			}
			?>
			<p class="rwgo-tracking-step__test-title"><strong><?php echo esc_html( get_the_title( $exp_post ) ); ?></strong></p>
			<ul class="rwgo-tracking-step__meta">
				<li><strong><?php esc_html_e( 'Goal:', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( $glab ); ?></li>
				<li><strong><?php esc_html_e( 'Builder:', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( $bld ); ?></li>
				<li><strong><?php esc_html_e( 'Experiment key:', 'reactwoo-geo-optimise' ); ?></strong> <code><?php echo esc_html( $key ); ?></code></li>
				<li><strong><?php esc_html_e( 'Control:', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( $src > 0 ? get_the_title( $src ) : '—' ); ?></li>
				<li><strong><?php esc_html_e( 'Variant B:', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( $var_b > 0 ? get_the_title( $var_b ) : '—' ); ?></li>
			</ul>
			<div class="rwgo-btn-row rwgo-btn-row--wrap">
				<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-copy-all-pack"><?php esc_html_e( 'Copy summary', 'reactwoo-geo-optimise' ); ?></button>
				<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-store-vars"><?php esc_html_e( 'Copy variables', 'reactwoo-geo-optimise' ); ?></button>
				<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-store-ga4"><?php esc_html_e( 'Copy GA4 mapping', 'reactwoo-geo-optimise' ); ?></button>
				<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( RWGO_Admin::edit_test_url( (int) $exp_post->ID, 'tests' ) ); ?>"><?php esc_html_e( 'Open test', 'reactwoo-geo-optimise' ); ?></a>
				<?php if ( $can_push ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form rwgo-gtm-push-form" data-rwgo-gtm-busy="<?php echo esc_attr__( 'Pushing to GTM… please wait (this can take up to a couple of minutes).', 'reactwoo-geo-optimise' ); ?>" onsubmit="if(!confirm('<?php echo esc_js( __( 'Create GTM workspace entities for this test? The container will not be published.', 'reactwoo-geo-optimise' ) ); ?>')){return false;} var b=this.querySelector('[type=submit]'); if(b){b.disabled=true; b.textContent=this.getAttribute('data-rwgo-gtm-busy')||'Pushing…';} return true;">
						<?php wp_nonce_field( 'rwgo_gtm_push_' . (int) $exp_post->ID ); ?>
						<input type="hidden" name="action" value="rwgo_gtm_push" />
						<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $exp_post->ID; ?>" />
						<button type="submit" class="button button-primary rwgo-btn rwgo-btn--primary"><?php esc_html_e( 'Publish to GTM workspace', 'reactwoo-geo-optimise' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
			<?php if ( count( $rwgo_experiments ) > 1 ) : ?>
				<p class="rwgo-tracking-step__hint"><?php esc_html_e( 'More tests and raw payloads are listed under Technical Reference.', 'reactwoo-geo-optimise' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</article>

</div>
