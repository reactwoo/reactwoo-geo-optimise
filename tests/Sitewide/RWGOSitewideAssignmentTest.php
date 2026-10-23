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
}
