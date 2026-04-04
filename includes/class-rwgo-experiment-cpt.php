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
		add_action( 'init', array( __CLASS__, 'register' ) );
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
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'edit_post'          => 'manage_options',
					'read_post'          => 'manage_options',
					'delete_post'        => 'manage_options',
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'publish_posts'      => 'manage_options',
					'read_private_posts' => 'manage_options',
					'create_posts'       => 'manage_options',
				),
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}
}
