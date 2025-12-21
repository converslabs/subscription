<?php
/**
 * Related Subscriptions meta box view.
 *
 * @package SpringDevs\Subscription\Admin
 */

use SpringDevs\Subscription\Illuminate\Helper;

?>
<style>
	.subscrpt-related-subscriptions td{
		vertical-align: middle;
	}
</style>

<table class="widefat striped subscrpt-related-subscriptions">
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
			$subscription_id   = $history->subscription_id;
			$subscription_data = Helper::get_subscription_data( $subscription_id );

			$subscrpt_status = $subscription_data['status'] ?? '';
			$verbose_status  = Helper::get_verbose_status( $subscrpt_status );

			$order_item_id = get_post_meta( $history->subscription_id, '_subscrpt_order_item_id', true );
			$order_item    = $order->get_item( $history->order_item_id );

			$trial = get_post_meta( $history->subscription_id, '_subscrpt_trial', true );

			$start_date = $subscription_data['start_date'] ?? '';
			$start_date = ! empty( $start_date ) ? wp_date( 'F j, Y - g:i A', strtotime( $start_date ) ) : '-';

			$next_date = $subscription_data['next_date'] ?? '';
			$next_date = ! empty( $next_date ) ? wp_date( 'F j, Y - g:i A', strtotime( $next_date ) ) : '-';

			$price          = $subscription_data['price'] ?? 0;
			$price_excl_tax = (float) $order_item->get_total();
			$tax_amount     = (float) $order_item->get_total_tax();

			if ( $tax_amount > 0 ) {
				$price = $price_excl_tax + $tax_amount;
				$price = number_format( (float) $price, 2, '.', '' );
			}

			$is_grace_period = isset( $subscription_data['grace_period'] );
			$grace_remaining = $subscription_data['grace_period']['remaining_days'] ?? 0;
			?>
				<tr>
					<td>
						<a href="<?php echo esc_html( get_edit_post_link( $subscription_id ) ); ?>" target="_blank">Subscription #<?php echo esc_html( $subscription_id ); ?></a>
					</td>
					<td>
						<?php echo esc_html( $order_item->get_name() ); ?>
					</td>
					<td>
						<?php echo wp_kses_post( Helper::format_price_with_order_item( $price, $order_item->get_id() ) ); ?>
					</td>
					<td>
						<?php echo esc_html( $start_date ); ?>
					</td>
					<td>
						<?php echo esc_html( $next_date ); ?>
					</td>
					<!-- <td><span class="subscrpt-<?php echo esc_attr( $status_object->name ); ?>"><?php echo esc_html( $status_object->label ); ?></span></td> -->

					<td>
						<?php if ( $is_grace_period && $grace_remaining > 0 ) : ?>
							<span class="subscrpt-active grace-active">
								Active

								<?php
									$grace_remaining_text = sprintf(
										// translators: Number of days remaining in grace period.
										__( '%d days remaining!', 'wp_subscription' ),
										$grace_remaining
									);
								?>
								<span class="grace-icon" data-tooltip="<?php echo esc_attr( $grace_remaining_text ); ?>">
									<span class="dashicons dashicons-warning"></span>
								</span>
							</span>
						<?php else : ?>
							<span class="subscrpt-<?php echo esc_attr( strtolower( $subscrpt_status ) ); ?>">
								<?php echo esc_html( $verbose_status ); ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
				<?php
		endforeach;
		?>
	</tbody>
</table>
