<?php
/**
 * GTM Quick Setup — shared standards + per-test handoff cards.
 *
 * Expects: $rwgo_experiments (array of WP_Post), class RWGO_GTM_Handoff.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgo_experiments = isset( $rwgo_experiments ) && is_array( $rwgo_experiments ) ? $rwgo_experiments : array();

$first_example_js = RWGO_GTM_Handoff::generic_example_datalayer_js();
foreach ( $rwgo_experiments as $exp_post_try ) {
	if ( ! $exp_post_try instanceof \WP_Post ) {
		continue;
	}
	$cfg_try = RWGO_Experiment_Repository::get_config( $exp_post_try->ID );
	if ( RWGO_GTM_Handoff::is_gtm_ready( $cfg_try ) ) {
		$pair = RWGO_GTM_Handoff::primary_goal_handler_pair( $cfg_try );
		if ( $pair ) {
			$obj            = RWGO_GTM_Handoff::build_datalayer_example_object( $exp_post_try, $cfg_try, 'var_b', $pair['goal'], $pair['handler'] );
			$first_example_js = "window.dataLayer = window.dataLayer || [];\nwindow.dataLayer.push(" . wp_json_encode( $obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . ');';
			break;
		}
	}
}

$copy_all_top = RWGO_GTM_Handoff::copy_all_simple_pack( $first_example_js );
$var_rows     = RWGO_GTM_Handoff::standard_variable_definitions();
$trigger_txt  = RWGO_GTM_Handoff::trigger_block_plain();
$ga4_txt      = RWGO_GTM_Handoff::ga4_mapping_plain();
$vars_plain   = RWGO_GTM_Handoff::variables_plain();

$rwgo_gtm_connected = class_exists( 'RWGO_GTM_Live', false ) && RWGO_GTM_Live::is_connected();
$rwgo_gtm_target    = class_exists( 'RWGO_GTM_Live', false ) ? RWGO_GTM_Live::get_target() : array(
	'account_id'     => '',
	'container_id'   => '',
	'workspace_id'   => '',
	'measurement_id' => '',
);
$rwgo_gtm_discovery = ( $rwgo_gtm_connected && class_exists( 'RWGO_GTM_Live', false ) )
	? RWGO_GTM_Live::discovery_for_admin()
	: array(
		'accounts'   => array(),
		'containers' => array(),
		'workspaces' => array(),
		'error'      => '',
	);
$rwgo_gtm_err  = isset( $_GET['rwgo_gtm_err'] ) ? sanitize_text_field( wp_unslash( $_GET['rwgo_gtm_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$rwgo_gtm_ok   = isset( $_GET['rwgo_gtm_ok'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$rwgo_gtm_saved = isset( $_GET['rwgo_gtm_saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$rwgo_gtm_last = get_transient( 'rwgo_gtm_last_result_' . get_current_user_id() );
?>
<textarea id="rwgo-gtm-copy-all-pack" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $copy_all_top ); ?></textarea>
<textarea id="rwgo-gtm-store-vars" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $vars_plain ); ?></textarea>
<textarea id="rwgo-gtm-store-ga4" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $ga4_txt ); ?></textarea>
<section class="rwgo-panel rwgo-gtm-quick" aria-labelledby="rwgo-gtm-quick-title" data-rwgo-gtm-mode="simple">
	<div class="rwgo-gtm-quick__head">
		<h2 id="rwgo-gtm-quick-title" class="rwgo-section__title"><?php esc_html_e( 'GTM Quick Setup', 'reactwoo-geo-optimise' ); ?></h2>
		<p class="rwgo-section__lead"><?php esc_html_e( 'Geo Optimise uses one shared event name and test-specific parameters so agencies can report on multiple tests safely without inventing a new event structure for every test. Download a GTM provision pack per test for offline import, or connect Google Tag Manager via React Cloud to push variables/triggers/tags into a workspace draft.', 'reactwoo-geo-optimise' ); ?></p>

	<?php if ( '' !== $rwgo_gtm_err ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $rwgo_gtm_err ); ?></p></div>
	<?php endif; ?>
	<?php if ( $rwgo_gtm_ok ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Google Tag Manager connected via React Cloud.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $rwgo_gtm_saved ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'GTM target saved.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgo-gtm-block rwgo-gtm-live">
		<h3 class="rwgo-gtm-block__title"><?php esc_html_e( 'Live GTM push (React Cloud)', 'reactwoo-geo-optimise' ); ?></h3>
		<p class="rwgo-gtm-block__hint"><?php esc_html_e( 'OAuth tokens stay on React Cloud. Push creates draft workspace entities (variables, triggers, optional GA4 tags) — it does not publish the container.', 'reactwoo-geo-optimise' ); ?></p>
		<div class="rwgo-btn-row rwgo-btn-row--wrap">
			<?php if ( ! $rwgo_gtm_connected ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
					<?php wp_nonce_field( 'rwgo_gtm_connect' ); ?>
					<input type="hidden" name="action" value="rwgo_gtm_connect" />
					<button type="submit" class="button button-primary rwgo-btn rwgo-btn--primary"><?php esc_html_e( 'Connect Google Tag Manager', 'reactwoo-geo-optimise' ); ?></button>
				</form>
			<?php else : ?>
				<span class="rwgo-meta-pill rwgo-meta-pill--ok"><?php esc_html_e( 'Connected', 'reactwoo-geo-optimise' ); ?></span>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
					<?php wp_nonce_field( 'rwgo_gtm_disconnect' ); ?>
					<input type="hidden" name="action" value="rwgo_gtm_disconnect" />
					<button type="submit" class="button rwgo-btn rwgo-btn--secondary"><?php esc_html_e( 'Disconnect (local flag)', 'reactwoo-geo-optimise' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php if ( $rwgo_gtm_connected ) : ?>
			<?php if ( ! empty( $rwgo_gtm_discovery['error'] ) ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( (string) $rwgo_gtm_discovery['error'] ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-gtm-target-form" id="rwgo-gtm-target-form" data-rwgo-gtm-picker="1">
				<?php wp_nonce_field( 'rwgo_gtm_save_target' ); ?>
				<input type="hidden" name="action" value="rwgo_gtm_save_target" />
				<div class="rwgo-field">
					<label class="rwgo-field__label" for="rwgo_gtm_account_id"><?php esc_html_e( 'GTM account', 'reactwoo-geo-optimise' ); ?></label>
					<select class="rwgo-input" name="rwgo_gtm_account_id" id="rwgo_gtm_account_id" required>
						<option value=""><?php esc_html_e( '— Select account —', 'reactwoo-geo-optimise' ); ?></option>
						<?php foreach ( (array) $rwgo_gtm_discovery['accounts'] as $acc_row ) : ?>
							<?php
							if ( ! is_array( $acc_row ) || empty( $acc_row['id'] ) ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( (string) $acc_row['id'] ); ?>" <?php selected( $rwgo_gtm_target['account_id'], (string) $acc_row['id'] ); ?>><?php echo esc_html( (string) ( $acc_row['label'] ?? $acc_row['id'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="rwgo-field">
					<label class="rwgo-field__label" for="rwgo_gtm_container_id"><?php esc_html_e( 'Container', 'reactwoo-geo-optimise' ); ?></label>
					<select class="rwgo-input" name="rwgo_gtm_container_id" id="rwgo_gtm_container_id" required <?php echo '' === $rwgo_gtm_target['account_id'] ? 'disabled' : ''; ?>>
						<option value=""><?php esc_html_e( '— Select container —', 'reactwoo-geo-optimise' ); ?></option>
						<?php foreach ( (array) $rwgo_gtm_discovery['containers'] as $c_row ) : ?>
							<?php
							if ( ! is_array( $c_row ) || empty( $c_row['id'] ) ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( (string) $c_row['id'] ); ?>" <?php selected( $rwgo_gtm_target['container_id'], (string) $c_row['id'] ); ?>><?php echo esc_html( (string) ( $c_row['label'] ?? $c_row['id'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="rwgo-field">
					<label class="rwgo-field__label" for="rwgo_gtm_workspace_id"><?php esc_html_e( 'Workspace', 'reactwoo-geo-optimise' ); ?></label>
					<select class="rwgo-input" name="rwgo_gtm_workspace_id" id="rwgo_gtm_workspace_id" <?php echo '' === $rwgo_gtm_target['container_id'] ? 'disabled' : ''; ?>>
						<option value=""><?php esc_html_e( '— Default workspace —', 'reactwoo-geo-optimise' ); ?></option>
						<?php foreach ( (array) $rwgo_gtm_discovery['workspaces'] as $w_row ) : ?>
							<?php
							if ( ! is_array( $w_row ) || empty( $w_row['id'] ) ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( (string) $w_row['id'] ); ?>" <?php selected( $rwgo_gtm_target['workspace_id'], (string) $w_row['id'] ); ?>><?php echo esc_html( (string) ( $w_row['label'] ?? $w_row['id'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="rwgo-field">
					<label class="rwgo-field__label" for="rwgo_gtm_measurement_id"><?php esc_html_e( 'GA4 measurement ID (optional)', 'reactwoo-geo-optimise' ); ?></label>
					<input class="rwgo-input regular-text" type="text" name="rwgo_gtm_measurement_id" id="rwgo_gtm_measurement_id" value="<?php echo esc_attr( $rwgo_gtm_target['measurement_id'] ); ?>" placeholder="G-XXXXXXXX" />
					<p class="description"><?php esc_html_e( 'Required to create GA4 event tags. Without it, only variables and triggers are pushed.', 'reactwoo-geo-optimise' ); ?></p>
				</div>
				<p class="rwgo-cta-row">
					<button type="button" class="button rwgo-btn rwgo-btn--secondary" id="rwgo-gtm-refresh-accounts"><?php esc_html_e( 'Refresh accounts', 'reactwoo-geo-optimise' ); ?></button>
					<button type="submit" class="button rwgo-btn rwgo-btn--primary"><?php esc_html_e( 'Save GTM target', 'reactwoo-geo-optimise' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
		<?php if ( is_array( $rwgo_gtm_last ) ) : ?>
			<details class="rwgo-gtm-last-result">
				<summary><?php esc_html_e( 'Last push / preview result', 'reactwoo-geo-optimise' ); ?></summary>
				<pre class="rwgo-code-block"><?php echo esc_html( wp_json_encode( $rwgo_gtm_last, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
			</details>
		<?php endif; ?>
	</div>

		<div class="rwgo-btn-row rwgo-gtm-quick__toolbar">
			<button type="button" class="button rwgo-btn rwgo-btn--primary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-copy-all-pack"><?php esc_html_e( 'Copy all GTM setup', 'reactwoo-geo-optimise' ); ?></button>
			<p class="rwgo-gtm-mode-toggle">
				<span class="rwgo-gtm-mode-toggle__label"><?php esc_html_e( 'View:', 'reactwoo-geo-optimise' ); ?></span>
				<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-gtm-mode-btn is-active" data-rwgo-gtm-mode-btn="simple"><?php esc_html_e( 'Simple', 'reactwoo-geo-optimise' ); ?></button>
				<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-gtm-mode-btn" data-rwgo-gtm-mode-btn="advanced"><?php esc_html_e( 'Advanced', 'reactwoo-geo-optimise' ); ?></button>
			</p>
		</div>
	</div>

	<div class="rwgo-gtm-block">
		<h3 class="rwgo-gtm-block__title"><?php esc_html_e( 'Recommended event', 'reactwoo-geo-optimise' ); ?></h3>
		<p class="rwgo-gtm-block__hint"><?php esc_html_e( 'Use one shared custom event and separate tests using the parameters Geo Optimise provides.', 'reactwoo-geo-optimise' ); ?></p>
		<p><strong><?php esc_html_e( 'Recommended GTM event:', 'reactwoo-geo-optimise' ); ?></strong> <code class="rwgo-code-inline"><?php echo esc_html( RWGO_GTM_Handoff::EVENT_NAME ); ?></code></p>
	</div>

	<div class="rwgo-gtm-block">
		<h3 class="rwgo-gtm-block__title"><?php esc_html_e( 'Recommended trigger', 'reactwoo-geo-optimise' ); ?></h3>
		<pre class="rwgo-code-block rwgo-code-block--copy" id="rwgo-gtm-copy-trigger"><?php echo esc_html( $trigger_txt ); ?></pre>
		<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-copy-trigger"><?php esc_html_e( 'Copy', 'reactwoo-geo-optimise' ); ?></button>
	</div>

	<div class="rwgo-gtm-block">
		<h3 class="rwgo-gtm-block__title"><?php esc_html_e( 'Recommended variables', 'reactwoo-geo-optimise' ); ?></h3>
		<div class="rwgo-table-wrap">
			<table class="rwgo-table rwgo-gtm-var-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Variable label', 'reactwoo-geo-optimise' ); ?></th>
						<th scope="col"><?php esc_html_e( 'GTM type', 'reactwoo-geo-optimise' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Data Layer Variable Name', 'reactwoo-geo-optimise' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Action', 'reactwoo-geo-optimise' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $var_rows as $row ) : ?>
					<tr>
						<td><code><?php echo esc_html( $row['label'] ); ?></code></td>
						<td><?php echo esc_html( $row['gtm_type'] ); ?></td>
						<td><code><?php echo esc_html( $row['key'] ); ?></code></td>
						<td><button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-btn--sm rwgo-copy-btn" data-rwgo-copy-text="<?php echo esc_attr( $row['key'] ); ?>"><?php esc_html_e( 'Copy', 'reactwoo-geo-optimise' ); ?></button></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-store-vars"><?php esc_html_e( 'Copy variables list', 'reactwoo-geo-optimise' ); ?></button>
	</div>

	<div class="rwgo-gtm-block">
		<h3 class="rwgo-gtm-block__title"><?php esc_html_e( 'Recommended GA4 mapping', 'reactwoo-geo-optimise' ); ?></h3>
		<pre class="rwgo-code-block rwgo-code-block--copy" id="rwgo-gtm-copy-ga4"><?php echo esc_html( $ga4_txt ); ?></pre>
		<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-copy-ga4"><?php esc_html_e( 'Copy', 'reactwoo-geo-optimise' ); ?></button>
		<p class="rwgo-gtm-note"><?php esc_html_e( 'This lets you report by test, goal, and variant without creating separate GA4 events for every experiment.', 'reactwoo-geo-optimise' ); ?></p>
	</div>

	<div class="rwgo-gtm-block">
		<h3 class="rwgo-gtm-block__title"><?php esc_html_e( 'Example dataLayer push', 'reactwoo-geo-optimise' ); ?></h3>
		<pre class="rwgo-code-block rwgo-code-block--copy" id="rwgo-gtm-copy-example-global"><?php echo esc_html( $first_example_js ); ?></pre>
		<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-copy-example-global"><?php esc_html_e( 'Copy example', 'reactwoo-geo-optimise' ); ?></button>
	</div>

	<div class="rwgo-gtm-advanced-block" hidden>
		<div class="rwgo-gtm-block rwgo-callout rwgo-callout--muted">
			<p class="rwgo-gtm-block__title"><?php esc_html_e( 'Advanced: multiple goals & handlers', 'reactwoo-geo-optimise' ); ?></p>
			<p class="rwgo-section__lead"><?php esc_html_e( 'Each test can define multiple goals; each goal can have multiple measurement handlers (e.g. several CTAs). The same rwgo_goal_fired event fires for all of them — use rwgo_goal_id and rwgo_handler_id to distinguish.', 'reactwoo-geo-optimise' ); ?></p>
			<p class="rwgo-setting-row__hint"><?php esc_html_e( 'For raw REST payloads, REST discovery, and PHP hooks, use Developer.', 'reactwoo-geo-optimise' ); ?>
				<a href="<?php echo esc_url( RWGO_Admin::developer_url( 'developer' ) ); ?>"><?php esc_html_e( 'Open Developer', 'reactwoo-geo-optimise' ); ?></a></p>
		</div>
	</div>

	<h3 class="rwgo-section-label rwgo-gtm-per-test-heading"><?php esc_html_e( 'Per-test GTM handoff', 'reactwoo-geo-optimise' ); ?></h3>
	<div class="rwgo-stack rwgo-stack--tight rwgo-gtm-per-test-cards">
		<?php if ( empty( $rwgo_experiments ) ) : ?>
			<div class="rwgo-panel rwgo-panel--compact">
				<p class="rwgo-section__lead"><?php esc_html_e( 'Create a test first — handoff cards appear here for each experiment.', 'reactwoo-geo-optimise' ); ?></p>
				<p><a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></a></p>
			</div>
		<?php else : ?>
			<?php
			foreach ( $rwgo_experiments as $exp_post ) :
				if ( ! $exp_post instanceof \WP_Post ) {
					continue;
				}
				$cfg   = RWGO_Experiment_Repository::get_config( $exp_post->ID );
				$ready = RWGO_GTM_Handoff::is_gtm_ready( $cfg );
				$st    = isset( $cfg['status'] ) ? (string) $cfg['status'] : '';
				$glab  = class_exists( 'RWGO_Goal_Service', false ) ? RWGO_Goal_Service::get_primary_goal_label( $cfg ) : '—';
				$bld   = RWGO_GTM_Handoff::builder_slug_for_datalayer( $cfg );
				$key   = isset( $cfg['experiment_key'] ) ? (string) $cfg['experiment_key'] : '';
				$src   = (int) ( $cfg['source_page_id'] ?? 0 );
				$var_b = 0;
				if ( ! empty( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
					foreach ( $cfg['variants'] as $row ) {
						if ( is_array( $row ) && isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
							$var_b = (int) ( $row['page_id'] ?? 0 );
							break;
						}
					}
				}
				$ctitle = $src > 0 ? get_the_title( $src ) : '—';
				$vtitle = $var_b > 0 ? get_the_title( $var_b ) : '—';
				?>
			<div class="rwgo-panel rwgo-gtm-test-card">
				<div class="rwgo-gtm-test-card__header">
					<h4 class="rwgo-gtm-test-card__title"><?php echo esc_html( get_the_title( $exp_post ) ); ?></h4>
					<span class="rwgo-meta-pill"><?php echo esc_html( $st ); ?></span>
				</div>
				<?php if ( ! $ready ) : ?>
					<p class="rwgo-gtm-test-card__warn"><?php esc_html_e( 'Configure at least one goal with a measurement handler in Edit Test to generate a dataLayer example.', 'reactwoo-geo-optimise' ); ?></p>
					<p><a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( RWGO_Admin::edit_test_url( (int) $exp_post->ID, 'tests' ) ); ?>"><?php esc_html_e( 'Edit Test', 'reactwoo-geo-optimise' ); ?></a></p>
				<?php else : ?>
					<?php
					$pair = RWGO_GTM_Handoff::primary_goal_handler_pair( $cfg );
					$obj  = RWGO_GTM_Handoff::build_datalayer_example_object( $exp_post, $cfg, 'var_b', $pair['goal'], $pair['handler'] );
					$js   = "window.dataLayer = window.dataLayer || [];\nwindow.dataLayer.push(" . wp_json_encode( $obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . ');';
					?>
					<ul class="rwgo-gtm-test-card__summary">
						<li><strong><?php esc_html_e( 'Success focus (label):', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( $glab ); ?></li>
						<li><strong><?php esc_html_e( 'Builder:', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( $bld ); ?></li>
						<li><strong><?php esc_html_e( 'Experiment key:', 'reactwoo-geo-optimise' ); ?></strong> <code><?php echo esc_html( $key ); ?></code></li>
						<li><strong><?php esc_html_e( 'Control:', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( $ctitle ); ?></li>
						<li><strong><?php esc_html_e( 'Variant B:', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( $vtitle ); ?></li>
						<li><strong><?php esc_html_e( 'Event:', 'reactwoo-geo-optimise' ); ?></strong> <code><?php echo esc_html( RWGO_GTM_Handoff::EVENT_NAME ); ?></code></li>
					</ul>
					<?php
					$preflight = class_exists( 'RWGO_Tracking_Preflight', false )
						? RWGO_Tracking_Preflight::run( $exp_post, $cfg )
						: null;
					if ( is_array( $preflight ) ) :
						?>
					<div class="rwgo-gtm-preflight">
						<p class="rwgo-gtm-preflight__title">
							<strong><?php esc_html_e( 'Tracking preflight', 'reactwoo-geo-optimise' ); ?></strong>
							<?php if ( ! empty( $preflight['ready'] ) ) : ?>
								<span class="rwgo-meta-pill rwgo-meta-pill--ok"><?php esc_html_e( 'Ready', 'reactwoo-geo-optimise' ); ?></span>
							<?php else : ?>
								<span class="rwgo-meta-pill"><?php esc_html_e( 'Needs attention', 'reactwoo-geo-optimise' ); ?></span>
							<?php endif; ?>
						</p>
						<ul class="rwgo-gtm-preflight__list">
							<?php foreach ( (array) ( $preflight['checks'] ?? array() ) as $chk ) : ?>
								<?php if ( ! is_array( $chk ) ) { continue; } ?>
								<li class="<?php echo ! empty( $chk['ok'] ) ? 'is-ok' : 'is-fail'; ?>">
									<span class="rwgo-gtm-preflight__label"><?php echo esc_html( (string) ( $chk['label'] ?? '' ) ); ?></span>
									<span class="rwgo-gtm-preflight__detail"><?php echo esc_html( (string) ( $chk['detail'] ?? '' ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
					<?php endif; ?>
					<pre class="rwgo-code-block rwgo-code-block--copy" id="rwgo-gtm-test-example-<?php echo (int) $exp_post->ID; ?>"><?php echo esc_html( $js ); ?></pre>
					<div class="rwgo-btn-row rwgo-btn-row--wrap">
						<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-test-example-<?php echo (int) $exp_post->ID; ?>"><?php esc_html_e( 'Copy example', 'reactwoo-geo-optimise' ); ?></button>
						<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-store-vars"><?php esc_html_e( 'Copy variables', 'reactwoo-geo-optimise' ); ?></button>
						<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-store-ga4"><?php esc_html_e( 'Copy GA4 mapping', 'reactwoo-geo-optimise' ); ?></button>
						<?php
						$pack_url = wp_nonce_url(
							add_query_arg(
								array(
									'action'        => 'rwgo_download_gtm_pack',
									'experiment_id' => (int) $exp_post->ID,
								),
								admin_url( 'admin-post.php' )
							),
							'rwgo_download_gtm_pack_' . (int) $exp_post->ID
						);
						?>
						<a class="button rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( $pack_url ); ?>"><?php esc_html_e( 'Download GTM pack', 'reactwoo-geo-optimise' ); ?></a>
						<?php if ( $rwgo_gtm_connected && '' !== $rwgo_gtm_target['account_id'] && '' !== $rwgo_gtm_target['container_id'] ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
								<?php wp_nonce_field( 'rwgo_gtm_push_' . (int) $exp_post->ID ); ?>
								<input type="hidden" name="action" value="rwgo_gtm_push" />
								<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $exp_post->ID; ?>" />
								<input type="hidden" name="rwgo_gtm_dry_run" value="1" />
								<button type="submit" class="button rwgo-btn rwgo-btn--secondary"><?php esc_html_e( 'Preview API push', 'reactwoo-geo-optimise' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Create GTM workspace entities for this test? The container will not be published.', 'reactwoo-geo-optimise' ) ); ?>');">
								<?php wp_nonce_field( 'rwgo_gtm_push_' . (int) $exp_post->ID ); ?>
								<input type="hidden" name="action" value="rwgo_gtm_push" />
								<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $exp_post->ID; ?>" />
								<button type="submit" class="button button-primary rwgo-btn rwgo-btn--primary"><?php esc_html_e( 'Push to GTM workspace', 'reactwoo-geo-optimise' ); ?></button>
							</form>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
				<?php
			endforeach;
			?>
		<?php endif; ?>
	</div>
</section>
