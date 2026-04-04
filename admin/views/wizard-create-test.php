<?php
/**
 * Create Test — guided form (posts to admin_post_rwgo_create_test).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-create-test';

$rwgo_require_confirm = class_exists( 'RWGO_Settings', false ) && RWGO_Settings::require_goal_confirm_publish();

$source_choices = get_posts(
	array(
		'post_type'      => array( 'page', 'post', 'product' ),
		'post_status'    => 'publish',
		'posts_per_page' => 400,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>
<div class="wrap rwgc-wrap rwgo-wrap rwgo-wrap--create-test">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Create Test', 'reactwoo-geo-optimise' ),
			__( 'Choose content, audience, and what counts as success — we generate keys, variants, and tracking ids for you.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( ! empty( $_GET['rwgo_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php if ( 'confirm' === $_GET['rwgo_error'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'Confirm the test setup below before publishing — your site requires an explicit confirmation.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php else : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'Could not create the test. Check fields and permissions, then try again.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rwgc-card" id="rwgo-create-test-form">
		<input type="hidden" name="action" value="rwgo_create_test" />
		<?php wp_nonce_field( 'rwgo_create_test' ); ?>

		<h2><?php esc_html_e( '1. Test name', 'reactwoo-geo-optimise' ); ?></h2>
		<p>
			<label for="rwgo_test_name"><?php esc_html_e( 'Name', 'reactwoo-geo-optimise' ); ?></label><br />
			<input type="text" class="regular-text" id="rwgo_test_name" name="rwgo_test_name" required placeholder="<?php esc_attr_e( 'e.g. Homepage hero', 'reactwoo-geo-optimise' ); ?>" />
		</p>

		<h2><?php esc_html_e( '2. Test type', 'reactwoo-geo-optimise' ); ?></h2>
		<p>
			<select name="rwgo_test_type" id="rwgo_test_type">
				<option value="page_ab"><?php esc_html_e( 'Page A/B test', 'reactwoo-geo-optimise' ); ?></option>
				<option value="elementor_page"><?php esc_html_e( 'Elementor page variation', 'reactwoo-geo-optimise' ); ?></option>
				<option value="gutenberg_page"><?php esc_html_e( 'Gutenberg page variation', 'reactwoo-geo-optimise' ); ?></option>
				<option value="woo_product"><?php esc_html_e( 'WooCommerce product page test', 'reactwoo-geo-optimise' ); ?></option>
				<option value="custom_php"><?php esc_html_e( 'Advanced / custom (PHP or bespoke)', 'reactwoo-geo-optimise' ); ?></option>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Variant B is duplicated from your source when possible; builder data is copied when present.', 'reactwoo-geo-optimise' ); ?></p>

		<h2><?php esc_html_e( '3. Source content', 'reactwoo-geo-optimise' ); ?></h2>
		<p>
			<label for="rwgo_source_page"><?php esc_html_e( 'Page, post, or product', 'reactwoo-geo-optimise' ); ?></label><br />
			<select name="rwgo_source_page" id="rwgo_source_page" required>
				<option value=""><?php esc_html_e( '— Select —', 'reactwoo-geo-optimise' ); ?></option>
				<?php foreach ( $source_choices as $p ) : ?>
					<option value="<?php echo esc_attr( (string) $p->ID ); ?>">
						<?php echo esc_html( $p->post_title . ' (' . $p->post_type . ' #' . $p->ID . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'After you publish, we store builder detection (Elementor, blocks, WooCommerce, etc.) with the test for goal setup and reporting.', 'reactwoo-geo-optimise' ); ?></p>

		<h2><?php esc_html_e( '4. Targeting', 'reactwoo-geo-optimise' ); ?></h2>
		<p>
			<select name="rwgo_targeting_mode" id="rwgo_targeting_mode">
				<option value="everyone"><?php esc_html_e( 'Everyone', 'reactwoo-geo-optimise' ); ?></option>
				<option value="countries"><?php esc_html_e( 'Selected countries', 'reactwoo-geo-optimise' ); ?></option>
			</select>
		</p>
		<p>
			<label for="rwgo_countries"><?php esc_html_e( 'Country codes (comma-separated, ISO 3166-1 alpha-2)', 'reactwoo-geo-optimise' ); ?></label><br />
			<input type="text" class="regular-text" id="rwgo_countries" name="rwgo_countries" placeholder="GB,US,DE" />
		</p>

		<h2><?php esc_html_e( '5. What counts as success?', 'reactwoo-geo-optimise' ); ?></h2>
		<fieldset>
			<label>
				<input type="radio" name="rwgo_winner_mode" value="goal" checked="checked" class="rwgo-winner-mode" />
				<?php esc_html_e( 'Define a primary goal (recommended) — used to pick a winning variant.', 'reactwoo-geo-optimise' ); ?>
			</label><br /><br />
			<label>
				<input type="radio" name="rwgo_winner_mode" value="traffic_only" class="rwgo-winner-mode" />
				<?php esc_html_e( 'Traffic split only — measure reach per variant; no conversion winner.', 'reactwoo-geo-optimise' ); ?>
			</label>
		</fieldset>

		<div id="rwgo-goal-wrap" class="rwgo-goal-wrap">
			<p>
				<label for="rwgo_goal_type"><?php esc_html_e( 'Primary goal', 'reactwoo-geo-optimise' ); ?></label><br />
				<select name="rwgo_goal_type" id="rwgo_goal_type">
					<option value="page_view"><?php esc_html_e( 'Page view', 'reactwoo-geo-optimise' ); ?></option>
					<option value="cta_click"><?php esc_html_e( 'CTA click', 'reactwoo-geo-optimise' ); ?></option>
					<option value="form_submit"><?php esc_html_e( 'Form submission', 'reactwoo-geo-optimise' ); ?></option>
					<option value="add_to_cart"><?php esc_html_e( 'Add to cart', 'reactwoo-geo-optimise' ); ?></option>
					<option value="begin_checkout"><?php esc_html_e( 'Begin checkout', 'reactwoo-geo-optimise' ); ?></option>
					<option value="purchase"><?php esc_html_e( 'Purchase', 'reactwoo-geo-optimise' ); ?></option>
					<option value="custom_event"><?php esc_html_e( 'Custom event', 'reactwoo-geo-optimise' ); ?></option>
				</select>
			</p>
			<p class="description"><?php esc_html_e( 'Finer binding (selectors, WooCommerce hooks) can be validated under Tracking Tools and Developer after publish.', 'reactwoo-geo-optimise' ); ?></p>
		</div>

		<h2><?php esc_html_e( '6. Publish', 'reactwoo-geo-optimise' ); ?></h2>
		<p class="description"><?php esc_html_e( 'We create Variant B from your page, start routing visitors between versions, and keep each visitor on the same version when they return.', 'reactwoo-geo-optimise' ); ?></p>
		<?php if ( $rwgo_require_confirm ) : ?>
			<div id="rwgo-confirm-wrap" class="rwgo-confirm-wrap">
				<p>
					<input type="hidden" name="rwgo_confirm_publish" value="0" />
					<label>
						<input type="checkbox" id="rwgo_confirm_publish" name="rwgo_confirm_publish" value="1" />
						<?php esc_html_e( 'I confirm the audience, success metric, and content choices for this test.', 'reactwoo-geo-optimise' ); ?>
					</label>
				</p>
			</div>
		<?php endif; ?>
		<?php submit_button( __( 'Publish test', 'reactwoo-geo-optimise' ), 'primary', 'submit', false ); ?>
	</form>
</div>
<script>
(function () {
	var radios = document.querySelectorAll('.rwgo-winner-mode');
	var wrap = document.getElementById('rwgo-goal-wrap');
	var confirmWrap = document.getElementById('rwgo-confirm-wrap');
	var confirmCb = document.getElementById('rwgo_confirm_publish');
	function sync() {
		var traffic = false;
		radios.forEach(function (r) { if (r.checked && r.value === 'traffic_only') traffic = true; });
		if (wrap) wrap.style.display = traffic ? 'none' : '';
		var sel = document.getElementById('rwgo_goal_type');
		if (sel) sel.disabled = traffic;
		if (confirmWrap) {
			confirmWrap.style.display = traffic ? 'none' : '';
		}
		if (confirmCb) {
			confirmCb.required = !traffic;
		}
	}
	radios.forEach(function (r) { r.addEventListener('change', sync); });
	sync();
})();
</script>
