<?php
/**
 * WordPress Abilities API for the site-wide experiment.
 *
 * Registered only when the Abilities API exists. miniOrange and the WordPress
 * MCP adapter discover abilities with meta.public and meta.mcp.public.
 * There is no raw option write, SQL, or eval.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discoverable read, validate, save, status, and preview abilities.
 */
class RWGO_Sitewide_Ability {

	const CATEGORY = 'geo-optimise';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function definitions() {
		$save_schema = self::save_input_schema();
		$result      = self::result_schema();
		return array(
			array(
				'name'  => 'reactwoo-geo-optimise/sitewide-read',
				'label' => __( 'Read site-wide test', 'reactwoo-geo-optimise' ),
				'description' => __( 'Reads the site-wide experiment status, page map, header and footer template IDs, revision, warnings, and whether it can be activated. Does not change the test or publish anything.', 'reactwoo-geo-optimise' ),
				'category' => self::CATEGORY,
				'input_schema' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(),
				),
				'output_schema' => $result,
				'execute_callback' => array( __CLASS__, 'execute_read' ),
				'permission_callback' => array( __CLASS__, 'permissions' ),
				'meta' => self::meta( true, false, true, __( 'Read only. The revision in the response is required on the next save.', 'reactwoo-geo-optimise' ) ),
			),
			array(
				'name'  => 'reactwoo-geo-optimise/sitewide-validate',
				'label' => __( 'Validate site-wide test', 'reactwoo-geo-optimise' ),
				'description' => __( 'Checks a proposed page map, control weight, and header and footer IDs. Returns errors and warnings. Does not save, publish, or activate.', 'reactwoo-geo-optimise' ),
				'category' => self::CATEGORY,
				'input_schema' => $save_schema,
				'output_schema' => $result,
				'execute_callback' => array( __CLASS__, 'execute_validate' ),
				'permission_callback' => array( __CLASS__, 'permissions' ),
				'meta' => self::meta( true, false, true, __( 'Read only. Fix errors before saving. Warnings do not block a paused save.', 'reactwoo-geo-optimise' ) ),
			),
			array(
				'name'  => 'reactwoo-geo-optimise/sitewide-save',
				'label' => __( 'Save site-wide test', 'reactwoo-geo-optimise' ),
				'description' => __( 'Saves a typed site-wide page map and header and footer IDs. New tests stay paused. This does not change status, publish drafts, or copy Elementor templates. Send expected_revision from the last read.', 'reactwoo-geo-optimise' ),
				'category' => self::CATEGORY,
				'input_schema' => $save_schema,
				'output_schema' => $result,
				'execute_callback' => array( __CLASS__, 'execute_save' ),
				'permission_callback' => array( __CLASS__, 'permissions' ),
				'meta' => self::meta( false, false, false, __( 'Does not activate the test. Use the set-status ability with confirm true to go active. Does not publish pages or templates.', 'reactwoo-geo-optimise' ) ),
			),
			array(
				'name'  => 'reactwoo-geo-optimise/sitewide-set-status',
				'label' => __( 'Set site-wide test status', 'reactwoo-geo-optimise' ),
				'description' => __( 'Sets the site-wide test to active, paused, or ended. confirm must be true. Active is refused until a variant page is published, or a non-empty variant header or footer is published and Elementor Pro theme locations are active. Does not publish drafts.', 'reactwoo-geo-optimise' ),
				'category' => self::CATEGORY,
				'input_schema' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'expected_revision', 'status', 'confirm' ),
					'properties'           => array(
						'expected_revision' => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Revision from the last read.', 'reactwoo-geo-optimise' ),
						),
						'status'            => array(
							'type'        => 'string',
							'enum'        => array( 'active', 'paused', 'ended' ),
							'description' => __( 'Next status. Active shows the test to visitors.', 'reactwoo-geo-optimise' ),
						),
						'confirm'           => array(
							'type'        => 'boolean',
							'description' => __( 'Must be true. This is the explicit status change.', 'reactwoo-geo-optimise' ),
						),
					),
				),
				'output_schema' => $result,
				'execute_callback' => array( __CLASS__, 'execute_status' ),
				'permission_callback' => array( __CLASS__, 'permissions' ),
				'meta' => self::meta( false, false, false, __( 'Requires confirm true. This is the only ability that activates the test.', 'reactwoo-geo-optimise' ) ),
			),
			array(
				'name'  => 'reactwoo-geo-optimise/sitewide-preview-link',
				'label' => __( 'Create site-wide preview link', 'reactwoo-geo-optimise' ),
				'description' => __( 'Returns a signed preview URL for the current manager. It does not write the assignment cookie, record exposure, or change the test.', 'reactwoo-geo-optimise' ),
				'category' => self::CATEGORY,
				'input_schema' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'arm' ),
					'properties'           => array(
						'arm'     => array(
							'type' => 'string',
							'enum' => array( 'control', 'variant' ),
						),
						'page_id' => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Original page to open. 0 uses the first mapped page or the home URL.', 'reactwoo-geo-optimise' ),
						),
					),
				),
				'output_schema' => array(
					'type'       => 'object',
					'required'   => array( 'ok', 'arm', 'url', 'measures', 'assigns' ),
					'properties' => array(
						'ok'       => array( 'type' => 'boolean' ),
						'arm'      => array( 'type' => 'string' ),
						'url'      => array( 'type' => 'string' ),
						'measures' => array( 'type' => 'boolean' ),
						'assigns'  => array( 'type' => 'boolean' ),
						'code'     => array( 'type' => 'string' ),
						'message'  => array( 'type' => 'string' ),
					),
				),
				'execute_callback' => array( __CLASS__, 'execute_preview' ),
				'permission_callback' => array( __CLASS__, 'permissions' ),
				'meta' => self::meta( true, false, false, __( 'The link is for this user only. It is not a visitor assignment and it is not counted.', 'reactwoo-geo-optimise' ) ),
			),
		);
	}

	/**
	 * @return void
	 */
	public static function register_categories() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Geo Optimise', 'reactwoo-geo-optimise' ),
				'description' => __( 'Site-wide experiment configuration. Does not publish pages or templates.', 'reactwoo-geo-optimise' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		foreach ( self::definitions() as $definition ) {
			$name = $definition['name'];
			$args = $definition;
			unset( $args['name'] );
			wp_register_ability( $name, $args );
		}
	}

	/**
	 * @param mixed $input Input.
	 * @return bool|WP_Error
	 */
	public static function permissions( $input = null ) {
		unset( $input );
		$code = RWGO_Sitewide_Proposal::permission_code( self::user_can_manage(), self::license_known(), self::licensed() );
		if ( '' === $code ) {
			return true;
		}
		return self::failure( $code );
	}

	/**
	 * @param mixed $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute_read( $input = null ) {
		unset( $input );
		$denied = self::deny_array();
		if ( null !== $denied ) {
			return $denied;
		}
		return self::view( self::stored(), array() );
	}

	/**
	 * @param mixed $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute_validate( $input = null ) {
		$denied = self::deny_array();
		if ( null !== $denied ) {
			return $denied;
		}
		$input   = is_array( $input ) ? $input : array();
		$posts   = self::records_for_proposal( $input );
		$checked = RWGO_Sitewide_Proposal::inspect( $input, $posts );
		return array(
			'ok'         => $checked['ok'],
			'saved'      => false,
			'status'     => 'paused',
			'revision'   => isset( self::stored()['revision'] ) ? (int) self::stored()['revision'] : 0,
			'ready'      => RWGO_Sitewide_Proposal::activation_ready( $checked, self::locations_loaded() ),
			'page_map'   => $checked['page_map'],
			'theme_context' => $checked['theme_context'],
			'warnings'   => $checked['warnings'],
			'errors'     => $checked['errors'],
			'published'  => false,
		);
	}

	/**
	 * @param mixed $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute_save( $input = null ) {
		$denied = self::deny_array();
		if ( null !== $denied ) {
			return $denied;
		}
		$input  = is_array( $input ) ? $input : array();
		$stored = self::stored();
		$plan   = RWGO_Sitewide_Proposal::plan_save( $input, $stored, self::records_for_proposal( $input ) );
		if ( empty( $plan['ok'] ) ) {
			return self::failure( isset( $plan['code'] ) ? (string) $plan['code'] : 'rwgo_invalid_config', isset( $plan['errors'] ) ? $plan['errors'] : array() );
		}
		$saved = self::store( $plan['config'], $input['expected_revision'] );
		if ( is_array( $saved ) && isset( $saved['code'] ) ) {
			return self::failure( (string) $saved['code'] );
		}
		$view = self::view( is_array( $saved ) ? $saved : self::stored(), isset( $plan['warnings'] ) ? $plan['warnings'] : array() );
		$view['saved']     = true;
		$view['published'] = false;
		return $view;
	}

	/**
	 * @param mixed $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute_status( $input = null ) {
		$denied = self::deny_array();
		if ( null !== $denied ) {
			return $denied;
		}
		$input  = is_array( $input ) ? $input : array();
		$stored = self::stored();
		$plan   = RWGO_Sitewide_Proposal::plan_status( $input, $stored, self::records_for_config( $stored ), self::locations_loaded() );
		if ( empty( $plan['ok'] ) ) {
			return self::failure( isset( $plan['code'] ) ? (string) $plan['code'] : 'rwgo_invalid_status', isset( $plan['errors'] ) ? $plan['errors'] : array() );
		}
		$saved = self::store( $plan['config'], $input['expected_revision'] );
		if ( is_array( $saved ) && isset( $saved['code'] ) ) {
			return self::failure( (string) $saved['code'] );
		}
		$view = self::view( is_array( $saved ) ? $saved : self::stored(), isset( $plan['warnings'] ) ? $plan['warnings'] : array() );
		$view['saved']     = true;
		$view['published'] = false;
		return $view;
	}

	/**
	 * @param mixed $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function execute_preview( $input = null ) {
		$denied = self::deny_array();
		if ( null !== $denied ) {
			return $denied;
		}
		$input = is_array( $input ) ? $input : array();
		$arm   = isset( $input['arm'] ) ? sanitize_key( (string) $input['arm'] ) : '';
		if ( ! in_array( $arm, array( 'control', 'variant' ), true ) ) {
			return self::failure( 'rwgo_invalid_status' );
		}
		$page_id = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		$nonce   = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( class_exists( 'RWGO_Theme_Context', false ) ? RWGO_Theme_Context::PREVIEW_ACTION : 'rwgo_preview' ) : 'test-nonce';
		$base    = self::preview_base( $page_id, self::stored() );
		return array(
			'ok'       => true,
			'arm'      => $arm,
			'url'      => self::preview_url( $base, $arm, $nonce ),
			'measures' => false,
			'assigns'  => false,
			'code'     => '',
			'message'  => __( 'Preview only. Visitors are not assigned and the view is not counted.', 'reactwoo-geo-optimise' ),
		);
	}

	/**
	 * @param string $base  Absolute URL.
	 * @param string $arm   control|variant.
	 * @param string $nonce User nonce.
	 * @return string
	 */
	public static function preview_url( $base, $arm, $nonce ) {
		$args = array(
			'rwgo_preview'       => $arm,
			'rwgo_preview_nonce' => $nonce,
		);
		if ( function_exists( 'add_query_arg' ) ) {
			return add_query_arg( $args, $base );
		}
		$join = false === strpos( $base, '?' ) ? '?' : '&';
		return $base . $join . http_build_query( $args );
	}

	/**
	 * @param array<string, mixed> $cfg Config whose IDs should be looked up.
	 * @return array<int, array<string, mixed>|null>
	 */
	public static function records_for_config( array $cfg ) {
		return self::records_for_ids( self::referenced_ids( $cfg ) );
	}

	/**
	 * @param bool   $readonly    Does not change the test.
	 * @param bool   $destructive Deletes or destroys content.
	 * @param bool   $idempotent  Safe to repeat.
	 * @param string $instructions Confirmation hint.
	 * @return array<string, mixed>
	 */
	private static function meta( $readonly, $destructive, $idempotent, $instructions ) {
		return array(
			'public'       => true,
			'show_in_rest' => true,
			'mcp'          => array(
				'public' => true,
			),
			'annotations'  => array(
				'readonly'     => (bool) $readonly,
				'destructive'  => (bool) $destructive,
				'idempotent'   => (bool) $idempotent,
				'instructions' => $instructions,
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function save_input_schema() {
		$id = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'expected_revision', 'page_map' ),
			'properties'           => array(
				'expected_revision' => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => __( 'Revision from sitewide-read. Required so two saves cannot overwrite each other.', 'reactwoo-geo-optimise' ),
				),
				'experiment_key'    => array(
					'type'      => 'string',
					'maxLength' => 80,
				),
				'control_weight_percent' => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'maximum'     => 100,
					'description' => __( 'Percent of visitors who stay on control.', 'reactwoo-geo-optimise' ),
				),
				'cookie_days'       => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 365,
				),
				'page_map'          => array(
					'type'  => 'array',
					'items' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'required'             => array( 'source_page_id', 'variant_page_id' ),
						'properties'           => array(
							'source_page_id'  => $id,
							'variant_page_id' => $id,
						),
					),
				),
				'theme_context'     => array(
					'type'       => 'object',
					'additionalProperties' => false,
					'properties' => array(
						'control' => self::theme_arm_schema(),
						'variant' => self::theme_arm_schema(),
					),
				),
				'goal_page_ids'     => array(
					'type'  => 'array',
					'items' => $id,
				),
				'track_purchase'    => array(
					'type' => 'boolean',
				),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function theme_arm_schema() {
		$optional = array(
			'type'    => 'integer',
			'minimum' => 0,
		);
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'header' => $optional,
				'footer' => $optional,
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function result_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'ok'            => array( 'type' => 'boolean' ),
				'saved'         => array( 'type' => 'boolean' ),
				'published'     => array( 'type' => 'boolean' ),
				'status'        => array( 'type' => 'string' ),
				'revision'      => array( 'type' => 'integer' ),
				'ready'         => array( 'type' => 'boolean' ),
				'licensed'      => array( 'type' => 'boolean' ),
				'page_map'      => array( 'type' => 'array' ),
				'theme_context' => array( 'type' => 'object' ),
				'warnings'      => array( 'type' => 'array' ),
				'errors'        => array( 'type' => 'array' ),
			),
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function deny_array() {
		$result = self::permissions( null );
		if ( true === $result ) {
			return null;
		}
		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'saved'   => false,
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			);
		}
		return array(
			'ok'    => false,
			'saved' => false,
			'code'  => 'rwgo_forbidden',
		);
	}

	/**
	 * @param string               $code   Error code.
	 * @param array<int, mixed>    $errors Optional issues.
	 * @return WP_Error|array<string, mixed>
	 */
	private static function failure( $code, array $errors = array() ) {
		$message = class_exists( 'RWGO_Sitewide_Proposal', false ) ? RWGO_Sitewide_Proposal::message( $code, 0 ) : $code;
		if ( class_exists( 'WP_Error', false ) ) {
			return new WP_Error(
				$code,
				$message,
				array(
					'status' => 'rwgo_forbidden' === $code || 'rwgo_unlicensed' === $code ? 403 : 400,
					'errors' => $errors,
				)
			);
		}
		return array(
			'ok'      => false,
			'code'    => $code,
			'message' => $message,
			'errors'  => $errors,
		);
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @param array<int, mixed>    $warnings Warnings.
	 * @return array<string, mixed>
	 */
	private static function view( array $cfg, array $warnings ) {
		$posts   = self::records_for_config( $cfg );
		$checked = class_exists( 'RWGO_Sitewide_Proposal', false ) ? RWGO_Sitewide_Proposal::inspect( $cfg, $posts ) : array( 'warnings' => array(), 'page_ready' => false, 'chrome_ready' => false );
		$list    = array() !== $warnings ? $warnings : ( isset( $checked['warnings'] ) ? $checked['warnings'] : array() );
		$status  = isset( $cfg['status'] ) ? sanitize_key( (string) $cfg['status'] ) : 'paused';
		return array(
			'ok'              => true,
			'saved'           => false,
			'published'       => false,
			'status'          => '' !== $status ? $status : 'paused',
			'revision'        => isset( $cfg['revision'] ) ? (int) $cfg['revision'] : 0,
			'experiment_key'  => isset( $cfg['experiment_key'] ) ? sanitize_key( (string) $cfg['experiment_key'] ) : '',
			'control_weight_percent' => isset( $cfg['control_weight'] ) ? (int) round( (float) $cfg['control_weight'] * 100 ) : 50,
			'cookie_days'     => isset( $cfg['cookie_days'] ) ? (int) $cfg['cookie_days'] : 90,
			'page_map'        => isset( $checked['page_map'] ) ? $checked['page_map'] : array(),
			'theme_context'   => isset( $checked['theme_context'] ) ? $checked['theme_context'] : array(),
			'warnings'        => $list,
			'errors'          => array(),
			'ready'           => class_exists( 'RWGO_Sitewide_Proposal', false ) && RWGO_Sitewide_Proposal::activation_ready( $checked, self::locations_loaded() ),
			'licensed'        => self::licensed(),
			'elementor_pro_locations' => self::locations_loaded(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function stored() {
		if ( ! class_exists( 'RWGO_Sitewide', false ) ) {
			return array();
		}
		$cfg = RWGO_Sitewide::get_config();
		return is_array( $cfg ) ? $cfg : array();
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @param mixed                $expected Revision the caller read.
	 * @return array<string, mixed>
	 */
	private static function store( array $cfg, $expected ) {
		if ( ! class_exists( 'RWGO_Sitewide', false ) ) {
			return array( 'code' => 'rwgo_invalid_config' );
		}
		return RWGO_Sitewide::store_config( $cfg, $expected );
	}

	/**
	 * @param array<string, mixed> $input Proposal.
	 * @return array<int, array<string, mixed>|null>
	 */
	private static function records_for_proposal( array $input ) {
		return self::records_for_ids( self::referenced_ids( $input ) );
	}

	/**
	 * @param array<int, int> $ids IDs.
	 * @return array<int, array<string, mixed>|null>
	 */
	private static function records_for_ids( array $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$id         = (int) $id;
			$out[ $id ] = self::record( $id );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return array<int, int>
	 */
	private static function referenced_ids( array $cfg ) {
		$ids  = array();
		$rows = isset( $cfg['page_map'] ) && is_array( $cfg['page_map'] ) ? $cfg['page_map'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( array( 'source_page_id', 'variant_page_id' ) as $key ) {
				$id = RWGO_Sitewide_Proposal::positive_int( $row[ $key ] ?? null );
				if ( null !== $id ) {
					$ids[] = $id;
				}
			}
		}
		if ( class_exists( 'RWGO_Theme_Context', false ) ) {
			foreach ( RWGO_Theme_Context::referenced_post_ids( $cfg ) as $id ) {
				$ids[] = (int) $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param int $id Post ID.
	 * @return array<string, mixed>|null
	 */
	private static function record( $id ) {
		if ( $id <= 0 || ! function_exists( 'get_post' ) ) {
			return null;
		}
		$post = get_post( $id );
		if ( ! is_object( $post ) ) {
			return null;
		}
		$raw   = function_exists( 'get_post_meta' ) ? get_post_meta( $id, '_elementor_data', true ) : '';
		$empty = ! is_string( $raw ) || '' === trim( $raw ) || '[]' === trim( $raw );
		return array(
			'type'            => isset( $post->post_type ) ? (string) $post->post_type : '',
			'status'          => isset( $post->post_status ) ? (string) $post->post_status : '',
			'elementor_empty' => $empty,
		);
	}

	/**
	 * @param int                  $page_id Requested page.
	 * @param array<string, mixed> $cfg Stored config.
	 * @return string
	 */
	private static function preview_base( $page_id, array $cfg ) {
		$page_id = (int) $page_id;
		if ( $page_id <= 0 && ! empty( $cfg['page_map'][0]['source_page_id'] ) ) {
			$page_id = (int) $cfg['page_map'][0]['source_page_id'];
		}
		if ( $page_id > 0 && function_exists( 'get_permalink' ) ) {
			$link = get_permalink( $page_id );
			if ( is_string( $link ) && '' !== $link ) {
				return $link;
			}
		}
		return function_exists( 'home_url' ) ? (string) home_url( '/' ) : '/';
	}

	/**
	 * @return bool
	 */
	private static function user_can_manage() {
		if ( class_exists( 'RWGO_Admin', false ) ) {
			return RWGO_Admin::can_manage();
		}
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * @return bool
	 */
	private static function license_known() {
		return class_exists( 'RWGO_Platform_Client', false );
	}

	/**
	 * @return bool
	 */
	private static function licensed() {
		$licensed = true;
		if ( self::license_known() ) {
			$licensed = RWGO_Platform_Client::is_configured();
			if ( ! $licensed && class_exists( 'RWGA_License', false ) && is_callable( array( 'RWGA_License', 'can_run_workflows' ) ) ) {
				$licensed = (bool) RWGA_License::can_run_workflows();
			}
		}
		if ( function_exists( 'apply_filters' ) ) {
			$licensed = (bool) apply_filters( 'rwgo_sitewide_ability_licensed', $licensed, self::license_known() );
		}
		return $licensed;
	}

	/**
	 * @return bool
	 */
	private static function locations_loaded() {
		return class_exists( 'RWGO_Theme_Context', false ) && RWGO_Theme_Context::elementor_pro_locations_loaded();
	}
}
