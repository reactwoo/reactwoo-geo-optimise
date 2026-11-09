<?php
/**
 * Token requests name the Optimise package, and only a covering token is valid.
 *
 * @package ReactWoo_Geo_Optimise
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-platform-client.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-settings.php';

/**
 * @covers RWGO_Platform_Client
 * @covers RWGO_Settings
 */
class RWGOLicenseEntitlementTest extends PHPUnit\Framework\TestCase {

	public function test_login_body_sends_the_optimise_package_type(): void {
		$body = RWGO_Platform_Client::auth_login_body( 'OPT-KEY', 'shop.example' );
		$this->assertSame( 'reactwoo-geo-optimise', $body['package_type'] );
		$this->assertSame( 'reactwoo-geo-optimise', $body['product_slug'] );
		$this->assertSame( 'reactwoo-geo-optimise', $body['catalog_slug'] );
		$this->assertSame( 'OPT-KEY', $body['license_key'] );
	}

	public function test_settings_filter_adds_package_type_only_for_the_optimise_key(): void {
		$GLOBALS['rwga_test_options']['rwgo_settings'] = array(
			'reactwoo_license_key' => 'OPT-KEY',
		);
		$ours = RWGO_Settings::filter_auth_login_body(
			array(
				'license_key' => 'OPT-KEY',
				'domain'      => 'shop.example',
			),
			'OPT-KEY',
			'shop.example'
		);
		$this->assertSame( 'reactwoo-geo-optimise', $ours['package_type'] );
		$this->assertSame( 'reactwoo-geo-optimise', $ours['product_slug'] );

		$core = RWGO_Settings::filter_auth_login_body(
			array(
				'license_key'  => 'PRO-KEY',
				'product_slug' => 'reactwoo-geocore-pro',
			),
			'PRO-KEY',
			'shop.example'
		);
		$this->assertArrayNotHasKey( 'package_type', $core );
		$this->assertSame( 'reactwoo-geocore-pro', $core['product_slug'] );
	}

	public function test_package_claim_accepts_optimise_and_the_legacy_geo_ai_alias(): void {
		$this->assertTrue( RWGO_Platform_Client::claims_cover_optimise( array( 'packageType' => 'reactwoo-geo-optimise' ) ) );
		$this->assertTrue( RWGO_Platform_Client::claims_cover_optimise( array( 'package_type' => 'reactwoo-geo-optimise' ) ) );
		$this->assertTrue( RWGO_Platform_Client::claims_cover_optimise( array( 'packageType' => 'reactwoo-geo-ai' ) ) );
	}

	public function test_echoed_product_slug_does_not_make_a_pro_key_valid(): void {
		$this->assertFalse(
			RWGO_Platform_Client::claims_cover_optimise(
				array(
					'packageType'  => 'reactwoo-geocore-pro',
					'product_slug' => 'reactwoo-geo-optimise',
					'catalog_slug' => 'reactwoo-geo-optimise',
				)
			)
		);
		$this->assertFalse( RWGO_Platform_Client::token_covers_optimise( 'not-a-jwt' ) );
	}

	public function test_decision_cloud_coverage_comes_from_product_slugs(): void {
		$this->assertTrue(
			RWGO_Platform_Client::claims_cover_optimise(
				array(
					'packageType'   => 'reactwoo-decision-cloud',
					'plan_code'     => 'growth',
					'product_slugs' => array( 'reactwoo-geocore-pro', 'reactwoo-geo-commerce', 'reactwoo-geo-optimise' ),
				)
			)
		);
		$this->assertTrue(
			RWGO_Platform_Client::claims_cover_optimise(
				array(
					'packageType'  => 'reactwoo-decision-cloud',
					'plan_code'    => 'scale',
					'productSlugs' => 'reactwoo-geocore-pro, reactwoo-geo-optimise',
				)
			)
		);
		$this->assertFalse(
			RWGO_Platform_Client::claims_cover_optimise(
				array(
					'packageType'   => 'reactwoo-decision-cloud',
					'plan_code'     => 'starter',
					'product_slugs' => array( 'reactwoo-geocore-pro' ),
				)
			)
		);
		$this->assertFalse(
			RWGO_Platform_Client::claims_cover_optimise(
				array(
					'packageType' => 'reactwoo-decision-cloud',
					'plan_code'   => 'growth',
				)
			)
		);
	}
}
