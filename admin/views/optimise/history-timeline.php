<?php
/**
 * Unified Optimise history timeline (hub History tab).
 *
 * @package ReactWooGeoOptimise
 *
 * @var array<int, array<string, mixed>> $rwgo_history_timeline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rwgo_history_timeline = isset( $rwgo_history_timeline ) && is_array( $rwgo_history_timeline ) ? $rwgo_history_timeline : array();
?>
<section class="rwgo-panel rwgo-optimise-hub__history-timeline" aria-labelledby="rwgo-history-timeline-title">
	<header class="rwgo-section__header">
		<h2 id="rwgo-history-timeline-title" class="rwgo-section__title"><?php esc_html_e( 'Recent activity', 'reactwoo-geo-optimise' ); ?></h2>
		<p class="rwgo-section__lead"><?php esc_html_e( 'AI analyses, experiment updates, and winner promotions in one timeline.', 'reactwoo-geo-optimise' ); ?></p>
	</header>

	<?php if ( empty( $rwgo_history_timeline ) ) : ?>
		<p class="rwgo-muted"><?php esc_html_e( 'No activity recorded yet. Run an AI review or create an experiment to populate this feed.', 'reactwoo-geo-optimise' ); ?></p>
	<?php else : ?>
		<ol class="rwgo-history-timeline">
			<?php foreach ( $rwgo_history_timeline as $entry ) : ?>
				<?php
				$url         = isset( $entry['url'] ) ? (string) $entry['url'] : '';
				$title       = isset( $entry['title'] ) ? (string) $entry['title'] : '';
				$detail      = isset( $entry['detail'] ) ? (string) $entry['detail'] : '';
				$badge       = isset( $entry['badge'] ) ? (string) $entry['badge'] : '';
				$badge_class = isset( $entry['badge_class'] ) ? (string) $entry['badge_class'] : '';
				$ts          = isset( $entry['ts'] ) ? (int) $entry['ts'] : 0;
				$when        = $ts > 0 ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $ts ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '';
				?>
				<li class="rwgo-history-timeline__item">
					<div class="rwgo-history-timeline__meta">
						<?php if ( '' !== $badge ) : ?>
							<span class="rwgo-history-timeline__badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $when ) : ?>
							<time class="rwgo-history-timeline__time" datetime="<?php echo esc_attr( gmdate( 'c', $ts ) ); ?>"><?php echo esc_html( $when ); ?></time>
						<?php endif; ?>
					</div>
					<div class="rwgo-history-timeline__body">
						<?php if ( '' !== $url ) : ?>
							<a class="rwgo-history-timeline__title" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
						<?php else : ?>
							<span class="rwgo-history-timeline__title"><?php echo esc_html( $title ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $detail ) : ?>
							<p class="rwgo-history-timeline__detail"><?php echo esc_html( $detail ); ?></p>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>
</section>
