<?php
/**
 * Geo AI capability row for Geo Core Insights dashboard.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers workflow, sync, usage, and pending action metrics.
 */
class RWGA_Insights_Provider {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_insights_providers', array( __CLASS__, 'register' ) );
		add_filter( 'rwgc_ai_snapshot_payload', array( __CLASS__, 'append_capability_insights' ), 30, 2 );
	}

	/**
	 * @param array<int, callable(): array<string, mixed>> $providers Provider callables.
	 * @return array<int, callable(): array<string, mixed>>
	 */
	public static function register( $providers ) {
		if ( ! is_array( $providers ) ) {
			$providers = array();
		}
		$providers[] = array( __CLASS__, 'build' );
		return $providers;
	}

	/**
	 * Compact capability map for Geo AI audit workflows.
	 *
	 * @param array<string, mixed> $payload Snapshot payload.
	 * @param array<string, mixed> $context Builder context.
	 * @return array<string, mixed>
	 */
	public static function append_capability_insights( array $payload, array $context = array() ) {
		unset( $context );
		if ( ! class_exists( 'RWGC_Insights', false ) ) {
			return $payload;
		}
		$payload['capability_insights'] = RWGC_Insights::get_compact_payload();
		return $payload;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function build() {
		if ( ! class_exists( 'RWGC_Insights', false ) ) {
			return array();
		}

		$plugin_file = 'reactwoo-geo-ai/reactwoo-geo-ai.php';
		$installed   = class_exists( 'RWGC_Admin_UI', false ) && RWGC_Admin_UI::is_plugin_active( $plugin_file );
		if ( ! $installed ) {
			return RWGC_Insights::normalize_provider(
				array(
					'id'            => 'geo-ai',
					'label'         => __( 'Geo AI', 'reactwoo-geo-ai' ),
					'status'        => 'missing',
					'summary'       => __( 'Install Geo AI for UX analysis, copy drafts, site intelligence sync, and approval-gated actions.', 'reactwoo-geo-ai' ),
					'capabilities'  => self::feature_list( 'missing' ),
					'empty_state'   => array(
						'type'  => 'not_installed',
						'title' => __( 'Install Geo AI to unlock intelligence workflows', 'reactwoo-geo-ai' ),
						'body'  => __( 'Run site audits, generate copy/SEO drafts, and sync compact site intelligence to the cloud.', 'reactwoo-geo-ai' ),
					),
					'actions'       => array(
						array(
							'url'     => admin_url( 'plugin-install.php?s=reactwoo-geo-ai&tab=search&type=term' ),
							'label'   => __( 'Install Geo AI', 'reactwoo-geo-ai' ),
							'primary' => true,
						),
					),
					'recommendations' => array(
						array(
							'label'    => __( 'Enable Geo AI sync', 'reactwoo-geo-ai' ),
							'priority' => 17,
							'reason'   => __( 'Let AI consume the capability map for audits and recommendations.', 'reactwoo-geo-ai' ),
						),
					),
				)
			);
		}

		$licensed = class_exists( 'RWGA_License', false ) && RWGA_License::can_run_workflows();
		$settings = class_exists( 'RWGA_Settings', false ) ? RWGA_Settings::get_settings() : array();
		$engine   = isset( $settings['workflow_engine'] ) ? sanitize_key( (string) $settings['workflow_engine'] ) : 'local';
		$sync     = class_exists( 'RWGA_Site_Intelligence_Sync', false ) ? RWGA_Site_Intelligence_Sync::get_status() : array();
		$pending  = class_exists( 'RWGA_Site_Intelligence_Journey', false )
			? RWGA_Site_Intelligence_Journey::count_all_pending_actions()
			: 0;

		$last_sync = ! empty( $sync['last_synced_at_gmt'] )
			? (string) $sync['last_synced_at_gmt']
			: __( 'Never', 'reactwoo-geo-ai' );
		$sync_ok   = ! empty( $sync['last_synced_at_gmt'] )
			&& ( empty( $sync['last_sync_status'] ) || 'synced' === (string) $sync['last_sync_status'] );

		$capabilities = self::feature_list(
			'active',
			array(
				'ux'         => $licensed,
				'copy'       => $licensed,
				'competitor' => $licensed,
				'sync'       => $sync_ok,
				'debug'      => true,
				'approval'   => $pending > 0 || $licensed,
			)
		);

		$status = 'active';
		if ( ! $licensed ) {
			$status = 'requires_license';
		} elseif ( ! $sync_ok ) {
			$status = 'inactive';
		} elseif ( ! $sync_ok && __( 'Never', 'reactwoo-geo-ai' ) === $last_sync ) {
			$status = 'no_data';
		}

		$missing = array();
		if ( ! $licensed ) {
			$missing[] = __( 'Geo AI licence key', 'reactwoo-geo-ai' );
		}
		if ( ! $sync_ok ) {
			$missing[] = __( 'Site intelligence sync', 'reactwoo-geo-ai' );
		}

		$audit_url = admin_url( 'admin.php?page=rwga-intelligence-wizard' );
		$lic_url   = admin_url( 'admin.php?page=rwga-license' );

		return RWGC_Insights::normalize_provider(
			array(
				'id'              => 'geo-ai',
				'label'           => __( 'Geo AI', 'reactwoo-geo-ai' ),
				'status'          => $status,
				'summary'         => $licensed
					? __( 'AI workflows can analyse the capability map and run approval-gated intelligence actions.', 'reactwoo-geo-ai' )
					: __( 'Geo AI is installed — add a licence key to run workflows and sync site intelligence.', 'reactwoo-geo-ai' ),
				'metrics'         => array(
					array(
						'label' => __( 'Workflow engine', 'reactwoo-geo-ai' ),
						'value' => 'remote' === $engine ? __( 'Remote', 'reactwoo-geo-ai' ) : __( 'Local', 'reactwoo-geo-ai' ),
					),
					array(
						'label' => __( 'Licence', 'reactwoo-geo-ai' ),
						'value' => $licensed ? __( 'Ready', 'reactwoo-geo-ai' ) : __( 'Not configured', 'reactwoo-geo-ai' ),
					),
					array(
						'label' => __( 'Last sync', 'reactwoo-geo-ai' ),
						'value' => $last_sync,
					),
					array(
						'label' => __( 'Pending actions', 'reactwoo-geo-ai' ),
						'value' => (string) $pending,
					),
				),
				'capabilities'    => $capabilities,
				'missing_setup'   => $missing,
				'recommendations' => ! $licensed
					? array(
						array(
							'label'    => __( 'Configure Geo AI licence', 'reactwoo-geo-ai' ),
							'priority' => 11,
							'reason'   => __( 'Workflows and site intelligence sync require a ReactWoo API licence.', 'reactwoo-geo-ai' ),
						),
					)
					: ( ! $sync_ok
						? array(
							array(
								'label'    => __( 'Enable Geo AI sync', 'reactwoo-geo-ai' ),
								'priority' => 13,
								'reason'   => __( 'Upload compact site intelligence so audits use live capability data.', 'reactwoo-geo-ai' ),
							),
							array(
								'label'    => __( 'Run site audit', 'reactwoo-geo-ai' ),
								'priority' => 19,
								'reason'   => __( 'Start the intelligence wizard to review UX and SEO opportunities.', 'reactwoo-geo-ai' ),
							),
						)
						: array(
							array(
								'label'    => __( 'Run site audit', 'reactwoo-geo-ai' ),
								'priority' => 21,
								'reason'   => __( 'Use the synced capability map for the next intelligence review.', 'reactwoo-geo-ai' ),
							),
						) ),
				'actions'         => array(
					array(
						'url'     => $licensed ? $audit_url : $lic_url,
						'label'   => $licensed ? __( 'Run site audit', 'reactwoo-geo-ai' ) : __( 'Configure licence', 'reactwoo-geo-ai' ),
						'primary' => true,
					),
				),
				'empty_state'     => ! $licensed
					? array(
						'type'  => 'not_configured',
						'title' => __( 'Geo AI needs a licence before workflows can run', 'reactwoo-geo-ai' ),
						'body'  => __( 'Add your ReactWoo licence key, then run site intelligence sync.', 'reactwoo-geo-ai' ),
					)
					: ( ! $sync_ok
						? array(
							'type'  => 'not_configured',
							'title' => __( 'Site intelligence has not synced yet', 'reactwoo-geo-ai' ),
							'body'  => __( 'Run sync from licence settings so AI audits use the capability map.', 'reactwoo-geo-ai' ),
						)
						: array() ),
			)
		);
	}

	/**
	 * @param string $default_status Default capability status.
	 * @param array<string, bool> $enabled Per-feature flags.
	 * @return array<int, array<string, string>>
	 */
	private static function feature_list( $default_status, array $enabled = array() ) {
		$map = array(
			'ux'         => __( 'UX analysis', 'reactwoo-geo-ai' ),
			'copy'       => __( 'Copy / SEO drafts', 'reactwoo-geo-ai' ),
			'competitor' => __( 'Competitor research', 'reactwoo-geo-ai' ),
			'sync'       => __( 'Site intelligence sync', 'reactwoo-geo-ai' ),
			'debug'      => __( 'Rule explain / debug', 'reactwoo-geo-ai' ),
			'approval'   => __( 'Approval-gated actions', 'reactwoo-geo-ai' ),
		);
		$out = array();
		foreach ( $map as $key => $label ) {
			$status = $default_status;
			if ( 'active' === $default_status && isset( $enabled[ $key ] ) ) {
				$status = $enabled[ $key ] ? 'active' : 'inactive';
			}
			$out[] = array(
				'label'  => $label,
				'status' => $status,
			);
		}
		return $out;
	}
}
