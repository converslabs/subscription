<?php
/**
 * Related Subscriptions meta box view.
 *
 * @package SpringDevs\Subscription\Admin
 */

use SpringDevs\Subscription\Illuminate\Helper;

?>

<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'ID', 'wp_subscription' ); ?></th>
			<th><?php esc_html_e( 'Product', 'wp_subscription' ); ?></th>
			<th><?php esc_html_e( 'Recurring', 'wp_subscription' ); ?></th>
			<th><?php esc_html_e( 'Started on', 'wp_subscription' ); ?></th>
			<th><?php esc_html_e( 'Expiry date', 'wp_subscription' ); ?></th>
			<th><?php esc_html_e( 'Status', 'wp_subscription' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $histories as $history ) :
			$subscription_id = $history->subscription_id;
			$order_item_id   = get_post_meta( $history->subscription_id, '_subscrpt_order_item_id', true );
			$order_item      = $order->get_item( $history->order_item_id );
			$trial           = get_post_meta( $history->subscription_id, '_subscrpt_trial', true );
			$start_date      = get_post_meta( $history->subscription_id, '_subscrpt_start_date', true );
			$next_date       = get_post_meta( $history->subscription_id, '_subscrpt_next_date', true );
			$status_object   = get_post_status_object( get_post_status( $history->subscription_id ) );

			$price          = get_post_meta( $history->subscription_id, '_subscrpt_price', true );
			$price_excl_tax = (float) $order_item->get_total();
			$tax_amount     = (float) $order_item->get_total_tax();

			if ( $tax_amount > 0 ) {
				$price = $price_excl_tax + $tax_amount;
				$price = number_format( (float) $price, 2, '.', '' );
			}
			?>
				<tr>
					<td>
						<a href="<?php echo esc_html( get_edit_post_link( $subscription_id ) ); ?>" target="_blank">Subscription #<?php echo esc_html( $subscription_id ); ?></a>
					</td>
					<td>
						<?php echo esc_html( $order_item->get_name() ); ?>
					</td>
					<td><?php echo wp_kses_post( Helper::format_price_with_order_item( $price, $order_item->get_id() ) ); ?></td>
					<td>
						<?php echo null == $trial ? ( ! empty( $start_date ) ? esc_html( gmdate( 'F d, Y', $start_date ) ) : '-' ) : '+' . esc_html( $trial ) . ' ' . esc_html__( 'free trial', 'wp_subscription' ); ?>
					</td>
					<td><?php echo esc_html( ! empty( $start_date ) && ! empty( $next_date ) ? ( $trial == null ? gmdate( 'F d, Y', $next_date ) : gmdate( 'F d, Y', $start_date ) ) : '-' ); ?></td>
					<td><span class="subscrpt-<?php echo esc_attr( $status_object->name ); ?>"><?php echo esc_html( $status_object->label ); ?></span></td>
				</tr>
				<?php
		endforeach;
		?>
	</tbody>
</table>
