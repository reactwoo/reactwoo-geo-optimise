<?php
/**
 * Extract UTM query parameters from clause text.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Planner_Utm_Condition_Resolver {

	/**
	 * @param string $text Normalised text.
	 * @return array<int,array{key:string,value:string}>
	 */
	public static function extract( $text ) {
		$text = RWGA_Local_Intent_Interpreter::normalise( $text );
		$rows = array();

		if ( preg_match( '/\bwhen\s+the\s+campaign\s+is\s+([\w-]+)/i', $text, $m ) ) {
			$val = trim( (string) ( $m[1] ?? '' ) );
			if ( '' !== $val ) {
				$rows[] = array(
					'key'   => 'utm_campaign',
					'field' => 'campaign',
					'value' => $val,
				);
			}
		} elseif ( preg_match( '/\b(?:the\s+)?campaign\s+is\s+([\w-]+)/i', $text, $m ) ) {
			$val = trim( (string) ( $m[1] ?? '' ) );
			if ( '' !== $val && ! preg_match( '/\bfor\s+(?:the\s+)?[\w\s-]+\s+campaign\b/i', $text ) ) {
				$rows[] = array(
					'key'   => 'utm_campaign',
					'field' => 'campaign',
					'value' => $val,
				);
			}
		}

		if ( preg_match( '/\b(?:from\s+)?email\s+traffic\b/i', $text )
			|| preg_match( '/\bvisitors?\s+from\s+email\b/i', $text )
			|| preg_match( '/\bemail\s+(?:referral|source|medium)\b/i', $text ) ) {
			$rows[] = array(
				'key'   => 'utm_medium',
				'field' => 'medium',
				'value' => 'email',
			);
		}

		if ( preg_match_all( '/\butm_([\w-]+)=([\w-]+)/i', $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$key = 'utm_' . strtolower( (string) ( $match[1] ?? '' ) );
				$val = (string) ( $match[2] ?? '' );
				if ( '' === $key || '' === $val ) {
					continue;
				}
				$rows[] = array(
					'key'   => $key,
					'field' => str_replace( 'utm_', '', $key ),
					'value' => $val,
				);
			}
		}

		return self::dedupe_rows( $rows );
	}

	/**
	 * Detect Google Ads traffic phrasing (may need UTM mapping before execution).
	 *
	 * @param string $text Normalised text.
	 * @return array<string,mixed>|null
	 */
	public static function extract_google_ads( $text ) {
		$text = RWGA_Local_Intent_Interpreter::normalise( (string) $text );
		if ( ! preg_match( '/\bgoogle\s+ads\b/i', $text ) ) {
			return null;
		}

		return array(
			'type'               => 'traffic_source',
			'value'              => 'google_ads',
			'status'             => 'needs_mapping',
			'label'              => __( 'Google Ads traffic', 'reactwoo-geocore' ),
			'warning'            => __( 'Google Ads traffic must be mapped to UTM parameters before this can be created.', 'reactwoo-geocore' ),
			'resolution_options' => array(
				array(
					'key'         => 'utm_source_google',
					'label'       => __( 'Match utm_source=google', 'reactwoo-geocore' ),
					'recommended' => false,
				),
				array(
					'key'         => 'utm_medium_cpc',
					'label'       => __( 'Match utm_medium=cpc', 'reactwoo-geocore' ),
					'recommended' => false,
				),
				array(
					'key'         => 'utm_source_google_and_medium_cpc',
					'label'       => __( 'Match utm_source=google AND utm_medium=cpc', 'reactwoo-geocore' ),
					'recommended' => true,
				),
				array(
					'key'         => 'gclid_exists',
					'label'       => __( 'Match gclid exists', 'reactwoo-geocore' ),
					'recommended' => false,
				),
				array(
					'key'         => 'configure_google_ads_mapping',
					'label'       => __( 'Configure Google Ads mapping', 'reactwoo-geocore' ),
					'recommended' => false,
				),
				array(
					'key'         => 'remove_google_ads_condition',
					'label'       => __( 'Remove Google Ads condition', 'reactwoo-geocore' ),
					'recommended' => false,
				),
			),
		);
	}

	/**
	 * @param array<int,array{key:string,field:string,value:string}> $rows Rows.
	 * @return array<int,array{key:string,field:string,value:string}>
	 */
	private static function dedupe_rows( array $rows ) {
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
}
