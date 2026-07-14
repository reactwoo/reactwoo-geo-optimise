<?php
/**
 * AI Connector diagnostics panel (Optimise → Settings).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RWGO_AI_Connector_Diagnostics', false ) ) {
	return;
}

$rows  = RWGO_AI_Connector_Diagnostics::status_rows();
$flash = RWGO_AI_Connector_Diagnostics::consume_flash();
$engine = isset( $rows['workflow_engine'] ) ? (string) $rows['workflow_engine'] : 'automatic';
$allowed = class_exists( 'RWGA_Engine', false )
	? RWGA_Engine::allowed_modes()
	: array( 'automatic', 'wordpress_ai', 'managed', 'local', 'remote', 'remote_fallback' );
if ( ! in_array( $engine, $allowed, true ) ) {
	$engine = 'automatic';
}

$badge_class = static function ( $state ) {
	$state = sanitize_key( (string) $state );
	if ( in_array( $state, array( 'available', 'connected', 'ok' ), true ) ) {
		return 'rwgo-tracking-badge--ok';
	}
	if ( in_array( $state, array( 'requires_pro', 'action', 'not_connected', 'not_installed' ), true ) ) {
		return 'rwgo-tracking-badge--action';
	}
	return 'rwgo-tracking-badge--action';
};
?>
<div class="rwgo-panel rwgo-ai-connector-diag" id="rwgo-ai-connector">
	<h2 class="rwgo-section__title"><?php esc_html_e( 'AI Connector', 'reactwoo-geo-optimise' ); ?></h2>
	<p class="rwgo-section__lead"><?php esc_html_e( 'Test local deterministic review, remote API authentication, and Pro-gated remote workflows separately. These checks do not redesign AI Review — they only diagnose the connector.', 'reactwoo-geo-optimise' ); ?></p>

	<?php if ( is_array( $flash ) ) : ?>
		<div class="notice notice-<?php echo ! empty( $flash['ok'] ) ? 'success' : ( ! empty( $flash['tier'] ) ? 'warning' : 'error' ); ?> inline">
			<p>
				<?php if ( ! empty( $flash['title'] ) ) : ?>
					<strong><?php echo esc_html( (string) $flash['title'] ); ?></strong>
					<br />
				<?php endif; ?>
				<?php echo esc_html( isset( $flash['message'] ) ? (string) $flash['message'] : '' ); ?>
			</p>
			<?php if ( ! empty( $flash['detail'] ) ) : ?>
				<p class="description"><?php echo esc_html( (string) $flash['detail'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $flash['meta'] ) && is_array( $flash['meta'] ) ) : ?>
				<ul class="ul-disc" style="margin-left:1.4em;">
					<?php foreach ( $flash['meta'] as $mk => $mv ) : ?>
						<li><code><?php echo esc_html( (string) $mk ); ?></code>: <?php echo esc_html( is_scalar( $mv ) ? (string) $mv : wp_json_encode( $mv ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<table class="widefat striped rwgo-table-comfortable" style="max-width:720px;margin-bottom:16px;">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Local deterministic engine', 'reactwoo-geo-optimise' ); ?></th>
				<td><span class="rwgo-tracking-badge <?php echo esc_attr( $badge_class( $rows['local']['state'] ) ); ?>"><?php echo esc_html( $rows['local']['label'] ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Remote API', 'reactwoo-geo-optimise' ); ?></th>
				<td><span class="rwgo-tracking-badge <?php echo esc_attr( $badge_class( $rows['remote_api']['state'] ) ); ?>"><?php echo esc_html( $rows['remote_api']['label'] ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Remote workflow entitlement', 'reactwoo-geo-optimise' ); ?></th>
				<td><span class="rwgo-tracking-badge <?php echo esc_attr( $badge_class( $rows['entitlement']['state'] ) ); ?>"><?php echo esc_html( $rows['entitlement']['label'] ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Product slug', 'reactwoo-geo-optimise' ); ?></th>
				<td><code><?php echo esc_html( $rows['product_slug']['label'] ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'API base', 'reactwoo-geo-optimise' ); ?></th>
				<td><code><?php echo esc_html( (string) $rows['api_base'] ); ?></code></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Workflow route', 'reactwoo-geo-optimise' ); ?></th>
				<td>
					<code><?php echo esc_html( (string) $rows['workflow_path'] ); ?></code>
					<span class="description">
						<?php
						echo esc_html(
							'geo-optimise' === $rows['route_alias']
								? __( '(new /geo-optimise alias)', 'reactwoo-geo-optimise' )
								: __( '(legacy /geo-ai)', 'reactwoo-geo-optimise' )
						);
						?>
					</span>
				</td>
			</tr>
		</tbody>
	</table>

	<?php if ( class_exists( 'RWGA_Settings', false ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px;margin-bottom:20px;">
			<input type="hidden" name="action" value="rwgo_ai_diag_save_engine" />
			<?php wp_nonce_field( 'rwgo_ai_diag_save_engine' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rwgo_ai_workflow_engine"><?php esc_html_e( 'Execution mode', 'reactwoo-geo-optimise' ); ?></label></th>
					<td>
						<select id="rwgo_ai_workflow_engine" name="workflow_engine">
							<option value="automatic" <?php selected( $engine, 'automatic' ); ?>><?php esc_html_e( 'Automatic — WordPress AI → managed → local', 'reactwoo-geo-optimise' ); ?></option>
							<option value="wordpress_ai" <?php selected( $engine, 'wordpress_ai' ); ?>><?php esc_html_e( 'WordPress AI (BYOK)', 'reactwoo-geo-optimise' ); ?></option>
							<option value="managed" <?php selected( in_array( $engine, array( 'managed', 'remote' ), true ) ); ?>><?php esc_html_e( 'ReactWoo managed AI (remote)', 'reactwoo-geo-optimise' ); ?></option>
							<option value="local" <?php selected( $engine, 'local' ); ?>><?php esc_html_e( 'Local deterministic', 'reactwoo-geo-optimise' ); ?></option>
							<option value="remote_fallback" <?php selected( $engine, 'remote_fallback' ); ?>><?php esc_html_e( 'Remote with local fallback', 'reactwoo-geo-optimise' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Controls AI Review generation. Remote workflows require Pro tier; local does not call reactwoo-api.', 'reactwoo-geo-optimise' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save execution mode', 'reactwoo-geo-optimise' ), 'secondary', 'submit', false ); ?>
		</form>
	<?php endif; ?>

	<div class="rwgo-cta-row" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwgo_ai_diag_local" />
			<?php wp_nonce_field( 'rwgo_ai_diag_local' ); ?>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Run local test', 'reactwoo-geo-optimise' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwgo_ai_diag_remote_api" />
			<?php wp_nonce_field( 'rwgo_ai_diag_remote_api' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Check remote API', 'reactwoo-geo-optimise' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwgo_ai_diag_remote_workflow" />
			<?php wp_nonce_field( 'rwgo_ai_diag_remote_workflow' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Run remote workflow test', 'reactwoo-geo-optimise' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="rwgo_ai_diag_remote_fallback" />
			<?php wp_nonce_field( 'rwgo_ai_diag_remote_fallback' ); ?>
			<button type="submit" class="button"><?php esc_html_e( 'Test remote fallback', 'reactwoo-geo-optimise' ); ?></button>
		</form>
	</div>
</div>
