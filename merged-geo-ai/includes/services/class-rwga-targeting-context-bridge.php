<?php
/**
 * Resolves Geo Core visibility / experience targeting for copy workflows.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin bridge to Geo Core portable rule context (no duplicate rule engine).
 */
class RWGA_Targeting_Context_Bridge {

	/**
	 * @param array<string, mixed> $input Workflow input (page_id, visibility_rule_id, geo_target, countries).
	 * @return array<string, mixed>
	 */
	public static function resolve( array $input ) {
		$empty = array(
			'rule_id'       => 0,
			'rule_title'    => '',
			'summary'       => '',
			'adapt_brief'   => '',
			'conditions'    => array(),
			'geo_codes'     => array(),
			'device_types'  => array(),
			'campaigns'     => array(),
			'primary_geo'   => '',
		);

		$rule_id = isset( $input['visibility_rule_id'] ) ? absint( $input['visibility_rule_id'] ) : 0;
		$page_id = isset( $input['page_id'] ) ? absint( $input['page_id'] ) : 0;

		if ( $rule_id > 0 && function_exists( 'rwgc_get_visibility_rule_copy_context' ) ) {
			$ctx = rwgc_get_visibility_rule_copy_context( $rule_id );
			return is_array( $ctx ) ? array_merge( $empty, $ctx ) : $empty;
		}

		if ( $page_id > 0 && function_exists( 'rwgc_get_page_experience_copy_context' ) ) {
			$ctx = rwgc_get_page_experience_copy_context( $page_id );
			if ( is_array( $ctx ) && ( ! empty( $ctx['summary'] ) || ! empty( $ctx['geo_codes'] ) ) ) {
				return array_merge( $empty, $ctx );
			}
		}

		if ( ! empty( $input['countries'] ) && is_array( $input['countries'] ) && function_exists( 'rwgc_get_country_codes_copy_context' ) ) {
			$ctx = rwgc_get_country_codes_copy_context( $input['countries'] );
			return is_array( $ctx ) ? array_merge( $empty, $ctx ) : $empty;
		}

		$geo = isset( $input['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $input['geo_target'] ), 0, 2 ) ) : '';
		if ( preg_match( '/^[A-Z]{2}$/', $geo ) && function_exists( 'rwgc_get_country_codes_copy_context' ) ) {
			$ctx = rwgc_get_country_codes_copy_context( array( $geo ) );
			return is_array( $ctx ) ? array_merge( $empty, $ctx ) : $empty;
		}

		return $empty;
	}

	/**
	 * Merge geo_target from input with resolved targeting (primary_geo wins when input empty).
	 *
	 * @param array<string, mixed> $input     Workflow input.
	 * @param array<string, mixed> $targeting Resolved targeting context.
	 * @return string ISO2 or empty.
	 */
	public static function resolve_geo_target( array $input, array $targeting ) {
		$geo = isset( $input['geo_target'] ) ? strtoupper( substr( sanitize_text_field( (string) $input['geo_target'] ), 0, 2 ) ) : '';
		if ( preg_match( '/^[A-Z]{2}$/', $geo ) ) {
			return $geo;
		}
		$primary = isset( $targeting['primary_geo'] ) ? (string) $targeting['primary_geo'] : '';
		return preg_match( '/^[A-Z]{2}$/', $primary ) ? $primary : '';
	}
}
