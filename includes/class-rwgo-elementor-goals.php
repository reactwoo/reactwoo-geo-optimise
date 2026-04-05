<?php
/**
 * Elementor: Geo Optimise goal controls (Advanced tab) + data attributes on the front end.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers controls on supported widgets and prints data-rwgo-* on render.
 */
class RWGO_Elementor_Goals {

	const WIDGETS = array( 'button', 'call-to-action', 'icon-box' );

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
		add_action( 'elementor/element/after_section_end', array( __CLASS__, 'register_section' ), 25, 3 );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_render_widget' ), 10, 1 );
	}

	/**
	 * @param \Elementor\Controls_Stack $element Element.
	 * @param string                    $section_id Section ID.
	 * @param array<string, mixed>      $args Args.
	 * @return void
	 */
	public static function register_section( $element, $section_id, $args ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) ) {
			return;
		}
		if ( 'section_advanced' !== $section_id ) {
			return;
		}
		if ( ! in_array( $element->get_name(), self::WIDGETS, true ) ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return;
		}
		$el = $element;
		$el->start_controls_section(
			'section_rwgo_geo_goal',
			array(
				'label' => __( 'Geo Optimise', 'reactwoo-geo-optimise' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);
		$el->add_control(
			'rwgo_goal_enabled',
			array(
				'label'        => __( 'Use as Geo Optimise goal', 'reactwoo-geo-optimise' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'reactwoo-geo-optimise' ),
				'label_off'    => __( 'No', 'reactwoo-geo-optimise' ),
				'return_value' => 'yes',
				'default'      => '',
			)
		);
		$el->add_control(
			'rwgo_goal_label',
			array(
				'label'       => __( 'Goal label', 'reactwoo-geo-optimise' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'e.g. Primary hero CTA', 'reactwoo-geo-optimise' ),
				'condition'   => array( 'rwgo_goal_enabled' => 'yes' ),
			)
		);
		$el->add_control(
			'rwgo_goal_type',
			array(
				'label'     => __( 'Goal type', 'reactwoo-geo-optimise' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'cta_click',
				'condition' => array( 'rwgo_goal_enabled' => 'yes' ),
				'options'   => array(
					'cta_click'         => __( 'CTA click', 'reactwoo-geo-optimise' ),
					'navigation_click'  => __( 'Navigation click', 'reactwoo-geo-optimise' ),
					'add_to_cart'       => __( 'Add to cart', 'reactwoo-geo-optimise' ),
					'custom'            => __( 'Custom', 'reactwoo-geo-optimise' ),
				),
			)
		);
		$el->add_control(
			'rwgo_goal_note',
			array(
				'label'       => __( 'Goal note', 'reactwoo-geo-optimise' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'description' => __( 'Optional internal note for your team.', 'reactwoo-geo-optimise' ),
				'condition'   => array( 'rwgo_goal_enabled' => 'yes' ),
			)
		);
		$el->end_controls_section();
	}

	/**
	 * @param \Elementor\Widget_Base $widget Widget.
	 * @return void
	 */
	public static function before_render_widget( $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
			return;
		}
		if ( ! in_array( $widget->get_name(), self::WIDGETS, true ) ) {
			return;
		}
		$settings = $widget->get_settings_for_display();
		if ( empty( $settings['rwgo_goal_enabled'] ) || 'yes' !== $settings['rwgo_goal_enabled'] ) {
			return;
		}
		$eid = method_exists( $widget, 'get_id' ) ? (string) $widget->get_id() : '';
		$pid = get_the_ID();
		if ( $pid <= 0 ) {
			return;
		}
		$ids = RWGO_Defined_Goal_Service::elementor_element_ids( $pid, $eid );
		$label = isset( $settings['rwgo_goal_label'] ) ? sanitize_text_field( (string) $settings['rwgo_goal_label'] ) : '';
		$type  = isset( $settings['rwgo_goal_type'] ) ? sanitize_key( (string) $settings['rwgo_goal_type'] ) : 'cta_click';
		if ( '' === $label ) {
			$label = __( 'Elementor CTA', 'reactwoo-geo-optimise' );
		}
		$widget->add_render_attribute(
			'_wrapper',
			array(
				'data-rwgo-goal-id'               => $ids['goal_id'],
				'data-rwgo-goal-label'            => $label,
				'data-rwgo-goal-type'             => $type,
				'data-rwgo-handler-id'            => $ids['handler_id'],
				'data-rwgo-builder'               => 'elementor',
				'data-rwgo-element-fingerprint'   => 'rwgo_defined',
			)
		);
	}
}
