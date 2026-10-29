<?php
/**
 * Site-wide assignment rules. No WordPress request is required.
 *
 * @package ReactWoo_Geo_Optimise
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-page-duplicator.php';

/**
 * @covers RWGO_Sitewide
 */
class RWGOSitewideAssignmentTest extends PHPUnit\Framework\TestCase {

	public function test_bucket_is_deterministic(): void {
		$arm = RWGO_Sitewide::bucket_arm( '0123456789abcdef0123456789abcdef', 'rwgo_sitewide', 0.5 );
		$this->assertContains( $arm, array( 'control', 'variant' ) );
		$this->assertSame( $arm, RWGO_Sitewide::bucket_arm( '0123456789abcdef0123456789abcdef', 'rwgo_sitewide', 0.5 ) );
		$this->assertSame( 'control', RWGO_Sitewide::bucket_arm( 'anything', 'rwgo_sitewide', 1 ) );
		$this->assertSame( 'variant', RWGO_Sitewide::bucket_arm( 'anything', 'rwgo_sitewide', 0 ) );
	}

	public function test_cookie_options(): void {
		$opts = RWGO_Sitewide::cookie_options( 90, true );
		$this->assertSame( '/', $opts['path'] );
		$this->assertSame( 'Lax', $opts['samesite'] );
		$this->assertTrue( $opts['secure'] );
		$this->assertTrue( $opts['httponly'] );
		$this->assertEqualsWithDelta( time() + ( 90 * 86400 ), $opts['expires'], 5 );
	}

	public function test_forged_cookie_is_overwritten(): void {
		$id   = '0123456789abcdef0123456789abcdef';
		$arm  = RWGO_Sitewide::bucket_arm( $id, 'rwgo_sitewide', 0.5 );
		$fake = ( 'control' === $arm ) ? 'variant' : 'control';
		$raw  = wp_json_encode(
			array(
				'id'  => $id,
				'arm' => $fake,
				'exp' => 'rwgo_sitewide',
			)
		);
		$fixed = RWGO_Sitewide::repair_cookie( $raw, 'rwgo_sitewide', 0.5, '' );
		$this->assertSame( $id, $fixed['id'] );
		$this->assertSame( $arm, $fixed['arm'] );
		$this->assertTrue( $fixed['rewritten'] );
		$this->assertFalse( $fixed['assigning'] );
	}

	public function test_invalid_cookie_mints_a_new_id(): void {
		$fixed = RWGO_Sitewide::repair_cookie( 'not-json', 'rwgo_sitewide', 0.5, '' );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $fixed['id'] );
		$this->assertSame( RWGO_Sitewide::bucket_arm( $fixed['id'], 'rwgo_sitewide', 0.5 ), $fixed['arm'] );
		$this->assertTrue( $fixed['assigning'] );
	}

	public function test_bots_are_detected(): void {
		$this->assertTrue( RWGO_Sitewide::is_known_bot( 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ) );
		$this->assertFalse( RWGO_Sitewide::is_known_bot( 'Mozilla/5.0 Chrome/120.0.0.0' ) );
	}

	public function test_qa_override_requires_capability(): void {
		$this->assertSame( '', RWGO_Sitewide::qa_override_allowed( false, 'variant' ) );
		$this->assertSame( '', RWGO_Sitewide::qa_override_allowed( true, 'nope' ) );
		$this->assertSame( 'variant', RWGO_Sitewide::qa_override_allowed( true, 'variant' ) );
		$this->assertSame( 'control', RWGO_Sitewide::serving_arm( 'variant', true, '' ) );
		$this->assertSame( 'variant', RWGO_Sitewide::serving_arm( 'control', true, 'variant' ) );
	}

	public function test_no_assignment_in_non_html_contexts(): void {
		foreach ( array( 'admin', 'ajax', 'cron', 'rest', 'cli', 'preview', 'json' ) as $flag ) {
			$this->assertTrue( RWGO_Sitewide::context_skips_assignment( array( $flag => true ) ) );
		}
		$this->assertFalse( RWGO_Sitewide::context_skips_assignment( array() ) );
	}

	public function test_returning_visitor_with_assignment_cookie_is_uncacheable(): void {
		$id  = '0123456789abcdef0123456789abcdef';
		$arm = RWGO_Sitewide::bucket_arm( $id, 'rwgo_sitewide', 0.5 );
		$raw = wp_json_encode(
			array(
				'id'  => $id,
				'arm' => $arm,
				'exp' => 'rwgo_sitewide',
			)
		);
		$fixed = RWGO_Sitewide::repair_cookie( $raw, 'rwgo_sitewide', 0.5, '' );
		$this->assertFalse( $fixed['assigning'] );
		$this->assertFalse( $fixed['rewritten'] );
		$this->assertTrue(
			RWGO_Sitewide::served_arm_needs_cache_bypass( $fixed['assigning'], $fixed['rewritten'], $fixed['arm'], false )
		);
		$this->assertTrue( RWGO_Sitewide::served_arm_needs_cache_bypass( true, false, 'control', false ) );
		$this->assertTrue( RWGO_Sitewide::mapped_response_needs_cache_bypass( 'source', true ) );
		$this->assertTrue( RWGO_Sitewide::mapped_response_needs_cache_bypass( 'variant', false ) );
		$this->assertFalse( RWGO_Sitewide::mapped_response_needs_cache_bypass( 'none', true ) );
		$this->assertFalse( RWGO_Sitewide::mapped_response_needs_cache_bypass( 'source', false ) );
		$headers = RWGO_Sitewide::cache_bypass_headers();
		$this->assertSame( 'private, no-store, max-age=0', $headers['Cache-Control'] );
		$this->assertSame( 'Cookie', $headers['Vary'] );
		$this->assertStringContainsString( 'no-store', $headers['Cache-Control'] );
		$this->assertStringContainsString( 'private', $headers['Cache-Control'] );
	}

	public function test_vary_cookie_is_emitted_once(): void {
		$this->assertSame( 'Cookie', RWGO_Sitewide::single_vary_header( array() ) );
		$this->assertSame(
			'Accept-Encoding, Cookie',
			RWGO_Sitewide::single_vary_header( array( 'Vary: Accept-Encoding' ) )
		);
		$this->assertSame(
			'Cookie',
			RWGO_Sitewide::single_vary_header( array( 'Vary: Cookie', 'Vary: Cookie' ) )
		);
		$this->assertSame(
			'Accept-Encoding, Cookie',
			RWGO_Sitewide::single_vary_header(
				array(
					'Cache-Control: private, no-store',
					'Vary: Accept-Encoding, Cookie',
					'Vary: cookie',
				)
			)
		);
	}

	public function test_bots_on_mapped_pages_bypass_cache(): void {
		$this->assertTrue( RWGO_Sitewide::bot_mapped_response_needs_cache_bypass( 'source' ) );
		$this->assertTrue( RWGO_Sitewide::bot_mapped_response_needs_cache_bypass( 'variant' ) );
		$this->assertFalse( RWGO_Sitewide::bot_mapped_response_needs_cache_bypass( 'none' ) );
	}

	public function test_variant_html_drops_source_more_marker_and_teaser(): void {
		$source  = "Welcome from the source page\n<!--more-->\nSource only details";
		$variant = 'Variant headline that replaced the source.';
		$html    = $source . '<div id="more-41"></div><span id="more-41"></span><p>' . $variant . '</p><a href="https://example.com/home/#more-41">more</a>';
		$clean   = RWGO_Sitewide::strip_source_page_leak( $html, $source, $variant, 41 );
		$this->assertStringNotContainsString( 'Welcome from the source page', $clean );
		$this->assertStringNotContainsString( 'Source only details', $clean );
		$this->assertStringNotContainsString( '<!--more-->', $clean );
		$this->assertStringNotContainsString( 'more-41', $clean );
		$this->assertStringContainsString( 'Variant headline that replaced the source.', $clean );
		$this->assertStringContainsString( 'id="more-88"', RWGO_Sitewide::strip_source_page_leak( '<span id="more-88"></span>', $source, $variant, 41 ) );
		$expanded = 'Welcome from the source page<div id="more-41"></div>Source only details<p>' . $variant . '</p>';
		$expanded_clean = RWGO_Sitewide::strip_source_page_leak( $expanded, $source, $variant, 41 );
		$this->assertStringNotContainsString( 'Welcome from the source page', $expanded_clean );
		$this->assertStringNotContainsString( 'Source only details', $expanded_clean );
		$this->assertStringNotContainsString( 'more-41', $expanded_clean );
		$this->assertStringContainsString( $variant, $expanded_clean );
		$kept = RWGO_Sitewide::strip_source_page_leak( '<p>Welcome from the source page</p><p>' . $variant . '</p>', $source, $variant, 41 );
		$this->assertStringContainsString( 'Welcome from the source page', $kept );
	}

	public function test_variant_keeps_its_own_more_marker(): void {
		$shared = "Shared intro\n<!--more-->\nShared rest";
		$html   = $shared . '<span id="more-9"></span>';
		$clean  = RWGO_Sitewide::strip_source_page_leak( $html, $shared, $shared, 4 );
		$this->assertStringContainsString( 'Shared intro', $clean );
		$this->assertStringContainsString( '<!--more-->', $clean );
		$this->assertStringContainsString( 'id="more-9"', $clean );
		$this->assertStringNotContainsString( 'more-4', $clean );
	}

	public function test_variant_content_globals_follow_the_variant_document(): void {
		$globals = RWGO_Sitewide::content_globals_for_post( "Variant only\n<!--nextpage-->\nSecond" );
		$this->assertSame( array( 'Variant only', 'Second' ), $globals['pages'] );
		$this->assertSame( 1, $globals['more'] );
		$this->assertSame( 2, $globals['numpages'] );
		$single = RWGO_Sitewide::content_globals_for_post( 'Just the variant' );
		$this->assertSame( array( 'Just the variant' ), $single['pages'] );
		$this->assertSame( 0, $single['multipage'] );
	}

	public function test_elementor_render_cache_is_not_copied_onto_a_variant(): void {
		$keys = RWGO_Page_Duplicator::elementor_generated_meta_keys();
		$this->assertContains( '_elementor_element_cache', $keys );
		$this->assertContains( '_elementor_css', $keys );
	}

	public function test_path_role_ignores_empty_paths(): void {
		$map = array(
			array(
				'source_page_id'  => 1,
				'variant_page_id' => 2,
				'source_path'     => '/',
				'variant_path'    => '/new-home/',
			),
			array(
				'source_page_id'  => 3,
				'variant_page_id' => 4,
				'source_path'     => '/contact/',
				'variant_path'    => '',
			),
		);
		$this->assertSame( 'source', RWGO_Sitewide::path_role( '/', $map ) );
		$this->assertSame( 'variant', RWGO_Sitewide::path_role( '/new-home/', $map ) );
		$this->assertSame( 'source', RWGO_Sitewide::path_role( '/contact', $map ) );
		$this->assertSame( 'none', RWGO_Sitewide::path_role( '/about/', $map ) );
	}

	public function test_variant_head_uses_rank_math_title_and_description(): void {
		$meta = RWGO_Sitewide::head_meta_from_fields(
			'Variant page',
			'Source page excerpt that must not win',
			'Source page body that must not win',
			array(
				'rank_math_title'                => '%title% %sep% %sitename%',
				'rank_math_description'          => 'Variant SEO description',
				'rank_math_facebook_title'       => 'Variant Open Graph title',
				'rank_math_facebook_description' => 'Variant Open Graph description',
				'rank_math_twitter_title'        => 'Variant Twitter title',
				'rank_math_twitter_description'  => 'Variant Twitter description',
				'rank_math_snippet_desc'         => 'Variant schema description',
				'_yoast_wpseo_title'             => 'Yoast title that must not win',
				'_yoast_wpseo_metadesc'          => 'Yoast description that must not win',
			),
			array(
				'site_name' => 'Example',
				'sep'       => '-',
			)
		);

		$this->assertSame( 'Variant page - Example', $meta['title'] );
		$this->assertTrue( $meta['has_custom_title'] );
		$this->assertSame( 'Variant SEO description', $meta['description'] );
		$this->assertSame( 'Variant schema description', $meta['schema_description'] );
		$this->assertSame( 'Variant Open Graph title', $meta['og_title'] );
		$this->assertSame( 'Variant Open Graph description', $meta['og_description'] );
		$this->assertSame( 'Variant Twitter title', $meta['twitter_title'] );
		$this->assertSame( 'Variant Twitter description', $meta['twitter_description'] );
		$this->assertStringNotContainsString( 'Source page', $meta['description'] );
		$this->assertStringNotContainsString( 'Yoast', $meta['title'] );
	}

	public function test_variant_head_falls_back_to_yoast_then_excerpt(): void {
		$yoast = RWGO_Sitewide::head_meta_from_fields(
			'Variant page',
			'Variant excerpt',
			'<p>Variant body</p>',
			array(
				'rank_math_title'       => '',
				'_yoast_wpseo_title'    => '%%title%% %%sep%% %%sitename%%',
				'_yoast_wpseo_metadesc' => 'Yoast variant description',
				'_yoast_wpseo_opengraph-description' => 'Yoast OG description',
			),
			array(
				'site_name' => 'Example',
				'sep'       => '|',
			)
		);
		$this->assertSame( 'Variant page | Example', $yoast['title'] );
		$this->assertSame( 'Yoast variant description', $yoast['description'] );
		$this->assertSame( 'Yoast OG description', $yoast['og_description'] );
		$this->assertSame( 'Yoast OG description', $yoast['twitter_description'] );

		$excerpt = RWGO_Sitewide::head_meta_from_fields(
			'Variant page',
			'Variant excerpt',
			'<p>Source body should not be the description</p>',
			array(),
			array( 'site_name' => 'Example', 'sep' => '-' )
		);
		$this->assertSame( 'Variant page - Example', $excerpt['title'] );
		$this->assertFalse( $excerpt['has_custom_title'] );
		$this->assertSame( 'Variant excerpt', $excerpt['description'] );
		$this->assertSame( 'Variant excerpt', $excerpt['schema_description'] );
		$this->assertStringNotContainsString( 'Source body', $excerpt['description'] );

		$long    = trim( str_repeat( 'variantword ', 40 ) );
		$content = RWGO_Sitewide::head_meta_from_fields( 'Variant page', '', '<p>' . $long . '</p>', array() );
		$this->assertSame( 'Variant page', $content['title'] );
		$this->assertLessThanOrEqual( 160, strlen( $content['description'] ) );
		$this->assertStringStartsWith( 'variantword', $content['description'] );
		$this->assertStringNotContainsString( '<p>', $content['description'] );
	}

	public function test_json_ld_description_follows_the_variant_and_keeps_urls(): void {
		$graph = array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type'       => 'WebSite',
					'name'        => 'Example',
					'description' => 'Site tagline',
					'url'         => 'https://example.com/',
				),
				array(
					'@type'       => 'WebPage',
					'name'        => 'Source title',
					'description' => 'Source description',
					'url'         => 'https://example.com/original/',
					'@id'         => 'https://example.com/original/#webpage',
				),
				array(
					'@type'       => 'Article',
					'headline'    => 'Source title',
					'name'        => 'Source title',
					'description' => 'Source description',
					'url'         => 'https://example.com/original/',
					'publisher'   => array(
						'@type' => 'Organization',
						'name'  => 'Example',
					),
				),
				array(
					'@type'       => 'Product',
					'name'        => 'Source product name',
					'description' => 'Source product description',
				),
				array(
					'@type'           => 'BreadcrumbList',
					'itemListElement' => array(
						array(
							'@type'    => 'ListItem',
							'name'     => 'Home',
							'item'     => 'https://example.com/',
						),
					),
				),
			),
		);

		$updated = RWGO_Sitewide::apply_variant_head_to_json_ld( $graph, 'Variant page - Example', 'Variant schema description' );
		$nodes   = $updated['@graph'];
		$this->assertSame( 'Site tagline', $nodes[0]['description'] );
		$this->assertSame( 'Example', $nodes[0]['name'] );
		$this->assertSame( 'https://example.com/', $nodes[0]['url'] );
		$this->assertSame( 'Variant page - Example', $nodes[1]['name'] );
		$this->assertSame( 'Variant schema description', $nodes[1]['description'] );
		$this->assertSame( 'https://example.com/original/', $nodes[1]['url'] );
		$this->assertSame( 'https://example.com/original/#webpage', $nodes[1]['@id'] );
		$this->assertSame( 'Variant page - Example', $nodes[2]['headline'] );
		$this->assertSame( 'Variant page - Example', $nodes[2]['name'] );
		$this->assertSame( 'Variant schema description', $nodes[2]['description'] );
		$this->assertSame( 'https://example.com/original/', $nodes[2]['url'] );
		$this->assertSame( 'Example', $nodes[2]['publisher']['name'] );
		$this->assertSame( 'Source product name', $nodes[3]['name'] );
		$this->assertSame( 'Variant schema description', $nodes[3]['description'] );
		$this->assertSame( 'Home', $nodes[4]['itemListElement'][0]['name'] );
		$this->assertSame( 'https://example.com/', $nodes[4]['itemListElement'][0]['item'] );

		$map = array(
			'WebPage' => array(
				'@type'       => 'WebPage',
				'description' => 'Source description',
				'url'         => 'https://example.com/original/',
			),
		);
		$mapped = RWGO_Sitewide::apply_variant_head_to_json_ld( $map, 'Variant page', 'Variant schema description' );
		$this->assertSame( 'Variant schema description', $mapped['WebPage']['description'] );
		$this->assertSame( 'https://example.com/original/', $mapped['WebPage']['url'] );
	}

	public function test_head_filters_stay_idle_until_a_variant_is_served(): void {
		$this->assertSame( 'Source title', RWGO_Sitewide::filter_seo_title( 'Source title' ) );
		$this->assertSame( 'Source description', RWGO_Sitewide::filter_seo_description( 'Source description' ) );
		$this->assertSame( 'Source Open Graph title', RWGO_Sitewide::filter_og_title( 'Source Open Graph title' ) );
		$this->assertSame( 'Source Open Graph description', RWGO_Sitewide::filter_og_description( 'Source Open Graph description' ) );
		$this->assertSame( 'Source Twitter title', RWGO_Sitewide::filter_twitter_title( 'Source Twitter title' ) );
		$this->assertSame( 'Source Twitter description', RWGO_Sitewide::filter_twitter_description( 'Source Twitter description' ) );
		$this->assertSame( 'Source excerpt', RWGO_Sitewide::filter_head_excerpt( 'Source excerpt' ) );
		$parts = array(
			'title' => 'Source title',
			'site'  => 'Example',
		);
		$this->assertSame( $parts, RWGO_Sitewide::filter_document_title_parts( $parts ) );
		$graph = array(
			array(
				'@type'       => 'WebPage',
				'description' => 'Source description',
				'url'         => 'https://example.com/original/',
			),
		);
		$this->assertSame( $graph, RWGO_Sitewide::filter_json_ld_graph( $graph ) );
		$this->assertSame(
			'https://example.com/original/',
			RWGO_Sitewide::filter_canonical_url( 'https://example.com/original/' )
		);
	}

	public function test_canonical_and_og_url_stay_on_the_original_page(): void {
		$this->assertSame(
			'https://example.com/original/',
			RWGO_Sitewide::canonical_url_for_variant( 'https://example.com/variant-page/', 'variant', 'https://example.com/original/' )
		);
		$this->assertSame(
			'https://example.com/variant-page/',
			RWGO_Sitewide::canonical_url_for_variant( 'https://example.com/variant-page/', 'control', 'https://example.com/original/' )
		);
		$this->assertSame(
			'https://example.com/control/',
			RWGO_Sitewide::canonical_url_for_variant( 'https://example.com/control/', 'variant', '' )
		);
		$parts = RWGO_Sitewide::document_title_parts_for_variant(
			array(
				'title'   => 'Source title',
				'site'    => 'Example',
				'tagline' => 'Tag',
				'page'    => '2',
			),
			array( 'title' => 'Variant page - Example' )
		);
		$this->assertSame( 'Variant page - Example', $parts['title'] );
		$this->assertSame( '2', $parts['page'] );
		$this->assertArrayNotHasKey( 'site', $parts );
		$this->assertArrayNotHasKey( 'tagline', $parts );
	}

	public function test_swapped_content_filter_is_registered_once(): void {
		$priorities = RWGO_Sitewide::swapped_content_hook_priorities();
		$this->assertCount( 1, $priorities );
		$this->assertSame( 0, $priorities[0] );
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide.php' );
		$this->assertSame(
			1,
			preg_match_all( "/add_filter\(\s*'the_content',\s*array\(\s*__CLASS__,\s*'filter_swapped_variant_content'\s*\)/", $source )
		);
		$this->assertSame( 'Body', RWGO_Sitewide::filter_swapped_variant_content( 'Body' ) );
		$source_html  = "Welcome from the source page\n<!--more-->\nSource only details";
		$variant_html = 'Variant headline that replaced the source.';
		$once         = RWGO_Sitewide::strip_source_page_leak( $source_html . '<p>' . $variant_html . '</p>', $source_html, $variant_html, 41 );
		$this->assertSame( $once, RWGO_Sitewide::strip_source_page_leak( $once, $source_html, $variant_html, 41 ) );
	}

	public function test_all_published_coverage_excludes_system_pages_and_selected_ids(): void {
		$cfg = array(
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
		$context = array( 'system_ids' => array( 90 ) );
		$this->assertSame( 'excluded', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 12, 'checkout', 1500 ), $cfg, $context ) );
		$this->assertSame( 'excluded', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 13, 'cart', 1500 ), $cfg, $context ) );
		$this->assertSame( 'excluded', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 14, 'my-account', 1500 ), $cfg, $context ) );
		$this->assertSame( 'excluded', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 90, 'billing', 1500 ), $cfg, $context ) );
		$this->assertSame( 'excluded', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 4, 'legal', 1500 ), $cfg, $context ) );
		$this->assertSame( 'mapped', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 7, 'about', 1500 ), $cfg, $context ) );
		$this->assertSame( 'ineligible', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 8, 'about-variant-b', 1500 ), $cfg, $context ) );
		$this->assertSame( 'eligible', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 9, 'contact', 1500 ), $cfg, $context ) );
		$this->assertSame( 'skipped_future', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 21, 'later', 2500 ), $cfg, $context ) );
		$include                 = $cfg;
		$include['future_pages'] = 'include';
		$this->assertSame( 'eligible', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 21, 'later', 2500 ), $include, $context ) );
		$this->assertSame( 'ineligible', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 30, 'draft-page', 1500, 'draft' ), $cfg, $context ) );
		$this->assertSame( 'unmapped', RWGO_Sitewide::resolve_page_coverage( $this->page_record( 9, 'contact', 1500 ), array( 'mapping_mode' => 'explicit' ), $context ) );
	}

	public function test_bulk_selection_copies_selected_or_eligible_pages_only(): void {
		$pages = array();
		for ( $i = 1; $i <= 40; $i++ ) {
			$slug    = 'page-' . $i;
			$created = 1000 + $i;
			if ( 12 === $i ) {
				$slug = 'checkout';
			}
			if ( 30 === $i ) {
				$created = 5000;
			}
			$pages[] = $this->page_record( $i, $slug, $created );
		}
		$cfg     = array(
			'mapping_mode'          => 'all_published',
			'exclude_system_pages'  => true,
			'exclude_page_ids'      => array( 4 ),
			'future_pages'          => 'skip',
			'coverage_baseline_gmt' => 2000,
			'page_map'              => array(
				array(
					'source_page_id'  => 7,
					'variant_page_id' => 8,
					'source_path'     => '/a/',
					'variant_path'    => '/b/',
				),
			),
		);
		$all     = RWGO_Sitewide::select_bulk_pages( $pages, $cfg, 'all', array() );
		$all_ids = array();
		foreach ( $all as $page ) {
			$all_ids[] = (int) $page['id'];
		}
		$this->assertNotContains( 12, $all_ids );
		$this->assertNotContains( 4, $all_ids );
		$this->assertNotContains( 7, $all_ids );
		$this->assertNotContains( 8, $all_ids );
		$this->assertNotContains( 30, $all_ids );
		$this->assertContains( 9, $all_ids );
		$this->assertLessThan( count( $pages ), count( $all_ids ) );

		$picked     = RWGO_Sitewide::select_bulk_pages( $pages, $cfg, 'selected', array( 30, 12, 9 ) );
		$picked_ids = array();
		foreach ( $picked as $page ) {
			$picked_ids[] = (int) $page['id'];
		}
		$this->assertSame( array( 9, 30 ), $picked_ids );

		$explicit = array( 'mapping_mode' => 'explicit', 'exclude_system_pages' => true );
		$copied   = RWGO_Sitewide::select_bulk_pages(
			array(
				$this->page_record( 3, 'home', 1000 ),
				$this->page_record( 12, 'cart', 1000 ),
			),
			$explicit,
			'all',
			array()
		);
		$this->assertSame( 3, (int) $copied[0]['id'] );
		$this->assertCount( 1, $copied );
	}

	public function test_page_map_appends_pairs_without_replacing_existing_sources(): void {
		$map = RWGO_Sitewide::append_map_rows(
			array(
				array(
					'source_page_id'  => 5,
					'variant_page_id' => 6,
					'source_path'     => '/a/',
					'variant_path'    => '/a-variant-b/',
				),
			),
			array(
				array(
					'source_page_id'  => 5,
					'variant_page_id' => 99,
					'source_path'     => '/a/',
					'variant_path'    => '/other/',
				),
				array(
					'source_page_id'  => 10,
					'variant_page_id' => 11,
					'source_path'     => '/b/',
					'variant_path'    => '/b-variant-b/',
				),
			)
		);
		$this->assertCount( 2, $map );
		$this->assertSame( 6, $map[0]['variant_page_id'] );
		$this->assertSame( 11, $map[1]['variant_page_id'] );
		$dropped = RWGO_Sitewide::drop_map_sources( $map, array( 5 ) );
		$this->assertCount( 1, $dropped );
		$this->assertSame( 10, $dropped[0]['source_page_id'] );
	}

	public function test_active_config_still_requires_a_page_map(): void {
		update_option(
			RWGO_Sitewide::OPTION,
			array(
				'test_type'    => 'sitewide',
				'status'       => 'active',
				'mapping_mode' => 'all_published',
				'page_map'     => array(),
			)
		);
		$this->assertNull( RWGO_Sitewide::active_config() );
		update_option(
			RWGO_Sitewide::OPTION,
			array(
				'test_type'    => 'sitewide',
				'status'       => 'active',
				'mapping_mode' => 'all_published',
				'page_map'     => array(
					array(
						'source_page_id'  => 5,
						'variant_page_id' => 9,
						'source_path'     => '/about/',
						'variant_path'    => '/about-variant-b/',
					),
				),
			)
		);
		$this->assertIsArray( RWGO_Sitewide::active_config() );
	}

	public function test_shop_terms_and_posts_page_are_reserved(): void {
		$this->assertContains( 'shop', RWGO_Sitewide::woocommerce_page_id_keys() );
		$this->assertContains( 'terms', RWGO_Sitewide::woocommerce_page_id_keys() );
		$this->assertContains( 'refund_returns', RWGO_Sitewide::woocommerce_page_id_keys() );
		$this->assertTrue( RWGO_Sitewide::is_reserved_system_page( 9, 'shop', array() ) );
		$this->assertTrue( RWGO_Sitewide::is_reserved_system_page( 9, 'store', array( 9 ) ) );
		$this->assertTrue( RWGO_Sitewide::is_reserved_system_page( 42, 'blog', array( 42 ) ) );
		$this->assertFalse( RWGO_Sitewide::is_reserved_system_page( 43, 'blog', array( 42 ) ) );
		$this->assertTrue( RWGO_Sitewide::is_reserved_system_page( 8, 'refund_returns', array() ) );
		$cfg = array(
			'mapping_mode'         => 'all_published',
			'exclude_system_pages' => true,
			'page_map'             => array(
				array(
					'source_page_id'  => 9,
					'variant_page_id' => 90,
					'source_path'     => '/shop/',
					'variant_path'    => '/shop-variant-1/',
				),
			),
		);
		$this->assertSame(
			'excluded',
			RWGO_Sitewide::resolve_page_coverage( $this->page_record( 9, 'shop', 1000 ), $cfg, array( 'system_ids' => array( 9 ) ) )
		);
	}

	public function test_failed_duplicate_is_not_mapped_and_rerun_reuses_one_variant(): void {
		$failed = RWGO_Sitewide::failed_duplicate_result( 251, 4000, 'invalid_page_template', 'Invalid page template.' );
		$this->assertFalse( $failed['map'] );
		$this->assertSame( 4000, $failed['discard_id'] );
		$this->assertSame( 'invalid_page_template', $failed['failure']['code'] );
		$this->assertSame( 'default', RWGO_Sitewide::page_template_for_publish( 'missing-template.php', array( 'full-width.php' => 'Full width' ) ) );
		$this->assertSame( 'full-width.php', RWGO_Sitewide::page_template_for_publish( 'full-width.php', array( 'full-width.php' => 'Full width' ) ) );
		$choice = RWGO_Sitewide::choose_variant_to_reuse(
			array(
				array( 'id' => 12, 'status' => 'trash' ),
				array( 'id' => 30, 'status' => 'publish' ),
				array( 'id' => 18, 'status' => 'publish' ),
				array( 'id' => 40, 'status' => 'draft' ),
			)
		);
		$this->assertSame( 18, $choice['keep'] );
		$this->assertSame( array( 30, 40 ), $choice['discard'] );
		$merged = RWGO_Sitewide::merge_bulk_failures(
			array(),
			array(
				array( 'source_id' => 251, 'code' => 'invalid_page_template', 'message' => 'Invalid page template.' ),
				array( 'source_id' => 2965, 'code' => 'invalid_page_template', 'message' => 'Invalid page template.' ),
			)
		);
		$this->assertCount( 2, $merged );
		$this->assertSame( 2965, $merged[1]['source_id'] );
	}

	public function test_variant_url_is_removed_from_oembed_and_duplicate_og_url(): void {
		$variant  = 'https://example.com/shop-variant-1/';
		$original = 'https://example.com/shop/';
		$oembed   = '<link rel="alternate" type="application/json+oembed" href="https://example.com/wp-json/oembed/1.0/embed?url=' . rawurlencode( $variant ) . '&#038;format=json" />';
		$rewritten = RWGO_Sitewide::rewrite_variant_url( $oembed, $variant, $original );
		$this->assertStringNotContainsString( rawurlencode( $variant ), $rewritten );
		$this->assertStringContainsString( rawurlencode( $original ), $rewritten );
		$head = '<meta property="og:url" content="' . $original . '" />' . "\n"
			. '<meta property="og:url" content="' . $variant . '" />' . "\n"
			. '<meta property="og:title" content="Shop" />';
		$single = RWGO_Sitewide::single_og_url_markup( $head, $original, 'variant' );
		$this->assertSame( 1, preg_match_all( '/property=(["\'])og:url\1/i', $single ) );
		$this->assertStringContainsString( $original, $single );
		$this->assertStringNotContainsString( $variant, $single );
		$this->assertStringContainsString( 'og:title', $single );
		$this->assertSame( $head, RWGO_Sitewide::single_og_url_markup( $head, $original, 'control' ) );
	}

	/**
	 * @param int    $id      Page id.
	 * @param string $slug    Slug.
	 * @param int    $created Created GMT unix time.
	 * @param string $status  Post status.
	 * @return array<string, mixed>
	 */
	private function page_record( $id, $slug, $created, $status = 'publish' ) {
		return array(
			'id'          => (int) $id,
			'status'      => $status,
			'type'        => 'page',
			'slug'        => $slug,
			'created_gmt' => (int) $created,
			'variant_of'  => 0,
		);
	}
}
