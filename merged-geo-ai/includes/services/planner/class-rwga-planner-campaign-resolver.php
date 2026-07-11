<?php
/**
 * Extract campaign context from setup phrases.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Campaign_Resolver {

	/**
	 * @param string $phrase Normalised phrase.
	 * @return array{label:string,slug:string}|null
	 */
	public static function detect( $phrase ) {
		return self::detect_from_clause( $phrase );
	}

	/**
	 * @param string $clause Clause or phrase text.
	 * @return array{label:string,slug:string}|null
	 */
	public static function detect_from_clause( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		$patterns = array(
			'/\bfor\s+(?:the\s+)?(.+?)\s+campaign\b/i',
			'/\b(?:set\s+up|setup)\s+(?:the\s+)?(.+?)\s+campaign\b/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( ! preg_match( $pattern, $clause, $m ) ) {
				continue;
			}
			$label = trim( (string) $m[1] );
			if ( '' === $label ) {
				continue;
			}
			return array(
				'label' => $label,
				'slug'  => sanitize_title( $label ),
			);
		}

		return null;
	}

	/**
	 * Resolve a detected campaign phrase against the site's synced campaigns.
	 *
	 * Synced ad campaigns (Google Ads, Meta Ads, GA4, …) are never invented from
	 * natural language. A detected phrase is matched against the registry; an
	 * exact match returns the synced campaign, otherwise an unresolved candidate
	 * (with suggestions) is returned so the assistant can ask for clarification.
	 *
	 * @param string              $clause   Clause text.
	 * @param array<int,array>    $entities Entity rows.
	 * @param array<string,mixed> $context  Planner context (may carry syncedCampaigns).
	 * @return array{matched:array|null,unresolved:array|null}|null
	 */
	public static function resolve_synced( $clause, array $entities = array(), array $context = array() ) {
		$detected = self::detect_from_clause( $clause );
		if ( null === $detected || ! class_exists( 'RWGA_Planner_Synced_Entity_Resolver', false ) ) {
			return null;
		}

		$registry    = RWGA_Planner_Synced_Entity_Resolver::campaigns( $context, $entities );
		$source_hint = self::source_hint( (string) $detected['label'] );
		$resolution  = RWGA_Planner_Synced_Entity_Resolver::resolve_phrase( (string) $detected['label'], $registry, $source_hint );

		if ( RWGA_Planner_Synced_Entity_Resolver::STATUS_MATCHED === $resolution['status'] ) {
			return array(
				'matched'    => array_merge( $resolution['matched'], array( 'raw' => $resolution['raw'] ) ),
				'unresolved' => null,
			);
		}

		return array(
			'matched'    => null,
			'unresolved' => $resolution,
		);
	}

	/**
	 * Infer a campaign source from wording ("facebook"/"google"/"email" …).
	 *
	 * @param string $label Campaign label.
	 * @return string
	 */
	public static function source_hint( $label ) {
		$label = strtolower( (string) $label );
		if ( preg_match( '/\b(?:facebook|meta|instagram|ig)\b/', $label ) ) {
			return 'meta_ads';
		}
		if ( preg_match( '/\b(?:google|adwords|youtube|gads)\b/', $label ) ) {
			return 'google_ads';
		}
		if ( preg_match( '/\b(?:email|newsletter|mailchimp|klaviyo)\b/', $label ) ) {
			return 'email_platform';
		}
		if ( preg_match( '/\b(?:ga4|analytics)\b/', $label ) ) {
			return 'ga4';
		}
		return '';
	}

	/**
	 * @param string $clause Clause text.
	 * @return bool
	 */
	public static function is_campaign_setup_clause( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		return (bool) preg_match( '/\b(?:set\s+up|setup)\s+(?:the\s+)?[\w\s-]+\s+campaign\b/i', $clause );
	}

	/**
	 * @param string $clause Clause text.
	 * @return bool
	 */
	public static function is_campaign_targeting_clause( $clause ) {
		$clause = RWGA_Local_Intent_Interpreter::normalise( $clause );
		return (bool) preg_match( '/\bfor\s+(?:the\s+)?[\w\s-]+\s+campaign\b/i', $clause );
	}
}
