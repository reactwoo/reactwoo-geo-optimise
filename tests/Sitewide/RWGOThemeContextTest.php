<?php
/**
 * Theme chrome, preview, measurement, and cache decisions.
 *
 * @package ReactWoo_Geo_Optimise
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-theme-context.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-cache-compat.php';

/**
 * @covers RWGO_Theme_Context
 */
class RWGOThemeContextTest extends PHPUnit\Framework\TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function theme() {
		return array(
			'control' => array(
				'header' => 11,
				'footer' => 12,
			),
			'variant' => array(
				'header' => 21,
				'footer' => 22,
			),
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function published_templates() {
		return array(
			11 => 'publish',
			12 => 'publish',
			21 => 'publish',
			22 => 'publish',
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function library_types() {
		return array(
			11 => 'elementor_library',
			12 => 'elementor_library',
			21 => 'elementor_library',
			22 => 'elementor_library',
		);
	}

	public function test_draft_variant_page_keeps_control_body_and_can_still_use_a_published_header(): void {
		$status = $this->published_templates();
		$plan   = RWGO_Theme_Context::delivery_plan(
			'variant',
			4038,
			'draft',
			false,
			$this->theme(),
			$status,
			$this->library_types()
		);
		$this->assertSame( 'control', $plan['body'] );
		$this->assertSame( 0, $plan['swap_page_id'] );
		$this->assertSame( 21, $plan['header'] );
		$this->assertSame( 22, $plan['footer'] );
		$this->assertSame( 'variant', $plan['delivered'] );
		$this->assertContains( 'unpublished_variant_page', $plan['reasons'] );
	}

	public function test_published_variant_page_swaps_and_selects_variant_templates(): void {
		$plan = RWGO_Theme_Context::delivery_plan(
			'variant',
			4038,
			'publish',
			false,
			$this->theme(),
			$this->published_templates(),
			$this->library_types()
		);
		$this->assertSame( 'variant', $plan['body'] );
		$this->assertSame( 4038, $plan['swap_page_id'] );
		$this->assertSame( 21, $plan['header'] );
		$this->assertTrue( $plan['allow_variant_templates'] );
	}

	public function test_unpublished_template_is_not_used_and_missing_everything_delivers_control(): void {
		$plan = RWGO_Theme_Context::delivery_plan(
			'variant',
			4038,
			'draft',
			false,
			$this->theme(),
			array(
				11 => 'publish',
				12 => 'publish',
				21 => 'draft',
				22 => 'draft',
			),
			$this->library_types()
		);
		$this->assertSame( 0, $plan['header'] );
		$this->assertSame( 0, $plan['footer'] );
		$this->assertSame( 'control', $plan['delivered'] );
		$this->assertFalse( $plan['allow_variant_templates'] );
	}

	public function test_page_post_type_is_not_printed_as_a_header(): void {
		$this->assertSame(
			0,
			RWGO_Theme_Context::usable_template_id(
				21,
				array( 21 => 'publish' ),
				array( 21 => 'page' )
			)
		);
	}

	public function test_control_arm_replaces_a_variant_template_chosen_by_display_conditions(): void {
		$plan = RWGO_Theme_Context::delivery_plan(
			'control',
			0,
			'',
			false,
			$this->theme(),
			$this->published_templates(),
			$this->library_types()
		);
		$this->assertSame(
			11,
			RWGO_Theme_Context::location_template_id( 'header', 21, $plan['header'], $plan['owned_variant_template_ids'], $plan['allow_variant_templates'] )
		);
		$this->assertSame(
			0,
			RWGO_Theme_Context::location_template_id( 'header', 21, 0, array( 21, 22 ), false )
		);
		$this->assertSame( 21, RWGO_Theme_Context::location_template_id( 'header', 21, 0, array( 21 ), true ) );
	}

	public function test_missing_elementor_pro_locations_do_not_count_as_variant_chrome(): void {
		$plan = RWGO_Theme_Context::delivery_plan(
			'variant',
			4038,
			'draft',
			false,
			$this->theme(),
			$this->published_templates(),
			$this->library_types(),
			false
		);
		$this->assertSame( 0, $plan['header'] );
		$this->assertSame( 'control', $plan['delivered'] );
		$this->assertContains( 'elementor_pro_locations_missing', $plan['reasons'] );
		$this->assertFalse( RWGO_Theme_Context::elementor_pro_locations_loaded() );
	}

	public function test_checkout_never_receives_variant_chrome(): void {
		$this->assertTrue(
			RWGO_Theme_Context::variant_theme_locked( 'checkout', 90, array( 90 ), false, array(), array( 'shop' ) )
		);
		$plan = RWGO_Theme_Context::delivery_plan( 'variant', 4038, 'publish', true, $this->theme(), $this->published_templates(), $this->library_types() );
		$this->assertSame( 0, $plan['swap_page_id'] );
		$this->assertSame( 0, $plan['header'] );
		$this->assertFalse( $plan['allow_variant_templates'] );
		$this->assertContains( 21, $plan['owned_variant_template_ids'] );
	}

	public function test_shop_is_locked_only_when_coverage_excludes_system_pages(): void {
		$this->assertFalse(
			RWGO_Theme_Context::variant_theme_locked( 'shop', 80, array(), false, array( 80 ), array( 'shop' ) )
		);
		$this->assertTrue(
			RWGO_Theme_Context::variant_theme_locked( 'shop', 80, array(), true, array( 80 ), array( 'shop' ) )
		);
	}

	public function test_unmapped_theme_split_is_not_a_shared_cache_entry(): void {
		$this->assertFalse( RWGO_Theme_Context::request_needs_cache_bypass( 'none', true, false, false ) );
		$this->assertTrue( RWGO_Theme_Context::request_needs_cache_bypass( 'none', true, true, false ) );
		$this->assertTrue( RWGO_Theme_Context::request_needs_cache_bypass( 'none', true, true, true ) );
		$this->assertTrue( RWGO_Sitewide::split_request_needs_cache_bypass( 'none', true, true, false ) );
		$this->assertTrue( RWGO_Theme_Context::request_needs_cache_bypass( 'variant', false, false, false ) );
		$this->assertFalse( RWGO_Theme_Context::request_needs_cache_bypass( 'source', false, false, false ) );
	}

	public function test_paused_and_ended_tests_canonicalize_without_serving_an_arm(): void {
		$this->assertFalse( RWGO_Theme_Context::experiment_serves_arms( 'paused' ) );
		$this->assertFalse( RWGO_Theme_Context::experiment_serves_arms( 'ended' ) );
		$this->assertTrue( RWGO_Theme_Context::experiment_serves_arms( 'active' ) );
		$this->assertTrue( RWGO_Theme_Context::experiment_canonicalizes_variant_urls( 'paused' ) );
		$this->assertTrue( RWGO_Theme_Context::experiment_canonicalizes_variant_urls( 'ended' ) );
		$this->assertFalse( RWGO_Theme_Context::experiment_canonicalizes_variant_urls( 'draft' ) );
	}

	public function test_redirect_refuses_a_loop_and_a_missing_target(): void {
		$this->assertSame(
			'',
			RWGO_Theme_Context::safe_variant_redirect( 10, 10, 'https://example.test/a/', '/a/', array( 10 ), array() )
		);
		$this->assertSame(
			'',
			RWGO_Theme_Context::safe_variant_redirect( 10, 11, 'https://example.test/a/', '/a/', array( 10, 11 ), array( '/a/' => true ) )
		);
		$this->assertSame(
			'https://example.test/atomic/',
			RWGO_Theme_Context::safe_variant_redirect( 3502, 4038, 'https://example.test/atomic/', '/atomic/', array( 4038 ), array( '/atomic-b/' => true ) )
		);
	}

	public function test_circular_page_map_is_not_served(): void {
		$rows = array(
			array(
				'source_page_id'  => 1,
				'variant_page_id' => 2,
				'source_path'     => '/a/',
				'variant_path'    => '/b/',
			),
			array(
				'source_page_id'  => 2,
				'variant_page_id' => 1,
				'source_path'     => '/b/',
				'variant_path'    => '/a/',
			),
		);
		$this->assertSame( array(), RWGO_Theme_Context::page_map_without_loops( $rows ) );
		$this->assertEquals( array( 1, 2 ), RWGO_Theme_Context::map_conflict_ids( $rows ) );
	}

	public function test_preview_requires_capability_and_nonce_and_does_not_rewrite_the_cookie(): void {
		$this->assertSame( '', RWGO_Theme_Context::preview_arm( 'variant', 'variant', false, true ) );
		$this->assertSame( '', RWGO_Theme_Context::preview_arm( 'variant', '', true, false ) );
		$this->assertSame( 'variant', RWGO_Theme_Context::preview_arm( 'variant', '', true, true ) );
		$this->assertSame( 'control', RWGO_Theme_Context::preview_arm( '', 'control', true, true ) );

		$id   = '0123456789abcdef0123456789abcdef';
		$arm  = RWGO_Sitewide::bucket_arm( $id, 'rwgo_sitewide', 0.5 );
		$raw  = wp_json_encode(
			array(
				'id'  => $id,
				'arm' => $arm,
				'exp' => 'rwgo_sitewide',
			)
		);
		$view = RWGO_Sitewide::preview_serving( $raw, 'rwgo_sitewide', 0.5, 'variant' );
		$this->assertSame( $arm, $view['cookie_arm'] );
		$this->assertSame( 'variant', $view['serve'] );
		$this->assertFalse( $view['measure'] );
		$plain = RWGO_Sitewide::preview_serving( $raw, 'rwgo_sitewide', 0.5, '' );
		$this->assertTrue( $plain['measure'] );
		$this->assertSame( $arm, $plain['serve'] );
	}

	public function test_measurement_skips_preview_and_refused_statistics_consent(): void {
		$this->assertFalse( RWGO_Theme_Context::measurement_allowed( true, false, true ) );
		$this->assertFalse( RWGO_Theme_Context::measurement_allowed( false, true, false ) );
		$this->assertTrue( RWGO_Theme_Context::measurement_allowed( false, false, false ) );
		$this->assertSame( '', RWGO_Theme_Context::conversion_arm( 'variant', false, false ) );
		$this->assertSame( 'control', RWGO_Theme_Context::conversion_arm( 'variant', false, true ) );
		$this->assertSame( 'variant', RWGO_Theme_Context::conversion_arm( 'variant', true, true ) );
	}

	public function test_audit_warns_and_does_not_publish(): void {
		$warnings = RWGO_Theme_Context::audit(
			array(
				array(
					'source_page_id'  => 3502,
					'variant_page_id' => 4038,
				),
			),
			array( 4038 => 'draft' ),
			array(
				'variant' => array(
					'header' => 21,
					'footer' => 0,
				),
			),
			array( 21 => 'draft' ),
			array( 21 => 'elementor_library' )
		);
		$codes = array();
		foreach ( $warnings as $warning ) {
			$codes[] = $warning['code'];
		}
		$this->assertContains( 'variant_page_unpublished', $codes );
		$this->assertContains( 'theme_template_unavailable', $codes );
		$this->assertContains( 'control_template_missing', $codes );
		$this->assertNotContains( 'publish', $codes );
	}

	public function test_cache_purge_includes_theme_template_ids(): void {
		$ids = RWGO_Cache_Compat::post_ids_for_config(
			array(
				'page_map'      => array(
					array(
						'source_page_id'  => 5,
						'variant_page_id' => 6,
					),
				),
				'theme_context' => $this->theme(),
			)
		);
		foreach ( array( 5, 6, 11, 12, 21, 22 ) as $id ) {
			$this->assertContains( $id, $ids );
		}
	}

	public function test_filter_uses_the_request_plan(): void {
		$plan = RWGO_Theme_Context::delivery_plan(
			'variant',
			8,
			'publish',
			false,
			$this->theme(),
			$this->published_templates(),
			$this->library_types()
		);
		RWGO_Theme_Context::set_request_plan( $plan );
		$this->assertContains( 11, $plan['suppress_template_ids'] );
		$this->assertSame( 0, RWGO_Theme_Context::filter_location_template( 11, 'header' ) );
		$this->assertSame( 21, RWGO_Theme_Context::filter_location_template( 21, 'header' ) );
		$this->assertSame( 21, RWGO_Theme_Context::forced_location_templates( $plan )['header'] );
		$this->assertSame( 22, RWGO_Theme_Context::forced_location_templates( $plan )['footer'] );
		$this->assertSame( 11, RWGO_Theme_Context::filter_location_template( 11, '' ) );
		RWGO_Theme_Context::set_request_plan( null );
		$this->assertSame( 11, RWGO_Theme_Context::filter_location_template( 11, 'header' ) );
	}

	/**
	 * Rules shaped like the live site: control chrome is the whole site except
	 * the B pages, variant chrome matches the B pages only, and cart has its
	 * own header.
	 *
	 * @return array<string, array<int, array<int, string>>>
	 */
	private function live_location_rules() {
		return array(
			'header' => array(
				40   => array(
					'include/general',
					'exclude/singular/page/3943',
					'exclude/singular/page/1037',
				),
				4040 => array(
					'include/singular/page/3943',
				),
				2831 => array(
					'include/singular/page/1037',
				),
			),
			'footer' => array(
				59   => array(
					'include/general',
					'exclude/singular/page/3943',
				),
				4041 => array(
					'include/singular/page/3943',
				),
			),
		);
	}

	/**
	 * @return array<string, array<string, int>>
	 */
	private function live_theme() {
		return array(
			'control' => array(
				'header' => 40,
				'footer' => 59,
			),
			'variant' => array(
				'header' => 4040,
				'footer' => 4041,
			),
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function live_template_status() {
		return array(
			40   => 'publish',
			59   => 'publish',
			4040 => 'publish',
			4041 => 'publish',
			2831 => 'publish',
		);
	}

	/**
	 * @return array<int, string>
	 */
	private function live_template_types() {
		return array(
			40   => 'elementor_library',
			59   => 'elementor_library',
			4040 => 'elementor_library',
			4041 => 'elementor_library',
			2831 => 'elementor_library',
		);
	}

	/**
	 * @return array<int, int>
	 */
	private function live_published_ids() {
		return array( 40, 59, 4040, 4041, 2831 );
	}

	/**
	 * @param string $arm          control|variant.
	 * @param bool   $theme_locked Cart, checkout, or account.
	 * @return array<string, mixed>
	 */
	private function live_plan( $arm, $theme_locked ) {
		return RWGO_Theme_Context::delivery_plan(
			$arm,
			3943,
			'publish',
			$theme_locked,
			$this->live_theme(),
			$this->live_template_status(),
			$this->live_template_types()
		);
	}

	public function test_variant_arm_renders_header_and_footer_markup_on_the_swapped_page(): void {
		$plan = $this->live_plan( 'variant', false );
		RWGO_Theme_Context::set_request_plan( $plan );
		$rules     = $this->live_location_rules();
		$published = $this->live_published_ids();
		$header    = RWGO_Theme_Context::elementor_condition_matches( $rules['header'], 'header', 3943, $published );
		$footer    = RWGO_Theme_Context::elementor_condition_matches( $rules['footer'], 'footer', 3943, $published );
		$this->assertContains( 4040, $header );
		$this->assertContains( 4041, $footer );
		$this->assertNotContains( 40, $header );
		$this->assertNotContains( 59, $footer );

		$markup = RWGO_Theme_Context::theme_chrome_markup( $rules, 3943, $published, $plan );
		$this->assertStringContainsString( 'data-elementor-type="header"', $markup );
		$this->assertStringContainsString( 'data-elementor-id="4040"', $markup );
		$this->assertStringContainsString( 'elementor-location-header', $markup );
		$this->assertStringContainsString( 'data-elementor-type="footer"', $markup );
		$this->assertStringContainsString( 'data-elementor-id="4041"', $markup );
		$this->assertStringContainsString( 'elementor-location-footer', $markup );
		$this->assertStringNotContainsString( 'data-elementor-id="40"', $markup );
		$this->assertStringNotContainsString( 'data-elementor-id="59"', $markup );
		$this->assertStringNotContainsString( 'data-elementor-id="2831"', $markup );
		RWGO_Theme_Context::set_request_plan( null );
	}

	public function test_variant_arm_renders_header_and_footer_when_conditions_do_not_match_the_url(): void {
		$plan = $this->live_plan( 'variant', false );
		RWGO_Theme_Context::set_request_plan( $plan );
		$rules     = $this->live_location_rules();
		$published = $this->live_published_ids();
		$header    = RWGO_Theme_Context::elementor_condition_matches( $rules['header'], 'header', 32, $published );
		$footer    = RWGO_Theme_Context::elementor_condition_matches( $rules['footer'], 'footer', 32, $published );
		$this->assertNotContains( 4040, $header );
		$this->assertNotContains( 4041, $footer );
		$this->assertNotContains( 40, $header );
		$this->assertNotContains( 59, $footer );

		$markup = RWGO_Theme_Context::theme_chrome_markup( $rules, 32, $published, $plan );
		$this->assertStringContainsString( 'data-elementor-type="header"', $markup );
		$this->assertStringContainsString( 'data-elementor-id="4040"', $markup );
		$this->assertStringContainsString( 'elementor-location-header', $markup );
		$this->assertStringContainsString( 'data-elementor-type="footer"', $markup );
		$this->assertStringContainsString( 'data-elementor-id="4041"', $markup );
		$this->assertStringContainsString( 'elementor-location-footer', $markup );
		$this->assertStringNotContainsString( 'data-elementor-id="40"', $markup );
		$this->assertStringNotContainsString( 'data-elementor-id="59"', $markup );
		RWGO_Theme_Context::set_request_plan( null );
	}

	public function test_control_arm_renders_control_header_and_footer(): void {
		$plan = $this->live_plan( 'control', false );
		RWGO_Theme_Context::set_request_plan( $plan );
		$rules     = $this->live_location_rules();
		$published = $this->live_published_ids();
		$markup    = RWGO_Theme_Context::theme_chrome_markup( $rules, 32, $published, $plan );
		$this->assertStringContainsString( 'data-elementor-id="40"', $markup );
		$this->assertStringContainsString( 'data-elementor-id="59"', $markup );
		$this->assertStringContainsString( 'elementor-location-header', $markup );
		$this->assertStringContainsString( 'elementor-location-footer', $markup );
		$this->assertStringNotContainsString( 'data-elementor-id="4040"', $markup );
		$this->assertStringNotContainsString( 'data-elementor-id="4041"', $markup );

		$leak = $rules;
		$leak['header'][4040] = array( 'include/general' );
		$leak['footer'][4041] = array( 'include/general' );
		$leaked = RWGO_Theme_Context::theme_chrome_markup( $leak, 32, $published, $plan );
		$this->assertStringContainsString( 'data-elementor-id="40"', $leaked );
		$this->assertStringContainsString( 'data-elementor-id="59"', $leaked );
		$this->assertStringNotContainsString( 'data-elementor-id="4040"', $leaked );
		$this->assertStringNotContainsString( 'data-elementor-id="4041"', $leaked );
		RWGO_Theme_Context::set_request_plan( null );
	}

	public function test_excluded_checkout_keeps_its_own_header_and_footer(): void {
		$plan = $this->live_plan( 'variant', true );
		$this->assertSame( 0, $plan['header'] );
		$this->assertSame( 0, $plan['footer'] );
		$this->assertFalse( $plan['allow_variant_templates'] );
		RWGO_Theme_Context::set_request_plan( $plan );
		$markup = RWGO_Theme_Context::theme_chrome_markup( $this->live_location_rules(), 1037, $this->live_published_ids(), $plan );
		$this->assertStringContainsString( 'data-elementor-type="header"', $markup );
		$this->assertStringContainsString( 'data-elementor-id="2831"', $markup );
		$this->assertStringContainsString( 'data-elementor-type="footer"', $markup );
		$this->assertStringContainsString( 'data-elementor-id="59"', $markup );
		$this->assertStringNotContainsString( 'data-elementor-id="40"', $markup );
		$this->assertStringNotContainsString( 'data-elementor-id="4040"', $markup );
		$this->assertStringNotContainsString( 'data-elementor-id="4041"', $markup );
		RWGO_Theme_Context::set_request_plan( null );
	}

	public function test_prime_queues_the_variant_templates_and_drops_the_control_templates(): void {
		$plan = $this->live_plan( 'variant', false );
		RWGO_Theme_Context::set_request_plan( $plan );
		$manager = new RWGO_Recording_Locations_Manager();
		$GLOBALS['rwga_test_filters']['rwgo_elementor_locations_manager'] = array(
			static function () use ( $manager ) {
				return $manager;
			},
		);
		RWGO_Theme_Context::prime_theme_locations();
		$this->assertContains( 4040, $manager->added['header'] );
		$this->assertContains( 4041, $manager->added['footer'] );
		$this->assertContains( 40, $manager->removed['header'] );
		$this->assertContains( 59, $manager->removed['footer'] );
		$this->assertNotContains( 4040, $manager->removed['header'] );
		$this->assertNotContains( 4041, $manager->removed['footer'] );

		$locked = $this->live_plan( 'variant', true );
		RWGO_Theme_Context::set_request_plan( $locked );
		$cart = new RWGO_Recording_Locations_Manager();
		$GLOBALS['rwga_test_filters']['rwgo_elementor_locations_manager'] = array(
			static function () use ( $cart ) {
				return $cart;
			},
		);
		RWGO_Theme_Context::prime_theme_locations();
		$this->assertSame( array(), $cart->added );

		unset( $GLOBALS['rwga_test_filters']['rwgo_elementor_locations_manager'] );
		RWGO_Theme_Context::set_request_plan( null );
	}
}

/**
 * Records Elementor location queue changes.
 */
class RWGO_Recording_Locations_Manager {

	/**
	 * @var array<string, array<int, int>>
	 */
	public $added = array();

	/**
	 * @var array<string, array<int, int>>
	 */
	public $removed = array();

	/**
	 * @param string $location    header|footer.
	 * @param int    $document_id Template ID.
	 * @return void
	 */
	public function add_doc_to_location( $location, $document_id ) {
		$this->added[ (string) $location ][] = (int) $document_id;
	}

	/**
	 * @param string $location    header|footer.
	 * @param int    $document_id Template ID.
	 * @return void
	 */
	public function remove_doc_from_location( $location, $document_id ) {
		$this->removed[ (string) $location ][] = (int) $document_id;
	}
}
