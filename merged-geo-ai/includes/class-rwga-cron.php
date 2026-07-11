<?php
/**
 * WP-Cron: scheduled automation rules.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers cron schedule and processes due automation rules.
 */
class RWGA_Cron {

	const HOOK     = 'rwga_automation_cron_tick';
	const SCHEDULE = 'rwga_every_15_minutes';

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedule' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule_event' ), 2 );
		add_action( self::HOOK, array( __CLASS__, 'run_due_rules' ) );
	}

	/**
	 * Ensure cron is scheduled (activation hook).
	 *
	 * @return void
	 */
	public static function activate() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_schedule' ) );
		self::maybe_schedule_event();
	}

	/**
	 * Clear scheduled hook (deactivation).
	 *
	 * @return void
	 */
	public static function deactivate() {
		$ts = wp_next_scheduled( self::HOOK );
		while ( $ts ) {
			wp_unschedule_event( $ts, self::HOOK );
			$ts = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * @param array<string, mixed> $schedules Schedules.
	 * @return array<string, mixed>
	 */
	public static function register_schedule( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}
		$schedules[ self::SCHEDULE ] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => did_action( 'init' )
				? __( 'Every 15 minutes (ReactWoo Geo AI)', 'reactwoo-geo-ai' )
				: 'Every 15 minutes (ReactWoo Geo AI)',
		);
		return $schedules;
	}

	/**
	 * @return void
	 */
	public static function maybe_schedule_event() {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		wp_schedule_event( time() + 120, self::SCHEDULE, self::HOOK );
	}

	/**
	 * Run up to N active scheduled rules that are due.
	 *
	 * @return void
	 */
	public static function run_due_rules() {
		if ( ! class_exists( 'RWGA_DB_Automation_Rules', false ) || ! class_exists( 'RWGA_Automation_Runner', false ) ) {
			return;
		}
		$ids = RWGA_DB_Automation_Rules::get_due_scheduled_ids( 5 );
		foreach ( $ids as $id ) {
			RWGA_Automation_Runner::run( (int) $id );
		}
	}
}
