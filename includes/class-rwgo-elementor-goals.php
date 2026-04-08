<?php
/**
 * Elementor: Geo Optimise goal controls (Advanced tab) + data attributes on the front end.
 *
 * Controls register on Elementor’s shared “common” / “common-optimized” stacks (merged into each
 * widget), using `elementor/element/common/_section_style/after_section_end` — the same hook
 * GeoElementor uses for geo controls. Registration is tied to `elementor/init` (priority 1) so hooks
 * exist before widget control stacks initialize (avoids late `plugins_loaded` ordering issues).
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

	const SECTION_ID = 'section_rwgo_geo_goal';

	/**
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * Interactive / CTA-capable Elementor widget names (core + common Pro).
	 * Kept intentionally narrow; extend via `rwgo_elementor_goal_widgets`.
	 *
	 * @return list<string>
	 */
	public static function get_supported_widgets() {
		$widgets = array(
			'button',
			'call-to-action',
			'icon-box',
			'image-box',
			'icon-list',
			'video',
			'accordion',
			'toggle',
			'tabs',
			'alert',
			'social-icons',
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
					'animated-headline',
					'dual-button',
					'paypal-button',
					'stripe-button',
					'woocommerce-product-add-to-cart',
					'woocommerce-menu-cart',
					'woocommerce-checkout',
					'woocommerce-cart',
					'woocommerce-my-account',
				)
			);
		}
		/**
		 * Widget names that receive Geo Optimise goal controls (merged from common).
		 *
		 * @param list<string> $widgets Widget names.
		 */
		$widgets = apply_filters( 'rwgo_elementor_goal_widgets', $widgets );
		$widgets = array_values( array_unique( array_map( 'sanitize_key', $widgets ) ) );
		return $widgets;
	}

	/**
	 * SELECT options for the shared merged common control (one list for all widgets).
	 * Narrow at runtime via `rwgo_elementor_goal_type_options` if needed.
	 *
	 * @return array<string, string>
	 */
	public static function get_goal_type_options_merged() {
		$opts = array(
			'cta_click'          => __( 'CTA click', 'reactwoo-geo-optimise' ),
			'navigation_click'   => __( 'Navigation click', 'reactwoo-geo-optimise' ),
			'form_submit'        => __( 'Form submit', 'reactwoo-geo-optimise' ),
			'checkbox_optin'     => __( 'Checkbox / opt-in interaction', 'reactwoo-geo-optimise' ),
			'add_to_cart'        => __( 'Add to cart', 'reactwoo-geo-optimise' ),
			'begin_checkout'     => __( 'Begin checkout', 'reactwoo-geo-optimise' ),
			'purchase'           => __( 'Purchase', 'reactwoo-geo-optimise' ),
			'custom'             => __( 'Custom', 'reactwoo-geo-optimise' ),
		);
		/**
		 * @param array<string, string> $opts Value => label (merged common control).
		 */
		return apply_filters( 'rwgo_elementor_goal_type_options_merged', $opts );
	}

	/**
	 * Goal type options when a concrete widget slug is known (reports, tooling, filters).
	 * Elementor’s merged common control uses `get_goal_type_options_merged()` because the
	 * registering element name is `common`, not the real widget.
	 *
	 * @param string $widget_name Widget name (e.g. button, form).
	 * @return array<string, string>
	 */
	public static function get_goal_type_options_for_widget( $widget_name ) {
		$widget_name = sanitize_key( (string) $widget_name );

		$formish = array( 'form' );
		$navish  = array( 'nav-menu', 'search-form' );
		$commerish = array(
			'woocommerce-product-add-to-cart',
			'woocommerce-menu-cart',
			'woocommerce-checkout',
			'woocommerce-cart',
			'woocommerce-my-account',
			'paypal-button',
			'stripe-button',
		);

		if ( in_array( $widget_name, $formish, true ) ) {
			$opts = array(
				'form_submit' => __( 'Form submit', 'reactwoo-geo-optimise' ),
				'custom'      => __( 'Custom', 'reactwoo-geo-optimise' ),
			);
		} elseif ( in_array( $widget_name, $navish, true ) ) {
			$opts = array(
				'navigation_click' => __( 'Navigation click', 'reactwoo-geo-optimise' ),
				'cta_click'        => __( 'CTA click', 'reactwoo-geo-optimise' ),
				'custom'           => __( 'Custom', 'reactwoo-geo-optimise' ),
			);
		} elseif ( in_array( $widget_name, $commerish, true ) ) {
			$opts = array(
				'add_to_cart'    => __( 'Add to cart', 'reactwoo-geo-optimise' ),
				'begin_checkout' => __( 'Begin checkout', 'reactwoo-geo-optimise' ),
				'purchase'       => __( 'Purchase', 'reactwoo-geo-optimise' ),
				'cta_click'      => __( 'CTA click', 'reactwoo-geo-optimise' ),
				'navigation_click' => __( 'Navigation click', 'reactwoo-geo-optimise' ),
				'checkbox_optin' => __( 'Checkbox / opt-in interaction', 'reactwoo-geo-optimise' ),
				'custom'         => __( 'Custom', 'reactwoo-geo-optimise' ),
			);
		} else {
			$opts = array(
				'cta_click'        => __( 'CTA click', 'reactwoo-geo-optimise' ),
				'navigation_click' => __( 'Navigation click', 'reactwoo-geo-optimise' ),
				'add_to_cart'      => __( 'Add to cart', 'reactwoo-geo-optimise' ),
				'begin_checkout'   => __( 'Begin checkout', 'reactwoo-geo-optimise' ),
				'purchase'         => __( 'Purchase', 'reactwoo-geo-optimise' ),
				'custom'           => __( 'Custom', 'reactwoo-geo-optimise' ),
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
		if ( did_action( 'elementor/init' ) ) {
			self::register_hooks();
		} else {
			add_action( 'elementor/init', array( __CLASS__, 'register_hooks' ), 1 );
		}
	}

	/**
	 * @return void
	 */
	public static function register_hooks() {
		if ( self::$hooks_registered ) {
			return;
		}
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return;
		}
		self::$hooks_registered = true;

		self::debug_log(
			'register_hooks',
			array(
				'did_elementor_init' => did_action( 'elementor/init' ),
			)
		);

		add_action( 'elementor/element/common/_section_style/after_section_end', array( __CLASS__, 'register_goal_section_on_common_stack' ), 30, 2 );
		add_action( 'elementor/element/common-optimized/_section_style/after_section_end', array( __CLASS__, 'register_goal_section_on_common_stack' ), 30, 2 );
		add_action( 'elementor/frontend/widget/before_render', array( __CLASS__, 'before_render_widget' ), 10, 1 );
	}

	/**
	 * @return bool
	 */
	private static function is_debug_logging_on() {
		if ( defined( 'RWGO_ELEMENTOR_GOALS_DEBUG' ) && RWGO_ELEMENTOR_GOALS_DEBUG ) {
			return true;
		}
		if ( defined( 'RWGO_TRACKING_DEBUG' ) && RWGO_TRACKING_DEBUG ) {
			return true;
		}
		/**
		 * Enable Elementor goal registration debug logs (`error_log`).
		 *
		 * @param bool $on Whether logging is on.
		 */
		return (bool) apply_filters( 'rwgo_elementor_goals_debug_log', false );
	}

	/**
	 * @param string               $message Context.
	 * @param array<string, mixed> $extra   Extra structured data.
	 * @return void
	 */
	private static function debug_log( $message, array $extra = array() ) {
		if ( ! self::is_debug_logging_on() ) {
			return;
		}
		$line = '[RWGO Elementor Goals] ' . $message;
		if ( ! empty( $extra ) ) {
			$line .= ' ' . wp_json_encode( $extra );
		}
		error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * @param \Elementor\Controls_Stack $element Common or common-optimized prototype.
	 * @param array<string, mixed>      $args    Section args.
	 * @return void
	 */
	public static function register_goal_section_on_common_stack( $element, $args = null ) {
		unset( $args );
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_name' ) ) {
			self::debug_log( 'common_stack_invalid_element', array() );
			return;
		}
		$name = $element->get_name();
		if ( ! in_array( $name, array( 'common', 'common-optimized' ), true ) ) {
			self::debug_log( 'common_stack_skip_name', array( 'name' => $name ) );
			return;
		}
		if ( ! $element instanceof \Elementor\Widget_Common_Base ) {
			self::debug_log( 'common_stack_skip_not_common_base', array( 'class' => get_class( $element ) ) );
			return;
		}
		if ( ! class_exists( '\Elementor\Plugin', false ) ) {
			return;
		}

		self::debug_log( 'common_stack_hook_fired', array( 'stack' => $name ) );

		$controls = $element->get_controls();
		if ( isset( $controls[ self::SECTION_ID ] ) ) {
			self::debug_log(
				'skip_duplicate_section',
				array(
					'stack'   => $name,
					'has_sec' => true,
				)
			);
			return;
		}

		$type_opts = self::get_goal_type_options_merged();

		$element->start_controls_section(
			self::SECTION_ID,
			array(
				'label' => __( 'Geo Optimise', 'reactwoo-geo-optimise' ),
				'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
			)
		);
		$element->add_control(
			'rwgo_goal_section_help',
			array(
				'type'            => \Elementor\Controls_Manager::RAW_HTML,
				'raw'             => '<p class="elementor-descriptor" style="margin-top:0;">' . esc_html__( 'Mark measurable CTAs and actions for A/B tests. Geo targeting uses GeoElementor separately.', 'reactwoo-geo-optimise' ) . '</p>',
				'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
			)
		);
		$element->add_control(
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
		$element->add_control(
			'rwgo_goal_label',
			array(
				'label'       => __( 'Goal label', 'reactwoo-geo-optimise' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'placeholder' => __( 'e.g. Primary hero CTA', 'reactwoo-geo-optimise' ),
				'description' => __( 'Used in test setup and reports to identify this goal clearly.', 'reactwoo-geo-optimise' ),
				'condition'   => array( 'rwgo_goal_enabled' => 'yes' ),
			)
		);
		$element->add_control(
			'rwgo_goal_type',
			array(
				'label'       => __( 'Goal type', 'reactwoo-geo-optimise' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => 'cta_click',
				'condition'   => array( 'rwgo_goal_enabled' => 'yes' ),
				'options'     => $type_opts,
				'description' => __( 'Choose the interaction that best matches what you are measuring.', 'reactwoo-geo-optimise' ),
			)
		);
		$element->add_control(
			'rwgo_goal_note',
			array(
				'label'       => __( 'Goal note', 'reactwoo-geo-optimise' ),
				'type'        => \Elementor\Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'description' => __( 'Optional note for editors or agencies.', 'reactwoo-geo-optimise' ),
				'condition'   => array( 'rwgo_goal_enabled' => 'yes' ),
			)
		);
		$element->end_controls_section();

		self::debug_log(
			'section_registered',
			array(
				'stack' => $name,
			)
		);
	}

	/**
	 * @param \Elementor\Widget_Base $widget Widget.
	 * @return void
	 */
	public static function before_render_widget( $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
			return;
		}
		$wname = $widget->get_name();
		if ( ! in_array( $wname, self::get_supported_widgets(), true ) ) {
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
				'data-rwgo-goal-id'               => $ids['goal_id'],
				'data-rwgo-goal-label'            => $label,
				'data-rwgo-goal-type'             => $type,
				'data-rwgo-handler-id'            => $ids['handler_id'],
				'data-rwgo-builder'               => 'elementor',
				'data-rwgo-element-fingerprint' => 'rwgo_defined',
			)
		);
		self::debug_log(
			'render_goal_stamp',
			array(
				'post_id'    => (int) $pid,
				'widget'     => (string) $wname,
				'element_id' => (string) $eid,
				'goal_id'    => (string) $ids['goal_id'],
				'handler_id' => (string) $ids['handler_id'],
				'label'      => (string) $label,
				'ui_type'    => (string) $type,
			)
		);
	}
}
