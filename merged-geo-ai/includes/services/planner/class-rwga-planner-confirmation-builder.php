<?php
/**
 * Build human-readable confirmation copy for interpretation plans.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Confirmation_Builder {

	/**
	 * @param array<string,mixed> $plan Interpretation plan.
	 * @return array{summary:string,setup_summary:string}
	 */
	public static function build( array $plan ) {
		$actions  = isset( $plan['actions'] ) && is_array( $plan['actions'] ) ? $plan['actions'] : array();
		$warnings = isset( $plan['warnings'] ) && is_array( $plan['warnings'] ) ? $plan['warnings'] : array();
		$count    = count( $actions );

		if ( 0 === $count ) {
			return array(
				'summary'       => __( 'I could not detect any geo actions in that message.', 'reactwoo-geo-ai' ),
				'setup_summary' => __( 'No actions detected', 'reactwoo-geo-ai' ),
			);
		}

		$summary_lines = array(
			sprintf(
				/* translators: %d: action count */
				_n( 'I found %d action:', 'I found %d actions:', $count, 'reactwoo-geo-ai' ),
				$count
			),
			'',
		);
		$setup_lines = array(
			__( 'Setup', 'reactwoo-geocore' ),
			'',
			sprintf(
				/* translators: %d: action count */
				_n( '%d action detected', '%d actions detected', $count, 'reactwoo-geo-ai' ),
				$count
			),
			'',
		);

		foreach ( $actions as $idx => $action ) {
			if ( ! is_array( $action ) ) {
				continue;
			}
			$num             = $idx + 1;
			$title           = self::action_title( $action );
			$type_label      = self::type_label( (string) ( $action['type'] ?? '' ) );
			$condition_lines = self::condition_lines( $action );

			$summary_lines[] = sprintf( '%d. %s', $num, $title );
			foreach ( $condition_lines as $line ) {
				$summary_lines[] = '   ' . $line;
			}

			$unresolved_lines = self::unresolved_lines( $action );
			foreach ( $unresolved_lines as $line ) {
				$summary_lines[] = '   ' . $line;
			}

			$setup_lines[] = sprintf( '%d. %s', $num, $title );
			$setup_lines[] = $type_label;
			$target_label  = self::target_line( $action );
			if ( '' !== $target_label ) {
				$setup_lines[] = $target_label;
			}
			$visibility_label = self::visibility_line( $action );
			if ( '' !== $visibility_label ) {
				$setup_lines[] = $visibility_label;
			}
			$campaign_label = self::campaign_line( $action );
			if ( '' !== $campaign_label ) {
				$setup_lines[] = $campaign_label;
			}
			foreach ( $condition_lines as $line ) {
				$setup_lines[] = $line;
			}
			foreach ( $unresolved_lines as $line ) {
				$setup_lines[] = $line;
			}
			if ( ! empty( $unresolved_lines ) ) {
				$setup_lines[] = __( 'Needs audience/campaign selection', 'reactwoo-geocore' );
			}
			$setup_lines[] = '';
		}

		foreach ( $warnings as $warning ) {
			$warning = (string) $warning;
			if ( '' === $warning ) {
				continue;
			}
			$summary_lines[] = '';
			$summary_lines[] = $warning;
			$setup_lines[]   = $warning;
		}

		if ( ! empty( $plan['clarification'] ) ) {
			$summary_lines[] = '';
			$summary_lines[] = (string) ( $plan['clarification']['message'] ?? '' );
		} else {
			$summary_lines[] = '';
			$summary_lines[] = __( 'Is this correct?', 'reactwoo-geo-ai' );
		}

		$setup_lines[] = __( 'Status', 'reactwoo-geocore' );
		$setup_lines[] = self::status_label( (string) ( $plan['status'] ?? '' ) );

		return array(
			'summary'       => implode( "\n", array_filter( $summary_lines, static function ( $line ) {
				return '' !== $line || "\n" === $line;
			} ) ),
			'setup_summary' => implode( "\n", $setup_lines ),
		);
	}

	/**
	 * @param array<string,mixed> $action Action row.
	 * @return string
	 */
	private static function action_title( array $action ) {
		$type   = (string) ( $action['type'] ?? '' );
		$target = is_array( $action['target'] ?? null ) ? $action['target'] : array();
		$label  = (string) ( $target['label'] ?? 'page' );

		switch ( $type ) {
			case RWGA_Geo_Action_Types::UPDATE_CAMPAIGN_TARGETING:
				return sprintf(
					/* translators: %s: target label */
					__( 'Campaign targeting for %s', 'reactwoo-geo-ai' ),
					$label
				);
			case RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING:
				return sprintf(
					/* translators: %s: page label */
					__( 'Update %s targeting', 'reactwoo-geo-ai' ),
					$label
				);
			case RWGA_Geo_Action_Types::CREATE_VARIANT:
				return sprintf(
					/* translators: %s: page label */
					__( 'Create %s variant', 'reactwoo-geo-ai' ),
					$label
				);
			case RWGA_Geo_Action_Types::UPDATE_VARIANT:
				return sprintf(
					/* translators: %s: variant label */
					__( 'Update %s', 'reactwoo-geo-ai' ),
					$label
				);
			case RWGA_Geo_Action_Types::UPDATE_RULE:
				return sprintf(
					/* translators: %s: rule label */
					__( 'Update %s', 'reactwoo-geo-ai' ),
					$label
				);
			case RWGA_Geo_Action_Types::CREATE_RULE:
				return sprintf(
					/* translators: %s: target label */
					__( 'Rule for %s', 'reactwoo-geo-ai' ),
					$label
				);
			case RWGA_Geo_Action_Types::CREATE_TEST:
				return sprintf(
					/* translators: %s: page label */
					__( 'Test %s view', 'reactwoo-geo-ai' ),
					$label
				);
			case RWGA_Geo_Action_Types::DIAGNOSE:
				return sprintf(
					/* translators: %s: page label */
					__( 'Diagnose %s', 'reactwoo-geo-ai' ),
					$label
				);
			default:
				return ucfirst( $label );
		}
	}

	/**
	 * @param string $type Action type.
	 * @return string
	 */
	private static function type_label( $type ) {
		$map = array(
			RWGA_Geo_Action_Types::UPDATE_CAMPAIGN_TARGETING => __( 'Update campaign targeting', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::UPDATE_ORIGINAL_TARGETING => __( 'Update original targeting', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::CREATE_VARIANT            => __( 'Create variant', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::UPDATE_VARIANT            => __( 'Update variant', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::UPDATE_RULE               => __( 'Update rule', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::CREATE_RULE               => __( 'Create rule', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::CREATE_TEST               => __( 'Create test', 'reactwoo-geo-ai' ),
			RWGA_Geo_Action_Types::DIAGNOSE                  => __( 'Diagnose', 'reactwoo-geo-ai' ),
		);
		return $map[ $type ] ?? $type;
	}

	/**
	 * Target type + label line, e.g. "Target: rule — VIP discount rule".
	 *
	 * @param array<string,mixed> $action Action.
	 * @return string
	 */
	private static function target_line( array $action ) {
		$target = is_array( $action['target'] ?? null ) ? $action['target'] : array();
		$label  = trim( (string) ( $target['label'] ?? '' ) );
		$type   = trim( (string) ( $target['type'] ?? '' ) );
		if ( '' === $label && '' === $type ) {
			return '';
		}
		if ( '' === $type ) {
			return sprintf( __( 'Target: %s', 'reactwoo-geocore' ), $label );
		}
		$type = str_replace( '_', ' ', $type );
		if ( '' === $label ) {
			return sprintf( __( 'Target: %s', 'reactwoo-geocore' ), $type );
		}
		return sprintf( __( 'Target: %1$s — %2$s', 'reactwoo-geocore' ), $type, $label );
	}

	/**
	 * Visibility/mode line.
	 *
	 * @param array<string,mixed> $action Action.
	 * @return string
	 */
	private static function visibility_line( array $action ) {
		$operation  = is_array( $action['operation'] ?? null ) ? $action['operation'] : array();
		$visibility = trim( (string) ( $operation['visibility'] ?? '' ) );
		if ( '' === $visibility ) {
			return '';
		}
		return sprintf( __( 'Visibility: %s', 'reactwoo-geocore' ), str_replace( '_', ' ', $visibility ) );
	}

	/**
	 * Build per-action condition lines for every detected condition type.
	 *
	 * @param array<string,mixed> $action Action.
	 * @return array<int,string>
	 */
	private static function condition_lines( array $action ) {
		$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
		$include    = RWGA_Planner_Condition_Polarity_Resolver::include_group( $conditions );
		$exclude    = RWGA_Planner_Condition_Polarity_Resolver::exclude_group( $conditions );
		$lines      = array();

		$location = self::location_line( $action );
		if ( '' !== $location ) {
			$lines[] = sprintf( __( 'Location: %s', 'reactwoo-geocore' ), $location );
		}

		$devices = array_values( array_filter( (array) ( $include['devices'] ?? array() ) ) );
		if ( ! empty( $devices ) ) {
			$lines[] = sprintf( __( 'Devices: %s', 'reactwoo-geocore' ), implode( ', ', $devices ) );
		}

		$audiences = self::audience_names( (array) ( $include['audiences'] ?? array() ) );
		if ( ! empty( $audiences ) ) {
			$lines[] = sprintf( __( 'Audiences: %s', 'reactwoo-geocore' ), implode( ', ', $audiences ) );
		}

		$visitor_states = array_values( array_filter( (array) ( $include['visitorStates'] ?? array() ) ) );
		if ( ! empty( $visitor_states ) ) {
			$lines[] = sprintf( __( 'Visitor state: %s', 'reactwoo-geocore' ), implode( ', ', array_map( static function ( $s ) {
				return str_replace( '_', ' ', (string) $s );
			}, $visitor_states ) ) );
		}

		$weather = array_values( array_filter( (array) ( $include['weather'] ?? array() ) ) );
		if ( ! empty( $weather ) ) {
			$lines[] = sprintf( __( 'Weather: %s', 'reactwoo-geocore' ), implode( ', ', $weather ) );
		}

		$urls = array_values( array_filter( (array) ( $include['urls'] ?? array() ) ) );
		if ( ! empty( $urls ) ) {
			$lines[] = sprintf( __( 'URL/path: %s', 'reactwoo-geocore' ), implode( ', ', $urls ) );
		}

		$utm = self::format_utm( (array) ( $include['utm'] ?? array() ) );
		if ( '' !== $utm ) {
			$lines[] = sprintf( __( 'UTM: %s', 'reactwoo-geocore' ), $utm );
		}

		foreach ( self::exclusion_lines( $exclude ) as $line ) {
			$lines[] = $line;
		}

		return $lines;
	}

	/**
	 * @param array<string,mixed> $exclude Exclude condition group.
	 * @return array<int,string>
	 */
	private static function exclusion_lines( array $exclude ) {
		$parts = array();

		$countries = array_values( array_filter( (array) ( $exclude['countries'] ?? array() ) ) );
		$regions   = array_values( array_filter( (array) ( $exclude['regions'] ?? array() ) ) );
		$locations = array_merge( $countries, $regions );
		if ( ! empty( $locations ) ) {
			$parts[] = sprintf( __( 'Exclude location: %s', 'reactwoo-geocore' ), implode( ', ', $locations ) );
		}

		$devices = array_values( array_filter( (array) ( $exclude['devices'] ?? array() ) ) );
		if ( ! empty( $devices ) ) {
			$parts[] = sprintf( __( 'Exclude devices: %s', 'reactwoo-geocore' ), implode( ', ', $devices ) );
		}

		$audiences = array_values( array_filter( (array) ( $exclude['audiences'] ?? array() ) ) );
		if ( ! empty( $audiences ) ) {
			$parts[] = sprintf( __( 'Exclude audiences: %s', 'reactwoo-geocore' ), implode( ', ', array_map( static function ( $a ) {
				return str_replace( '_', ' ', (string) $a );
			}, $audiences ) ) );
		}

		$weather = array_values( array_filter( (array) ( $exclude['weather'] ?? array() ) ) );
		if ( ! empty( $weather ) ) {
			$parts[] = sprintf( __( 'Exclude weather: %s', 'reactwoo-geocore' ), implode( ', ', $weather ) );
		}

		$utm = self::format_utm( (array) ( $exclude['utm'] ?? array() ) );
		if ( '' !== $utm ) {
			$parts[] = sprintf( __( 'Exclude UTM: %s', 'reactwoo-geocore' ), $utm );
		}

		return $parts;
	}

	/**
	 * @param array<int,mixed> $audiences Audience rows (objects or strings).
	 * @return array<int,string>
	 */
	private static function audience_names( array $audiences ) {
		$names = array();
		foreach ( $audiences as $audience ) {
			if ( is_array( $audience ) ) {
				$name = trim( (string) ( $audience['name'] ?? '' ) );
			} else {
				$name = str_replace( '_', ' ', (string) $audience );
			}
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * Lines describing unresolved synced audiences/campaigns on an action.
	 *
	 * @param array<string,mixed> $action Action.
	 * @return array<int,string>
	 */
	private static function unresolved_lines( array $action ) {
		$unresolved = is_array( $action['unresolved'] ?? null ) ? $action['unresolved'] : array();
		$lines      = array();

		foreach ( (array) ( $unresolved['audiences'] ?? array() ) as $row ) {
			if ( is_array( $row ) ) {
				$lines[] = sprintf( __( 'Audience not defined: %s', 'reactwoo-geocore' ), (string) ( $row['raw'] ?? '' ) );
				$lines   = array_merge( $lines, self::suggestion_lines( $row ) );
			}
		}
		foreach ( (array) ( $unresolved['campaigns'] ?? array() ) as $row ) {
			if ( is_array( $row ) ) {
				$lines[] = sprintf( __( 'Campaign not defined: %s', 'reactwoo-geocore' ), (string) ( $row['raw'] ?? '' ) );
				$lines   = array_merge( $lines, self::suggestion_lines( $row ) );
			}
		}

		return $lines;
	}

	/**
	 * @param array<string,mixed> $row Unresolved row.
	 * @return array<int,string>
	 */
	private static function suggestion_lines( array $row ) {
		$suggestions = (array) ( $row['suggestions'] ?? array() );
		if ( empty( $suggestions ) ) {
			return array();
		}
		$labels = array();
		foreach ( $suggestions as $suggestion ) {
			if ( ! is_array( $suggestion ) ) {
				continue;
			}
			$name   = (string) ( $suggestion['name'] ?? '' );
			$source = (string) ( $suggestion['source'] ?? '' );
			$labels[] = '' !== $source ? sprintf( '%s — %s', $name, $source ) : $name;
		}
		if ( empty( $labels ) ) {
			return array();
		}
		return array( sprintf( __( 'Suggested matches: %s', 'reactwoo-geocore' ), implode( ', ', $labels ) ) );
	}

	/**
	 * Campaign line (matched synced campaign or detected label).
	 *
	 * @param array<string,mixed> $action Action.
	 * @return string
	 */
	private static function campaign_line( array $action ) {
		$campaign = is_array( $action['campaign'] ?? null ) ? $action['campaign'] : array();
		if ( empty( $campaign ) ) {
			return '';
		}
		$name = trim( (string) ( $campaign['name'] ?? $campaign['label'] ?? '' ) );
		if ( '' === $name ) {
			return '';
		}
		$source = trim( (string) ( $campaign['source'] ?? '' ) );
		if ( '' !== $source ) {
			return sprintf( __( 'Campaign: %1$s — %2$s', 'reactwoo-geocore' ), $name, $source );
		}
		return sprintf( __( 'Campaign: %s', 'reactwoo-geocore' ), $name );
	}

	/**
	 * @param array<int,mixed> $rows UTM rows.
	 * @return string
	 */
	private static function format_utm( array $rows ) {
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['key'] ) ) {
				continue;
			}
			$out[] = (string) $row['key'] . '=' . (string) ( $row['value'] ?? '' );
		}
		return implode( ', ', $out );
	}

	/**
	 * @param array<string,mixed> $action Action.
	 * @return string
	 */
	private static function location_line( array $action ) {
		$conditions = is_array( $action['conditions'] ?? null ) ? $action['conditions'] : array();
		$include    = RWGA_Planner_Condition_Polarity_Resolver::include_group( $conditions );
		$labels     = (array) ( $action['location_labels'] ?? array() );
		if ( ! empty( $labels ) ) {
			return implode( ' + ', $labels );
		}
		foreach ( (array) ( $include['regions'] ?? array() ) as $region ) {
			if ( 'GB-ENG' === $region ) {
				$labels[] = 'England';
			} else {
				$labels[] = (string) $region;
			}
		}
		if ( ! empty( $labels ) ) {
			return implode( ' + ', $labels );
		}
		return RWGA_Planner_Location_Resolver::display_label(
			array(
				'countries' => $include['countries'] ?? array(),
				'regions'   => $include['regions'] ?? array(),
				'labels'    => array(),
			)
		);
	}

	/**
	 * @param string $status Plan status.
	 * @return string
	 */
	private static function status_label( $status ) {
		if ( RWGA_Geo_Action_Types::STATUS_NEEDS_CLARIFICATION === $status ) {
			return __( 'Needs clarification', 'reactwoo-geocore' );
		}
		if ( RWGA_Geo_Action_Types::STATUS_READY === $status ) {
			return __( 'Ready', 'reactwoo-geocore' );
		}
		return __( 'Needs confirmation', 'reactwoo-geocore' );
	}
}
