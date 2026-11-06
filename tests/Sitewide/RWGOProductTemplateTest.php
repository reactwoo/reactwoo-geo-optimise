<?php
/**
 * Product-body template map, preview grants, and variant document CSS.
 *
 * @package ReactWoo_Geo_Optimise
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-theme-context.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide-proposal.php';

/**
 * @covers RWGO_Theme_Context
 * @covers RWGO_Sitewide_Proposal
 */
class RWGOProductTemplateTest extends PHPUnit\Framework\TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function library( $status, $elementor_type, $empty = false ) {
		return array(
			'type'                    => 'elementor_library',
			'status'                  => $status,
			'elementor_empty'         => $empty,
			'elementor_template_type' => $elementor_type,
		);
	}

	public function test_variant_arm_prints_mapped_product_template_on_the_same_query() {
		$status = array(
			70 => 'publish',
			80 => 'publish',
		);
		$types  = array(
			70 => 'elementor_library',
			80 => 'elementor_library',
		);
		$map    = array(
			array(
				'location'            => 'product',
				'source_template_id'  => 70,
				'variant_template_id' => 80,
			),
		);
		$plan   = RWGO_Theme_Context::delivery_plan( 'variant', 0, '', false, array(), $status, $types, true, $map );
		$this->assertSame( 'variant', $plan['delivered'] );
		$this->assertSame( 0, $plan['swap_page_id'] );
		$this->assertContains( 80, $plan['preload_template_ids'] );
		$this->assertContains( 70, $plan['suppress_template_ids'] );
		$this->assertSame(
			80,
			RWGO_Theme_Context::translate_body_template( 'single', 70, 'variant', $plan['template_map'], $plan['usable_template_ids'], true, false )
		);
		$this->assertSame(
			80,
			RWGO_Theme_Context::translate_body_template( 'product', 70, 'variant', $plan['template_map'], $plan['usable_template_ids'], true, false )
		);
	}

	public function test_control_arm_does_not_print_the_variant_product_template() {
		$plan = RWGO_Theme_Context::delivery_plan(
			'control',
			0,
			'',
			false,
			array(),
			array(
				70 => 'publish',
				80 => 'publish',
			),
			array(
				70 => 'elementor_library',
				80 => 'elementor_library',
			),
			true,
			array(
				array(
					'location'            => 'single',
					'source_template_id'  => 70,
					'variant_template_id' => 80,
				),
			)
		);
		$this->assertSame( 'control', $plan['delivered'] );
		$this->assertSame(
			70,
			RWGO_Theme_Context::translate_body_template( 'single', 80, 'control', $plan['template_map'], $plan['usable_template_ids'], false, false )
		);
		$this->assertSame(
			70,
			RWGO_Theme_Context::translate_body_template( 'single', 70, 'control', $plan['template_map'], $plan['usable_template_ids'], false, false )
		);
	}

	public function test_unpublished_or_empty_variant_template_falls_back_to_source() {
		$draft = RWGO_Theme_Context::delivery_plan(
			'variant',
			0,
			'',
			false,
			array(),
			array(
				70 => 'publish',
				80 => 'draft',
			),
			array(
				70 => 'elementor_library',
				80 => 'elementor_library',
			),
			true,
			array(
				array(
					'location'            => 'single',
					'source_template_id'  => 70,
					'variant_template_id' => 80,
				),
			)
		);
		$this->assertContains( 'template_variant_unavailable', $draft['reasons'] );
		$this->assertSame( array(), $draft['preload_template_ids'] );
		$this->assertSame( 70, RWGO_Theme_Context::translate_body_template( 'single', 70, 'variant', $draft['template_map'], $draft['usable_template_ids'], true, false ) );

		$empty = RWGO_Theme_Context::delivery_plan(
			'variant',
			0,
			'',
			false,
			array(),
			array(
				70 => 'publish',
				80 => 'publish',
			),
			array(
				70 => 'elementor_library',
				80 => 'elementor_library',
			),
			true,
			array(
				array(
					'location'            => 'single',
					'source_template_id'  => 70,
					'variant_template_id' => 80,
				),
			),
			array( 80 => true )
		);
		$this->assertSame( array(), $empty['preload_template_ids'] );
		$this->assertSame( 70, RWGO_Theme_Context::translate_body_template( 'single', 70, 'variant', $empty['template_map'], $empty['usable_template_ids'], true, false ) );
	}

	public function test_cart_checkout_and_account_do_not_take_the_product_template() {
		$this->assertTrue( RWGO_Theme_Context::variant_theme_locked( 'cart', 5, array( 5 ), false, array(), array() ) );
		$this->assertTrue( RWGO_Theme_Context::variant_theme_locked( 'checkout', 6, array(), false, array(), array() ) );
		$this->assertTrue( RWGO_Theme_Context::variant_theme_locked( 'my-account', 7, array(), false, array(), array() ) );
		$locked = RWGO_Theme_Context::delivery_plan(
			'variant',
			0,
			'',
			true,
			array(),
			array(
				70 => 'publish',
				80 => 'publish',
			),
			array(
				70 => 'elementor_library',
				80 => 'elementor_library',
			),
			true,
			array(
				array(
					'location'            => 'single',
					'source_template_id'  => 70,
					'variant_template_id' => 80,
				),
			)
		);
		$this->assertContains( 'theme_locked', $locked['reasons'] );
		$this->assertSame( array(), $locked['template_map'] );
		$this->assertSame( array(), $locked['preload_template_ids'] );
		$this->assertSame( 70, RWGO_Theme_Context::translate_body_template( 'single', 70, 'variant', $locked['template_map'], $locked['usable_template_ids'], false, true ) );
	}

	public function test_header_override_still_wins_without_a_template_map() {
		$theme = array(
			'control' => array(
				'header' => 11,
				'footer' => 0,
			),
			'variant' => array(
				'header' => 21,
				'footer' => 0,
			),
		);
		$plan  = RWGO_Theme_Context::delivery_plan(
			'variant',
			0,
			'',
			false,
			$theme,
			array(
				11 => 'publish',
				21 => 'publish',
			),
			array(
				11 => 'elementor_library',
				21 => 'elementor_library',
			),
			true
		);
		$this->assertSame( 21, $plan['header'] );
		$this->assertSame( array(), $plan['template_map'] );
		$this->assertSame( 21, RWGO_Theme_Context::location_template_id( 'header', 11, $plan['header'], $plan['owned_variant_template_ids'], true ) );
	}

	public function test_printed_template_css_is_queued_and_replaced_template_css_is_not() {
		$late = RWGO_Theme_Context::document_asset_actions( 80, 70, true );
		$this->assertSame( array( 80 ), $late['enqueue'] );
		$this->assertSame( array( 70 ), $late['suppress'] );
		$this->assertTrue( $late['print_late'] );
		$this->assertSame( 'elementor-post-80', RWGO_Theme_Context::elementor_style_handle( 80 ) );
		$this->assertSame( 'elementor-post-70', RWGO_Theme_Context::elementor_style_handle( 70 ) );

		$same = RWGO_Theme_Context::document_asset_actions( 80, 80, false );
		$this->assertSame( array( 80 ), $same['enqueue'] );
		$this->assertSame( array(), $same['suppress'] );
		$this->assertFalse( $same['print_late'] );

		$plan = RWGO_Theme_Context::delivery_plan(
			'variant',
			34,
			'publish',
			false,
			array(),
			array(
				70 => 'publish',
				80 => 'publish',
			),
			array(
				70 => 'elementor_library',
				80 => 'elementor_library',
			),
			true,
			array(
				array(
					'location'            => 'single',
					'source_template_id'  => 70,
					'variant_template_id' => 80,
				),
			)
		);
		$plan['replaced_body_id'] = 12;
		RWGO_Theme_Context::set_request_plan( $plan );
		$this->assertSame( 80, RWGO_Theme_Context::filter_location_template( 70, 'single' ) );
		$this->assertSame( 21, RWGO_Theme_Context::filter_location_template( 21, 'header' ) );
		RWGO_Theme_Context::set_request_plan( null );
	}

	public function test_shareable_preview_requires_a_matching_unexpired_hash() {
		$token = 'abcdefghijklmnopqrstuvwxyz012345';
		$hash  = hash( 'sha256', $token );
		$now   = 1700000000;
		$links = array(
			array(
				'hash'    => $hash,
				'expires' => $now + 60,
			),
		);
		$this->assertTrue( RWGO_Theme_Context::shareable_preview_granted( $token, $links, $now ) );
		$this->assertFalse( RWGO_Theme_Context::shareable_preview_granted( $token, $links, $now + 61 ) );
		$this->assertFalse( RWGO_Theme_Context::shareable_preview_granted( $token, array(), $now ) );
		$this->assertFalse( RWGO_Theme_Context::shareable_preview_granted( 'not-a-real-token', $links, $now ) );
		$this->assertFalse( RWGO_Theme_Context::shareable_preview_granted( $token . 'x', $links, $now ) );
		$this->assertFalse(
			RWGO_Theme_Context::shareable_preview_granted(
				$token,
				array(
					array(
						'hash'    => hash( 'sha256', 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz' ),
						'expires' => $now + 60,
					),
				),
				$now
			)
		);
	}

	public function test_canonical_redirect_ignores_a_bare_token_and_a_failed_rwgo_nonce() {
		$this->assertFalse( RWGO_Theme_Context::canonical_redirect_blocked( false, '', false ) );
		$this->assertTrue( RWGO_Theme_Context::canonical_redirect_blocked( true, '', false ) );
		$this->assertTrue( RWGO_Theme_Context::canonical_redirect_blocked( false, 'variant', false ) );
		$this->assertTrue( RWGO_Theme_Context::canonical_redirect_blocked( false, '', true ) );
		$this->assertSame( '', RWGO_Theme_Context::preview_arm( 'variant', '', true, false ) );
		$this->assertSame( 'rwgo_preview_session_mismatch', RWGO_Theme_Context::preview_diagnostic_code( true, false, true, '' ) );
		$this->assertSame( '', RWGO_Theme_Context::preview_diagnostic_code( true, false, false, '' ) );
		$this->assertSame( '', RWGO_Theme_Context::preview_diagnostic_code( true, true, true, 'variant' ) );
		$this->assertSame( '', RWGO_Theme_Context::preview_diagnostic_code( false, false, true, '' ) );
	}

	public function test_template_map_persists_and_rejects_products_and_bad_locations() {
		$posts    = array(
			70 => $this->library( 'publish', 'product' ),
			80 => $this->library( 'publish', 'single-product' ),
			81 => $this->library( 'draft', 'product' ),
			82 => $this->library( 'publish', 'header' ),
			90 => array(
				'type'            => 'product',
				'status'          => 'publish',
				'elementor_empty' => false,
			),
		);
		$existing = array(
			'revision'      => 3,
			'status'        => 'paused',
			'page_map'      => array(),
			'theme_context' => RWGO_Theme_Context::empty_context(),
		);
		$saved    = RWGO_Sitewide_Proposal::plan_save(
			array(
				'expected_revision' => 3,
				'page_map'          => array(),
				'template_map'      => array(
					array(
						'location'            => 'product',
						'source_template_id'  => 70,
						'variant_template_id' => 80,
					),
				),
			),
			$existing,
			$posts
		);
		$this->assertTrue( $saved['ok'] );
		$this->assertSame( 'single', $saved['config']['template_map'][0]['location'] );
		$this->assertSame( 70, $saved['config']['template_map'][0]['source_template_id'] );
		$this->assertSame( 80, $saved['config']['template_map'][0]['variant_template_id'] );
		$this->assertSame( 'paused', $saved['config']['status'] );

		$kept = RWGO_Sitewide_Proposal::plan_save(
			array(
				'expected_revision' => 4,
				'page_map'          => array(),
			),
			$saved['config'],
			$posts
		);
		$this->assertTrue( $kept['ok'] );
		$this->assertSame( $saved['config']['template_map'], $kept['config']['template_map'] );

		$checked = RWGO_Sitewide_Proposal::inspect( $saved['config'], $posts );
		$this->assertTrue( $checked['ok'] );
		$this->assertSame( 1, $checked['product_templates_ready'] );
		$this->assertTrue( RWGO_Sitewide_Proposal::activation_ready( $checked, true ) );
		$this->assertFalse( RWGO_Sitewide_Proposal::activation_ready( array( 'page_ready' => false, 'chrome_ready' => true ), false ) );
		$coverage = RWGO_Sitewide_Proposal::coverage_report( $checked, true );
		$this->assertSame( 1, $coverage['product_template_pairs'] );
		$this->assertTrue( $coverage['serves_product_bodies'] );
		$this->assertContains( 'cart', $coverage['excluded'] );
		$this->assertContains( 'checkout', $coverage['excluded'] );
		$this->assertContains( 'my-account', $coverage['excluded'] );
		$this->assertContains( 'admin', $coverage['excluded'] );

		$product = RWGO_Sitewide_Proposal::inspect(
			array(
				'page_map'     => array(),
				'template_map' => array(
					array(
						'location'            => 'single',
						'source_template_id'  => 70,
						'variant_template_id' => 90,
					),
				),
			),
			$posts
		);
		$this->assertContains( 'rwgo_product_not_template', array_column( $product['errors'], 'code' ) );

		$page = RWGO_Sitewide_Proposal::inspect(
			array(
				'page_map' => array(
					array(
						'source_page_id'  => 90,
						'variant_page_id' => 70,
					),
				),
			),
			$posts
		);
		$this->assertContains( 'rwgo_wrong_post_type', array_column( $page['errors'], 'code' ) );

		$location = RWGO_Sitewide_Proposal::inspect(
			array(
				'page_map'     => array(),
				'template_map' => array(
					array(
						'location'            => 'header',
						'source_template_id'  => 70,
						'variant_template_id' => 80,
					),
				),
			),
			$posts
		);
		$this->assertContains( 'rwgo_template_location', array_column( $location['errors'], 'code' ) );

		$draft = RWGO_Sitewide_Proposal::inspect(
			array(
				'page_map'     => array(),
				'template_map' => array(
					array(
						'location'            => 'single',
						'source_template_id'  => 70,
						'variant_template_id' => 81,
					),
				),
			),
			$posts
		);
		$this->assertTrue( $draft['ok'] );
		$this->assertContains( 'rwgo_theme_template_unpublished', array_column( $draft['warnings'], 'code' ) );
		$this->assertSame( 0, $draft['product_templates_ready'] );

		$mismatch = RWGO_Sitewide_Proposal::inspect(
			array(
				'page_map'     => array(),
				'template_map' => array(
					array(
						'location'            => 'single',
						'source_template_id'  => 70,
						'variant_template_id' => 82,
					),
				),
			),
			$posts
		);
		$this->assertTrue( $mismatch['ok'] );
		$this->assertContains( 'rwgo_template_location_mismatch', array_column( $mismatch['warnings'], 'code' ) );
		$this->assertSame( 0, $mismatch['product_templates_ready'] );
	}

	public function test_existing_page_map_config_without_template_map_stays_valid() {
		$posts   = array(
			10 => array(
				'type'            => 'page',
				'status'          => 'publish',
				'elementor_empty' => false,
			),
			11 => array(
				'type'            => 'page',
				'status'          => 'publish',
				'elementor_empty' => false,
			),
			21 => $this->library( 'publish', 'header' ),
		);
		$checked = RWGO_Sitewide_Proposal::inspect(
			array(
				'page_map'      => array(
					array(
						'source_page_id'  => 10,
						'variant_page_id' => 11,
					),
				),
				'theme_context' => array(
					'variant' => array(
						'header' => 21,
					),
				),
			),
			$posts
		);
		$this->assertTrue( $checked['ok'] );
		$this->assertSame( array(), $checked['template_map'] );
		$this->assertSame( 11, $checked['page_map'][0]['variant_page_id'] );
		$this->assertSame( 0, $checked['theme_context']['control']['header'] );
	}
}
