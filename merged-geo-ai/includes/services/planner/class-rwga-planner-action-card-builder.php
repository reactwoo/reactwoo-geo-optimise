<?php
/**
 * Build per-action review cards with explicit resolution status.
 *
 * Each card turns a parsed action into a reviewable unit: target, campaign,
 * audiences, include/exclude conditions, warnings and — crucially — the list of
 * fields that still require the user to resolve an unknown mapping before the
 * setup can be created. This is what powers the guided "review card" UI instead
 * of a flat, optimistic summary.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Action_Card_Builder {

	const STATUS_READY           = 'ready';
	const STATUS_NEEDS_RESOLUTION = 'needs_resolution';

	/**
	 * @param array<int,array<string,mixed>> $actions  Plan actions.
	 * @param array<string,mixed>            $context  Planner context.
	 * @param array<int,array>               $entities Entity rows.
	 * @return array{cards:array<int,array<string,mixed>>,fields_needing_attention:int,requires_resolution:bool}
	 */
	public static function build( array $actions, array $context = array(), array $entities = array() ) {
		$cards = array();
		$total = 0;

		$position = 0;
		foreach ( $actions as $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$position++;
			$card   = self::build_card( $action, $position, $context, $entities );
			$total += count( $card['requiredResolutions'] );
			$cards[] = $card;
		}

		return array(
			'cards'                    => $cards,
			'fields_needing_attention' => $total,
			'requires_resolution'      => $total > 0,
			'shared_targets'           => self::collect_shared_targets( $cards ),
		);
	}

	/**
	 * Group unresolved targets shared by more than one action so the user can
	 * resolve a single dependency once and apply it everywhere it is used.
	 *
	 * @param array<int,array<string,mixed>> $cards Built cards.
	 * @return array<int,array<string,mixed>>
	 */
	private static function collect_shared_targets( array $cards ) {
		$groups = array();
		foreach ( $cards as $card ) {
			$target = is_array( $card['target'] ?? null ) ? $card['target'] : array();
			if ( ! self::target_needs_resolution( $target ) ) {
				continue;
			}
			$dependency = (string) ( $target['dependencyId'] ?? '' );
			if ( '' === $dependency ) {
				continue;
			}
			if ( ! isset( $groups[ $dependency ] ) ) {
				$groups[ $dependency ] = array(
					'dependencyId' => $dependency,
					'raw'          => (string) ( $target['raw'] ?? '' ),
					'type'         => (string) ( $target['type'] ?? 'page' ),
					'status'       => (string) ( $target['status'] ?? '' ),
					'suggestions'  => is_array( $target['suggestions'] ?? null ) ? $target['suggestions'] : array(),
					'linkedActions' => array(),
				);
			}
			$groups[ $dependency ]['linkedActions'][] = (int) ( $card['index'] ?? 0 );
		}

		$shared = array();
		foreach ( $groups as $group ) {
			if ( count( $group['linkedActions'] ) > 1 ) {
				$shared[] = $group;
			}
		}
		return $shared;
	}

	/**
	 * @param array<string,mixed> $action   Action.
	 * @param int                 $position 1-based index.
	 * @param array<string,mixed> $context  Context.
	 * @param array<int,array>    $entities Entities.
	 * @return array<string,mixed>
	 */
	private static function build_card( array $action, $position, array $context, array $entities ) {
		$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
		$include    = class_exists( 'RWGA_Planner_Condition_Polarity_Resolver', false )
			? RWGA_Planner_Condition_Polarity_Resolver::include_group( $conditions )
			: ( is_array( $conditions['include'] ?? null ) ? $conditions['include'] : array() );
		$exclude    = class_exists( 'RWGA_Planner_Condition_Polarity_Resolver', false )
			? RWGA_Planner_Condition_Polarity_Resolver::exclude_group( $conditions )
			: ( is_array( $conditions['exclude'] ?? null ) ? $conditions['exclude'] : array() );

		$target    = self::build_target( $action, $context, $entities );
		$campaign  = self::build_campaign( $action );
		$audiences = self::build_audiences( $action, $include );

		$required = array();
		if ( self::target_needs_resolution( $target ) ) {
			$is_popup = 'popup' === (string) ( $target['type'] ?? '' );
			$required[] = array(
				'field'       => 'target',
				'raw'         => (string) $target['raw'],
				'status'      => (string) $target['status'],
				'target_type' => (string) ( $target['type'] ?? 'page' ),
				'actions'     => $is_popup
					? array( 'choose_popup', 'search_popups', 'remove_action' )
					: array( 'choose_target', 'search_targets', 'remove_action' ),
			);
		}
		if ( null !== $campaign && self::entity_needs_resolution( $campaign ) ) {
			$required[] = array(
				'field'   => 'campaign',
				'raw'     => (string) $campaign['raw'],
				'status'  => (string) $campaign['status'],
				'actions' => array( 'choose_campaign', 'ignore_campaign', 'refresh_campaigns' ),
			);
		}
		foreach ( $audiences as $audience ) {
			if ( self::entity_needs_resolution( $audience ) ) {
				$required[] = array(
					'field'   => 'audience',
					'raw'     => (string) $audience['raw'],
					'status'  => (string) $audience['status'],
					'actions' => array( 'choose_audience', 'ignore_audience', 'refresh_audiences' ),
				);
			}
		}

		$locations = array();
		foreach ( (array) ( $action['unresolved']['locations'] ?? array() ) as $loc ) {
			if ( ! is_array( $loc ) || '' === (string) ( $loc['raw'] ?? '' ) ) {
				continue;
			}
			$locations[] = $loc;
			$required[]  = array(
				'field'   => 'location',
				'raw'     => (string) $loc['raw'],
				'status'  => (string) ( $loc['status'] ?? 'needs_resolution' ),
				'actions' => array(),
				'options' => self::location_options( $loc ),
			);
		}
		foreach ( (array) ( $action['unresolved']['traffic_sources'] ?? array() ) as $traffic ) {
			if ( ! is_array( $traffic ) || '' === (string) ( $traffic['value'] ?? '' ) ) {
				continue;
			}
			$required[] = array(
				'field'   => 'traffic_source',
				'raw'     => (string) ( $traffic['label'] ?? $traffic['value'] ?? '' ),
				'status'  => (string) ( $traffic['status'] ?? 'needs_mapping' ),
				'actions' => array( 'choose_traffic_mapping', 'ignore_traffic_source' ),
				'options' => (array) ( $traffic['resolution_options'] ?? array() ),
			);
		}

		$public_include = self::public_include( $include );
		$action_notes     = is_array( $action['notes'] ?? null ) ? $action['notes'] : array();
		$conditions     = array(
			'include' => $public_include,
			'exclude' => $exclude,
		);

		return array(
			'id'                  => (string) ( $action['id'] ?? 'action_' . $position ),
			'index'               => $position,
			'type'                => (string) ( $action['type'] ?? '' ),
			'label'               => '' !== (string) ( $action['label'] ?? '' )
				? (string) $action['label']
				: self::action_label( (string) ( $action['type'] ?? '' ) ),
			'status'              => empty( $required ) ? self::STATUS_READY : self::STATUS_NEEDS_RESOLUTION,
			'can_execute'         => empty( $required ),
			'target'              => $target,
			'campaign'            => $campaign,
			'operation'           => is_array( $action['operation'] ?? null ) ? $action['operation'] : array(),
			'logic'               => self::build_logic( $action ),
			'conditions'          => $conditions,
			'condition_rows'      => self::build_condition_rows( $public_include, $exclude, $audiences, $locations, $action_notes, $entities ),
			'uses_shared_target'  => ! empty( $action['uses_shared_target'] ),
			'notes'               => $action_notes,
			'audiences'           => $audiences,
			'warnings'            => array_values( (array) ( $action['warnings'] ?? array() ) ),
			'requiredResolutions' => $required,
			'confirmation_instruction' => is_array( $action['confirmation_instruction'] ?? null )
				? $action['confirmation_instruction']
				: null,
		);
	}

	/**
	 * Human label for an action type.
	 *
	 * @param string $type Action type.
	 * @return string
	 */
	private static function action_label( $type ) {
		$map = array(
			'create_rule'               => __( 'Create rule', 'reactwoo-geocore' ),
			'create_variant'            => __( 'Create variant', 'reactwoo-geocore' ),
			'update_variant'            => __( 'Update variant', 'reactwoo-geocore' ),
			'update_rule'               => __( 'Update rule', 'reactwoo-geocore' ),
			'update_campaign_targeting' => __( 'Update campaign targeting', 'reactwoo-geocore' ),
			'update_original_targeting' => __( 'Update original targeting', 'reactwoo-geocore' ),
			'create_test'               => __( 'Preview / test', 'reactwoo-geocore' ),
		);
		return $map[ $type ] ?? ucwords( str_replace( '_', ' ', (string) $type ) );
	}

	/**
	 * Logic operator for the action (default: match all). "any"/"or" phrasing in
	 * the source clause maps to OR.
	 *
	 * @param array<string,mixed> $action Action.
	 * @return array<string,mixed>
	 */
	private static function build_logic( array $action ) {
		$clause   = strtolower( (string) ( $action['sourceClause'] ?? '' ) );
		$operator = 'AND';
		if ( preg_match( '/\b(?:any\s+of\s+(?:these|the\s+following)|match(?:es)?\s+any\s+of|one\s+of\s+(?:these|the\s+following))\b/i', $clause ) ) {
			$operator = 'OR';
		}
		return array(
			'operator' => $operator,
			'label'    => 'OR' === $operator
				? __( 'Match any condition', 'reactwoo-geocore' )
				: __( 'Match all conditions', 'reactwoo-geocore' ),
			'status'   => 'valid',
		);
	}

	/**
	 * @param array<string,mixed> $loc Unresolved location row.
	 * @return array<int,array<string,mixed>>
	 */
	private static function location_options( array $loc ) {
		$options = array();
		foreach ( (array) ( $loc['candidates'] ?? array() ) as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				continue;
			}
			$options[] = array(
				'key'   => (string) ( $candidate['key'] ?? '' ),
				'label' => (string) ( $candidate['label'] ?? '' ),
				'value' => is_array( $candidate['value'] ?? null ) ? $candidate['value'] : null,
			);
		}
		return $options;
	}

	/**
	 * Build the normalised per-condition rows used by the Action Review UI.
	 *
	 * @param array<string,mixed>            $include   Public include group.
	 * @param array<string,mixed>            $exclude   Exclude group.
	 * @param array<int,array<string,mixed>> $audiences Audience rows.
	 * @param array<int,array<string,mixed>> $locations Unresolved location rows.
	 * @param array<int,string|mixed>        $notes     Action notes (e.g. no_weather_restriction).
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_condition_rows( array $include, array $exclude, array $audiences, array $locations, array $notes = array(), array $entities = array() ) {
		$rows = array();
		$n    = 0;
		$id   = static function () use ( &$n ) {
			$n++;
			return 'condition_' . $n;
		};

		// Resolved locations (countries / regions), include + exclude.
		foreach ( array( 'include' => $include, 'exclude' => $exclude ) as $mode => $group ) {
			$countries = array_values( (array) ( $group['countries'] ?? array() ) );
			if ( count( $countries ) > 1 ) {
				$labels = self::country_labels( $countries, $entities );
				$rows[] = self::valid_row(
					$id(),
					'location',
					$mode,
					implode( '|', $countries ),
					'public',
					self::country_row_label( $mode, $labels )
				);
			} else {
				foreach ( $countries as $code ) {
					$labels = self::country_labels( array( $code ), $entities );
					$rows[] = self::valid_row(
						$id(),
						'location',
						$mode,
						(string) $code,
						'public',
						self::country_row_label( $mode, $labels )
					);
				}
			}
			foreach ( (array) ( $group['regions'] ?? array() ) as $code ) {
				$rows[] = self::valid_row( $id(), 'location', $mode, (string) $code, 'public', sprintf( __( 'Region: %s', 'reactwoo-geocore' ), (string) $code ) );
			}
		}

		// Unresolved location decisions (country vs region).
		foreach ( $locations as $loc ) {
			$rows[] = array(
				'id'                 => $id(),
				'type'               => 'location',
				'mode'               => 'include',
				'raw'                => (string) ( $loc['raw'] ?? '' ),
				'label'              => (string) ( $loc['raw'] ?? '' ),
				'value'              => null,
				'status'             => 'needs_resolution',
				'icon'               => 'admin-site-alt3',
				'warning'            => (string) ( $loc['warning'] ?? '' ),
				'resolution_options' => self::location_options( $loc ),
			);
		}

		// Weather, devices, urls, utm, visitor states.
		foreach ( array( 'include' => $include, 'exclude' => $exclude ) as $mode => $group ) {
			foreach ( (array) ( $group['weather'] ?? array() ) as $w ) {
				if ( 'any' === strtolower( (string) $w ) ) {
					continue;
				}
				$rows[] = self::valid_row( $id(), 'weather', $mode, (string) $w, 'cloud', ucfirst( (string) $w ) );
			}
			foreach ( (array) ( $group['devices'] ?? array() ) as $d ) {
				$rows[] = self::valid_row( $id(), 'device', $mode, (string) $d, 'smartphone', ucfirst( (string) $d ) );
			}
			foreach ( (array) ( $group['urls'] ?? array() ) as $u ) {
				$rows[] = self::valid_row( $id(), 'url', $mode, (string) $u, 'admin-links', (string) $u );
			}
			foreach ( (array) ( $group['utm'] ?? array() ) as $u ) {
				if ( is_array( $u ) ) {
					$field = (string) ( $u['field'] ?? str_replace( 'utm_', '', (string) ( $u['key'] ?? '' ) ) );
					$val   = (string) ( $u['value'] ?? '' );
					$label = self::utm_label( $field, $val );
					$rows[] = self::valid_row( $id(), 'utm', $mode, $field . '=' . $val, 'megaphone', $label );
				} else {
					$rows[] = self::valid_row( $id(), 'utm', $mode, (string) $u, 'megaphone', (string) $u );
				}
			}
			foreach ( (array) ( $group['visitorStates'] ?? array() ) as $v ) {
				$rows[] = self::valid_row( $id(), 'visitor', $mode, (string) $v, 'admin-users', ucwords( str_replace( '_', ' ', (string) $v ) ) );
			}
			foreach ( (array) ( $group['pageTypes'] ?? array() ) as $page_type ) {
				$label = 'product' === $page_type ? __( 'Product pages', 'reactwoo-geocore' ) : ucwords( str_replace( '_', ' ', (string) $page_type ) );
				$rows[] = self::valid_row( $id(), 'page_type', $mode, (string) $page_type, 'admin-page', $label );
			}
			foreach ( (array) ( $group['condition_groups'] ?? array() ) as $condition_group ) {
				if ( ! is_array( $condition_group ) ) {
					continue;
				}
				$children     = self::condition_group_child_rows( $condition_group );
				$group_status = 'valid';
				$group_warning = '';
				$group_options = array();
				foreach ( $children as $child ) {
					if ( 'valid' !== (string) ( $child['status'] ?? '' ) ) {
						$group_status = 'needs_resolution';
						if ( '' === $group_warning && ! empty( $child['warning'] ) ) {
							$group_warning = (string) $child['warning'];
						}
						if ( empty( $group_options ) && ! empty( $child['resolution_options'] ) ) {
							$group_options = (array) $child['resolution_options'];
						}
					}
				}
				$rows[] = array(
					'id'                 => $id(),
					'type'               => 'condition_group',
					'mode'               => $mode,
					'raw'                => (string) ( $condition_group['label'] ?? '' ),
					'label'              => (string) ( $condition_group['label'] ?? '' ),
					'value'              => (array) ( $condition_group['conditions'] ?? array() ),
					'status'             => $group_status,
					'icon'               => 'randomize',
					'warning'            => $group_warning,
					'logic'              => (string) ( $condition_group['logic'] ?? 'OR' ),
					'children'           => $children,
					'resolution_options' => $group_options,
				);
			}
		}

		// Audiences.
		foreach ( $audiences as $audience ) {
			$status = (string) ( $audience['status'] ?? '' );
			$valid  = 'matched' === $status;
			$label  = (string) ( $audience['raw'] ?? '' );
			if ( 'matches_any' === $status ) {
				$label = self::matches_any_audience_label( $audience );
			} elseif ( $valid && is_array( $audience['resolved'] ?? null ) ) {
				$label = (string) ( $audience['resolved']['name'] ?? $label );
			}
			$rows[] = array(
				'id'                 => $id(),
				'type'               => 'audience',
				'mode'               => 'include',
				'raw'                => (string) ( $audience['raw'] ?? '' ),
				'label'              => $label,
				'operator'           => 'matches_any' === $status ? 'matches_any' : '',
				'value'              => $valid ? ( is_array( $audience['resolved'] ?? null ) ? $audience['resolved'] : null ) : ( $audience['segment_keys'] ?? null ),
				'status'             => $valid ? 'valid' : 'needs_resolution',
				'icon'               => 'groups',
				'warning'            => $valid ? '' : self::audience_warning( $status ),
				'suggestions'        => is_array( $audience['suggestions'] ?? null ) ? $audience['suggestions'] : array(),
				'resolution_options' => $valid ? array() : self::audience_options( $status ),
			);
		}

		foreach ( $notes as $note ) {
			if ( 'no_weather_restriction' === $note ) {
				$rows[] = array(
					'id'                 => $id(),
					'type'               => 'weather',
					'mode'               => 'include',
					'raw'                => 'no_weather_restriction',
					'label'              => __( 'No restriction', 'reactwoo-geocore' ),
					'value'              => null,
					'status'             => 'valid',
					'icon'               => 'cloud',
					'warning'            => '',
					'is_note'            => true,
					'resolution_options' => array(),
				);
			}
		}

		return $rows;
	}

	/**
	 * Normalise OR-group child conditions for review UI status inheritance.
	 *
	 * @param array<string,mixed> $condition_group Condition group row.
	 * @return array<int,array<string,mixed>>
	 */
	private static function condition_group_child_rows( array $condition_group ) {
		$children = array();
		foreach ( (array) ( $condition_group['conditions'] ?? array() ) as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$type   = (string) ( $child['type'] ?? '' );
			$status = (string) ( $child['status'] ?? 'valid' );
			$label  = (string) ( $child['label'] ?? '' );
			if ( '' === $label ) {
				if ( 'traffic_source' === $type ) {
					$label = __( 'Google Ads traffic', 'reactwoo-geocore' );
				} elseif ( 'url' === $type ) {
					$label = sprintf(
						/* translators: %s: URL path fragment */
						__( 'URL contains %s', 'reactwoo-geocore' ),
						(string) ( $child['value'] ?? '' )
					);
				}
			}
			$row = array(
				'type'   => $type,
				'status' => $status,
				'label'  => $label,
			);
			if ( 'needs_mapping' === $status || 'needs_resolution' === $status ) {
				$row['warning'] = (string) ( $child['warning'] ?? __( 'Google Ads traffic must be mapped to UTM parameters before this can be created.', 'reactwoo-geocore' ) );
				$row['resolution_options'] = (array) ( $child['resolution_options'] ?? array() );
			}
			$children[] = $row;
		}
		return $children;
	}

	/**
	 * @param string $id    Row id.
	 * @param string $type  Condition type.
	 * @param string $mode  include|exclude.
	 * @param string $value Stored value.
	 * @param string $icon  Dashicon name.
	 * @param string $label Display label.
	 * @return array<string,mixed>
	 */
	private static function valid_row( $id, $type, $mode, $value, $icon, $label ) {
		return array(
			'id'                 => $id,
			'type'               => $type,
			'mode'               => $mode,
			'raw'                => $value,
			'label'              => $label,
			'value'              => $value,
			'status'             => 'valid',
			'icon'               => $icon,
			'warning'            => '',
			'resolution_options' => array(),
		);
	}

	/**
	 * @param string $status Audience status.
	 * @return string
	 */
	private static function audience_warning( $status ) {
		if ( 'audience_any' === $status ) {
			return __( 'Choose whether this means any audience or selected audience groups.', 'reactwoo-geocore' );
		}
		if ( 'matches_any' === $status ) {
			return __( 'Audience segments must be selected or synced before this can be created.', 'reactwoo-geocore' );
		}
		if ( in_array( $status, array( 'not_found', 'not_defined' ), true ) ) {
			return __( 'Audience targeting is not available or no audiences are synced yet.', 'reactwoo-geocore' );
		}
		return __( 'No synced audience list is available yet.', 'reactwoo-geocore' );
	}

	/**
	 * @param array<string,mixed> $audience Audience row.
	 * @return string
	 */
	private static function matches_any_audience_label( array $audience ) {
		$keys = (array) ( $audience['segment_keys'] ?? array() );
		$parts = array();
		foreach ( $keys as $key ) {
			if ( 'vip' === $key ) {
				$parts[] = 'VIP';
			} elseif ( 'returning_customer' === $key ) {
				$parts[] = __( 'Returning Customer', 'reactwoo-geocore' );
			}
		}
		if ( ! empty( $parts ) ) {
			return implode( ' ' . __( 'or', 'reactwoo-geocore' ) . ' ', $parts );
		}
		return (string) ( $audience['raw'] ?? '' );
	}

	/**
	 * @param string $field UTM field (campaign, medium, …).
	 * @param string $value Value.
	 * @return string
	 */
	private static function utm_label( $field, $value ) {
		if ( 'campaign' === $field ) {
			return sprintf( __( 'Campaign: %s', 'reactwoo-geocore' ), $value );
		}
		if ( 'medium' === $field ) {
			return sprintf( __( 'Traffic medium: %s', 'reactwoo-geocore' ), $value );
		}
		return 'utm_' . $field . '=' . $value;
	}

	/**
	 * @param string $status Audience status.
	 * @return array<int,array<string,mixed>>
	 */
	private static function audience_options( $status ) {
		if ( 'audience_any' === $status ) {
			return array(
				array( 'key' => 'any_audience', 'label' => __( 'Any audience', 'reactwoo-geocore' ) ),
				array( 'key' => 'choose_audiences', 'label' => __( 'Choose audience groups', 'reactwoo-geocore' ), 'picker' => true ),
				array( 'key' => 'remove', 'label' => __( 'Remove audience condition', 'reactwoo-geocore' ) ),
			);
		}
		if ( 'matches_any' === $status ) {
			return array(
				array( 'key' => 'choose_audience_segments', 'label' => __( 'Choose audience segments', 'reactwoo-geocore' ), 'picker' => true ),
				array( 'key' => 'refresh_synced_audiences', 'label' => __( 'Refresh synced audiences', 'reactwoo-geocore' ) ),
				array( 'key' => 'keep_as_draft', 'label' => __( 'Keep as draft', 'reactwoo-geocore' ) ),
				array( 'key' => 'remove_audience_condition', 'label' => __( 'Remove audience condition', 'reactwoo-geocore' ) ),
			);
		}
		return array(
			array( 'key' => 'choose_audiences', 'label' => __( 'Choose audience', 'reactwoo-geocore' ), 'picker' => true ),
			array( 'key' => 'refresh', 'label' => __( 'Refresh synced audiences', 'reactwoo-geocore' ) ),
			array( 'key' => 'remove', 'label' => __( 'Remove audience condition', 'reactwoo-geocore' ) ),
		);
	}

	/**
	 * @param array<string,mixed> $action   Action.
	 * @param array<string,mixed> $context  Context.
	 * @param array<int,array>    $entities Entities.
	 * @return array<string,mixed>
	 */
	private static function build_target( array $action, array $context, array $entities ) {
		$raw_target = is_array( $action['target'] ?? null ) ? $action['target'] : array();
		$type       = (string) ( $raw_target['type'] ?? 'page' );
		if ( class_exists( 'RWGA_Planner_Target_Resolver', false ) ) {
			$clean_label = RWGA_Planner_Target_Resolver::clean_detected_label(
				(string) ( $raw_target['label'] ?? '' ),
				$type
			);
			if ( '' !== $clean_label ) {
				$raw_target['label'] = $clean_label;
			}
		}

		if ( ! empty( $raw_target['user_resolved'] ) && is_array( $raw_target['user_resolved'] ) ) {
			$chosen = $raw_target['user_resolved'];
			return self::stamp_dependency(
				array(
					'type'        => (string) ( $chosen['type'] ?? ( $raw_target['type'] ?? 'page' ) ),
					'raw'         => (string) ( $chosen['name'] ?? ( $raw_target['label'] ?? '' ) ),
					'resolved'    => array(
						'id'   => (string) ( $chosen['id'] ?? '' ),
						'name' => (string) ( $chosen['name'] ?? '' ),
					),
					'status'      => 'matched',
					'suggestions' => array(),
				),
				$raw_target
			);
		}
		if ( ! empty( $raw_target['user_ignored'] ) ) {
			return self::stamp_dependency(
				array(
					'type'        => (string) ( $raw_target['type'] ?? 'page' ),
					'raw'         => (string) ( $raw_target['label'] ?? '' ),
					'resolved'    => null,
					'status'      => 'ignored',
					'suggestions' => array(),
				),
				$raw_target
			);
		}

		$resolution = class_exists( 'RWGA_Planner_Target_Registry_Resolver', false )
			? RWGA_Planner_Target_Registry_Resolver::resolve( $raw_target, $context, $entities )
			: array(
				'type'        => (string) ( $raw_target['type'] ?? 'page' ),
				'raw'         => (string) ( $raw_target['label'] ?? '' ),
				'resolved'    => null,
				'status'      => 'registry_unavailable',
				'suggestions' => array(),
			);

		$source = (string) ( $raw_target['source'] ?? '' );
		if ( in_array( $source, array( 'parent_variant', 'inherited' ), true ) ) {
			$resolution['inherited'] = true;
			$resolution['inheritedFrom'] = (string) ( $raw_target['inheritedFrom'] ?? $raw_target['sourcePage'] ?? $raw_target['label'] ?? '' );
			if ( in_array( $resolution['status'], array( 'not_found', 'ambiguous' ), true ) ) {
				$resolution['status'] = 'inherited_unresolved';
			}
		}

		if ( self::target_needs_resolution( $resolution ) ) {
			$resolution['status'] = 'needs_resolution';
		}

		return self::stamp_dependency( $resolution, $raw_target );
	}

	/**
	 * Attach a stable dependency id derived from the inherited origin (so linked
	 * variant actions share the same id) or the resolved raw target label.
	 *
	 * @param array<string,mixed> $resolution Target resolution.
	 * @param array<string,mixed> $raw_target Raw action target.
	 * @return array<string,mixed>
	 */
	private static function stamp_dependency( array $resolution, array $raw_target ) {
		$key = (string) ( $raw_target['inheritedFrom'] ?? '' );
		if ( '' === $key ) {
			$key = (string) ( $resolution['raw'] ?? '' );
		}
		$key = trim( $key );
		if ( '' !== $key ) {
			$resolution['dependencyId'] = 'target_' . sanitize_title( $key );
		}
		return $resolution;
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @return array<string,mixed>|null
	 */
	private static function build_campaign( array $action ) {
		$campaign   = is_array( $action['campaign'] ?? null ) ? $action['campaign'] : array();
		$unresolved = (array) ( $action['unresolved']['campaigns'] ?? array() );

		if ( ! empty( $campaign['id'] ) ) {
			return array(
				'raw'         => (string) ( $campaign['name'] ?? '' ),
				'resolved'    => array(
					'id'     => (string) $campaign['id'],
					'name'   => (string) ( $campaign['name'] ?? '' ),
					'source' => (string) ( $campaign['source'] ?? '' ),
				),
				'status'      => 'matched',
				'suggestions' => array(),
			);
		}

		if ( ! empty( $unresolved ) && is_array( $unresolved[0] ) ) {
			$row = $unresolved[0];
			return array(
				'raw'         => (string) ( $row['raw'] ?? ( $campaign['label'] ?? '' ) ),
				'resolved'    => null,
				'status'      => (string) ( $row['status'] ?? 'not_found' ),
				'suggestions' => self::entity_suggestions( (array) ( $row['suggestions'] ?? array() ) ),
			);
		}

		if ( ! empty( $campaign['label'] ) ) {
			return array(
				'raw'         => (string) $campaign['label'],
				'resolved'    => null,
				'status'      => (string) ( $campaign['status'] ?? 'not_found' ),
				'suggestions' => array(),
			);
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $action  Action.
	 * @param array<string,mixed> $include Include condition group.
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_audiences( array $action, array $include ) {
		$audiences = array();

		foreach ( (array) ( $include['audiences'] ?? array() ) as $matched ) {
			if ( is_array( $matched ) && '' !== (string) ( $matched['name'] ?? '' ) ) {
				$audiences[] = array(
					'raw'         => (string) ( $matched['raw'] ?? $matched['name'] ),
					'resolved'    => array(
						'id'     => (string) ( $matched['id'] ?? '' ),
						'name'   => (string) $matched['name'],
						'source' => (string) ( $matched['source'] ?? '' ),
					),
					'status'      => 'matched',
					'suggestions' => array(),
				);
			} elseif ( is_string( $matched ) && '' !== $matched ) {
				$audiences[] = array(
					'raw'         => $matched,
					'resolved'    => array( 'id' => '', 'name' => $matched, 'source' => '' ),
					'status'      => 'matched',
					'suggestions' => array(),
				);
			}
		}

		foreach ( (array) ( $action['unresolved']['audiences'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) || '' === (string) ( $row['raw'] ?? '' ) ) {
				continue;
			}
			$audiences[] = array(
				'raw'          => (string) $row['raw'],
				'resolved'     => null,
				'status'       => (string) ( $row['status'] ?? 'not_found' ),
				'segment_keys' => is_array( $row['segment_keys'] ?? null ) ? $row['segment_keys'] : array(),
				'suggestions'  => self::entity_suggestions( (array) ( $row['suggestions'] ?? array() ) ),
			);
		}

		return $audiences;
	}

	/**
	 * Strip internal audience objects from the public include group (the card
	 * exposes audiences separately with resolution status).
	 *
	 * @param array<string,mixed> $include Include group.
	 * @return array<string,mixed>
	 */
	private static function public_include( array $include ) {
		unset( $include['audiences'] );
		if ( isset( $include['weather'] ) && is_array( $include['weather'] ) ) {
			$include['weather'] = array_values(
				array_filter(
					$include['weather'],
					static function ( $value ) {
						return 'any' !== strtolower( (string) $value );
					}
				)
			);
		}
		return $include;
	}

	/**
	 * @param array<int,array> $suggestions Raw suggestions.
	 * @return array<int,array{id:string,name:string,source:string,confidence:float}>
	 */
	private static function entity_suggestions( array $suggestions ) {
		$out = array();
		foreach ( $suggestions as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = trim( (string) ( $row['name'] ?? '' ) );
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'id'         => (string) ( $row['id'] ?? '' ),
				'name'       => $name,
				'source'     => (string) ( $row['source'] ?? '' ),
				'confidence' => (float) ( $row['confidence'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Human labels for unresolved fields (Resolution Hub / execute errors).
	 *
	 * @param array<int,array<string,mixed>> $cards Action cards.
	 * @return array<int,string>
	 */
	public static function unresolved_field_labels( array $cards ) {
		$labels = array();
		foreach ( self::unresolved_field_details( $cards ) as $row ) {
			if ( ! empty( $row['label'] ) ) {
				$labels[] = (string) $row['label'];
			}
		}
		return $labels;
	}

	/**
	 * Structured unresolved rows for execute rejection responses.
	 *
	 * @param array<int,array<string,mixed>> $cards Action cards.
	 * @return array<int,array{key:string,label:string,field:string,raw:string,card:int,path:string}>
	 */
	public static function unresolved_field_details( array $cards ) {
		$rows = array();
		$seen = array();
		foreach ( $cards as $card_index => $card ) {
			if ( ! is_array( $card ) ) {
				continue;
			}
			foreach ( (array) ( $card['requiredResolutions'] ?? array() ) as $req ) {
				if ( ! is_array( $req ) ) {
					continue;
				}
				$field = (string) ( $req['field'] ?? '' );
				if ( '' === $field || isset( $seen[ $field ] ) ) {
					continue;
				}
				$seen[ $field ] = true;
				$key            = 'traffic_source' === $field ? 'google_ads_mapping' : $field;
				$rows[]         = array(
					'key'   => $key,
					'label' => self::resolution_field_label( $field, $card ),
					'field' => $field,
					'raw'   => (string) ( $req['raw'] ?? '' ),
					'card'  => (int) $card_index,
					'path'  => sprintf( 'actions[%d].requiredResolutions[%s]', (int) $card_index, $field ),
				);
			}
		}
		return $rows;
	}

	/**
	 * @param string              $field Field key.
	 * @param array<string,mixed> $card  Action card.
	 * @return string
	 */
	private static function resolution_field_label( $field, array $card ) {
		switch ( $field ) {
			case 'target':
				$target = is_array( $card['target'] ?? null ) ? $card['target'] : array();
				return 'popup' === (string) ( $target['type'] ?? '' )
					? __( 'Popup target', 'reactwoo-geocore' )
					: __( 'Target page', 'reactwoo-geocore' );
			case 'traffic_source':
				return __( 'Google Ads mapping', 'reactwoo-geocore' );
			case 'audience':
				return __( 'Audience segments', 'reactwoo-geocore' );
			case 'campaign':
				return __( 'Campaign', 'reactwoo-geocore' );
			case 'location':
				return __( 'Location', 'reactwoo-geocore' );
			default:
				return ucwords( str_replace( '_', ' ', (string) $field ) );
		}
	}

	/**
	 * @param array<int,string> $codes    ISO country codes.
	 * @param array<int,array>  $entities Entity rows.
	 * @return array<int,string>
	 */
	private static function country_labels( array $codes, array $entities ) {
		$labels = array();
		foreach ( $codes as $code ) {
			$labels[] = class_exists( 'RWGA_Planner_Location_Resolver', false )
				? RWGA_Planner_Location_Resolver::country_label( (string) $code, $entities )
				: (string) $code;
		}
		return $labels;
	}

	/**
	 * @param string            $mode   include|exclude.
	 * @param array<int,string> $labels Country display names.
	 * @return string
	 */
	private static function country_row_label( $mode, array $labels ) {
		$list = implode( ' OR ', $labels );
		if ( 'exclude' === $mode ) {
			return sprintf(
				/* translators: %s: country list joined with OR */
				__( 'Exclude country: %s', 'reactwoo-geocore' ),
				$list
			);
		}
		return $list;
	}

	/**
	 * @param array<string,mixed> $target Target resolution.
	 * @return bool
	 */
	private static function target_needs_resolution( array $target ) {
		$status = (string) ( $target['status'] ?? '' );
		if ( in_array( $status, array( 'matched', 'ignored' ), true ) ) {
			return false;
		}
		if ( in_array( $status, array( 'not_found', 'ambiguous', 'inherited_unresolved', 'needs_resolution' ), true ) ) {
			return true;
		}
		if ( 'registry_unavailable' === $status ) {
			return self::named_target_requires_verification( $target );
		}
		return '' !== $status;
	}

	/**
	 * Named popups/banners/pages should be verified; generic placeholders should not block review.
	 *
	 * @param array<string,mixed> $target Target resolution row.
	 * @return bool
	 */
	private static function named_target_requires_verification( array $target ) {
		$raw  = strtolower( trim( (string) ( $target['raw'] ?? '' ) ) );
		$type = (string) ( $target['type'] ?? 'page' );
		$generic = array(
			'',
			'page',
			'product',
			'product page',
			'category',
			'category page',
			'popup',
			'banner',
			'variant',
			'rule',
			'homepage',
			'home page',
			'shop',
			'shop page',
			'checkout',
			'checkout page',
			'cart',
			'cart page',
		);
		if ( in_array( $raw, $generic, true ) ) {
			return false;
		}
		return in_array( $type, array( 'popup', 'banner', 'page', 'category', 'product', 'category_page', 'product_page' ), true );
	}

	/**
	 * @param array<string,mixed> $entity Campaign/audience resolution.
	 * @return bool
	 */
	private static function entity_needs_resolution( array $entity ) {
		$status = (string) ( $entity['status'] ?? '' );
		return '' !== $status && 'matched' !== $status;
	}
}
