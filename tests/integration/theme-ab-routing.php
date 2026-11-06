<?php
/**
 * Routing and cache decisions for one site-wide test.
 *
 * This does not boot WordPress or Elementor. It runs the same pure decisions
 * the front-end hooks call: unpublished body, header selection, cache bypass,
 * redirect safety, preview, and consent.
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * @param mixed $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string|false
	 */
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook  Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		unset( $hook );
		return $value;
	}
}

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-theme-context.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide.php';

$fail  = 0;
$check = static function ( $label, $ok ) use ( &$fail ) {
	if ( ! $ok ) {
		echo "FAIL {$label}\n";
		$fail++;
		return;
	}
	echo "ok {$label}\n";
};

$theme = array(
	'control' => array(
		'header' => 11,
		'footer' => 12,
	),
	'variant' => array(
		'header' => 21,
		'footer' => 22,
	),
);
$types = array(
	11 => 'elementor_library',
	12 => 'elementor_library',
	21 => 'elementor_library',
	22 => 'elementor_library',
);
$live = array(
	11 => 'publish',
	12 => 'publish',
	21 => 'publish',
	22 => 'publish',
);

$draft_body = RWGO_Theme_Context::delivery_plan( 'variant', 4038, 'draft', false, $theme, $live, $types );
$check( 'draft body stays on the original page', 'control' === $draft_body['body'] && 0 === $draft_body['swap_page_id'] );
$check( 'published header still delivers variant chrome', 'variant' === $draft_body['delivered'] && 21 === $draft_body['header'] );

$control = RWGO_Theme_Context::delivery_plan( 'control', 0, '', false, $theme, $live, $types );
$chosen  = RWGO_Theme_Context::location_template_id( 'header', 21, $control['header'], $control['owned_variant_template_ids'], false );
$check( 'display condition cannot leak variant header onto control', 11 === $chosen );

$check(
	'unmapped page with a theme split bypasses a shared cache',
	RWGO_Theme_Context::request_needs_cache_bypass( 'none', true, true, false )
	&& RWGO_Theme_Context::request_needs_cache_bypass( 'none', true, true, true )
);
$headers = RWGO_Sitewide::cache_bypass_headers( false );
$check(
	'response is private and varies on the cookie',
	false !== strpos( $headers['Cache-Control'], 'private' )
	&& false !== strpos( $headers['Cache-Control'], 'no-store' )
	&& 'Cookie' === $headers['Vary']
);

$check( 'paused test does not serve an arm', ! RWGO_Theme_Context::experiment_serves_arms( 'paused' ) );
$check( 'paused test still sends a variant URL home', RWGO_Theme_Context::experiment_canonicalizes_variant_urls( 'paused' ) );
$check(
	'variant URL returns to the original once',
	'https://example.test/atomic/' === RWGO_Theme_Context::safe_variant_redirect( 3502, 4038, 'https://example.test/atomic/', '/atomic/', array( 4038 ), array( '/atomic-b/' => true ) )
);
$check(
	'original that is also a variant is not redirected',
	'' === RWGO_Theme_Context::safe_variant_redirect( 3502, 4038, 'https://example.test/atomic/', '/atomic/', array( 3502, 4038 ), array() )
);

$id  = '0123456789abcdef0123456789abcdef';
$arm = RWGO_Sitewide::bucket_arm( $id, 'rwgo_sitewide', 0.5 );
$raw = wp_json_encode(
	array(
		'id'  => $id,
		'arm' => $arm,
		'exp' => 'rwgo_sitewide',
	)
);
$view = RWGO_Sitewide::preview_serving( $raw, 'rwgo_sitewide', 0.5, 'variant' );
$check( 'preview shows variant without rewriting the cookie or measuring', 'variant' === $view['serve'] && $view['cookie_arm'] === $arm && ! $view['measure'] );
$check( 'preview without a nonce is ignored', '' === RWGO_Theme_Context::preview_arm( 'variant', 'variant', true, false ) );
$check( 'refused statistics consent is not measured', ! RWGO_Theme_Context::measurement_allowed( false, true, false ) );
$check(
	'variant bucket is reported as control until a variant experience exists',
	'control' === RWGO_Theme_Context::conversion_arm( 'variant', false, true )
);

$returning = RWGO_Sitewide::bucket_arm( $id, 'rwgo_sitewide', 0.5 );
$check( 'the same visitor id keeps the same arm', $arm === $returning );

if ( $fail > 0 ) {
	echo "{$fail} failed\n";
	exit( 1 );
}
echo "all checks passed\n";
