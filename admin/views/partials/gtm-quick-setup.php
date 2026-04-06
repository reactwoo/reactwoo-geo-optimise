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
?>
<textarea id="rwgo-gtm-copy-all-pack" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $copy_all_top ); ?></textarea>
<textarea id="rwgo-gtm-store-vars" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $vars_plain ); ?></textarea>
<textarea id="rwgo-gtm-store-ga4" class="rwgo-copy-source-hidden" readonly hidden><?php echo esc_textarea( $ga4_txt ); ?></textarea>
<section class="rwgo-panel rwgo-gtm-quick" aria-labelledby="rwgo-gtm-quick-title" data-rwgo-gtm-mode="simple">
	<div class="rwgo-gtm-quick__head">
		<h2 id="rwgo-gtm-quick-title" class="rwgo-section__title"><?php esc_html_e( 'GTM Quick Setup', 'reactwoo-geo-optimise' ); ?></h2>
		<p class="rwgo-section__lead"><?php esc_html_e( 'Geo Optimise uses one shared event name and test-specific parameters so agencies can report on multiple tests safely without inventing a new event structure for every test.', 'reactwoo-geo-optimise' ); ?></p>
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
					<pre class="rwgo-code-block rwgo-code-block--copy" id="rwgo-gtm-test-example-<?php echo (int) $exp_post->ID; ?>"><?php echo esc_html( $js ); ?></pre>
					<div class="rwgo-btn-row rwgo-btn-row--wrap">
						<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-test-example-<?php echo (int) $exp_post->ID; ?>"><?php esc_html_e( 'Copy example', 'reactwoo-geo-optimise' ); ?></button>
						<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-store-vars"><?php esc_html_e( 'Copy variables', 'reactwoo-geo-optimise' ); ?></button>
						<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-copy-btn" data-rwgo-copy-target="#rwgo-gtm-store-ga4"><?php esc_html_e( 'Copy GA4 mapping', 'reactwoo-geo-optimise' ); ?></button>
					</div>
				<?php endif; ?>
			</div>
				<?php
			endforeach;
			?>
		<?php endif; ?>
	</div>
</section>
