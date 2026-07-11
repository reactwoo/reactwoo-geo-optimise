<?php
/**
 * Split campaign / product narrative phrases into independent action clauses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Narrative_Clause_Splitter {

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array<int,array<string,mixed>>
	 */
	public static function split( $phrase ) {
		$phrase = RWGA_Local_Intent_Interpreter::normalise( $phrase );
		if ( class_exists( 'RWGA_Rule_Plan_Parser', false )
			&& RWGA_Rule_Plan_Parser::is_rule_plan_command( $phrase ) ) {
			return array();
		}
		if ( ! self::is_multi_action_narrative( $phrase ) ) {
			return array();
		}

		$block    = $phrase;
		$trailing = array();

		if ( preg_match( '/^(.*?)(?:,\s*(?:and\s+)?add\s+a\s+rule\s+(?:to\s+)?)(.+)$/is', $block, $m ) ) {
			$block      = trim( (string) $m[1] );
			$trailing[] = array(
				'raw'  => 'add a rule to ' . trim( (string) $m[2] ),
				'type' => 'rule',
			);
		}

		if ( preg_match( '/^(.*?)(?:[,.]\s*then\s+)(create\s+(?:a\s+)?variant\b.+)$/is', $block, $m ) ) {
			$block      = trim( (string) $m[1] );
			$trailing[] = array(
				'raw'  => trim( (string) $m[2] ),
				'type' => 'variant_create',
			);
		} elseif ( preg_match( '/^(.*?)(?:\.\s*then\s+)(create\s+(?:a\s+)?(?:second|third|fourth|\d+(?:st|nd|rd|th)?)\s+(?:version|variant).+)$/is', $block, $m ) ) {
			$block      = trim( (string) $m[1] );
			$trailing[] = array(
				'raw'  => trim( (string) $m[2] ),
				'type' => 'variant_version',
			);
		}

		if ( preg_match( '/^(.*?)(?:,\s*but\s+)((?:don\'t|do not)\s+show\s+.+|hide\s+.+)$/is', $block, $m ) ) {
			$block      = trim( (string) $m[1] );
			$trailing[] = array(
				'raw'  => trim( (string) $m[2] ),
				'type' => 'rule',
			);
		} elseif ( preg_match( '/^(.*?)(?:,\s*but\s+)(hide\s+.+)$/is', $block, $m ) ) {
			$block      = trim( (string) $m[1] );
			$trailing[] = array(
				'raw'  => trim( (string) $m[2] ),
				'type' => 'rule',
			);
		}

		$clauses = array();
		if ( '' !== trim( $block ) ) {
			$clauses[] = array(
				'raw'   => trim( $block ),
				'index' => 0,
				'type'  => self::lead_clause_type( $block ),
			);
		}

		foreach ( array_reverse( $trailing ) as $row ) {
			$clauses[] = array(
				'raw'   => (string) ( $row['raw'] ?? '' ),
				'index' => count( $clauses ),
				'type'  => (string) ( $row['type'] ?? '' ),
			);
		}

		return count( $clauses ) >= 2 ? $clauses : array();
	}

	/**
	 * @param string $phrase Normalised phrase.
	 * @return bool
	 */
	private static function is_multi_action_narrative( $phrase ) {
		if ( preg_match( '/\b(?:set\s+up|setup)\s+.+?\s+campaign\b/i', $phrase ) ) {
			return true;
		}
		if ( preg_match( '/\bfor\s+(?:the\s+)?[\w\s-]+\s+campaign\b/i', $phrase ) ) {
			return (bool) preg_match( '/\b(?:then|but|add\s+a\s+rule)\b/i', $phrase );
		}
		if ( preg_match( '/\bproduct\s+page\b/i', $phrase )
			&& preg_match( '/\b(?:then|add\s+a\s+rule)\b/i', $phrase ) ) {
			return true;
		}
		if ( preg_match( '/\bproduct\s+pages?\b/i', $phrase )
			&& preg_match( '/\b(?:,\s*)?but\s+(?:don\'t|do not)\s+show\b/i', $phrase )
			&& class_exists( 'RWGA_Rule_Plan_Parser', false )
			&& RWGA_Rule_Plan_Parser::has_create_rule_primary_intent( $phrase ) ) {
			return false;
		}
		return false;
	}

	/**
	 * @param string $clause Clause text.
	 * @return string
	 */
	private static function lead_clause_type( $clause ) {
		if ( RWGA_Planner_Campaign_Resolver::is_campaign_targeting_clause( $clause ) ) {
			return 'campaign_targeting';
		}
		if ( RWGA_Planner_Campaign_Resolver::is_campaign_setup_clause( $clause ) ) {
			return 'campaign_targeting';
		}
		return '';
	}
}
