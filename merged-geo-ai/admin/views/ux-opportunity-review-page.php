<?php
/**
 * AI UX Reviewer — primary screen.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'RWGA_UX_Reviewer_UI', false ) ) {
	echo '<div class="wrap"><p>' . esc_html__( 'AI UX Reviewer UI is not available.', 'reactwoo-geo-ai' ) . '</p></div>';
	return;
}

RWGA_UX_Reviewer_UI::render_workspace(
	array(
		'display_mode'     => isset( $rwga_display_mode ) ? (string) $rwga_display_mode : 'fresh',
		'source'           => isset( $rwga_source ) ? (string) $rwga_source : 'dashboard',
		'page_id'          => isset( $rwga_page_id ) ? (int) $rwga_page_id : 0,
		'product_id'       => isset( $rwga_product_id ) ? (int) $rwga_product_id : 0,
		'variant_page_id'  => isset( $rwga_variant_id ) ? (int) $rwga_variant_id : 0,
		'rule_id'          => isset( $rwga_rule_id ) ? (string) $rwga_rule_id : '',
		'engine_source'    => isset( $rwga_engine_source ) ? (string) $rwga_engine_source : '',
		'action_count'     => isset( $rwga_action_count ) ? (int) $rwga_action_count : 0,
		'capabilities'     => isset( $rwga_capabilities ) && is_array( $rwga_capabilities ) ? $rwga_capabilities : array(),
		'cards'            => isset( $rwga_cards ) && is_array( $rwga_cards ) ? $rwga_cards : array(),
		'session_meta'     => isset( $rwga_session_meta ) && is_array( $rwga_session_meta ) ? $rwga_session_meta : array(),
		'show_inner_nav'   => true,
		'nav_current'      => isset( $rwgc_nav_current ) ? (string) $rwgc_nav_current : 'rwga-ux-opportunity-review',
		'form_action_page' => 'rwga-ux-opportunity-review',
	)
);
