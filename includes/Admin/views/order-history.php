<?php
/**
 * @var array $order_histories ;
 */

use SpringDevs\Subscription\Illuminate\Helper;

if ( empty( $order_histories ) ) :
	?>
	<p><?php esc_html_e( 'No related orders found.', 'subscription' ); ?></p>
	<?php
	return;
endif;
?>

<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Order', 'subscription' ); ?></th>
			<th><?php esc_html_e( 'Type', 'subscription' ); ?></th>
			<th><?php esc_html_e( 'Date', 'subscription' ); ?></th>
			<th><?php esc_html_e( 'Status', 'subscription' ); ?></th>
			<th><?php esc_html_e( 'Total', 'subscription' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $order_histories as $history ) : ?>
			<?php $order = wc_get_order( $history->order_id ); ?>
			<?php if ( $order ) : ?>
				<tr>
					<td>
						<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" target="_blank">
							#<?php echo esc_html( $order->get_order_number() ); ?>
						</a>
					</td>
					<td><?php echo esc_html( ucfirst( str_replace( '-', ' ', $history->type ) ) ); ?></td>
					<td><?php echo esc_html( $order->get_date_created()->date( 'M j, Y - g:i A' ) ); ?></td>
					<td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
					<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
				</tr>
			<?php endif; ?>
		<?php endforeach; ?>
	</tbody>
</table>
