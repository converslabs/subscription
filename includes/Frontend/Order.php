<?php

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Order class of frontend.
 */
class Order {
	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_subscrpt_details' ) );
	}

	/**
	 * Display subscriptions related to the order.
	 *
	 * @param \WC_Order $order Order Object.
	 *
	 * @return void
	 */
	public function display_subscrpt_details( $order ) {
		$histories = Helper::get_subscriptions_from_order( $order->get_id() );

		if ( is_array( $histories ) && count( $histories ) === 0 ) {
			return;
		}

		// Render Subscription Details.
		?>

		<h2 class="woocommerce-order-details__title"><?php esc_html_e( 'Related Subscriptions', 'wp_subscription' ); ?></h2>
		
		<?php foreach ( $histories as $history ) : ?>
			<?php
			$subscription_id   = $history->subscription_id;
			$subscription_data = Helper::get_subscription_data( $subscription_id );

			$subscrpt_status = $subscription_data['status'] ?? '-';
			$verbose_status  = Helper::get_verbose_status( $subscrpt_status );

			$order_item_id = $subscription_data['order']['order_item_id'] ?? $history->order_item_id;
			$order_item    = $order->get_item( $order_item_id );

			$start_date_string = $subscription_data['start_date'] ?? '';
			$start_date        = ! empty( $start_date_string ) ? gmdate( 'F d, Y', strtotime( $start_date_string ) ) : '-';

			$next_date_string = $subscription_data['next_date'] ?? '';
			$next_date        = ! empty( $next_date_string ) ? gmdate( 'F d, Y', strtotime( $next_date_string ) ) : '-';

			$cost           = $subscription_data['price'] ?? '0.00';
			$price_excl_tax = (float) $order_item->get_total();
			$tax_amount     = (float) $order_item->get_total_tax();

			if ( $tax_amount > 0 ) {
				$cost = $price_excl_tax + $tax_amount;
				$cost = number_format( (float) $cost, 2, '.', '' );
			}

			$recurring_amount_string = Helper::format_price_with_order_item( $cost, $order_item_id );

			$is_grace_period = isset( $subscription_data['grace_period'] );
			$grace_remaining = $subscription_data['grace_period']['remaining_days'] ?? 0;

			// TODO: Fix trial system and update the code accordingly.
			// $trial = isset( $subscription_data['trial'] );
			$trial = false;
			?>

			<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
				<thead>
					<tr>
						<th class="woocommerce-table__product-name product-name">
							<?php esc_html_e( 'Subscription ID', 'wp_subscription' ); ?>:
						</th>
						<th class="woocommerce-table__product-table product-total">
							<?php echo esc_html( $subscription_id ); ?>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Status', 'wp_subscription' ); ?>:
						</th>
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
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Recurring amount', 'wp_subscription' ); ?>:
						</th>
						<td class="woocommerce-table__product-total product-total">
							<?php echo wp_kses_post( $recurring_amount_string ); ?>
						</td>
					</tr>

					<?php if ( ! $trial ) : ?>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Next billing on', 'wp_subscription' ); ?>:
							</th>
							<td>
								<?php echo esc_html( $next_date ); ?>
							</td>
						</tr>
					<?php else : ?>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Trial', 'wp_subscription' ); ?>:
							</th>
							<td>
								<?php echo esc_html( get_post_meta( $history->subscription_id, '_subscrpt_trial', true ) ); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'First billing on', 'wp_subscription' ); ?>:
							</th>
							<td>
								<?php echo esc_html( $start_date ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
		
		<?php
		// End Render Subscription Details.
	}
}
