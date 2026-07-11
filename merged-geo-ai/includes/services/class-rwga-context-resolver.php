<?php
/**
 * Resolve admin context for local command interpretation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RWGA_Context_Resolver {

	/**
	 * @param array<string,mixed> $overrides Optional mock context for testing.
	 * @return array<string,mixed>
	 */
	public static function resolve( array $overrides = array() ) {
		$context = array(
			'screen'       => self::detect_screen(),
			'target_type'  => '',
			'target_id'    => 0,
			'target_label' => '',
			'builder'      => '',
			'post_type'    => '',
			'rule_id'      => 0,
			'popup_id'     => 0,
			'variant_id'   => 0,
			'product_id'   => 0,
			'page_id'      => 0,
		);

		if ( is_admin() ) {
			$context = array_merge( $context, self::resolve_from_admin_request() );
		}

		if ( ! empty( $overrides ) ) {
			$context = array_merge( $context, $overrides );
		}

		$context['resolved_target'] = self::build_resolved_target( $context );

		return $context;
	}

	/**
	 * Resolve vague references like "this popup" using context.
	 *
	 * @param string              $reference Reference token from phrase.
	 * @param array<string,mixed> $context   Current context.
	 * @return array{type:string,id:int}|null
	 */
	public static function resolve_reference( $reference, array $context ) {
		$ref = strtolower( trim( (string) $reference ) );
		if ( '' === $ref || 'this' === $ref ) {
			return isset( $context['resolved_target'] ) ? $context['resolved_target'] : null;
		}
		if ( false !== strpos( $ref, 'popup' ) && ! empty( $context['popup_id'] ) ) {
			return array( 'type' => 'popup', 'id' => (int) $context['popup_id'] );
		}
		if ( false !== strpos( $ref, 'rule' ) && ! empty( $context['rule_id'] ) ) {
			return array( 'type' => 'rule', 'id' => (int) $context['rule_id'] );
		}
		if ( false !== strpos( $ref, 'variant' ) && ! empty( $context['variant_id'] ) ) {
			return array( 'type' => 'variant', 'id' => (int) $context['variant_id'] );
		}
		if ( false !== strpos( $ref, 'product' ) && ! empty( $context['product_id'] ) ) {
			return array( 'type' => 'product', 'id' => (int) $context['product_id'] );
		}
		if ( false !== strpos( $ref, 'page' ) && ! empty( $context['page_id'] ) ) {
			return array( 'type' => 'page', 'id' => (int) $context['page_id'] );
		}
		return isset( $context['resolved_target'] ) ? $context['resolved_target'] : null;
	}

	/**
	 * @return string
	 */
	private static function detect_screen() {
		global $pagenow;
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $page ) {
			return $page;
		}
		return is_string( $pagenow ) ? $pagenow : '';
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function resolve_from_admin_request() {
		$out = array();

		$post_id = 0;
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( wp_unslash( (string) $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( $post_id > 0 ) {
			$out['page_id'] = $post_id;
			$post           = get_post( $post_id );
			if ( $post instanceof WP_Post ) {
				$out['post_type']    = $post->post_type;
				$out['target_label'] = $post->post_title;
				if ( 'product' === $post->post_type ) {
					$out['product_id']   = $post_id;
					$out['target_type']  = 'product';
					$out['target_id']    = $post_id;
				} else {
					$out['target_type'] = 'page';
					$out['target_id']   = $post_id;
				}
			}
		}

		foreach ( array( 'rule_id', 'popup_id', 'variant_id', 'product_id', 'page_id' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$val = absint( wp_unslash( (string) $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( $val > 0 ) {
					$out[ $key ] = $val;
					if ( '' === ( $out['target_type'] ?? '' ) ) {
						$out['target_type'] = str_replace( '_id', '', $key );
						$out['target_id']   = $val;
					}
				}
			}
		}

		if ( class_exists( 'Elementor\Plugin', false ) && isset( $_GET['action'] ) && 'elementor' === sanitize_key( wp_unslash( (string) $_GET['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$out['builder'] = 'elementor';
		}

		return $out;
	}

	/**
	 * @param array<string,mixed> $context Context array.
	 * @return array{type:string,id:int}|null
	 */
	private static function build_resolved_target( array $context ) {
		$priority = array( 'popup_id', 'rule_id', 'variant_id', 'product_id', 'page_id' );
		foreach ( $priority as $key ) {
			if ( empty( $context[ $key ] ) ) {
				continue;
			}
			return array(
				'type' => str_replace( '_id', '', $key ),
				'id'   => (int) $context[ $key ],
			);
		}
		if ( ! empty( $context['target_type'] ) && ! empty( $context['target_id'] ) ) {
			return array(
				'type' => (string) $context['target_type'],
				'id'   => (int) $context['target_id'],
			);
		}
		return null;
	}
}
