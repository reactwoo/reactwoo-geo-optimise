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

$check( 'vary once from empty', 'Cookie' === RWGO_Sitewide::single_vary_header( array() ) );
$check(
	'vary once when already sent',
	'Accept-Encoding, Cookie' === RWGO_Sitewide::single_vary_header( array( 'Vary: Accept-Encoding', 'Vary: Cookie', 'Vary: Cookie' ) )
);
$check( 'bot source bypass', RWGO_Sitewide::bot_mapped_response_needs_cache_bypass( 'source' ) );
$check( 'bot variant bypass', RWGO_Sitewide::bot_mapped_response_needs_cache_bypass( 'variant' ) );
$check( 'bot unmapped stays cacheable', ! RWGO_Sitewide::bot_mapped_response_needs_cache_bypass( 'none' ) );

$source_html = "Welcome from the source page\n<!--more-->\nSource only details";
$variant_html = 'Variant headline that replaced the source.';
$leaked = $source_html . '<div id="more-41"></div><span id="more-41"></span><p>' . $variant_html . '</p>';
$clean = RWGO_Sitewide::strip_source_page_leak( $leaked, $source_html, $variant_html, 41 );
$check(
	'variant drops source marker',
	false === strpos( $clean, 'Welcome from the source page' )
	&& false === strpos( $clean, 'Source only details' )
	&& false === strpos( $clean, '<!--more-->' )
	&& false === strpos( $clean, 'more-41' )
	&& false !== strpos( $clean, 'Variant headline that replaced the source.' )
);
$globals = RWGO_Sitewide::content_globals_for_post( 'Just the variant' );
$check( 'variant content globals', isset( $globals['pages'][0] ) && 'Just the variant' === $globals['pages'][0] && 1 === $globals['more'] );

$head = RWGO_Sitewide::head_meta_from_fields(
	'Variant page',
	'Source page excerpt that must not win',
	'Source page body that must not win',
	array(
		'rank_math_title'                => '%title% %sep% %sitename%',
		'rank_math_description'          => 'Variant SEO description',
		'rank_math_facebook_description' => 'Variant Open Graph description',
		'rank_math_twitter_description'  => 'Variant Twitter description',
		'rank_math_snippet_desc'         => 'Variant schema description',
	),
	array(
		'site_name' => 'Example',
		'sep'       => '-',
	)
);
$check(
	'variant head uses rank math',
	'Variant page - Example' === $head['title']
	&& 'Variant SEO description' === $head['description']
	&& 'Variant Open Graph description' === $head['og_description']
	&& 'Variant Twitter description' === $head['twitter_description']
	&& 'Variant schema description' === $head['schema_description']
	&& false === strpos( $head['description'], 'Source page' )
);
$yoast = RWGO_Sitewide::head_meta_from_fields(
	'Variant page',
	'',
	'<p>Variant body sentence for the description.</p>',
	array(
		'_yoast_wpseo_metadesc' => 'Yoast variant description',
	)
);
$check(
	'variant head uses yoast then content',
	'Yoast variant description' === $yoast['description']
	&& 'Variant page' === RWGO_Sitewide::head_meta_from_fields( 'Variant page', '', '<p>Variant body sentence for the description.</p>', array() )['title']
	&& false !== strpos( RWGO_Sitewide::head_meta_from_fields( 'Variant page', '', '<p>Variant body sentence for the description.</p>', array() )['description'], 'Variant body sentence' )
);
$graph = array(
	'@graph' => array(
		array(
			'@type'       => 'WebSite',
			'description' => 'Site tagline',
			'url'         => 'https://example.com/',
		),
		array(
			'@type'       => 'WebPage',
			'name'        => 'Source title',
			'description' => 'Source description',
			'url'         => 'https://example.com/original/',
		),
		array(
			'@type'       => 'Article',
			'headline'    => 'Source title',
			'description' => 'Source description',
			'url'         => 'https://example.com/original/',
		),
	),
);
$updated = RWGO_Sitewide::apply_variant_head_to_json_ld( $graph, 'Variant page', 'Variant schema description' );
$check(
	'json ld description follows variant',
	'Site tagline' === $updated['@graph'][0]['description']
	&& 'https://example.com/' === $updated['@graph'][0]['url']
	&& 'Variant schema description' === $updated['@graph'][1]['description']
	&& 'Variant page' === $updated['@graph'][1]['name']
	&& 'https://example.com/original/' === $updated['@graph'][1]['url']
	&& 'Variant page' === $updated['@graph'][2]['headline']
	&& 'https://example.com/original/' === $updated['@graph'][2]['url']
);
$check( 'idle title filter', 'Source title' === RWGO_Sitewide::filter_seo_title( 'Source title' ) );
$check( 'idle description filter', 'Source description' === RWGO_Sitewide::filter_seo_description( 'Source description' ) );
$check(
	'canonical stays original for variant arm',
	'https://example.com/original/' === RWGO_Sitewide::canonical_url_for_variant( 'https://example.com/variant-page/', 'variant', 'https://example.com/original/' )
	&& 'https://example.com/variant-page/' === RWGO_Sitewide::canonical_url_for_variant( 'https://example.com/variant-page/', 'control', 'https://example.com/original/' )
);

exit( $fail > 0 ? 1 : 0 );
