<?php
/**
 * Emit Geo Core RWGC_Event (assignment) when Optimise assigns a variant.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges `rwgo_variant_assigned` → `rwgc_emit_geo_event` when Core is available.
 */
class RWGO_Core_Event_Bridge {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwgo_variant_assigned', array( __CLASS__, 'maybe_emit' ), 10, 3 );
	}

	/**
	 * @param string       $slug      Experiment slug.
	 * @param string       $variant   Chosen variant.
	 * @param list<string> $variants  Pool.
	 * @return void
	 */
	public static function maybe_emit( $slug, $variant, $variants ) {
		/**
		 * Set false to skip emitting a Core geo event for assignments.
		 *
		 * @param bool         $emit     Default true.
		 * @param string       $slug     Experiment slug.
		 * @param string       $variant  Variant.
		 * @param list<string> $variants Pool.
		 */
		if ( ! apply_filters( 'rwgo_emit_assignment_geo_event', true, $slug, $variant, $variants ) ) {
			return;
		}
		if ( ! function_exists( 'rwgc_emit_geo_event' ) || ! class_exists( 'RWGC_Event', false ) ) {
			return;
		}

		$context = array();
		if ( function_exists( 'rwgc_get_visitor_data' ) ) {
			$v = rwgc_get_visitor_data();
			if ( is_array( $v ) ) {
				$context = $v;
			}
		}

		$event = new RWGC_Event(
			array(
				'event_type'    => RWGC_Event::TYPE_ASSIGNMENT,
				'experiment_id' => sanitize_key( (string) $slug ),
				'variant_id'    => sanitize_key( (string) $variant ),
				'context'       => $context,
				'meta'          => array(
					'variants_pool' => is_array( $variants ) ? array_values( array_map( 'strval', $variants ) ) : array(),
					'source'        => 'reactwoo-geo-optimise',
				),
			)
		);

		rwgc_emit_geo_event( $event );
	}
}
