<?php
/**
 * License screen — ReactWoo product key for Geo Optimise (same pipeline as Geo AI).
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

?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--license">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'License', 'reactwoo-geo-optimise' ),
			__( 'Activate your ReactWoo Geo Optimise plan. The key is stored on this site and used with Geo Core’s platform client (JWT) when features require it.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Optimise — License', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( ! empty( $_GET['rwgo_disconnected'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'License key removed from this site.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>

	<div class="rwgc-grid" style="align-items: flex-start;">
		<div class="rwgc-card" style="max-width: 520px;">
			<h2><?php esc_html_e( 'Product license', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="description"><?php esc_html_e( 'If you already entered a key in Geo AI or Geo Core, it may be copied here automatically until you save a key specific to Geo Optimise.', 'reactwoo-geo-optimise' ); ?></p>

			<p style="margin: 12px 0;">
				<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
					<?php
					RWGC_Admin_UI::render_badge(
						$lic_ok ? __( 'Key on file', 'reactwoo-geo-optimise' ) : __( 'Not configured', 'reactwoo-geo-optimise' ),
						$lic_ok ? 'success' : 'warning'
					);
					?>
				<?php else : ?>
					<strong><?php echo $lic_ok ? esc_html__( 'Key on file', 'reactwoo-geo-optimise' ) : esc_html__( 'Not configured', 'reactwoo-geo-optimise' ); ?></strong>
				<?php endif; ?>
			</p>

			<form method="post" action="options.php" class="rwgo-license-form">
				<?php settings_fields( 'rwgo_license_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[rwgo_form_scope]" value="license" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="rwgo_reactwoo_license_key"><?php esc_html_e( 'License key', 'reactwoo-geo-optimise' ); ?></label></th>
						<td>
							<input type="password" id="rwgo_reactwoo_license_key" name="<?php echo esc_attr( $option_key ); ?>[reactwoo_license_key]" value="" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr__( 'Enter new key or leave blank to keep current', 'reactwoo-geo-optimise' ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave blank to keep the saved key.', 'reactwoo-geo-optimise' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save license', 'reactwoo-geo-optimise' ) ); ?>
			</form>

			<?php if ( $lic_ok ) : ?>
				<p class="rwgo-license-actions">
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rwgo-license&rwgo_action=clear_license' ), 'rwgo_clear_license' ) ); ?>" onclick="return window.confirm(<?php echo esc_js( __( 'Remove the license key from this site?', 'reactwoo-geo-optimise' ) ); ?>);"><?php esc_html_e( 'Disconnect', 'reactwoo-geo-optimise' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>
