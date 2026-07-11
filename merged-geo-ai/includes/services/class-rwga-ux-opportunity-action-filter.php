<?php
/**
 * Capability-aware filtering for UX opportunity recommendation actions.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server-side action gating before pending intelligence actions are stored.
 */
class RWGA_UX_Opportunity_Action_Filter {

	/**
	 * @return array<string, string>
	 */
	public static function upgrade_labels() {
		return array(
			'geocore_pro'   => __( 'Requires GeoCore Pro', 'reactwoo-geo-ai' ),
			'geo_optimise'  => __( 'Requires Geo Optimise', 'reactwoo-geo-ai' ),
			'geo_commerce'  => __( 'Requires Geo Commerce', 'reactwoo-geo-ai' ),
			'woocommerce'   => __( 'Requires WooCommerce', 'reactwoo-geo-ai' ),
			'remote_ai'     => __( 'Requires remote Geo AI', 'reactwoo-geo-ai' ),
			'geo_ai_license'=> __( 'Requires valid licence', 'reactwoo-geo-ai' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_capabilities() {
		if ( function_exists( 'rwgc_get_suite_capability_map' ) ) {
			return rwgc_get_suite_capability_map();
		}
		return array(
			'geocore_active'           => class_exists( 'RWGC_Plugin', false ),
			'geo_ai_licensed'          => class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows(),
			'local_fallback_available' => true,
		);
	}

	/**
	 * @param array<string, mixed> $card         Recommendation card.
	 * @param array<string, mixed> $capabilities Capability map.
	 * @return array<string, mixed>
	 */
	public static function enrich_card( array $card, array $capabilities ) {
		$action = sanitize_key( (string) ( $card['suggested_action'] ?? '' ) );
		$eval   = self::evaluate_action( $action, $capabilities );

		$card['available_now']     = ! empty( $eval['available_now'] );
		$card['required_products'] = isset( $eval['required_products'] ) && is_array( $eval['required_products'] )
			? $eval['required_products']
			: array();
		$card['upgrade_label']     = (string) ( $eval['upgrade_label'] ?? '' );
		$card['requires_approval'] = true;
		if ( empty( $card['effort'] ) ) {
			$card['effort'] = 'medium';
		}

		return $card;
	}

	/**
	 * @param string               $action_type  Suggested action key.
	 * @param array<string, mixed> $capabilities Capability map.
	 * @return bool
	 */
	public static function should_create_pending_action( $action_type, array $capabilities ) {
		$eval = self::evaluate_action( $action_type, $capabilities );
		return ! empty( $eval['available_now'] ) && ! empty( $eval['creates_pending_action'] );
	}

	/**
	 * @param string               $action_type  Action key.
	 * @param array<string, mixed> $capabilities Capability map.
	 * @return array<string, mixed>
	 */
	public static function evaluate_action( $action_type, array $capabilities ) {
		$action_type = sanitize_key( (string) $action_type );
		$labels      = self::upgrade_labels();

		$base = array(
			'available_now'          => false,
			'creates_pending_action' => false,
			'required_products'      => array(),
			'upgrade_label'          => '',
		);

		if ( '' === $action_type ) {
			return $base;
		}

		if ( in_array( $action_type, array( 'requires_geocore_pro', 'requires_geo_optimise', 'requires_geo_commerce', 'requires_woocommerce', 'requires_remote_ai' ), true ) ) {
			$key = str_replace( 'requires_', '', $action_type );
			if ( 'remote_ai' === $key ) {
				$key = 'remote_ai';
			}
			$base['upgrade_label'] = $labels[ $key ] ?? '';
			return $base;
		}

		if ( ! empty( $capabilities['geo_ai_licensed'] ) ) {
			// Geo AI licence required for executable actions.
		} elseif ( class_exists( 'RWGA_License', false ) && ! RWGA_License::can_run_workflows() ) {
			$base['upgrade_label'] = $labels['geo_ai_license'];
			return $base;
		}

		switch ( $action_type ) {
			case 'create_implementation_draft':
				$base['available_now']          = true;
				$base['creates_pending_action'] = true;
				return $base;

			case 'open_admin_route':
				$base['available_now']          = true;
				$base['creates_pending_action'] = true;
				return $base;

			case 'create_optimise_test_prefill':
				if ( ! empty( $capabilities['geo_optimise_active'] ) && ! empty( $capabilities['geo_optimise_licensed'] ) ) {
					$base['available_now']          = true;
					$base['creates_pending_action'] = true;
					return $base;
				}
				$base['required_products'] = array( 'geo_optimise' );
				$base['upgrade_label']     = $labels['geo_optimise'];
				return $base;
		}

		return $base;
	}

	/**
	 * @param array<string, mixed> $card         Card row.
	 * @param array<string, mixed> $capabilities Capabilities.
	 * @return array<string, mixed>|null Pending action row or null.
	 */
	public static function build_pending_action_row( array $card, array $capabilities ) {
		$action = sanitize_key( (string) ( $card['suggested_action'] ?? '' ) );
		if ( ! self::should_create_pending_action( $action, $capabilities ) ) {
			return null;
		}

		$label = isset( $card['title'] ) ? (string) $card['title'] : __( 'UX opportunity action', 'reactwoo-geo-ai' );
		$payload = array(
			'page_id'        => isset( $card['page_id'] ) ? (int) $card['page_id'] : 0,
			'product_id'     => isset( $card['product_id'] ) ? (int) $card['product_id'] : 0,
			'variant_page_id'=> isset( $card['variant_page_id'] ) ? (int) $card['variant_page_id'] : 0,
			'ux_area'        => isset( $card['ux_area'] ) ? (string) $card['ux_area'] : '',
			'recommendation' => isset( $card['recommendation'] ) ? (string) $card['recommendation'] : '',
		);

		if ( 'open_admin_route' === $action ) {
			$payload['admin_page']  = isset( $card['admin_page'] ) ? sanitize_key( (string) $card['admin_page'] ) : 'rwgc-visibility-rules';
			$payload['query_args']  = isset( $card['query_args'] ) && is_array( $card['query_args'] ) ? $card['query_args'] : array();
		}
		if ( 'create_optimise_test_prefill' === $action ) {
			$payload['test_name'] = $label;
			$payload['source_id'] = isset( $card['variant_page_id'] ) && (int) $card['variant_page_id'] > 0
				? (int) $card['variant_page_id']
				: (int) ( $card['page_id'] ?? 0 );
		}
		if ( 'create_implementation_draft' === $action ) {
			$payload['draft_type']    = 'ux_opportunity';
			$payload['title']         = $label;
			$payload['input_context'] = isset( $card['recommendation'] ) ? (string) $card['recommendation'] : '';
			$payload['draft_payload'] = array(
				'ux_area'   => isset( $card['ux_area'] ) ? (string) $card['ux_area'] : '',
				'audience'  => isset( $card['audience'] ) ? (string) $card['audience'] : '',
				'problem'   => isset( $card['problem'] ) ? (string) $card['problem'] : '',
			);
		}

		return array(
			'action_type' => $action,
			'label'       => $label,
			'action_json' => $payload,
			'entity_type' => 'ux_opportunity',
			'entity_id'   => isset( $card['ux_area'] ) ? sanitize_key( (string) $card['ux_area'] ) : '',
		);
	}
}
