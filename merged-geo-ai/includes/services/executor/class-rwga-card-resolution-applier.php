<?php
/**
 * Apply user card resolutions to a stored multi-action plan.
 *
 * The targeting assistant renders each action as a review card with field-level
 * controls. When the user chooses a synced entity, ignores a field, or removes
 * an action, the client sends those decisions to the execute endpoint. This
 * service applies them to the stored planner actions so the plan can be
 * re-validated server-side before anything is created.
 *
 * Resolution row shape (from the client):
 *   { card: int, field: 'target'|'campaign'|'audience', raw: string,
 *     action: 'choose'|'ignore'|'remove_action', id?: string, label?: string }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Card_Resolution_Applier {

	/**
	 * @param array<int,array<string,mixed>> $actions     Planner actions.
	 * @param array<int,array<string,mixed>> $resolutions Client resolution rows.
	 * @return array<int,array<string,mixed>>
	 */
	public static function apply( array $actions, array $resolutions ) {
		$removed = array();
		$by_card = array();

		foreach ( $resolutions as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$card = (int) ( $row['card'] ?? -1 );
			$kind = (string) ( $row['action'] ?? '' );
			if ( 'remove_action' === $kind || 'remove' === $kind ) {
				$removed[ $card ] = true;
				continue;
			}
			$by_card[ $card ][] = $row;
		}

		$out = array();
		foreach ( array_values( $actions ) as $index => $action ) {
			if ( ! is_array( $action ) || isset( $removed[ $index ] ) ) {
				continue;
			}
			if ( isset( $by_card[ $index ] ) ) {
				$action = self::apply_to_action( $action, $by_card[ $index ] );
			}
			$out[] = $action;
		}

		return array_values( $out );
	}

	/**
	 * @param array<string,mixed>            $action Action.
	 * @param array<int,array<string,mixed>> $rows   Field resolution rows for this action.
	 * @return array<string,mixed>
	 */
	private static function apply_to_action( array $action, array $rows ) {
		foreach ( $rows as $row ) {
			$field = (string) ( $row['field'] ?? '' );
			$kind  = (string) ( $row['action'] ?? '' );
			$raw   = (string) ( $row['raw'] ?? '' );
			$id    = (string) ( $row['id'] ?? '' );
			$label = (string) ( $row['label'] ?? '' );

			if ( 'target' === $field ) {
				$action = self::apply_target( $action, $kind, $id, $label );
			} elseif ( 'campaign' === $field ) {
				$action = self::apply_campaign( $action, $kind, $id, $label, $raw );
			} elseif ( 'audience' === $field ) {
				$action = self::apply_audience( $action, $kind, $id, $label, $raw );
			} elseif ( 'location' === $field ) {
				$action = self::apply_location( $action, $kind, $id, $raw );
			} elseif ( 'traffic_source' === $field ) {
				$action = self::apply_traffic_source( $action, $kind, $id, $label, $raw );
			} elseif ( 'logic' === $field ) {
				$operator = 'OR' === strtoupper( (string) $id ) ? 'any' : 'all';
				$action['condition_match'] = $operator;
			}
		}
		return $action;
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @param string              $kind   choose|ignore.
	 * @param string              $id     Chosen id.
	 * @param string              $label  Chosen label.
	 * @return array<string,mixed>
	 */
	private static function apply_target( array $action, $kind, $id, $label ) {
		if ( ! is_array( $action['target'] ?? null ) ) {
			$action['target'] = array();
		}
		if ( 'choose' === $kind ) {
			$type = (string) ( $action['target']['type'] ?? 'page' );
			$action['target']['user_resolved'] = array(
				'id'   => '' !== $id ? $id : sanitize_title( $label ),
				'name' => '' !== $label ? $label : (string) ( $action['target']['label'] ?? '' ),
				'type' => $type,
			);
			if ( '' !== $label ) {
				$action['target']['label'] = $label;
			}
			unset( $action['target']['user_ignored'] );
		} else {
			$action['target']['user_ignored'] = true;
			unset( $action['target']['user_resolved'] );
		}
		return $action;
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @param string              $kind   choose|ignore.
	 * @param string              $id     Chosen id.
	 * @param string              $label  Chosen label.
	 * @param string              $raw    Raw phrase.
	 * @return array<string,mixed>
	 */
	private static function apply_campaign( array $action, $kind, $id, $label, $raw ) {
		self::clear_unresolved( $action, 'campaigns', $raw );
		if ( 'choose' === $kind ) {
			$action['campaign'] = array(
				'id'     => '' !== $id ? $id : sanitize_title( $label ),
				'name'   => $label,
				'source' => '',
			);
		} else {
			unset( $action['campaign'] );
		}
		return $action;
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @param string              $kind   choose|ignore.
	 * @param string              $id     Chosen id.
	 * @param string              $label  Chosen label.
	 * @param string              $raw    Raw phrase.
	 * @return array<string,mixed>
	 */
	private static function apply_audience( array $action, $kind, $id, $label, $raw ) {
		self::clear_unresolved( $action, 'audiences', $raw );
		if ( 'choose' === $kind ) {
			if ( ! is_array( $action['conditions'] ?? null ) ) {
				$action['conditions'] = array();
			}
			if ( ! is_array( $action['conditions']['include'] ?? null ) ) {
				$action['conditions']['include'] = array();
			}
			if ( ! is_array( $action['conditions']['include']['audiences'] ?? null ) ) {
				$action['conditions']['include']['audiences'] = array();
			}
			$action['conditions']['include']['audiences'][] = array(
				'id'     => '' !== $id ? $id : sanitize_title( $label ),
				'name'   => $label,
				'source' => '',
				'raw'    => $raw,
			);
		}
		return $action;
	}

	/**
	 * Apply a country/region decision for an ambiguous nation (e.g. England).
	 *
	 * The chosen id encodes the decision as "type:code" (e.g. "country:GB" or
	 * "region:GB-ENG"). "ignore"/remove simply drops the unresolved location.
	 *
	 * @param array<string,mixed> $action Action.
	 * @param string              $kind   choose|ignore.
	 * @param string              $id     Encoded decision "type:code".
	 * @param string              $raw    Raw nation phrase.
	 * @return array<string,mixed>
	 */
	private static function apply_location( array $action, $kind, $id, $raw ) {
		self::clear_unresolved( $action, 'locations', $raw );
		if ( 'choose' !== $kind ) {
			return $action;
		}
		$parts = explode( ':', (string) $id, 2 );
		$type  = strtolower( trim( $parts[0] ?? '' ) );
		$code  = trim( $parts[1] ?? '' );
		if ( '' === $code ) {
			return $action;
		}
		if ( ! is_array( $action['conditions'] ?? null ) ) {
			$action['conditions'] = array();
		}
		if ( ! is_array( $action['conditions']['include'] ?? null ) ) {
			$action['conditions']['include'] = array();
		}
		$bucket = 'country' === $type ? 'countries' : 'regions';
		$code   = 'country' === $type ? strtoupper( $code ) : strtoupper( $code );
		if ( ! is_array( $action['conditions']['include'][ $bucket ] ?? null ) ) {
			$action['conditions']['include'][ $bucket ] = array();
		}
		$action['conditions']['include'][ $bucket ][] = $code;
		$action['conditions']['include'][ $bucket ]   = array_values( array_unique( $action['conditions']['include'][ $bucket ] ) );
		return $action;
	}

	/**
	 * Apply a Google Ads / traffic-source UTM mapping decision.
	 *
	 * @param array<string,mixed> $action Action.
	 * @param string              $kind   choose|ignore.
	 * @param string              $id     Mapping option key.
	 * @param string              $label  Human label.
	 * @param string              $raw    Raw phrase from the review card.
	 * @return array<string,mixed>
	 */
	private static function apply_traffic_source( array $action, $kind, $id, $label, $raw ) {
		self::clear_traffic_unresolved( $action, $raw, 'choose' === $kind );
		if ( 'choose' !== $kind ) {
			self::remove_traffic_from_condition_groups( $action );
			return $action;
		}

		$mapping_key = '' !== $id ? (string) $id : sanitize_title( $label );
		$mapping_label = '' !== $label ? $label : $mapping_key;
		$action['traffic_mapping'] = array(
			'key'   => $mapping_key,
			'label' => $mapping_label,
		);

		self::resolve_traffic_in_condition_groups( $action, $mapping_key, $mapping_label );

		if ( ! is_array( $action['conditions'] ?? null ) ) {
			$action['conditions'] = array();
		}
		if ( ! is_array( $action['conditions']['include'] ?? null ) ) {
			$action['conditions']['include'] = array();
		}
		if ( ! is_array( $action['conditions']['include']['utm'] ?? null ) ) {
			$action['conditions']['include']['utm'] = array();
		}
		foreach ( self::utm_rows_from_mapping_key( $mapping_key ) as $utm ) {
			$action['conditions']['include']['utm'][] = $utm;
		}
		$action['conditions']['include']['utm'] = self::dedupe_utm_rows( $action['conditions']['include']['utm'] );

		return $action;
	}

	/**
	 * @param string $mapping_key Option key from the resolver UI.
	 * @return array<int,array{key:string,field:string,value:string}>
	 */
	private static function utm_rows_from_mapping_key( $mapping_key ) {
		switch ( (string) $mapping_key ) {
			case 'utm_source_google':
				return array( array( 'key' => 'utm_source', 'field' => 'source', 'value' => 'google' ) );
			case 'utm_medium_cpc':
				return array( array( 'key' => 'utm_medium', 'field' => 'medium', 'value' => 'cpc' ) );
			case 'utm_source_google_and_medium_cpc':
				return array(
					array( 'key' => 'utm_source', 'field' => 'source', 'value' => 'google' ),
					array( 'key' => 'utm_medium', 'field' => 'medium', 'value' => 'cpc' ),
				);
			case 'gclid_exists':
				return array( array( 'key' => 'gclid', 'field' => 'gclid', 'value' => 'exists' ) );
			default:
				return array();
		}
	}

	/**
	 * @param array<string,mixed> $action Action (by reference).
	 * @param string              $raw    Raw phrase.
	 * @return void
	 */
	private static function clear_traffic_unresolved( array &$action, $raw, $force_all = false ) {
		if ( empty( $action['unresolved']['traffic_sources'] ) || ! is_array( $action['unresolved']['traffic_sources'] ) ) {
			return;
		}
		if ( $force_all ) {
			$action['unresolved']['traffic_sources'] = array();
			return;
		}
		$needle = self::normalise( $raw );
		$kept   = array();
		foreach ( $action['unresolved']['traffic_sources'] as $entry ) {
			if ( ! is_array( $entry ) ) {
				$kept[] = $entry;
				continue;
			}
			$entry_raw = (string) ( $entry['label'] ?? $entry['value'] ?? '' );
			if ( '' !== $needle && self::normalise( $entry_raw ) === $needle ) {
				continue;
			}
			if ( 'google_ads' === (string) ( $entry['value'] ?? '' ) && ( '' === $needle || false !== strpos( $needle, 'google ads' ) ) ) {
				continue;
			}
			$kept[] = $entry;
		}
		$action['unresolved']['traffic_sources'] = array_values( $kept );
	}

	/**
	 * @param array<string,mixed> $action        Action (by reference).
	 * @param string              $mapping_key   Mapping key.
	 * @param string              $mapping_label Display label.
	 * @return void
	 */
	private static function resolve_traffic_in_condition_groups( array &$action, $mapping_key, $mapping_label ) {
		if ( ! is_array( $action['conditions']['include']['condition_groups'] ?? null ) ) {
			return;
		}
		foreach ( $action['conditions']['include']['condition_groups'] as &$group ) {
			if ( ! is_array( $group['conditions'] ?? null ) ) {
				continue;
			}
			foreach ( $group['conditions'] as &$child ) {
				if ( 'traffic_source' !== (string) ( $child['type'] ?? '' ) ) {
					continue;
				}
				$child['status']      = 'valid';
				$child['mapping_key'] = $mapping_key;
				if ( '' !== $mapping_label ) {
					$child['label'] = $mapping_label;
				}
				unset( $child['warning'], $child['resolution_options'] );
			}
			unset( $child );
		}
		unset( $group );
	}

	/**
	 * @param array<string,mixed> $action Action (by reference).
	 * @return void
	 */
	private static function remove_traffic_from_condition_groups( array &$action ) {
		if ( ! is_array( $action['conditions']['include']['condition_groups'] ?? null ) ) {
			return;
		}
		$groups = array();
		foreach ( $action['conditions']['include']['condition_groups'] as $group ) {
			if ( ! is_array( $group['conditions'] ?? null ) ) {
				$groups[] = $group;
				continue;
			}
			$children = array();
			foreach ( $group['conditions'] as $child ) {
				if ( 'traffic_source' === (string) ( $child['type'] ?? '' ) ) {
					continue;
				}
				$children[] = $child;
			}
			if ( empty( $children ) ) {
				continue;
			}
			$group['conditions'] = array_values( $children );
			$groups[]            = $group;
		}
		$action['conditions']['include']['condition_groups'] = array_values( $groups );
	}

	/**
	 * @param array<int,array{key:string,field:string,value:string}> $rows UTM rows.
	 * @return array<int,array{key:string,field:string,value:string}>
	 */
	private static function dedupe_utm_rows( array $rows ) {
		$seen = array();
		$out  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = (string) ( $row['key'] ?? '' ) . '=' . (string) ( $row['value'] ?? '' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $row;
		}
		return $out;
	}

	/**
	 * Remove an unresolved entity row (campaign/audience/location) matching a raw phrase.
	 *
	 * @param array<string,mixed> $action Action (by reference).
	 * @param string              $type   'campaigns'|'audiences'|'locations'.
	 * @param string              $raw    Raw phrase to drop.
	 * @return void
	 */
	private static function clear_unresolved( array &$action, $type, $raw ) {
		if ( empty( $action['unresolved'][ $type ] ) || ! is_array( $action['unresolved'][ $type ] ) ) {
			return;
		}
		$needle = self::normalise( $raw );
		$kept   = array();
		foreach ( $action['unresolved'][ $type ] as $entry ) {
			$entry_raw = is_array( $entry ) ? (string) ( $entry['raw'] ?? '' ) : (string) $entry;
			if ( '' !== $needle && self::normalise( $entry_raw ) === $needle ) {
				continue;
			}
			$kept[] = $entry;
		}
		$action['unresolved'][ $type ] = array_values( $kept );
	}

	/**
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function normalise( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return (string) preg_replace( '/\s+/', ' ', $value );
	}
}
