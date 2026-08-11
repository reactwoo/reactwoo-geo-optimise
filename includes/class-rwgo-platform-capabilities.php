<?php
/**
 * Register Geo Optimise experiment/goal capabilities with the platform registry.
 *
 * Existing {@see RWGO_Assignment} and goal tracking remain unchanged.
 *
 * @package ReactWoo_Geo_Optimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optimise → platform capability bridge.
 */
class RWGO_Platform_Capabilities {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'reactwoo_register_capabilities', array( __CLASS__, 'register' ), 40 );
	}

	/**
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'reactwoo_register_capability' ) ) {
			return;
		}

		$provider = 'reactwoo-geo-optimise';
		$version  = defined( 'RWGO_VERSION' ) ? RWGO_VERSION : '1';

		if ( function_exists( 'reactwoo_register_action' ) && ! reactwoo_has_capability( 'experiment.assign' ) ) {
			reactwoo_register_action(
				'experiment.assign',
				array(
					'label'       => __( 'Experiment assignment', 'reactwoo-geo-optimise' ),
					'description' => __( 'Stable visitor → variant assignment (cookie-backed).', 'reactwoo-geo-optimise' ),
					'provider'    => $provider,
					'version'     => $version,
					'entitlement' => 'geo_optimise',
				)
			);
		}

		$goals = array(
			'goal.purchase'     => __( 'Purchase', 'reactwoo-geo-optimise' ),
			'goal.add_to_cart'  => __( 'Add to cart', 'reactwoo-geo-optimise' ),
			'goal.click'        => __( 'Click', 'reactwoo-geo-optimise' ),
			'goal.lead'         => __( 'Lead', 'reactwoo-geo-optimise' ),
			'goal.page_view'    => __( 'Page view', 'reactwoo-geo-optimise' ),
			'goal.custom'       => __( 'Custom goal', 'reactwoo-geo-optimise' ),
			'commerce.purchase' => __( 'Commerce purchase', 'reactwoo-geo-optimise' ),
			'commerce.add_to_cart' => __( 'Commerce add to cart', 'reactwoo-geo-optimise' ),
		);

		foreach ( $goals as $id => $label ) {
			if ( function_exists( 'reactwoo_has_capability' ) && reactwoo_has_capability( $id ) ) {
				continue;
			}
			reactwoo_register_goal(
				$id,
				array(
					'label'       => $label,
					'description' => $label,
					'provider'    => $provider,
					'version'     => $version,
					'entitlement' => 'geo_optimise',
				)
			);
		}
	}
}
