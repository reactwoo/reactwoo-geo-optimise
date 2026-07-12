<?php
/**
 * Tracking Tools setup status + next-step helpers (admin UX only).
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Derives guided-setup status from GTM connection, target, push history, and tests.
 */
class RWGO_Tracking_Setup {

	const OPTION_LAST_PUSH = 'rwgo_gtm_last_push';
	const OPTION_MODE      = 'rwgo_tracking_setup_mode';

	/**
	 * @param array<int, \WP_Post> $experiments Experiment posts.
	 * @return array<string, mixed>
	 */
	public static function build_context( array $experiments = array() ) {
		$connected = class_exists( 'RWGO_GTM_Live', false ) && RWGO_GTM_Live::is_connected();
		$target    = class_exists( 'RWGO_GTM_Live', false ) ? RWGO_GTM_Live::get_target() : array(
			'account_id'     => '',
			'container_id'   => '',
			'workspace_id'   => '',
			'measurement_id' => '',
		);
		$discovery = ( $connected && class_exists( 'RWGO_GTM_Live', false ) )
			? RWGO_GTM_Live::discovery_for_admin()
			: array(
				'accounts'   => array(),
				'containers' => array(),
				'workspaces' => array(),
				'error'      => '',
			);

		$account_label   = self::label_for_id( $discovery['accounts'], $target['account_id'] );
		$container_label = self::label_for_id( $discovery['containers'], $target['container_id'] );
		$workspace_label = self::label_for_id( $discovery['workspaces'], $target['workspace_id'] );
		if ( '' === $workspace_label && '' !== $target['container_id'] ) {
			$workspace_label = __( 'Default Workspace', 'reactwoo-geo-optimise' );
		}

		$has_account   = '' !== (string) $target['account_id'];
		$has_container = '' !== (string) $target['container_id'];
		$has_ga4       = '' !== (string) $target['measurement_id'];
		$last_push     = self::get_last_push();
		$assets_pushed = ! empty( $last_push['at'] );
		if ( ! $assets_pushed ) {
			$transient = get_transient( 'rwgo_gtm_last_result_' . get_current_user_id() );
			if ( is_array( $transient ) ) {
				$meta = isset( $transient['_rwgo'] ) && is_array( $transient['_rwgo'] ) ? $transient['_rwgo'] : array();
				if ( ( isset( $meta['mode'] ) && 'push' === $meta['mode'] ) || ( isset( $transient['dry_run'] ) && false === $transient['dry_run'] ) ) {
					$assets_pushed = true;
				}
			}
		}

		$primary = self::primary_test( $experiments );
		$var_count = class_exists( 'RWGO_GTM_Handoff', false )
			? count( RWGO_GTM_Handoff::standard_variable_definitions() )
			: 0;
		$trigger_count = 2;
		$tag_count     = $has_ga4 ? 2 : 0;

		$mode = self::get_mode();

		$preflight_ready = false;
		$preview_url     = '';
		if ( $primary && class_exists( 'RWGO_Tracking_Preflight', false ) ) {
			$pf = RWGO_Tracking_Preflight::run( $primary['post'], $primary['cfg'] );
			$preflight_ready = ! empty( $pf['ready'] );
			$src             = (int) ( $primary['cfg']['source_page_id'] ?? 0 );
			if ( $src > 0 ) {
				$preview_url = (string) get_permalink( $src );
			}
		}

		$next = self::next_step(
			array(
				'connected'       => $connected,
				'has_account'     => $has_account,
				'has_container'   => $has_container,
				'has_ga4'         => $has_ga4,
				'assets_pushed'   => $assets_pushed,
				'preflight_ready' => $preflight_ready,
				'has_primary'     => (bool) $primary,
			)
		);

		return array(
			'connected'         => $connected,
			'target'            => $target,
			'discovery'         => $discovery,
			'account_label'     => $account_label,
			'container_label'   => $container_label,
			'workspace_label'   => $workspace_label,
			'has_account'       => $has_account,
			'has_container'     => $has_container,
			'has_ga4'           => $has_ga4,
			'assets_pushed'     => $assets_pushed,
			'last_push'         => $last_push,
			'asset_counts'      => array(
				'variables' => $var_count,
				'triggers'  => $trigger_count,
				'tags'      => $tag_count,
			),
			'mode'              => $mode,
			'primary'           => $primary,
			'preflight_ready'   => $preflight_ready,
			'preview_url'       => $preview_url,
			'next'              => $next,
			'status_rows'       => self::status_rows(
				$connected,
				$container_label,
				$has_container,
				$has_ga4,
				$assets_pushed,
				$preflight_ready,
				$primary
			),
		);
	}

	/**
	 * @return string simple|advanced
	 */
	public static function get_mode() {
		$mode = (string) get_option( self::OPTION_MODE, 'simple' );
		return in_array( $mode, array( 'simple', 'advanced' ), true ) ? $mode : 'simple';
	}

	/**
	 * @param string $mode simple|advanced
	 * @return void
	 */
	public static function set_mode( $mode ) {
		$mode = in_array( $mode, array( 'simple', 'advanced' ), true ) ? $mode : 'simple';
		update_option( self::OPTION_MODE, $mode, false );
	}

	/**
	 * @return array{at:int,experiment_id:int,experiment_title:string,dry_run:bool}
	 */
	public static function get_last_push() {
		$row = get_option( self::OPTION_LAST_PUSH, array() );
		if ( ! is_array( $row ) ) {
			$row = array();
		}
		return array(
			'at'               => isset( $row['at'] ) ? (int) $row['at'] : 0,
			'experiment_id'    => isset( $row['experiment_id'] ) ? (int) $row['experiment_id'] : 0,
			'experiment_title' => isset( $row['experiment_title'] ) ? (string) $row['experiment_title'] : '',
			'dry_run'          => ! empty( $row['dry_run'] ),
		);
	}

	/**
	 * @param int    $experiment_id Experiment id.
	 * @param string $title         Title.
	 * @param bool   $dry_run       Preview only.
	 * @return void
	 */
	public static function record_push( $experiment_id, $title, $dry_run = false ) {
		if ( $dry_run ) {
			return;
		}
		update_option(
			self::OPTION_LAST_PUSH,
			array(
				'at'               => time(),
				'experiment_id'    => (int) $experiment_id,
				'experiment_title' => (string) $title,
				'dry_run'          => false,
			),
			false
		);
	}

	/**
	 * @param array<int, \WP_Post> $experiments Experiments.
	 * @return array{post:\WP_Post,cfg:array<string,mixed>}|null
	 */
	public static function primary_test( array $experiments ) {
		$fallback = null;
		foreach ( $experiments as $post ) {
			if ( ! ( $post instanceof WP_Post ) || ! class_exists( 'RWGO_Experiment_Repository', false ) ) {
				continue;
			}
			$cfg = RWGO_Experiment_Repository::get_config( $post->ID );
			if ( ! is_array( $cfg ) ) {
				continue;
			}
			$row = array(
				'post' => $post,
				'cfg'  => $cfg,
			);
			if ( null === $fallback ) {
				$fallback = $row;
			}
			if ( class_exists( 'RWGO_GTM_Handoff', false ) && RWGO_GTM_Handoff::is_gtm_ready( $cfg ) ) {
				return $row;
			}
		}
		return $fallback;
	}

	/**
	 * @param array<string, bool> $flags State flags.
	 * @return array{key:string,title:string,body:string}
	 */
	public static function next_step( array $flags ) {
		if ( empty( $flags['connected'] ) ) {
			return array(
				'key'   => 'connect',
				'title' => __( 'Next step', 'reactwoo-geo-optimise' ),
				'body'  => __( 'Connect Google Tag Manager via React Cloud to continue setup.', 'reactwoo-geo-optimise' ),
			);
		}
		if ( empty( $flags['has_account'] ) || empty( $flags['has_container'] ) ) {
			return array(
				'key'   => 'target',
				'title' => __( 'Next step', 'reactwoo-geo-optimise' ),
				'body'  => __( 'Select your GTM account and container, then save the target.', 'reactwoo-geo-optimise' ),
			);
		}
		if ( empty( $flags['has_ga4'] ) ) {
			return array(
				'key'   => 'ga4',
				'title' => __( 'Next step', 'reactwoo-geo-optimise' ),
				'body'  => __( 'Add your GA4 measurement ID (G-XXXX), then publish the recommended GTM assets to your workspace.', 'reactwoo-geo-optimise' ),
			);
		}
		if ( empty( $flags['assets_pushed'] ) ) {
			return array(
				'key'   => 'publish',
				'title' => __( 'Next step', 'reactwoo-geo-optimise' ),
				'body'  => __( 'Preview, then push the recommended GTM assets into your workspace draft (container publish stays manual in GTM).', 'reactwoo-geo-optimise' ),
			);
		}
		if ( empty( $flags['has_primary'] ) ) {
			return array(
				'key'   => 'create_test',
				'title' => __( 'Next step', 'reactwoo-geo-optimise' ),
				'body'  => __( 'Create a Geo Optimise test so you can verify events and use per-test handoff.', 'reactwoo-geo-optimise' ),
			);
		}
		if ( empty( $flags['preflight_ready'] ) ) {
			return array(
				'key'   => 'verify',
				'title' => __( 'Next step', 'reactwoo-geo-optimise' ),
				'body'  => __( 'Finish goal/handler setup on your test, then open a preview page and confirm events in Reports.', 'reactwoo-geo-optimise' ),
			);
		}
		return array(
			'key'   => 'complete',
			'title' => __( 'Setup looks complete', 'reactwoo-geo-optimise' ),
			'body'  => __( 'Review drafts in GTM and publish the container when ready. Use Technical Reference for agency handoff snippets.', 'reactwoo-geo-optimise' ),
		);
	}

	/**
	 * @param bool                     $connected Connected.
	 * @param string                   $container_label Label.
	 * @param bool                     $has_container Has container.
	 * @param bool                     $has_ga4 Has GA4.
	 * @param bool                     $assets_pushed Pushed.
	 * @param bool                     $preflight_ready Preflight.
	 * @param array<string,mixed>|null $primary Primary test.
	 * @return array<int, array{label:string,value:string,tone:string}>
	 */
	private static function status_rows( $connected, $container_label, $has_container, $has_ga4, $assets_pushed, $preflight_ready, $primary ) {
		$rows   = array();
		$rows[] = array(
			'label' => __( 'GTM connection', 'reactwoo-geo-optimise' ),
			'value' => $connected ? __( 'Connected', 'reactwoo-geo-optimise' ) : __( 'Not connected', 'reactwoo-geo-optimise' ),
			'tone'  => $connected ? 'ok' : 'action',
		);
		$rows[] = array(
			'label' => __( 'Container', 'reactwoo-geo-optimise' ),
			'value' => $has_container
				? ( '' !== $container_label ? $container_label : __( 'Selected', 'reactwoo-geo-optimise' ) )
				: __( 'Not selected', 'reactwoo-geo-optimise' ),
			'tone'  => $has_container ? 'ok' : 'action',
		);
		$rows[] = array(
			'label' => __( 'GA4 measurement ID', 'reactwoo-geo-optimise' ),
			'value' => $has_ga4 ? __( 'Set', 'reactwoo-geo-optimise' ) : __( 'Missing', 'reactwoo-geo-optimise' ),
			'tone'  => $has_ga4 ? 'ok' : 'action',
		);
		$rows[] = array(
			'label' => __( 'GTM assets', 'reactwoo-geo-optimise' ),
			'value' => $assets_pushed ? __( 'Draft pushed', 'reactwoo-geo-optimise' ) : __( 'Not published', 'reactwoo-geo-optimise' ),
			'tone'  => $assets_pushed ? 'ok' : 'action',
		);
		$verify = __( 'Not verified', 'reactwoo-geo-optimise' );
		$vtone  = 'action';
		if ( $preflight_ready ) {
			$verify = __( 'Ready to verify', 'reactwoo-geo-optimise' );
			$vtone  = 'ok';
		} elseif ( $primary ) {
			$verify = __( 'Needs attention', 'reactwoo-geo-optimise' );
		}
		$rows[] = array(
			'label' => __( 'Tracking verification', 'reactwoo-geo-optimise' ),
			'value' => $verify,
			'tone'  => $vtone,
		);
		return $rows;
	}

	/**
	 * @param array<int, array<string, string>> $rows Option rows.
	 * @param string                            $id   Id.
	 * @return string
	 */
	private static function label_for_id( $rows, $id ) {
		$id = (string) $id;
		if ( '' === $id || ! is_array( $rows ) ) {
			return '';
		}
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) ) {
				continue;
			}
			if ( (string) $row['id'] === $id ) {
				return (string) ( $row['label'] ?? $row['id'] );
			}
		}
		return $id;
	}
}
