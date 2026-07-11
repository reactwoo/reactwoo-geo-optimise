<?php
/**
 * Validate interpreted plans before confirmation — fail safe when entities are mis-attached.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Plan_Validator {

	/**
	 * @param string                         $phrase   Normalised phrase.
	 * @param array<int,array<string,mixed>> $actions  Built actions.
	 * @param array<int,array>               $entities Entity rows.
	 * @param array<int,array<string,mixed>> $clauses  Clause rows.
	 * @return array<string,mixed>|null Clarification payload or null when valid.
	 */
	public static function validate( $phrase, array $actions, array $entities, array $clauses = array() ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( '' === $phrase || empty( $actions ) ) {
			return null;
		}

		if ( ! self::should_use_safe_failure( $phrase ) ) {
			return null;
		}

		$issues            = array();
		$expected_actions  = self::expected_action_count( $phrase );
		$phrase_entities   = self::scan_phrase_entities( $phrase, $entities );
		$attached          = self::collect_attached_entities( $actions );

		if ( $expected_actions > 1 && count( $actions ) < $expected_actions ) {
			$issues[] = 'action_count_mismatch';
		}

		$declared_variants = self::declared_variant_count( $phrase );
		if ( $declared_variants >= 2 ) {
			$variant_actions = 0;
			foreach ( $actions as $action ) {
				if ( is_array( $action ) && RWGA_Geo_Action_Types::CREATE_VARIANT === (string) ( $action['type'] ?? '' ) ) {
					++$variant_actions;
				}
			}
			if ( $variant_actions < $declared_variants ) {
				$issues[] = 'variant_count_mismatch';
			}
		}

		foreach ( array_keys( $phrase_entities['countries'] ) as $code ) {
			$owners = (array) ( $attached['country_slots'][ $code ] ?? array() );
			if ( count( $owners ) !== 1 ) {
				$issues[] = 'country_' . $code;
			}
		}

		foreach ( $phrase_entities['devices'] as $device ) {
			$owners = (array) ( $attached['device_slots'][ $device ] ?? array() );
			if ( count( $owners ) !== 1 ) {
				$issues[] = 'device_' . $device;
			}
		}

		if ( ! empty( $phrase_entities['utm'] ) && empty( $attached['utm'] ) ) {
			$issues[] = 'utm_unattached';
		}

		if ( ! empty( $phrase_entities['urls'] ) ) {
			foreach ( $phrase_entities['urls'] as $url ) {
				if ( empty( $attached['url_slots'][ $url ] ) ) {
					$issues[] = 'url_' . $url;
				}
			}
		}

		if ( self::has_unresolved_boundary_markers( $phrase, $clauses, $actions ) ) {
			$issues[] = 'unresolved_clause_markers';
		}

		if ( self::has_preview_target_mismatch( $phrase, $actions ) ) {
			$issues[] = 'preview_target_mismatch';
		}

		if ( self::has_existing_rule_mismatch( $phrase, $actions ) ) {
			$issues[] = 'existing_rule_not_update';
		}

		if ( empty( $issues ) ) {
			return null;
		}

		return array(
			'type'               => 'plan_validation_failed',
			'message'            => __( 'I found multiple targeting instructions but need clarification before creating anything.', 'reactwoo-geo-ai' ),
			'unresolved_clauses' => self::unresolved_clauses( $phrase, $clauses, $actions ),
			'issues'             => array_values( array_unique( $issues ) ),
		);
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function expected_action_count( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( class_exists( 'RWGA_Rule_Plan_Parser', false )
			&& RWGA_Rule_Plan_Parser::has_create_rule_primary_intent( $phrase )
			&& ! RWGA_Rule_Plan_Parser::has_explicit_multi_action_boundary( $phrase ) ) {
			return 1;
		}

		$count  = 1;

		if ( preg_match( '/\b(?:,\s*)?but\s+(?:don\'t|do not|hide)\b/i', $phrase ) ) {
			++$count;
		}
		if ( preg_match( '/\b(?:[,.]\s*)?then\s+create\b/i', $phrase ) ) {
			++$count;
		}
		if ( preg_match( '/\b(?:,\s*)?(?:and\s+)?add\s+a\s+rule\b/i', $phrase ) ) {
			++$count;
		}
		if ( preg_match( '/\b(?:,\s*)?also\s+(?:hide|show|create)\b/i', $phrase ) ) {
			++$count;
		}

		return max( 1, $count );
	}

	/**
	 * Number of variants explicitly requested ("two versions of the homepage").
	 *
	 * @param string $phrase Normalised phrase.
	 * @return int
	 */
	public static function declared_variant_count( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( class_exists( 'RWGA_Planner_Parent_Variant_Resolver', false ) ) {
			$parent = RWGA_Planner_Parent_Variant_Resolver::detect_parent( $phrase );
			if ( is_array( $parent ) ) {
				return (int) ( $parent['count'] ?? 0 );
			}
		}
		return 0;
	}

	/**
	 * Only fail-safe on phrases where wrong automation is especially risky.
	 *
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	public static function should_use_safe_failure( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );

		if ( class_exists( 'RWGA_Rule_Plan_Parser', false )
			&& RWGA_Rule_Plan_Parser::is_rule_plan_command( $phrase ) ) {
			return false;
		}

		if ( self::declared_variant_count( $phrase ) >= 2 ) {
			return true;
		}

		if ( preg_match( '/\b(?:preview|test|check|simulate)\b.*\bwould\s+see\b/i', $phrase ) ) {
			return true;
		}

		if ( preg_match( '/\bexisting\s+[\w\s-]+\b(?:variant|rule)\b/i', $phrase ) ) {
			return true;
		}

		if ( class_exists( 'RWGA_Planner_Campaign_Resolver', false )
			&& RWGA_Planner_Campaign_Resolver::is_campaign_targeting_clause( $phrase ) ) {
			return true;
		}

		if ( class_exists( 'RWGA_Planner_Audience_Resolver', false )
			&& ! empty( RWGA_Planner_Audience_Resolver::extract( $phrase ) ) ) {
			return true;
		}

		if ( class_exists( 'RWGA_Planner_Utm_Condition_Resolver', false )
			&& ! empty( RWGA_Planner_Utm_Condition_Resolver::extract( $phrase ) ) ) {
			return true;
		}

		if ( preg_match( '/\b(?:except\s+when|don\'t\s+show\s+it|do not show it)\b/i', $phrase ) ) {
			return true;
		}

		if ( preg_match( '/\bfor\s+(?:the\s+)?[\w\s-]+\s+campaign\b/i', $phrase )
			&& preg_match( '/\b(?:,\s*)?but\s+(?:don\'t|do not)\b/i', $phrase )
			&& preg_match( '/\bthen\s+create\b/i', $phrase ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param string           $phrase   Phrase.
	 * @param array<int,array> $entities Entities.
	 * @return array<string,mixed>
	 */
	private static function scan_phrase_entities( $phrase, array $entities ) {
		$countries = array();
		$regions   = array();

		if ( class_exists( 'RWGA_Planner_Location_Resolver', false ) ) {
			$location = RWGA_Planner_Location_Resolver::resolve_from_text( $phrase, $entities );
			foreach ( (array) ( $location['countries'] ?? array() ) as $code ) {
				$countries[ (string) $code ] = true;
			}
			foreach ( (array) ( $location['regions'] ?? array() ) as $code ) {
				$regions[ (string) $code ] = true;
			}
		} else {
			$parsed = class_exists( 'RWGA_Multi_Variant_Interpreter', false )
				? RWGA_Multi_Variant_Interpreter::parse_country_list( $phrase, $entities )
				: array();
			foreach ( $parsed as $code ) {
				$countries[ (string) $code ] = true;
			}
		}

		$devices = array();
		foreach ( array( 'mobile', 'desktop', 'tablet' ) as $device ) {
			if ( preg_match( '/\b' . preg_quote( $device, '/' ) . '\b/i', $phrase ) ) {
				$devices[] = $device;
			}
		}

		$audiences = class_exists( 'RWGA_Planner_Audience_Resolver', false )
			? RWGA_Planner_Audience_Resolver::extract( $phrase )
			: array();

		$utm = class_exists( 'RWGA_Planner_Utm_Condition_Resolver', false )
			? RWGA_Planner_Utm_Condition_Resolver::extract( $phrase )
			: array();

		$urls = class_exists( 'RWGA_Planner_Url_Condition_Resolver', false )
			? RWGA_Planner_Url_Condition_Resolver::extract_paths( $phrase )
			: array();

		return array(
			'countries' => $countries,
			'regions'   => $regions,
			'devices'   => $devices,
			'audiences' => $audiences,
			'utm'       => $utm,
			'urls'      => $urls,
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $actions Actions.
	 * @return array<string,mixed>
	 */
	private static function collect_attached_entities( array $actions ) {
		$country_slots  = array();
		$region_slots   = array();
		$device_slots   = array();
		$url_slots      = array();
		$utm            = array();

		foreach ( $actions as $idx => $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$slot = 'action_' . ( $idx + 1 );
			$include = RWGA_Planner_Condition_Polarity_Resolver::include_group( (array) ( $action['conditions'] ?? array() ) );
			$exclude = RWGA_Planner_Condition_Polarity_Resolver::exclude_group( (array) ( $action['conditions'] ?? array() ) );

			foreach ( array_merge( (array) ( $include['countries'] ?? array() ), (array) ( $exclude['countries'] ?? array() ) ) as $code ) {
				$country_slots[ (string) $code ]   = (array) ( $country_slots[ (string) $code ] ?? array() );
				$country_slots[ (string) $code ][] = $slot;
			}
			foreach ( array_merge( (array) ( $include['regions'] ?? array() ), (array) ( $exclude['regions'] ?? array() ) ) as $code ) {
				$region_slots[ (string) $code ]   = (array) ( $region_slots[ (string) $code ] ?? array() );
				$region_slots[ (string) $code ][] = $slot;
			}
			foreach ( array_merge( (array) ( $include['devices'] ?? array() ), (array) ( $exclude['devices'] ?? array() ) ) as $device ) {
				$device_slots[ (string) $device ]   = (array) ( $device_slots[ (string) $device ] ?? array() );
				$device_slots[ (string) $device ][] = $slot;
			}
			foreach ( array_merge( (array) ( $include['urls'] ?? array() ), (array) ( $exclude['urls'] ?? array() ) ) as $url ) {
				$url_slots[ (string) $url ]   = (array) ( $url_slots[ (string) $url ] ?? array() );
				$url_slots[ (string) $url ][] = $slot;
			}
			foreach ( self::urls_from_condition_groups( $include ) as $url ) {
				$url_slots[ (string) $url ]   = (array) ( $url_slots[ (string) $url ] ?? array() );
				$url_slots[ (string) $url ][] = $slot;
			}
			foreach ( array_merge( (array) ( $include['utm'] ?? array() ), (array) ( $exclude['utm'] ?? array() ) ) as $row ) {
				if ( is_array( $row ) && ! empty( $row['key'] ) ) {
					$utm[] = $row;
				}
			}
		}

		return array(
			'country_slots'  => $country_slots,
			'region_slots'   => $region_slots,
			'device_slots'   => $device_slots,
			'url_slots'      => $url_slots,
			'utm'            => $utm,
		);
	}

	/**
	 * @param array<string,mixed> $include Include condition group.
	 * @return array<int,string>
	 */
	private static function urls_from_condition_groups( array $include ) {
		$urls = array();
		foreach ( (array) ( $include['condition_groups'] ?? array() ) as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			foreach ( (array) ( $group['conditions'] ?? array() ) as $condition ) {
				if ( ! is_array( $condition ) || 'url' !== (string) ( $condition['type'] ?? '' ) ) {
					continue;
				}
				$value = (string) ( $condition['value'] ?? '' );
				if ( '' !== $value ) {
					$urls[] = $value;
				}
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * @param string                         $phrase   Phrase.
	 * @param array<int,array<string,mixed>> $clauses  Clauses.
	 * @param array<int,array<string,mixed>> $actions  Actions.
	 * @return bool
	 */
	private static function has_unresolved_boundary_markers( $phrase, array $clauses, array $actions ) {
		unset( $actions );
		if ( self::expected_action_count( $phrase ) <= 1 ) {
			return false;
		}
		return count( $clauses ) < self::expected_action_count( $phrase );
	}

	/**
	 * Reject when "preview/test what ... would see on [page]" but the diagnose
	 * action targets a popup/banner (context bleed from a previous clause).
	 *
	 * @param string                         $phrase  Phrase.
	 * @param array<int,array<string,mixed>> $actions Actions.
	 * @return bool
	 */
	private static function has_preview_target_mismatch( $phrase, array $actions ) {
		if ( ! preg_match( '/\b(?:preview|test|check|simulate)\b.*\bwould\s+see\s+(?:on|in)\s+(?:the\s+)?[\w\s-]*?\bpage\b/i', $phrase ) ) {
			return false;
		}

		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$type = (string) ( $action['type'] ?? '' );
			if ( RWGA_Geo_Action_Types::DIAGNOSE !== $type && RWGA_Geo_Action_Types::CREATE_TEST !== $type ) {
				continue;
			}
			$target_type = (string) ( $action['target']['type'] ?? '' );
			if ( in_array( $target_type, array( 'popup', 'banner' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * When the phrase says "update the existing [name] rule" there must be an
	 * update_rule action targeting a rule — not a generic page/create_rule.
	 *
	 * @param string                         $phrase  Phrase.
	 * @param array<int,array<string,mixed>> $actions Actions.
	 * @return bool
	 */
	private static function has_existing_rule_mismatch( $phrase, array $actions ) {
		if ( ! preg_match( '/\b(?:update|change|edit|modify|tweak|adjust)\s+(?:the\s+)?existing\s+[\w\s-]*?\brule\b/i', $phrase ) ) {
			return false;
		}

		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			if ( RWGA_Geo_Action_Types::UPDATE_RULE === (string) ( $action['type'] ?? '' )
				&& 'rule' === (string) ( $action['target']['type'] ?? '' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string                         $phrase   Phrase.
	 * @param array<int,array<string,mixed>> $clauses  Clauses.
	 * @param array<int,array<string,mixed>> $actions  Actions.
	 * @return array<int,string>
	 */
	private static function unresolved_clauses( $phrase, array $clauses, array $actions ) {
		unset( $actions );
		$unresolved = array();
		if ( count( $clauses ) >= self::expected_action_count( $phrase ) ) {
			return $unresolved;
		}

		$parts = preg_split(
			'/\s*(?:,\s*but\s+(?:don\'t|do not|hide)|[,.]\s*then\s+create|,\s*(?:and\s+)?add\s+a\s+rule)\s*/i',
			$phrase,
			-1,
			PREG_SPLIT_NO_EMPTY
		);
		if ( ! is_array( $parts ) ) {
			return array( $phrase );
		}

		$known = array();
		foreach ( $clauses as $row ) {
			$known[] = RWGA_Local_Intent_Interpreter::normalise( (string) ( $row['raw'] ?? '' ) );
		}

		foreach ( $parts as $part ) {
			$part = trim( (string) $part );
			if ( '' === $part ) {
				continue;
			}
			$found = false;
			foreach ( $known as $clause ) {
				if ( false !== strpos( $clause, $part ) || false !== strpos( $part, $clause ) ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$unresolved[] = $part;
			}
		}

		return $unresolved;
	}
}
