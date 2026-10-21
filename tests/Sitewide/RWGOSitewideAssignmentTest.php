<?php
/**
 * Site-wide assignment rules. No WordPress request is required.
 *
 * @package ReactWoo_Geo_Optimise
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide.php';

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
