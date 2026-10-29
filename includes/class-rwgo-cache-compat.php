<?php
/**
 * Page-cache vary and purge for experiment assignment.
 *
 * Adapters run only when that cache plugin is loaded. With none active, public
 * responses are left alone.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * LiteSpeed vary, guarded purges, and the settings cache-clear action.
 */
class RWGO_Cache_Compat {

	const CLEAR_ACTION = 'rwgo_clear_optimise_cache';

	const CLEAR_NONCE = 'rwgo_clear_optimise_cache';

	/**
	 * Steps already sent to an adapter during this request.
	 *
	 * @var array<string, bool>
	 */
	private static $once = array();

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'litespeed_vary_cookies', array( __CLASS__, 'filter_litespeed_vary_cookies' ) );
		add_action( 'send_headers', array( __CLASS__, 'maybe_send_litespeed_vary_header' ), 20 );
		add_action( 'litespeed_init', array( __CLASS__, 'maybe_send_litespeed_vary_header' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_send_litespeed_vary_header' ), 0 );
		add_action( 'update_option_' . self::sitewide_option_name(), array( __CLASS__, 'on_sitewide_option_updated' ), 10, 2 );
		add_action( 'add_option_' . self::sitewide_option_name(), array( __CLASS__, 'on_sitewide_option_added' ), 10, 2 );
		add_action( 'rwgo_variant_promoted_to_control', array( __CLASS__, 'on_variant_promoted' ), 10, 3 );
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( __CLASS__, 'handle_clear_cache' ) );
	}

	/**
	 * Cookie names whose values select control or variant B.
	 *
	 * @return array<int, string>
	 */
	public static function assignment_cookie_names() {
		$experiment = 'rwgo_ab';
		$sitewide   = 'rwgo_site';
		if ( class_exists( 'RWGO_Assignment', false ) ) {
			$experiment = (string) RWGO_Assignment::COOKIE;
		}
		if ( class_exists( 'RWGO_Sitewide', false ) ) {
			$sitewide = (string) RWGO_Sitewide::COOKIE;
		}
		$names = array();
		foreach ( array( $experiment, $sitewide ) as $name ) {
			$name = (string) $name;
			if ( '' === $name || in_array( $name, $names, true ) ) {
				continue;
			}
			$names[] = $name;
		}
		return $names;
	}

	/**
	 * @param mixed $cookies Cookie names LiteSpeed already varies on.
	 * @return array<int, string>
	 */
	public static function filter_litespeed_vary_cookies( $cookies ) {
		if ( ! is_array( $cookies ) ) {
			$cookies = array();
		}
		foreach ( self::assignment_cookie_names() as $name ) {
			if ( ! in_array( $name, $cookies, true ) ) {
				$cookies[] = $name;
			}
		}
		return $cookies;
	}

	/**
	 * Value for the X-LiteSpeed-Vary response header.
	 *
	 * @return string
	 */
	public static function litespeed_vary_header_value() {
		$parts = array();
		foreach ( self::assignment_cookie_names() as $name ) {
			$parts[] = 'cookie=' . $name;
		}
		return implode( ',', $parts );
	}

	/**
	 * @param string $existing Header value already queued.
	 * @param string $addition Cookies this plugin adds.
	 * @return string
	 */
	public static function merge_litespeed_vary( $existing, $addition ) {
		$tokens = array();
		foreach ( array( (string) $existing, (string) $addition ) as $chunk ) {
			foreach ( explode( ',', $chunk ) as $part ) {
				$part = trim( $part );
				if ( '' !== $part ) {
					$tokens[] = $part;
				}
			}
		}
		$unique = array();
		$seen   = array();
		foreach ( $tokens as $token ) {
			$key = strtolower( $token );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$unique[]     = $token;
		}
		return implode( ',', $unique );
	}

	/**
	 * @param bool $litespeed_active LiteSpeed Cache plugin is loaded.
	 * @param bool $is_admin         Admin request.
	 * @param bool $headers_sent     Headers already sent.
	 * @return bool
	 */
	public static function should_send_litespeed_vary_header( $litespeed_active, $is_admin, $headers_sent ) {
		return (bool) $litespeed_active && ! $is_admin && ! $headers_sent;
	}

	/**
	 * Send X-LiteSpeed-Vary when LiteSpeed Cache is active.
	 *
	 * @return void
	 */
	public static function maybe_send_litespeed_vary_header() {
		$is_admin = ( function_exists( 'is_admin' ) && is_admin() )
			|| ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
			|| ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() )
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		$sent = function_exists( 'headers_sent' ) ? headers_sent() : false;
		if ( ! self::should_send_litespeed_vary_header( self::litespeed_active(), $is_admin, $sent ) ) {
			return;
		}
		$existing = '';
		if ( function_exists( 'headers_list' ) ) {
			foreach ( headers_list() as $line ) {
				if ( is_string( $line ) && preg_match( '/^X-LiteSpeed-Vary:\s*(.+)$/i', $line, $matches ) ) {
					$existing = trim( $matches[1] );
				}
			}
		}
		$value = self::merge_litespeed_vary( $existing, self::litespeed_vary_header_value() );
		if ( '' === $value ) {
			return;
		}
		if ( '' !== $existing && function_exists( 'header_remove' ) ) {
			header_remove( 'X-LiteSpeed-Vary' );
		}
		header( 'X-LiteSpeed-Vary: ' . $value, true );
	}

	/**
	 * A brand-new assignment cookie must not be stored as the shared page.
	 *
	 * No headers are sent when no page-cache plugin is active.
	 *
	 * @return void
	 */
	public static function on_new_assignment_cookie() {
		if ( ! self::any_page_cache_active() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( function_exists( 'nocache_headers' ) && ( ! function_exists( 'headers_sent' ) || ! headers_sent() ) ) {
			nocache_headers();
		}
		if ( self::litespeed_active() && function_exists( 'do_action' ) ) {
			do_action( 'litespeed_control_set_nocache', 'rwgo-new-assignment' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed public API.
		}
	}

	/**
	 * Fields localized for rwgo-tracking.js. The nonce is intentionally absent.
	 *
	 * @param string $rest_url  Goal endpoint.
	 * @param string $nonce_url Fresh-nonce endpoint.
	 * @return array{restUrl: string, restNonceUrl: string}
	 */
	public static function public_tracking_fields( $rest_url, $nonce_url ) {
		return array(
			'restUrl'      => (string) $rest_url,
			'restNonceUrl' => (string) $nonce_url,
		);
	}

	/**
	 * Headers for GET /rwgo/v1/tracking-nonce so a page cache cannot store it.
	 *
	 * @return array<string, string>
	 */
	public static function nonce_response_headers() {
		return array(
			'Cache-Control'                  => 'private, no-store, no-cache, must-revalidate, max-age=0',
			'Pragma'                         => 'no-cache',
			'Expires'                        => 'Wed, 11 Jan 1984 05:00:00 GMT',
			'CDN-Cache-Control'              => 'no-store',
			'Cloudflare-CDN-Cache-Control'   => 'no-store',
			'X-LiteSpeed-Cache-Control'      => 'no-cache',
			'X-Accel-Expires'                => '0',
		);
	}

	/**
	 * @return void
	 */
	public static function send_nonce_nocache() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( self::litespeed_active() && function_exists( 'do_action' ) ) {
			do_action( 'litespeed_control_set_nocache', 'rwgo-tracking-nonce' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed public API.
		}
	}

	/**
	 * Why this save should purge: started, stopped, promoted, page_map, or saved.
	 *
	 * @param array<string, mixed> $before Previous config.
	 * @param array<string, mixed> $after  New config.
	 * @return string
	 */
	public static function change_reason( array $before, array $after ) {
		$before_promoted = isset( $before['promoted_at'] ) ? (string) $before['promoted_at'] : '';
		$after_promoted  = isset( $after['promoted_at'] ) ? (string) $after['promoted_at'] : '';
		if ( '' !== $after_promoted && $after_promoted !== $before_promoted ) {
			return 'promoted';
		}
		if ( ! empty( $after['promoted_from_id'] ) && empty( $before['promoted_from_id'] ) ) {
			return 'promoted';
		}
		$old = isset( $before['status'] ) ? sanitize_key( (string) $before['status'] ) : '';
		$new = isset( $after['status'] ) ? sanitize_key( (string) $after['status'] ) : '';
		if ( 'active' === $new && 'active' !== $old ) {
			return 'started';
		}
		$stopped = array( 'paused', 'ended', 'completed', 'stopped' );
		if ( in_array( $new, $stopped, true ) && ! in_array( $old, $stopped, true ) ) {
			return 'stopped';
		}
		if ( self::page_map_signature( $before ) !== self::page_map_signature( $after ) ) {
			return 'page_map';
		}
		return 'saved';
	}

	/**
	 * Original and variant post IDs referenced by an experiment or site-wide config.
	 *
	 * @param array<string, mixed> $cfg Config.
	 * @return array<int, int>
	 */
	public static function post_ids_for_config( $cfg ) {
		if ( ! is_array( $cfg ) ) {
			return array();
		}
		$ids = array();
		if ( ! empty( $cfg['source_page_id'] ) ) {
			$ids[] = (int) $cfg['source_page_id'];
		}
		if ( ! empty( $cfg['promoted_from_id'] ) ) {
			$ids[] = (int) $cfg['promoted_from_id'];
		}
		if ( ! empty( $cfg['variants'] ) && is_array( $cfg['variants'] ) ) {
			foreach ( $cfg['variants'] as $row ) {
				if ( is_array( $row ) && ! empty( $row['page_id'] ) ) {
					$ids[] = (int) $row['page_id'];
				}
			}
		}
		if ( ! empty( $cfg['page_map'] ) && is_array( $cfg['page_map'] ) ) {
			foreach ( $cfg['page_map'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				if ( ! empty( $row['source_page_id'] ) ) {
					$ids[] = (int) $row['source_page_id'];
				}
				if ( ! empty( $row['variant_page_id'] ) ) {
					$ids[] = (int) $row['variant_page_id'];
				}
			}
		}
		$out = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 && ! in_array( $id, $out, true ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Stored paths on a page map (original and variant).
	 *
	 * @param array<string, mixed> $cfg Config.
	 * @return array<int, string>
	 */
	public static function paths_for_config( $cfg ) {
		if ( ! is_array( $cfg ) || empty( $cfg['page_map'] ) || ! is_array( $cfg['page_map'] ) ) {
			return array();
		}
		$paths = array();
		foreach ( $cfg['page_map'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			foreach ( array( 'source_path', 'variant_path' ) as $key ) {
				if ( empty( $row[ $key ] ) || ! is_string( $row[ $key ] ) ) {
					continue;
				}
				$path = trim( $row[ $key ] );
				if ( '' !== $path && ! in_array( $path, $paths, true ) ) {
					$paths[] = $path;
				}
			}
		}
		return $paths;
	}

	/**
	 * Post IDs and paths that change between two configs.
	 *
	 * @param array<string, mixed> $before Previous config.
	 * @param array<string, mixed> $after  New config.
	 * @return array{reason: string, post_ids: array<int, int>, paths: array<int, string>}
	 */
	public static function targets_for_change( $before, $after ) {
		$before = is_array( $before ) ? $before : array();
		$after  = is_array( $after ) ? $after : array();
		$ids    = array();
		foreach ( array_merge( self::post_ids_for_config( $before ), self::post_ids_for_config( $after ) ) as $id ) {
			$id = (int) $id;
			if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		$paths = array();
		foreach ( array_merge( self::paths_for_config( $before ), self::paths_for_config( $after ) ) as $path ) {
			if ( ! in_array( $path, $paths, true ) ) {
				$paths[] = $path;
			}
		}
		return array(
			'reason'   => self::change_reason( $before, $after ),
			'post_ids' => $ids,
			'paths'    => $paths,
		);
	}

	/**
	 * Purge original and variant URLs after a save, start, stop, promotion, or page-map edit.
	 *
	 * @param array<string, mixed>|mixed $before Previous config.
	 * @param array<string, mixed>|mixed $after  New config.
	 * @param string                     $reason Optional reason override.
	 * @return void
	 */
	public static function purge_experiment_change( $before, $after, $reason = '' ) {
		$targets = self::targets_for_change( $before, $after );
		if ( is_string( $reason ) && '' !== $reason ) {
			$targets['reason'] = $reason;
		}
		$urls = self::urls_for_targets( $targets['post_ids'], $targets['paths'] );
		self::purge_posts_and_urls( $targets['post_ids'], $urls );
		if ( function_exists( 'do_action' ) ) {
			do_action( 'rwgo_optimise_cache_purged', $targets['post_ids'], $urls, $targets['reason'] );
		}
	}

	/**
	 * @param array<int, int> $post_ids Post IDs.
	 * @return void
	 */
	public static function purge_post_ids( array $post_ids ) {
		$ids = array();
		foreach ( $post_ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		self::purge_posts_and_urls( $ids, self::urls_for_targets( $ids, array() ) );
	}

	/**
	 * @param array<int, int>    $post_ids Post IDs.
	 * @param array<int, string> $urls     Absolute URLs.
	 * @return array<int, string> Adapter step names that ran.
	 */
	public static function purge_posts_and_urls( array $post_ids, array $urls ) {
		$plan = self::purge_plan( $post_ids, $urls, self::adapter_availability() );
		return self::execute_plan( $plan );
	}

	/**
	 * Which adapter calls a purge would make. Empty when no plugin is available.
	 *
	 * @param array<int, int>         $post_ids   Post IDs.
	 * @param array<int, string>      $urls       Absolute URLs.
	 * @param array<string, bool>     $available  Adapter flags.
	 * @return array<int, array{0: string, 1: int|string}>
	 */
	public static function purge_plan( array $post_ids, array $urls, array $available ) {
		$actions = array();
		$clean_urls = array();
		foreach ( $urls as $url ) {
			$url = is_string( $url ) ? trim( $url ) : '';
			if ( '' !== $url && ! in_array( $url, $clean_urls, true ) ) {
				$clean_urls[] = $url;
			}
		}
		$clean_ids = array();
		foreach ( $post_ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 && ! in_array( $id, $clean_ids, true ) ) {
				$clean_ids[] = $id;
			}
		}
		foreach ( $clean_urls as $url ) {
			if ( ! empty( $available['litespeed'] ) ) {
				$actions[] = array( 'litespeed_purge_url', $url );
			}
			if ( ! empty( $available['rocket'] ) ) {
				$actions[] = array( 'rocket_clean_files', $url );
			}
			if ( ! empty( $available['w3tc'] ) ) {
				$actions[] = array( 'w3tc_flush_url', $url );
			}
			if ( ! empty( $available['supercache'] ) ) {
				$actions[] = array( 'wpsc_delete_url_cache', $url );
			}
			if ( ! empty( $available['cloudflare'] ) ) {
				$actions[] = array( 'cloudflare_purge_url', $url );
			}
		}
		foreach ( $clean_ids as $id ) {
			if ( ! empty( $available['litespeed'] ) ) {
				$actions[] = array( 'litespeed_purge_post', $id );
			}
			if ( ! empty( $available['rocket'] ) ) {
				$actions[] = array( 'rocket_clean_post', $id );
			}
			if ( ! empty( $available['w3tc'] ) ) {
				$actions[] = array( 'w3tc_flush_post', $id );
			}
			if ( ! empty( $available['supercache'] ) ) {
				$actions[] = array( 'wp_cache_post_change', $id );
			}
			if ( ! empty( $available['cloudflare'] ) ) {
				$actions[] = array( 'cloudflare_purge_post', $id );
			}
			if ( ! empty( $available['elementor'] ) ) {
				$actions[] = array( 'elementor_css', $id );
			}
		}
		return $actions;
	}

	/**
	 * Run a plan. Each step is skipped when its plugin is not actually loaded.
	 *
	 * @param array<int, array{0: string, 1: int|string}> $plan Steps.
	 * @return array<int, string> Step names that ran.
	 */
	public static function execute_plan( array $plan ) {
		$ran = array();
		foreach ( $plan as $step ) {
			if ( ! is_array( $step ) || ! isset( $step[0] ) ) {
				continue;
			}
			$name = (string) $step[0];
			$arg  = $step[1] ?? '';
			$key  = $name . '|' . (string) $arg;
			if ( isset( self::$once[ $key ] ) ) {
				continue;
			}
			if ( ! self::run_adapter_step( $name, $arg ) ) {
				continue;
			}
			self::$once[ $key ] = true;
			$ran[]               = $name;
		}
		return $ran;
	}

	/**
	 * @return array<string, bool>
	 */
	public static function adapter_availability() {
		return array(
			'litespeed'  => self::litespeed_active(),
			'rocket'     => function_exists( 'rocket_clean_files' ) || function_exists( 'rocket_clean_post' ),
			'w3tc'       => function_exists( 'w3tc_flush_url' ) || function_exists( 'w3tc_flush_post' ),
			'supercache' => function_exists( 'wpsc_delete_url_cache' ) || function_exists( 'wp_cache_post_change' ),
			'cloudflare' => self::cloudflare_active(),
			'elementor'  => class_exists( '\Elementor\Core\Files\CSS\Post', false ),
		);
	}

	/**
	 * Human labels for plugins that are loaded right now.
	 *
	 * @return array<int, string>
	 */
	public static function active_adapter_labels() {
		$labels = array(
			'litespeed'  => 'LiteSpeed Cache',
			'rocket'     => 'WP Rocket',
			'w3tc'       => 'W3 Total Cache',
			'supercache' => 'WP Super Cache',
			'cloudflare' => 'Cloudflare',
			'elementor'  => 'Elementor',
		);
		$out = array();
		foreach ( self::adapter_availability() as $key => $on ) {
			if ( $on && isset( $labels[ $key ] ) ) {
				$out[] = $labels[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Page-cache plugins only. Elementor CSS is separate.
	 *
	 * @return bool
	 */
	public static function any_page_cache_active() {
		$available = self::adapter_availability();
		foreach ( array( 'litespeed', 'rocket', 'w3tc', 'supercache', 'cloudflare' ) as $key ) {
			if ( ! empty( $available[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Clear every known original and variant URL.
	 *
	 * @return array{post_ids: array<int, int>, urls: array<int, string>, ran: array<int, string>, available: array<string, bool>}
	 */
	public static function clear_optimise_cache() {
		$ids   = array();
		$paths = array();
		if ( class_exists( 'RWGO_Experiment_Repository', false ) ) {
			foreach ( RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 500 ) ) as $post ) {
				if ( ! is_object( $post ) || empty( $post->ID ) ) {
					continue;
				}
				$cfg = RWGO_Experiment_Repository::get_config( (int) $post->ID );
				foreach ( self::post_ids_for_config( $cfg ) as $id ) {
					if ( ! in_array( $id, $ids, true ) ) {
						$ids[] = $id;
					}
				}
				foreach ( self::paths_for_config( $cfg ) as $path ) {
					if ( ! in_array( $path, $paths, true ) ) {
						$paths[] = $path;
					}
				}
			}
		}
		$site = get_option( self::sitewide_option_name(), array() );
		if ( is_array( $site ) ) {
			foreach ( self::post_ids_for_config( $site ) as $id ) {
				if ( ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}
			foreach ( self::paths_for_config( $site ) as $path ) {
				if ( ! in_array( $path, $paths, true ) ) {
					$paths[] = $path;
				}
			}
		}
		$urls = self::urls_for_targets( $ids, $paths );
		$ran  = self::purge_posts_and_urls( $ids, $urls );
		return array(
			'post_ids'  => $ids,
			'urls'      => $urls,
			'ran'       => $ran,
			'available' => self::adapter_availability(),
		);
	}

	/**
	 * @param mixed $old Previous option.
	 * @param mixed $new New option.
	 * @return void
	 */
	public static function on_sitewide_option_updated( $old, $new ) {
		self::purge_experiment_change( $old, $new );
	}

	/**
	 * @param string $option Option name.
	 * @param mixed  $value  Stored value.
	 * @return void
	 */
	public static function on_sitewide_option_added( $option, $value ) {
		unset( $option );
		self::purge_experiment_change( array(), $value, 'saved' );
	}

	/**
	 * @param int $experiment_post_id Experiment post ID.
	 * @param int $control_page_id    Control page.
	 * @param int $variant_page_id    Variant page.
	 * @return void
	 */
	public static function on_variant_promoted( $experiment_post_id, $control_page_id, $variant_page_id ) {
		unset( $experiment_post_id );
		$after = array(
			'status'           => 'completed',
			'promoted_at'      => gmdate( 'c' ),
			'source_page_id'   => (int) $control_page_id,
			'promoted_from_id' => (int) $variant_page_id,
			'variants'         => array(
				array(
					'variant_id' => 'var_b',
					'page_id'    => (int) $variant_page_id,
				),
			),
		);
		self::purge_experiment_change( array(), $after, 'promoted' );
	}

	/**
	 * Settings button: capability, nonce, then purge.
	 *
	 * @return void
	 */
	public static function handle_clear_cache() {
		if ( ! self::current_user_can_clear() ) {
			wp_die( esc_html__( 'Forbidden.', 'reactwoo-geo-optimise' ) );
		}
		check_admin_referer( self::CLEAR_NONCE );
		$result = self::clear_optimise_cache();
		if ( function_exists( 'set_transient' ) && function_exists( 'get_current_user_id' ) ) {
			set_transient( self::notice_key(), $result, 60 );
		}
		$target = function_exists( 'wp_get_referer' ) ? wp_get_referer() : '';
		if ( ! is_string( $target ) || '' === $target ) {
			$target = admin_url( 'admin.php?page=rwgo-settings' );
		}
		wp_safe_redirect( add_query_arg( 'rwgo_cache_cleared', '1', $target ) );
		exit;
	}

	/**
	 * @return bool
	 */
	public static function current_user_can_clear() {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		if ( class_exists( 'RWGO_Admin', false ) ) {
			return RWGO_Admin::can_manage();
		}
		return current_user_can( 'manage_options' );
	}

	/**
	 * Admin notice after the settings button.
	 *
	 * @return string
	 */
	public static function consume_clear_notice() {
		if ( empty( $_GET['rwgo_cache_cleared'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag after a nonce-checked post.
			return '';
		}
		$result = null;
		if ( function_exists( 'get_transient' ) && function_exists( 'get_current_user_id' ) ) {
			$stored = get_transient( self::notice_key() );
			if ( is_array( $stored ) ) {
				$result = $stored;
			}
			if ( function_exists( 'delete_transient' ) ) {
				delete_transient( self::notice_key() );
			}
		}
		return self::clear_notice_message( is_array( $result ) ? $result : array() );
	}

	/**
	 * @param array<string, mixed> $result Result from clear_optimise_cache().
	 * @return string
	 */
	public static function clear_notice_message( array $result ) {
		$ran       = isset( $result['ran'] ) && is_array( $result['ran'] ) ? $result['ran'] : array();
		$available = isset( $result['available'] ) && is_array( $result['available'] ) ? $result['available'] : array();
		$labels    = array(
			'litespeed_purge_url'    => 'LiteSpeed Cache',
			'litespeed_purge_post'   => 'LiteSpeed Cache',
			'rocket_clean_files'     => 'WP Rocket',
			'rocket_clean_post'      => 'WP Rocket',
			'w3tc_flush_url'         => 'W3 Total Cache',
			'w3tc_flush_post'        => 'W3 Total Cache',
			'wpsc_delete_url_cache'  => 'WP Super Cache',
			'wp_cache_post_change'   => 'WP Super Cache',
			'cloudflare_purge_url'   => 'Cloudflare',
			'cloudflare_purge_post'  => 'Cloudflare',
		);
		$caches = array();
		foreach ( $ran as $step ) {
			$step = (string) $step;
			if ( isset( $labels[ $step ] ) && ! in_array( $labels[ $step ], $caches, true ) ) {
				$caches[] = $labels[ $step ];
			}
		}
		$elementor         = in_array( 'elementor_css', $ran, true );
		$page_cache_loaded = false;
		foreach ( array( 'litespeed', 'rocket', 'w3tc', 'supercache', 'cloudflare' ) as $key ) {
			if ( ! empty( $available[ $key ] ) ) {
				$page_cache_loaded = true;
			}
		}
		if ( empty( $caches ) && ! $elementor ) {
			if ( $page_cache_loaded ) {
				return __( 'No original or variant URLs were found to clear. Public caching was left unchanged.', 'reactwoo-geo-optimise' );
			}
			return __( 'No page-cache plugin is active. Public caching was left unchanged.', 'reactwoo-geo-optimise' );
		}
		if ( empty( $caches ) ) {
			return __( 'Elementor CSS was regenerated for the experiment pages. No page-cache plugin is active, so public caching was left unchanged.', 'reactwoo-geo-optimise' );
		}
		$message = sprintf(
			/* translators: %s: comma-separated cache plugin names. */
			__( 'Cleared the original and variant URLs in %s.', 'reactwoo-geo-optimise' ),
			implode( ', ', $caches )
		);
		if ( $elementor ) {
			$message .= ' ' . __( 'Elementor CSS for those pages was regenerated.', 'reactwoo-geo-optimise' );
		}
		return $message;
	}

	/**
	 * @return bool
	 */
	public static function litespeed_active() {
		return defined( 'LSCWP_V' )
			|| defined( 'LSCWP_DIR' )
			|| class_exists( '\LiteSpeed\Core', false )
			|| class_exists( 'LiteSpeed_Cache', false );
	}

	/**
	 * Official Cloudflare plugin, or Super Page Cache for Cloudflare.
	 *
	 * @return bool
	 */
	public static function cloudflare_active() {
		return defined( 'CLOUDFLARE_PLUGIN_DIR' )
			|| class_exists( '\CF\WordPress\Hooks', false )
			|| class_exists( '\Cloudflare\APO\WordPress\Hooks', false )
			|| class_exists( 'SW_CLOUDFLARE_PAGECACHE', false )
			|| defined( 'SWCFPC_PLUGIN_PATH' );
	}

	/**
	 * @param array<int, int>    $post_ids Post IDs.
	 * @param array<int, string> $paths    Request paths.
	 * @return array<int, string>
	 */
	public static function urls_for_targets( array $post_ids, array $paths ) {
		$urls = array();
		foreach ( $post_ids as $id ) {
			$url = self::permalink( (int) $id );
			if ( '' !== $url && ! in_array( $url, $urls, true ) ) {
				$urls[] = $url;
			}
		}
		foreach ( $paths as $path ) {
			$url = self::url_for_path( (string) $path );
			if ( '' !== $url && ! in_array( $url, $urls, true ) ) {
				$urls[] = $url;
			}
		}
		return $urls;
	}

	/**
	 * @param string          $name Adapter step.
	 * @param int|string|mixed $arg  URL or post ID.
	 * @return bool Whether the plugin call ran.
	 */
	private static function run_adapter_step( $name, $arg ) {
		switch ( $name ) {
			case 'litespeed_purge_url':
				if ( ! self::litespeed_active() || ! function_exists( 'do_action' ) || ! is_string( $arg ) ) {
					return false;
				}
				do_action( 'litespeed_purge_url', $arg ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed public API.
				return true;
			case 'litespeed_purge_post':
				if ( ! self::litespeed_active() || ! function_exists( 'do_action' ) ) {
					return false;
				}
				do_action( 'litespeed_purge_post', (int) $arg ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed public API.
				return true;
			case 'rocket_clean_files':
				if ( ! function_exists( 'rocket_clean_files' ) || ! is_string( $arg ) ) {
					return false;
				}
				rocket_clean_files( $arg );
				return true;
			case 'rocket_clean_post':
				if ( ! function_exists( 'rocket_clean_post' ) ) {
					return false;
				}
				rocket_clean_post( (int) $arg );
				return true;
			case 'w3tc_flush_url':
				if ( ! function_exists( 'w3tc_flush_url' ) || ! is_string( $arg ) ) {
					return false;
				}
				w3tc_flush_url( $arg );
				return true;
			case 'w3tc_flush_post':
				if ( ! function_exists( 'w3tc_flush_post' ) ) {
					return false;
				}
				w3tc_flush_post( (int) $arg );
				return true;
			case 'wpsc_delete_url_cache':
				if ( ! function_exists( 'wpsc_delete_url_cache' ) || ! is_string( $arg ) ) {
					return false;
				}
				wpsc_delete_url_cache( $arg );
				return true;
			case 'wp_cache_post_change':
				if ( ! function_exists( 'wp_cache_post_change' ) ) {
					return false;
				}
				wp_cache_post_change( (int) $arg );
				return true;
			case 'cloudflare_purge_url':
				return self::purge_cloudflare_url( is_string( $arg ) ? $arg : '' );
			case 'cloudflare_purge_post':
				return self::purge_cloudflare_post( (int) $arg );
			case 'elementor_css':
				return self::regenerate_elementor_css( (int) $arg );
			default:
				return false;
		}
	}

	/**
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	private static function purge_cloudflare_url( $url ) {
		if ( '' === $url || ! self::cloudflare_active() || ! function_exists( 'do_action' ) ) {
			return false;
		}
		$ran = false;
		if ( class_exists( 'SW_CLOUDFLARE_PAGECACHE', false ) || defined( 'SWCFPC_PLUGIN_PATH' ) ) {
			do_action( 'swcfpc_purge_cache', array( $url ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Super Page Cache for Cloudflare public API.
			$ran = true;
		}
		return $ran;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function purge_cloudflare_post( $post_id ) {
		if ( $post_id <= 0 || ! self::cloudflare_active() || ! function_exists( 'do_action' ) ) {
			return false;
		}
		$ran = false;
		if ( defined( 'CLOUDFLARE_PLUGIN_DIR' ) || class_exists( '\CF\WordPress\Hooks', false ) || class_exists( '\Cloudflare\APO\WordPress\Hooks', false ) ) {
			do_action( 'cloudflare_purge_by_url', $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Cloudflare plugin public API.
			$ran = true;
		}
		return $ran;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function regenerate_elementor_css( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! class_exists( '\Elementor\Core\Files\CSS\Post', false ) ) {
			return false;
		}
		if ( function_exists( 'get_post_meta' ) ) {
			$mode = get_post_meta( $post_id, '_elementor_edit_mode', true );
			$data = get_post_meta( $post_id, '_elementor_data', true );
			$built = ( 'builder' === $mode ) || ( is_string( $data ) && '' !== $data );
			if ( ! $built ) {
				return false;
			}
		}
		try {
			$file = \Elementor\Core\Files\CSS\Post::create( $post_id );
			if ( is_object( $file ) && method_exists( $file, 'update' ) ) {
				$file->update();
				return true;
			}
		} catch ( \Throwable $e ) {
			unset( $e );
			return false;
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @return string
	 */
	private static function page_map_signature( array $cfg ) {
		$rows = array();
		if ( ! empty( $cfg['page_map'] ) && is_array( $cfg['page_map'] ) ) {
			foreach ( $cfg['page_map'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$rows[] = (int) ( $row['source_page_id'] ?? 0 ) . ':' . (int) ( $row['variant_page_id'] ?? 0 );
			}
		}
		return implode( ',', $rows );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function permalink( $post_id ) {
		if ( $post_id <= 0 || ! function_exists( 'get_permalink' ) ) {
			return '';
		}
		$url = get_permalink( $post_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param string $path Path beginning with a slash, or a stored map path.
	 * @return string
	 */
	private static function url_for_path( $path ) {
		$path = trim( (string) $path );
		if ( '' === $path || ! function_exists( 'home_url' ) ) {
			return '';
		}
		if ( 0 === strpos( $path, 'http://' ) || 0 === strpos( $path, 'https://' ) ) {
			return $path;
		}
		return home_url( '/' . ltrim( $path, '/' ) );
	}

	/**
	 * @return string
	 */
	private static function sitewide_option_name() {
		if ( class_exists( 'RWGO_Sitewide', false ) ) {
			return RWGO_Sitewide::OPTION;
		}
		return 'rwgo_sitewide_experiment';
	}

	/**
	 * @return string
	 */
	private static function notice_key() {
		$uid = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		return 'rwgo_cache_clear_notice_' . $uid;
	}
}
