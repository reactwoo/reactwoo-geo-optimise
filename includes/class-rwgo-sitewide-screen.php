<?php
/**
 * Site-wide test screen: grouped editor, searchable selectors, and the save payload.
 *
 * The stored option stays the array written by the previous form. This class
 * only changes how that array is edited.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for the site-wide experiment.
 */
class RWGO_Sitewide_Screen {

	const SEARCH_ACTION = 'rwgo_sitewide_search';

	const DUPLICATE_ACTION = 'rwgo_sitewide_duplicate';

	const NONCE_SEARCH = 'rwgo_sitewide_search';

	const NONCE_DUPLICATE = 'rwgo_sitewide_duplicate';

	/**
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( ! self::is_screen( $hook ) ) {
			return;
		}
		wp_enqueue_style(
			'rwgo-sitewide-screen',
			RWGO_URL . 'admin/css/rwgo-sitewide-screen.css',
			array(),
			RWGO_VERSION
		);
		wp_enqueue_script(
			'rwgo-sitewide-screen',
			RWGO_URL . 'admin/js/rwgo-sitewide-screen.js',
			array(),
			RWGO_VERSION,
			true
		);
		wp_localize_script(
			'rwgo-sitewide-screen',
			'rwgoSitewideScreen',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'searchNonce'    => wp_create_nonce( self::NONCE_SEARCH ),
				'duplicateNonce' => wp_create_nonce( self::NONCE_DUPLICATE ),
				'searchAction'   => self::SEARCH_ACTION,
				'duplicateAction'=> self::DUPLICATE_ACTION,
				'i18n'           => array(
					'searchPages'      => __( 'Search pages', 'reactwoo-geo-optimise' ),
					'searchVariants'   => __( 'Search variants', 'reactwoo-geo-optimise' ),
					'searchTemplates'  => __( 'Search templates', 'reactwoo-geo-optimise' ),
					'noResults'        => __( 'No matches.', 'reactwoo-geo-optimise' ),
					'duplicate'        => __( 'Duplicate this page as variant', 'reactwoo-geo-optimise' ),
					'duplicating'      => __( 'Duplicating...', 'reactwoo-geo-optimise' ),
					'duplicateFailed'  => __( 'The variant page was not created.', 'reactwoo-geo-optimise' ),
					'removeMatch'      => __( 'Remove page match', 'reactwoo-geo-optimise' ),
					'removePair'       => __( 'Remove match', 'reactwoo-geo-optimise' ),
					'preview'          => __( 'Preview variant', 'reactwoo-geo-optimise' ),
					'selectPage'       => __( 'Search for a page', 'reactwoo-geo-optimise' ),
					'selectVariant'    => __( 'Select a variant', 'reactwoo-geo-optimise' ),
					'selectTemplate'   => __( 'Select a template', 'reactwoo-geo-optimise' ),
					'controlPercent'   => __( 'Control %d%%', 'reactwoo-geo-optimise' ),
					'variantPercent'   => __( 'Variant B %d%%', 'reactwoo-geo-optimise' ),
					'draftWarning'     => __( '%1$s is still a draft. Visitors in variant B will see the original %2$s until you publish it.', 'reactwoo-geo-optimise' ),
					'unpublishedWarning' => __( '%1$s is not published. Visitors in variant B will see the original %2$s until you publish it.', 'reactwoo-geo-optimise' ),
					'templateDraft'    => __( '%s is still a draft. Visitors keep the source template until you publish it.', 'reactwoo-geo-optimise' ),
					'removeChip'       => __( 'Remove %s', 'reactwoo-geo-optimise' ),
					'loading'          => __( 'Searching...', 'reactwoo-geo-optimise' ),
					'locationsFull'    => __( 'Header and footer are already listed.', 'reactwoo-geo-optimise' ),
					'active'           => __( 'Active', 'reactwoo-geo-optimise' ),
					'paused'           => __( 'Paused', 'reactwoo-geo-optimise' ),
					'ended'            => __( 'Ended', 'reactwoo-geo-optimise' ),
				),
			)
		);
	}

	/**
	 * @param string $hook Hook suffix.
	 * @return bool
	 */
	public static function is_screen( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return 'rwgo-sitewide' === $page || false !== strpos( (string) $hook, 'rwgo-sitewide' );
	}

	/**
	 * Capability first, then nonce. Empty string means the search may run.
	 *
	 * @param bool $can_manage  Current user may manage Geo Optimise.
	 * @param bool $nonce_valid Request nonce matches the search action.
	 * @return string forbidden|invalid_nonce|empty
	 */
	public static function search_denial( $can_manage, $nonce_valid ) {
		if ( ! $can_manage ) {
			return 'forbidden';
		}
		if ( ! $nonce_valid ) {
			return 'invalid_nonce';
		}
		return '';
	}

	/**
	 * WP_Query arguments for one selector. Unknown kinds return an empty array.
	 *
	 * Numeric text is a title or slug search. It is not treated as a post ID.
	 *
	 * @param string $kind      page|exclude|goal|variant|header|footer|product|elementor.
	 * @param string $term      Search text.
	 * @param int    $source_id Original page, for the variant list.
	 * @return array<string, mixed>
	 */
	public static function search_query_args( $kind, $term, $source_id = 0 ) {
		$kind = sanitize_key( (string) $kind );
		$term = trim( (string) $term );
		if ( strlen( $term ) > 80 ) {
			$term = substr( $term, 0, 80 );
		}
		$args = array(
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => 20,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);
		if ( '' !== $term ) {
			$args['s'] = $term;
		}
		if ( in_array( $kind, array( 'page', 'exclude', 'goal' ), true ) ) {
			$args['post_type'] = 'page';
			return $args;
		}
		if ( 'variant' === $kind ) {
			$args['post_type']  = 'page';
			$args['meta_key']   = class_exists( 'RWGO_Sitewide', false ) ? RWGO_Sitewide::VARIANT_META : '_rwgo_sitewide_variant_of'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = (string) (int) $source_id; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			return $args;
		}
		if ( ! in_array( $kind, array( 'elementor', 'header', 'footer', 'product' ), true ) ) {
			return array();
		}
		$args['post_type'] = 'elementor_library';
		if ( 'header' === $kind || 'footer' === $kind ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_elementor_template_type',
					'value' => $kind,
				),
			);
		}
		if ( 'product' === $kind ) {
			$types = class_exists( 'RWGO_Theme_Context', false )
				? RWGO_Theme_Context::single_template_types()
				: array( 'single', 'single-post', 'single-page', 'product', 'single-product' );
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => '_elementor_template_type',
					'value'   => $types,
					'compare' => 'IN',
				),
			);
		}
		return $args;
	}

	/**
	 * @return void
	 */
	public static function ajax_search() {
		$can   = class_exists( 'RWGO_Admin', false ) && RWGO_Admin::can_manage();
		$nonce = isset( $_REQUEST['nonce'] ) && function_exists( 'wp_verify_nonce' ) && wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_REQUEST['nonce'] ) ), self::NONCE_SEARCH ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code  = self::search_denial( $can, (bool) $nonce );
		if ( '' !== $code ) {
			self::send_denial( $code );
		}
		$kind   = isset( $_REQUEST['kind'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['kind'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$term   = isset( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$source = isset( $_REQUEST['source_id'] ) ? (int) wp_unslash( $_REQUEST['source_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args   = self::search_query_args( $kind, $term, $source );
		if ( array() === $args ) {
			wp_send_json_success( array( 'items' => array() ) );
		}
		wp_send_json_success( array( 'items' => self::query_choices( $args, $term ) ) );
	}

	/**
	 * @return void
	 */
	public static function ajax_duplicate() {
		$can   = class_exists( 'RWGO_Admin', false ) && RWGO_Admin::can_manage();
		$nonce = isset( $_REQUEST['nonce'] ) && function_exists( 'wp_verify_nonce' ) && wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_REQUEST['nonce'] ) ), self::NONCE_DUPLICATE );
		$code  = self::search_denial( $can, (bool) $nonce );
		if ( '' !== $code ) {
			self::send_denial( $code );
		}
		$source = isset( $_POST['source_id'] ) ? (int) wp_unslash( $_POST['source_id'] ) : 0;
		if ( $source <= 0 || ! class_exists( 'RWGO_Sitewide', false ) ) {
			wp_send_json_error(
				array(
					'code'    => 'rwgo_dup_missing',
					'message' => __( 'Choose an original page first.', 'reactwoo-geo-optimise' ),
				),
				400
			);
		}
		$new_id = RWGO_Sitewide::create_variant_for_admin( $source );
		if ( is_wp_error( $new_id ) || (int) $new_id <= 0 ) {
			$message = is_wp_error( $new_id ) ? $new_id->get_error_message() : __( 'The variant page was not created.', 'reactwoo-geo-optimise' );
			wp_send_json_error(
				array(
					'code'    => is_wp_error( $new_id ) ? $new_id->get_error_code() : 'rwgo_dup_missing',
					'message' => $message,
				),
				500
			);
		}
		$item = self::choice_from_id( (int) $new_id );
		wp_send_json_success( array( 'item' => $item ) );
	}

	/**
	 * @param string $code forbidden|invalid_nonce.
	 * @return void
	 */
	private static function send_denial( $code ) {
		$status  = 'forbidden' === $code ? 403 : 401;
		$message = 'forbidden' === $code
			? __( 'You cannot edit this test.', 'reactwoo-geo-optimise' )
			: __( 'The request could not be verified. Reload the screen and try again.', 'reactwoo-geo-optimise' );
		wp_send_json_error(
			array(
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * @param array<string, mixed> $args Query args from search_query_args().
	 * @param string               $term Search text, used for an extra slug lookup.
	 * @return array<int, array<string, mixed>>
	 */
	public static function query_choices( array $args, $term = '' ) {
		$items = array();
		$seen  = array();
		if ( class_exists( 'WP_Query' ) ) {
			$query = new WP_Query( $args );
			$posts = isset( $query->posts ) && is_array( $query->posts ) ? $query->posts : array();
			foreach ( $posts as $post ) {
				$choice = self::choice_from_post( $post );
				if ( null === $choice || isset( $seen[ $choice['id'] ] ) ) {
					continue;
				}
				$seen[ $choice['id'] ] = true;
				$items[]               = $choice;
			}
		}
		$slug = function_exists( 'sanitize_title' ) ? sanitize_title( (string) $term ) : '';
		if ( '' === $slug || ! function_exists( 'get_posts' ) ) {
			return $items;
		}
		$slug_args = $args;
		unset( $slug_args['s'] );
		$slug_args['name']           = $slug;
		$slug_args['posts_per_page'] = 5;
		$extra                       = get_posts( $slug_args );
		if ( ! is_array( $extra ) ) {
			return $items;
		}
		foreach ( $extra as $post ) {
			$choice = self::choice_from_post( $post );
			if ( null === $choice || isset( $seen[ $choice['id'] ] ) ) {
				continue;
			}
			$seen[ $choice['id'] ] = true;
			array_unshift( $items, $choice );
		}
		return $items;
	}

	/**
	 * Fields posted by the grouped screen.
	 *
	 * @param array<string, mixed> $post Unslashed request body.
	 * @return array<string, mixed>
	 */
	public static function payload_from_post( array $post ) {
		$status = isset( $post['rwgo_sw_status'] ) ? sanitize_key( (string) $post['rwgo_sw_status'] ) : 'paused';
		if ( ! in_array( $status, array( 'active', 'paused', 'ended' ), true ) ) {
			$status = 'paused';
		}
		$key = isset( $post['rwgo_sw_key'] ) ? sanitize_key( (string) $post['rwgo_sw_key'] ) : 'rwgo_sitewide';
		$mode = isset( $post['rwgo_sw_mapping'] ) ? sanitize_key( (string) $post['rwgo_sw_mapping'] ) : 'explicit';
		if ( ! in_array( $mode, array( 'explicit', 'all_published' ), true ) ) {
			$mode = 'explicit';
		}
		$future = isset( $post['rwgo_sw_future'] ) ? sanitize_key( (string) $post['rwgo_sw_future'] ) : 'skip';
		if ( ! in_array( $future, array( 'skip', 'include' ), true ) ) {
			$future = 'skip';
		}
		$weight = isset( $post['rwgo_sw_weight'] ) ? (float) $post['rwgo_sw_weight'] : 50;
		$matches = array();
		if ( isset( $post['rwgo_sw_match'] ) && is_array( $post['rwgo_sw_match'] ) ) {
			foreach ( $post['rwgo_sw_match'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$matches[] = array(
					'source_page_id'  => isset( $row['source'] ) ? (int) $row['source'] : 0,
					'variant_page_id' => isset( $row['variant'] ) ? (int) $row['variant'] : 0,
				);
			}
		}
		$products = array();
		if ( isset( $post['rwgo_sw_product'] ) && is_array( $post['rwgo_sw_product'] ) ) {
			foreach ( $post['rwgo_sw_product'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$products[] = array(
					'source_template_id'  => isset( $row['source'] ) ? (int) $row['source'] : 0,
					'variant_template_id' => isset( $row['variant'] ) ? (int) $row['variant'] : 0,
				);
			}
		}
		$goals = $post['rwgo_sw_goal_ids'] ?? '';
		if ( '' === $goals || array() === $goals ) {
			$goals = $post['rwgo_sw_goals'] ?? '';
		}
		return array(
			'status'                 => $status,
			'experiment_key'         => '' !== $key ? $key : 'rwgo_sitewide',
			'control_weight_percent' => max( 0, min( 100, $weight ) ),
			'cookie_days'            => isset( $post['rwgo_sw_days'] ) ? (int) $post['rwgo_sw_days'] : 90,
			'mapping_mode'           => $mode,
			'exclude_system_pages'   => ! empty( $post['rwgo_sw_exclude_system'] ),
			'exclude_page_ids'       => self::id_list( $post['rwgo_sw_exclude_ids'] ?? '' ),
			'future_pages'           => $future,
			'page_matches'           => $matches,
			'map_merge'              => ! empty( $post['rwgo_sw_map_merge'] ),
			'unmap_source_ids'       => self::id_list( $post['rwgo_sw_unmap'] ?? '' ),
			'header_control'         => isset( $post['rwgo_sw_header_a'] ) ? (int) $post['rwgo_sw_header_a'] : 0,
			'header_variant'         => isset( $post['rwgo_sw_header_b'] ) ? (int) $post['rwgo_sw_header_b'] : 0,
			'footer_control'         => isset( $post['rwgo_sw_footer_a'] ) ? (int) $post['rwgo_sw_footer_a'] : 0,
			'footer_variant'         => isset( $post['rwgo_sw_footer_b'] ) ? (int) $post['rwgo_sw_footer_b'] : 0,
			'product_templates'      => $products,
			'goal_page_ids'          => self::id_list( $goals ),
			'track_purchase'         => ! empty( $post['rwgo_sw_purchase'] ),
		);
	}

	/**
	 * Stored site-wide option built from the grouped screen.
	 *
	 * Paths are filled when $path_resolver is provided. The option keys match
	 * the previous textarea form.
	 *
	 * @param array<string, mixed> $payload        Canonical UI payload.
	 * @param array<string, mixed> $old            Previous stored config.
	 * @param callable|null        $path_resolver  fn( int $post_id ): string.
	 * @return array<string, mixed>
	 */
	public static function stored_config_from_payload( array $payload, array $old, $path_resolver = null ) {
		$rows = array();
		$list = isset( $payload['page_matches'] ) && is_array( $payload['page_matches'] ) ? $payload['page_matches'] : array();
		foreach ( $list as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source  = isset( $row['source_page_id'] ) ? (int) $row['source_page_id'] : 0;
			$variant = isset( $row['variant_page_id'] ) ? (int) $row['variant_page_id'] : 0;
			if ( $source <= 0 || $variant <= 0 ) {
				continue;
			}
			$source_path  = '';
			$variant_path = '';
			if ( is_callable( $path_resolver ) ) {
				$source_path  = (string) call_user_func( $path_resolver, $source );
				$variant_path = (string) call_user_func( $path_resolver, $variant );
			}
			$rows[] = array(
				'source_page_id'  => $source,
				'variant_page_id' => $variant,
				'source_path'     => $source_path,
				'variant_path'    => $variant_path,
			);
		}
		if ( ! empty( $payload['map_merge'] ) && class_exists( 'RWGO_Sitewide', false ) ) {
			$base = isset( $old['page_map'] ) && is_array( $old['page_map'] ) ? $old['page_map'] : array();
			$map  = RWGO_Sitewide::append_map_rows( $base, $rows );
			$map  = RWGO_Sitewide::drop_map_sources( $map, isset( $payload['unmap_source_ids'] ) && is_array( $payload['unmap_source_ids'] ) ? $payload['unmap_source_ids'] : array() );
		} else {
			$map = $rows;
		}
		$mode = isset( $payload['mapping_mode'] ) ? (string) $payload['mapping_mode'] : 'explicit';
		$baseline = isset( $old['coverage_baseline_gmt'] ) ? (int) $old['coverage_baseline_gmt'] : 0;
		if ( 'all_published' === $mode && $baseline <= 0 ) {
			$baseline = time();
		}
		$weight = isset( $payload['control_weight_percent'] ) ? (float) $payload['control_weight_percent'] : 50;
		$weight = max( 0, min( 100, $weight ) ) / 100;
		$days   = isset( $payload['cookie_days'] ) ? (int) $payload['cookie_days'] : 90;
		$theme  = array(
			'control' => array(
				'header' => max( 0, (int) ( $payload['header_control'] ?? 0 ) ),
				'footer' => max( 0, (int) ( $payload['footer_control'] ?? 0 ) ),
			),
			'variant' => array(
				'header' => max( 0, (int) ( $payload['header_variant'] ?? 0 ) ),
				'footer' => max( 0, (int) ( $payload['footer_variant'] ?? 0 ) ),
			),
		);
		if ( class_exists( 'RWGO_Theme_Context', false ) ) {
			$theme = RWGO_Theme_Context::normalize( $theme );
		}
		$template_rows = array();
		$posted_products = isset( $payload['product_templates'] ) && is_array( $payload['product_templates'] ) ? $payload['product_templates'] : array();
		foreach ( $posted_products as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source  = isset( $row['source_template_id'] ) ? (int) $row['source_template_id'] : 0;
			$variant = isset( $row['variant_template_id'] ) ? (int) $row['variant_template_id'] : 0;
			if ( $source <= 0 || $variant <= 0 ) {
				continue;
			}
			$template_rows[] = array(
				'location'            => 'single',
				'source_template_id'  => $source,
				'variant_template_id' => $variant,
			);
		}
		$key = isset( $payload['experiment_key'] ) ? sanitize_key( (string) $payload['experiment_key'] ) : 'rwgo_sitewide';
		return array(
			'experiment_key'        => '' !== $key ? $key : 'rwgo_sitewide',
			'status'                => isset( $payload['status'] ) ? (string) $payload['status'] : 'paused',
			'test_type'             => 'sitewide',
			'scope'                 => 'site',
			'cookie_days'           => max( 1, $days ),
			'control_weight'        => $weight,
			'page_map'              => $map,
			'goal_page_ids'         => isset( $payload['goal_page_ids'] ) && is_array( $payload['goal_page_ids'] ) ? array_values( array_map( 'intval', $payload['goal_page_ids'] ) ) : array(),
			'track_purchase'        => ! empty( $payload['track_purchase'] ),
			'source_page_id'        => isset( $map[0]['source_page_id'] ) ? (int) $map[0]['source_page_id'] : 0,
			'mapping_mode'          => $mode,
			'exclude_system_pages'  => ! empty( $payload['exclude_system_pages'] ),
			'exclude_page_ids'      => isset( $payload['exclude_page_ids'] ) && is_array( $payload['exclude_page_ids'] ) ? array_values( array_map( 'intval', $payload['exclude_page_ids'] ) ) : array(),
			'future_pages'          => isset( $payload['future_pages'] ) ? (string) $payload['future_pages'] : 'skip',
			'coverage_baseline_gmt' => $baseline,
			'theme_context'         => $theme,
			'template_map'          => $template_rows,
			'variants'              => array(
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
			'goals'                 => array(
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
			'primary_goal_id'       => 'sitewide_destination',
		);
	}

	/**
	 * Half-filled rows are rejected before save so a blank selector is not stored as an ID.
	 *
	 * @param array<string, mixed> $payload UI payload.
	 * @return array<int, array<string, string>>
	 */
	public static function incomplete_row_issues( array $payload ) {
		$issues = array();
		$rows   = isset( $payload['page_matches'] ) && is_array( $payload['page_matches'] ) ? $payload['page_matches'] : array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source  = isset( $row['source_page_id'] ) ? (int) $row['source_page_id'] : 0;
			$variant = isset( $row['variant_page_id'] ) ? (int) $row['variant_page_id'] : 0;
			if ( ( $source > 0 && $variant <= 0 ) || ( $variant > 0 && $source <= 0 ) ) {
				$issues[] = array(
					'code'    => 'rwgo_incomplete_match',
					'message' => __( 'Choose both pages in each page match, or remove the row.', 'reactwoo-geo-optimise' ),
				);
				break;
			}
		}
		$products = isset( $payload['product_templates'] ) && is_array( $payload['product_templates'] ) ? $payload['product_templates'] : array();
		foreach ( $products as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source  = isset( $row['source_template_id'] ) ? (int) $row['source_template_id'] : 0;
			$variant = isset( $row['variant_template_id'] ) ? (int) $row['variant_template_id'] : 0;
			if ( ( $source > 0 && $variant <= 0 ) || ( $variant > 0 && $source <= 0 ) ) {
				$issues[] = array(
					'code'    => 'rwgo_incomplete_template',
					'message' => __( 'Choose both templates in each product match, or remove the row.', 'reactwoo-geo-optimise' ),
				);
				break;
			}
		}
		return $issues;
	}

	/**
	 * Visitor-facing warning for one mapped variant that is not published.
	 *
	 * @param string $variant_title Variant title.
	 * @param string $source_title  Original title.
	 * @param string $status        Variant post status.
	 * @return string
	 */
	public static function unpublished_variant_warning( $variant_title, $source_title, $status ) {
		$status = sanitize_key( (string) $status );
		if ( '' === $status || 'publish' === $status ) {
			return '';
		}
		if ( 'draft' === $status ) {
			return sprintf(
				/* translators: 1: variant page title, 2: original page title */
				__( '%1$s is still a draft. Visitors in variant B will see the original %2$s until you publish it.', 'reactwoo-geo-optimise' ),
				$variant_title,
				$source_title
			);
		}
		return sprintf(
			/* translators: 1: variant page title, 2: original page title */
			__( '%1$s is not published. Visitors in variant B will see the original %2$s until you publish it.', 'reactwoo-geo-optimise' ),
			$variant_title,
			$source_title
		);
	}

	/**
	 * @param string $audit_html    Existing audit notice markup.
	 * @param string $rejected_html Save-rejection notice markup.
	 * @return void
	 */
	public static function render( $audit_html = '', $rejected_html = '' ) {
		$cfg = class_exists( 'RWGO_Sitewide', false ) ? RWGO_Sitewide::get_config() : array();
		if ( ! is_array( $cfg ) ) {
			$cfg = array();
		}
		$view                  = self::view_model( $cfg );
		$view['audit_html']    = (string) $audit_html;
		$view['rejected_html'] = (string) $rejected_html;
		$view['notices']       = self::admin_notices();
		$view['icons']         = array(
			'chevron' => self::icon( 'chevron' ),
			'arrow'   => self::icon( 'arrow' ),
			'eye'     => self::icon( 'eye' ),
			'trash'   => self::icon( 'trash' ),
			'info'    => self::icon( 'info' ),
			'warning' => self::icon( 'warning' ),
			'search'  => self::icon( 'search' ),
			'plus'    => self::icon( 'plus' ),
		);
		include RWGO_PATH . 'admin/views/sitewide-screen.php';
	}

	/**
	 * @param array<string, mixed> $cfg Stored config.
	 * @return array<string, mixed>
	 */
	public static function view_model( array $cfg ) {
		if ( empty( $cfg['experiment_key'] ) ) {
			$cfg['experiment_key'] = 'rwgo_sitewide';
		}
		$status   = isset( $cfg['status'] ) ? sanitize_key( (string) $cfg['status'] ) : 'paused';
		if ( ! in_array( $status, array( 'active', 'paused', 'ended' ), true ) ) {
			$status = 'paused';
		}
		$days     = isset( $cfg['cookie_days'] ) ? (int) $cfg['cookie_days'] : 90;
		$weight   = isset( $cfg['control_weight'] ) ? (float) $cfg['control_weight'] : 0.5;
		$percent  = (int) round( max( 0, min( 1, $weight ) ) * 100 );
		$coverage = class_exists( 'RWGO_Sitewide', false ) ? RWGO_Sitewide::coverage_from_config( $cfg ) : array(
			'mapping_mode'         => 'explicit',
			'exclude_system_pages' => true,
			'exclude_page_ids'     => array(),
			'future_pages'         => 'skip',
		);
		$rows = isset( $cfg['page_map'] ) && is_array( $cfg['page_map'] ) ? $cfg['page_map'] : array();
		$map  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$source  = isset( $row['source_page_id'] ) ? (int) $row['source_page_id'] : 0;
			$variant = isset( $row['variant_page_id'] ) ? (int) $row['variant_page_id'] : 0;
			if ( $source <= 0 || $variant <= 0 ) {
				continue;
			}
			$map[] = array(
				'source_page_id'  => $source,
				'variant_page_id' => $variant,
			);
		}
		$limit = class_exists( 'RWGO_Sitewide', false ) ? (int) RWGO_Sitewide::MAP_EDITOR_LIMIT : 80;
		$merge = count( $map ) > $limit;
		$shown = $merge ? array() : $map;
		$matches = array();
		$warnings = array();
		foreach ( $shown as $index => $row ) {
			$source  = self::choice_from_id( (int) $row['source_page_id'] );
			$variant = self::choice_from_id( (int) $row['variant_page_id'] );
			$warning = self::unpublished_variant_warning( $variant['title'], $source['title'], $variant['status'] );
			if ( '' !== $warning ) {
				$warnings[] = $warning;
			}
			$matches[] = array(
				'index'   => $index,
				'source'  => $source,
				'variant' => $variant,
				'warning' => $warning,
			);
		}
		$theme = class_exists( 'RWGO_Theme_Context', false )
			? RWGO_Theme_Context::normalize( isset( $cfg['theme_context'] ) ? $cfg['theme_context'] : array() )
			: array(
				'control' => array( 'header' => 0, 'footer' => 0 ),
				'variant' => array( 'header' => 0, 'footer' => 0 ),
			);
		$products = array();
		$template_rows = class_exists( 'RWGO_Theme_Context', false )
			? RWGO_Theme_Context::normalize_template_map( isset( $cfg['template_map'] ) ? $cfg['template_map'] : array() )
			: array();
		foreach ( $template_rows as $index => $row ) {
			$products[] = array(
				'index'   => $index,
				'source'  => self::choice_from_id( (int) $row['source_template_id'] ),
				'variant' => self::choice_from_id( (int) $row['variant_template_id'] ),
			);
		}
		$exclude = array();
		foreach ( $coverage['exclude_page_ids'] as $id ) {
			$exclude[] = self::choice_from_id( (int) $id );
		}
		$goals = array();
		$goal_ids = isset( $cfg['goal_page_ids'] ) && is_array( $cfg['goal_page_ids'] ) ? $cfg['goal_page_ids'] : array();
		foreach ( $goal_ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$goals[] = self::choice_from_id( $id );
			}
		}
		$preview_base = function_exists( 'home_url' ) ? home_url( '/' ) : '';
		if ( ! empty( $map[0]['source_page_id'] ) && function_exists( 'get_permalink' ) ) {
			$link = get_permalink( (int) $map[0]['source_page_id'] );
			if ( is_string( $link ) && '' !== $link ) {
				$preview_base = $link;
			}
		}
		$empty_pages = ! $merge && array() === $matches;
		return array(
			'status'          => $status,
			'experiment_key'  => (string) $cfg['experiment_key'],
			'weight_percent'  => $percent,
			'cookie_days'     => max( 1, $days ),
			'mapping_mode'    => (string) $coverage['mapping_mode'],
			'exclude_system'  => ! empty( $coverage['exclude_system_pages'] ),
			'future_pages'    => (string) $coverage['future_pages'],
			'exclude_pages'   => $exclude,
			'matches'         => $matches,
			'map_count'       => count( $map ),
			'map_merge'       => $merge,
			'empty_pages'     => $empty_pages,
			'page_warnings'   => $warnings,
			'header'          => array(
				'control' => self::choice_from_id( (int) $theme['control']['header'] ),
				'variant' => self::choice_from_id( (int) $theme['variant']['header'] ),
			),
			'footer'          => array(
				'control' => self::choice_from_id( (int) $theme['control']['footer'] ),
				'variant' => self::choice_from_id( (int) $theme['variant']['footer'] ),
			),
			'header_set'      => (int) $theme['control']['header'] > 0 || (int) $theme['variant']['header'] > 0,
			'footer_set'      => (int) $theme['control']['footer'] > 0 || (int) $theme['variant']['footer'] > 0,
			'products'        => $products,
			'goals'           => $goals,
			'track_purchase'  => ! empty( $cfg['track_purchase'] ),
			'revision'        => isset( $cfg['revision'] ) ? (int) $cfg['revision'] : 0,
			'preview_control' => self::signed_preview_url( $preview_base, 'control' ),
			'preview_variant' => self::signed_preview_url( $preview_base, 'variant' ),
			'locations_on'    => class_exists( 'RWGO_Theme_Context', false ) && RWGO_Theme_Context::elementor_pro_locations_loaded(),
			'experiences_url' => function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=rwgo-dashboard' ) : '',
			'optimise_url'    => function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=rwgo-optimise' ) : '',
		);
	}

	/**
	 * @param mixed $value Posted ids.
	 * @return array<int, int>
	 */
	public static function id_list( $value ) {
		if ( is_array( $value ) ) {
			$ids = array();
			foreach ( $value as $item ) {
				if ( is_array( $item ) ) {
					continue;
				}
				$id = (int) $item;
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
			return array_values( array_unique( $ids ) );
		}
		if ( class_exists( 'RWGO_Sitewide', false ) ) {
			return RWGO_Sitewide::parse_id_list( (string) $value );
		}
		return array();
	}

	/**
	 * @param int $post_id Post id. Zero returns an empty choice.
	 * @return array<string, mixed>
	 */
	public static function choice_from_id( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return self::empty_choice();
		}
		$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		if ( ! $post ) {
			$choice           = self::empty_choice();
			$choice['id']     = $post_id;
			$choice['title']  = __( 'Missing item', 'reactwoo-geo-optimise' );
			$choice['missing'] = true;
			return $choice;
		}
		$choice = self::choice_from_post( $post );
		return null === $choice ? self::empty_choice() : $choice;
	}

	/**
	 * @param mixed $post Post object.
	 * @return array<string, mixed>|null
	 */
	public static function choice_from_post( $post ) {
		if ( ! is_object( $post ) || empty( $post->ID ) ) {
			return null;
		}
		$id     = (int) $post->ID;
		$status = isset( $post->post_status ) ? sanitize_key( (string) $post->post_status ) : '';
		if ( function_exists( 'get_post_status' ) ) {
			$live = get_post_status( $id );
			if ( is_string( $live ) && '' !== $live ) {
				$status = sanitize_key( $live );
			}
		}
		$type  = isset( $post->post_type ) ? sanitize_key( (string) $post->post_type ) : '';
		$title = isset( $post->post_title ) ? (string) $post->post_title : '';
		if ( '' === trim( $title ) ) {
			$title = __( '(no title)', 'reactwoo-geo-optimise' );
		}
		$slug   = isset( $post->post_name ) ? (string) $post->post_name : '';
		$label  = self::status_label( $status );
		$hint   = self::choice_hint( $type, $slug, $id );
		$meta   = self::choice_meta( $type, $slug, $id, $label );
		$link   = '';
		if ( function_exists( 'get_permalink' ) ) {
			$permalink = get_permalink( $id );
			$link      = is_string( $permalink ) ? $permalink : '';
		}
		return array(
			'id'           => $id,
			'title'        => $title,
			'slug'         => $slug,
			'status'       => $status,
			'status_label' => $label,
			'type'         => $type,
			'hint'         => $hint,
			'meta'         => $meta,
			'preview_url'  => self::signed_preview_url( $link, 'variant' ),
			'missing'      => false,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function empty_choice() {
		return array(
			'id'           => 0,
			'title'        => '',
			'slug'         => '',
			'status'       => '',
			'status_label' => '',
			'type'         => '',
			'hint'         => '',
			'meta'         => '',
			'preview_url'  => '',
			'missing'      => false,
		);
	}

	/**
	 * @param string $status Post status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$map = array(
			'publish' => __( 'Published', 'reactwoo-geo-optimise' ),
			'draft'   => __( 'Draft', 'reactwoo-geo-optimise' ),
			'pending' => __( 'Pending', 'reactwoo-geo-optimise' ),
			'private' => __( 'Private', 'reactwoo-geo-optimise' ),
			'future'  => __( 'Scheduled', 'reactwoo-geo-optimise' ),
		);
		$status = sanitize_key( (string) $status );
		return isset( $map[ $status ] ) ? $map[ $status ] : '';
	}

	/**
	 * @param string $type Post type.
	 * @param string $slug Slug.
	 * @param int    $id   Post id.
	 * @return string
	 */
	private static function choice_hint( $type, $slug, $id ) {
		if ( 'page' === $type ) {
			return '' === $slug ? '/' : '/' . $slug;
		}
		$elementor = self::elementor_type( $id );
		if ( in_array( $elementor, array( 'single', 'single-post', 'single-page', 'product', 'single-product' ), true ) ) {
			return __( 'single product', 'reactwoo-geo-optimise' );
		}
		if ( '' !== $slug ) {
			return $slug;
		}
		return 'elementor_library' === $type ? 'elementor_library' : $type;
	}

	/**
	 * @param string $type  Post type.
	 * @param string $slug  Slug.
	 * @param int    $id    Post id.
	 * @param string $label Status label.
	 * @return string
	 */
	private static function choice_meta( $type, $slug, $id, $label ) {
		$hint = self::choice_hint( $type, $slug, $id );
		if ( 'elementor_library' === $type ) {
			$elementor = self::elementor_type( $id );
			if ( ! in_array( $elementor, array( 'single', 'single-post', 'single-page', 'product', 'single-product' ), true ) ) {
				$hint = 'elementor_library';
			}
		}
		if ( '' === $label ) {
			return $hint;
		}
		return $hint . ' · ' . $label;
	}

	/**
	 * @param int $post_id Post id.
	 * @return string
	 */
	private static function elementor_type( $post_id ) {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$type = get_post_meta( $post_id, '_elementor_template_type', true );
		return is_string( $type ) ? sanitize_key( $type ) : '';
	}

	/**
	 * @param string $url Permalink.
	 * @param string $arm control|variant.
	 * @return string
	 */
	public static function signed_preview_url( $url, $arm ) {
		if ( ! is_string( $url ) || '' === $url || ! function_exists( 'add_query_arg' ) || ! function_exists( 'wp_create_nonce' ) ) {
			return '';
		}
		$action = class_exists( 'RWGO_Theme_Context', false ) ? RWGO_Theme_Context::PREVIEW_ACTION : 'rwgo_preview';
		return add_query_arg(
			array(
				'rwgo_preview'       => 'variant' === $arm ? 'variant' : 'control',
				'rwgo_preview_nonce' => wp_create_nonce( $action ),
			),
			$url
		);
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function admin_notices() {
		$notices = array();
		if ( ! empty( $_GET['rwgo_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notices[] = array(
				'type' => 'success',
				'text' => __( 'Site-wide test saved.', 'reactwoo-geo-optimise' ),
			);
		}
		if ( ! empty( $_GET['rwgo_bulk_error'] ) && 'ids' === sanitize_key( (string) $_GET['rwgo_bulk_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notices[] = array(
				'type' => 'error',
				'text' => __( 'Select at least one original page, then create the variants.', 'reactwoo-geo-optimise' ),
			);
		}
		if ( ! empty( $_GET['rwgo_bulk_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$result  = function_exists( 'get_option' ) ? get_option( 'rwgo_sitewide_bulk_result', array() ) : array();
			$created = isset( $_GET['rwgo_bulk_created'] ) ? (int) $_GET['rwgo_bulk_created'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$failed  = isset( $_GET['rwgo_bulk_failed'] ) ? (int) $_GET['rwgo_bulk_failed'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( is_array( $result ) ) {
				if ( isset( $result['created'] ) ) {
					$created = (int) $result['created'];
				}
				if ( isset( $result['failed'] ) ) {
					$failed = (int) $result['failed'];
				}
			}
			$notices[] = array(
				'type' => $failed > 0 ? 'warning' : 'success',
				'text' => sprintf(
					/* translators: 1: pages added to the map, 2: failed count */
					__( 'Variant pages added to the map: %1$d. Failed: %2$d. Existing variants were reused instead of copied again.', 'reactwoo-geo-optimise' ),
					$created,
					$failed
				),
			);
		}
		if ( function_exists( 'get_option' ) ) {
			$job = get_option( 'rwgo_sitewide_bulk_job', array() );
			if ( is_array( $job ) && ! empty( $job['active'] ) && function_exists( 'wp_nonce_url' ) && function_exists( 'admin_url' ) ) {
				$created = isset( $job['created'] ) ? (int) $job['created'] : 0;
				$notices[] = array(
					'type' => 'info',
					'html' => sprintf(
						/* translators: 1: created count, 2: continue URL */
						__( 'Creating variant pages in batches. Created so far: %1$d. <a href="%2$s">Continue now</a>', 'reactwoo-geo-optimise' ),
						$created,
						esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=rwgo_sitewide_bulk_continue' ), 'rwgo_sitewide_bulk' ) )
					),
				);
			}
		}
		return $notices;
	}

	/**
	 * @param string $name Icon name.
	 * @return string
	 */
	public static function icon( $name ) {
		$common = ' xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true" focusable="false"';
		if ( 'chevron' === $name ) {
			return '<svg class="rwgo-sw-svg" width="14" height="14"' . $common . '><path d="M3.5 6L8 10.5 12.5 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}
		if ( 'arrow' === $name ) {
			return '<svg class="rwgo-sw-svg" width="16" height="16"' . $common . '><path d="M2.5 8h11M9.5 4.5 13 8l-3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}
		if ( 'eye' === $name ) {
			return '<svg class="rwgo-sw-svg" width="15" height="15"' . $common . '><path d="M1.5 8s2.4-4 6.5-4 6.5 4 6.5 4-2.4 4-6.5 4S1.5 8 1.5 8z" fill="none" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r="1.7" fill="none" stroke="currentColor" stroke-width="1.4"/></svg>';
		}
		if ( 'trash' === $name ) {
			return '<svg class="rwgo-sw-svg" width="15" height="15"' . $common . '><path d="M3.2 4.5h9.6M6.2 4.4V3.2h3.6v1.2M4.4 4.5l.6 8.2h6l.6-8.2" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}
		if ( 'info' === $name ) {
			return '<svg class="rwgo-sw-svg" width="14" height="14"' . $common . '><circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M8 7.2V11.2M8 5.1h.01" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>';
		}
		if ( 'warning' === $name ) {
			return '<svg class="rwgo-sw-svg" width="16" height="16"' . $common . '><path d="M8 2.2 14.2 13.4H1.8L8 2.2z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M8 6.4v3.2M8 11.4h.01" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>';
		}
		if ( 'search' === $name ) {
			return '<svg class="rwgo-sw-svg" width="14" height="14"' . $common . '><circle cx="7" cy="7" r="4.2" fill="none" stroke="currentColor" stroke-width="1.4"/><path d="M10.2 10.2 13 13" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>';
		}
		if ( 'plus' === $name ) {
			return '<svg class="rwgo-sw-svg" width="12" height="12"' . $common . '><path d="M8 3.2v9.6M3.2 8h9.6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
		}
		return '';
	}
}
