<?php
/**
 * Geo Assistant action type constants.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Geo_Action_Types {

	const UPDATE_CAMPAIGN_TARGETING = 'update_campaign_targeting';
	const UPDATE_ORIGINAL_TARGETING = 'update_original_targeting';
	const CREATE_VARIANT            = 'create_variant';
	const UPDATE_VARIANT            = 'update_variant';
	const CREATE_RULE               = 'create_rule';
	const UPDATE_RULE               = 'update_rule';
	const CREATE_TEST               = 'create_test';
	const DIAGNOSE                  = 'diagnose';
	const SHOW                      = 'show';
	const HIDE                      = 'hide';

	const PLAN_INTENT = 'geo_targeting_plan';

	const STATUS_DRAFT               = 'draft';
	const STATUS_NEEDS_CONFIRMATION  = 'needs_confirmation';
	const STATUS_NEEDS_CLARIFICATION = 'needs_clarification';
	const STATUS_READY               = 'ready';
	const STATUS_FAILED              = 'failed';

	/**
	 * @return array<int,string>
	 */
	public static function all() {
		return array(
			self::UPDATE_CAMPAIGN_TARGETING,
			self::UPDATE_ORIGINAL_TARGETING,
			self::CREATE_VARIANT,
			self::UPDATE_VARIANT,
			self::CREATE_RULE,
			self::UPDATE_RULE,
			self::CREATE_TEST,
			self::DIAGNOSE,
			self::SHOW,
			self::HIDE,
		);
	}
}
