<?php
/**
 * Shared site-wide experiment checks for the admin screen and the Abilities API.
 *
 * The input is a typed structure. This class does not write options, publish
 * posts, or copy Elementor documents.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Structural and site-local validation for one site-wide test.
 */
class RWGO_Sitewide_Proposal {

	/**
	 * @param mixed $value Raw ID.
	 * @return int|null Positive integer, or null when the value is not one.
	 */
	public static function positive_int( $value ) {
		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		}
		if ( is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]*$/', $value ) ) {
			return (int) $value;
		}
		return null;
	}

	/**
	 * @param array<string, mixed>              $cfg   Proposed or stored config.
	 * @param array<int, array<string, mixed>|null>|null $posts ID => record, null when missing. Null skips site lookup.
	 * @return array{ok:bool,errors:array<int,array<string,mixed>>,warnings:array<int,array<string,mixed>>,page_map:array<int,array<string,int>>,theme_context:array<string,array<string,int>>,control_weight:float,cookie_days:int,ready:bool}
	 */
	public static function inspect( array $cfg, $posts = null ) {
		$errors   = array();
		$warnings = array();
		$map      = array();
		$sources  = array();
		$as_variant = array();
		$rows     = isset( $cfg['page_map'] ) && is_array( $cfg['page_map'] ) ? $cfg['page_map'] : array();
		foreach ( $rows as $index => $row ) {
			if ( ! is_array( $row ) ) {
				$errors[] = self::issue( 'rwgo_malformed_id', 0, (string) $index );
				continue;
			}
			$source  = self::positive_int( $row['source_page_id'] ?? null );
			$variant = self::positive_int( $row['variant_page_id'] ?? null );
			if ( null === $source || null === $variant ) {
				$errors[] = self::issue( 'rwgo_malformed_id', null === $source ? 0 : (int) $variant, (string) $index );
				continue;
			}
			if ( $source === $variant || isset( $as_variant[ $source ] ) || isset( $sources[ $variant ] ) ) {
				$errors[] = self::issue( 'rwgo_redirect_loop', $source, (string) $variant );
				continue;
			}
			if ( isset( $sources[ $source ] ) ) {
				$errors[] = self::issue( 'rwgo_duplicate_source', $source, (string) $variant );
				continue;
			}
			$sources[ $source ]    = $variant;
			$as_variant[ $variant ] = $source;
			$map[]                 = array(
				'source_page_id'  => $source,
				'variant_page_id' => $variant,
			);
		}

		$weight = self::weight_percent( $cfg['control_weight_percent'] ?? ( isset( $cfg['control_weight'] ) ? (float) $cfg['control_weight'] * 100 : 50 ), $errors );
		$days   = self::cookie_days( $cfg['cookie_days'] ?? 90, $errors );
		$theme  = class_exists( 'RWGO_Theme_Context', false )
			? RWGO_Theme_Context::normalize( isset( $cfg['theme_context'] ) ? $cfg['theme_context'] : array() )
			: array(
				'control' => array( 'header' => 0, 'footer' => 0 ),
				'variant' => array( 'header' => 0, 'footer' => 0 ),
			);
		self::check_theme_ids( $cfg, $errors );

		if ( is_array( $posts ) ) {
			self::check_posts( $map, $theme, $posts, $errors, $warnings );
		}

		$parts = self::readiness_parts( $map, $theme, $posts );
		return array(
			'ok'             => array() === $errors,
			'errors'         => $errors,
			'warnings'       => $warnings,
			'page_map'       => $map,
			'theme_context'  => $theme,
			'control_weight' => $weight / 100,
			'cookie_days'    => $days,
			'page_ready'     => $parts['page_ready'],
			'chrome_ready'   => $parts['chrome_ready'],
			'ready'          => $parts['page_ready'] || $parts['chrome_ready'],
		);
	}

	/**
	 * @param array<string, mixed> $existing Stored config.
	 * @param mixed                $expected Value the caller last read.
	 * @param bool                 $required Ability saves must send it. The admin form sends it when the field is present.
	 * @return string Empty when the write may continue.
	 */
	public static function revision_error( array $existing, $expected, $required ) {
		$current = isset( $existing['revision'] ) ? (int) $existing['revision'] : 0;
		if ( null === $expected || '' === $expected ) {
			return $required ? 'rwgo_revision_required' : '';
		}
		if ( is_bool( $expected ) || is_array( $expected ) || is_float( $expected ) ) {
			return 'rwgo_revision_required';
		}
		if ( is_string( $expected ) && 1 !== preg_match( '/^(0|[1-9][0-9]*)$/', $expected ) ) {
			return 'rwgo_revision_required';
		}
		if ( (int) $expected !== $current ) {
			return 'rwgo_revision_conflict';
		}
		return '';
	}

	/**
	 * @param bool $can_manage     Current user can manage Optimise.
	 * @param bool $license_known  A license signal exists.
	 * @param bool $licensed       That signal allows Optimise.
	 * @return string
	 */
	public static function permission_code( $can_manage, $license_known, $licensed ) {
		if ( ! $can_manage ) {
			return 'rwgo_forbidden';
		}
		if ( $license_known && ! $licensed ) {
			return 'rwgo_unlicensed';
		}
		return '';
	}

	/**
	 * Build the stored config for a content save. Status is never taken from the proposal.
	 *
	 * @param array<string, mixed> $input    Typed proposal.
	 * @param array<string, mixed> $existing Stored config.
	 * @param array<int, array<string, mixed>|null> $posts Post records.
	 * @return array<string, mixed>
	 */
	public static function plan_save( array $input, array $existing, array $posts ) {
		$revision = self::revision_error( $existing, $input['expected_revision'] ?? null, true );
		if ( '' !== $revision ) {
			return array(
				'ok'     => false,
				'code'   => $revision,
				'errors' => array( self::issue( $revision, 0, '' ) ),
			);
		}
		$checked = self::inspect( $input, $posts );
		if ( ! $checked['ok'] ) {
			return array(
				'ok'     => false,
				'code'   => isset( $checked['errors'][0]['code'] ) ? (string) $checked['errors'][0]['code'] : 'rwgo_invalid_config',
				'errors' => $checked['errors'],
			);
		}
		$status = isset( $existing['status'] ) ? sanitize_key( (string) $existing['status'] ) : 'paused';
		if ( ! in_array( $status, array( 'active', 'paused', 'ended' ), true ) ) {
			$status = 'paused';
		}
		$config = $existing;
		$config['experiment_key'] = self::experiment_key( $input, $existing );
		$config['status']         = $status;
		$config['test_type']      = 'sitewide';
		$config['scope']          = 'site';
		$config['cookie_days']    = $checked['cookie_days'];
		$config['control_weight'] = $checked['control_weight'];
		$config['page_map']       = self::with_empty_paths( $checked['page_map'] );
		$config['theme_context']  = $checked['theme_context'];
		$config['source_page_id'] = isset( $checked['page_map'][0]['source_page_id'] ) ? (int) $checked['page_map'][0]['source_page_id'] : 0;
		$config['revision']       = ( isset( $existing['revision'] ) ? (int) $existing['revision'] : 0 ) + 1;
		$config['variants']       = array(
			array(
				'variant_id' => 'control',
				'page_id'    => 0,
				'weight'     => $checked['control_weight'],
			),
			array(
				'variant_id' => 'var_b',
				'page_id'    => 0,
				'weight'     => 1 - $checked['control_weight'],
			),
		);
		if ( array_key_exists( 'goal_page_ids', $input ) ) {
			$config['goal_page_ids'] = self::id_list( $input['goal_page_ids'] );
		}
		if ( array_key_exists( 'track_purchase', $input ) ) {
			$config['track_purchase'] = ! empty( $input['track_purchase'] );
		}
		return array(
			'ok'       => true,
			'code'     => '',
			'config'   => $config,
			'warnings' => $checked['warnings'],
			'ready'    => $checked['ready'],
		);
	}

	/**
	 * @param array<string, mixed> $input    Status request.
	 * @param array<string, mixed> $existing Stored config.
	 * @param array<int, array<string, mixed>|null> $posts Post records.
	 * @param bool                 $locations Elementor Pro locations are loaded.
	 * @return array<string, mixed>
	 */
	public static function plan_status( array $input, array $existing, array $posts, $locations ) {
		$revision = self::revision_error( $existing, $input['expected_revision'] ?? null, true );
		if ( '' !== $revision ) {
			return array(
				'ok'     => false,
				'code'   => $revision,
				'errors' => array( self::issue( $revision, 0, '' ) ),
			);
		}
		if ( empty( $input['confirm'] ) ) {
			return array(
				'ok'     => false,
				'code'   => 'rwgo_confirm_required',
				'errors' => array( self::issue( 'rwgo_confirm_required', 0, '' ) ),
			);
		}
		$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : '';
		if ( ! in_array( $status, array( 'active', 'paused', 'ended' ), true ) ) {
			return array(
				'ok'     => false,
				'code'   => 'rwgo_invalid_status',
				'errors' => array( self::issue( 'rwgo_invalid_status', 0, $status ) ),
			);
		}
		$checked = self::inspect( $existing, $posts );
		$ready   = self::activation_ready( $checked, $locations );
		if ( 'active' === $status && ( ! $checked['ok'] || ! $ready ) ) {
			$code = $checked['ok'] ? 'rwgo_not_ready' : (string) $checked['errors'][0]['code'];
			$errs = $checked['ok'] ? array( self::issue( 'rwgo_not_ready', 0, '' ) ) : $checked['errors'];
			return array(
				'ok'     => false,
				'code'   => $code,
				'errors' => $errs,
			);
		}
		$config             = $existing;
		$config['status']   = $status;
		$config['revision'] = ( isset( $existing['revision'] ) ? (int) $existing['revision'] : 0 ) + 1;
		return array(
			'ok'       => true,
			'code'     => '',
			'config'   => $config,
			'warnings' => $checked['warnings'],
			'ready'    => isset( $ready ) ? $ready : $checked['ready'],
		);
	}

	/**
	 * A published variant page is enough. Header and footer count only when Elementor Pro can print them.
	 *
	 * @param array<string, mixed> $checked   Result of inspect().
	 * @param bool                 $locations Elementor Pro theme locations are loaded.
	 * @return bool
	 */
	public static function activation_ready( array $checked, $locations ) {
		if ( ! empty( $checked['page_ready'] ) ) {
			return true;
		}
		return (bool) $locations && ! empty( $checked['chrome_ready'] );
	}

	/**
	 * @param string $code    Machine code.
	 * @param int    $post_id Related ID.
	 * @param string $detail  Short detail, never a secret.
	 * @return array{code:string,post_id:int,detail:string,message:string}
	 */
	public static function issue( $code, $post_id, $detail ) {
		$code = sanitize_key( (string) $code );
		return array(
			'code'    => $code,
			'post_id' => (int) $post_id,
			'detail'  => sanitize_text_field( (string) $detail ),
			'message' => self::message( $code, (int) $post_id ),
		);
	}

	/**
	 * @param string $code    Machine code.
	 * @param int    $post_id Related ID.
	 * @return string
	 */
	public static function message( $code, $post_id ) {
		if ( 'rwgo_malformed_id' === $code ) {
			return __( 'A page or template ID must be a positive whole number.', 'reactwoo-geo-optimise' );
		}
		if ( 'rwgo_duplicate_source' === $code ) {
			return sprintf(
				/* translators: %d: original page ID */
				__( 'Original page %d is mapped more than once.', 'reactwoo-geo-optimise' ),
				(int) $post_id
			);
		}
		if ( 'rwgo_redirect_loop' === $code ) {
			return sprintf(
				/* translators: %d: page ID */
				__( 'Page %d is both an original and a variant, which would redirect in a circle.', 'reactwoo-geo-optimise' ),
				(int) $post_id
			);
		}
		if ( 'rwgo_unknown_post' === $code ) {
			return sprintf(
				/* translators: %d: post ID */
				__( 'Post %d is not on this site.', 'reactwoo-geo-optimise' ),
				(int) $post_id
			);
		}
		if ( 'rwgo_wrong_post_type' === $code ) {
			return sprintf(
				/* translators: %d: post ID */
				__( 'Post %d is not the expected page or Elementor template.', 'reactwoo-geo-optimise' ),
				(int) $post_id
			);
		}
		if ( 'rwgo_weight_range' === $code ) {
			return __( 'Control weight must be a whole number from 0 to 100.', 'reactwoo-geo-optimise' );
		}
		if ( 'rwgo_cookie_days' === $code ) {
			return __( 'Cookie lifetime must be from 1 to 365 days.', 'reactwoo-geo-optimise' );
		}
		if ( 'rwgo_variant_unpublished' === $code ) {
			return sprintf(
				/* translators: %d: variant page ID */
				__( 'Variant page %d is not published. Visitors keep the original body until you publish it. This does not publish it.', 'reactwoo-geo-optimise' ),
				(int) $post_id
			);
		}
		if ( 'rwgo_theme_template_unpublished' === $code ) {
			return sprintf(
				/* translators: %d: template ID */
				__( 'Template %d is not a published Elementor header or footer. It is not shown and is not published automatically.', 'reactwoo-geo-optimise' ),
				(int) $post_id
			);
		}
		if ( 'rwgo_theme_template_empty' === $code ) {
			return sprintf(
				/* translators: %d: template ID */
				__( 'Template %d has no Elementor content yet. Design it in Elementor before using it. This does not copy or publish it.', 'reactwoo-geo-optimise' ),
				(int) $post_id
			);
		}
		if ( 'rwgo_control_template_missing' === $code ) {
			return __( 'Set the control header and footer too, or Elementor can leave that location empty for the control arm.', 'reactwoo-geo-optimise' );
		}
		if ( 'rwgo_not_ready' === $code ) {
			return __( 'The test cannot go active until a variant page is published, or a non-empty variant header or footer is published and Elementor Pro theme locations are active.', 'reactwoo-geo-optimise' );
		}
		if ( 'rwgo_confirm_required' === $code ) {
			return __( 'Changing status requires confirm set to true. Saving the map does not activate the test.', 'reactwoo-geo-optimise' );
		}
		if ( 'rwgo_invalid_status' === $code ) {
			return __( 'Status must be active, paused, or ended.', 'reactwoo-geo-optimise' );
		}
		if ( 'rwgo_revision_required' === $code ) {
			return __( 'Send the revision from the last read before saving.', 'reactwoo-geo-optimise' );
		}
		if ( 'rwgo_revision_conflict' === $code ) {
			return __( 'The test changed since it was read. Read it again, then save.', 'reactwoo-geo-optimise' );
		}
		if ( 'rwgo_forbidden' === $code ) {
			return __( 'You cannot manage this site-wide test.', 'reactwoo-geo-optimise' );
		}
		if ( 'rwgo_unlicensed' === $code ) {
			return __( 'Geo Optimise is not licensed on this site.', 'reactwoo-geo-optimise' );
		}
		return $code;
	}

	/**
	 * @param mixed $percent Percent.
	 * @param array<int, array<string, mixed>> $errors Errors.
	 * @return float
	 */
	private static function weight_percent( $percent, array &$errors ) {
		if ( is_bool( $percent ) || is_array( $percent ) || ! is_numeric( $percent ) ) {
			$errors[] = self::issue( 'rwgo_weight_range', 0, '' );
			return 50.0;
		}
		$value = (float) $percent;
		if ( $value < 0 || $value > 100 ) {
			$errors[] = self::issue( 'rwgo_weight_range', 0, '' );
			return 50.0;
		}
		return $value;
	}

	/**
	 * @param mixed $days Days.
	 * @param array<int, array<string, mixed>> $errors Errors.
	 * @return int
	 */
	private static function cookie_days( $days, array &$errors ) {
		$id = self::positive_int( is_int( $days ) || ( is_string( $days ) && preg_match( '/^[1-9][0-9]*$/', $days ) ) ? $days : null );
		if ( null === $id || $id > 365 ) {
			$errors[] = self::issue( 'rwgo_cookie_days', 0, '' );
			return 90;
		}
		return $id;
	}

	/**
	 * @param array<string, mixed> $cfg Config.
	 * @param array<int, array<string, mixed>> $errors Errors.
	 * @return void
	 */
	private static function check_theme_ids( array $cfg, array &$errors ) {
		if ( ! isset( $cfg['theme_context'] ) || ! is_array( $cfg['theme_context'] ) ) {
			return;
		}
		foreach ( array( 'control', 'variant' ) as $arm ) {
			if ( ! isset( $cfg['theme_context'][ $arm ] ) || ! is_array( $cfg['theme_context'][ $arm ] ) ) {
				continue;
			}
			foreach ( array( 'header', 'footer' ) as $location ) {
				if ( ! array_key_exists( $location, $cfg['theme_context'][ $arm ] ) ) {
					continue;
				}
				$value = $cfg['theme_context'][ $arm ][ $location ];
				if ( 0 === $value || '0' === $value ) {
					continue;
				}
				if ( null === self::positive_int( $value ) ) {
					$errors[] = self::issue( 'rwgo_malformed_id', 0, $arm . '-' . $location );
				}
			}
		}
	}

	/**
	 * @param array<int, array<string, int>> $map Map.
	 * @param array<string, array<string, int>> $theme Theme.
	 * @param array<int, array<string, mixed>|null> $posts Posts.
	 * @param array<int, array<string, mixed>> $errors Errors.
	 * @param array<int, array<string, mixed>> $warnings Warnings.
	 * @return void
	 */
	private static function check_posts( array $map, array $theme, array $posts, array &$errors, array &$warnings ) {
		foreach ( $map as $row ) {
			foreach ( array( 'source_page_id' => 'page', 'variant_page_id' => 'page' ) as $key => $expect ) {
				$id = (int) $row[ $key ];
				self::check_one_post( $id, $expect, 'variant_page_id' === $key, $posts, $errors, $warnings );
			}
		}
		foreach ( array( 'control', 'variant' ) as $arm ) {
			foreach ( array( 'header', 'footer' ) as $location ) {
				$id = (int) $theme[ $arm ][ $location ];
				if ( $id <= 0 ) {
					if ( 'control' === $arm && (int) $theme['variant'][ $location ] > 0 ) {
						$warnings[] = self::issue( 'rwgo_control_template_missing', 0, $location );
					}
					continue;
				}
				self::check_one_post( $id, 'elementor_library', false, $posts, $errors, $warnings );
			}
		}
	}

	/**
	 * @param int  $id Post ID.
	 * @param string $expect Expected post type.
	 * @param bool $variant_page Variant page, unpublished is a warning.
	 * @param array<int, array<string, mixed>|null> $posts Posts.
	 * @param array<int, array<string, mixed>> $errors Errors.
	 * @param array<int, array<string, mixed>> $warnings Warnings.
	 * @return void
	 */
	private static function check_one_post( $id, $expect, $variant_page, array $posts, array &$errors, array &$warnings ) {
		if ( ! array_key_exists( $id, $posts ) || null === $posts[ $id ] ) {
			$errors[] = self::issue( 'rwgo_unknown_post', $id, '' );
			return;
		}
		$record = $posts[ $id ];
		$type   = isset( $record['type'] ) ? sanitize_key( (string) $record['type'] ) : '';
		$status = isset( $record['status'] ) ? sanitize_key( (string) $record['status'] ) : '';
		if ( $type !== $expect ) {
			$errors[] = self::issue( 'rwgo_wrong_post_type', $id, $type );
			return;
		}
		if ( $variant_page && 'publish' !== $status ) {
			$warnings[] = self::issue( 'rwgo_variant_unpublished', $id, $status );
		}
		if ( 'elementor_library' === $expect && 'publish' !== $status ) {
			$warnings[] = self::issue( 'rwgo_theme_template_unpublished', $id, $status );
		}
		if ( 'elementor_library' === $expect && ! empty( $record['elementor_empty'] ) ) {
			$warnings[] = self::issue( 'rwgo_theme_template_empty', $id, '' );
		}
	}

	/**
	 * @param array<int, array<string, int>> $map Map.
	 * @param array<string, array<string, int>> $theme Theme.
	 * @param array<int, array<string, mixed>|null>|null $posts Posts.
	 * @return bool
	 */
	/**
	 * @param array<int, array<string, int>>         $map   Map.
	 * @param array<string, array<string, int>>      $theme Theme.
	 * @param array<int, array<string, mixed>|null>|null $posts Posts.
	 * @return array{page_ready:bool,chrome_ready:bool}
	 */
	private static function readiness_parts( array $map, array $theme, $posts ) {
		$page   = false;
		$chrome = false;
		if ( ! is_array( $posts ) ) {
			return array(
				'page_ready'   => false,
				'chrome_ready' => false,
			);
		}
		foreach ( $map as $row ) {
			$id = (int) $row['variant_page_id'];
			if ( isset( $posts[ $id ]['status'] ) && 'publish' === $posts[ $id ]['status'] && isset( $posts[ $id ]['type'] ) && 'page' === $posts[ $id ]['type'] ) {
				$page = true;
			}
		}
		foreach ( array( 'header', 'footer' ) as $location ) {
			$id = (int) $theme['variant'][ $location ];
			if ( $id <= 0 || empty( $posts[ $id ] ) || ! is_array( $posts[ $id ] ) ) {
				continue;
			}
			$record = $posts[ $id ];
			if ( 'publish' === ( $record['status'] ?? '' ) && 'elementor_library' === ( $record['type'] ?? '' ) && empty( $record['elementor_empty'] ) ) {
				$chrome = true;
			}
		}
		return array(
			'page_ready'   => $page,
			'chrome_ready' => $chrome,
		);
	}

	/**
	 * @param array<string, mixed> $input Input.
	 * @param array<string, mixed> $existing Existing.
	 * @return string
	 */
	private static function experiment_key( array $input, array $existing ) {
		$raw = '';
		if ( isset( $input['experiment_key'] ) ) {
			$raw = sanitize_key( (string) $input['experiment_key'] );
		} elseif ( isset( $existing['experiment_key'] ) ) {
			$raw = sanitize_key( (string) $existing['experiment_key'] );
		}
		return '' !== $raw ? $raw : 'rwgo_sitewide';
	}

	/**
	 * @param mixed $value List.
	 * @return array<int, int>
	 */
	private static function id_list( $value ) {
		$ids = array();
		if ( ! is_array( $value ) ) {
			return $ids;
		}
		foreach ( $value as $item ) {
			$id = self::positive_int( $item );
			if ( null !== $id && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * @param array<int, array<string, int>> $map Map.
	 * @return array<int, array<string, mixed>>
	 */
	private static function with_empty_paths( array $map ) {
		$out = array();
		foreach ( $map as $row ) {
			$out[] = array(
				'source_page_id'  => (int) $row['source_page_id'],
				'variant_page_id' => (int) $row['variant_page_id'],
				'source_path'     => '',
				'variant_path'    => '',
			);
		}
		return $out;
	}
}
