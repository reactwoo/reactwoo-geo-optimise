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

$priorities = RWGO_Sitewide::swapped_content_hook_priorities();
$sitewide_src = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-rwgo-sitewide.php' );
$check(
	'content filter once',
	1 === count( $priorities )
	&& 0 === $priorities[0]
	&& 1 === preg_match_all( "/add_filter\(\s*'the_content',\s*array\(\s*__CLASS__,\s*'filter_swapped_variant_content'\s*\)/", $sitewide_src )
);
$check( 'idle content filter', 'Body' === RWGO_Sitewide::filter_swapped_variant_content( 'Body' ) );

$coverage = array(
	'mapping_mode'          => 'all_published',
	'exclude_system_pages'  => true,
	'exclude_page_ids'      => array( 4 ),
	'future_pages'          => 'skip',
	'coverage_baseline_gmt' => 2000,
	'page_map'              => array(
		array(
			'source_page_id'  => 7,
			'variant_page_id' => 8,
			'source_path'     => '/about/',
			'variant_path'    => '/about-variant-b/',
		),
	),
);
$page = static function ( $id, $slug, $created, $status = 'publish' ) {
	return array(
		'id'          => $id,
		'status'      => $status,
		'type'        => 'page',
		'slug'        => $slug,
		'created_gmt' => $created,
		'variant_of'  => 0,
	);
};
$check( 'checkout excluded', 'excluded' === RWGO_Sitewide::resolve_page_coverage( $page( 12, 'checkout', 1500 ), $coverage ) );
$check( 'cart excluded', 'excluded' === RWGO_Sitewide::resolve_page_coverage( $page( 13, 'cart', 1500 ), $coverage ) );
$check( 'account excluded', 'excluded' === RWGO_Sitewide::resolve_page_coverage( $page( 14, 'my-account', 1500 ), $coverage ) );
$check( 'selected id excluded', 'excluded' === RWGO_Sitewide::resolve_page_coverage( $page( 4, 'legal', 1500 ), $coverage ) );
$check( 'mapped stays mapped', 'mapped' === RWGO_Sitewide::resolve_page_coverage( $page( 7, 'about', 1500 ), $coverage ) );
$check( 'current page eligible', 'eligible' === RWGO_Sitewide::resolve_page_coverage( $page( 9, 'contact', 1500 ), $coverage ) );
$check( 'later page skipped', 'skipped_future' === RWGO_Sitewide::resolve_page_coverage( $page( 21, 'later', 2500 ), $coverage ) );
$include = $coverage;
$include['future_pages'] = 'include';
$check( 'later page included', 'eligible' === RWGO_Sitewide::resolve_page_coverage( $page( 21, 'later', 2500 ), $include ) );
$bulk = RWGO_Sitewide::select_bulk_pages(
	array(
		$page( 9, 'contact', 1500 ),
		$page( 12, 'checkout', 1500 ),
		$page( 21, 'later', 2500 ),
	),
	$coverage,
	'all',
	array()
);
$check( 'bulk all skips exclusions and later pages', 1 === count( $bulk ) && 9 === (int) $bulk[0]['id'] );
$picked = RWGO_Sitewide::select_bulk_pages(
	array(
		$page( 9, 'contact', 1500 ),
		$page( 12, 'checkout', 1500 ),
		$page( 21, 'later', 2500 ),
	),
	$coverage,
	'selected',
	array( 21, 12 )
);
$check( 'bulk selected can add a later page', 1 === count( $picked ) && 21 === (int) $picked[0]['id'] );
$merged = RWGO_Sitewide::append_map_rows(
	$coverage['page_map'],
	array(
		array(
			'source_page_id'  => 9,
			'variant_page_id' => 19,
			'source_path'     => '/contact/',
			'variant_path'    => '/contact-variant-b/',
		),
	)
);
$check( 'map keeps existing pair and appends', 2 === count( $merged ) && 8 === $merged[0]['variant_page_id'] && 19 === $merged[1]['variant_page_id'] );

$check( 'shop slug excluded', RWGO_Sitewide::is_reserved_system_page( 9, 'shop', array() ) );
$check( 'shop id excluded', RWGO_Sitewide::is_reserved_system_page( 9, 'store', array( 9 ) ) );
$check( 'posts page excluded', RWGO_Sitewide::is_reserved_system_page( 42, 'blog', array( 42 ) ) && ! RWGO_Sitewide::is_reserved_system_page( 43, 'blog', array( 42 ) ) );
$shop_cfg = $coverage;
$shop_cfg['page_map'][] = array(
	'source_page_id'  => 9,
	'variant_page_id' => 90,
	'source_path'     => '/shop/',
	'variant_path'    => '/shop-variant-1/',
);
$check(
	'mapped shop stays excluded',
	'excluded' === RWGO_Sitewide::resolve_page_coverage( $page( 9, 'shop', 1500 ), $shop_cfg, array( 'system_ids' => array( 9 ) ) )
);
$failed_dup = RWGO_Sitewide::failed_duplicate_result( 251, 4000, 'invalid_page_template', 'Invalid page template.' );
$check( 'failed duplicate is not mapped', ! $failed_dup['map'] && 4000 === $failed_dup['discard_id'] );
$reuse = RWGO_Sitewide::choose_variant_to_reuse(
	array(
		array( 'id' => 30, 'status' => 'publish' ),
		array( 'id' => 18, 'status' => 'publish' ),
		array( 'id' => 12, 'status' => 'trash' ),
	)
);
$check( 'rerun reuses one variant', 18 === $reuse['keep'] && array( 30 ) === $reuse['discard'] );
$variant_url  = 'https://example.com/shop-variant-1/';
$original_url = 'https://example.com/shop/';
$oembed_html  = 'url=' . rawurlencode( $variant_url );
$oembed_fixed = RWGO_Sitewide::rewrite_variant_url( $oembed_html, $variant_url, $original_url );
$check( 'oembed uses original url', false === strpos( $oembed_fixed, rawurlencode( $variant_url ) ) && false !== strpos( $oembed_fixed, rawurlencode( $original_url ) ) );
$og_html = '<meta property="og:url" content="' . $original_url . '" /><meta property="og:url" content="' . $variant_url . '" />';
$og_one  = RWGO_Sitewide::single_og_url_markup( $og_html, $original_url, 'variant' );
$check(
	'one original og url',
	1 === preg_match_all( '/property=(["\'])og:url\1/i', $og_one )
	&& false === strpos( $og_one, $variant_url )
	&& false !== strpos( $og_one, $original_url )
);

exit( $fail > 0 ? 1 : 0 );
