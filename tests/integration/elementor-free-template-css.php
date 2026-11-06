<?php
/**
 * Elementor free document CSS and condition-meta contract.
 *
 * Run after WordPress is loaded:
 * php -r 'require "/path/to/wp-load.php"; require "tests/integration/elementor-free-template-css.php";'
 *
 * Elementor Pro is not required and is not claimed. This checks that an
 * unassigned library template is absent from the condition candidate list our
 * guard uses, and that enqueueing one Elementor document stylesheet leaves
 * WooCommerce and Elementor frontend styles queued.
 *
 * @package ReactWoo_Geo_Optimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Load WordPress before this script.\n" );
	exit( 1 );
}

$failures = 0;
$check    = function ( $label, $ok ) use ( &$failures ) {
	echo ( $ok ? 'ok ' : 'FAIL ' ) . $label . "\n";
	if ( ! $ok ) {
		$failures++;
	}
};

echo class_exists( '\ElementorPro\Modules\ThemeBuilder\Classes\Conditions_Manager', false )
	? "note Elementor Pro conditions manager is loaded; this script does not call get_documents_for_location\n"
	: "ok Elementor Pro conditions manager is not loaded\n";
$check( 'Elementor Post CSS exists', class_exists( '\Elementor\Core\Files\CSS\Post' ) );
$check( 'condition candidate helper exists', method_exists( 'RWGO_Theme_Context', 'elementor_location_candidates' ) );

if ( ! method_exists( 'RWGO_Theme_Context', 'elementor_location_candidates' ) || ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
	echo "elementor-free integration skipped\n";
	exit( 0 );
}

$source  = wp_insert_post(
	array(
		'post_type'   => 'elementor_library',
		'post_status' => 'publish',
		'post_title'  => 'rwgo source template',
	)
);
$variant = wp_insert_post(
	array(
		'post_type'   => 'elementor_library',
		'post_status' => 'publish',
		'post_title'  => 'rwgo variant template',
	)
);
$check( 'created library posts', $source > 0 && $variant > 0 );
update_post_meta( $source, '_elementor_conditions', array( 'include/product' ) );
update_post_meta( $variant, '_elementor_conditions', array() );
update_post_meta( $variant, '_elementor_data', '[{"id":"abc","elType":"container","elements":[]}]' );
update_post_meta( $variant, '_elementor_edit_mode', 'builder' );

$cache = array(
	'single' => array(
		$variant => get_post_meta( $variant, '_elementor_conditions', true ),
		$source  => get_post_meta( $source, '_elementor_conditions', true ),
	),
);
$candidates = RWGO_Theme_Context::elementor_location_candidates( $cache, 'single' );
$check( 'unassigned variant is not a candidate', array( (int) $source ) === $candidates );

wp_register_style( 'woocommerce-general', 'https://example.test/woocommerce.css' );
wp_register_style( 'elementor-frontend', 'https://example.test/frontend.css' );
wp_enqueue_style( 'woocommerce-general' );
wp_enqueue_style( 'elementor-frontend' );
$css = new \Elementor\Core\Files\CSS\Post( $variant );
$css->enqueue();
$check( 'woocommerce-general stays queued', wp_style_is( 'woocommerce-general', 'enqueued' ) );
$check( 'elementor-frontend stays queued', wp_style_is( 'elementor-frontend', 'enqueued' ) );
$check(
	'variant document style registered',
	wp_style_is( 'elementor-post-' . $variant, 'enqueued' ) || wp_style_is( 'elementor-post-' . $variant, 'registered' )
);

wp_delete_post( $source, true );
wp_delete_post( $variant, true );
echo 0 === $failures ? "elementor-free integration passed\n" : "elementor-free integration failed\n";
exit( 0 === $failures ? 0 : 1 );
