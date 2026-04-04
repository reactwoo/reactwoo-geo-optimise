<?php
/**
 * Site settings — builder detection mode and measurement behaviour.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$option_key       = RWGO_Settings::OPTION_KEY;
$settings         = RWGO_Settings::get_settings();
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-settings';

$builder_mode = isset( $settings['builder_mode'] ) ? sanitize_key( (string) $settings['builder_mode'] ) : 'recommended';
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--settings">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Settings', 'reactwoo-geo-optimise' ),
			__( 'How the plugin detects builders and attaches goals — tuned for your site, without exposing raw jargon in the test wizard.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Optimise — Settings', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<form method="post" action="options.php" class="rwgc-card">
		<?php settings_fields( 'rwgo_license_group' ); ?>
		<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[rwgo_form_scope]" value="optimisation" />

		<h2><?php esc_html_e( 'Builder & detection', 'reactwoo-geo-optimise' ); ?></h2>
		<p class="description"><?php esc_html_e( 'These presets control how we infer Elementor, blocks, and WooCommerce context. Technical details stay under Developer.', 'reactwoo-geo-optimise' ); ?></p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Default mode', 'reactwoo-geo-optimise' ); ?></th>
				<td>
					<select name="<?php echo esc_attr( $option_key ); ?>[builder_mode]" id="rwgo_builder_mode">
						<option value="recommended" <?php selected( $builder_mode, 'recommended' ); ?>><?php esc_html_e( 'Recommended — safest for most sites', 'reactwoo-geo-optimise' ); ?></option>
						<option value="page_builder" <?php selected( $builder_mode, 'page_builder' ); ?>><?php esc_html_e( 'Page builder focused — Elementor or Gutenberg-heavy', 'reactwoo-geo-optimise' ); ?></option>
						<option value="flexible" <?php selected( $builder_mode, 'flexible' ); ?>><?php esc_html_e( 'Flexible detection — mixed or complex pages', 'reactwoo-geo-optimise' ); ?></option>
						<option value="manual" <?php selected( $builder_mode, 'manual' ); ?>><?php esc_html_e( 'Manual / developer — minimal automatic binding', 'reactwoo-geo-optimise' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Mixed site support', 'reactwoo-geo-optimise' ); ?></th>
				<td>
					<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[mixed_site_support]" value="0" />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[mixed_site_support]" value="1" <?php checked( ! empty( $settings['mixed_site_support'] ) ); ?> />
						<?php esc_html_e( 'Show extra guidance when multiple builders are detected on one page.', 'reactwoo-geo-optimise' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'DOM fallback', 'reactwoo-geo-optimise' ); ?></th>
				<td>
					<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[enable_dom_fallback]" value="0" />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[enable_dom_fallback]" value="1" <?php checked( ! empty( $settings['enable_dom_fallback'] ) ); ?> />
						<?php esc_html_e( 'Allow rendered-page fallbacks when stored builder data is unclear.', 'reactwoo-geo-optimise' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Measurement', 'reactwoo-geo-optimise' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'WooCommerce server goals', 'reactwoo-geo-optimise' ); ?></th>
				<td>
					<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[enable_woocommerce_goal_hooks]" value="0" />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[enable_woocommerce_goal_hooks]" value="1" <?php checked( ! empty( $settings['enable_woocommerce_goal_hooks'] ) ); ?> />
						<?php esc_html_e( 'Record add to cart, begin checkout, and purchase goals via WooCommerce hooks (recommended when those goals are used).', 'reactwoo-geo-optimise' ); ?>
					</label>
					<?php if ( ! class_exists( 'WooCommerce', false ) ) : ?>
						<p class="description"><?php esc_html_e( 'WooCommerce is not active — these hooks load only when WooCommerce is available.', 'reactwoo-geo-optimise' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Publishing', 'reactwoo-geo-optimise' ); ?></th>
				<td>
					<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[require_goal_confirm_publish]" value="0" />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[require_goal_confirm_publish]" value="1" <?php checked( ! empty( $settings['require_goal_confirm_publish'] ) ); ?> />
						<?php esc_html_e( 'Require a confirmation checkbox on Create Test before a test can be published.', 'reactwoo-geo-optimise' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Strict binding', 'reactwoo-geo-optimise' ); ?></th>
				<td>
					<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[strict_binding_mode]" value="0" />
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[strict_binding_mode]" value="1" <?php checked( ! empty( $settings['strict_binding_mode'] ) ); ?> />
						<?php esc_html_e( 'Prefer explicit selectors and fingerprints over loose matches (advanced).', 'reactwoo-geo-optimise' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'reactwoo-geo-optimise' ) ); ?>
	</form>
</div>
