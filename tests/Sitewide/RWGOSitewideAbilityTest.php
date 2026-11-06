<?php
/**
 * Site-wide ability validation, permissions, and discovery metadata.
 *
 * @package ReactWoo_Geo_Optimise
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-theme-context.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide-proposal.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide-ability.php';

if ( ! function_exists( 'wp_register_ability_category' ) ) {
	/**
	 * @param string               $slug Category slug.
	 * @param array<string, mixed> $args Args.
	 * @return object
	 */
	function wp_register_ability_category( $slug, $args ) {
		$GLOBALS['rwgo_test_ability_categories'][ $slug ] = $args;
		return (object) array( 'slug' => $slug );
	}
}

if ( ! function_exists( 'wp_register_ability' ) ) {
	/**
	 * @param string               $name Ability name.
	 * @param array<string, mixed> $args Args.
	 * @return object
	 */
	function wp_register_ability( $name, $args ) {
		$GLOBALS['rwgo_test_abilities'][ $name ] = $args;
		return (object) array( 'name' => $name );
	}
}

/**
 * @covers RWGO_Sitewide_Proposal
 * @covers RWGO_Sitewide_Ability
 */
class RWGOSitewideAbilityTest extends PHPUnit\Framework\TestCase {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function posts() {
		return array(
			10 => array( 'type' => 'page', 'status' => 'publish', 'elementor_empty' => false ),
			11 => array( 'type' => 'page', 'status' => 'draft', 'elementor_empty' => false ),
			12 => array( 'type' => 'page', 'status' => 'publish', 'elementor_empty' => false ),
			21 => array( 'type' => 'elementor_library', 'status' => 'draft', 'elementor_empty' => true ),
			22 => array( 'type' => 'elementor_library', 'status' => 'publish', 'elementor_empty' => false ),
		);
	}

	/**
	 * @param array<string, mixed> $extra Extra fields.
	 * @return array<string, mixed>
	 */
	private function input( array $extra = array() ) {
		return array_merge(
			array(
				'expected_revision'      => 0,
				'control_weight_percent' => 50,
				'cookie_days'            => 90,
				'page_map'               => array(
					array(
						'source_page_id'  => 10,
						'variant_page_id' => 11,
					),
				),
				'theme_context'          => array(
					'control' => array( 'header' => 0, 'footer' => 0 ),
					'variant' => array( 'header' => 21, 'footer' => 0 ),
				),
			),
			$extra
		);
	}

	public function test_discovery_metadata_is_public_to_rest_and_mcp(): void {
		$names = array();
		foreach ( RWGO_Sitewide_Ability::definitions() as $definition ) {
			$names[] = $definition['name'];
			$this->assertSame( 'geo-optimise', $definition['category'] );
			$this->assertTrue( $definition['meta']['public'] );
			$this->assertTrue( $definition['meta']['show_in_rest'] );
			$this->assertTrue( $definition['meta']['mcp']['public'] );
			$this->assertArrayHasKey( 'instructions', $definition['meta']['annotations'] );
			$this->assertArrayHasKey( 'output_schema', $definition );
			$this->assertStringStartsWith( 'reactwoo-geo-optimise/', $definition['name'] );
		}
		$this->assertSame(
			array(
				'reactwoo-geo-optimise/sitewide-read',
				'reactwoo-geo-optimise/sitewide-validate',
				'reactwoo-geo-optimise/sitewide-save',
				'reactwoo-geo-optimise/sitewide-set-status',
				'reactwoo-geo-optimise/sitewide-preview-link',
			),
			$names
		);
		$save = RWGO_Sitewide_Ability::definitions()[2];
		$this->assertFalse( $save['meta']['annotations']['readonly'] );
		$this->assertNotContains( 'status', $save['input_schema']['required'] );
		$this->assertArrayNotHasKey( 'status', $save['input_schema']['properties'] );
		$status = RWGO_Sitewide_Ability::definitions()[3];
		$this->assertContains( 'confirm', $status['input_schema']['required'] );
		$this->assertFalse( $status['meta']['annotations']['destructive'] );
	}

	public function test_registration_exports_the_same_definitions(): void {
		$GLOBALS['rwgo_test_abilities']            = array();
		$GLOBALS['rwgo_test_ability_categories']   = array();
		RWGO_Sitewide_Ability::register_categories();
		RWGO_Sitewide_Ability::register_abilities();
		$this->assertArrayHasKey( 'geo-optimise', $GLOBALS['rwgo_test_ability_categories'] );
		$this->assertArrayHasKey( 'reactwoo-geo-optimise/sitewide-read', $GLOBALS['rwgo_test_abilities'] );
		$this->assertTrue( $GLOBALS['rwgo_test_abilities']['reactwoo-geo-optimise/sitewide-read']['meta']['show_in_rest'] );
		$this->assertTrue( $GLOBALS['rwgo_test_abilities']['reactwoo-geo-optimise/sitewide-save']['meta']['mcp']['public'] );
	}

	public function test_permission_denied_and_unlicensed(): void {
		$this->assertSame( 'rwgo_forbidden', RWGO_Sitewide_Proposal::permission_code( false, true, true ) );
		$this->assertSame( 'rwgo_unlicensed', RWGO_Sitewide_Proposal::permission_code( true, true, false ) );
		$this->assertSame( '', RWGO_Sitewide_Proposal::permission_code( true, false, false ) );
		$this->assertSame( '', RWGO_Sitewide_Proposal::permission_code( true, true, true ) );
	}

	public function test_malformed_duplicate_and_cycle_maps_are_rejected(): void {
		$posts = $this->posts();
		$bad   = RWGO_Sitewide_Proposal::inspect(
			$this->input(
				array(
					'page_map' => array(
						array( 'source_page_id' => 'nope', 'variant_page_id' => 11 ),
						array( 'source_page_id' => -3, 'variant_page_id' => 11 ),
						array( 'source_page_id' => 1.5, 'variant_page_id' => 11 ),
					),
				)
			),
			$posts
		);
		$this->assertFalse( $bad['ok'] );
		$this->assertContains( 'rwgo_malformed_id', array_column( $bad['errors'], 'code' ) );

		$duplicate = RWGO_Sitewide_Proposal::inspect(
			$this->input(
				array(
					'page_map' => array(
						array( 'source_page_id' => 10, 'variant_page_id' => 11 ),
						array( 'source_page_id' => 10, 'variant_page_id' => 12 ),
					),
				)
			),
			$posts
		);
		$this->assertContains( 'rwgo_duplicate_source', array_column( $duplicate['errors'], 'code' ) );

		$cycle = RWGO_Sitewide_Proposal::inspect(
			$this->input(
				array(
					'page_map' => array(
						array( 'source_page_id' => 10, 'variant_page_id' => 12 ),
						array( 'source_page_id' => 12, 'variant_page_id' => 10 ),
					),
				)
			),
			$posts
		);
		$this->assertContains( 'rwgo_redirect_loop', array_column( $cycle['errors'], 'code' ) );

		$weight = RWGO_Sitewide_Proposal::inspect( $this->input( array( 'control_weight_percent' => 150 ) ), $posts );
		$this->assertContains( 'rwgo_weight_range', array_column( $weight['errors'], 'code' ) );
		$this->assertContains( 'rwgo_unknown_post', array_column( RWGO_Sitewide_Proposal::inspect( $this->input( array( 'page_map' => array( array( 'source_page_id' => 99, 'variant_page_id' => 11 ) ) ) ), $posts )['errors'], 'code' ) );
	}

	public function test_unpublished_and_empty_templates_warn_and_block_activation_only(): void {
		$before = $this->posts();
		$plan   = RWGO_Sitewide_Proposal::plan_save( $this->input(), array(), $before );
		$this->assertTrue( $plan['ok'] );
		$this->assertSame( 'paused', $plan['config']['status'] );
		$this->assertSame( $before, $this->posts() );
		$this->assertContains( 'rwgo_variant_unpublished', array_column( $plan['warnings'], 'code' ) );
		$this->assertContains( 'rwgo_theme_template_empty', array_column( $plan['warnings'], 'code' ) );
		$this->assertFalse( $plan['ready'] );

		$activate = RWGO_Sitewide_Proposal::plan_status(
			array(
				'expected_revision' => 1,
				'status'            => 'active',
				'confirm'           => true,
			),
			$plan['config'],
			$before,
			true
		);
		$this->assertFalse( $activate['ok'] );
		$this->assertSame( 'rwgo_not_ready', $activate['code'] );

		$paused = RWGO_Sitewide_Proposal::plan_status(
			array(
				'expected_revision' => 1,
				'status'            => 'paused',
				'confirm'           => true,
			),
			$plan['config'],
			$before,
			false
		);
		$this->assertTrue( $paused['ok'] );
		$this->assertSame( 'paused', $paused['config']['status'] );
	}

	public function test_save_roundtrip_stays_paused_and_revision_guards_the_next_write(): void {
		$posts = $this->posts();
		$first = RWGO_Sitewide_Proposal::plan_save( $this->input( array( 'status' => 'active' ) ), array(), $posts );
		$this->assertSame( 'paused', $first['config']['status'] );
		$this->assertSame( 1, $first['config']['revision'] );
		$this->assertEqualsWithDelta( 0.5, $first['config']['control_weight'], 0.001 );

		$second = RWGO_Sitewide_Proposal::plan_save(
			$this->input(
				array(
					'expected_revision'      => 1,
					'control_weight_percent' => 40,
				)
			),
			$first['config'],
			$posts
		);
		$this->assertTrue( $second['ok'] );
		$this->assertSame( 'paused', $second['config']['status'] );
		$this->assertSame( 2, $second['config']['revision'] );
		$this->assertEqualsWithDelta( 0.4, $second['config']['control_weight'], 0.001 );

		$stale = RWGO_Sitewide_Proposal::plan_save( $this->input( array( 'expected_revision' => 1 ) ), $second['config'], $posts );
		$this->assertFalse( $stale['ok'] );
		$this->assertSame( 'rwgo_revision_conflict', $stale['code'] );

		$read = RWGO_Sitewide_Proposal::inspect( $second['config'], $posts );
		$this->assertSame( 10, $read['page_map'][0]['source_page_id'] );
		$this->assertSame( 11, $read['page_map'][0]['variant_page_id'] );
		$this->assertSame( 21, $read['theme_context']['variant']['header'] );
	}

	public function test_published_page_can_activate_without_elementor_pro_and_chrome_cannot(): void {
		$posts = $this->posts();
		$posts[11]['status'] = 'publish';
		$saved = RWGO_Sitewide_Proposal::plan_save( $this->input(), array(), $posts );
		$on    = RWGO_Sitewide_Proposal::plan_status(
			array( 'expected_revision' => 1, 'status' => 'active', 'confirm' => true ),
			$saved['config'],
			$posts,
			false
		);
		$this->assertTrue( $on['ok'] );
		$this->assertSame( 'active', $on['config']['status'] );

		$chrome_only = $this->posts();
		$chrome_only[21]['status'] = 'publish';
		$chrome_only[21]['elementor_empty'] = false;
		$saved_chrome = RWGO_Sitewide_Proposal::plan_save(
			$this->input(
				array(
					'page_map'      => array(),
					'theme_context' => array(
						'variant' => array( 'header' => 21, 'footer' => 0 ),
						'control' => array( 'header' => 22, 'footer' => 0 ),
					),
				)
			),
			array(),
			$chrome_only
		);
		$this->assertTrue( $saved_chrome['ok'] );
		$without_pro = RWGO_Sitewide_Proposal::plan_status(
			array( 'expected_revision' => 1, 'status' => 'active', 'confirm' => true ),
			$saved_chrome['config'],
			$chrome_only,
			false
		);
		$this->assertSame( 'rwgo_not_ready', $without_pro['code'] );
		$with_pro = RWGO_Sitewide_Proposal::plan_status(
			array( 'expected_revision' => 1, 'status' => 'active', 'confirm' => true ),
			$saved_chrome['config'],
			$chrome_only,
			true
		);
		$this->assertTrue( $with_pro['ok'] );
	}

	public function test_status_change_requires_confirm_and_preview_does_not_assign(): void {
		$posts  = $this->posts();
		$saved  = RWGO_Sitewide_Proposal::plan_save( $this->input(), array(), $posts );
		$silent = RWGO_Sitewide_Proposal::plan_status(
			array( 'expected_revision' => 1, 'status' => 'active', 'confirm' => false ),
			$saved['config'],
			$posts,
			true
		);
		$this->assertSame( 'rwgo_confirm_required', $silent['code'] );
		$url = RWGO_Sitewide_Ability::preview_url( 'https://example.test/original/', 'variant', 'nonce-value' );
		$this->assertStringContainsString( 'rwgo_preview=variant', $url );
		$this->assertStringContainsString( 'rwgo_preview_nonce=nonce-value', $url );
		$this->assertStringNotContainsString( 'rwgo_site', $url );
	}

	public function test_admin_validation_uses_the_same_unknown_post_rule(): void {
		if ( function_exists( 'get_post' ) ) {
			$this->markTestSkipped( 'This process already has get_post.' );
		}
		$errors = RWGO_Sitewide::validate_admin_config(
			array(
				'page_map'       => array(
					array( 'source_page_id' => 10, 'variant_page_id' => 11 ),
				),
				'control_weight' => 0.5,
				'cookie_days'    => 90,
			)
		);
		$this->assertContains( 'rwgo_unknown_post', array_column( $errors, 'code' ) );
	}
}
