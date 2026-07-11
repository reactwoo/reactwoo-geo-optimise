<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$option_key = RWGA_Settings::OPTION_KEY;
$settings   = RWGA_Settings::get_settings();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwga-license';

$summary = class_exists( 'RWGA_Connection', false ) ? RWGA_Connection::get_summary() : array();
$cache   = class_exists( 'RWGA_Usage', false ) ? RWGA_Usage::get_cache() : null;
$import_sources = class_exists( 'RWGA_Settings', false ) ? RWGA_Settings::get_manual_import_sources() : array();

$lic_ok = ! empty( $summary['license_configured'] );
$last_refresh = ( null !== $cache && ! empty( $cache['refreshed_at_gmt'] ) ) ? (string) $cache['refreshed_at_gmt'] : __( 'Never', 'reactwoo-geo-ai' );

$refresh_url = wp_nonce_url( admin_url( 'admin.php?page=rwga-license&rwga_action=ai_usage' ), 'rwga_dash_ai_usage' );
$snapshot_sync_url = wp_nonce_url( admin_url( 'admin.php?page=rwga-license&rwga_action=snapshot_sync' ), 'rwga_dash_snapshot_sync' );
$snapshot_force_url = wp_nonce_url( admin_url( 'admin.php?page=rwga-license&rwga_action=snapshot_sync&rwga_force=1' ), 'rwga_dash_snapshot_sync' );
$intel_page   = class_exists( 'RWGA_Site_Intelligence_Sync', false )
	? RWGA_Site_Intelligence_Sync::get_license_page_status()
	: array();
$intel_status = isset( $intel_page['status'] ) && is_array( $intel_page['status'] ) ? $intel_page['status'] : array();
$intel_label  = isset( $intel_page['label'] ) ? (string) $intel_page['label'] : __( 'Not available', 'reactwoo-geo-ai' );
$intel_hint   = isset( $intel_page['hint'] ) ? (string) $intel_page['hint'] : '';
$intel_error  = isset( $intel_page['error'] ) ? (string) $intel_page['error'] : '';
$intel_can_sync = ! empty( $intel_page['live_allowed'] );
$geocore_ready  = ! empty( $intel_page['geocore_ready'] );
$connect_hint = __( 'Connect your ReactWoo plan here. Usage and tokens are tied to this key on this site.', 'reactwoo-geo-ai' );

$site_host    = class_exists( 'RWGA_Platform_Client', false ) ? RWGA_Platform_Client::get_site_domain() : '';
$expect_slug  = class_exists( 'RWGA_Platform_Client', false ) ? RWGA_Platform_Client::PRODUCT_SLUG : 'reactwoo-geo-ai';
$live_claims  = ( $lic_ok && class_exists( 'RWGA_License_Introspection', false ) ) ? RWGA_License_Introspection::get_bearer_claims() : null;
$jwt_domain   = is_array( $cache ) && isset( $cache['jwt_domain'] ) ? (string) $cache['jwt_domain'] : '';
$jwt_prod     = is_array( $cache ) && isset( $cache['jwt_product_slug'] ) ? (string) $cache['jwt_product_slug'] : '';
$jwt_cat      = is_array( $cache ) && isset( $cache['jwt_catalog_slug'] ) ? (string) $cache['jwt_catalog_slug'] : '';
$jwt_pkg      = is_array( $cache ) && isset( $cache['jwt_package_type'] ) ? (string) $cache['jwt_package_type'] : '';
$jwt_plan     = is_array( $cache ) && isset( $cache['jwt_plan_code'] ) ? (string) $cache['jwt_plan_code'] : '';
$jwt_tier_c   = is_array( $cache ) && isset( $cache['jwt_tier'] ) ? (string) $cache['jwt_tier'] : '';
$jwt_label    = is_array( $cache ) && isset( $cache['jwt_package_label'] ) ? (string) $cache['jwt_package_label'] : '';
$api_tier_raw = is_array( $cache ) && isset( $cache['api_license_tier_raw'] ) ? (string) $cache['api_license_tier_raw'] : '';
if ( is_array( $live_claims ) ) {
	if ( '' === $jwt_domain && isset( $live_claims['domain'] ) ) {
		$jwt_domain = sanitize_text_field( (string) $live_claims['domain'] );
	}
	if ( '' === $jwt_prod && isset( $live_claims['product_slug'] ) ) {
		$jwt_prod = sanitize_key( (string) $live_claims['product_slug'] );
	}
	if ( '' === $jwt_cat && isset( $live_claims['catalog_slug'] ) ) {
		$jwt_cat = sanitize_key( (string) $live_claims['catalog_slug'] );
	}
	if ( '' === $jwt_pkg && isset( $live_claims['packageType'] ) ) {
		$jwt_pkg = sanitize_text_field( (string) $live_claims['packageType'] );
	}
	if ( '' === $jwt_plan && isset( $live_claims['plan_code'] ) ) {
		$jwt_plan = sanitize_text_field( (string) $live_claims['plan_code'] );
	}
	if ( '' === $jwt_tier_c && class_exists( 'RWGA_License_Introspection', false ) ) {
		$jwt_tier_c = RWGA_License_Introspection::tier_from_claims( $live_claims );
	}
	if ( '' === $jwt_label && class_exists( 'RWGA_License_Introspection', false ) ) {
		$jwt_label = RWGA_License_Introspection::format_package_summary( $live_claims );
	}
}
$domain_ok = ( '' !== $jwt_domain && '' !== $site_host && class_exists( 'RWGA_License_Introspection', false ) )
	? RWGA_License_Introspection::domain_matches_token( $site_host, $jwt_domain )
	: null;
$slug_ok = null;
if ( '' !== $jwt_prod || '' !== $jwt_cat ) {
	$slug_ok = ( $expect_slug === $jwt_prod || $expect_slug === $jwt_cat );
}
$tier_mismatch = false;
if ( '' !== $jwt_tier_c && is_array( $cache ) && isset( $cache['license_tier'] ) ) {
	$tier_mismatch = ( sanitize_key( (string) $cache['license_tier'] ) !== $jwt_tier_c );
}

if ( class_exists( 'RWGA_Updates_Diagnostics', false ) ) {
	RWGA_Updates_Diagnostics::maybe_clear_stale_no_bearer_record();
}
$upd_last = class_exists( 'RWGA_Updates_Diagnostics', false ) ? RWGA_Updates_Diagnostics::get_last() : array();
$upd_ts   = ! empty( $upd_last['ts'] ) ? (int) $upd_last['ts'] : 0;
$force_updates_url = is_admin() ? admin_url( 'update-core.php?force-check=1' ) : '';

?>
<div class="wrap rwgc-wrap rwgc-suite rwga-wrap rwga-wrap--license">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Settings', 'reactwoo-geo-ai' ),
			__( 'License, usage, and connection. Advanced API overrides stay under Advanced.', 'reactwoo-geo-ai' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo AI — Settings', 'reactwoo-geo-ai' ); ?></h1>
	<?php endif; ?>

	<?php RWGA_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php settings_errors( 'rwga_geo_ai' ); ?>
	<?php RWGA_Admin::render_usage_refresh_notices(); ?>

	<?php if ( ! empty( $_GET['rwga_disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'License key removed from this site.', 'reactwoo-geo-ai' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwga_imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'License key imported into Geo AI.', 'reactwoo-geo-ai' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwga_import_err'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['rwga_import_err'] ) ) ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-grid" style="align-items: flex-start;">
		<div class="rwgc-card" style="max-width: 560px;">
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_section_header(
					__( 'License & connection', 'reactwoo-geo-ai' ),
					$connect_hint
				);
			} else {
				echo '<h2>' . esc_html__( 'Product license', 'reactwoo-geo-ai' ) . '</h2>';
			}
			?>

			<p style="margin: 12px 0;">
				<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
					<?php
					RWGC_Admin_UI::render_badge(
						$lic_ok ? __( 'Connected', 'reactwoo-geo-ai' ) : __( 'Not configured', 'reactwoo-geo-ai' ),
						$lic_ok ? 'success' : 'warning'
					);
					?>
				<?php else : ?>
					<strong><?php echo $lic_ok ? esc_html__( 'Key on file', 'reactwoo-geo-ai' ) : esc_html__( 'Not configured', 'reactwoo-geo-ai' ); ?></strong>
				<?php endif; ?>
			</p>

			<dl class="rwga-license-dl">
				<dt><?php esc_html_e( 'Site intelligence sync', 'reactwoo-geo-ai' ); ?></dt>
				<dd><?php echo esc_html( $intel_label ); ?></dd>
				<?php if ( ! empty( $intel_status['last_synced_at_gmt'] ) ) : ?>
					<dt><?php esc_html_e( 'Last intelligence sync (GMT)', 'reactwoo-geo-ai' ); ?></dt>
					<dd><?php echo esc_html( (string) $intel_status['last_synced_at_gmt'] ); ?></dd>
				<?php endif; ?>
				<?php if ( '' !== $intel_error ) : ?>
					<dt><?php esc_html_e( 'Sync status detail', 'reactwoo-geo-ai' ); ?></dt>
					<dd><span class="description"><?php echo esc_html( $intel_error ); ?></span></dd>
				<?php endif; ?>
				<?php if ( '' !== $intel_hint ) : ?>
					<dt><?php esc_html_e( 'Sync note', 'reactwoo-geo-ai' ); ?></dt>
					<dd><span class="description"><?php echo esc_html( $intel_hint ); ?></span></dd>
				<?php endif; ?>
				<dt><?php esc_html_e( 'Last usage refresh', 'reactwoo-geo-ai' ); ?></dt>
				<dd><?php echo esc_html( $last_refresh ); ?></dd>
				<?php if ( $lic_ok && null === $cache && class_exists( 'RWGA_Usage', false ) ) : ?>
					<dt><?php esc_html_e( 'Plan', 'reactwoo-geo-ai' ); ?></dt>
					<dd><span class="description"><?php esc_html_e( 'Not loaded yet — use Refresh usage below after saving your key.', 'reactwoo-geo-ai' ); ?></span></dd>
					<dt><?php esc_html_e( 'Usage (this period)', 'reactwoo-geo-ai' ); ?></dt>
					<dd><span class="description"><?php esc_html_e( '—', 'reactwoo-geo-ai' ); ?></span></dd>
				<?php elseif ( null !== $cache && class_exists( 'RWGA_Usage', false ) ) : ?>
					<?php
					$plan_line = RWGA_Usage::format_plan_label( $cache );
					if ( '' !== $plan_line ) :
						?>
					<dt><?php esc_html_e( 'Plan', 'reactwoo-geo-ai' ); ?></dt>
					<dd><?php echo esc_html( $plan_line ); ?></dd>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( null !== $cache && isset( $cache['used'], $cache['limit'] ) ) : ?>
					<dt><?php esc_html_e( 'Usage (this period)', 'reactwoo-geo-ai' ); ?></dt>
					<dd><?php echo esc_html( (string) (int) $cache['used'] . ' / ' . (int) $cache['limit'] ); ?></dd>
				<?php endif; ?>
			</dl>
			<?php if ( $lic_ok && ( null !== $cache || is_array( $live_claims ) ) ) : ?>
				<div class="rwga-license-introspection" style="margin-top: 16px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
					<p class="description" style="margin-bottom: 8px;"><strong><?php esc_html_e( 'License server check (token + last API response)', 'reactwoo-geo-ai' ); ?></strong></p>
					<p class="description" style="margin-top: 0;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: product slug Geo AI sends at login, 2: site hostname used for license binding */
								__( 'Login sends product/catalog slug %1$s with domain %2$s. The API combines your JWT claims with GET /api/v5/ai/assistant/usage (licenseTier + usage limits).', 'reactwoo-geo-ai' ),
								$expect_slug,
								$site_host ? $site_host : __( '(unknown)', 'reactwoo-geo-ai' )
							)
						);
						?>
					</p>
					<dl class="rwga-license-dl" style="margin-top: 10px;">
						<dt><?php esc_html_e( 'This site (home URL host)', 'reactwoo-geo-ai' ); ?></dt>
						<dd><code><?php echo esc_html( $site_host ? $site_host : '—' ); ?></code></dd>
						<dt><?php esc_html_e( 'Domain in license token', 'reactwoo-geo-ai' ); ?></dt>
						<dd><code><?php echo esc_html( $jwt_domain !== '' ? $jwt_domain : '—' ); ?></code></dd>
						<?php if ( null !== $domain_ok ) : ?>
							<dt><?php esc_html_e( 'Domain match', 'reactwoo-geo-ai' ); ?></dt>
							<dd>
								<?php if ( $domain_ok ) : ?>
									<?php esc_html_e( 'Yes', 'reactwoo-geo-ai' ); ?>
								<?php else : ?>
									<span class="notice-warning" style="display:inline;padding:2px 6px;"><?php esc_html_e( 'No — activate this site’s host for the key or use the correct key.', 'reactwoo-geo-ai' ); ?></span>
								<?php endif; ?>
							</dd>
						<?php endif; ?>
						<dt><?php esc_html_e( 'Product slug (token)', 'reactwoo-geo-ai' ); ?></dt>
						<dd><code><?php echo esc_html( $jwt_prod !== '' ? $jwt_prod : '—' ); ?></code></dd>
						<dt><?php esc_html_e( 'Catalog slug (token)', 'reactwoo-geo-ai' ); ?></dt>
						<dd><code><?php echo esc_html( $jwt_cat !== '' ? $jwt_cat : '—' ); ?></code></dd>
						<?php if ( null !== $slug_ok ) : ?>
							<dt><?php esc_html_e( 'Geo AI product match', 'reactwoo-geo-ai' ); ?></dt>
							<dd>
								<?php if ( $slug_ok ) : ?>
									<?php esc_html_e( 'Yes', 'reactwoo-geo-ai' ); ?>
								<?php else : ?>
									<span class="notice-warning" style="display:inline;padding:2px 6px;"><?php esc_html_e( 'No — token is for another product; usage may not reflect this plugin’s plan.', 'reactwoo-geo-ai' ); ?></span>
								<?php endif; ?>
							</dd>
						<?php endif; ?>
						<dt><?php esc_html_e( 'Package / plan (token)', 'reactwoo-geo-ai' ); ?></dt>
						<dd><?php echo esc_html( $jwt_label !== '' ? $jwt_label : ( $jwt_pkg !== '' || $jwt_plan !== '' ? trim( $jwt_pkg . ( $jwt_pkg && $jwt_plan ? ' — ' : '' ) . $jwt_plan ) : '—' ) ); ?></dd>
						<dt><?php esc_html_e( 'Tier in token (if present)', 'reactwoo-geo-ai' ); ?></dt>
						<dd><code><?php echo esc_html( $jwt_tier_c !== '' ? $jwt_tier_c : '—' ); ?></code></dd>
						<dt><?php esc_html_e( 'Tier from usage API (raw)', 'reactwoo-geo-ai' ); ?></dt>
						<dd><code><?php echo esc_html( $api_tier_raw !== '' ? $api_tier_raw : '—' ); ?></code></dd>
						<?php if ( $tier_mismatch ) : ?>
							<dt><?php esc_html_e( 'Tier note', 'reactwoo-geo-ai' ); ?></dt>
							<dd><span class="notice-warning" style="display:inline;padding:2px 6px;"><?php esc_html_e( 'Token tier and usage API tier differ — the UI uses the usage response + limits after refresh.', 'reactwoo-geo-ai' ); ?></span></dd>
						<?php endif; ?>
					</dl>
					<p class="description" style="margin-bottom: 0;"><?php esc_html_e( 'Refresh usage after saving your key to capture API + token details. If package text is wrong, verify the license in ReactWoo is for Geo AI and this domain.', 'reactwoo-geo-ai' ); ?></p>
					<?php if ( $lic_ok && 'free' === sanitize_key( (string) $jwt_tier_c ) && '' === trim( (string) ( $jwt_label . $jwt_pkg . $jwt_plan ) ) ) : ?>
						<p class="description" style="margin-top: 8px;"><?php esc_html_e( 'If you have a paid Geo AI plan but the token still shows tier “free” and no package line, the license server or API is not attaching paid package claims to this JWT — check the Geo AI package in ReactWoo License (assistant tier / monthly_ai_tokens) and that api.reactwoo.com is using the live license JWT path, not a stub.', 'reactwoo-geo-ai' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $lic_ok ) : ?>
				<div class="rwga-license-introspection" style="margin-top: 16px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
					<p class="description" style="margin-bottom: 8px;"><strong><?php esc_html_e( 'Plugin updates (ZIP via api.reactwoo.com)', 'reactwoo-geo-ai' ); ?></strong></p>
					<p class="description" style="margin-top: 0;">
						<?php esc_html_e( 'WordPress checks for updates when you open Plugins or Dashboard → Updates. If a new build does not appear, use Check for updates there, or open the link below to force a refresh.', 'reactwoo-geo-ai' ); ?>
					</p>
					<?php if ( $force_updates_url ) : ?>
						<p style="margin: 8px 0 0;">
							<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $force_updates_url ); ?>"><?php esc_html_e( 'Force check for updates (WordPress)', 'reactwoo-geo-ai' ); ?></a>
						</p>
					<?php endif; ?>
					<?php if ( $upd_ts > 0 && ! empty( $upd_last['summary'] ) ) : ?>
						<dl class="rwga-license-dl" style="margin-top: 12px;">
							<dt><?php esc_html_e( 'Last /api/v5/updates/check (this site)', 'reactwoo-geo-ai' ); ?></dt>
							<dd><code><?php echo esc_html( gmdate( 'c', $upd_ts ) ); ?></code></dd>
							<dt><?php esc_html_e( 'HTTP', 'reactwoo-geo-ai' ); ?></dt>
							<dd><code><?php
								$upd_http_num = isset( $upd_last['http'] ) ? (int) $upd_last['http'] : 0;
								if ( 0 === $upd_http_num && ! empty( $upd_last['summary'] ) && false !== strpos( (string) $upd_last['summary'], 'No license JWT' ) ) {
									esc_html_e( '0 — request not sent (no bearer)', 'reactwoo-geo-ai' );
								} else {
									echo (string) $upd_http_num;
								}
								?></code></dd>
							<?php if ( ! empty( $upd_last['api_version'] ) ) : ?>
								<dt><?php esc_html_e( 'Catalog version offered', 'reactwoo-geo-ai' ); ?></dt>
								<dd><code><?php echo esc_html( (string) $upd_last['api_version'] ); ?></code></dd>
							<?php endif; ?>
							<dt><?php esc_html_e( 'Result', 'reactwoo-geo-ai' ); ?></dt>
							<dd><?php echo esc_html( (string) $upd_last['summary'] ); ?></dd>
							<?php if ( ! empty( $upd_last['body_snip'] ) ) : ?>
								<dt><?php esc_html_e( 'Response (truncated)', 'reactwoo-geo-ai' ); ?></dt>
								<dd><pre style="white-space:pre-wrap;max-height:120px;overflow:auto;font-size:11px;"><?php echo esc_html( (string) $upd_last['body_snip'] ); ?></pre></dd>
							<?php endif; ?>
						</dl>
					<?php else : ?>
						<p class="description" style="margin-top: 10px; margin-bottom: 0;"><?php esc_html_e( 'No update check has been recorded yet — visit Plugins or Dashboard → Updates once, then reload this page.', 'reactwoo-geo-ai' ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'After upgrading your ReactWoo plan, save the license here and refresh usage so limits stay accurate.', 'reactwoo-geo-ai' ); ?></p>
			<p class="description" style="margin-top:8px;">
				<strong><?php esc_html_e( 'Disconnect vs Save:', 'reactwoo-geo-ai' ); ?></strong>
				<?php esc_html_e( 'Use Disconnect to remove the key from this site. Saving the form with an empty license field keeps your current key — it does not disconnect.', 'reactwoo-geo-ai' ); ?>
			</p>

			<form id="rwga-license-save-form" method="post" action="options.php" class="rwga-license-form">
				<?php settings_fields( 'rwga_license_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[rwga_form_scope]" value="license" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rwga_reactwoo_license_key"><?php esc_html_e( 'License key', 'reactwoo-geo-ai' ); ?></label></th>
						<td>
							<input type="password" id="rwga_reactwoo_license_key" name="<?php echo esc_attr( $option_key ); ?>[reactwoo_license_key]" value="" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr__( 'Enter new key or leave blank to keep current', 'reactwoo-geo-ai' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to keep the saved key.', 'reactwoo-geo-ai' ); ?></p>
						</td>
					</tr>
				</table>
			</form>
			<div class="rwgc-actions rwga-license-primary-actions">
				<button type="submit" form="rwga-license-save-form" class="rwgc-btn rwgc-btn--primary"><?php esc_html_e( 'Save license', 'reactwoo-geo-ai' ); ?></button>
				<?php if ( $lic_ok ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwga-license-disconnect-form" id="rwga-disconnect-form" onsubmit="if(!window.confirm(<?php echo esc_js( __( 'Remove the license key from this site?', 'reactwoo-geo-ai' ) ); ?>)){return false;}var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;}return true;">
						<?php wp_nonce_field( 'rwga_clear_license' ); ?>
						<input type="hidden" name="action" value="rwga_clear_geo_ai_license" />
						<button type="submit" class="rwgc-btn rwgc-btn--danger"><?php esc_html_e( 'Disconnect', 'reactwoo-geo-ai' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<div class="rwgc-card" style="max-width: 560px;">
			<?php
			if ( class_exists( 'RWGC_Admin_UI', false ) ) {
				RWGC_Admin_UI::render_section_header(
					__( 'Import & usage', 'reactwoo-geo-ai' ),
					__( 'Copy a key from another ReactWoo plugin on this site, or refresh plan limits from the API.', 'reactwoo-geo-ai' )
				);
			} else {
				echo '<h2>' . esc_html__( 'Import & usage', 'reactwoo-geo-ai' ) . '</h2>';
			}
			?>
			<?php if ( ! empty( $import_sources ) ) : ?>
				<p class="rwgc-actions rwga-license-actions">
					<?php foreach ( $import_sources as $source => $label ) : ?>
						<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwga-license&rwga_action=import_license&source=' . rawurlencode( $source ) ), 'rwga_import_license' ) ); ?>"><?php echo esc_html( sprintf( __( 'Import from %s', 'reactwoo-geo-ai' ), $label ) ); ?></a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>

			<p class="rwgc-actions rwga-license-actions">
				<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $refresh_url ); ?>"><?php esc_html_e( 'Refresh usage', 'reactwoo-geo-ai' ); ?></a>
				<?php if ( $lic_ok && $intel_can_sync ) : ?>
					<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $snapshot_sync_url ); ?>"><?php esc_html_e( 'Sync site intelligence', 'reactwoo-geo-ai' ); ?></a>
					<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $snapshot_force_url ); ?>"><?php esc_html_e( 'Force sync', 'reactwoo-geo-ai' ); ?></a>
				<?php endif; ?>
			</p>
			<p class="description"><?php esc_html_e( 'Site intelligence uploads a compact Geo Core snapshot (rules, variants, relationships) — not page content. Preview it under Geo Core → Settings → AI Data Snapshot.', 'reactwoo-geo-ai' ); ?></p>
			<?php if ( $lic_ok && ! empty( $intel_status['last_synced_at_gmt'] ) ) : ?>
				<p class="rwgc-actions rwga-license-actions" style="margin-top:12px;">
					<a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=rwga-intelligence-wizard' ) ); ?>"><?php esc_html_e( 'Open site intelligence guide', 'reactwoo-geo-ai' ); ?></a>
				</p>
				<p class="description"><?php esc_html_e( 'Use the guided wizard to sync, run audits, and review suggestions in order.', 'reactwoo-geo-ai' ); ?></p>
			<?php endif; ?>
			<?php if ( $lic_ok && ! $geocore_ready ) : ?>
				<p class="description" style="color:#b45309;"><strong><?php esc_html_e( 'Geo Core update required:', 'reactwoo-geo-ai' ); ?></strong> <?php esc_html_e( 'Install or update ReactWoo Geo Core so the AI Data Snapshot builder is available, then use Sync site intelligence.', 'reactwoo-geo-ai' ); ?></p>
			<?php elseif ( $lic_ok && $geocore_ready && ! $intel_can_sync && '' === $intel_error ) : ?>
				<p class="description"><?php esc_html_e( 'Refresh usage does not upload site intelligence. Use Sync site intelligence when the status shows Ready to sync.', 'reactwoo-geo-ai' ); ?></p>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Subscription and billing are managed in your ReactWoo account.', 'reactwoo-geo-ai' ); ?></p>
		</div>
	</div>
</div>
