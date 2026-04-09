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
$import_sources   = class_exists( 'RWGO_Settings', false ) ? RWGO_Settings::get_manual_import_sources() : array();

?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--license">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'License', 'reactwoo-geo-optimise' ),
			__( 'Activate your ReactWoo Geo Optimise plan. The key is stored on this site and used only by Geo Optimise when features require it.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Optimise — License', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( ! empty( $_GET['rwgo_disconnected'] ) || isset( $_GET['rwgo_license_test'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="rwgo-page-notices">
	<?php if ( ! empty( $_GET['rwgo_disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible rwgo-alert rwgo-alert--success"><p class="rwgo-alert__text"><?php esc_html_e( 'License key removed from this site.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgo_license_test'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php if ( '1' === (string) $_GET['rwgo_license_test'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success is-dismissible rwgo-alert rwgo-alert--success"><p class="rwgo-alert__text"><?php esc_html_e( 'Connection OK — license validated with the ReactWoo API.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php elseif ( '0' === (string) $_GET['rwgo_license_test'] ) : ?>
			<div class="notice notice-error is-dismissible rwgo-alert rwgo-alert--danger"><p class="rwgo-alert__text"><?php esc_html_e( 'Could not validate the license. Check the key, API base, and network, then try again.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php elseif ( 'na' === (string) $_GET['rwgo_license_test'] ) : ?>
			<div class="notice notice-warning is-dismissible rwgo-alert rwgo-alert--warning"><p class="rwgo-alert__text"><?php esc_html_e( 'Geo Optimise platform client is not available — validation could not run.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>
	</div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible rwgo-alert rwgo-alert--success"><p class="rwgo-alert__text"><?php esc_html_e( 'License key imported into Geo Optimise.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['rwgo_import_err'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-error is-dismissible rwgo-alert rwgo-alert--danger"><p class="rwgo-alert__text"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['rwgo_import_err'] ) ) ); ?></p></div>
	<?php endif; ?>

	<div class="rwgo-stack rwgo-license-layout">
		<div class="rwgo-panel rwgo-panel--compact">
			<h2 class="rwgo-section__title"><?php esc_html_e( 'License status', 'reactwoo-geo-optimise' ); ?></h2>
			<div class="rwgo-license-status <?php echo $lic_ok ? 'is-ok' : ''; ?>">
				<dl>
					<dt><?php esc_html_e( 'Status', 'reactwoo-geo-optimise' ); ?></dt>
					<dd><?php echo $lic_ok ? esc_html__( 'Connected — key saved on this site', 'reactwoo-geo-optimise' ) : esc_html__( 'No active license saved', 'reactwoo-geo-optimise' ); ?></dd>
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
		</div>

		<form method="post" action="options.php" class="rwgo-panel">
			<?php settings_fields( 'rwgo_license_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[rwgo_form_scope]" value="license" />

			<h2 class="rwgo-section__title"><?php esc_html_e( 'License key', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="rwgo-section__lead"><?php esc_html_e( 'Your license is connected and stored on this site when a key is saved. Leave the field blank to keep the current key.', 'reactwoo-geo-optimise' ); ?></p>

			<div class="rwgo-setting-rows">
				<div class="rwgo-setting-row">
					<div class="rwgo-setting-row__label">
						<label for="rwgo_reactwoo_license_key"><?php esc_html_e( 'Key', 'reactwoo-geo-optimise' ); ?></label>
					</div>
					<div class="rwgo-setting-row__control">
						<input type="password" id="rwgo_reactwoo_license_key" name="<?php echo esc_attr( $option_key ); ?>[reactwoo_license_key]" value="" class="regular-text rwgo-input" autocomplete="off" placeholder="<?php echo esc_attr__( 'Enter new key or leave blank to keep current', 'reactwoo-geo-optimise' ); ?>" />
						<p class="rwgo-setting-row__hint"><?php esc_html_e( 'Leave blank to keep the saved key.', 'reactwoo-geo-optimise' ); ?></p>
					</div>
				</div>
			</div>

			<div class="rwgo-license-actions-bar">
				<?php submit_button( __( 'Save license', 'reactwoo-geo-optimise' ), 'primary', 'submit', false ); ?>
			</div>

			<?php if ( ! empty( $import_sources ) ) : ?>
				<p class="rwgo-setting-row__hint"><?php esc_html_e( 'Optional: import a key once from another ReactWoo plugin. This does not create ongoing sharing between plugins.', 'reactwoo-geo-optimise' ); ?></p>
				<div class="rwgo-license-actions-bar">
					<?php foreach ( $import_sources as $source => $label ) : ?>
						<a class="button rwgo-btn rwgo-btn--secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwgo-license&rwgo_action=import_license&source=' . rawurlencode( $source ) ), 'rwgo_import_license' ) ); ?>"><?php echo esc_html( sprintf( __( 'Import from %s', 'reactwoo-geo-optimise' ), $label ) ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</form>

		<?php if ( $lic_ok ) : ?>
			<div class="rwgo-panel rwgo-panel--compact rwgo-license-actions-panel">
				<h2 class="screen-reader-text"><?php esc_html_e( 'More license actions', 'reactwoo-geo-optimise' ); ?></h2>
				<div class="rwgo-license-actions-bar">
					<?php if ( class_exists( 'RWGO_Platform_Client', false ) ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgo-license-test-form">
							<input type="hidden" name="action" value="rwgo_test_license" />
							<?php wp_nonce_field( 'rwgo_test_license' ); ?>
							<button type="submit" class="button rwgo-btn rwgo-btn--secondary"><?php esc_html_e( 'Test connection', 'reactwoo-geo-optimise' ); ?></button>
						</form>
						<span class="rwgo-license-actions-bar__hint"><?php esc_html_e( 'Validates that the saved key can obtain an API token.', 'reactwoo-geo-optimise' ); ?></span>
					<?php endif; ?>
					<a class="rwgo-link-destructive" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwgo-license&rwgo_action=clear_license' ), 'rwgo_clear_license' ) ); ?>" onclick="return window.confirm(<?php echo esc_js( __( 'Remove the license key from this site?', 'reactwoo-geo-optimise' ) ); ?>);"><?php esc_html_e( 'Disconnect', 'reactwoo-geo-optimise' ); ?></a>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
