<?php
/**
 * Detect geo action type from a single clause.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Action_Type_Detector {

	/**
	 * @param string              $clause     Normalised clause.
	 * @param array<string,mixed> $clause_row Clause metadata.
	 * @return array{type:string,visibility:string,mode:string,confidence:float}
	 */
	public static function detect( $clause, array $clause_row = array() ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$visibility = 'show';
		$mode       = 'update';

		if ( RWGA_Planner_Campaign_Resolver::is_campaign_targeting_clause( $clause ) ) {
			$visibility = self::is_only_show_clause( $clause ) ? 'only_show' : 'show';
			return array(
				'type'       => RWGA_Geo_Action_Types::UPDATE_CAMPAIGN_TARGETING,
				'visibility' => $visibility,
				'mode'       => 'update',
				'confidence' => 0.92,
			);
		}

		if ( 'campaign_targeting' === (string) ( $clause_row['type'] ?? '' ) ) {
			$visibility = self::is_only_show_clause( $clause ) ? 'only_show' : 'show';
			return array(
				'type'       => RWGA_Geo_Action_Types::UPDATE_CAMPAIGN_TARGETING,
				'visibility' => $visibility,
				'mode'       => 'update',
				'confidence' => 0.92,
			);
		}

		if ( preg_match( '/\b(?:update|change|edit|modify|tweak|adjust)\s+(?:the\s+)?(?:homepage|shop(?:\s+page)?)\b/i', $clause )
			&& ! preg_match( '/\b(?:create|make|build|add)\s+(?:a\s+)?(?:new\s+)?rule\b/i', $clause ) ) {
			$visibility = self::is_only_show_clause( $clause ) ? 'only_show' : 'show';
			return array(
				'type'       => RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING,
				'visibility' => $visibility,
				'mode'       => 'update',
				'confidence' => 0.9,
			);
		}

		if ( preg_match( '/\b(?:update|change|edit|modify|tweak|adjust)\s+(?:the\s+)?(?:existing\s+)?[\w\s-]*?\bvariant\b/i', $clause )
			&& ! preg_match( '/\b(?:create|make|build|add|duplicate)\b/i', $clause ) ) {
			$visibility = self::is_only_show_clause( $clause ) ? 'only_show' : 'show';
			return array(
				'type'       => RWGA_Geo_Action_Types::UPDATE_VARIANT,
				'visibility' => $visibility,
				'mode'       => 'update',
				'confidence' => 0.9,
			);
		}

		if ( preg_match( '/\b(?:update|change|edit|modify|tweak|adjust)\s+(?:the\s+)?(?:existing\s+)?[\w\s-]*?\brule\b/i', $clause )
			&& ! preg_match( '/\b(?:create|make|build|add)\s+(?:a\s+)?(?:new\s+)?rule\b/i', $clause ) ) {
			$visibility = preg_match( '/\bonly\s+appl(?:y|ies)\b|\bappl(?:y|ies)\s+only\b/i', $clause )
				? 'only_apply'
				: ( self::is_only_show_clause( $clause ) ? 'only_show' : 'apply' );
			return array(
				'type'       => RWGA_Geo_Action_Types::UPDATE_RULE,
				'visibility' => $visibility,
				'mode'       => 'update',
				'confidence' => 0.9,
			);
		}

		if ( 'variant_version' === (string) ( $clause_row['type'] ?? '' )
			|| 'variant_create' === (string) ( $clause_row['type'] ?? '' )
			|| ( class_exists( 'RWGA_Planner_Second_Version_Resolver', false )
				&& RWGA_Planner_Second_Version_Resolver::detect( $clause ) ) ) {
			return array(
				'type'       => RWGA_Geo_Action_Types::CREATE_VARIANT,
				'visibility' => 'show',
				'mode'       => 'create',
				'confidence' => 0.9,
			);
		}

		if ( 'test' === (string) ( $clause_row['type'] ?? '' ) ) {
			return array(
				'type'       => RWGA_Geo_Action_Types::CREATE_TEST,
				'visibility' => 'show',
				'mode'       => 'test',
				'confidence' => 0.9,
			);
		}

		if ( 'diagnose' === (string) ( $clause_row['type'] ?? '' ) ) {
			return array(
				'type'       => RWGA_Geo_Action_Types::DIAGNOSE,
				'visibility' => 'show',
				'mode'       => 'diagnose',
				'confidence' => 0.9,
			);
		}

		if ( 'rule' === (string) ( $clause_row['type'] ?? '' )
			|| preg_match( '/\b(?:create|add|set\s+up|build)\s+(?:a\s+)?(?:(?:targeting|[\w-]+)\s+)?rule\b/i', $clause ) ) {
			$has_positive_show = (bool) preg_match(
				'/\b(?:show\s+it\s+only|only\s+trigger|only\s+show|shows?\s+only|only\s+shows?)\b/i',
				$clause
			);
			if ( ! $has_positive_show
				&& ( preg_match( '/\b(?:don\'t|do not)\s+show\s+it\b/i', $clause )
					|| ( preg_match( '/\b(?:hide|block)\b/i', $clause )
						&& ! preg_match( '/\b(?:show|display)\b/i', $clause ) ) ) ) {
				return array(
					'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
					'visibility' => 'hide',
					'mode'       => 'create',
					'confidence' => 0.9,
				);
			}
			$visibility = 'show';
			if ( self::is_only_show_clause( $clause )
				|| preg_match( '/\bshow\s+it\s+only\b/i', $clause )
				|| preg_match( '/\bonly\s+trigger\b/i', $clause ) ) {
				$visibility = 'only_show';
			} elseif ( preg_match( '/\b(?:hide|block)\s+(?:the\s+)?/i', $clause )
				&& ! preg_match( '/\b(?:show|display|only\s+show|only\s+trigger)\b/i', $clause ) ) {
				$visibility = 'hide';
			}
			return array(
				'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
				'visibility' => $visibility,
				'mode'       => 'create',
				'confidence' => 0.9,
			);
		}

		if ( 'variant_child' === (string) ( $clause_row['type'] ?? '' )
			|| ( class_exists( 'RWGA_Planner_Parent_Variant_Resolver', false )
				&& RWGA_Planner_Parent_Variant_Resolver::is_variant_child_clause( $clause ) ) ) {
			$visibility = preg_match( '/\b(?:only|just)\s+(?:show|display)\b|\bshow\s+only\b|\bonly\s+show\b/i', $clause )
				? 'only_show'
				: 'show';
			return array(
				'type'       => RWGA_Geo_Action_Types::CREATE_VARIANT,
				'visibility' => $visibility,
				'mode'       => 'create',
				'confidence' => 0.9,
			);
		}

		if ( preg_match( '/\b(?:test|preview|simulate|check what)\b/i', $clause ) ) {
			return array(
				'type'       => RWGA_Geo_Action_Types::CREATE_TEST,
				'visibility' => 'show',
				'mode'       => 'test',
				'confidence' => 0.86,
			);
		}
		if ( preg_match( '/\b(?:diagnose|debug|troubleshoot|why (?:is|does))\b/i', $clause ) ) {
			return array(
				'type'       => RWGA_Geo_Action_Types::DIAGNOSE,
				'visibility' => 'show',
				'mode'       => 'diagnose',
				'confidence' => 0.84,
			);
		}
		if ( preg_match( '/\b(?:don\'t|do not)\s+show\s+it\b/i', $clause ) ) {
			return array(
				'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
				'visibility' => 'hide',
				'mode'       => 'create',
				'confidence' => 0.92,
			);
		}
		if ( preg_match( '/\b(?:hide|exclude|block|do not show|don\'t show)\s+it\s+from\b/i', $clause ) ) {
			return array(
				'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
				'visibility' => 'hide',
				'mode'       => 'create',
				'confidence' => 0.9,
			);
		}
		if ( preg_match( '/\b(?:hide|exclude|block|do not show|don\'t show)\b/i', $clause ) ) {
			if ( self::is_create_rule_condition_clause( $clause, $clause_row ) ) {
				$visibility = self::is_only_show_clause( $clause ) ? 'only_show' : 'show';
				return array(
					'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
					'visibility' => $visibility,
					'mode'       => 'create',
					'confidence' => 0.9,
				);
			}
			$visibility = 'hide';
			$mode       = 'create';
			if ( preg_match( '/\b(?:popup|banner)\b/i', $clause ) ) {
				return array(
					'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
					'visibility' => $visibility,
					'mode'       => $mode,
					'confidence' => 0.88,
				);
			}
			return array(
				'type'       => RWGA_Geo_Action_Types::HIDE,
				'visibility' => $visibility,
				'mode'       => $mode,
				'confidence' => 0.82,
			);
		}
		if ( self::mentions_original_version( $clause ) ) {
			if ( preg_match( '/\b(?:only|just)\s+(?:show|display)\b|\bonly\s+show\b/i', $clause ) ) {
				$visibility = 'only_show';
			}
			return array(
				'type'       => RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING,
				'visibility' => $visibility,
				'mode'       => 'update',
				'confidence' => 0.9,
			);
		}
		if ( preg_match( '/\b(?:create|make|build|duplicate|copy|clone)\s+(?:an?\s+)?(?:additional\s+)?(?:\d+|one|two|three|four|five)?\s*(?:new\s+)?(?:variants?|variations?|versions?)\b/i', $clause )
			|| preg_match( '/\b(?:create|make|build|duplicate|copy|clone)\b[\w\s-]*?\b(?:variants?|variations?|versions?)\b/i', $clause )
			|| preg_match( '/\b(?:one|another)\s+(?:variant|variation|version)\b/i', $clause ) ) {
			$visibility = self::is_only_show_clause( $clause ) ? 'only_show' : 'show';
			return array(
				'type'       => RWGA_Geo_Action_Types::CREATE_VARIANT,
				'visibility' => $visibility,
				'mode'       => 'create',
				'confidence' => 0.88,
			);
		}
		if ( preg_match( '/\b(?:show|display|target|visible)\b/i', $clause ) ) {
			if ( preg_match( '/\b(?:only|just)\b/i', $clause ) ) {
				$visibility = 'only_show';
			}
			if ( self::is_create_rule_condition_clause( $clause, $clause_row ) ) {
				return array(
					'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
					'visibility' => $visibility,
					'mode'       => 'create',
					'confidence' => 0.9,
				);
			}
			if ( self::mentions_original_version( $clause ) ) {
				return array(
					'type'       => RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING,
					'visibility' => $visibility,
					'mode'       => 'update',
					'confidence' => 0.84,
				);
			}
			if ( preg_match( '/\b(?:popup|banner|rule)\b/i', $clause ) ) {
				return array(
					'type'       => RWGA_Geo_Action_Types::CREATE_RULE,
					'visibility' => $visibility,
					'mode'       => 'create',
					'confidence' => 0.85,
				);
			}
			return array(
				'type'       => RWGA_Geo_Action_Types::SHOW,
				'visibility' => $visibility,
				'mode'       => $mode,
				'confidence' => 0.72,
			);
		}
		return array(
			'type'       => RWGA_Geo_Action_Types::SHOW,
			'visibility' => $visibility,
			'mode'       => $mode,
			'confidence' => 0.5,
		);
	}

	/**
	 * @param string $clause Clause text.
	 * @return bool
	 */
	private static function is_only_show_clause( $clause ) {
		return (bool) preg_match(
			'/\bonly\s+(?:to|show|display|for|in)\b|\bshows?\s+only\b|\bonly\s+shows?\b|\bdisplays?\s+only\b/i',
			$clause
		);
	}

	/**
	 * Whether the user explicitly refers to the original / source / default version.
	 *
	 * @param string $clause Clause text.
	 * @return bool
	 */
	private static function mentions_original_version( $clause ) {
		return (bool) preg_match(
			'/\b(?:update|keep|leave)\s+(?:the\s+)?original\b|\boriginal\s+(?:version|targeting|page)\b|\bexisting\s+version\b|\bsource\s+page\b|\bdefault\s+version\b/i',
			$clause
		);
	}

	/**
	 * Show / exclude / only-trigger phrasing inside a create_rule journey — not a separate action.
	 *
	 * @param string              $clause     Clause text.
	 * @param array<string,mixed> $clause_row Clause metadata.
	 * @return bool
	 */
	private static function is_create_rule_condition_clause( $clause, array $clause_row = array() ) {
		if ( 'rule' === (string) ( $clause_row['type'] ?? '' ) ) {
			return true;
		}
		if ( preg_match( '/\b(?:show|display)\b.+\b(?:but\s+)?(?:exclude|not)\b/is', $clause ) ) {
			return true;
		}
		if ( preg_match( '/\bshow\s+it\s+only\b|\bonly\s+(?:show|trigger)\b/i', $clause ) ) {
			return true;
		}
		if ( preg_match( '/\b(?:exclude|do not show|don\'t show)\s+(?:visitors?\s+)?from\b/i', $clause ) ) {
			return true;
		}
		return false;
	}
}
