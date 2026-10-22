<?php
/**
 * Site-wide design split: one arm per visitor across mapped pages.
 *
 * Serving keeps the original URL. The variant page is rendered internally.
 * A direct hit on a variant URL is a 302 back to the original.
 *
 * Order on template_redirect:
 * 1. This class redirects variant URLs at priority -1.
 * 2. Geo Core Pro country routing runs at priority 0 and may exit.
 * 3. This class renders the arm at priority 1.
 * 4. Existing per-page tests still redirect at priority 5.
 *
 * A mapped response that assigns an arm, or serves one already stored in the
 * assignment cookie, is private and no-store with Vary: Cookie. Returning
 * visitors are not cacheable.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site-wide experiment runtime and assignment helpers.
 */
class RWGO_Sitewide {

	const COOKIE = 'rwgo_site';

	const OPTION = 'rwgo_sitewide_experiment';

	const POST_OPTION = 'rwgo_sitewide_experiment_post_id';

	const DEFAULT_DAYS = 90;

	/**
	 * @var bool
	 */
	private static $handled = false;

	/**
	 * @var string
	 */
	private static $rendered_arm = '';

	/**
	 * @var int
	 */
	private static $original_page_id = 0;

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_mark_uncacheable' ), 0 );
		add_action( 'litespeed_init', array( __CLASS__, 'litespeed_cache' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_variant_url' ), -1 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_apply' ), 1 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'keep_original_url' ), 10, 2 );
		add_filter( 'get_canonical_url', array( __CLASS__, 'filter_canonical' ), 10, 2 );
		add_filter( 'wpseo_canonical', array( __CLASS__, 'filter_canonical_url' ) );
		add_filter( 'rank_math/frontend/canonical', array( __CLASS__, 'filter_canonical_url' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'exclude_core_sitemap' ) );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( __CLASS__, 'exclude_yoast_sitemap' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 90 );
		add_action( 'admin_post_rwgo_save_sitewide', array( __CLASS__, 'handle_save' ) );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'on_purchase' ), 30, 1 );
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return bool
	 */
	public static function is_sitewide_config( $cfg ) {
		if ( ! is_array( $cfg ) ) {
			return false;
		}
		$type  = isset( $cfg['test_type'] ) ? sanitize_key( (string) $cfg['test_type'] ) : '';
		$scope = isset( $cfg['scope'] ) ? sanitize_key( (string) $cfg['scope'] ) : '';
		return 'sitewide' === $type || 'site' === $scope;
	}

	/**
	 * @return bool
	 */
	public static function request_handled() {
		return self::$handled;
	}

	/**
	 * @param string $visitor_id     Stored id.
	 * @param string $experiment_key Experiment key.
	 * @param float  $control_weight Weight for control, 0–1.
	 * @return string control|variant
	 */
	public static function bucket_arm( $visitor_id, $experiment_key, $control_weight ) {
		$weight = (float) $control_weight;
		if ( $weight >= 1 ) {
			return 'control';
		}
		if ( $weight <= 0 ) {
			return 'variant';
		}
		$hash  = hash( 'sha256', (string) $visitor_id . '|' . (string) $experiment_key );
		$n     = hexdec( substr( $hash, 0, 8 ) );
		$ratio = $n / 4294967295;
		return ( $ratio < $weight ) ? 'control' : 'variant';
	}

	/**
	 * @param string $arm control|variant.
	 * @return string control|var_b
	 */
	public static function report_slug( $arm ) {
		return 'variant' === $arm ? 'var_b' : 'control';
	}

	/**
	 * @param int  $days   Lifetime in days.
	 * @param bool $secure Secure flag.
	 * @return array{expires:int,path:string,secure:bool,httponly:bool,samesite:string}
	 */
	public static function cookie_options( $days, $secure ) {
		$days = (int) $days;
		if ( $days < 1 ) {
			$days = self::DEFAULT_DAYS;
		}
		return array(
			'expires'  => time() + ( $days * 86400 ),
			'path'     => '/',
			'secure'   => (bool) $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		);
	}

	/**
	 * @param bool   $can_manage User can manage_options.
	 * @param string $arm        Requested arm.
	 * @return string
	 */
	public static function qa_override_allowed( $can_manage, $arm ) {
		$arm = sanitize_key( (string) $arm );
		if ( ! $can_manage || ! in_array( $arm, array( 'control', 'variant' ), true ) ) {
			return '';
		}
		return $arm;
	}

	/**
	 * @param string $bucket_arm Assigned arm.
	 * @param bool   $is_editor  Logged-in editor.
	 * @param string $qa_arm     Allowed QA arm or empty.
	 * @return string
	 */
	public static function serving_arm( $bucket_arm, $is_editor, $qa_arm ) {
		if ( in_array( $qa_arm, array( 'control', 'variant' ), true ) ) {
			return $qa_arm;
		}
		if ( $is_editor ) {
			return 'control';
		}
		return in_array( $bucket_arm, array( 'control', 'variant' ), true ) ? $bucket_arm : 'control';
	}

	/**
	 * @param string $ua User agent.
	 * @return bool
	 */
	public static function is_known_bot( $ua ) {
		$ua = strtolower( (string) $ua );
		if ( '' === $ua ) {
			return false;
		}
		$needles = array(
			'googlebot',
			'bingbot',
			'slurp',
			'duckduckbot',
			'baiduspider',
			'yandex',
			'facebookexternalhit',
			'twitterbot',
			'linkedinbot',
			'whatsapp',
			'ahrefsbot',
			'semrushbot',
			'petalbot',
			'applebot',
			'bytespider',
			'gptbot',
			'claudebot',
			'amazonbot',
			'pingdom',
			'uptimerobot',
			'lighthouse',
			'chrome-lighthouse',
			'headlesschrome',
		);
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $ua, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, bool> $flags admin, ajax, cron, rest, cli, preview, json.
	 * @return bool
	 */
	public static function context_skips_assignment( array $flags ) {
		foreach ( array( 'admin', 'ajax', 'cron', 'rest', 'cli', 'preview', 'json' ) as $key ) {
			if ( ! empty( $flags[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Repair a cookie. A forged or mismatched arm is overwritten from the stored id.
	 *
	 * @param string $raw             Raw cookie JSON.
	 * @param string $experiment_key  Experiment key.
	 * @param float  $control_weight  Control weight.
	 * @param string $qa_arm          Allowed QA arm, or empty.
	 * @return array{id:string,arm:string,rewritten:bool,assigning:bool,qa:bool}
	 */
	public static function repair_cookie( $raw, $experiment_key, $control_weight, $qa_arm = '' ) {
		$experiment_key = sanitize_key( (string) $experiment_key );
		$parsed         = self::parse_cookie( $raw );
		$qa_arm         = in_array( $qa_arm, array( 'control', 'variant' ), true ) ? $qa_arm : '';
		if ( '' !== $qa_arm ) {
			$id = ( '' !== $parsed['id'] ) ? $parsed['id'] : self::new_visitor_id();
			return array(
				'id'         => $id,
				'arm'        => $qa_arm,
				'rewritten'  => ( $parsed['id'] !== $id || $parsed['arm'] !== $qa_arm || $parsed['exp'] !== $experiment_key || empty( $parsed['qa'] ) ),
				'assigning'  => '' === $parsed['id'],
				'qa'         => true,
			);
		}
		if ( '' === $parsed['id'] ) {
			$id = self::new_visitor_id();
			return array(
				'id'        => $id,
				'arm'       => self::bucket_arm( $id, $experiment_key, $control_weight ),
				'rewritten' => true,
				'assigning' => true,
				'qa'        => false,
			);
		}
		$arm     = self::bucket_arm( $parsed['id'], $experiment_key, $control_weight );
		$rewrite = ( $parsed['arm'] !== $arm || $parsed['exp'] !== $experiment_key || ! empty( $parsed['qa'] ) );
		return array(
			'id'        => $parsed['id'],
			'arm'       => $arm,
			'rewritten' => $rewrite,
			'assigning' => false,
			'qa'        => false,
		);
	}

	/**
	 * @param string $path Request path.
	 * @param array<int, array<string, mixed>> $map Page map.
	 * @return string source|variant|none
	 */
	public static function path_role( $path, array $map ) {
		$path = self::normalize_request_path( $path );
		foreach ( $map as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( self::paths_match( $path, isset( $row['variant_path'] ) ? (string) $row['variant_path'] : '' ) ) {
				return 'variant';
			}
		}
		foreach ( $map as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( self::paths_match( $path, isset( $row['source_path'] ) ? (string) $row['source_path'] : '' ) ) {
				return 'source';
			}
		}
		return 'none';
	}

	/**
	 * @param string $path Path.
	 * @return string
	 */
	public static function normalize_request_path( $path ) {
		$path = (string) $path;
		$q    = strpos( $path, '?' );
		if ( false !== $q ) {
			$path = substr( $path, 0, $q );
		}
		$path = rawurldecode( $path );
		$path = (string) preg_replace( '#/index\.php#i', '', $path );
		$path = '/' . trim( $path, '/' );
		return '/' === $path ? '/' : $path;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_config() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$cfg = apply_filters( 'rwgo_sitewide_config', $stored );
		return is_array( $cfg ) ? $cfg : array();
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public static function active_config() {
		$cfg = self::get_config();
		if ( ! self::is_sitewide_config( $cfg ) ) {
			return null;
		}
		if ( empty( $cfg['status'] ) || 'active' !== sanitize_key( (string) $cfg['status'] ) ) {
			return null;
		}
		if ( empty( $cfg['page_map'] ) || ! is_array( $cfg['page_map'] ) ) {
			return null;
		}
		return $cfg;
	}

	/**
	 * Mark mapped URLs uncacheable before a full-page cache can store an arm.
	 *
	 * A returning visitor with a valid assignment cookie still receives one arm.
	 * That response needs the same bypass as a fresh assignment.
	 *
	 * @return void
	 */
	public static function maybe_mark_uncacheable() {
		if ( self::request_is_skipped() || self::is_bot_request() ) {
			return;
		}
		$cfg = self::active_config();
		if ( null === $cfg ) {
			return;
		}
		$role = self::role_for_current_uri( $cfg );
		if ( 'none' === $role ) {
			return;
		}
		// Source pages always assign or serve. A matching cookie is the serve case.
		$serves_or_assigns = ( 'source' === $role ) || self::incoming_cookie_matches( $cfg );
		if ( self::mapped_response_needs_cache_bypass( $role, $serves_or_assigns ) ) {
			self::send_uncacheable();
		}
	}

	/**
	 * LiteSpeed vary for a request that already has a valid arm.
	 *
	 * @return void
	 */
	public static function litespeed_cache() {
		if ( self::request_is_skipped() ) {
			return;
		}
		$cfg = self::active_config();
		if ( null === $cfg ) {
			return;
		}
		if ( self::is_bot_request() ) {
			do_action( 'litespeed_control_set_nocache', 'rwgo-sitewide-bot' );
			return;
		}
		$role = self::role_for_current_uri( $cfg );
		if ( 'none' === $role ) {
			return;
		}
		$serves_or_assigns = ( 'source' === $role ) || self::incoming_cookie_matches( $cfg );
		if ( ! self::mapped_response_needs_cache_bypass( $role, $serves_or_assigns ) ) {
			return;
		}
		do_action( 'litespeed_control_set_nocache', 'rwgo-sitewide-assign' );
		$arm = self::arm_from_raw_cookie( self::raw_cookie(), $cfg );
		if ( in_array( $arm, array( 'control', 'variant' ), true ) ) {
			do_action( 'litespeed_vary_add', 'rwgo_arm_' . $arm ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed public API.
		}
	}

	/**
	 * @return void
	 */
	public static function maybe_redirect_variant_url() {
		if ( self::request_is_skipped() ) {
			return;
		}
		$cfg = self::active_config();
		if ( null === $cfg ) {
			return;
		}
		$pid = self::queried_page_id();
		$row = self::row_for_variant_page( $cfg, $pid );
		if ( null === $row ) {
			return;
		}
		$source = (int) $row['source_page_id'];
		$url    = function_exists( 'get_permalink' ) ? get_permalink( $source ) : '';
		if ( ! is_string( $url ) || '' === $url ) {
			return;
		}
		$arm = 'control';
		if ( ! self::is_bot_request() && ! self::is_editor_request() ) {
			$fixed = self::ensure_assignment( $cfg );
			$arm   = $fixed['arm'];
		}
		self::send_uncacheable();
		self::$handled = true;
		if ( ! headers_sent() ) {
			header( 'X-RWGO-Arm: ' . $arm );
			header( 'X-RWGO-Sitewide-Redirect: original' );
		}
		wp_safe_redirect( $url, 302 );
		exit;
	}

	/**
	 * @return void
	 */
	public static function maybe_apply() {
		if ( self::request_is_skipped() || self::$handled ) {
			return;
		}
		$cfg = self::active_config();
		if ( null === $cfg ) {
			self::maybe_record_goal_page( null );
			return;
		}
		$pid = self::queried_page_id();
		$row = self::row_for_source_page( $cfg, $pid );
		if ( null === $row ) {
			self::maybe_record_goal_page( $cfg );
			return;
		}
		self::$handled         = true;
		self::$original_page_id = $pid;
		$decision              = self::decide( $cfg );
		$arm                   = $decision['arm'];
		self::$rendered_arm    = $arm;
		if ( $decision['nocache'] ) {
			self::send_uncacheable();
		}
		if ( ! headers_sent() ) {
			header( 'X-RWGO-Arm: ' . $arm );
		}
		$variant_id = (int) $row['variant_page_id'];
		if ( 'variant' === $arm && $variant_id > 0 && $variant_id !== $pid ) {
			self::swap_query_to( $variant_id );
		}
		self::record_exposure( $cfg, $arm, $pid, ( 'variant' === $arm && $variant_id > 0 ) ? $variant_id : $pid );
		self::maybe_record_goal_page( $cfg );
	}

	/**
	 * @param mixed $redirect  Redirect target.
	 * @param mixed $requested Requested URL.
	 * @return mixed
	 */
	public static function keep_original_url( $redirect, $requested ) {
		unset( $requested );
		if ( self::$original_page_id > 0 && 'variant' === self::$rendered_arm ) {
			return false;
		}
		return $redirect;
	}

	/**
	 * @param string       $canonical Canonical.
	 * @param WP_Post|null $post      Post.
	 * @return string
	 */
	public static function filter_canonical( $canonical, $post = null ) {
		unset( $post );
		$url = self::original_permalink();
		return '' !== $url ? $url : $canonical;
	}

	/**
	 * @param string $canonical Canonical.
	 * @return string
	 */
	public static function filter_canonical_url( $canonical ) {
		$url = self::original_permalink();
		return '' !== $url ? $url : $canonical;
	}

	/**
	 * @param array<string, bool|string> $robots Robots directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( $robots ) {
		if ( ! is_array( $robots ) ) {
			$robots = array();
		}
		$cfg = self::get_config();
		$pid = self::queried_page_id();
		if ( self::$original_page_id > 0 ) {
			return $robots;
		}
		if ( null !== self::row_for_variant_page( $cfg, $pid ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	public static function exclude_core_sitemap( $args ) {
		$ids = self::variant_page_ids( self::get_config() );
		if ( empty( $ids ) || ! is_array( $args ) ) {
			return $args;
		}
		$existing              = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ? $args['post__not_in'] : array();
		$args['post__not_in'] = array_values( array_unique( array_merge( array_map( 'intval', $existing ), $ids ) ) );
		return $args;
	}

	/**
	 * @param array<int, int> $ids Excluded ids.
	 * @return array<int, int>
	 */
	public static function exclude_yoast_sitemap( $ids ) {
		if ( ! is_array( $ids ) ) {
			$ids = array();
		}
		return array_values( array_unique( array_merge( array_map( 'intval', $ids ), self::variant_page_ids( self::get_config() ) ) ) );
	}

	/**
	 * @param WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public static function admin_bar( $bar ) {
		if ( ! is_object( $bar ) || ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( null === self::active_config() && '' === self::$rendered_arm ) {
			return;
		}
		$arm   = '' !== self::$rendered_arm ? self::$rendered_arm : 'control';
		$label = 'variant' === $arm
			? __( 'Site test: Variant', 'reactwoo-geo-optimise' )
			: __( 'Site test: Control', 'reactwoo-geo-optimise' );
		if ( self::qa_arm_from_request() !== '' ) {
			$label .= ' ' . __( '(forced)', 'reactwoo-geo-optimise' );
		}
		$bar->add_node(
			array(
				'id'    => 'rwgo-sitewide-arm',
				'title' => $label,
			)
		);
	}

	/**
	 * @return void
	 */
	public static function render_screen() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			return;
		}
		$cfg = self::get_config();
		if ( empty( $cfg['experiment_key'] ) ) {
			$cfg['experiment_key'] = 'rwgo_sitewide';
		}
		$status = isset( $cfg['status'] ) ? sanitize_key( (string) $cfg['status'] ) : 'paused';
		$days   = isset( $cfg['cookie_days'] ) ? (int) $cfg['cookie_days'] : self::DEFAULT_DAYS;
		$weight = isset( $cfg['control_weight'] ) ? (float) $cfg['control_weight'] : 0.5;
		$lines  = array();
		foreach ( self::page_map( $cfg ) as $row ) {
			$lines[] = (int) $row['source_page_id'] . ',' . (int) $row['variant_page_id'];
		}
		$goals = isset( $cfg['goal_page_ids'] ) && is_array( $cfg['goal_page_ids'] ) ? implode( ',', array_map( 'intval', $cfg['goal_page_ids'] ) ) : '';
		$buy   = ! empty( $cfg['track_purchase'] );
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Site-wide design test', 'reactwoo-geo-optimise' ) . '</h1>';
		echo '<p>' . esc_html__( 'One arm per visitor. Mapped pages keep their original URL. Unmapped pages stay on the original and the visitor keeps their arm.', 'reactwoo-geo-optimise' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'rwgo_save_sitewide' );
		echo '<input type="hidden" name="action" value="rwgo_save_sitewide" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row"><label for="rwgo_sw_status">' . esc_html__( 'Status', 'reactwoo-geo-optimise' ) . '</label></th><td><select id="rwgo_sw_status" name="rwgo_sw_status">';
		foreach ( array( 'active' => __( 'Active', 'reactwoo-geo-optimise' ), 'paused' => __( 'Paused', 'reactwoo-geo-optimise' ), 'ended' => __( 'Ended', 'reactwoo-geo-optimise' ) ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row"><label for="rwgo_sw_key">' . esc_html__( 'Experiment key', 'reactwoo-geo-optimise' ) . '</label></th><td><input id="rwgo_sw_key" class="regular-text" name="rwgo_sw_key" value="' . esc_attr( (string) $cfg['experiment_key'] ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="rwgo_sw_weight">' . esc_html__( 'Control weight (%)', 'reactwoo-geo-optimise' ) . '</label></th><td><input id="rwgo_sw_weight" type="number" min="0" max="100" name="rwgo_sw_weight" value="' . esc_attr( (string) (int) round( $weight * 100 ) ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="rwgo_sw_days">' . esc_html__( 'Cookie lifetime (days)', 'reactwoo-geo-optimise' ) . '</label></th><td><input id="rwgo_sw_days" type="number" min="1" name="rwgo_sw_days" value="' . esc_attr( (string) $days ) . '" /></td></tr>';
		echo '<tr><th scope="row"><label for="rwgo_sw_map">' . esc_html__( 'Page map', 'reactwoo-geo-optimise' ) . '</label></th><td><textarea id="rwgo_sw_map" name="rwgo_sw_map" rows="8" class="large-text code">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea><p class="description">' . esc_html__( 'One pair per line: original page ID, variant page ID.', 'reactwoo-geo-optimise' ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="rwgo_sw_goals">' . esc_html__( 'Goal page IDs', 'reactwoo-geo-optimise' ) . '</label></th><td><input id="rwgo_sw_goals" class="regular-text" name="rwgo_sw_goals" value="' . esc_attr( $goals ) . '" /><p class="description">' . esc_html__( 'Comma-separated. A view of these pages (for example the contact thank-you page) counts for the site-wide arm.', 'reactwoo-geo-optimise' ) . '</p></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'WooCommerce purchase', 'reactwoo-geo-optimise' ) . '</th><td><label><input type="checkbox" name="rwgo_sw_purchase" value="1" ' . checked( $buy, true, false ) . ' /> ' . esc_html__( 'Attribute purchases to the site-wide arm', 'reactwoo-geo-optimise' ) . '</label></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Save site-wide test', 'reactwoo-geo-optimise' ) );
		echo '</form></div>';
	}

	/**
	 * @return void
	 */
	public static function handle_save() {
		if ( ! class_exists( 'RWGO_Admin', false ) || ! RWGO_Admin::can_manage() ) {
			wp_die( esc_html__( 'You cannot edit this test.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( 'rwgo_save_sitewide' );
		$old    = self::get_config();
		$status = isset( $_POST['rwgo_sw_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgo_sw_status'] ) ) : 'paused';
		if ( ! in_array( $status, array( 'active', 'paused', 'ended' ), true ) ) {
			$status = 'paused';
		}
		$key    = isset( $_POST['rwgo_sw_key'] ) ? sanitize_key( wp_unslash( (string) $_POST['rwgo_sw_key'] ) ) : 'rwgo_sitewide';
		$weight = isset( $_POST['rwgo_sw_weight'] ) ? (float) wp_unslash( $_POST['rwgo_sw_weight'] ) : 50;
		$weight = max( 0, min( 100, $weight ) ) / 100;
		$days   = isset( $_POST['rwgo_sw_days'] ) ? (int) wp_unslash( $_POST['rwgo_sw_days'] ) : self::DEFAULT_DAYS;
		$map_in = isset( $_POST['rwgo_sw_map'] ) ? (string) wp_unslash( $_POST['rwgo_sw_map'] ) : '';
		$goals  = isset( $_POST['rwgo_sw_goals'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['rwgo_sw_goals'] ) ) : '';
		$map    = self::parse_map_text( $map_in );
		$goal_ids = array();
		foreach ( preg_split( '/\s*,\s*/', $goals ) as $part ) {
			$id = (int) $part;
			if ( $id > 0 ) {
				$goal_ids[] = $id;
			}
		}
		$cfg = array(
			'experiment_key'  => '' !== $key ? $key : 'rwgo_sitewide',
			'status'          => $status,
			'test_type'       => 'sitewide',
			'scope'           => 'site',
			'cookie_days'     => max( 1, $days ),
			'control_weight'  => $weight,
			'page_map'        => $map,
			'goal_page_ids'   => array_values( array_unique( $goal_ids ) ),
			'track_purchase'  => ! empty( $_POST['rwgo_sw_purchase'] ),
			'source_page_id'  => isset( $map[0]['source_page_id'] ) ? (int) $map[0]['source_page_id'] : 0,
			'variants'        => array(
				array(
					'variant_id' => 'control',
					'page_id'    => 0,
					'weight'     => $weight,
				),
				array(
					'variant_id' => 'var_b',
					'page_id'    => 0,
					'weight'     => 1 - $weight,
				),
			),
			'goals'           => array(
				array(
					'goal_id'   => 'sitewide_destination',
					'goal_type' => 'page_view',
					'label'     => __( 'Site-wide conversion', 'reactwoo-geo-optimise' ),
					'handlers'  => array(
						array(
							'handler_id'   => 'sitewide_destination',
							'handler_type' => 'page_view',
						),
					),
				),
			),
			'primary_goal_id' => 'sitewide_destination',
		);
		update_option( self::OPTION, $cfg, false );
		self::sync_experiment_post( $cfg );
		self::purge_config( $old );
		self::purge_config( $cfg );
		wp_safe_redirect( admin_url( 'admin.php?page=rwgo-sitewide&rwgo_saved=1' ) );
		exit;
	}

	/**
	 * @param int $order_id Order id.
	 * @return void
	 */
	public static function on_purchase( $order_id ) {
		$cfg = self::active_config();
		if ( null === $cfg || empty( $cfg['track_purchase'] ) ) {
			return;
		}
		$order_id = (int) $order_id;
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ! is_object( $order ) ) {
			return;
		}
		if ( method_exists( $order, 'get_meta' ) && $order->get_meta( '_rwgo_sitewide_purchase', true ) ) {
			return;
		}
		$arm = self::sticky_arm( $cfg );
		if ( '' === $arm ) {
			return;
		}
		self::fire_goal( $cfg, $arm, 'purchase', 'sitewide_purchase', 0, 0 );
		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_rwgo_sitewide_purchase', self::report_slug( $arm ) );
			$order->save();
		}
	}

	/**
	 * @param string $raw Cookie JSON.
	 * @return array{id:string,arm:string,exp:string,qa:bool}
	 */
	private static function parse_cookie( $raw ) {
		$empty = array(
			'id'  => '',
			'arm' => '',
			'exp' => '',
			'qa'  => false,
		);
		if ( ! is_string( $raw ) || '' === $raw ) {
			return $empty;
		}
		$dec = json_decode( $raw, true );
		if ( ! is_array( $dec ) ) {
			return $empty;
		}
		$id = isset( $dec['id'] ) ? strtolower( (string) $dec['id'] ) : '';
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $id ) ) {
			$id = '';
		}
		$arm = isset( $dec['arm'] ) ? sanitize_key( (string) $dec['arm'] ) : '';
		if ( ! in_array( $arm, array( 'control', 'variant' ), true ) ) {
			$arm = '';
		}
		return array(
			'id'  => $id,
			'arm' => $arm,
			'exp' => isset( $dec['exp'] ) ? sanitize_key( (string) $dec['exp'] ) : '',
			'qa'  => ! empty( $dec['qa'] ),
		);
	}

	/**
	 * @return string
	 */
	private static function new_visitor_id() {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( Exception $e ) {
			return substr( hash( 'sha256', uniqid( 'rwgo', true ) ), 0, 32 );
		}
	}

	/**
	 * @param string               $path  Left.
	 * @param string               $other Right.
	 * @return bool
	 */
	private static function paths_match( $path, $other ) {
		if ( '' === trim( (string) $other ) ) {
			return false;
		}
		return $path === self::normalize_request_path( $other );
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return array<int, array{source_page_id:int,variant_page_id:int,source_path:string,variant_path:string}>
	 */
	private static function page_map( $cfg ) {
		$out = array();
		$rows = isset( $cfg['page_map'] ) && is_array( $cfg['page_map'] ) ? $cfg['page_map'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source = (int) ( $row['source_page_id'] ?? 0 );
			$variant = (int) ( $row['variant_page_id'] ?? 0 );
			if ( $source <= 0 || $variant <= 0 || $source === $variant ) {
				continue;
			}
			$out[] = array(
				'source_page_id'  => $source,
				'variant_page_id' => $variant,
				'source_path'     => isset( $row['source_path'] ) ? (string) $row['source_path'] : '',
				'variant_path'    => isset( $row['variant_path'] ) ? (string) $row['variant_path'] : '',
			);
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return array<int, int>
	 */
	private static function variant_page_ids( $cfg ) {
		$ids = array();
		foreach ( self::page_map( $cfg ) as $row ) {
			$ids[] = (int) $row['variant_page_id'];
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param string $text Textarea.
	 * @return array<int, array<string, mixed>>
	 */
	private static function parse_map_text( $text ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, ',' ) ) {
				continue;
			}
			$parts   = array_map( 'trim', explode( ',', $line ) );
			$source  = (int) $parts[0];
			$variant = (int) ( $parts[1] ?? 0 );
			if ( $source <= 0 || $variant <= 0 ) {
				continue;
			}
			$row = array(
				'source_page_id'  => $source,
				'variant_page_id' => $variant,
				'source_path'     => self::path_for_post( $source ),
				'variant_path'    => self::path_for_post( $variant ),
			);
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * @param int $post_id Post id.
	 * @return string
	 */
	private static function path_for_post( $post_id ) {
		if ( ! function_exists( 'get_permalink' ) ) {
			return '';
		}
		$url = get_permalink( (int) $post_id );
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}
		$path = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url, PHP_URL_PATH ) : parse_url( $url, PHP_URL_PATH );
		return self::normalize_request_path( is_string( $path ) ? $path : '' );
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return string
	 */
	private static function experiment_key( array $cfg ) {
		$key = isset( $cfg['experiment_key'] ) ? sanitize_key( (string) $cfg['experiment_key'] ) : '';
		return '' !== $key ? $key : 'rwgo_sitewide';
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return float
	 */
	private static function control_weight( array $cfg ) {
		return isset( $cfg['control_weight'] ) ? (float) $cfg['control_weight'] : 0.5;
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return int
	 */
	private static function cookie_days( array $cfg ) {
		$days = isset( $cfg['cookie_days'] ) ? (int) $cfg['cookie_days'] : self::DEFAULT_DAYS;
		$days = (int) apply_filters( 'rwgo_sitewide_cookie_days', $days, $cfg );
		return $days > 0 ? $days : self::DEFAULT_DAYS;
	}

	/**
	 * @return bool
	 */
	private static function request_is_skipped() {
		$flags = array(
			'admin'   => function_exists( 'is_admin' ) && is_admin(),
			'ajax'    => function_exists( 'wp_doing_ajax' ) && wp_doing_ajax(),
			'cron'    => ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) || ( defined( 'DOING_CRON' ) && DOING_CRON ),
			'rest'    => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'cli'     => defined( 'WP_CLI' ) && WP_CLI,
			'preview' => ( function_exists( 'is_preview' ) && is_preview() ) || ! empty( $_GET['preview'] ) || ! empty( $_GET['elementor-preview'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'json'    => function_exists( 'wp_is_json_request' ) && wp_is_json_request(),
		);
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			$flags['preview'] = true;
		}
		return self::context_skips_assignment( $flags );
	}

	/**
	 * @return bool
	 */
	private static function is_bot_request() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		return self::is_known_bot( $ua );
	}

	/**
	 * Editors stay on control unless they pass a capability-checked arm.
	 *
	 * @return bool
	 */
	private static function is_editor_request() {
		if ( self::qa_arm_from_request() !== '' ) {
			return false;
		}
		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) {
			return false;
		}
		return ( function_exists( 'current_user_can' ) && ( current_user_can( 'edit_pages' ) || current_user_can( 'edit_posts' ) ) );
	}

	/**
	 * @return string
	 */
	private static function qa_arm_from_request() {
		if ( empty( $_GET['rwgo_arm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}
		$arm = sanitize_key( wp_unslash( (string) $_GET['rwgo_arm'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ok  = function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
		return self::qa_override_allowed( $ok, $arm );
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return array{arm:string,nocache:bool}
	 */
	private static function decide( array $cfg ) {
		if ( self::is_bot_request() ) {
			return array(
				'arm'     => 'control',
				'nocache' => true,
			);
		}
		$qa = self::qa_arm_from_request();
		if ( self::is_editor_request() && '' === $qa ) {
			return array(
				'arm'     => 'control',
				'nocache' => true,
			);
		}
		$fixed = self::ensure_assignment( $cfg );
		$arm   = self::serving_arm( $fixed['arm'], false, $qa );
		return array(
			'arm'     => $arm,
			'nocache' => self::served_arm_needs_cache_bypass( $fixed['assigning'], $fixed['rewritten'], $arm, '' !== $qa ),
		);
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return array{id:string,arm:string,rewritten:bool,assigning:bool,qa:bool}
	 */
	private static function ensure_assignment( array $cfg ) {
		$key   = self::experiment_key( $cfg );
		$fixed = self::repair_cookie( self::raw_cookie(), $key, self::control_weight( $cfg ), self::qa_arm_from_request() );
		if ( $fixed['rewritten'] || $fixed['assigning'] ) {
			self::write_cookie( $cfg, $fixed );
		}
		if ( class_exists( 'RWGO_Assignment', false ) ) {
			RWGO_Assignment::remember( $key, self::report_slug( $fixed['arm'] ) );
		}
		return $fixed;
	}

	/**
	 * Arm already stored, without creating one.
	 *
	 * @param array<string, mixed> $cfg Config.
	 * @return string
	 */
	private static function sticky_arm( array $cfg ) {
		if ( self::is_bot_request() ) {
			return '';
		}
		$parsed = self::parse_cookie( self::raw_cookie() );
		if ( '' === $parsed['id'] || $parsed['exp'] !== self::experiment_key( $cfg ) ) {
			return '';
		}
		if ( ! empty( $parsed['qa'] ) && ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) ) {
			return self::bucket_arm( $parsed['id'], self::experiment_key( $cfg ), self::control_weight( $cfg ) );
		}
		if ( ! empty( $parsed['qa'] ) ) {
			return $parsed['arm'];
		}
		return self::bucket_arm( $parsed['id'], self::experiment_key( $cfg ), self::control_weight( $cfg ) );
	}

	/**
	 * @return string
	 */
	private static function raw_cookie() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) || ! is_string( $_COOKIE[ self::COOKIE ] ) ) {
			return '';
		}
		return (string) wp_unslash( $_COOKIE[ self::COOKIE ] );
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return bool
	 */
	private static function incoming_cookie_matches( array $cfg ) {
		$fixed = self::repair_cookie( self::raw_cookie(), self::experiment_key( $cfg ), self::control_weight( $cfg ), '' );
		return ! $fixed['assigning'] && ! $fixed['rewritten'];
	}

	/**
	 * @param string               $raw Raw cookie.
	 * @param array<string, mixed> $cfg Config.
	 * @return string
	 */
	private static function arm_from_raw_cookie( $raw, array $cfg ) {
		$parsed = self::parse_cookie( $raw );
		if ( '' === $parsed['id'] ) {
			return '';
		}
		if ( ! empty( $parsed['qa'] ) ) {
			return $parsed['arm'];
		}
		return self::bucket_arm( $parsed['id'], self::experiment_key( $cfg ), self::control_weight( $cfg ) );
	}

	/**
	 * @param array<string, mixed>                               $cfg   Config.
	 * @param array{id:string,arm:string,rewritten:bool,assigning:bool,qa:bool} $fixed Assignment.
	 * @return void
	 */
	private static function write_cookie( array $cfg, array $fixed ) {
		$payload = array(
			'id'  => $fixed['id'],
			'arm' => $fixed['arm'],
			'exp' => self::experiment_key( $cfg ),
		);
		if ( ! empty( $fixed['qa'] ) ) {
			$payload['qa'] = 1;
		}
		$json = wp_json_encode( $payload );
		if ( ! is_string( $json ) || headers_sent() ) {
			return;
		}
		$opts = self::cookie_options( self::cookie_days( $cfg ), function_exists( 'is_ssl' ) && is_ssl() );
		if ( defined( 'COOKIE_DOMAIN' ) && is_string( COOKIE_DOMAIN ) && '' !== COOKIE_DOMAIN ) {
			$opts['domain'] = COOKIE_DOMAIN;
		}
		setcookie( self::COOKIE, $json, $opts );
		$_COOKIE[ self::COOKIE ] = $json;
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return string
	 */
	private static function role_for_current_uri( array $cfg ) {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$role = self::path_role( $uri, self::page_map( $cfg ) );
		if ( 'none' !== $role || ! function_exists( 'url_to_postid' ) ) {
			return $role;
		}
		$path = self::normalize_request_path( $uri );
		$home = function_exists( 'home_url' ) ? home_url( $path ) : $path;
		$id   = (int) url_to_postid( is_string( $home ) ? $home : $path );
		if ( $id <= 0 ) {
			return 'none';
		}
		if ( null !== self::row_for_variant_page( $cfg, $id ) ) {
			return 'variant';
		}
		if ( null !== self::row_for_source_page( $cfg, $id ) ) {
			return 'source';
		}
		return 'none';
	}

	/**
	 * @return int
	 */
	private static function queried_page_id() {
		if ( function_exists( 'get_queried_object_id' ) ) {
			return (int) get_queried_object_id();
		}
		return 0;
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @param int                  $page_id Page id.
	 * @return array{source_page_id:int,variant_page_id:int,source_path:string,variant_path:string}|null
	 */
	private static function row_for_source_page( $cfg, $page_id ) {
		$page_id = (int) $page_id;
		foreach ( self::page_map( $cfg ) as $row ) {
			if ( (int) $row['source_page_id'] === $page_id ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @param int                  $page_id Page id.
	 * @return array{source_page_id:int,variant_page_id:int,source_path:string,variant_path:string}|null
	 */
	private static function row_for_variant_page( $cfg, $page_id ) {
		$page_id = (int) $page_id;
		foreach ( self::page_map( $cfg ) as $row ) {
			if ( (int) $row['variant_page_id'] === $page_id ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * @param int $variant_id Variant page.
	 * @return void
	 */
	private static function swap_query_to( $variant_id ) {
		$variant_id = (int) $variant_id;
		if ( $variant_id <= 0 || ! function_exists( 'get_post' ) ) {
			return;
		}
		$post = get_post( $variant_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
			return;
		}
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->queried_object    = $post;
			$wp_query->queried_object_id = $variant_id;
			$wp_query->post              = $post;
			$wp_query->posts             = array( $post );
			$wp_query->post_count        = 1;
		}
		$GLOBALS['post'] = $post;
		if ( function_exists( 'setup_postdata' ) ) {
			setup_postdata( $post );
		}
	}

	/**
	 * @return string
	 */
	private static function original_permalink() {
		if ( self::$original_page_id <= 0 || 'variant' !== self::$rendered_arm || ! function_exists( 'get_permalink' ) ) {
			return '';
		}
		$url = get_permalink( self::$original_page_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Whether a mapped URL must bypass shared page caches.
	 *
	 * Variant URLs redirect. Source URLs assign an arm, or serve the arm already
	 * stored for a returning visitor. Either response can be replayed as the wrong arm.
	 *
	 * @param string $role              source|variant|none.
	 * @param bool   $serves_or_assigns True when this response assigns an arm or serves a stored one.
	 * @return bool
	 */
	public static function mapped_response_needs_cache_bypass( $role, $serves_or_assigns ) {
		if ( 'variant' === (string) $role ) {
			return true;
		}
		if ( 'source' === (string) $role && $serves_or_assigns ) {
			return true;
		}
		return false;
	}

	/**
	 * Whether the arm about to be rendered must not be stored.
	 *
	 * A returning visitor has assigning and rewritten false. The response still
	 * serves control or variant, so it needs the same bypass as a new assignment.
	 *
	 * @param bool   $assigning Fresh visitor id.
	 * @param bool   $rewritten Cookie repaired.
	 * @param string $arm       Arm this response will serve.
	 * @param bool   $qa        QA override active.
	 * @return bool
	 */
	public static function served_arm_needs_cache_bypass( $assigning, $rewritten, $arm, $qa ) {
		if ( $assigning || $rewritten || $qa ) {
			return true;
		}
		return in_array( (string) $arm, array( 'control', 'variant' ), true );
	}

	/**
	 * Headers sent whenever an arm is assigned or served.
	 *
	 * @return array<string, string>
	 */
	public static function cache_bypass_headers() {
		return array(
			'Cache-Control'             => 'private, no-store, max-age=0',
			'Vary'                      => 'Cookie',
			'X-LiteSpeed-Cache-Control' => 'no-cache',
		);
	}

	/**
	 * @return void
	 */
	private static function send_uncacheable() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		if ( ! headers_sent() ) {
			foreach ( self::cache_bypass_headers() as $name => $value ) {
				header( $name . ': ' . $value, 'Vary' !== $name );
			}
		}
		do_action( 'litespeed_control_set_nocache', 'rwgo-sitewide' );
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @param string               $arm Arm.
	 * @param int                  $source Source page.
	 * @param int                  $rendered Rendered page.
	 * @return void
	 */
	private static function record_exposure( array $cfg, $arm, $source, $rendered ) {
		$key  = self::experiment_key( $cfg );
		$slug = self::report_slug( $arm );
		if ( class_exists( 'RWGO_Stats', false ) ) {
			RWGO_Stats::record_variant_served( $key, $slug );
		}
		if ( class_exists( 'RWGO_Exposure', false ) ) {
			RWGO_Exposure::record( $cfg, $slug, $source, $rendered, (int) get_option( self::POST_OPTION, 0 ) );
		}
	}

	/**
	 * @param array<string, mixed>|null $cfg Config.
	 * @return void
	 */
	private static function maybe_record_goal_page( $cfg ) {
		if ( ! is_array( $cfg ) ) {
			$cfg = self::active_config();
		}
		if ( ! is_array( $cfg ) ) {
			return;
		}
		$goals = isset( $cfg['goal_page_ids'] ) && is_array( $cfg['goal_page_ids'] ) ? array_map( 'intval', $cfg['goal_page_ids'] ) : array();
		if ( empty( $goals ) ) {
			return;
		}
		$pid = self::$original_page_id > 0 ? self::$original_page_id : self::queried_page_id();
		$rendered = self::queried_page_id();
		if ( ! in_array( $pid, $goals, true ) && ! in_array( $rendered, $goals, true ) ) {
			return;
		}
		$arm = '' !== self::$rendered_arm ? self::$rendered_arm : self::sticky_arm( $cfg );
		if ( '' === $arm || self::is_bot_request() ) {
			return;
		}
		self::fire_goal( $cfg, $arm, 'page_view', 'sitewide_destination', $pid, $rendered );
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @param string               $arm Arm.
	 * @param string               $goal_type Goal type.
	 * @param string               $handler Handler id.
	 * @param int                  $source Source page.
	 * @param int                  $rendered Rendered page.
	 * @return void
	 */
	private static function fire_goal( array $cfg, $arm, $goal_type, $handler, $source, $rendered ) {
		$key     = self::experiment_key( $cfg );
		$slug    = self::report_slug( $arm );
		$session = class_exists( 'RWGO_Exposure', false ) ? RWGO_Exposure::session_hash() : substr( hash( 'sha256', self::raw_cookie() ), 0, 32 );
		$instance = 'sw_' . substr( hash( 'sha256', $key . '|' . $handler . '|' . $session . '|' . gmdate( 'Y-m-d' ) . '|' . (int) $source ), 0, 40 );
		if ( class_exists( 'RWGO_Event_Store', false ) && RWGO_Event_Store::has_event_instance_id( $instance ) ) {
			return;
		}
		$parts = array(
			'event_instance_id'    => $instance,
			'experiment_id'        => (int) get_option( self::POST_OPTION, 0 ),
			'experiment_key'       => $key,
			'variant_id'           => $slug,
			'variant_label'        => 'var_b' === $slug ? 'Variant' : 'Control',
			'goal_id'              => 'purchase' === $goal_type ? 'sitewide_purchase' : 'sitewide_destination',
			'goal_type'            => $goal_type,
			'handler_id'           => $handler,
			'page_context_id'      => (int) $source,
			'page_variant_post_id' => (int) $rendered,
			'source'               => 'rwgo_sitewide',
		);
		$payload = class_exists( 'RWGO_Event_Payload', false )
			? RWGO_Event_Payload::normalize_goal_fired( $parts )
			: $parts;
		do_action( 'rwgo_goal_fired', $payload );
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return void
	 */
	private static function sync_experiment_post( array $cfg ) {
		if ( ! class_exists( 'RWGO_Experiment_Repository', false ) || ! function_exists( 'wp_insert_post' ) ) {
			return;
		}
		$post_id = (int) get_option( self::POST_OPTION, 0 );
		$valid   = $post_id > 0 && function_exists( 'get_post_type' ) && RWGO_Experiment_CPT::POST_TYPE === get_post_type( $post_id );
		if ( ! $valid ) {
			$post_id = (int) wp_insert_post(
				array(
					'post_type'   => RWGO_Experiment_CPT::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => __( 'Site-wide design test', 'reactwoo-geo-optimise' ),
				)
			);
			if ( $post_id > 0 ) {
				update_option( self::POST_OPTION, $post_id, false );
			}
		}
		if ( $post_id > 0 ) {
			RWGO_Experiment_Repository::save_config( $post_id, $cfg );
		}
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return void
	 */
	private static function purge_config( $cfg ) {
		if ( ! is_array( $cfg ) ) {
			return;
		}
		foreach ( self::page_map( $cfg ) as $row ) {
			foreach ( array( 'source_page_id', 'variant_page_id' ) as $key ) {
				$id = (int) $row[ $key ];
				if ( $id <= 0 ) {
					continue;
				}
				do_action( 'litespeed_purge_post', $id );
				if ( function_exists( 'get_permalink' ) ) {
					$url = get_permalink( $id );
					if ( is_string( $url ) && '' !== $url ) {
						do_action( 'litespeed_purge_url', $url );
					}
				}
			}
		}
	}
}
