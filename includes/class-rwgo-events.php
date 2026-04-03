<?php
/**
 * Geo Optimise — subscribe to Geo Core events and re-emit for extensions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges `rwgc_*` events into `rwgo_*` hooks and lightweight counters (admin diagnostics).
 */
class RWGO_Events {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'rwgc_geo_event', array( __CLASS__, 'on_geo_event' ), 10, 1 );
		add_action( 'rwgc_route_variant_resolved', array( __CLASS__, 'on_route_resolved' ), 10, 4 );
	}

	/**
	 * @param mixed $payload Geo event payload array.
	 * @return void
	 */
	public static function on_geo_event( $payload ) {
		if ( ! is_array( $payload ) ) {
			return;
		}
		self::bump_count( 'rwgo_geo_event_count' );
		/**
		 * Geo Optimise: forwarded Geo Core geo event (same shape as `rwgc_geo_event`).
		 *
		 * @param array<string, mixed> $payload Event data.
		 */
		do_action( 'rwgo_geo_event', $payload );
	}

	/**
	 * @param mixed  $decision Route decision.
	 * @param mixed  $context  RWGC_Context.
	 * @param array  $config   Page config.
	 * @param int    $page_id  Page ID.
	 * @return void
	 */
	public static function on_route_resolved( $decision, $context, $config, $page_id ) {
		self::bump_count( 'rwgo_route_resolved_count' );
		/**
		 * Geo Optimise: forwarded route resolution (same args as Core).
		 *
		 * @param mixed                $decision Decision array.
		 * @param mixed                $context  Context object.
		 * @param array<string, mixed> $config   Route config.
		 * @param int                  $page_id  Page ID.
		 */
		do_action( 'rwgo_route_variant_resolved', $decision, $context, $config, (int) $page_id );
	}

	/**
	 * @param string $option Option name.
	 * @return void
	 */
	private static function bump_count( $option ) {
		$n = (int) get_option( $option, 0 );
		update_option( $option, $n + 1, false );
	}
}
