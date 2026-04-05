<?php
/**
 * Elementor: page settings — Geo Optimise destination goal (syncs with post meta).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mirrors RWGO_Page_Goal_Meta in the Elementor document settings panel.
 */
class RWGO_Elementor_Page_Goal {

	/**
	 * @return void
	 */
	public static function init() {
		if ( ! did_action( 'elementor/loaded' ) ) {
			add_action( 'elementor/loaded', array( __CLASS__, 'register_hooks' ) );
		} else {
			self::register_hooks();
		}
	}

	/**
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'elementor/documents/register_controls', array( __CLASS__, 'register_controls' ), 25 );
		add_action( 'elementor/document/after_save', array( __CLASS__, 'after_document_save' ), 20, 2 );
	}

	/**
	 * @param \Elementor\Core\Base\Document $document Document.
	 * @return void
	 */
	public static function register_controls( $document ) {
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
			return;
		}
		$post_id = (int) $document->get_main_id();
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$pto = get_post_type_object( $post->post_type );
		if ( ! $pto || empty( $pto->public ) ) {
			return;
		}

		if ( ! class_exists( '\Elementor\Controls_Manager', false ) ) {
			return;
		}

		$en    = get_post_meta( $post_id, RWGO_Defined_Goal_Service::META_DEST_ENABLED, true );
		$label = (string) get_post_meta( $post_id, RWGO_Defined_Goal_Service::META_DEST_LABEL, true );
		$type  = (string) get_post_meta( $post_id, RWGO_Defined_Goal_Service::META_DEST_TYPE, true );
		if ( '' === $type ) {
			$type = 'page_visit';
		}
		$enabled = ( '1' === (string) $en || 'yes' === (string) $en );

		$document->start_controls_section(
			'rwgo_page_destination_goal_section',
			array(
				'label' => __( 'Geo Optimise — destination goal', 'reactwoo-geo-optimise' ),
				'tab'   => \Elementor\Controls_Manager::TAB_SETTINGS,
			)
		);

		$document->add_control(
			'rwgo_dest_goal_enabled',
			array(
				'label'        => __( 'Use this page as a Geo Optimise goal destination', 'reactwoo-geo-optimise' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'reactwoo-geo-optimise' ),
				'label_off'    => __( 'No', 'reactwoo-geo-optimise' ),
				'return_value' => 'yes',
				'default'      => $enabled ? 'yes' : '',
				'description'  => __( 'Turn this on if visiting this page should count as a conversion in Geo Optimise tests.', 'reactwoo-geo-optimise' ),
			)
		);

		$document->add_control(
			'rwgo_dest_goal_label',
			array(
				'label'       => __( 'Goal label', 'reactwoo-geo-optimise' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'e.g. Thank you page', 'reactwoo-geo-optimise' ),
				'default'     => $label,
				'condition'   => array( 'rwgo_dest_goal_enabled' => 'yes' ),
			)
		);

		$document->add_control(
			'rwgo_dest_goal_type',
			array(
				'label'     => __( 'Goal type', 'reactwoo-geo-optimise' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => $type,
				'condition' => array( 'rwgo_dest_goal_enabled' => 'yes' ),
				'options'   => array(
					'page_visit'           => __( 'Page visit', 'reactwoo-geo-optimise' ),
					'thank_you'            => __( 'Thank-you / confirmation page', 'reactwoo-geo-optimise' ),
					'lead_confirmation'  => __( 'Lead confirmation', 'reactwoo-geo-optimise' ),
					'checkout_success'     => __( 'Checkout success', 'reactwoo-geo-optimise' ),
					'custom_destination'   => __( 'Custom destination', 'reactwoo-geo-optimise' ),
				),
			)
		);

		$document->end_controls_section();
	}

	/**
	 * Persist Elementor document settings into the same post meta as the classic meta box.
	 *
	 * @param \Elementor\Core\Base\Document $document Document.
	 * @param array<string, mixed>          $data Editor data (unused; read saved settings from document).
	 * @return void
	 */
	public static function after_document_save( $document, $data ) {
		unset( $data );
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) || ! method_exists( $document, 'get_settings' ) ) {
			return;
		}
		$post_id = (int) $document->get_main_id();
		if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$settings = $document->get_settings();
		if ( ! is_array( $settings ) ) {
			return;
		}
		// Avoid overwriting meta on saves that never loaded Geo Optimise document controls.
		if ( ! array_key_exists( 'rwgo_dest_goal_enabled', $settings ) && ! array_key_exists( 'rwgo_dest_goal_label', $settings ) && ! array_key_exists( 'rwgo_dest_goal_type', $settings ) ) {
			return;
		}

		$en = ! empty( $settings['rwgo_dest_goal_enabled'] ) && 'yes' === (string) $settings['rwgo_dest_goal_enabled'];
		update_post_meta( $post_id, RWGO_Defined_Goal_Service::META_DEST_ENABLED, $en ? '1' : '0' );

		$label = isset( $settings['rwgo_dest_goal_label'] ) ? sanitize_text_field( (string) $settings['rwgo_dest_goal_label'] ) : '';
		update_post_meta( $post_id, RWGO_Defined_Goal_Service::META_DEST_LABEL, $label );

		$type = isset( $settings['rwgo_dest_goal_type'] ) ? sanitize_key( (string) $settings['rwgo_dest_goal_type'] ) : 'page_visit';
		$ok   = array( 'page_visit', 'thank_you', 'lead_confirmation', 'checkout_success', 'custom_destination' );
		if ( ! in_array( $type, $ok, true ) ) {
			$type = 'page_visit';
		}
		update_post_meta( $post_id, RWGO_Defined_Goal_Service::META_DEST_TYPE, $type );

		if ( $en ) {
			RWGO_Defined_Goal_Service::maybe_fill_destination_ids( $post_id );
		}
	}
}
