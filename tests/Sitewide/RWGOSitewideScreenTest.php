<?php
/**
 * Mapping from the site-wide screen payload to the stored option, and search permission.
 *
 * @package ReactWoo_Geo_Optimise
 */

require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-theme-context.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide.php';
require_once dirname( __DIR__, 2 ) . '/includes/class-rwgo-sitewide-screen.php';

/**
 * @covers RWGO_Sitewide_Screen
 */
class RWGOSitewideScreenTest extends PHPUnit\Framework\TestCase {

	/**
	 * @return array<string, mixed>
	 */
	private function post() {
		return array(
			'rwgo_sw_ui'              => '1',
			'rwgo_sw_status'          => 'active',
			'rwgo_sw_key'             => 'Sitewide Home Refresh',
			'rwgo_sw_weight'          => '40',
			'rwgo_sw_days'            => '90',
			'rwgo_sw_mapping'         => 'explicit',
			'rwgo_sw_exclude_system'  => '1',
			'rwgo_sw_future'          => 'skip',
			'rwgo_sw_exclude_ids'     => array( '4', '8', '4' ),
			'rwgo_sw_goal_ids'        => array( '70', '71' ),
			'rwgo_sw_purchase'        => '1',
			'rwgo_sw_header_a'        => '20',
			'rwgo_sw_header_b'        => '21',
			'rwgo_sw_footer_a'        => '0',
			'rwgo_sw_footer_b'        => '31',
			'rwgo_sw_match'           => array(
				array( 'source' => '10', 'variant' => '11' ),
				array( 'source' => '12', 'variant' => '13' ),
				array( 'source' => '14', 'variant' => '' ),
				array( 'source' => '15', 'variant' => '15' ),
			),
			'rwgo_sw_product'         => array(
				array( 'source' => '40', 'variant' => '41' ),
				array( 'source' => '42', 'variant' => '42' ),
			),
		);
	}

	public function test_payload_maps_onto_the_stored_option_shape(): void {
		$payload = RWGO_Sitewide_Screen::payload_from_post( $this->post() );
		$stored  = RWGO_Sitewide_Screen::stored_config_from_payload(
			$payload,
			array( 'coverage_baseline_gmt' => 123 ),
			static function ( $id ) {
				return '/p' . (int) $id;
			}
		);

		$this->assertSame( 'sitewidehomerefresh', $stored['experiment_key'] );
		$this->assertSame( 'active', $stored['status'] );
		$this->assertSame( 'sitewide', $stored['test_type'] );
		$this->assertSame( 'site', $stored['scope'] );
		$this->assertSame( 90, $stored['cookie_days'] );
		$this->assertEquals( 0.4, $stored['control_weight'] );
		$this->assertSame( 10, $stored['source_page_id'] );
		$this->assertSame( 'explicit', $stored['mapping_mode'] );
		$this->assertTrue( $stored['exclude_system_pages'] );
		$this->assertSame( array( 4, 8 ), $stored['exclude_page_ids'] );
		$this->assertSame( 'skip', $stored['future_pages'] );
		$this->assertSame( 123, $stored['coverage_baseline_gmt'] );
		$this->assertTrue( $stored['track_purchase'] );
		$this->assertSame( array( 70, 71 ), $stored['goal_page_ids'] );
		$this->assertSame( 'sitewide_destination', $stored['primary_goal_id'] );
		$this->assertSame( 0.4, $stored['variants'][0]['weight'] );
		$this->assertSame( 0.6, $stored['variants'][1]['weight'] );
		$this->assertSame( 'var_b', $stored['variants'][1]['variant_id'] );

		$this->assertSame(
			array(
				array(
					'source_page_id'  => 10,
					'variant_page_id' => 11,
					'source_path'     => '/p10',
					'variant_path'    => '/p11',
				),
				array(
					'source_page_id'  => 12,
					'variant_page_id' => 13,
					'source_path'     => '/p12',
					'variant_path'    => '/p13',
				),
				array(
					'source_page_id'  => 15,
					'variant_page_id' => 15,
					'source_path'     => '/p15',
					'variant_path'    => '/p15',
				),
			),
			$stored['page_map']
		);
		$this->assertSame( 20, $stored['theme_context']['control']['header'] );
		$this->assertSame( 21, $stored['theme_context']['variant']['header'] );
		$this->assertSame( 0, $stored['theme_context']['control']['footer'] );
		$this->assertSame( 31, $stored['theme_context']['variant']['footer'] );
		$this->assertSame(
			array(
				array(
					'location'            => 'single',
					'source_template_id'  => 40,
					'variant_template_id' => 41,
				),
				array(
					'location'            => 'single',
					'source_template_id'  => 42,
					'variant_template_id' => 42,
				),
			),
			$stored['template_map']
		);
	}

	public function test_half_filled_rows_are_rejected_and_not_stored(): void {
		$payload = RWGO_Sitewide_Screen::payload_from_post( $this->post() );
		$issues  = RWGO_Sitewide_Screen::incomplete_row_issues( $payload );
		$this->assertSame( 'rwgo_incomplete_match', $issues[0]['code'] );
		$stored = RWGO_Sitewide_Screen::stored_config_from_payload( $payload, array() );
		$sources = array_column( $stored['page_map'], 'source_page_id' );
		$this->assertNotContains( 14, $sources );
	}

	public function test_large_map_appends_and_can_drop_an_original(): void {
		$payload = RWGO_Sitewide_Screen::payload_from_post(
			array(
				'rwgo_sw_map_merge' => '1',
				'rwgo_sw_unmap'     => array( '10' ),
				'rwgo_sw_match'     => array(
					array( 'source' => '16', 'variant' => '17' ),
				),
			)
		);
		$stored = RWGO_Sitewide_Screen::stored_config_from_payload(
			$payload,
			array(
				'page_map' => array(
					array(
						'source_page_id'  => 10,
						'variant_page_id' => 11,
						'source_path'     => '/old',
						'variant_path'    => '/old-b',
					),
				),
			)
		);
		$this->assertSame(
			array( 16 ),
			array_column( $stored['page_map'], 'source_page_id' )
		);
	}

	public function test_all_published_sets_a_baseline_once(): void {
		$payload = RWGO_Sitewide_Screen::payload_from_post(
			array(
				'rwgo_sw_mapping' => 'all_published',
				'rwgo_sw_future'  => 'include',
			)
		);
		$first = RWGO_Sitewide_Screen::stored_config_from_payload( $payload, array() );
		$this->assertSame( 'all_published', $first['mapping_mode'] );
		$this->assertSame( 'include', $first['future_pages'] );
		$this->assertGreaterThan( 0, $first['coverage_baseline_gmt'] );
		$second = RWGO_Sitewide_Screen::stored_config_from_payload(
			$payload,
			array( 'coverage_baseline_gmt' => 50 )
		);
		$this->assertSame( 50, $second['coverage_baseline_gmt'] );
	}

	public function test_weight_and_cookie_are_clamped(): void {
		$stored = RWGO_Sitewide_Screen::stored_config_from_payload(
			RWGO_Sitewide_Screen::payload_from_post(
				array(
					'rwgo_sw_weight' => '140',
					'rwgo_sw_days'   => '0',
					'rwgo_sw_status' => 'nope',
				)
			),
			array()
		);
		$this->assertEquals( 1.0, $stored['control_weight'] );
		$this->assertSame( 1, $stored['cookie_days'] );
		$this->assertSame( 'paused', $stored['status'] );
		$this->assertFalse( $stored['exclude_system_pages'] );
		$this->assertFalse( $stored['track_purchase'] );
	}

	public function test_search_denial_checks_capability_then_nonce(): void {
		$this->assertSame( 'forbidden', RWGO_Sitewide_Screen::search_denial( false, true ) );
		$this->assertSame( 'forbidden', RWGO_Sitewide_Screen::search_denial( false, false ) );
		$this->assertSame( 'invalid_nonce', RWGO_Sitewide_Screen::search_denial( true, false ) );
		$this->assertSame( '', RWGO_Sitewide_Screen::search_denial( true, true ) );
	}

	public function test_search_query_uses_pages_or_elementor_templates_not_raw_ids(): void {
		$page = RWGO_Sitewide_Screen::search_query_args( 'page', '12', 0 );
		$this->assertSame( 'page', $page['post_type'] );
		$this->assertSame( '12', $page['s'] );
		$this->assertArrayNotHasKey( 'p', $page );
		$this->assertArrayNotHasKey( 'post__in', $page );

		$variant = RWGO_Sitewide_Screen::search_query_args( 'variant', 'home', 10 );
		$this->assertSame( 'page', $variant['post_type'] );
		$this->assertSame( RWGO_Sitewide::VARIANT_META, $variant['meta_key'] );
		$this->assertSame( '10', $variant['meta_value'] );

		$header = RWGO_Sitewide_Screen::search_query_args( 'header', '', 0 );
		$this->assertSame( 'elementor_library', $header['post_type'] );
		$this->assertSame( 'header', $header['meta_query'][0]['value'] );

		$product = RWGO_Sitewide_Screen::search_query_args( 'product', 'sale', 0 );
		$this->assertSame( 'elementor_library', $product['post_type'] );
		$this->assertSame( 'IN', $product['meta_query'][0]['compare'] );
		$this->assertContains( 'single-product', $product['meta_query'][0]['value'] );
		$this->assertNotSame( 'product', $product['post_type'] );

		$this->assertSame( array(), RWGO_Sitewide_Screen::search_query_args( 'post', 'hello', 0 ) );
	}

	public function test_experiments_list_offers_new_test_before_any_tests_exist(): void {
		$list    = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/views/tests-list.php' );
		$partial = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/views/partials/new-test-menu.php' );
		$menu    = strpos( $list, 'new-test-menu.php' );
		$empty   = strpos( $list, 'empty( $rwgo_experiments )' );
		$this->assertNotFalse( $menu );
		$this->assertNotFalse( $empty );
		$this->assertLessThan( $empty, $menu );
		$this->assertStringContainsString( 'page=rwgo-create-test', $partial );
		$this->assertStringContainsString( 'page=rwgo-sitewide', $partial );
		$this->assertStringContainsString( 'Single page test', $partial );
		$this->assertStringContainsString( 'Site-wide test', $partial );
		$this->assertStringNotContainsString( 'Create Test', substr( $list, (int) $empty, 600 ) );
	}

	public function test_draft_variant_warning_names_both_pages(): void {
		$this->assertSame(
			'Home (variant B) is still a draft. Visitors in variant B will see the original Home until you publish it.',
			RWGO_Sitewide_Screen::unpublished_variant_warning( 'Home (variant B)', 'Home', 'draft' )
		);
		$this->assertSame( '', RWGO_Sitewide_Screen::unpublished_variant_warning( 'About (variant B)', 'About', 'publish' ) );
		$this->assertStringContainsString( 'not published', RWGO_Sitewide_Screen::unpublished_variant_warning( 'Services (variant B)', 'Services', 'pending' ) );
	}
}
