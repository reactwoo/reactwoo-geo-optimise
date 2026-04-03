<?php
/**
 * Experiment × variant assignment table (expects $assignment_rows).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$assignment_rows = isset( $assignment_rows ) && is_array( $assignment_rows ) ? $assignment_rows : array();
?>
<?php if ( ! empty( $assignment_rows ) ) : ?>
	<table class="widefat striped rwgo-table-comfortable">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Experiment', 'reactwoo-geo-optimise' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Variant', 'reactwoo-geo-optimise' ); ?></th>
				<th scope="col"><?php esc_html_e( 'First-time assignments', 'reactwoo-geo-optimise' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $assignment_rows as $r ) : ?>
				<tr>
					<td><code><?php echo esc_html( $r['exp'] ); ?></code></td>
					<td><code><?php echo esc_html( $r['var'] ); ?></code></td>
					<td><?php echo esc_html( (string) $r['count'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php else : ?>
	<p class="rwgo-empty-hint"><?php esc_html_e( 'No variant assignments recorded yet. When your theme or plugin calls rwgo_get_variant(), counts appear here.', 'reactwoo-geo-optimise' ); ?></p>
<?php endif; ?>
