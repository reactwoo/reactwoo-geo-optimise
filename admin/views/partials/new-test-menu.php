<?php
/**
 * New test menu for the experiments list.
 *
 * Shown whether or not any single-page tests exist. Does not write options.
 *
 * @package ReactWooGeoOptimise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgo_single_url   = admin_url( 'admin.php?page=rwgo-create-test' );
$rwgo_sitewide_url = admin_url( 'admin.php?page=rwgo-sitewide' );
?>
<div class="rwgo-new-test-bar">
	<details class="rwgo-new-test">
		<summary class="rwgo-new-test__button"><?php esc_html_e( 'New test', 'reactwoo-geo-optimise' ); ?></summary>
		<div class="rwgo-new-test__menu" role="menu">
			<a role="menuitem" href="<?php echo esc_url( $rwgo_single_url ); ?>">
				<strong><?php esc_html_e( 'Single page test', 'reactwoo-geo-optimise' ); ?></strong>
				<span><?php esc_html_e( 'Compare two versions of one page or product.', 'reactwoo-geo-optimise' ); ?></span>
			</a>
			<a role="menuitem" href="<?php echo esc_url( $rwgo_sitewide_url ); ?>">
				<strong><?php esc_html_e( 'Site-wide test', 'reactwoo-geo-optimise' ); ?></strong>
				<span><?php esc_html_e( 'One visitor arm across mapped pages, headers, and product templates.', 'reactwoo-geo-optimise' ); ?></span>
			</a>
		</div>
	</details>
</div>
