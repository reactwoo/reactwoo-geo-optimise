<?php
/**
 * Front-end runtime bootstrap (page swapper, tracking enqueue).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runtime coordinator.
 */
class RWGO_Runtime {

	/**
	 * @var bool
	 */
	private static $html_trace_started = false;

	/**
	 * @return void
	 */
	public static function init() {
		RWGO_Page_Swapper::init();
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_tracking' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_record_variant_served' ), 99 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_start_goal_html_trace' ), 100 );
	}

	/**
	 * Count **served** impressions per variant (page actually rendered) for split validation.
	 * Does not change sticky assignment cookies.
	 *
	 * @return void
	 */
	public static function maybe_record_variant_served() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! class_exists( 'RWGO_Experiment_Repository', false ) || ! class_exists( 'RWGO_Stats', false ) || ! class_exists( 'RWGO_Experiment_Service', false ) ) {
			return;
		}
		$pid = self::resolve_frontend_context_post_id();
		if ( $pid <= 0 ) {
			return;
		}
		foreach ( RWGO_Experiment_Repository::get_active_touching_page( $pid ) as $row ) {
			if ( ! isset( $row['config'] ) || ! is_array( $row['config'] ) ) {
				continue;
			}
			$cfg = $row['config'];
			if ( empty( $cfg['experiment_key'] ) ) {
				continue;
			}
			$exp_post = isset( $row['post'] ) && $row['post'] instanceof \WP_Post ? $row['post'] : null;
			$variant  = RWGO_Experiment_Service::resolve_variant_for_context( $cfg, $pid, $exp_post ? (int) $exp_post->ID : 0 );
			if ( '' === $variant ) {
				continue;
			}
			RWGO_Stats::record_variant_served( (string) $cfg['experiment_key'], $variant );
		}
	}

	/**
	 * Post ID for the page being viewed (singular, WooCommerce shop, or filtered).
	 *
	 * @return int
	 */
	public static function resolve_frontend_context_post_id() {
		if ( is_singular() ) {
			$pid = (int) get_queried_object_id();
			if ( $pid > 0 ) {
				return $pid;
			}
			global $post;
			if ( $post instanceof \WP_Post && $post->ID > 0 ) {
				return (int) $post->ID;
			}
		}
		if ( function_exists( 'is_front_page' ) && is_front_page() && get_option( 'show_on_front' ) === 'page' ) {
			$front = (int) get_option( 'page_on_front' );
			if ( $front > 0 ) {
				return $front;
			}
		}
		if ( function_exists( 'is_home' ) && is_home() && ! is_front_page() && get_option( 'show_on_front' ) === 'page' ) {
			$blog = (int) get_option( 'page_for_posts' );
			if ( $blog > 0 ) {
				return $blog;
			}
		}
		if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) ) {
			$sid = (int) wc_get_page_id( 'shop' );
			if ( $sid > 0 ) {
				return $sid;
			}
		}
		/**
		 * @param int $post_id Default 0.
		 */
		return (int) apply_filters( 'rwgo_tracking_context_post_id', 0 );
	}

	/**
	 * Localize experiment + goal config for rwgo-tracking.js on singular pages.
	 *
	 * @return void
	 */
	public static function enqueue_tracking() {
		if ( is_admin() ) {
			return;
		}
		$pid = self::resolve_frontend_context_post_id();
		if ( $pid <= 0 ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[RWGO] enqueue_tracking skipped: no context post_id (not singular/home/shop or filter returned 0).' );
			}
			return;
		}
		$config = RWGO_Goal_Registry::build_frontend_config( $pid );
		if ( empty( $config['experiments'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[RWGO] enqueue_tracking skipped: post_id=' . (int) $pid . ' has no active experiment config for this URL (check test source/variant pages).' );
			}
			return;
		}

		$config['restUrl']      = rest_url( 'rwgo/v1/goal' );
		$config['restNonceUrl'] = rest_url( 'rwgo/v1/tracking-nonce' );
		$config['nonce']        = wp_create_nonce( RWGO_REST_Tracking::NONCE_ACTION );
		/**
		 * Persist browser goal events via REST into wp_rwgo_events (in addition to dataLayer).
		 *
		 * @param bool $persist Default true.
		 */
		$config['persistClientGoals'] = (bool) apply_filters( 'rwgo_persist_client_goals', true );
		/**
		 * Surface REST validation errors in the browser console (rwgo-tracking.js).
		 *
		 * @param bool $debug Default: RWGO_TRACKING_DEBUG or false.
		 */
		$config['trackClientDebug'] = (bool) apply_filters(
			'rwgo_tracking_client_debug',
			( defined( 'RWGO_TRACKING_DEBUG' ) && RWGO_TRACKING_DEBUG )
				|| ( defined( 'WP_DEBUG' ) && WP_DEBUG )
		);

		$tracking_src = plugins_url( 'assets/js/rwgo-tracking.js', RWGO_FILE );
		$tracking_path = RWGO_PATH . 'assets/js/rwgo-tracking.js';
		if ( ! is_readable( $tracking_path ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[RWGO] rwgo-tracking.js missing at ' . $tracking_path );
			}
			return;
		}

		wp_register_script(
			'rwgo-tracking',
			$tracking_src,
			array(),
			RWGO_VERSION,
			true
		);
		wp_enqueue_script( 'rwgo-tracking' );
		wp_localize_script(
			'rwgo-tracking',
			'rwgoTracking',
			$config
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$n = isset( $config['experiments'] ) && is_array( $config['experiments'] ) ? count( $config['experiments'] ) : 0;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional when WP_DEBUG.
			error_log( '[RWGO] enqueue_tracking: front page load post_id=' . (int) $pid . ' experiments_localized=' . (int) $n );
		}
	}

	/**
	 * Start a debug-only output buffer that inspects final HTML for stamped RWGO goal nodes.
	 *
	 * @return void
	 */
	public static function maybe_start_goal_html_trace() {
		if ( self::$html_trace_started || ! self::should_trace_goal_html() ) {
			return;
		}
		self::$html_trace_started = true;
		ob_start( array( __CLASS__, 'trace_goal_markup_html' ) );
	}

	/**
	 * @return bool
	 */
	private static function should_trace_goal_html() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return false;
		}
		$on = ( defined( 'RWGO_TRACKING_DEBUG' ) && RWGO_TRACKING_DEBUG )
			|| ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		/**
		 * Enable final HTML tracing for stamped `data-rwgo-*` nodes.
		 *
		 * @param bool $on Default follows tracking debug constants.
		 */
		return (bool) apply_filters( 'rwgo_trace_goal_html', $on );
	}

	/**
	 * Output-buffer callback: inspect final HTML and log stamped RWGO goal nodes with context hints.
	 *
	 * @param string $html Final HTML.
	 * @return string
	 */
	public static function trace_goal_markup_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		if ( false === strpos( $html, 'data-rwgo-goal-id' ) ) {
			return $html;
		}
		$pattern = '/<([a-z0-9:-]+)\b[^>]*data-rwgo-goal-id=(["\'])([^"\']+)\2[^>]*data-rwgo-handler-id=(["\'])([^"\']+)\4[^>]*>/i';
		if ( ! preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}
		$rows = array();
		foreach ( $matches as $idx => $m ) {
			if ( $idx >= 12 ) {
				break;
			}
			$full_tag = isset( $m[0][0] ) ? (string) $m[0][0] : '';
			$offset   = isset( $m[0][1] ) ? (int) $m[0][1] : 0;
			$before   = substr( $html, max( 0, $offset - 220 ), min( 220, $offset ) );
			$after    = substr( $html, $offset, 420 );
			$window   = $before . $after;
			$rows[]   = array(
				'tag'            => isset( $m[1][0] ) ? strtolower( (string) $m[1][0] ) : '',
				'goal_id'        => isset( $m[3][0] ) ? sanitize_key( (string) $m[3][0] ) : '',
				'handler_id'     => isset( $m[5][0] ) ? sanitize_key( (string) $m[5][0] ) : '',
				'experiment_key' => self::extract_attr_from_html_tag( $full_tag, 'data-rwgo-experiment-key' ),
				'classes'        => self::extract_attr_from_html_tag( $full_tag, 'class' ),
				'elementor_id'   => self::extract_attr_from_html_tag( $full_tag, 'data-id' ),
				'context_hint'   => self::goal_trace_context_hint( $window, $full_tag ),
			);
		}
		$pid = self::resolve_frontend_context_post_id();
		error_log( '[RWGO HTML trace] page=' . (int) $pid . ' ' . wp_json_encode( $rows ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return $html;
	}

	/**
	 * @param string $tag_html Full opening tag.
	 * @param string $attr_name Attribute name.
	 * @return string
	 */
	private static function extract_attr_from_html_tag( $tag_html, $attr_name ) {
		$tag_html  = (string) $tag_html;
		$attr_name = preg_quote( (string) $attr_name, '/' );
		if ( preg_match( '/\b' . $attr_name . '=(["\'])(.*?)\1/i', $tag_html, $m ) ) {
			return sanitize_text_field( (string) $m[2] );
		}
		return '';
	}

	/**
	 * @param string $window   Nearby HTML slice around the stamped node.
	 * @param string $tag_html Full opening tag.
	 * @return string
	 */
	private static function goal_trace_context_hint( $window, $tag_html ) {
		$haystack = strtolower( $window . ' ' . $tag_html );
		if ( false !== strpos( $haystack, 'elementor-popup-modal' ) || false !== strpos( $haystack, 'dialog-widget' ) || false !== strpos( $haystack, 'mfp-hide' ) ) {
			return 'popup';
		}
		if ( false !== strpos( $haystack, 'elementor-location-header' ) ) {
			return 'theme_header';
		}
		if ( false !== strpos( $haystack, 'elementor-location-footer' ) ) {
			return 'theme_footer';
		}
		if ( false !== strpos( $haystack, 'elementor-widget-template' ) || false !== strpos( $haystack, 'template_id' ) || false !== strpos( $haystack, 'saved_template' ) ) {
			return 'embedded_template';
		}
		if ( false !== strpos( $haystack, 'elementor-widget') ) {
			return 'page_widget';
		}
		return 'unknown';
	}
}
