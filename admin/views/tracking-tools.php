<?php
/**
 * Tracking setup — guided workflow + technical reference.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-tracking-tools';
$rwgo_experiments = isset( $rwgo_experiments ) && is_array( $rwgo_experiments ) ? $rwgo_experiments : array();

$rwgo_setup = class_exists( 'RWGO_Tracking_Setup', false )
	? RWGO_Tracking_Setup::build_context( $rwgo_experiments )
	: array(
		'connected'       => false,
		'target'          => array(
			'account_id'     => '',
			'container_id'   => '',
			'workspace_id'   => '',
			'measurement_id' => '',
		),
		'discovery'       => array(
			'accounts'   => array(),
			'containers' => array(),
			'workspaces' => array(),
			'error'      => '',
		),
		'account_label'   => '',
		'container_label' => '',
		'workspace_label' => '',
		'has_account'     => false,
		'has_container'   => false,
		'has_ga4'         => false,
		'assets_pushed'   => false,
		'last_push'       => array(),
		'asset_counts'    => array(
			'variables' => 0,
			'triggers'  => 2,
			'tags'      => 0,
		),
		'mode'            => 'simple',
		'primary'         => null,
		'preflight_ready' => false,
		'preview_url'     => '',
		'next'            => array(
			'key'   => 'connect',
			'title' => __( 'Next step', 'reactwoo-geo-optimise' ),
			'body'  => __( 'Connect Google Tag Manager via React Cloud to continue setup.', 'reactwoo-geo-optimise' ),
		),
		'status_rows'     => array(),
	);

$rwgo_gtm_page_flash = class_exists( 'RWGO_GTM_Live', false ) ? RWGO_GTM_Live::consume_flash() : null;
$rwgo_view           = isset( $_GET['rwgo_view'] ) ? sanitize_key( wp_unslash( $_GET['rwgo_view'] ) ) : 'guide'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $rwgo_view, array( 'guide', 'reference' ), true ) ) {
	$rwgo_view = 'guide';
}
?>
<div class="wrap rwgc-wrap rwgc-suite rwgo-wrap rwgo-wrap--tracking-tools" data-rwgo-tracking-view="<?php echo esc_attr( $rwgo_view ); ?>">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Tracking setup', 'reactwoo-geo-optimise' ),
			__( 'Connect Google Tag Manager, publish recommended tracking assets, and verify Geo Optimise events.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Tracking setup', 'reactwoo-geo-optimise' ); ?></h1>
		<p class="rwgo-section__lead"><?php esc_html_e( 'Connect Google Tag Manager, publish recommended tracking assets, and verify Geo Optimise events.', 'reactwoo-geo-optimise' ); ?></p>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php if ( is_array( $rwgo_gtm_page_flash ) && isset( $rwgo_gtm_page_flash['mode'] ) && in_array( (string) $rwgo_gtm_page_flash['mode'], array( 'preview', 'push' ), true ) ) : ?>
		<?php
		$rwgo_top_summary = ( isset( $rwgo_gtm_page_flash['summary'] ) && is_array( $rwgo_gtm_page_flash['summary'] ) )
			? $rwgo_gtm_page_flash['summary']
			: array(
				'headline' => '',
				'lines'    => array(),
				'note'     => '',
			);
		$rwgo_top_is_preview = ( 'preview' === (string) $rwgo_gtm_page_flash['mode'] );
		?>
		<div class="rwgo-gtm-result-frame rwgo-gtm-result-frame--page" id="rwgo-gtm-result-notice" role="status">
			<p class="rwgo-gtm-result-frame__eyebrow"><?php echo $rwgo_top_is_preview
				? esc_html__( 'Preview result', 'reactwoo-geo-optimise' )
				: esc_html__( 'Push result', 'reactwoo-geo-optimise' ); ?></p>
			<?php if ( '' !== (string) ( $rwgo_top_summary['headline'] ?? '' ) ) : ?>
				<p class="rwgo-gtm-result-frame__headline"><strong><?php echo esc_html( (string) $rwgo_top_summary['headline'] ); ?></strong></p>
			<?php else : ?>
				<p class="rwgo-gtm-result-frame__headline"><strong><?php echo $rwgo_top_is_preview
					? esc_html__( 'Preview completed (dry run).', 'reactwoo-geo-optimise' )
					: esc_html__( 'Draft entities were pushed to your GTM workspace.', 'reactwoo-geo-optimise' ); ?></strong></p>
			<?php endif; ?>
			<?php if ( ! empty( $rwgo_top_summary['lines'] ) && is_array( $rwgo_top_summary['lines'] ) ) : ?>
				<ul class="rwgo-gtm-result-frame__list">
					<?php foreach ( $rwgo_top_summary['lines'] as $line ) : ?>
						<li><?php echo esc_html( (string) $line ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! empty( $rwgo_top_summary['note'] ) ) : ?>
				<p class="rwgo-gtm-result-frame__note"><?php echo esc_html( (string) $rwgo_top_summary['note'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $rwgo_gtm_page_flash['result'] ) && is_array( $rwgo_gtm_page_flash['result'] ) ) : ?>
				<details class="rwgo-gtm-result-frame__raw">
					<summary><?php esc_html_e( 'Raw API response', 'reactwoo-geo-optimise' ); ?></summary>
					<pre class="rwgo-code-block"><?php echo esc_html( wp_json_encode( $rwgo_gtm_page_flash['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
				</details>
			<?php endif; ?>
		</div>
	<?php elseif ( is_array( $rwgo_gtm_page_flash ) && 'error' === (string) ( $rwgo_gtm_page_flash['mode'] ?? '' ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( (string) ( $rwgo_gtm_page_flash['message'] ?? __( 'GTM request failed.', 'reactwoo-geo-optimise' ) ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['rwgo_gtm_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'GTM target saved.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgo_gtm_ok'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Google Tag Manager connected via React Cloud.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['rwgo_mode_saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tracking mode saved.', 'reactwoo-geo-optimise' ); ?></p></div>
	<?php endif; ?>

	<section class="rwgo-panel rwgo-tracking-status" aria-labelledby="rwgo-tracking-status-title">
		<h2 id="rwgo-tracking-status-title" class="rwgo-section__title"><?php esc_html_e( 'Status summary', 'reactwoo-geo-optimise' ); ?></h2>
		<ul class="rwgo-tracking-status__list">
			<?php foreach ( (array) $rwgo_setup['status_rows'] as $row ) : ?>
				<?php if ( ! is_array( $row ) ) { continue; } ?>
				<li class="rwgo-tracking-status__item is-<?php echo esc_attr( (string) ( $row['tone'] ?? 'action' ) ); ?>">
					<span class="rwgo-tracking-status__label"><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></span>
					<span class="rwgo-tracking-badge rwgo-tracking-badge--<?php echo esc_attr( (string) ( $row['tone'] ?? 'action' ) ); ?>"><?php echo esc_html( (string) ( $row['value'] ?? '' ) ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>

	<section class="rwgo-tracking-next rwgo-gtm-result-frame" aria-labelledby="rwgo-tracking-next-title">
		<p class="rwgo-gtm-result-frame__eyebrow" id="rwgo-tracking-next-title"><?php echo esc_html( (string) ( $rwgo_setup['next']['title'] ?? __( 'Next step', 'reactwoo-geo-optimise' ) ) ); ?></p>
		<p class="rwgo-gtm-result-frame__headline"><?php echo esc_html( (string) ( $rwgo_setup['next']['body'] ?? '' ) ); ?></p>
	</section>

	<div class="rwgo-tracking-view-toggle" role="tablist" aria-label="<?php esc_attr_e( 'Tracking setup views', 'reactwoo-geo-optimise' ); ?>">
		<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-tracking-view-btn<?php echo 'guide' === $rwgo_view ? ' is-active' : ''; ?>" data-rwgo-tracking-view-btn="guide" role="tab" aria-selected="<?php echo 'guide' === $rwgo_view ? 'true' : 'false'; ?>"><?php esc_html_e( 'Setup Guide', 'reactwoo-geo-optimise' ); ?></button>
		<button type="button" class="button rwgo-btn rwgo-btn--secondary rwgo-tracking-view-btn<?php echo 'reference' === $rwgo_view ? ' is-active' : ''; ?>" data-rwgo-tracking-view-btn="reference" role="tab" aria-selected="<?php echo 'reference' === $rwgo_view ? 'true' : 'false'; ?>"><?php esc_html_e( 'Technical Reference', 'reactwoo-geo-optimise' ); ?></button>
	</div>

	<div class="rwgo-stack">
		<div class="rwgo-tracking-panel" data-rwgo-tracking-panel="guide" <?php echo 'guide' === $rwgo_view ? '' : 'hidden'; ?>>
			<?php
			$rwgo_gtm_flash_already_shown = is_array( $rwgo_gtm_page_flash );
			$rwgo_gtm_flash               = is_array( $rwgo_gtm_page_flash ) ? $rwgo_gtm_page_flash : null;
			require RWGO_PATH . 'admin/views/partials/tracking-setup-guide.php';
			?>
		</div>
		<div class="rwgo-tracking-panel" data-rwgo-tracking-panel="reference" <?php echo 'reference' === $rwgo_view ? '' : 'hidden'; ?>>
			<?php require RWGO_PATH . 'admin/views/partials/tracking-technical-reference.php'; ?>
		</div>
	</div>
</div>
