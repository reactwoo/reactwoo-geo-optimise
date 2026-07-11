<?php
/**
 * Prominent UX opportunity review entry card.
 *
 * @package ReactWoo_Geo_AI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$geo_ai_licensed  = isset( $geo_ai_licensed ) ? (bool) $geo_ai_licensed : false;
$remote_available = isset( $remote_available ) ? (bool) $remote_available : false;
$usage_available  = isset( $usage_available ) ? (bool) $usage_available : false;
$review_url       = isset( $review_url ) ? (string) $review_url : admin_url( 'admin.php?page=rwga-ux-opportunity-review' );
$license_url      = isset( $license_url ) ? (string) $license_url : admin_url( 'admin.php?page=rwga-license' );
?>
<div class="rwgc-card rwgc-insights-panel rwga-ux-review-entry" style="margin-bottom:1rem;">
	<?php if ( class_exists( 'RWGC_Admin_UI', false ) ) : ?>
		<?php RWGC_Admin_UI::render_section_header( __( 'AI UX Opportunity Review', 'reactwoo-geo-ai' ) ); ?>
	<?php else : ?>
		<h2><?php esc_html_e( 'AI UX Opportunity Review', 'reactwoo-geo-ai' ); ?></h2>
	<?php endif; ?>
	<p class="description">
		<?php esc_html_e( 'Review a page, variant, product, or rule and get UX, copy, CTA, layout, targeting, and testing recommendations.', 'reactwoo-geo-ai' ); ?>
	</p>
	<ul class="rwga-capability-list" style="display:flex;flex-wrap:wrap;gap:8px;list-style:none;margin:0 0 12px;padding:0;">
		<li><span class="rwgc-geo-badge rwgc-geo-badge--success"><?php esc_html_e( 'Local deterministic review available', 'reactwoo-geo-ai' ); ?></span></li>
		<li>
			<span class="rwgc-geo-badge rwgc-geo-badge--<?php echo $remote_available ? 'success' : 'locked'; ?>">
				<?php echo $remote_available ? esc_html__( 'Remote Geo AI connected', 'reactwoo-geo-ai' ) : esc_html__( 'Remote Geo AI requires valid licence', 'reactwoo-geo-ai' ); ?>
			</span>
		</li>
		<?php if ( ! $geo_ai_licensed ) : ?>
			<li><span class="rwgc-geo-badge rwgc-geo-badge--locked"><?php esc_html_e( 'Licence required', 'reactwoo-geo-ai' ); ?></span></li>
		<?php elseif ( ! $usage_available ) : ?>
			<li><span class="rwgc-geo-badge rwgc-geo-badge--locked"><?php esc_html_e( 'Usage unavailable', 'reactwoo-geo-ai' ); ?></span></li>
		<?php endif; ?>
	</ul>
	<p>
		<a class="rwgc-btn rwgc-btn--primary" href="<?php echo esc_url( $review_url ); ?>">
			<?php esc_html_e( 'Start UX Review', 'reactwoo-geo-ai' ); ?>
		</a>
		<?php if ( ! $geo_ai_licensed ) : ?>
			<a class="rwgc-btn rwgc-btn--secondary" href="<?php echo esc_url( $license_url ); ?>" style="margin-left:8px;">
				<?php esc_html_e( 'Add licence', 'reactwoo-geo-ai' ); ?>
			</a>
		<?php endif; ?>
	</p>
</div>
