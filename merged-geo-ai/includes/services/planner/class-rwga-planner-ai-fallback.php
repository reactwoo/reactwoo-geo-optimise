<?php
/**
 * AI fallback for low-confidence planner output (interpretation only).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Ai_Fallback {

	const CONFIDENCE_THRESHOLD = 0.72;

	/**
	 * @param string              $user_text User text.
	 * @param array<string,mixed> $local_plan Local plan.
	 * @param array<string,mixed> $context Context.
	 * @param array<int,array>    $entities Entities.
	 * @return array<string,mixed>|null Improved plan or null.
	 */
	public static function maybe_improve( $user_text, array $local_plan, array $context, array $entities ) {
		if ( ! self::should_call( $local_plan ) ) {
			return null;
		}

		$payload = array(
			'userText'              => $user_text,
			'localPlan'             => $local_plan,
			'unresolved'            => self::unresolved_items( $local_plan ),
			'availableCapabilities' => self::capabilities(),
			'knownPages'            => array( 'homepage', 'shop', 'pricing', 'contact' ),
			'knownPopups'           => array(),
			'knownCampaigns'        => array(),
		);

		/**
		 * Allow remote AI to refine a geo targeting plan.
		 *
		 * @param array<string,mixed>|null $ai_plan  AI plan or null.
		 * @param array<string,mixed>      $payload  Compact payload.
		 * @param array<string,mixed>      $context  Context.
		 */
		$ai_plan = apply_filters( 'rwga_geo_assistant_ai_plan', null, $payload, $context );
		if ( ! is_array( $ai_plan ) ) {
			return null;
		}
		if ( ! self::validate_plan_schema( $ai_plan ) ) {
			return null;
		}
		unset( $entities );
		$ai_plan['debug'] = is_array( $ai_plan['debug'] ?? null ) ? $ai_plan['debug'] : array();
		$ai_plan['debug']['decisions'][] = 'ai_fallback_applied';
		return $ai_plan;
	}

	/**
	 * @param array<string,mixed> $plan Plan.
	 * @return bool
	 */
	public static function should_call( array $plan ) {
		if ( (float) ( $plan['confidence'] ?? 0 ) < self::CONFIDENCE_THRESHOLD ) {
			return true;
		}
		if ( RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION === (string) ( $plan['status'] ?? '' ) ) {
			return true;
		}
		if ( ! empty( $plan['clarification'] ) ) {
			return true;
		}
		if ( ! empty( $plan['debug']['unresolved_entities'] ) ) {
			return true;
		}
		return ! empty( $plan['debug']['action_count_mismatch'] );
	}

	/**
	 * @param array<string,mixed> $plan Plan.
	 * @return array<int,string>
	 */
	private static function unresolved_items( array $plan ) {
		$items = array();
		if ( ! empty( $plan['clarification']['type'] ) ) {
			$items[] = (string) $plan['clarification']['type'];
		}
		foreach ( (array) ( $plan['warnings'] ?? array() ) as $warning ) {
			$items[] = (string) $warning;
		}
		return array_values( array_filter( array_unique( $items ) ) );
	}

	/**
	 * @return array<string,bool>
	 */
	private static function capabilities() {
		return array(
			'countryTargeting'   => true,
			'regionalTargeting'  => class_exists( 'RWGA_Site_Interpretation_Preferences', false )
				&& RWGA_Site_Interpretation_Preferences::region_targeting_available(),
			'variants'           => true,
			'rules'              => true,
			'weather'            => true,
			'tests'              => true,
		);
	}

	/**
	 * @param array<string,mixed> $plan Plan.
	 * @return bool
	 */
	public static function validate_plan_schema( array $plan ) {
		if ( RWGA_Geo_Action_Types::PLAN_INTENT !== (string) ( $plan['intent'] ?? '' ) ) {
			return false;
		}
		if ( ! isset( $plan['actions'] ) || ! is_array( $plan['actions'] ) ) {
			return false;
		}
		foreach ( $plan['actions'] as $action ) {
			if ( ! is_array( $action ) || empty( $action['type'] ) ) {
				return false;
			}
		}
		return true;
	}
}
