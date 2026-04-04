<?php
/**
 * Tracking: guidance grid + generated snippets by test.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgo_experiments = isset( $rwgo_experiments ) && is_array( $rwgo_experiments ) ? $rwgo_experiments : array();
?>

<section class="rwgo-tracking-guidance" aria-labelledby="rwgo-tracking-guidance-title">
	<h2 id="rwgo-tracking-guidance-title" class="screen-reader-text"><?php esc_html_e( 'Measurement guidance', 'reactwoo-geo-optimise' ); ?></h2>
	<div class="rwgo-grid rwgo-grid--2">
		<div class="rwgo-panel rwgo-panel--compact">
			<h3 class="rwgo-section__title"><?php esc_html_e( 'HTML stamps (automatic clicks)', 'reactwoo-geo-optimise' ); ?></h3>
			<p class="rwgo-setting-row__hint"><?php esc_html_e( 'The front-end script binds elements that include data-rwgo-experiment-key, data-rwgo-goal-id, and data-rwgo-handler-id. Optional: data-rwgo-variant-id (otherwise the resolved variant from the server is used), data-rwgo-element-fingerprint. Under Settings → Strict binding, a non-empty fingerprint is required on stamped elements; automatic page-view goals are skipped in that mode. Matching goals are also sent to the REST API so they appear in Reports (see Developer → Browser REST endpoint).', 'reactwoo-geo-optimise' ); ?></p>
		</div>
		<div class="rwgo-panel rwgo-panel--compact">
			<h3 class="rwgo-section__title"><?php esc_html_e( 'GA4 recommendation', 'reactwoo-geo-optimise' ); ?></h3>
			<p class="rwgo-setting-row__hint"><?php esc_html_e( 'Send one event name and put test-specific data in parameters so multiple tests never overwrite each other in analytics.', 'reactwoo-geo-optimise' ); ?></p>
			<ul class="rwgo-checklist">
				<li><?php esc_html_e( 'Suggested event name:', 'reactwoo-geo-optimise' ); ?> <code>rwgo_goal_fired</code></li>
				<li><?php esc_html_e( 'Suggested parameters: experiment and goal identifiers (see snippets below).', 'reactwoo-geo-optimise' ); ?></li>
			</ul>
		</div>
	</div>
</section>

<section class="rwgo-tracking-snippets" aria-labelledby="rwgo-tracking-snippets-title">
	<h2 id="rwgo-tracking-snippets-title" class="rwgo-section-label"><?php esc_html_e( 'Generated snippets by test', 'reactwoo-geo-optimise' ); ?></h2>

	<?php if ( empty( $rwgo_experiments ) ) : ?>
		<div class="rwgo-panel rwgo-panel--compact">
			<p class="rwgo-section__lead"><?php esc_html_e( 'Create and publish a test first — snippets are generated for each goal and measurement point.', 'reactwoo-geo-optimise' ); ?></p>
			<p class="rwgo-actions">
				<a class="button button-primary rwgo-btn rwgo-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<div class="rwgo-stack rwgo-stack--tight">
			<?php
			foreach ( $rwgo_experiments as $exp_post ) :
				if ( ! $exp_post instanceof \WP_Post ) {
					continue;
				}
				$cfg = RWGO_Experiment_Repository::get_config( $exp_post->ID );
				$key = isset( $cfg['experiment_key'] ) ? (string) $cfg['experiment_key'] : '';
				$src = (int) ( $cfg['source_page_id'] ?? 0 );
				if ( '' === $key ) {
					continue;
				}
				$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
				?>
			<div class="rwgo-panel">
				<h3 class="rwgo-test-card__title"><?php echo esc_html( get_the_title( $exp_post ) ); ?></h3>
				<p class="description"><?php esc_html_e( 'Internal test reference (for GTM variables and debugging):', 'reactwoo-geo-optimise' ); ?> <code class="rwgo-muted-key"><?php echo esc_html( $key ); ?></code></p>

				<?php if ( empty( $goals ) ) : ?>
					<p class="rwgo-empty-hint"><?php esc_html_e( 'No goals configured for this test.', 'reactwoo-geo-optimise' ); ?></p>
				<?php else : ?>
					<div class="rwgo-snippet-stack">
						<?php foreach ( $goals as $goal ) : ?>
							<?php
							if ( ! is_array( $goal ) ) {
								continue;
							}
							$gid      = isset( $goal['goal_id'] ) ? (string) $goal['goal_id'] : '';
							$handlers = isset( $goal['handlers'] ) && is_array( $goal['handlers'] ) ? $goal['handlers'] : array();
							$glabel   = isset( $goal['label'] ) ? (string) $goal['label'] : __( 'Goal', 'reactwoo-geo-optimise' );
							?>
						<details class="rwgo-goal-snippet" open>
							<summary><?php echo esc_html( $glabel ); ?> — <code><?php echo esc_html( $gid ); ?></code></summary>
							<div class="rwgo-goal-snippet__body">
								<?php foreach ( $handlers as $handler ) : ?>
									<?php
									if ( ! is_array( $handler ) ) {
										continue;
									}
									$hid    = isset( $handler['handler_id'] ) ? (string) $handler['handler_id'] : '';
									$hlabel = isset( $handler['label'] ) ? (string) $handler['label'] : __( 'Measurement point', 'reactwoo-geo-optimise' );
									$detail = wp_json_encode(
										array(
											'experiment_key'  => $key,
											'experiment_id'   => (int) $exp_post->ID,
											'goal_id'         => $gid,
											'handler_id'      => $hid,
											'variant_id'      => 'var_b',
											'page_context_id' => $src,
										),
										JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
									);
									$js     = 'window.dispatchEvent(new CustomEvent("rwgo:goal", { detail: ' . $detail . ' }));';
									$dl_obj = array_merge(
										array( 'event' => 'rwgo_goal_fired' ),
										array(
											'rwgo_experiment_key'  => $key,
											'rwgo_experiment_id'   => (int) $exp_post->ID,
											'rwgo_goal_id'         => $gid,
											'rwgo_handler_id'      => $hid,
											'rwgo_variant_id'      => 'var_b',
											'rwgo_page_context_id' => $src,
										)
									);
									$dl     = "window.dataLayer = window.dataLayer || [];\nwindow.dataLayer.push(" . wp_json_encode( $dl_obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . ');';
									?>
								<div class="rwgo-handler-snippet">
									<h4 class="rwgo-handler-snippet__title"><?php echo esc_html( $hlabel ); ?> <span class="rwgo-muted">— <?php esc_html_e( 'Handler ID:', 'reactwoo-geo-optimise' ); ?> <code><?php echo esc_html( $hid ); ?></code></span></h4>
									<details class="rwgo-nested-code">
										<summary class="rwgo-nested-code__summary"><?php esc_html_e( 'Browser CustomEvent', 'reactwoo-geo-optimise' ); ?></summary>
										<pre class="rwgo-code-block"><code><?php echo esc_html( $js ); ?></code></pre>
									</details>
									<details class="rwgo-nested-code rwgo-nested-code--spaced">
										<summary class="rwgo-nested-code__summary"><?php esc_html_e( 'Google dataLayer push', 'reactwoo-geo-optimise' ); ?></summary>
										<pre class="rwgo-code-block"><code><?php echo esc_html( $dl ); ?></code></pre>
									</details>
								</div>
								<?php endforeach; ?>
							</div>
						</details>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
				<?php
			endforeach;
			?>
		</div>
	<?php endif; ?>
</section>
