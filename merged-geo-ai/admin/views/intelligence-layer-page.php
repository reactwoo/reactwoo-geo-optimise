<?php
/**
 * Geo AI Intelligence — bundle sync, local interpreter test, pattern browser.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_nav_current     = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-intelligence-layer';
$rwga_intel_status    = isset( $rwga_intel_status ) && is_array( $rwga_intel_status ) ? $rwga_intel_status : array();
$rwga_test_result     = isset( $rwga_test_result ) && is_array( $rwga_test_result ) ? $rwga_test_result : null;
$rwga_test_phrase     = isset( $rwga_test_phrase ) ? (string) $rwga_test_phrase : '';
$rwga_patterns        = isset( $rwga_patterns ) && is_array( $rwga_patterns ) ? $rwga_patterns : array();
$rwga_learning_log    = isset( $rwga_learning_log ) && is_array( $rwga_learning_log ) ? $rwga_learning_log : array();
$rwga_bundle_raw      = isset( $rwga_bundle_raw ) && is_array( $rwga_bundle_raw ) ? $rwga_bundle_raw : array();
$rwga_learning_enabled = ! empty( $rwga_learning_enabled );
?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--intelligence-layer">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Geo AI Intelligence', 'reactwoo-geo-ai' ),
			__( 'Local command interpretation using the shared ReactWoo intelligence bundle.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo AI Intelligence', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgc-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem;margin:1rem 0;">
		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Bundle status', 'reactwoo-geo-ai' ); ?></h2>
			<ul>
				<li><strong><?php esc_html_e( 'Version', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) ( $rwga_intel_status['version'] ?? '—' ) ); ?></li>
				<li><strong><?php esc_html_e( 'Hash', 'reactwoo-geo-ai' ); ?>:</strong> <code><?php echo esc_html( substr( (string) ( $rwga_intel_status['hash'] ?? '' ), 0, 16 ) ); ?>…</code></li>
				<li><strong><?php esc_html_e( 'Last sync', 'reactwoo-geo-ai' ); ?>:</strong>
					<?php
					$last = (int) ( $rwga_intel_status['last_sync'] ?? 0 );
					echo $last > 0 ? esc_html( wp_date( 'Y-m-d H:i', $last ) ) : esc_html__( 'Never', 'reactwoo-geo-ai' );
					?>
				</li>
				<li><strong><?php esc_html_e( 'Source', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) ( $rwga_intel_status['source'] ?? 'unknown' ) ); ?></li>
				<?php if ( ! empty( $rwga_intel_status['last_error'] ) ) : ?>
					<li><strong><?php esc_html_e( 'Last error', 'reactwoo-geo-ai' ); ?>:</strong> <?php echo esc_html( (string) $rwga_intel_status['last_error'] ); ?></li>
				<?php endif; ?>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1rem;">
				<?php wp_nonce_field( 'rwga_intelligence_sync' ); ?>
				<input type="hidden" name="action" value="rwga_intelligence_sync" />
				<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Refresh bundle', 'reactwoo-geo-ai' ); ?></button>
			</form>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Learning events', 'reactwoo-geo-ai' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'rwga_intelligence_learning_toggle' ); ?>
				<input type="hidden" name="action" value="rwga_intelligence_learning_toggle" />
				<label>
					<input type="checkbox" name="enabled" value="1" <?php checked( $rwga_learning_enabled ); ?> />
					<?php esc_html_e( 'Send anonymised learning events to ReactWoo API', 'reactwoo-geo-ai' ); ?>
				</label>
				<p><button type="submit" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Save', 'reactwoo-geo-ai' ); ?></button></p>
			</form>
			<?php if ( empty( $rwga_learning_log ) ) : ?>
				<p class="description"><?php esc_html_e( 'No local learning events yet.', 'reactwoo-geo-ai' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="margin-top:0.5rem;">
					<thead><tr><th><?php esc_html_e( 'Phrase', 'reactwoo-geo-ai' ); ?></th><th><?php esc_html_e( 'Outcome', 'reactwoo-geo-ai' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( array_reverse( $rwga_learning_log ), 0, 10 ) as $row ) : ?>
						<?php if ( ! is_array( $row ) ) { continue; } ?>
						<tr>
							<td><?php echo esc_html( substr( (string) ( $row['raw_phrase'] ?? '' ), 0, 60 ) ); ?></td>
							<td><?php echo esc_html( (string) ( $row['outcome'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="rwgc-card">
			<h2><?php esc_html_e( 'Interpretation memory', 'reactwoo-geo-ai' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Reuse accepted interpretations locally and from ReactWoo shared memory before calling AI fallback.', 'reactwoo-geo-ai' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'rwga_intelligence_memory_settings' ); ?>
				<input type="hidden" name="action" value="rwga_intelligence_memory_settings" />
				<label>
					<input type="checkbox" name="local_enabled" value="1" <?php checked( ! empty( $rwga_memory_local_enabled ) ); ?> />
					<?php esc_html_e( 'Enable local interpretation memory', 'reactwoo-geo-ai' ); ?>
				</label><br />
				<label>
					<input type="checkbox" name="shared_enabled" value="1" <?php checked( ! empty( $rwga_memory_shared_enabled ) ); ?> />
					<?php esc_html_e( 'Enable shared ReactWoo interpretation memory', 'reactwoo-geo-ai' ); ?>
				</label><br />
				<label>
					<input type="checkbox" name="clear_local_memory" value="1" />
					<?php esc_html_e( 'Clear local memory cache on save', 'reactwoo-geo-ai' ); ?>
				</label>
				<p><button type="submit" class="rwgc-btn rwgc-btn--secondary"><?php esc_html_e( 'Save memory settings', 'reactwoo-geo-ai' ); ?></button></p>
			</form>
			<p class="description">
				<?php
				$count = is_array( $rwga_memory_cache ?? null ) ? count( $rwga_memory_cache ) : 0;
				printf(
					/* translators: %d: cached memory count */
					esc_html__( 'Local cache entries: %d', 'reactwoo-geo-ai' ),
					(int) $count
				);
				?>
			</p>
		</div>
	</div>

	<div class="rwgc-card" style="margin:1rem 0;">
		<h2><?php esc_html_e( 'Local interpreter test', 'reactwoo-geo-ai' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'rwga_intelligence_interpret_test' ); ?>
			<input type="hidden" name="action" value="rwga_intelligence_interpret_test" />
			<p>
				<label for="rwga-test-phrase"><strong><?php esc_html_e( 'Phrase', 'reactwoo-geo-ai' ); ?></strong></label><br />
				<input type="text" id="rwga-test-phrase" name="phrase" class="large-text" value="<?php echo esc_attr( $rwga_test_phrase ); ?>" placeholder="<?php esc_attr_e( 'Only show this to Canada', 'reactwoo-geo-ai' ); ?>" />
			</p>
			<p>
				<label for="rwga-mock-target"><?php esc_html_e( 'Mock target type', 'reactwoo-geo-ai' ); ?></label>
				<select id="rwga-mock-target" name="mock_target_type">
					<option value="popup"><?php esc_html_e( 'Popup', 'reactwoo-geo-ai' ); ?></option>
					<option value="page"><?php esc_html_e( 'Page', 'reactwoo-geo-ai' ); ?></option>
					<option value="rule"><?php esc_html_e( 'Rule', 'reactwoo-geo-ai' ); ?></option>
					<option value="product"><?php esc_html_e( 'Product', 'reactwoo-geo-ai' ); ?></option>
				</select>
				<input type="number" name="mock_target_id" value="456" min="1" style="width:100px;" />
			</p>
			<button type="submit" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Interpret', 'reactwoo-geo-ai' ); ?></button>
		</form>

		<?php if ( is_array( $rwga_test_result ) ) : ?>
			<h3><?php esc_html_e( 'Result', 'reactwoo-geo-ai' ); ?></h3>
			<pre style="background:#f6f7f7;padding:1rem;overflow:auto;max-height:320px;"><?php echo esc_html( wp_json_encode( $rwga_test_result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
		<?php endif; ?>
	</div>

	<div class="rwgc-card" style="margin:1rem 0;">
		<h2><?php esc_html_e( 'Pattern browser', 'reactwoo-geo-ai' ); ?></h2>
		<?php if ( empty( $rwga_patterns ) ) : ?>
			<p class="description"><?php esc_html_e( 'No patterns loaded. Sync the intelligence bundle first.', 'reactwoo-geo-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Intent', 'reactwoo-geo-ai' ); ?></th>
						<th><?php esc_html_e( 'Pattern', 'reactwoo-geo-ai' ); ?></th>
						<th><?php esc_html_e( 'Action', 'reactwoo-geo-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( array_slice( $rwga_patterns, 0, 50 ) as $pat ) : ?>
					<?php if ( ! is_array( $pat ) ) { continue; } ?>
					<tr>
						<td><?php echo esc_html( (string) ( $pat['intent_key'] ?? '' ) ); ?></td>
						<td><code><?php echo esc_html( (string) ( $pat['pattern'] ?? '' ) ); ?></code></td>
						<td><?php echo esc_html( (string) ( $pat['action_key'] ?? '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<details class="rwgc-card" style="margin:1rem 0;">
		<summary><strong><?php esc_html_e( 'Debug: raw local bundle', 'reactwoo-geo-ai' ); ?></strong></summary>
		<pre style="background:#f6f7f7;padding:1rem;overflow:auto;max-height:400px;"><?php echo esc_html( wp_json_encode( $rwga_bundle_raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
	</details>
</div>
