<?php
/**
 * Runs the site-wide assignment checks without a PHPUnit binary.
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

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

require_once dirname( __DIR__ ) . '/includes/class-rwgo-sitewide.php';

$fail = 0;
$check = static function ( $label, $ok ) use ( &$fail ) {
	if ( ! $ok ) {
		echo "FAIL {$label}\n";
		$fail++;
		return;
	}
	echo "ok {$label}\n";
};

$id  = '0123456789abcdef0123456789abcdef';
$arm = RWGO_Sitewide::bucket_arm( $id, 'rwgo_sitewide', 0.5 );
$check( 'deterministic', $arm === RWGO_Sitewide::bucket_arm( $id, 'rwgo_sitewide', 0.5 ) );
$check( 'weight bounds', 'control' === RWGO_Sitewide::bucket_arm( 'x', 'k', 1 ) && 'variant' === RWGO_Sitewide::bucket_arm( 'x', 'k', 0 ) );

$opts = RWGO_Sitewide::cookie_options( 90, true );
$check( 'cookie path', '/' === $opts['path'] && 'Lax' === $opts['samesite'] && true === $opts['secure'] && true === $opts['httponly'] );
$check( 'cookie lifetime', abs( $opts['expires'] - ( time() + ( 90 * 86400 ) ) ) < 5 );

$fake  = ( 'control' === $arm ) ? 'variant' : 'control';
$fixed = RWGO_Sitewide::repair_cookie( wp_json_encode( array( 'id' => $id, 'arm' => $fake, 'exp' => 'rwgo_sitewide' ) ), 'rwgo_sitewide', 0.5, '' );
$check( 'forged overwritten', $fixed['id'] === $id && $fixed['arm'] === $arm && $fixed['rewritten'] && ! $fixed['assigning'] );

$fresh = RWGO_Sitewide::repair_cookie( 'not-json', 'rwgo_sitewide', 0.5, '' );
$check( 'invalid mints id', (bool) preg_match( '/^[a-f0-9]{32}$/', $fresh['id'] ) && $fresh['arm'] === RWGO_Sitewide::bucket_arm( $fresh['id'], 'rwgo_sitewide', 0.5 ) );

$check( 'googlebot', RWGO_Sitewide::is_known_bot( 'Mozilla/5.0 (compatible; Googlebot/2.1)' ) && ! RWGO_Sitewide::is_known_bot( 'Mozilla/5.0 Chrome/120' ) );
$check( 'qa capability', '' === RWGO_Sitewide::qa_override_allowed( false, 'variant' ) && 'variant' === RWGO_Sitewide::qa_override_allowed( true, 'variant' ) );
$check( 'editor control', 'control' === RWGO_Sitewide::serving_arm( 'variant', true, '' ) && 'variant' === RWGO_Sitewide::serving_arm( 'control', true, 'variant' ) );

foreach ( array( 'admin', 'ajax', 'cron', 'rest', 'cli', 'preview', 'json' ) as $flag ) {
	$check( "skip {$flag}", RWGO_Sitewide::context_skips_assignment( array( $flag => true ) ) );
}
$check( 'html context assigns', ! RWGO_Sitewide::context_skips_assignment( array() ) );

$valid = wp_json_encode( array( 'id' => $id, 'arm' => $arm, 'exp' => 'rwgo_sitewide' ) );
$kept  = RWGO_Sitewide::repair_cookie( $valid, 'rwgo_sitewide', 0.5, '' );
$check( 'returning cookie kept', $kept['id'] === $id && $kept['arm'] === $arm && ! $kept['assigning'] && ! $kept['rewritten'] );
$check(
	'returning visitor uncacheable',
	RWGO_Sitewide::served_arm_needs_cache_bypass( $kept['assigning'], $kept['rewritten'], $kept['arm'], false )
);
$check( 'source with cookie bypasses cache', RWGO_Sitewide::mapped_response_needs_cache_bypass( 'source', true ) );
$check( 'variant url bypasses cache', RWGO_Sitewide::mapped_response_needs_cache_bypass( 'variant', false ) );
$check( 'unmapped stays cacheable', ! RWGO_Sitewide::mapped_response_needs_cache_bypass( 'none', true ) );
$headers = RWGO_Sitewide::cache_bypass_headers();
$check(
	'cache headers',
	isset( $headers['Cache-Control'], $headers['Vary'] )
	&& false !== strpos( $headers['Cache-Control'], 'private' )
	&& false !== strpos( $headers['Cache-Control'], 'no-store' )
	&& 'Cookie' === $headers['Vary']
);

exit( $fail > 0 ? 1 : 0 );
