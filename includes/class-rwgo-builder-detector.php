<?php
/**
 * Builder / platform detection for a post — drives goal options and scan strategy.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect Elementor, Gutenberg, WooCommerce product context, or mixed usage.
 */
class RWGO_Builder_Detector {

	/**
	 * Inspect a post and return detection payload for storage and UI.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public static function detect( $post_id ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return self::unknown( __( 'Post not found.', 'reactwoo-geo-optimise' ) );
		}

		$notes           = array();
		$elementor       = self::is_elementor( $post_id );
		$content         = (string) $post->post_content;
		$gutenberg       = function_exists( 'has_blocks' ) && has_blocks( $content );
		$is_product      = 'product' === $post->post_type && class_exists( 'WooCommerce', false );
		$woo_cart_ctx    = $is_product; // Product detail is commerce-relevant.

		$signals = array();
		if ( $elementor ) {
			$signals[] = 'elementor';
		}
		if ( $gutenberg ) {
			$signals[] = 'gutenberg';
		}
		if ( $is_product ) {
			$signals[] = 'woocommerce';
		}

		$builder = 'unknown';
		if ( count( array_unique( $signals ) ) > 1 || ( $elementor && $gutenberg ) ) {
			$builder = 'mixed';
		} elseif ( $is_product && ! $elementor && ! $gutenberg ) {
			$builder = 'woocommerce';
		} elseif ( $elementor ) {
			$builder = 'elementor';
		} elseif ( $gutenberg ) {
			$builder = 'gutenberg';
		} elseif ( in_array( $post->post_type, array( 'page', 'post' ), true ) ) {
			$builder = 'custom';
			$notes[] = __( 'Classic editor or template-driven content — use page visit or manual targeting.', 'reactwoo-geo-optimise' );
		}

		$confidence = 'low';
		if ( $elementor || ( $is_product && ! $gutenberg ) ) {
			$confidence = 'high';
		} elseif ( $gutenberg || $is_product ) {
			$confidence = 'medium';
		}

		if ( 'mixed' === $builder ) {
			$confidence = $elementor && $gutenberg ? 'medium' : 'high';
		}

		$supports = self::supports_for_builder( $builder, $woo_cart_ctx );
		$scan     = self::scan_strategy( $builder, $elementor );
		$fallback = 'elementor' === $builder || 'mixed' === $builder ? 'dom_scan' : 'safe_minimal';

		$user_builder = self::user_builder_label( $builder );
		$user_conf    = self::user_confidence_label( $confidence );

		$out = array(
			'builder'               => $builder,
			'confidence'            => $confidence,
			'supports'              => $supports,
			'scan_strategy'         => $scan,
			'fallback_strategy'     => $fallback,
			'notes'                 => $notes,
			'user_builder_label'    => $user_builder,
			'user_confidence_label'   => $user_conf,
			'signals'               => $signals,
			'site_builder_mode'     => '',
		);

		return self::merge_site_preferences( $out );
	}

	/**
	 * Adjust scan / fallback hints using Geo Optimise site settings (RWGO_Settings).
	 *
	 * @param array<string, mixed> $detection Raw detection payload.
	 * @return array<string, mixed>
	 */
	public static function merge_site_preferences( array $detection ) {
		if ( ! class_exists( 'RWGO_Settings', false ) ) {
			return $detection;
		}

		$mode = RWGO_Settings::get_builder_mode();
		$detection['site_builder_mode'] = $mode;

		switch ( $mode ) {
			case 'page_builder':
				if ( isset( $detection['scan_strategy'] ) && 'dom_scan' === $detection['scan_strategy'] ) {
					if ( ! empty( $detection['signals'] ) && in_array( 'gutenberg', $detection['signals'], true ) ) {
						$detection['scan_strategy'] = 'block_parse';
					} elseif ( ! empty( $detection['signals'] ) && in_array( 'elementor', $detection['signals'], true ) ) {
						$detection['scan_strategy'] = 'elementor_document';
					}
				}
				if ( RWGO_Settings::dom_fallback_enabled() ) {
					$detection['fallback_strategy'] = 'dom_scan';
				}
				break;
			case 'flexible':
				$detection['fallback_strategy'] = RWGO_Settings::dom_fallback_enabled() ? 'dom_scan' : 'safe_minimal';
				break;
			case 'manual':
				$detection['scan_strategy']     = 'manual';
				$detection['fallback_strategy'] = 'safe_minimal';
				if ( empty( $detection['notes'] ) || ! is_array( $detection['notes'] ) ) {
					$detection['notes'] = array();
				}
				$detection['notes'][] = __( 'Site preference: manual / developer setup — automatic binding is limited.', 'reactwoo-geo-optimise' );
				break;
			case 'recommended':
			default:
				if ( RWGO_Settings::dom_fallback_enabled() && in_array( $detection['builder'] ?? '', array( 'unknown', 'custom', 'mixed' ), true ) ) {
					$detection['fallback_strategy'] = 'dom_scan';
				}
				break;
		}

		$rwgo_s = RWGO_Settings::get_settings();
		if ( ! empty( $rwgo_s['mixed_site_support'] ) && isset( $detection['builder'] ) && 'mixed' === $detection['builder'] ) {
			if ( empty( $detection['notes'] ) || ! is_array( $detection['notes'] ) ) {
				$detection['notes'] = array();
			}
			$detection['notes'][] = __( 'Mixed-site support is enabled — confirm goal binding per test when confidence is medium or low.', 'reactwoo-geo-optimise' );
		}

		/**
		 * Filters detection after site preferences are applied.
		 *
		 * @param array<string, mixed> $detection Payload.
		 */
		return apply_filters( 'rwgo_builder_detection_with_settings', $detection );
	}

	/**
	 * @param string $builder Builder key.
	 * @param bool   $commerce Whether product/commerce context.
	 * @return list<string>
	 */
	private static function supports_for_builder( $builder, $commerce ) {
		$base = array( 'page_visit', 'custom_event' );
		switch ( $builder ) {
			case 'elementor':
				return array_merge( array( 'cta_click', 'form_submit' ), $base );
			case 'gutenberg':
				return array_merge( array( 'cta_click', 'form_submit' ), $base );
			case 'woocommerce':
				return array_merge(
					array( 'add_to_cart', 'begin_checkout', 'purchase', 'cta_click' ),
					$base
				);
			case 'mixed':
				return array_merge(
					array( 'cta_click', 'form_submit', 'add_to_cart', 'begin_checkout', 'purchase' ),
					$base
				);
			case 'custom':
			case 'unknown':
			default:
				return $commerce
					? array_merge( array( 'add_to_cart', 'begin_checkout', 'purchase' ), $base )
					: $base;
		}
	}

	/**
	 * @param string $builder   Builder.
	 * @param bool   $elementor Elementor present.
	 * @return string
	 */
	private static function scan_strategy( $builder, $elementor ) {
		if ( $elementor || 'elementor' === $builder ) {
			return 'elementor_document';
		}
		if ( in_array( $builder, array( 'gutenberg', 'mixed' ), true ) ) {
			return 'block_parse';
		}
		if ( 'woocommerce' === $builder ) {
			return 'woocommerce_hooks';
		}
		return 'dom_scan';
	}

	/**
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function is_elementor( $post_id ) {
		$mode = get_post_meta( (int) $post_id, '_elementor_edit_mode', true );
		return 'builder' === $mode;
	}

	/**
	 * @param string $reason Note.
	 * @return array<string, mixed>
	 */
	private static function unknown( $reason ) {
		$out = array(
			'builder'               => 'unknown',
			'confidence'            => 'low',
			'supports'              => array( 'page_visit', 'custom_event' ),
			'scan_strategy'         => 'dom_scan',
			'fallback_strategy'     => 'safe_minimal',
			'notes'                 => array( $reason ),
			'user_builder_label'    => __( 'Unknown', 'reactwoo-geo-optimise' ),
			'user_confidence_label' => __( 'Low', 'reactwoo-geo-optimise' ),
			'signals'               => array(),
			'site_builder_mode'     => '',
		);
		return self::merge_site_preferences( $out );
	}

	/**
	 * @param string $builder Internal key.
	 * @return string
	 */
	private static function user_builder_label( $builder ) {
		$map = array(
			'elementor'    => __( 'Elementor', 'reactwoo-geo-optimise' ),
			'gutenberg'      => __( 'Block editor (Gutenberg)', 'reactwoo-geo-optimise' ),
			'woocommerce'    => __( 'WooCommerce product', 'reactwoo-geo-optimise' ),
			'mixed'          => __( 'Mixed builders', 'reactwoo-geo-optimise' ),
			'custom'         => __( 'Classic / theme template', 'reactwoo-geo-optimise' ),
			'unknown'        => __( 'Unclear', 'reactwoo-geo-optimise' ),
		);
		return isset( $map[ $builder ] ) ? $map[ $builder ] : $map['unknown'];
	}

	/**
	 * @param string $c Confidence key.
	 * @return string
	 */
	private static function user_confidence_label( $c ) {
		$map = array(
			'high'   => __( 'High', 'reactwoo-geo-optimise' ),
			'medium' => __( 'Medium', 'reactwoo-geo-optimise' ),
			'low'    => __( 'Low', 'reactwoo-geo-optimise' ),
		);
		return isset( $map[ $c ] ) ? $map[ $c ] : $map['low'];
	}
}
