<?php
/**
 * Site intelligence timeline (local memory events).
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Append and query rwga_memory_events.
 */
class RWGA_Memory_Service {

	/**
	 * Record an event.
	 *
	 * @param string               $event_type Event slug.
	 * @param string               $entity_type Entity type.
	 * @param int                  $entity_id Entity id or 0 if none.
	 * @param int                  $page_id Page id or 0 if none.
	 * @param string               $geo_target ISO2 or empty string.
	 * @param array<string, mixed> $payload JSON-serialisable payload.
	 * @return int Insert id or 0.
	 */
	public static function append( $event_type, $entity_type, $entity_id, $page_id, $geo_target, array $payload ) {
		global $wpdb;
		$table = RWGA_DB::memory_events_table();
		$now   = current_time( 'mysql', true );
		$uid   = get_current_user_id();

		$eid = max( 0, (int) $entity_id );
		$pid = max( 0, (int) $page_id );
		$geo = $geo_target ? strtoupper( substr( sanitize_text_field( (string) $geo_target ), 0, 2 ) ) : '';

		$data = array(
			'event_type'  => sanitize_key( (string) $event_type ),
			'entity_type' => sanitize_key( (string) $entity_type ),
			'entity_id'   => $eid,
			'page_id'     => $pid,
			'geo_target'  => '' !== $geo ? $geo : null,
			'payload'     => wp_json_encode( $payload ),
			'importance'  => 5,
			'created_by'  => $uid > 0 ? $uid : 0,
			'created_at'  => $now,
		);

		$formats = array( '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%d', '%s' );

		$ok = $wpdb->insert( $table, $data, $formats );
		return $ok ? (int) $wpdb->insert_id : 0;
	}
}
