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

	/**
	 * Interactive / CTA-capable Elementor widget names (core + common Pro).
	 * Plugins can add via filter `rwgo_elementor_goal_widgets`.
	 *
	 * @return list<string>
	 */
	public static function get_supported_widgets() {
		$widgets = array(
			'button',
			'heading',
			'text-editor',
			'html',
			'shortcode',
			'call-to-action',
			'icon-box',
			'image-box',
			'icon-list',
			'image-carousel',
			'image-gallery',
			'basic-gallery',
			'video',
			'divider',
			'spacer',
			'google_maps',
			'icon',
			'accordion',
			'toggle',
			'tabs',
			'alert',
			'counter',
			'progress',
			'testimonial',
			'rating',
			'social-icons',
			'blockquote',
		);
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$widgets = array_merge(
				$widgets,
				array(
					'form',
					'slides',
					'nav-menu',
					'search-form',
					'flip-box',
					'price-table',
					'testimonial',
					'animated-headline',
					'dual-button',
					'paypal-button',
					'stripe-button',
					'woocommerce-product-add-to-cart',
					'woocommerce-product-price',
					'woocommerce-menu-cart',
					'woocommerce-checkout',
					'woocommerce-cart',
					'woocommerce-my-account',
				)
			);
		}
		/**
		 * Widget names that receive the Geo Optimise Advanced section.
		 *
		 * @param list<string> $widgets Widget names.
		 */
		$widgets = apply_filters( 'rwgo_elementor_goal_widgets', $widgets );
		$widgets = array_values( array_unique( array_map( 'sanitize_key', $widgets ) ) );
		return $widgets;
	}

	/**
	 * Goal type options for Elementor SELECT control, keyed by value => label.
	 *
	 * @param string $widget_name Widget name.
	 * @return array<string, string>
	 */
	public static function get_goal_type_options_for_widget( $widget_name ) {
		$widget_name = sanitize_key( (string) $widget_name );
		$formish     = array( 'form' );
		$commerish   = array(
			'woocommerce-product-add-to-cart',
			'woocommerce-menu-cart',
			'woocommerce-checkout',
			'woocommerce-cart',
			'paypal-button',
			'stripe-button',
			'dual-button',
		);
		if ( in_array( $widget_name, $formish, true ) ) {
			$opts = array(
				'form_submit' => __( 'Form submit', 'reactwoo-geo-optimise' ),
				'custom'      => __( 'Custom', 'reactwoo-geo-optimise' ),
			);
		} elseif ( in_array( $widget_name, $commerish, true ) ) {
			$opts = array(
				'add_to_cart'    => __( 'Add to cart', 'reactwoo-geo-optimise' ),
				'begin_checkout' => __( 'Begin checkout', 'reactwoo-geo-optimise' ),
				'purchase'       => __( 'Purchase', 'reactwoo-geo-optimise' ),
				'cta_click'      => __( 'CTA click', 'reactwoo-geo-optimise' ),
				'custom'         => __( 'Custom', 'reactwoo-geo-optimise' ),
			);
		} else {
			$opts = array(
				'cta_click'          => __( 'CTA click', 'reactwoo-geo-optimise' ),
				'navigation_click'   => __( 'Navigation click', 'reactwoo-geo-optimise' ),
				'form_submit'        => __( 'Form submit', 'reactwoo-geo-optimise' ),
				'checkbox_optin'     => __( 'Checkbox / opt-in interaction', 'reactwoo-geo-optimise' ),
				'add_to_cart'        => __( 'Add to cart', 'reactwoo-geo-optimise' ),
				'begin_checkout'     => __( 'Begin checkout', 'reactwoo-geo-optimise' ),
				'purchase'           => __( 'Purchase', 'reactwoo-geo-optimise' ),
				'page_visit'         => __( 'Page visit', 'reactwoo-geo-optimise' ),
				'thank_you'          => __( 'Thank-you / confirmation visit', 'reactwoo-geo-optimise' ),
				'custom'             => __( 'Custom', 'reactwoo-geo-optimise' ),
			);
		}
		/**
		 * @param array<string, string> $opts        Value => label.
		 * @param string                 $widget_name Widget name.
		 */
		return apply_filters( 'rwgo_elementor_goal_type_options', $opts, $widget_name );
	}

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
		/*
		 * Widget Advanced-tab controls live on the merged Widget_Common / Widget_Common_Optimized stack.
		 * Modern Elementor uses _section_style for the first Advanced section (Layout), not section_advanced.
		 * Same pattern as GeoElementor: hook after Layout so Geo (10) and City (20) run first.
		 */
		add_action( 'elementor/element/common/_section_style/after_section_end', array( __CLASS__, 'register_goal_section_on_common' ), 30, 2 );
		add_action( 'elementor/element/common-optimized/_section_style/after_section_end', array( __CLASS__, 'register_goal_section_on_common' ), 30, 2 );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_render_widget' ), 10, 1 );
	}

	/**
	 * Register goal controls on the common widget stack (merged into all core widgets).
	 *
	 * @param \Elementor\Controls_Stack $element Common or common-optimized widget instance.
	 * @param array<string, mixed>      $args Section args (unused).
	 * @return void
	 */
	public static function register_goal_section_on_common( $element, $args = null ) {
		unset( $args );
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) ) {
			return;
		}
		if ( ! $element instanceof \Elementor\Widget_Common_Base ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return;
		}
		$controls = $element->get_controls();
		if ( isset( $controls['section_rwgo_geo_goal'] ) ) {
			return;
		}
		$el          = $element;
		$widget_name = $element->get_name();
		$type_opts   = self::get_goal_type_options_for_widget( $widget_name );
		$el->start_controls_section(
			'section_rwgo_geo_goal',
			array(
				'label' => __( 'Geo Optimise — goal', 'reactwoo-geo-optimise' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);
		$el->add_control(
			'rwgo_goal_section_help',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => '<p class="elementor-descriptor" style="margin-top:0;">' . esc_html__( 'Mark measurable CTAs and actions for A/B tests. Geo targeting and routing use GeoElementor separately.', 'reactwoo-geo-optimise' ) . '</p>',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
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
				'description'  => __( 'Turn this on if this widget should be available as a measurable goal in Geo Optimise tests.', 'reactwoo-geo-optimise' ),
			)
		);
		$el->add_control(
			'rwgo_goal_label',
			array(
				'label'       => __( 'Goal label', 'reactwoo-geo-optimise' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'e.g. Primary hero CTA', 'reactwoo-geo-optimise' ),
				'description' => __( 'Used in test setup and reports to identify this goal clearly.', 'reactwoo-geo-optimise' ),
				'condition'   => array( 'rwgo_goal_enabled' => 'yes' ),
			)
		);
		$el->add_control(
			'rwgo_goal_type',
			array(
				'label'     => __( 'Goal type', 'reactwoo-geo-optimise' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'form' === $widget_name ? 'form_submit' : 'cta_click',
				'condition' => array( 'rwgo_goal_enabled' => 'yes' ),
				'options'   => $type_opts,
			)
		);
		$el->add_control(
			'rwgo_goal_note',
			array(
				'label'       => __( 'Goal note', 'reactwoo-geo-optimise' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'description' => __( 'Optional internal note for agencies or editors.', 'reactwoo-geo-optimise' ),
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
		if ( ! in_array( $widget->get_name(), self::get_supported_widgets(), true ) ) {
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
		$ids   = RWGO_Defined_Goal_Service::elementor_element_ids( $pid, $eid );
		$label = isset( $settings['rwgo_goal_label'] ) ? sanitize_text_field( (string) $settings['rwgo_goal_label'] ) : '';
		$type  = isset( $settings['rwgo_goal_type'] ) ? sanitize_key( (string) $settings['rwgo_goal_type'] ) : 'cta_click';
		if ( '' === $label ) {
			$label = __( 'Elementor CTA', 'reactwoo-geo-optimise' );
		}
		$widget->add_render_attribute(
			'_wrapper',
			array(
				'data-rwgo-goal-id'             => $ids['goal_id'],
				'data-rwgo-goal-label'          => $label,
				'data-rwgo-goal-type'           => $type,
				'data-rwgo-handler-id'          => $ids['handler_id'],
				'data-rwgo-builder'             => 'elementor',
				'data-rwgo-element-fingerprint' => 'rwgo_defined',
			)
		);
	}
}
