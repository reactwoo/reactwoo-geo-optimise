<?php
/**
 * License screen — ReactWoo product key for Geo Optimise.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$option_key       = RWGO_Settings::OPTION_KEY;
$settings         = RWGO_Settings::get_settings();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-license';
$lic_ok           = ! empty( $settings['reactwoo_license_key'] );
$last_check       = get_option( 'rwgo_license_last_check', array() );
$last_ok          = is_array( $last_check ) && ! empty( $last_check['ok'] );
$last_time        = is_array( $last_check ) && ! empty( $last_check['time'] ) ? (string) $last_check['time'] : '';
$last_err         = is_array( $last_check ) && isset( $last_check['error'] ) ? (string) $last_check['error'] : '';

?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--license">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'License', 'reactwoo-geo-optimise' ),
			__( 'Activate your ReactWoo Geo Optimise plan. The key is stored on this site and used with Geo Core’s platform client when features require it.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Optimise — License', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( ! empty( $_GET['rwgo_disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible rwgo-notice"><p><?php esc_html_e( 'License key removed from this site.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgo_license_test'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php if ( '1' === (string) $_GET['rwgo_license_test'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible rwgo-notice"><p><?php esc_html_e( 'Connection OK — license validated with the ReactWoo API.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php elseif ( '0' === (string) $_GET['rwgo_license_test'] ) : ?>
			<div class="notice notice-error is-dismissible rwgo-notice"><p><?php esc_html_e( 'Could not validate the license. Check the key, API base, and network, then try again.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php elseif ( 'na' === (string) $_GET['rwgo_license_test'] ) : ?>
			<div class="notice notice-warning is-dismissible rwgo-notice"><p><?php esc_html_e( 'Geo Core platform client is not available — validation could not run.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="rwgc-grid" style="align-items: flex-start;">
		<div class="rwgc-card" style="max-width: 560px;">
			<h2><?php esc_html_e( 'Product license', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Your license is connected and stored on this site when a key is saved. Leave the field blank to keep the current key.', 'reactwoo-geo-optimise' ); ?></p>

			<div class="rwgo-license-status <?php echo $lic_ok ? 'is-ok' : ''; ?>">
				<dl>
					<dt><?php esc_html_e( 'Status', 'reactwoo-geo-optimise' ); ?></dt>
					<dd><?php echo $lic_ok ? esc_html__( 'License active — key on file', 'reactwoo-geo-optimise' ) : esc_html__( 'No active license saved', 'reactwoo-geo-optimise' ); ?></dd>
					<?php if ( $last_time ) : ?>
						<dt><?php esc_html_e( 'Last validation', 'reactwoo-geo-optimise' ); ?></dt>
						<dd>
							<?php
							echo esc_html( $last_time );
							if ( ! $last_ok && $last_err ) {
								echo ' — ';
								echo esc_html( $last_err );
							}
							?>
						</dd>
					<?php endif; ?>
				</dl>
			</div>

			<form method="post" action="options.php" class="rwgo-license-form">
				<?php settings_fields( 'rwgo_license_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[rwgo_form_scope]" value="license" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rwgo_reactwoo_license_key"><?php esc_html_e( 'License key', 'reactwoo-geo-optimise' ); ?></label></th>
						<td>
							<input type="password" id="rwgo_reactwoo_license_key" name="<?php echo esc_attr( $option_key ); ?>[reactwoo_license_key]" value="" class="regular-text rwgo-input" autocomplete="off" placeholder="<?php echo esc_attr__( 'Enter new key or leave blank to keep current', 'reactwoo-geo-optimise' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to keep the saved key.', 'reactwoo-geo-optimise' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save license', 'reactwoo-geo-optimise' ), 'primary', 'submit', false ); ?>
			</form>

			<?php if ( $lic_ok && class_exists( 'RWGC_Platform_Client', false ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-license-test-form">
					<input type="hidden" name="action" value="rwgo_test_license" />
					<?php wp_nonce_field( 'rwgo_test_license' ); ?>
					<p class="rwgo-cta-row">
						<button type="submit" class="button"><?php esc_html_e( 'Test connection', 'reactwoo-geo-optimise' ); ?></button>
						<span class="rwgo-hint"><?php esc_html_e( 'Checks that your saved key can obtain an API token.', 'reactwoo-geo-optimise' ); ?></span>
					</p>
				</form>
			<?php endif; ?>

			<?php if ( $lic_ok ) : ?>
				<p class="rwgo-license-actions">
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwgo-license&rwgo_action=clear_license' ), 'rwgo_clear_license' ) ); ?>" onclick="return window.confirm(<?php echo esc_js( __( 'Remove the license key from this site?', 'reactwoo-geo-optimise' ) ); ?>);"><?php esc_html_e( 'Disconnect', 'reactwoo-geo-optimise' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>
