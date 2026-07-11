<?php
/**
 * Custom capabilities for Geo AI workflows.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers rwga_* caps and maps them to default roles.
 */
class RWGA_Capabilities {

	const CAP_MANAGE_AI       = 'rwga_manage_ai';
	const CAP_RUN_AI          = 'rwga_run_ai';
	const CAP_VIEW_REPORTS    = 'rwga_view_ai_reports';
	const CAP_MANAGE_AUTOMATIONS = 'rwga_manage_automations';

	/**
	 * Add caps to roles (idempotent).
	 *
	 * @return void
	 */
	public static function install() {
		$roles = array(
			'administrator' => array(
				self::CAP_MANAGE_AI,
				self::CAP_RUN_AI,
				self::CAP_VIEW_REPORTS,
				self::CAP_MANAGE_AUTOMATIONS,
			),
			'editor'          => array(
				self::CAP_RUN_AI,
				self::CAP_VIEW_REPORTS,
			),
			'shop_manager'    => array(
				self::CAP_RUN_AI,
				self::CAP_VIEW_REPORTS,
				self::CAP_MANAGE_AUTOMATIONS,
			),
		);

		foreach ( $roles as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Whether the current user may run bounded AI workflows (REST + admin actions).
	 *
	 * @return bool
	 */
	public static function current_user_can_run_ai() {
		return current_user_can( self::CAP_RUN_AI );
	}

	/**
	 * Whether the current user may manage plugin AI settings (future use).
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_ai() {
		return current_user_can( self::CAP_MANAGE_AI );
	}

	/**
	 * Whether the current user may create or edit automation rules.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_automations() {
		return current_user_can( self::CAP_MANAGE_AUTOMATIONS );
	}
}
