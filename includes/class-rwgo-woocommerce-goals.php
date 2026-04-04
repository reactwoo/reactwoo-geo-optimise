<?php
/**
 * Server-side WooCommerce goal events (hooks first, not click-only).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fires rwgo_goal_fired for commerce goals when assignments exist in the cookie map.
 */
class RWGO_WooCommerce_Goals {

	/**
	 * Register after WooCommerce is booted (plugins_loaded → woocommerce_init).
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'woocommerce_init', array( __CLASS__, 'init' ) );
	}

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'on_add_to_cart' ), 25, 6 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'on_before_checkout' ), 5 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'on_thankyou' ), 25, 1 );
	}

	/**
	 * @return bool
	 */
	private static function enabled() {
		if ( ! class_exists( 'WooCommerce', false ) ) {
			return false;
		}
		if ( ! class_exists( 'RWGO_Settings', false ) ) {
			return false;
		}
		$base = RWGO_Settings::woocommerce_goal_hooks_enabled();
		/**
		 * Disable WooCommerce server-side goal hooks (testing or custom stack).
		 *
		 * @param bool $enabled Default from settings.
		 */
		return (bool) apply_filters( 'rwgo_woocommerce_goal_hooks_enabled', $base );
	}

	/**
	 * @param string               $cart_item_key Cart key.
	 * @param int                  $product_id    Product or variation ID.
	 * @param int                  $quantity      Qty.
	 * @param int                  $variation_id  Variation ID.
	 * @param array<string, mixed> $variation     Variation attrs.
	 * @param array<string, mixed> $cart_item_data Extra.
	 * @return void
	 */
	public static function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! self::enabled() ) {
			return;
		}
		$page_id = self::resolve_product_page_id( (int) $product_id, (int) $variation_id );
		if ( $page_id <= 0 ) {
			return;
		}
		foreach ( RWGO_Experiment_Repository::get_active_touching_page( $page_id ) as $row ) {
			$post = $row['post'];
			$cfg  = $row['config'];
			if ( RWGO_Goal_Service::is_assignment_only( $cfg ) ) {
				continue;
			}
			if ( ! self::config_has_goal_type( $cfg, 'add_to_cart' ) ) {
				continue;
			}
			$key = isset( $cfg['experiment_key'] ) ? sanitize_key( (string) $cfg['experiment_key'] ) : '';
			if ( '' === $key ) {
				continue;
			}
			$variant_id = self::cookie_variant( $key, $cfg );
			if ( '' === $variant_id ) {
				continue;
			}
			$goal_handler = self::first_goal_handler( $cfg, 'add_to_cart' );
			if ( null === $goal_handler ) {
				continue;
			}
			self::emit(
				(int) $post->ID,
				$cfg,
				$goal_handler['goal'],
				$goal_handler['handler'],
				$variant_id,
				(int) ( $cfg['source_page_id'] ?? 0 ),
				$page_id
			);
		}
	}

	/**
	 * @return void
	 */
	public static function on_before_checkout() {
		if ( ! self::enabled() || ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$map = RWGO_Assignment::get_map();
		if ( empty( $map ) ) {
			return;
		}
		$fired = WC()->session->get( 'rwgo_begin_checkout_fired', array() );
		if ( ! is_array( $fired ) ) {
			$fired = array();
		}
		foreach ( RWGO_Experiment_Repository::query_experiments( array( 'posts_per_page' => 500 ) ) as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$cfg = RWGO_Experiment_Repository::get_config( $post->ID );
			if ( empty( $cfg['status'] ) || 'active' !== $cfg['status'] ) {
				continue;
			}
			if ( RWGO_Goal_Service::is_assignment_only( $cfg ) || ! self::config_has_goal_type( $cfg, 'begin_checkout' ) ) {
				continue;
			}
			$key = isset( $cfg['experiment_key'] ) ? sanitize_key( (string) $cfg['experiment_key'] ) : '';
			if ( '' === $key || empty( $map[ $key ] ) ) {
				continue;
			}
			if ( in_array( $key, $fired, true ) ) {
				continue;
			}
			$variant_id = self::cookie_variant( $key, $cfg );
			if ( '' === $variant_id ) {
				continue;
			}
			$goal_handler = self::first_goal_handler( $cfg, 'begin_checkout' );
			if ( null === $goal_handler ) {
				continue;
			}
			$ctx = (int) ( $cfg['source_page_id'] ?? 0 );
			$vp  = (int) RWGO_Experiment_Service::page_id_for_variant( $cfg, $variant_id );
			self::emit( (int) $post->ID, $cfg, $goal_handler['goal'], $goal_handler['handler'], $variant_id, $ctx, $vp > 0 ? $vp : $ctx );
			$fired[] = $key;
		}
		WC()->session->set( 'rwgo_begin_checkout_fired', $fired );
	}

	/**
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function on_thankyou( $order_id ) {
		if ( ! self::enabled() ) {
			return;
		}
		$order_id = (int) $order_id;
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$done = $order->get_meta( '_rwgo_purchase_goals_fired', true );
		if ( ! is_array( $done ) ) {
			$done = array();
		}
		$map = RWGO_Assignment::get_map();

		$product_ids = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_product_id' ) ) {
				continue;
			}
			$pid = (int) $item->get_product_id();
			if ( method_exists( $item, 'get_variation_id' ) && (int) $item->get_variation_id() ) {
				$pid = (int) wp_get_post_parent_id( (int) $item->get_variation_id() );
			}
			if ( $pid > 0 ) {
				$product_ids[ $pid ] = true;
			}
		}

		foreach ( array_keys( $product_ids ) as $pid ) {
			foreach ( RWGO_Experiment_Repository::get_active_touching_page( (int) $pid ) as $row ) {
				$post = $row['post'];
				$cfg  = $row['config'];
				if ( RWGO_Goal_Service::is_assignment_only( $cfg ) || ! self::config_has_goal_type( $cfg, 'purchase' ) ) {
					continue;
				}
				$key = isset( $cfg['experiment_key'] ) ? sanitize_key( (string) $cfg['experiment_key'] ) : '';
				if ( '' === $key ) {
					continue;
				}
				if ( in_array( $key, $done, true ) ) {
					continue;
				}
				if ( empty( $map[ $key ] ) ) {
					continue;
				}
				$variant_id = self::cookie_variant( $key, $cfg );
				if ( '' === $variant_id ) {
					continue;
				}
				$goal_handler = self::first_goal_handler( $cfg, 'purchase' );
				if ( null === $goal_handler ) {
					continue;
				}
				$ctx = (int) ( $cfg['source_page_id'] ?? 0 );
				self::emit(
					(int) $post->ID,
					$cfg,
					$goal_handler['goal'],
					$goal_handler['handler'],
					$variant_id,
					$ctx,
					(int) $pid,
					array(
						'meta' => array(
							'order_id' => $order_id,
						),
					)
				);
				$done[] = $key;
			}
		}
		$order->update_meta_data( '_rwgo_purchase_goals_fired', $done );
		$order->save();
	}

	/**
	 * @param int $product_id   Added product ID.
	 * @param int $variation_id Variation ID.
	 * @return int Parent product page ID for matching experiments.
	 */
	private static function resolve_product_page_id( $product_id, $variation_id ) {
		$vid = $variation_id > 0 ? $variation_id : $product_id;
		if ( function_exists( 'wc_get_product' ) ) {
			$p = wc_get_product( $vid );
			if ( $p && $p->is_type( 'variation' ) ) {
				return (int) $p->get_parent_id();
			}
		}
		return (int) $product_id;
	}

	/**
	 * @param array<string, mixed> $cfg       Config.
	 * @param string               $goal_type Goal type key.
	 * @return bool
	 */
	private static function config_has_goal_type( array $cfg, $goal_type ) {
		$goal_type = sanitize_key( (string) $goal_type );
		foreach ( isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array() as $g ) {
			if ( ! is_array( $g ) ) {
				continue;
			}
			if ( isset( $g['goal_type'] ) && sanitize_key( (string) $g['goal_type'] ) === $goal_type ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string, mixed> $cfg .
	 * @param string               $goal_type .
	 * @return array{goal: array<string, mixed>, handler: array<string, mixed>}|null
	 */
	private static function first_goal_handler( array $cfg, $goal_type ) {
		$goal_type = sanitize_key( (string) $goal_type );
		foreach ( isset( $cfg['goals'] ) && is_array( $cfg['goals'] ) ? $cfg['goals'] : array() as $g ) {
			if ( ! is_array( $g ) || empty( $g['goal_id'] ) ) {
				continue;
			}
			if ( sanitize_key( (string) ( $g['goal_type'] ?? '' ) ) !== $goal_type ) {
				continue;
			}
			$handlers = isset( $g['handlers'] ) && is_array( $g['handlers'] ) ? $g['handlers'] : array();
			foreach ( $handlers as $h ) {
				if ( is_array( $h ) && ! empty( $h['handler_id'] ) ) {
					return array( 'goal' => $g, 'handler' => $h );
				}
			}
		}
		return null;
	}

	/**
	 * @param string               $experiment_key Experiment key.
	 * @param array<string, mixed> $cfg            Config.
	 * @return string Variant slug or empty.
	 */
	private static function cookie_variant( $experiment_key, array $cfg ) {
		$key = sanitize_key( (string) $experiment_key );
		$map = RWGO_Assignment::get_map();
		if ( empty( $map[ $key ] ) ) {
			return '';
		}
		$v = sanitize_key( (string) $map[ $key ] );
		$allowed = RWGO_Experiment_Service::assignment_variant_slugs( $cfg );
		return in_array( $v, $allowed, true ) ? $v : '';
	}

	/**
	 * @param int                  $experiment_post_id Experiment CPT ID.
	 * @param array<string, mixed> $cfg                Config.
	 * @param array<string, mixed> $goal               Goal row.
	 * @param array<string, mixed> $handler            Handler row.
	 * @param string               $variant_id         Variant slug.
	 * @param int                  $page_context_id    Source / context page.
	 * @param int                  $page_variant_post_id Variant page or product.
	 * @param array<string, mixed> $extra              Merged into payload parts.
	 * @return void
	 */
	private static function emit( $experiment_post_id, array $cfg, array $goal, array $handler, $variant_id, $page_context_id, $page_variant_post_id, array $extra = array() ) {
		if ( ! class_exists( 'RWGO_Event_Payload', false ) ) {
			return;
		}
		$label = '';
		foreach ( isset( $cfg['variants'] ) && is_array( $cfg['variants'] ) ? $cfg['variants'] : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['variant_id'] ) && sanitize_key( (string) $row['variant_id'] ) === sanitize_key( (string) $variant_id ) ) {
				$label = isset( $row['variant_label'] ) ? (string) $row['variant_label'] : '';
				break;
			}
		}
		$parts = array_merge(
			array(
				'experiment_id'          => (int) $experiment_post_id,
				'experiment_key'         => (string) ( $cfg['experiment_key'] ?? '' ),
				'variant_id'             => (string) $variant_id,
				'variant_label'          => $label,
				'goal_id'                => (string) ( $goal['goal_id'] ?? '' ),
				'goal_type'              => (string) ( $goal['goal_type'] ?? '' ),
				'handler_id'             => (string) ( $handler['handler_id'] ?? '' ),
				'page_context_id'        => (int) $page_context_id,
				'page_variant_post_id'   => (int) $page_variant_post_id,
				'element_fingerprint'    => (string) ( $handler['hook_strategy'] ?? 'woocommerce' ),
			),
			$extra
		);
		$payload = RWGO_Event_Payload::normalize_goal_fired( $parts );
		/**
		 * WooCommerce-originated goal payload before storage / analytics bridge.
		 *
		 * @param array<string, mixed> $payload Canonical payload.
		 * @param array<string, mixed> $cfg     Experiment config.
		 */
		$payload = apply_filters( 'rwgo_woocommerce_goal_payload', $payload, $cfg );
		do_action( 'rwgo_goal_fired', $payload );
	}
}
