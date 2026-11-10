<?php
/**
 * WordPress integration for the site-wide theme arm.
 *
 * Run with: wp eval-file tests/integration/local-wp-theme-ab.php
 * from a local WordPress that has Geo Core, Elementor, and this plugin active.
 *
 * This is not staging.reactwoo.com.
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file with wp eval-file inside WordPress.\n" );
	exit( 1 );
}

$fail = 0;
$check = static function ( $label, $ok ) use ( &$fail ) {
	if ( ! $ok ) {
		echo "FAIL {$label}\n";
		$fail++;
		return;
	}
	echo "ok {$label}\n";
};

$check( 'Geo Core is loaded', defined( 'RWGC_VERSION' ) && class_exists( 'RWGC_Plugin', false ) );
$check( 'Optimise is 0.5.10', defined( 'RWGO_VERSION' ) && '0.5.10' === RWGO_VERSION );
$check( 'Elementor is loaded', defined( 'ELEMENTOR_VERSION' ) );
$check( 'theme context class is loaded', class_exists( 'RWGO_Theme_Context', false ) && class_exists( 'RWGO_Sitewide', false ) );

$control = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'RWGO local control',
		'post_name'   => 'rwgo-local-control',
	),
	true
);
$variant = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'RWGO local variant',
		'post_name'   => 'rwgo-local-variant',
	),
	true
);
$check( 'pages were created', ! is_wp_error( $control ) && ! is_wp_error( $variant ) && (int) $control > 0 && (int) $variant > 0 );

$header_type = post_type_exists( 'elementor_library' ) ? 'elementor_library' : 'page';
$header      = wp_insert_post(
	array(
		'post_type'   => $header_type,
		'post_status' => 'publish',
		'post_title'  => 'RWGO local variant header',
	),
	true
);
$check( 'header document was created', ! is_wp_error( $header ) && (int) $header > 0 );
if ( 'elementor_library' === $header_type ) {
	update_post_meta( (int) $header, '_elementor_template_type', 'header' );
}

$cfg = array(
	'experiment_key' => 'rwgo_local_wp',
	'status'         => 'active',
	'test_type'      => 'sitewide',
	'scope'          => 'site',
	'cookie_days'    => 90,
	'control_weight' => 0.5,
	'page_map'       => array(
		array(
			'source_page_id'  => (int) $control,
			'variant_page_id' => (int) $variant,
			'source_path'     => '/rwgo-local-control/',
			'variant_path'    => '/rwgo-local-variant/',
		),
	),
	'theme_context'  => array(
		'control' => array(
			'header' => 0,
			'footer' => 0,
		),
		'variant' => array(
			'header' => (int) $header,
			'footer' => 0,
		),
	),
);
update_option( 'rwgo_sitewide_experiment', $cfg, false );

$plan = RWGO_Theme_Context::delivery_plan(
	'variant',
	(int) $variant,
	(string) get_post_status( (int) $variant ),
	false,
	$cfg['theme_context'],
	array( (int) $header => (string) get_post_status( (int) $header ) ),
	array( (int) $header => (string) get_post_type( (int) $header ) )
);
$check( 'draft variant page is not swapped', 'control' === $plan['body'] && 0 === (int) $plan['swap_page_id'] );
$check( 'draft variant page was not published', 'draft' === get_post_status( (int) $variant ) );

if ( 'elementor_library' === $header_type ) {
	$check( 'published library header is deliverable', (int) $plan['header'] === (int) $header && 'variant' === $plan['delivered'] );
} else {
	$check( 'a page is not used as a header', 0 === (int) $plan['header'] && 'control' === $plan['delivered'] );
}

RWGO_Theme_Context::set_request_plan( $plan );
$filtered = apply_filters( 'elementor/theme/get_location_templates/template_id', 999001, 'header' );
if ( 'elementor_library' === $header_type && 'variant' === $plan['delivered'] ) {
	$check( 'location filter returns the variant header', (int) $filtered === (int) $header );
} else {
	$check( 'location filter does not invent a header', (int) $filtered !== (int) $header || 0 === (int) $plan['header'] );
}
RWGO_Theme_Context::set_request_plan( null );

$locked = RWGO_Theme_Context::variant_theme_locked( 'checkout', (int) $control, array( (int) $control ), false, array(), array( 'shop' ) );
$check( 'checkout slug locks variant chrome', $locked );

$warnings = RWGO_Theme_Context::audit(
	$cfg['page_map'],
	array( (int) $variant => 'draft' ),
	$cfg['theme_context'],
	array( (int) $header => (string) get_post_status( (int) $header ) ),
	array( (int) $header => (string) get_post_type( (int) $header ) )
);
$codes = array();
foreach ( $warnings as $warning ) {
	$codes[] = $warning['code'];
}
$check( 'admin audit warns that the variant page is unpublished', in_array( 'variant_page_unpublished', $codes, true ) );
$check( 'audit did not publish the draft', 'draft' === get_post_status( (int) $variant ) );

echo 'RWGC_VERSION=' . ( defined( 'RWGC_VERSION' ) ? RWGC_VERSION : '' ) . "\n";
echo 'RWGO_VERSION=' . ( defined( 'RWGO_VERSION' ) ? RWGO_VERSION : '' ) . "\n";
echo 'ELEMENTOR_VERSION=' . ( defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '' ) . "\n";
echo 'header_post_type=' . $header_type . "\n";

if ( $fail > 0 ) {
	echo "{$fail} failed\n";
	exit( 1 );
}
echo "local wordpress checks passed\n";
