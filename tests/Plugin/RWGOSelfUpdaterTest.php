<?php
/**
 * Fallback update check when Geo Core's updater is the wordpress.org stub.
 *
 * @package ReactWoo_Geo_Optimise
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-self-updater.php';

/**
 * @covers RWGO_Self_Updater
 */
class RWGOSelfUpdaterTest extends PHPUnit\Framework\TestCase {

	public function test_fallback_is_not_installed_when_geo_core_already_checks(): void {
		$this->assertFalse( RWGO_Self_Updater::should_install_fallback( true ) );
		$this->assertTrue( RWGO_Self_Updater::should_install_fallback( false ) );
	}

	public function test_missing_token_response_is_not_an_update(): void {
		$offer = RWGO_Self_Updater::offer_from_response(
			'0.5.3',
			401,
			array(
				'update' => false,
				'error'  => 'Missing license token',
			)
		);
		$this->assertNull( $offer );
	}

	public function test_newer_catalog_version_is_an_offer_and_the_signed_url_is_not_stored(): void {
		$offer = RWGO_Self_Updater::offer_from_response(
			'0.5.3',
			200,
			array(
				'update'       => true,
				'version'      => '0.5.4',
				'download_url' => 'https://downloads.example/signed.zip',
				'slug'         => 'reactwoo-geo-optimise',
			)
		);
		$this->assertSame( '0.5.4', $offer['version'] );
		$transient           = new stdClass();
		$transient->checked  = array( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' => '0.5.3' );
		$transient->response = array();
		$offer['current']    = '0.5.3';
		$updated             = RWGO_Self_Updater::apply_offer( $transient, 'reactwoo-geo-optimise/reactwoo-geo-optimise.php', $offer );
		$row                 = $updated->response['reactwoo-geo-optimise/reactwoo-geo-optimise.php'];
		$this->assertSame( '0.5.4', $row->new_version );
		$this->assertStringStartsWith( RWGO_Self_Updater::PACKAGE_PREFIX, $row->package );
		$this->assertStringNotContainsString( 'signed.zip', $row->package );
	}

	public function test_same_version_is_not_offered(): void {
		$this->assertNull(
			RWGO_Self_Updater::offer_from_response(
				'0.5.4',
				200,
				array(
					'update'       => true,
					'version'      => '0.5.4',
					'download_url' => 'https://downloads.example/signed.zip',
				)
			)
		);
	}

	public function test_last_checked_only_save_is_ignored(): void {
		$stored               = new stdClass();
		$stored->last_checked = 100;
		$stored->checked      = array( 'reactwoo-geo-optimise/reactwoo-geo-optimise.php' => '0.5.3' );
		$incoming             = clone $stored;
		$incoming->last_checked = 200;
		$this->assertTrue( RWGO_Self_Updater::is_last_checked_only( $stored, $incoming ) );
		$incoming->checked['reactwoo-geo-optimise/reactwoo-geo-optimise.php'] = '0.5.4';
		$this->assertFalse( RWGO_Self_Updater::is_last_checked_only( $stored, $incoming ) );
	}

	public function test_https_package_rejects_credentials_and_token_markers(): void {
		$this->assertTrue( RWGO_Self_Updater::package_is_https( 'https://downloads.example/plugin.zip' ) );
		$this->assertFalse( RWGO_Self_Updater::package_is_https( 'http://downloads.example/plugin.zip' ) );
		$this->assertFalse( RWGO_Self_Updater::package_is_https( 'https://user:secret@downloads.example/plugin.zip' ) );
		$this->assertFalse( RWGO_Self_Updater::package_is_https( 'https://downloads.example/plugin.zip?license_key=abc' ) );
		$this->assertNull(
			RWGO_Self_Updater::offer_from_response(
				'0.5.3',
				200,
				array(
					'update'       => true,
					'version'      => '0.5.5',
					'download_url' => 'https://downloads.example/plugin.zip?access_token=abc',
				)
			)
		);
	}

	public function test_check_body_uses_the_optimise_slug_and_stable_channel_without_the_token(): void {
		$seen = array();
		$GLOBALS['rwga_test_filters']['rwgo_self_updater_bearer'] = array(
			static function () {
				return 'sekret-token';
			},
		);
		$GLOBALS['rwga_test_filters']['rwgo_self_updater_http'] = array(
			static function ( $value, $body ) use ( &$seen ) {
				$seen = $body;
				return array(
					'code' => 401,
					'data' => array( 'update' => false, 'error' => 'Missing license token' ),
				);
			},
		);
		try {
			$this->assertNull( RWGO_Self_Updater::request_offer( '0.5.3' ) );
		} finally {
			unset( $GLOBALS['rwga_test_filters']['rwgo_self_updater_bearer'], $GLOBALS['rwga_test_filters']['rwgo_self_updater_http'] );
		}
		$this->assertSame( 'reactwoo-geo-optimise', $seen['slug'] );
		$this->assertSame( 'stable', $seen['channel'] );
		$this->assertSame( '0.5.3', $seen['current_version'] );
		$this->assertArrayNotHasKey( 'license_key', $seen );
		$this->assertArrayNotHasKey( 'access_token', $seen );
		$this->assertStringNotContainsString( 'sekret-token', wp_json_encode( $seen ) );
	}

	public function test_request_without_a_bearer_does_not_call_the_catalog(): void {
		$GLOBALS['rwga_test_filters']['rwgo_self_updater_bearer'] = array(
			static function () {
				return '';
			},
		);
		$GLOBALS['rwga_test_filters']['rwgo_self_updater_http'] = array(
			static function () {
				throw new RuntimeException( 'catalog was called' );
			},
		);
		try {
			$this->assertNull( RWGO_Self_Updater::request_offer( '0.5.3' ) );
		} finally {
			unset( $GLOBALS['rwga_test_filters']['rwgo_self_updater_bearer'], $GLOBALS['rwga_test_filters']['rwgo_self_updater_http'] );
		}
	}
}
