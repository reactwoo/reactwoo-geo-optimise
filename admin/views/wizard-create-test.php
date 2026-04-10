<?php
/**
 * Create Test — guided form (shared partial).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-create-test';

$rwgo_test_types = array( 'page_ab', 'elementor_page', 'gutenberg_page', 'woo_product', 'custom_php' );
$rwgo_catalog      = array();
foreach ( $rwgo_test_types as $tt ) {
	$rwgo_catalog[ $tt ] = class_exists( 'RWGO_Admin_Content_Catalog', false )
		? RWGO_Admin_Content_Catalog::get_choices( $tt )
		: array();
}
$rwgo_form_mode = 'create';
?>
<div class="wrap rwgc-wrap rwgc-suite rwgo-wrap rwgo-wrap--create-test">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Create Test', 'reactwoo-geo-optimise' ),
			__( 'Create page tests, compare variants, and see which version is leading — Control (A) vs Variant (B), without editing code.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Create Test', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( ! empty( $_GET['rwgo_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<?php if ( 'confirm' === $_GET['rwgo_error'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-warning rwgo-notice"><p><?php esc_html_e( 'Confirm the test setup below before publishing — your site requires an extra confirmation step (see Settings).', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php elseif ( 'variant_b' === $_GET['rwgo_error'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-error rwgo-notice"><p><?php esc_html_e( 'Choose a valid Variant B page that matches the test type and is different from Control.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php elseif ( 'source_type' === $_GET['rwgo_error'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-error rwgo-notice"><p><?php esc_html_e( 'The selected source does not match the test type. Pick again or change the test type.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php elseif ( 'dup_invalid' === $_GET['rwgo_error'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-error rwgo-notice"><p><?php esc_html_e( 'Variant B could not be created as a valid duplicate of your source page (builder data did not validate). The test was not created. Try again, pick an existing page as Variant B, or create a blank variant.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php else : ?>
			<div class="notice notice-error rwgo-notice"><p><?php esc_html_e( 'Could not create the test. Check fields and permissions, then try again.', 'reactwoo-geo-optimise' ); ?></p></div>
		<?php endif; ?>
	<?php endif; ?>

	<?php require RWGO_PATH . 'admin/views/partials/test-form.php'; ?>
</div>
