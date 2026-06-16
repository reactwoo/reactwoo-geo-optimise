<?php
/**
 * Geo Optimise capability row for Geo Core Insights dashboard.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers experiment, goal, and reporting metrics.
 */
class RWGO_Insights_Provider {

	/**
	 * @return void
	 */
	public static function init() {
		add_filter( 'rwgc_insights_providers', array( __CLASS__, 'register' ) );
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
	 * @return array<string, mixed>
	 */
	public static function build() {
		if ( ! class_exists( 'RWGC_Insights', false ) ) {
			return array();
		}

		$plugin_file = 'reactwoo-geo-optimise/reactwoo-geo-optimise.php';
		$installed   = class_exists( 'RWGC_Admin_UI', false ) && RWGC_Admin_UI::is_plugin_active( $plugin_file );
		if ( ! $installed ) {
			return RWGC_Insights::normalize_provider(
				array(
					'id'            => 'geo-optimise',
					'label'         => __( 'Geo Optimise', 'reactwoo-geo-optimise' ),
					'status'        => 'missing',
					'summary'       => __( 'Install Geo Optimise to run geo A/B tests, sticky variants, and conversion reports.', 'reactwoo-geo-optimise' ),
					'capabilities'  => self::feature_list( 'missing' ),
					'empty_state'   => array(
						'type'  => 'not_installed',
						'title' => __( 'Install Geo Optimise to unlock experiments and reports', 'reactwoo-geo-optimise' ),
						'body'  => __( 'Create tests, track goals, and promote winning variants from Geo Core page routing.', 'reactwoo-geo-optimise' ),
					),
					'actions'       => array(
						array(
							'url'     => admin_url( 'plugin-install.php?s=reactwoo-geo-optimise&tab=search&type=term' ),
							'label'   => __( 'Install Geo Optimise', 'reactwoo-geo-optimise' ),
							'primary' => true,
						),
					),
					'recommendations' => array(
						array(
							'label'    => __( 'Create first experiment', 'reactwoo-geo-optimise' ),
							'priority' => 25,
							'reason'   => __( 'Measure geo variants with sticky assignment and goal tracking.', 'reactwoo-geo-optimise' ),
						),
					),
				)
			);
		}

		$total       = class_exists( 'RWGO_Experiment_Repository', false ) ? RWGO_Experiment_Repository::count_all() : 0;
		$active      = class_exists( 'RWGO_Experiment_Repository', false ) ? RWGO_Experiment_Repository::count_by_status( 'active' ) : 0;
		$goals       = class_exists( 'RWGO_Event_Store', false ) ? RWGO_Event_Store::count_total() : 0;
		$snapshot    = class_exists( 'RWGO_Stats', false ) ? RWGO_Stats::get_snapshot() : array();
		$assignments = isset( $snapshot['assignment_count'] ) ? (int) $snapshot['assignment_count'] : 0;
		$with_data   = self::count_experiments_with_conversion_data( $snapshot );

		$capabilities = self::feature_list(
			'active',
			array(
				'ab'        => $total > 0,
				'sticky'    => $assignments > 0,
				'goals'     => $goals > 0,
				'reports'   => ! empty( $snapshot['csv_export_count'] ),
				'promotion' => class_exists( 'RWGO_Promotion_Service', false ),
				'builder'   => true,
			)
		);

		$status = 'active';
		if ( $total <= 0 ) {
			$status = 'inactive';
		} elseif ( $with_data <= 0 && $active > 0 ) {
			$status = 'no_data';
		}

		$missing = array();
		if ( $total <= 0 ) {
			$missing[] = __( 'Experiments', 'reactwoo-geo-optimise' );
		}
		if ( $active <= 0 && $total > 0 ) {
			$missing[] = __( 'Active running tests', 'reactwoo-geo-optimise' );
		}

		$wizard_url  = admin_url( 'admin.php?page=rwgo-edit-test' );
		$reports_url = admin_url( 'admin.php?page=rwgo-reports' );

		return RWGC_Insights::normalize_provider(
			array(
				'id'              => 'geo-optimise',
				'label'           => __( 'Geo Optimise', 'reactwoo-geo-optimise' ),
				'status'          => $status,
				'summary'         => $active > 0
					? __( 'Experiments are running and collecting assignment data.', 'reactwoo-geo-optimise' )
					: ( $total > 0
						? __( 'Experiments exist but none are active right now.', 'reactwoo-geo-optimise' )
						: __( 'Geo Optimise is ready — create your first geo experiment.', 'reactwoo-geo-optimise' ) ),
				'metrics'         => array(
					array(
						'label' => __( 'Experiments', 'reactwoo-geo-optimise' ),
						'value' => (string) $total,
					),
					array(
						'label' => __( 'Active tests', 'reactwoo-geo-optimise' ),
						'value' => (string) $active,
					),
					array(
						'label' => __( 'Goal events', 'reactwoo-geo-optimise' ),
						'value' => (string) $goals,
					),
					array(
						'label' => __( 'Experiments with conversion data', 'reactwoo-geo-optimise' ),
						'value' => (string) $with_data,
					),
				),
				'capabilities'    => $capabilities,
				'missing_setup'   => $missing,
				'recommendations' => $total <= 0
					? array(
						array(
							'label'    => __( 'Create test', 'reactwoo-geo-optimise' ),
							'priority' => 16,
							'reason'   => __( 'Start with a single geo variant test on a high-traffic page.', 'reactwoo-geo-optimise' ),
						),
					)
					: ( $with_data <= 0
						? array(
							array(
								'label'    => __( 'View reports', 'reactwoo-geo-optimise' ),
								'priority' => 24,
								'reason'   => __( 'Tracking is ready — data will appear once visitors trigger goals.', 'reactwoo-geo-optimise' ),
							),
						)
						: array() ),
				'actions'         => array(
					array(
						'url'     => $total <= 0 ? $wizard_url : $reports_url,
						'label'   => $total <= 0 ? __( 'Create test', 'reactwoo-geo-optimise' ) : __( 'View reports', 'reactwoo-geo-optimise' ),
						'primary' => true,
					),
				),
				'empty_state'     => $total <= 0
					? array(
						'type'  => 'not_configured',
						'title' => __( 'Geo Optimise is active, but no experiments exist yet', 'reactwoo-geo-optimise' ),
						'body'  => __( 'Create a test to compare geo variants and measure conversion goals.', 'reactwoo-geo-optimise' ),
					)
					: ( $with_data <= 0
						? array(
							'type'  => 'no_data',
							'title' => __( 'Tracking is ready. Data will appear once visitors trigger goals.', 'reactwoo-geo-optimise' ),
							'body'  => __( 'Experiments are configured — conversion metrics populate after traffic and goal events.', 'reactwoo-geo-optimise' ),
						)
						: array() ),
			)
		);
	}

	/**
	 * @param string              $default_status Default capability status.
	 * @param array<string, bool> $enabled        Per-feature flags.
	 * @return array<int, array<string, string>>
	 */
	private static function feature_list( $default_status, array $enabled = array() ) {
		$map = array(
			'ab'        => __( 'A/B tests', 'reactwoo-geo-optimise' ),
			'sticky'    => __( 'Sticky variants', 'reactwoo-geo-optimise' ),
			'goals'     => __( 'Goal tracking', 'reactwoo-geo-optimise' ),
			'reports'   => __( 'Reports', 'reactwoo-geo-optimise' ),
			'promotion' => __( 'Winner promotion', 'reactwoo-geo-optimise' ),
			'builder'   => __( 'Builder-defined goals', 'reactwoo-geo-optimise' ),
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

	/**
	 * @param array<string, mixed> $snapshot Stats snapshot.
	 * @return int
	 */
	private static function count_experiments_with_conversion_data( array $snapshot ) {
		$dist = isset( $snapshot['experiment_variant_counts'] ) && is_array( $snapshot['experiment_variant_counts'] )
			? $snapshot['experiment_variant_counts']
			: array();
		$count = 0;
		foreach ( $dist as $variants ) {
			if ( ! is_array( $variants ) ) {
				continue;
			}
			$sum = 0;
			foreach ( $variants as $n ) {
				$sum += (int) $n;
			}
			if ( $sum > 0 ) {
				++$count;
			}
		}
		return $count;
	}
}
