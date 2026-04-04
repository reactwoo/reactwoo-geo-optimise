<?php
/**
 * Tools tab: GTM / GA4 / dataLayer snippets.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgo_experiments = isset( $rwgo_experiments ) && is_array( $rwgo_experiments ) ? $rwgo_experiments : array();
?>

<div class="rwgc-card rwgc-card--highlight">
	<h2><?php esc_html_e( 'Who this is for', 'reactwoo-geo-optimise' ); ?></h2>
	<p><?php esc_html_e( 'Use this tab when you are connecting Google Tag Manager, GA4, or a data layer to record goal events alongside Geo Optimise. You do not need it to run a basic page test.', 'reactwoo-geo-optimise' ); ?></p>
</div>

<div class="rwgc-card">
	<h2><?php esc_html_e( 'HTML stamps (automatic clicks)', 'reactwoo-geo-optimise' ); ?></h2>
	<p class="description"><?php esc_html_e( 'The front-end script binds elements that include data-rwgo-experiment-key, data-rwgo-goal-id, and data-rwgo-handler-id. Optional: data-rwgo-variant-id (otherwise the resolved variant from the server is used), data-rwgo-element-fingerprint. Under Settings → Strict binding, a non-empty fingerprint is required on stamped elements; automatic page-view goals are skipped in that mode. Matching goals are also sent to the REST API so they appear in Reports (see Developer → Browser REST endpoint).', 'reactwoo-geo-optimise' ); ?></p>
</div>

<div class="rwgc-card">
	<h2><?php esc_html_e( 'GA4 recommendation', 'reactwoo-geo-optimise' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Send one event name and put test-specific data in parameters so multiple tests never overwrite each other in analytics.', 'reactwoo-geo-optimise' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Suggested event name:', 'reactwoo-geo-optimise' ); ?> <code>rwgo_goal_fired</code></li>
		<li><?php esc_html_e( 'Suggested parameters: experiment and goal identifiers (see snippets below).', 'reactwoo-geo-optimise' ); ?></li>
	</ul>
</div>

<?php if ( empty( $rwgo_experiments ) ) : ?>
	<div class="rwgc-card">
		<p><?php esc_html_e( 'Create and publish a test first — snippets are generated for each goal and measurement point.', 'reactwoo-geo-optimise' ); ?></p>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwgo-create-test' ) ); ?>"><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></a></p>
	</div>
<?php else : ?>
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
		<div class="rwgc-card rwgc-card--full">
			<h2><?php echo esc_html( get_the_title( $exp_post ) ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Internal test reference (for GTM variables and debugging):', 'reactwoo-geo-optimise' ); ?>
				<code><?php echo esc_html( $key ); ?></code>
			</p>
			<?php if ( empty( $goals ) ) : ?>
				<p class="rwgo-empty-hint"><?php esc_html_e( 'No goals configured for this test.', 'reactwoo-geo-optimise' ); ?></p>
			<?php else : ?>
				<?php foreach ( $goals as $goal ) : ?>
					<?php
					if ( ! is_array( $goal ) ) {
						continue;
					}
					$gid      = isset( $goal['goal_id'] ) ? (string) $goal['goal_id'] : '';
					$handlers = isset( $goal['handlers'] ) && is_array( $goal['handlers'] ) ? $goal['handlers'] : array();
					?>
					<h3><?php echo esc_html( isset( $goal['label'] ) ? (string) $goal['label'] : __( 'Goal', 'reactwoo-geo-optimise' ) ); ?></h3>
					<p class="description"><?php esc_html_e( 'Goal ID (use in tags):', 'reactwoo-geo-optimise' ); ?> <code><?php echo esc_html( $gid ); ?></code></p>
					<?php foreach ( $handlers as $handler ) : ?>
						<?php
						if ( ! is_array( $handler ) ) {
							continue;
						}
						$hid    = isset( $handler['handler_id'] ) ? (string) $handler['handler_id'] : '';
						$detail = wp_json_encode(
							array(
								'experiment_key'   => $key,
								'experiment_id'    => (int) $exp_post->ID,
								'goal_id'          => $gid,
								'handler_id'       => $hid,
								'variant_id'       => 'var_b',
								'page_context_id'  => $src,
							),
							JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
						);
						$js     = 'window.dispatchEvent(new CustomEvent("rwgo:goal", { detail: ' . $detail . ' }));';
						$dl_obj = array_merge(
							array( 'event' => 'rwgo_goal_fired' ),
							array(
								'rwgo_experiment_key'   => $key,
								'rwgo_experiment_id'    => (int) $exp_post->ID,
								'rwgo_goal_id'          => $gid,
								'rwgo_handler_id'       => $hid,
								'rwgo_variant_id'       => 'var_b',
								'rwgo_page_context_id'  => $src,
							)
						);
						$dl     = "window.dataLayer = window.dataLayer || [];\nwindow.dataLayer.push(" . wp_json_encode( $dl_obj, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . ');';
						?>
						<h4><?php echo esc_html( isset( $handler['label'] ) ? (string) $handler['label'] : __( 'Measurement point', 'reactwoo-geo-optimise' ) ); ?></h4>
						<p class="description"><?php esc_html_e( 'Handler ID:', 'reactwoo-geo-optimise' ); ?> <code><?php echo esc_html( $hid ); ?></code></p>
						<p class="description"><?php esc_html_e( 'Browser CustomEvent (listen in GTM or your app):', 'reactwoo-geo-optimise' ); ?></p>
						<pre class="rwgo-code-block"><code><?php echo esc_html( $js ); ?></code></pre>
						<p class="description"><?php esc_html_e( 'Google dataLayer push:', 'reactwoo-geo-optimise' ); ?></p>
						<pre class="rwgo-code-block"><code><?php echo esc_html( $dl ); ?></code></pre>
					<?php endforeach; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php endif; ?>
