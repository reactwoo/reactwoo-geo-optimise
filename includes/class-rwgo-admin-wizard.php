<?php
/**
 * Create Test wizard — admin POST handler.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wizard / create flows.
 */
class RWGO_Admin_Wizard {

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_rwgo_create_test', array( __CLASS__, 'handle_create_test' ) );
		add_action( 'admin_post_rwgo_update_test', array( __CLASS__, 'handle_update_test' ) );
	}

	/**
	 * Quick create: source page → duplicate variant B → active experiment with auto key.
	 *
	 * @return void
	 */
	public static function handle_create_test() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_create_test' );

		$title  = isset( $_POST['rwgo_test_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgo_test_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$source = isset( $_POST['rwgo_source_page'] ) ? (int) $_POST['rwgo_source_page'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $title || $source <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-create-test&rwgo_error=missing' ) );
			exit;
		}

		$post = get_post( $source );
		if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-create-test&rwgo_error=perm' ) );
			exit;
		}

		$test_type     = isset( $_POST['rwgo_test_type'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_test_type'] ) ) : 'page_ab'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$allowed_types = array( 'page_ab', 'elementor_page', 'gutenberg_page', 'woo_product', 'custom_php' );
		if ( ! in_array( $test_type, $allowed_types, true ) ) {
			$test_type = 'page_ab';
		}
		if ( ! class_exists( 'RWGO_Admin_Content_Catalog', false ) || ! RWGO_Admin_Content_Catalog::is_valid_for_test_type( $source, $test_type ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-create-test&rwgo_error=source_type' ) );
			exit;
		}

		$winner_mode_early = isset( $_POST['rwgo_winner_mode'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_winner_mode'] ) ) : 'goal'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $winner_mode_early, array( 'goal', 'traffic_only' ), true ) ) {
			$winner_mode_early = 'goal';
		}

		if ( class_exists( 'RWGO_Settings', false ) && RWGO_Settings::require_goal_confirm_publish() && 'traffic_only' !== $winner_mode_early ) {
			$confirmed = isset( $_POST['rwgo_confirm_publish'] ) && '1' === (string) wp_unslash( $_POST['rwgo_confirm_publish'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( ! $confirmed ) {
				wp_safe_redirect( admin_url( 'admin.php?page=rwgo-create-test&rwgo_error=confirm' ) );
				exit;
			}
		}

		$winner_mode = $winner_mode_early;

		$goal_type = isset( $_POST['rwgo_goal_type'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_goal_type'] ) ) : 'page_view'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$allowed_goals = array( 'page_view', 'cta_click', 'button_click', 'form_submit', 'add_to_cart', 'begin_checkout', 'purchase', 'custom_event' );
		if ( 'goal' === $winner_mode && ! in_array( $goal_type, $allowed_goals, true ) ) {
			$goal_type = 'page_view';
		}

		$variant_mode = isset( $_POST['rwgo_variant_mode'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_variant_mode'] ) ) : 'duplicate'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$allowed_vm   = array( 'duplicate', 'existing', 'blank' );
		if ( ! in_array( $variant_mode, $allowed_vm, true ) ) {
			$variant_mode = 'duplicate';
		}

		$dup = 0;
		if ( 'existing' === $variant_mode ) {
			$dup = isset( $_POST['rwgo_variant_b_page'] ) ? (int) $_POST['rwgo_variant_b_page'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $dup <= 0 || $dup === $source ) {
				wp_safe_redirect( admin_url( 'admin.php?page=rwgo-create-test&rwgo_error=variant_b' ) );
				exit;
			}
			if ( ! class_exists( 'RWGO_Admin_Content_Catalog', false ) || ! RWGO_Admin_Content_Catalog::is_valid_for_test_type( $dup, $test_type ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=rwgo-create-test&rwgo_error=variant_b' ) );
				exit;
			}
		} elseif ( 'blank' === $variant_mode ) {
			$blank = RWGO_Page_Duplicator::create_blank_variant( $source, $title );
			if ( is_wp_error( $blank ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=rwgo-create-test&rwgo_error=dup' ) );
				exit;
			}
			$dup = (int) $blank;
		} else {
			$dup_res = RWGO_Page_Duplicator::duplicate( $source );
			if ( is_wp_error( $dup_res ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=rwgo-create-test&rwgo_error=dup' ) );
				exit;
			}
			$dup = (int) $dup_res;
		}

		$key = RWGO_Experiment_Service::generate_experiment_key( $source );

		$mode = isset( $_POST['rwgo_targeting_mode'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_targeting_mode'] ) ) : 'everyone'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$targeting = array( 'mode' => 'everyone' );
		if ( 'countries' === $mode ) {
			$raw       = isset( $_POST['rwgo_countries'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgo_countries'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$parts     = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
			$targeting = array(
				'mode'      => 'countries',
				'countries' => array_map( 'strtoupper', $parts ),
			);
		}

		$detection = class_exists( 'RWGO_Builder_Detector', false )
			? RWGO_Builder_Detector::detect( $source )
			: array();

		$goals           = array();
		$primary_goal_id = '';
		$assignment_only = ( 'traffic_only' === $winner_mode );

		if ( $assignment_only ) {
			$goals = array();
		} else {
			$built = self::build_goals_from_goal_type( $goal_type );
			$goals = $built['goals'];
			$primary_goal_id = $built['primary_goal_id'];
		}

		$config = array(
			'experiment_key'    => $key,
			'status'            => 'active',
			'test_type'         => $test_type,
			'variant_creation'  => $variant_mode,
			'source_page_id'    => $source,
			'builder_type'      => isset( $detection['builder'] ) ? (string) $detection['builder'] : self::legacy_detect_builder_string( $source ),
			'builder_detection' => $detection,
			'variants'          => RWGO_Experiment_Service::default_variants( $source, $dup ),
			'targeting'         => $targeting,
			'traffic_weights'   => array( 'control' => 0.5, 'var_b' => 0.5 ),
			'goals'             => $goals,
			'winner_mode'       => $assignment_only ? 'traffic_only' : 'goal',
			'assignment_only'   => $assignment_only,
			'primary_goal_id'   => $primary_goal_id,
		);

		$exp_post = wp_insert_post(
			array(
				'post_type'   => RWGO_Experiment_CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $exp_post ) || ! $exp_post ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-create-test&rwgo_error=save' ) );
			exit;
		}
		RWGO_Experiment_Repository::save_config( (int) $exp_post, $config );

		wp_safe_redirect(
			admin_url(
				'admin.php?page=rwgo-edit-test&rwgo_experiment_id=' . (int) $exp_post . '&rwgo_created=1'
			)
		);
		exit;
	}

	/**
	 * Update an existing managed test (experiment config + post title).
	 *
	 * @return void
	 */
	public static function handle_update_test() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_update_test' );

		$exp_id = isset( $_POST['rwgo_experiment_id'] ) ? (int) $_POST['rwgo_experiment_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $exp_id <= 0 ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests' ) );
			exit;
		}

		$exp_post = get_post( $exp_id );
		if ( ! $exp_post instanceof \WP_Post || RWGO_Experiment_CPT::POST_TYPE !== $exp_post->post_type ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests' ) );
			exit;
		}

		if ( ! current_user_can( 'edit_post', $exp_id ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=rwgo-tests&rwgo_error=perm' ) );
			exit;
		}

		$prev = RWGO_Experiment_Repository::get_config( $exp_id );
		if ( empty( $prev['experiment_key'] ) ) {
			wp_safe_redirect( add_query_arg( 'rwgo_error', 'config', RWGO_Admin::edit_test_url( $exp_id ) ) );
			exit;
		}

		$title = isset( $_POST['rwgo_test_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgo_test_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $title ) {
			wp_safe_redirect( add_query_arg( 'rwgo_error', 'missing', RWGO_Admin::edit_test_url( $exp_id ) ) );
			exit;
		}

		$test_type = isset( $prev['test_type'] ) ? sanitize_key( (string) $prev['test_type'] ) : 'page_ab';
		$source    = (int) ( $prev['source_page_id'] ?? 0 );
		if ( $source <= 0 ) {
			wp_safe_redirect( add_query_arg( 'rwgo_error', 'config', RWGO_Admin::edit_test_url( $exp_id ) ) );
			exit;
		}

		$winner_mode = isset( $_POST['rwgo_winner_mode'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_winner_mode'] ) ) : 'goal'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $winner_mode, array( 'goal', 'traffic_only' ), true ) ) {
			$winner_mode = 'goal';
		}

		$goal_type = isset( $_POST['rwgo_goal_type'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_goal_type'] ) ) : 'page_view'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$allowed_goals = array( 'page_view', 'cta_click', 'button_click', 'form_submit', 'add_to_cart', 'begin_checkout', 'purchase', 'custom_event' );
		if ( 'goal' === $winner_mode && ! in_array( $goal_type, $allowed_goals, true ) ) {
			$goal_type = 'page_view';
		}

		$mode = isset( $_POST['rwgo_targeting_mode'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_targeting_mode'] ) ) : 'everyone'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$targeting = array( 'mode' => 'everyone' );
		if ( 'countries' === $mode ) {
			$raw   = isset( $_POST['rwgo_countries'] ) ? sanitize_text_field( wp_unslash( $_POST['rwgo_countries'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$parts = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
			$targeting = array(
				'mode'      => 'countries',
				'countries' => array_map( 'strtoupper', $parts ),
			);
		}

		$assignment_only = ( 'traffic_only' === $winner_mode );
		$goals             = array();
		$primary_goal_id   = isset( $prev['primary_goal_id'] ) ? (string) $prev['primary_goal_id'] : '';

		$prev_traffic = ! empty( $prev['assignment_only'] ) || ( isset( $prev['winner_mode'] ) && 'traffic_only' === $prev['winner_mode'] );
		$prev_goal_inf = self::infer_goal_type_from_config( $prev );

		if ( $assignment_only ) {
			$goals           = array();
			$primary_goal_id = '';
		} elseif ( ! $prev_traffic && ! $assignment_only && $prev_goal_inf === $goal_type ) {
			$goals           = isset( $prev['goals'] ) && is_array( $prev['goals'] ) ? $prev['goals'] : array();
			$primary_goal_id = isset( $prev['primary_goal_id'] ) ? (string) $prev['primary_goal_id'] : '';
		} else {
			$built           = self::build_goals_from_goal_type( $goal_type );
			$goals           = $built['goals'];
			$primary_goal_id = $built['primary_goal_id'];
		}

		$variants = isset( $prev['variants'] ) && is_array( $prev['variants'] ) ? $prev['variants'] : RWGO_Experiment_Service::default_variants( $source, 0 );

		$var_action = isset( $_POST['rwgo_variant_edit_action'] ) ? sanitize_key( wp_unslash( $_POST['rwgo_variant_edit_action'] ) ) : 'keep'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $var_action, array( 'keep', 'replace_existing', 'duplicate_source' ), true ) ) {
			$var_action = 'keep';
		}

		$var_b_new = (int) self::variant_b_page_id_from_variants( $variants );
		$variant_creation = isset( $prev['variant_creation'] ) ? sanitize_key( (string) $prev['variant_creation'] ) : 'duplicate';

		if ( 'replace_existing' === $var_action ) {
			$new_id = isset( $_POST['rwgo_variant_b_page'] ) ? (int) $_POST['rwgo_variant_b_page'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $new_id <= 0 || $new_id === $source ) {
				wp_safe_redirect( RWGO_Admin::edit_test_url( $exp_id ) . '&rwgo_error=variant_b' );
				exit;
			}
			if ( ! class_exists( 'RWGO_Admin_Content_Catalog', false ) || ! RWGO_Admin_Content_Catalog::is_valid_for_test_type( $new_id, $test_type ) ) {
				wp_safe_redirect( RWGO_Admin::edit_test_url( $exp_id ) . '&rwgo_error=variant_b' );
				exit;
			}
			$variants         = self::patch_variants_var_b( $variants, $new_id );
			$var_b_new        = $new_id;
			$variant_creation = 'existing';
		} elseif ( 'duplicate_source' === $var_action ) {
			$dup_res = RWGO_Page_Duplicator::duplicate( $source );
			if ( is_wp_error( $dup_res ) ) {
				wp_safe_redirect( add_query_arg( 'rwgo_error', 'dup', RWGO_Admin::edit_test_url( $exp_id ) ) );
				exit;
			}
			$variants         = self::patch_variants_var_b( $variants, (int) $dup_res );
			$var_b_new        = (int) $dup_res;
			$variant_creation = 'duplicate';
		} else {
			if ( $var_b_new <= 0 || ! get_post( $var_b_new ) ) {
				wp_safe_redirect( add_query_arg( 'rwgo_error', 'variant_missing', RWGO_Admin::edit_test_url( $exp_id ) ) );
				exit;
			}
		}

		$config = array(
			'targeting'         => $targeting,
			'goals'             => $goals,
			'winner_mode'       => $assignment_only ? 'traffic_only' : 'goal',
			'assignment_only'   => $assignment_only,
			'primary_goal_id'   => $primary_goal_id,
			'variants'          => $variants,
			'variant_creation'  => $variant_creation,
		);

		wp_update_post(
			array(
				'ID'         => $exp_id,
				'post_title' => $title,
			)
		);

		RWGO_Experiment_Repository::save_config( $exp_id, $config );

		wp_safe_redirect( add_query_arg( 'rwgo_saved', '1', RWGO_Admin::edit_test_url( $exp_id ) ) );
		exit;
	}

	/**
	 * Variant B page ID from variants array.
	 *
	 * @param array<int, array<string, mixed>> $variants Variants.
	 * @return int
	 */
	private static function variant_b_page_id_from_variants( array $variants ) {
		foreach ( $variants as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
				return (int) ( $row['page_id'] ?? 0 );
			}
		}
		return 0;
	}

	/**
	 * Set variant B page_id in variants list.
	 *
	 * @param array<int, array<string, mixed>> $variants Variants.
	 * @param int                              $page_id  New Variant B post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private static function patch_variants_var_b( array $variants, $page_id ) {
		$page_id = (int) $page_id;
		foreach ( $variants as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['variant_id'] ) && 'var_b' === sanitize_key( (string) $row['variant_id'] ) ) {
				$variants[ $i ]['page_id'] = $page_id;
				return $variants;
			}
		}
		$control_id = 0;
		foreach ( $variants as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['variant_id'] ) && 'control' === sanitize_key( (string) $row['variant_id'] ) ) {
				$control_id = (int) ( $row['page_id'] ?? 0 );
				break;
			}
		}
		if ( $control_id <= 0 ) {
			return $variants;
		}
		return RWGO_Experiment_Service::default_variants( $control_id, $page_id );
	}

	/**
	 * Infer wizard goal type key from saved experiment config.
	 *
	 * @param array<string, mixed> $cfg Config.
	 * @return string traffic_only|page_view|cta_click|…
	 */
	public static function infer_goal_type_from_config( array $cfg ) {
		if ( ! empty( $cfg['assignment_only'] ) || ( isset( $cfg['winner_mode'] ) && 'traffic_only' === $cfg['winner_mode'] ) ) {
			return 'traffic_only';
		}
		$goals = isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array();
		foreach ( $goals as $g ) {
			if ( ! is_array( $g ) || empty( $g['is_primary'] ) ) {
				continue;
			}
			$gt = isset( $g['goal_type'] ) ? sanitize_key( (string) $g['goal_type'] ) : '';
			if ( 'click' === $gt ) {
				return 'cta_click';
			}
			if ( in_array( $gt, array( 'page_view', 'form_submit', 'add_to_cart', 'begin_checkout', 'purchase', 'custom_event' ), true ) ) {
				return $gt;
			}
			return 'cta_click';
		}
		return 'page_view';
	}

	/**
	 * Legacy string for configs without builder_detection.
	 *
	 * @param int $post_id Page ID.
	 * @return string
	 */
	private static function legacy_detect_builder_string( $post_id ) {
		$post_id = (int) $post_id;
		$el      = get_post_meta( $post_id, '_elementor_edit_mode', true );
		if ( 'builder' === $el ) {
			return 'elementor';
		}
		$content = get_post_field( 'post_content', $post_id );
		if ( is_string( $content ) && function_exists( 'has_blocks' ) && has_blocks( $content ) ) {
			return 'gutenberg';
		}
		return 'classic';
	}

	/**
	 * Build goal + handler structures from wizard goal type.
	 *
	 * @param string $goal_type Sanitized key.
	 * @return array{goals: array<int, array<string, mixed>>, primary_goal_id: string}
	 */
	private static function build_goals_from_goal_type( $goal_type ) {
		$goal_id    = RWGO_Experiment_Service::generate_uid( 'goal_' );
		$handler_id = RWGO_Experiment_Service::generate_uid( 'hdl_' );

		$mk = static function ( $type, $label, $handlers ) use ( $goal_id ) {
			return array(
				'goal_id'    => $goal_id,
				'goal_type'  => $type,
				'label'      => $label,
				'is_primary' => true,
				'handlers'   => $handlers,
			);
		};

		switch ( $goal_type ) {
			case 'page_view':
				return array(
					'goals'           => array(
						$mk(
							'page_view',
							__( 'Page view', 'reactwoo-geo-optimise' ),
							array(
								array(
									'handler_id'   => $handler_id,
									'handler_type' => 'page_view',
									'label'        => __( 'Default', 'reactwoo-geo-optimise' ),
									'dedupe'       => 'once_per_page_view',
								),
							)
						),
					),
					'primary_goal_id' => $goal_id,
				);

			case 'form_submit':
				return array(
					'goals'           => array(
						$mk(
							'form_submit',
							__( 'Form submission', 'reactwoo-geo-optimise' ),
							array(
								array(
									'handler_id'   => $handler_id,
									'handler_type' => 'form_submit',
									'label'        => __( 'Primary form', 'reactwoo-geo-optimise' ),
									'selector'     => '',
									'dedupe'       => 'once_per_page_view',
									'event_name'   => 'rwgo_goal_fired',
								),
							)
						),
					),
					'primary_goal_id' => $goal_id,
				);

			case 'add_to_cart':
				return array(
					'goals'           => array(
						$mk(
							'add_to_cart',
							__( 'Add to cart', 'reactwoo-geo-optimise' ),
							array(
								array(
									'handler_id'   => $handler_id,
									'handler_type' => 'woocommerce',
									'label'        => __( 'WooCommerce cart', 'reactwoo-geo-optimise' ),
									'dedupe'       => 'allow_multiple',
									'hook_strategy'=> 'add_to_cart',
								),
							)
						),
					),
					'primary_goal_id' => $goal_id,
				);

			case 'begin_checkout':
				return array(
					'goals'           => array(
						$mk(
							'begin_checkout',
							__( 'Begin checkout', 'reactwoo-geo-optimise' ),
							array(
								array(
									'handler_id'   => $handler_id,
									'handler_type' => 'woocommerce',
									'label'        => __( 'Checkout', 'reactwoo-geo-optimise' ),
									'dedupe'       => 'once_per_assignment',
									'hook_strategy'=> 'begin_checkout',
								),
							)
						),
					),
					'primary_goal_id' => $goal_id,
				);

			case 'purchase':
				return array(
					'goals'           => array(
						$mk(
							'purchase',
							__( 'Purchase', 'reactwoo-geo-optimise' ),
							array(
								array(
									'handler_id'   => $handler_id,
									'handler_type' => 'woocommerce',
									'label'        => __( 'Order complete', 'reactwoo-geo-optimise' ),
									'dedupe'       => 'once_per_order',
									'hook_strategy'=> 'purchase',
								),
							)
						),
					),
					'primary_goal_id' => $goal_id,
				);

			case 'custom_event':
				return array(
					'goals'           => array(
						$mk(
							'custom_event',
							__( 'Custom event', 'reactwoo-geo-optimise' ),
							array(
								array(
									'handler_id'   => $handler_id,
									'handler_type' => 'custom_event',
									'label'        => __( 'Custom', 'reactwoo-geo-optimise' ),
									'dedupe'       => 'once_per_session',
									'event_name'   => 'rwgo_goal_fired',
								),
							)
						),
					),
					'primary_goal_id' => $goal_id,
				);

			case 'button_click':
			case 'cta_click':
			default:
				return array(
					'goals'           => array(
						$mk(
							'click',
							__( 'CTA click', 'reactwoo-geo-optimise' ),
							array(
								array(
									'handler_id'   => $handler_id,
									'handler_type' => 'click',
									'label'        => __( 'Primary CTA', 'reactwoo-geo-optimise' ),
									'selector'     => '',
									'dedupe'       => 'allow_multiple',
									'event_name'   => 'rwgo_goal_fired',
								),
							)
						),
					),
					'primary_goal_id' => $goal_id,
				);
		}
	}
}
