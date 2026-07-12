<?php
/**
 * Tracking Technical Reference — snippets, tables, per-test raw handoff.
 *
 * Expects: $rwgo_experiments, $rwgo_setup (optional).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgo_experiments = isset( $rwgo_experiments ) && is_array( $rwgo_experiments ) ? $rwgo_experiments : array();
$rwgo_setup       = isset( $rwgo_setup ) && is_array( $rwgo_setup ) ? $rwgo_setup : array();
$connected        = ! empty( $rwgo_setup['connected'] );
$target           = isset( $rwgo_setup['target'] ) && is_array( $rwgo_setup['target'] ) ? $rwgo_setup['target'] : array(
	'account_id'   => '',
	'container_id' => '',
);

$first_example_js = class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::generic_example_datalayer_js() : '';
foreach ( $rwgo_experiments as $exp_post_try ) {
	if ( ! $exp_post_try instanceof WP_Post || ! class_exists( 'RWGO_GTM_Handoff', false ) ) {
		continue;
	}
	$cfg_try = RWGO_Experiment_Repository::get_config( $exp_post_try->ID );
	if ( RWGO_GTM_Handoff::is_gtm_ready( $cfg_try ) ) {
		$pair = RWGO_GTM_Handoff::primary_goal_handler_pair( $cfg_try );
		if ( $pair ) {
			$obj              = RWGO_GTM_Handoff::build_datalayer_example_object( $exp_post_try, $cfg_try, 'var_b', $pair['goal'], $pair['handler'] );
			$first_example_js = "window.dataLayer = window.dataLayer || [];\nwindow.dataLayer.push(" . wp_json_encode( $obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . ');';
			break;
		}
	}
}

$var_rows    = class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::standard_variable_definitions() : array();
$trigger_txt = class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::trigger_block_plain() : '';
$ga4_txt     = class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::ga4_mapping_plain() : '';
$vars_plain  = class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::variables_plain() : '';
$copy_all    = class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::copy_all_simple_pack( $first_example_js ) : '';
?>
<textarea id="rwgo-gtm-ref-copy-all" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $copy_all ); ?></textarea>
<textarea id="rwgo-gtm-ref-store-vars" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $vars_plain ); ?></textarea>
<textarea id="rwgo-gtm-ref-store-ga4" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $ga4_txt ); ?></textarea>

<section class="rwgo-tracking-reference" aria-labelledby="rwgo-tracking-ref-title">
	<h2 id="rwgo-tracking-ref-title" class="rwgo-section__title"><?php esc_html_e( 'Technical reference', 'reactwoo-geo-optimise' ); ?></h2>
	<p class="rwgo-section__lead"><?php esc_html_e( 'Agency and developer snippets. Event names and payload keys match the live Geo Optimise tracking script.', 'reactwoo-geo-optimise' ); ?></p>
	<p class="rwgo-btn-row">
		<button type="button" class="button rwgo-btn rwgo-btn--primary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-ref-copy-all"><?php esc_html_e( 'Copy all GTM setup', 'reactwoo-geo-optimise' ); ?></button>
	</p>

	<details class="rwgo-panel rwgo-tracking-ref-block" open>
		<summary class="rwgo-tracking-ref-block__summary"><?php esc_html_e( 'Event names', 'reactwoo-geo-optimise' ); ?></summary>
		<div class="rwgo-tracking-ref-block__body">
			<p><strong><?php esc_html_e( 'Goal event:', 'reactwoo-geo-optimise' ); ?></strong> <code class="rwgo-code-inline"><?php echo esc_html( class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::EVENT_NAME : 'rwgo_goal_fired' ); ?></code></p>
			<p><strong><?php esc_html_e( 'Exposure event:', 'reactwoo-geo-optimise' ); ?></strong> <code class="rwgo-code-inline"><?php echo esc_html( class_exists( 'RWGO_GTM_Handoff', false ) ? RWGO_GTM_Handoff::EXPOSURE_EVENT_NAME : 'rwgo_experiment_exposure' ); ?></code></p>
		</div>
	</details>

	<details class="rwgo-panel rwgo-tracking-ref-block">
		<summary class="rwgo-tracking-ref-block__summary"><?php esc_html_e( 'Trigger definition', 'reactwoo-geo-optimise' ); ?></summary>
		<div class="rwgo-tracking-ref-block__body">
			<pre class="rwgo-code-block rwgo-code-block--copy" id="rwgo-gtm-ref-trigger"><?php echo esc_html( $trigger_txt ); ?></pre>
			<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-ref-trigger"><?php esc_html_e( 'Copy', 'reactwoo-geo-optimise' ); ?></button>
		</div>
	</details>

	<details class="rwgo-panel rwgo-tracking-ref-block">
		<summary class="rwgo-tracking-ref-block__summary"><?php esc_html_e( 'Variables', 'reactwoo-geo-optimise' ); ?></summary>
		<div class="rwgo-tracking-ref-block__body">
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
			<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-ref-store-vars"><?php esc_html_e( 'Copy variables list', 'reactwoo-geo-optimise' ); ?></button>
		</div>
	</details>

	<details class="rwgo-panel rwgo-tracking-ref-block">
		<summary class="rwgo-tracking-ref-block__summary"><?php esc_html_e( 'GA4 mapping', 'reactwoo-geo-optimise' ); ?></summary>
		<div class="rwgo-tracking-ref-block__body">
			<pre class="rwgo-code-block rwgo-code-block--copy" id="rwgo-gtm-ref-ga4"><?php echo esc_html( $ga4_txt ); ?></pre>
			<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-ref-ga4"><?php esc_html_e( 'Copy', 'reactwoo-geo-optimise' ); ?></button>
		</div>
	</details>

	<details class="rwgo-panel rwgo-tracking-ref-block">
		<summary class="rwgo-tracking-ref-block__summary"><?php esc_html_e( 'Example dataLayer payload', 'reactwoo-geo-optimise' ); ?></summary>
		<div class="rwgo-tracking-ref-block__body">
			<pre class="rwgo-code-block rwgo-code-block--copy" id="rwgo-gtm-ref-example"><?php echo esc_html( $first_example_js ); ?></pre>
			<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-ref-example"><?php esc_html_e( 'Copy example', 'reactwoo-geo-optimise' ); ?></button>
		</div>
	</details>

	<details class="rwgo-panel rwgo-tracking-ref-block" open>
		<summary class="rwgo-tracking-ref-block__summary"><?php esc_html_e( 'Per-test raw handoff', 'reactwoo-geo-optimise' ); ?></summary>
		<div class="rwgo-tracking-ref-block__body rwgo-stack rwgo-stack--tight">
			<?php if ( empty( $rwgo_experiments ) ) : ?>
				<p class="rwgo-section__lead"><?php esc_html_e( 'Create a test first — handoff cards appear here for each experiment.', 'reactwoo-geo-optimise' ); ?></p>
			<?php else : ?>
				<?php
				foreach ( $rwgo_experiments as $exp_post ) :
					if ( ! $exp_post instanceof WP_Post ) {
						continue;
					}
					$cfg   = RWGO_Experiment_Repository::get_config( $exp_post->ID );
					$ready = class_exists( 'RWGO_GTM_Handoff', false ) && RWGO_GTM_Handoff::is_gtm_ready( $cfg );
					$st    = isset( $cfg['status'] ) ? (string) $cfg['status'] : '';
					$glab  = class_exists( 'RWGO_Goal_Service', false ) ? RWGO_Goal_Service::get_primary_goal_label( $cfg ) : '—';
					$key   = isset( $cfg['experiment_key'] ) ? (string) $cfg['experiment_key'] : '';
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
						<ul class="rwgo-gtm-test-card__summary">
							<li><strong><?php esc_html_e( 'Goal:', 'reactwoo-geo-optimise' ); ?></strong> <?php echo esc_html( $glab ); ?></li>
							<li><strong><?php esc_html_e( 'Experiment key:', 'reactwoo-geo-optimise' ); ?></strong> <code><?php echo esc_html( $key ); ?></code></li>
						</ul>
						<pre class="rwgo-code-block rwgo-code-block--copy" id="rwgo-gtm-ref-test-<?php echo (int) $exp_post->ID; ?>"><?php echo esc_html( $js ); ?></pre>
						<div class="rwgo-btn-row rwgo-btn-row--wrap">
							<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-ref-test-<?php echo (int) $exp_post->ID; ?>"><?php esc_html_e( 'Copy example', 'reactwoo-geo-optimise' ); ?></button>
							<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-ref-store-vars"><?php esc_html_e( 'Copy variables', 'reactwoo-geo-optimise' ); ?></button>
							<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-ref-store-ga4"><?php esc_html_e( 'Copy GA4 mapping', 'reactwoo-geo-optimise' ); ?></button>
							<a class="button rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( $pack_url ); ?>"><?php esc_html_e( 'Download GTM pack', 'reactwoo-geo-optimise' ); ?></a>
							<?php if ( $connected && '' !== (string) ( $target['account_id'] ?? '' ) && '' !== (string) ( $target['container_id'] ?? '' ) ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form">
									<?php wp_nonce_field( 'rwgo_gtm_push_' . (int) $exp_post->ID ); ?>
									<input type="hidden" name="action" value="rwgo_gtm_push" />
									<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $exp_post->ID; ?>" />
									<input type="hidden" name="rwgo_gtm_dry_run" value="1" />
									<button type="submit" class="button rwgo-btn rwgo-btn--secondary"><?php esc_html_e( 'Preview API push', 'reactwoo-geo-optimise' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-inline-form rwgo-gtm-push-form" data-rwgo-gtm-busy="<?php echo esc_attr__( 'Pushing to GTM… please wait (this can take up to a couple of minutes).', 'reactwoo-geo-optimise' ); ?>" onsubmit="if(!confirm('<?php echo esc_js( __( 'Create GTM workspace entities for this test? The container will not be published.', 'reactwoo-geo-optimise' ) ); ?>')){return false;} var b=this.querySelector('[type=submit]'); if(b){b.disabled=true; b.textContent=this.getAttribute('data-rwgo-gtm-busy')||'Pushing…';} return true;">
									<?php wp_nonce_field( 'rwgo_gtm_push_' . (int) $exp_post->ID ); ?>
									<input type="hidden" name="action" value="rwgo_gtm_push" />
									<input type="hidden" name="rwgo_experiment_id" value="<?php echo (int) $exp_post->ID; ?>" />
									<button type="submit" class="button button-primary rwgo-btn rwgo-btn--primary"><?php esc_html_e( 'Push to GTM workspace', 'reactwoo-geo-optimise' ); ?></button>
								</form>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</details>

	<details class="rwgo-panel rwgo-tracking-ref-block">
		<summary class="rwgo-tracking-ref-block__summary"><?php esc_html_e( 'Debug snippets & measurement guidance', 'reactwoo-geo-optimise' ); ?></summary>
		<div class="rwgo-tracking-ref-block__body">
			<?php include RWGO_PATH . 'admin/views/partials/tools-section-tracking-advanced.php'; ?>
		</div>
	</details>
</section>
