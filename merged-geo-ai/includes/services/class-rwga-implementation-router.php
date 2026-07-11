<?php
/**
 * Implementation application routes.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Implementation_Router {
	/**
	 * @param array<int, int> $draft_ids Draft ids.
	 * @param int             $page_id Source page id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function apply_drafts_to_live( array $draft_ids, $page_id ) {
		$page_id = (int) $page_id;
		if ( $page_id <= 0 ) {
			return new WP_Error( 'rwga_no_page', __( 'No page selected for applying drafts.', 'reactwoo-geo-ai' ) );
		}
		$content = self::build_content_from_drafts( $draft_ids );
		if ( '' === trim( $content ) ) {
			return new WP_Error( 'rwga_no_content', __( 'No draft content found to apply.', 'reactwoo-geo-ai' ) );
		}
		$post = get_post( $page_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			return new WP_Error( 'rwga_page_missing', __( 'Page not found.', 'reactwoo-geo-ai' ) );
		}
		update_post_meta( $page_id, '_rwga_backup_content', (string) $post->post_content );
		$ok = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => $content,
			),
			true
		);
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return array( 'page_id' => $page_id );
	}

	/**
	 * @param array<int, int> $draft_ids Draft ids.
	 * @param int             $page_id Source page id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function create_variant_from_drafts( array $draft_ids, $page_id ) {
		$page_id = (int) $page_id;
		$post    = get_post( $page_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			return new WP_Error( 'rwga_page_missing', __( 'Page not found for variant.', 'reactwoo-geo-ai' ) );
		}
		$content = self::build_content_from_drafts( $draft_ids );
		$new_id  = wp_insert_post(
			array(
				'post_type'    => $post->post_type,
				'post_status'  => 'draft',
				'post_title'   => $post->post_title . ' (Variant)',
				'post_content' => '' !== trim( $content ) ? $content : (string) $post->post_content,
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}
		return array( 'variant_page_id' => (int) $new_id );
	}

	/**
	 * @param int                  $variant_page_id Variant id.
	 * @param array<string, mixed> $context Context.
	 * @return string|\WP_Error
	 */
	/**
	 * Hand off an intelligence optimisation recommendation to Geo Optimise Create Test.
	 *
	 * @param array<string, mixed> $run_or_context Cloud run row or handoff context.
	 * @return string|\WP_Error
	 */
	public static function send_intelligence_to_geo_optimise( array $run_or_context ) {
		if ( ! class_exists( 'RWGA_Intelligence_Optimise_Handoff', false ) ) {
			return new WP_Error( 'rwga_handoff_missing', __( 'Intelligence optimise handoff is unavailable.', 'reactwoo-geo-ai' ) );
		}
		if ( isset( $run_or_context['workflow_key'] ) && ( isset( $run_or_context['result'] ) || isset( $run_or_context['run_id'] ) ) ) {
			return RWGA_Intelligence_Optimise_Handoff::build_from_cloud_run( $run_or_context );
		}
		return RWGA_Intelligence_Optimise_Handoff::build_create_test_url( $run_or_context );
	}

	/**
	 * @param int                  $variant_page_id Variant id.
	 * @param array<string, mixed> $context Context.
	 * @return string|\WP_Error
	 */
	public static function send_variant_to_geo_optimise( $variant_page_id, array $context = array() ) {
		$variant_page_id = (int) $variant_page_id;
		if ( $variant_page_id <= 0 ) {
			return new WP_Error( 'rwga_no_variant', __( 'No variant page available for Geo Optimise handoff.', 'reactwoo-geo-ai' ) );
		}
		$base = admin_url( 'admin.php?page=rwgo-dashboard' );
		$args = array(
			'rwgc_handoff'         => 1,
			'rwgc_from'            => 'geo_ai',
			'rwgc_launcher'        => 'experiment',
			'rwgc_variant_page_id' => $variant_page_id,
		);
		if ( ! empty( $context['analysis_run_id'] ) ) {
			$args['analysis_run'] = (int) $context['analysis_run_id'];
		}
		return add_query_arg( $args, $base );
	}

	/**
	 * @param array<int, int> $draft_ids Draft ids.
	 * @return string
	 */
	private static function build_content_from_drafts( array $draft_ids ) {
		$blocks = array();
		foreach ( $draft_ids as $id ) {
			$row = class_exists( 'RWGA_DB_Implementation_Drafts', false ) ? RWGA_DB_Implementation_Drafts::get( (int) $id ) : null;
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( ! empty( $row['report_html'] ) ) {
				$blocks[] = wp_kses_post( (string) $row['report_html'] );
				continue;
			}
			$payload = isset( $row['draft_payload'] ) ? json_decode( (string) $row['draft_payload'], true ) : array();
			if ( is_array( $payload ) ) {
				$pieces = array();
				foreach ( $payload as $k => $v ) {
					$pieces[] = '<p><strong>' . esc_html( (string) $k ) . ':</strong> ' . esc_html( is_scalar( $v ) ? (string) $v : wp_json_encode( $v ) ) . '</p>';
				}
				if ( ! empty( $pieces ) ) {
					$blocks[] = implode( "\n", $pieces );
				}
			}
		}
		return implode( "\n\n", $blocks );
	}
}

