<?php
/**
 * Stores guided journey context for current user.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Current_Workflow {
	const META_KEY = '_rwga_current_workflow';

	/**
	 * @return array<string, mixed>
	 */
	public static function get() {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return array();
		}
		$state = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * @param array<string, mixed> $state State.
	 * @return void
	 */
	public static function set( array $state ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		$state['updated_at'] = current_time( 'mysql', true );
		update_user_meta( $user_id, self::META_KEY, $state );
	}

	/**
	 * @return void
	 */
	public static function clear() {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		delete_user_meta( $user_id, self::META_KEY );
	}

	/**
	 * @param array<string, mixed> $patch Patch.
	 * @return void
	 */
	public static function update( array $patch ) {
		self::set( array_merge( self::get(), $patch ) );
	}

	/**
	 * @param string $step Step value.
	 * @return void
	 */
	public static function set_step( $step ) {
		self::update( array( 'journey_step' => sanitize_key( (string) $step ) ) );
	}

	/**
	 * @return string
	 */
	public static function get_step() {
		$s = self::get();
		return isset( $s['journey_step'] ) ? (string) $s['journey_step'] : 'started';
	}

	/**
	 * @param int $run_id Run id.
	 * @return void
	 */
	public static function set_analysis_run_id( $run_id ) {
		self::update( array( 'analysis_run_id' => (int) $run_id ) );
	}

	/**
	 * @param array<int, int> $ids IDs.
	 * @return void
	 */
	public static function set_recommendation_ids( array $ids ) {
		self::update( array( 'recommendation_ids' => array_values( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * @param array<int, int> $ids IDs.
	 * @return void
	 */
	public static function set_draft_ids( array $ids ) {
		self::update( array( 'draft_ids' => array_values( array_map( 'intval', $ids ) ) ) );
	}

	/**
	 * @param int $page_id Variant page id.
	 * @return void
	 */
	public static function set_variant_page_id( $page_id ) {
		self::update( array( 'variant_page_id' => (int) $page_id ) );
	}
}

