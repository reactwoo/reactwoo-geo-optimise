<?php
/**
 * Registers builder adapters and resolves the active builder for a post.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registry for Elementor, Gutenberg, and classic content adapters.
 */
class RWGA_Builder_Registry {

	/**
	 * @var array<int, RWGA_Builder_Adapter_Interface>
	 */
	private static $adapters = null;

	/**
	 * Register default adapters once.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( null !== self::$adapters ) {
			return;
		}
		self::$adapters = array();

		$defaults = array(
			new RWGA_Elementor_Adapter(),
			new RWGA_Gutenberg_Adapter(),
			new RWGA_Default_Post_Content_Adapter(),
		);

		foreach ( $defaults as $adapter ) {
			self::register( $adapter );
		}

		/**
		 * Register additional builder adapters (e.g. third-party builders).
		 *
		 * @param RWGA_Builder_Registry $registry Registry instance.
		 */
		do_action( 'rwga_builder_registry_init', new self() );
	}

	/**
	 * @param RWGA_Builder_Adapter_Interface $adapter Adapter instance.
	 * @return void
	 */
	public static function register( RWGA_Builder_Adapter_Interface $adapter ) {
		self::boot();
		self::$adapters[] = $adapter;
	}

	/**
	 * Detect adapter for post (first match wins).
	 *
	 * @param int $post_id Post ID.
	 * @return RWGA_Builder_Adapter_Interface|null
	 */
	public static function resolve( $post_id ) {
		self::boot();
		$post_id = (int) $post_id;
		foreach ( self::$adapters as $adapter ) {
			if ( $adapter->supports( $post_id ) ) {
				return $adapter;
			}
		}
		return null;
	}

	/**
	 * Builder slug or empty.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function detect_builder_name( $post_id ) {
		$adapter = self::resolve( $post_id );
		return $adapter ? $adapter->get_builder_name() : 'classic';
	}

	/**
	 * Normalized page context from the resolved adapter.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function get_page_context( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return array();
		}

		$adapter = self::resolve( $post_id );
		if ( ! $adapter ) {
			$fallback = new RWGA_Default_Post_Content_Adapter();
			$ctx      = $fallback->extract_page_context( $post_id );
		} else {
			$ctx = $adapter->extract_page_context( $post_id );
		}

		/**
		 * Filter normalized builder page context.
		 *
		 * @param array<string, mixed>              $ctx     Context.
		 * @param int                               $post_id Post ID.
		 * @param RWGA_Builder_Adapter_Interface|null $adapter Adapter or null.
		 */
		$ctx = apply_filters( 'rwga_builder_page_context', $ctx, $post_id, $adapter );
		return is_array( $ctx ) ? $ctx : array();
	}

	/**
	 * @return array<int, RWGA_Builder_Adapter_Interface>
	 */
	public static function get_adapters() {
		self::boot();
		return self::$adapters;
	}
}
