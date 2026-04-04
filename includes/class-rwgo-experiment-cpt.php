<?php
/**
 * Experiment configuration storage (internal CPT; no public UI — lists use custom screens).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the rwgo_experiment post type.
 */
class RWGO_Experiment_CPT {

	const POST_TYPE = 'rwgo_experiment';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 9 );
		add_action( 'init', array( __CLASS__, 'ensure_role_caps' ), 11 );
	}

	/**
	 * Grant experiment primitives to roles that may use Geo Optimise (admins + WooCommerce shop managers).
	 * Keeps the CPT private while matching RWGO_Admin::required_capability().
	 *
	 * @return void
	 */
	public static function ensure_role_caps() {
		$pto = get_post_type_object( self::POST_TYPE );
		if ( ! $pto || empty( $pto->cap ) || ! is_object( $pto->cap ) ) {
			return;
		}
		foreach ( get_object_vars( $pto->cap ) as $cap_name ) {
			if ( ! is_string( $cap_name ) || '' === $cap_name ) {
				continue;
			}
			foreach ( wp_roles()->roles as $_role_name => $_info ) {
				$role = get_role( $_role_name );
				if ( ! $role ) {
					continue;
				}
				if ( ! $role->has_cap( 'manage_options' ) && ! $role->has_cap( 'manage_woocommerce' ) ) {
					continue;
				}
				$role->add_cap( $cap_name );
			}
		}
	}

	/**
	 * @return void
	 */
	public static function register() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Geo Optimise tests', 'reactwoo-geo-optimise' ),
					'singular_name' => __( 'Test', 'reactwoo-geo-optimise' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'exclude_from_search' => true,
				'capability_type'     => array( 'rwgo_experiment', 'rwgo_experiments' ),
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}
}
