<?php
/**
 * Tracking Tools — GTM / GA4 / dataLayer agency handoff.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgc_nav_current = isset( $rwgc_nav_current ) ? $rwgc_nav_current : 'rwgo-tracking-tools';
?>
<div class="wrap rwgc-wrap rwgc-suite rwgo-wrap rwgo-wrap--tracking-tools">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php
		RWGC_Admin_UI::render_page_header(
			__( 'Tracking Tools', 'reactwoo-geo-optimise' ),
			__( 'Agency-friendly GTM and dataLayer handoff for Geo Optimise tests.', 'reactwoo-geo-optimise' )
		);
		?>
	<?php else : ?>
		<h1><?php esc_html_e( 'Tracking Tools', 'reactwoo-geo-optimise' ); ?></h1>
	<?php endif; ?>

	<?php RWGO_Admin::render_inner_nav( $rwgc_nav_current ); ?>

	<?php
	$rwgo_gtm_page_flash = class_exists( 'RWGO_GTM_Live', false ) ? RWGO_GTM_Live::consume_flash() : null;
	?>
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
			<p class="rwgo-gtm-result-frame__next"><?php echo $rwgo_top_is_preview
				? esc_html__( 'Next: click “Push to GTM workspace” on the test card to create these drafts, then open GTM → your workspace to review before publishing.', 'reactwoo-geo-optimise' )
				: esc_html__( 'Next: open Google Tag Manager → the selected workspace, confirm the new drafts look right, then publish the container when ready.', 'reactwoo-geo-optimise' ); ?></p>
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

	<div class="rwgo-stack">
		<section class="rwgo-panel rwgo-panel--hero rwgo-tracking-orient" aria-labelledby="rwgo-tracking-orient-title">
			<h2 id="rwgo-tracking-orient-title" class="rwgo-section__title"><?php esc_html_e( 'Tracking & agency handoff', 'reactwoo-geo-optimise' ); ?></h2>
			<p class="rwgo-section__lead"><?php esc_html_e( 'Use this section when handing measurement to an agency or implementing reporting in Google Tag Manager. You do not need this for a basic test unless you want external analytics reporting.', 'reactwoo-geo-optimise' ); ?></p>
			<p class="rwgo-setting-row__hint"><?php esc_html_e( 'Need PHP hooks, raw counters, or CSV export?', 'reactwoo-geo-optimise' ); ?>
				<a href="<?php echo esc_url( RWGO_Admin::developer_url( 'developer' ) ); ?>"><?php esc_html_e( 'Open Developer', 'reactwoo-geo-optimise' ); ?></a></p>
		</section>

		<?php
		// Pass already-consumed flash so the GTM partial does not consume again / duplicate the banner.
		$rwgo_gtm_flash_already_shown = is_array( $rwgo_gtm_page_flash );
		$rwgo_gtm_flash               = is_array( $rwgo_gtm_page_flash ) ? $rwgo_gtm_page_flash : null;
		require RWGO_PATH . 'admin/views/partials/gtm-quick-setup.php';
		?>

		<details class="rwgo-panel rwgo-tracking-technical-details">
			<summary class="rwgo-tracking-technical-details__summary"><?php esc_html_e( 'Technical details & generated snippets', 'reactwoo-geo-optimise' ); ?></summary>
			<div class="rwgo-tracking-technical-details__body">
				<?php include RWGO_PATH . 'admin/views/partials/tools-section-tracking-advanced.php'; ?>
			</div>
		</details>
	</div>
</div>
