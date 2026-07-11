<?php
/**
 * Resolves builder-specific context extractors.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry for semantic builder context extraction.
 */
class RWGA_Context_Extractor_Registry {

	/**
	 * @var array<string, RWGA_Context_Extractor_Interface>|null
	 */
	private static $extractors = null;

	/**
	 * @return void
	 */
	public static function boot() {
		if ( null !== self::$extractors ) {
			return;
		}
		self::$extractors = array();

		$defaults = array(
			new RWGA_Elementor_Context_Extractor(),
			new RWGA_Gutenberg_Context_Extractor(),
			new RWGA_Classic_Context_Extractor(),
		);
		foreach ( $defaults as $extractor ) {
			self::register( $extractor );
		}

		/**
		 * Register additional builder context extractors.
		 *
		 * @param RWGA_Context_Extractor_Registry $registry Registry instance.
		 */
		do_action( 'rwga_context_extractor_registry_init', new self() );
	}

	/**
	 * @param RWGA_Context_Extractor_Interface $extractor Extractor.
	 * @return void
	 */
	public static function register( RWGA_Context_Extractor_Interface $extractor ) {
		self::boot();
		self::$extractors[ $extractor->get_builder_slug() ] = $extractor;
	}

	/**
	 * @param string $builder_slug Builder slug.
	 * @return RWGA_Context_Extractor_Interface|null
	 */
	public static function resolve( $builder_slug ) {
		self::boot();
		$builder_slug = sanitize_key( (string) $builder_slug );
		return isset( self::$extractors[ $builder_slug ] ) ? self::$extractors[ $builder_slug ] : null;
	}

	/**
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $payload Classified builder payload.
	 * @return array<string, mixed>
	 */
	public static function extract( $post_id, array $payload ) {
		$builder   = isset( $payload['builder'] ) ? sanitize_key( (string) $payload['builder'] ) : 'classic';
		$extractor = self::resolve( $builder );
		if ( $extractor ) {
			return $extractor->extract( (int) $post_id, $payload );
		}
		$fallback = new RWGA_Classic_Context_Extractor();
		return $fallback->extract( (int) $post_id, $payload );
	}
}
