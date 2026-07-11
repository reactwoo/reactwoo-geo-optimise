<?php
/**
 * Create or replace Elementor pages from intent-level blueprints (with measurement keys).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * High-level blueprint → Elementor page writer.
 */
class RWGO_Blueprint_Page_Writer {

	/**
	 * Apply a page blueprint to an existing post (replaces `_elementor_data`).
	 *
	 * @param int                      $post_id   Target post.
	 * @param RWGA_Page_Blueprint|null $blueprint Blueprint (default: lead-gen landing).
	 * @param array<string, mixed>     $options   mode, content, title.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function apply_to_post( $post_id, $blueprint = null, array $options = array() ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return new WP_Error( 'rwgo_bp_bad_post', __( 'Invalid page for blueprint write.', 'reactwoo-geo-optimise' ) );
		}
		if ( ! self::ensure_builder() ) {
			return new WP_Error( 'rwgo_bp_no_builder', __( 'Elementor blueprint builder is unavailable.', 'reactwoo-geo-optimise' ) );
		}

		if ( ! ( $blueprint instanceof RWGA_Page_Blueprint ) ) {
			$blueprint = RWGA_Page_Blueprint::lead_generation_landing();
		}

		$built = RWGA_Elementor_Blueprint_Builder::build( $blueprint, $options );
		$tree  = isset( $built['tree'] ) && is_array( $built['tree'] ) ? $built['tree'] : array();
		if ( empty( $tree ) ) {
			return new WP_Error( 'rwgo_bp_empty', __( 'Blueprint produced an empty Elementor document.', 'reactwoo-geo-optimise' ) );
		}

		$saved = RWGA_Elementor_Document_Writer::write_document( $post_id, $tree );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$tracked = isset( $built['tracked_elements'] ) && is_array( $built['tracked_elements'] )
			? $built['tracked_elements']
			: array();

		$result = array(
			'post_id'          => $post_id,
			'mode'             => isset( $built['mode'] ) ? (string) $built['mode'] : 'v3',
			'sections'         => count( $tree ),
			'tracked_elements' => $tracked,
			'goals_stamped'    => count( $tracked ),
			'edit_url'         => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
		);

		/**
		 * After a blueprint was written onto a page.
		 *
		 * @param array<string, mixed> $result    Result.
		 * @param RWGA_Page_Blueprint  $blueprint Blueprint.
		 * @param array<string, mixed> $options   Options.
		 */
		do_action( 'rwgo_blueprint_page_written', $result, $blueprint, $options );

		return $result;
	}

	/**
	 * Create a draft page from the lead-generation blueprint (or provided blueprint).
	 *
	 * @param array<string, mixed>     $options   mode, content, post_title, post_status.
	 * @param RWGA_Page_Blueprint|null $blueprint Blueprint.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function create_draft_page( array $options = array(), $blueprint = null ) {
		$title  = isset( $options['post_title'] ) ? sanitize_text_field( (string) $options['post_title'] ) : '';
		$status = isset( $options['post_status'] ) ? sanitize_key( (string) $options['post_status'] ) : 'draft';
		if ( ! in_array( $status, array( 'draft', 'pending', 'private' ), true ) ) {
			$status = 'draft';
		}
		if ( '' === $title ) {
			$title = __( 'Geo Optimise blueprint page', 'reactwoo-geo-optimise' );
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => $status,
				'post_type'   => 'page',
				'post_content'=> '',
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		if ( ! $post_id ) {
			return new WP_Error( 'rwgo_bp_insert', __( 'Could not create blueprint page.', 'reactwoo-geo-optimise' ) );
		}

		$result = self::apply_to_post( (int) $post_id, $blueprint, $options );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['created'] = true;
		return $result;
	}

	/**
	 * @return bool
	 */
	private static function ensure_builder() {
		if ( class_exists( 'RWGA_Elementor_Blueprint_Builder', false ) && class_exists( 'RWGA_Elementor_Document_Writer', false ) ) {
			return true;
		}
		if ( defined( 'RWGA_PATH' ) ) {
			$files = array(
				RWGA_PATH . 'includes/builders/class-rwga-widget-blueprint.php',
				RWGA_PATH . 'includes/builders/class-rwga-section-blueprint.php',
				RWGA_PATH . 'includes/builders/class-rwga-page-blueprint.php',
				RWGA_PATH . 'includes/builders/elementor/class-rwga-elementor-node-version.php',
				RWGA_PATH . 'includes/builders/elementor/class-rwga-elementor-document-writer.php',
				RWGA_PATH . 'includes/builders/elementor/class-rwga-elementor-blueprint-builder.php',
			);
			foreach ( $files as $file ) {
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		}
		return class_exists( 'RWGA_Elementor_Blueprint_Builder', false )
			&& class_exists( 'RWGA_Elementor_Document_Writer', false )
			&& class_exists( 'RWGA_Page_Blueprint', false );
	}
}
