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
			__( 'These settings control how Geo Optimise behaves by default across your site.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Geo Optimise — Settings', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<div class="rwgo-stack">
	<form method="post" action="options.php" class="rwgo-panel">
		<?php settings_fields( 'rwgo_license_group' ); ?>
		<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[rwgo_form_scope]" value="optimisation" />

		<h2 class="rwgo-section__title"><?php esc_html_e( 'Builder & detection', 'reactwoo-geo-optimise' ); ?></h2>
		<p class="rwgo-section__lead"><?php esc_html_e( 'Default site-wide preferences for how we infer Elementor, blocks, and WooCommerce context. Technical details stay under Developer.', 'reactwoo-geo-optimise' ); ?></p>

		<div class="rwgo-setting-rows">
			<div class="rwgo-setting-row">
				<div class="rwgo-setting-row__label"><?php esc_html_e( 'Default mode', 'reactwoo-geo-optimise' ); ?></div>
				<div class="rwgo-setting-row__control">
					<select name="<?php echo esc_attr( $option_key ); ?>[builder_mode]" id="rwgo_builder_mode" class="rwgo-input">
						<option value="recommended" <?php selected( $builder_mode, 'recommended' ); ?>><?php esc_html_e( 'Recommended — safest for most sites', 'reactwoo-geo-optimise' ); ?></option>
						<option value="page_builder" <?php selected( $builder_mode, 'page_builder' ); ?>><?php esc_html_e( 'Page builder focused — Elementor or Gutenberg-heavy', 'reactwoo-geo-optimise' ); ?></option>
						<option value="flexible" <?php selected( $builder_mode, 'flexible' ); ?>><?php esc_html_e( 'Flexible detection — mixed or complex pages', 'reactwoo-geo-optimise' ); ?></option>
						<option value="manual" <?php selected( $builder_mode, 'manual' ); ?>><?php esc_html_e( 'Manual / developer — minimal automatic binding', 'reactwoo-geo-optimise' ); ?></option>
					</select>
				</div>
			</div>
			<div class="rwgo-setting-row">
				<div class="rwgo-setting-row__label"><?php esc_html_e( 'Mixed site support', 'reactwoo-geo-optimise' ); ?></div>
				<div class="rwgo-setting-row__control">
					<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[mixed_site_support]" value="0" />
					<label class="rwgo-checkbox-line">
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[mixed_site_support]" value="1" <?php checked( ! empty( $settings['mixed_site_support'] ) ); ?> />
						<span><?php esc_html_e( 'Show extra guidance when multiple builders are detected on one page.', 'reactwoo-geo-optimise' ); ?></span>
					</label>
				</div>
			</div>
			<div class="rwgo-setting-row">
				<div class="rwgo-setting-row__label"><?php esc_html_e( 'DOM fallback', 'reactwoo-geo-optimise' ); ?></div>
				<div class="rwgo-setting-row__control">
					<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[enable_dom_fallback]" value="0" />
					<label class="rwgo-checkbox-line">
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[enable_dom_fallback]" value="1" <?php checked( ! empty( $settings['enable_dom_fallback'] ) ); ?> />
						<span><?php esc_html_e( 'Allow rendered-page fallbacks when stored builder data is unclear.', 'reactwoo-geo-optimise' ); ?></span>
					</label>
				</div>
			</div>
		</div>

		<h2 class="rwgo-section__title rwgo-section__title--subsection"><?php esc_html_e( 'Measurement', 'reactwoo-geo-optimise' ); ?></h2>
		<div class="rwgo-setting-rows">
			<div class="rwgo-setting-row">
				<div class="rwgo-setting-row__label"><?php esc_html_e( 'WooCommerce server goals', 'reactwoo-geo-optimise' ); ?></div>
				<div class="rwgo-setting-row__control">
					<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[enable_woocommerce_goal_hooks]" value="0" />
					<label class="rwgo-checkbox-line">
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[enable_woocommerce_goal_hooks]" value="1" <?php checked( ! empty( $settings['enable_woocommerce_goal_hooks'] ) ); ?> />
						<span><?php esc_html_e( 'Record add to cart, begin checkout, and purchase goals via WooCommerce hooks (recommended when those goals are used).', 'reactwoo-geo-optimise' ); ?></span>
					</label>
					<?php if ( ! class_exists( 'WooCommerce', false ) ) : ?>
						<p class="rwgo-setting-row__hint"><?php esc_html_e( 'WooCommerce is not active — these hooks load only when WooCommerce is available.', 'reactwoo-geo-optimise' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
			<div class="rwgo-setting-row">
				<div class="rwgo-setting-row__label"><?php esc_html_e( 'Publish confirmation', 'reactwoo-geo-optimise' ); ?></div>
				<div class="rwgo-setting-row__control">
					<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[require_goal_confirm_publish]" value="0" />
					<label class="rwgo-checkbox-line">
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[require_goal_confirm_publish]" value="1" <?php checked( ! empty( $settings['require_goal_confirm_publish'] ) ); ?> />
						<span><?php esc_html_e( 'Require an extra confirmation step before a test can be published.', 'reactwoo-geo-optimise' ); ?></span>
					</label>
				</div>
			</div>
			<div class="rwgo-setting-row">
				<div class="rwgo-setting-row__label"><?php esc_html_e( 'Strict binding', 'reactwoo-geo-optimise' ); ?></div>
				<div class="rwgo-setting-row__control">
					<input type="hidden" name="<?php echo esc_attr( $option_key ); ?>[strict_binding_mode]" value="0" />
					<label class="rwgo-checkbox-line">
						<input type="checkbox" name="<?php echo esc_attr( $option_key ); ?>[strict_binding_mode]" value="1" <?php checked( ! empty( $settings['strict_binding_mode'] ) ); ?> />
						<span><?php esc_html_e( 'Prefer explicit CSS selectors and element fingerprints over loose matches. Useful for strict tag setups; can require more markup work.', 'reactwoo-geo-optimise' ); ?></span>
					</label>
				</div>
			</div>
		</div>

		<p class="rwgo-actions rwgo-actions--form-submit">
			<?php submit_button( __( 'Save settings', 'reactwoo-geo-optimise' ), 'primary', 'submit', false, array( 'class' => 'button button-primary rwgo-btn rwgo-btn--primary' ) ); ?>
		</p>
	</form>
	</div>
</div>
