<?php
/**
 * Related subscriptions for email.
 *
 * @var \WC_Order $order Order Object.
 * @var object[] $histories Order Object.
 *
 * @package SpringDevs\Subscription\Illuminate\Email
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$subscrpt_th_style = 'width:40%;padding:10px 14px;text-align:left;vertical-align:top;font-weight:600;color:#50575e;background-color:#f6f7f7;';
$subscrpt_td_style = 'padding:10px 14px;text-align:left;vertical-align:top;color:#1d2327;';
$subscrpt_divider  = 'border-top:1px solid #e3e3e8;';
?>

<div style="margin-bottom:40px;">
	<h2 style="margin:0 0 8px;"><?php esc_html_e( 'Related Subscriptions', 'subscription' ); ?></h2>

	<?php if ( ! $order->has_status( 'completed' ) ) : ?>
		<p style="margin:0 0 16px;"><small><?php esc_html_e( 'Your subscription will be activated when order status is completed.', 'subscription' ); ?></small></p>
	<?php endif; ?>

	<?php
	foreach ( $histories as $history ) :
		$item = $order->get_item( $history->order_item_id );

		// Skip if item is not found.
		if ( ! $item ) {
			continue;
		}

		$item_meta                  = wc_get_order_item_meta( $history->order_item_id, '_subscrpt_meta', true );
		$subscription_id            = $history->subscription_id;
		$subscription_status_object = get_post_status_object( get_post_status( $subscription_id ) );
		$has_trial                  = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;
		$start_date                 = get_post_meta( $subscription_id, '_subscrpt_start_date', true );
		?>
		<div style="margin-bottom:24px;">
			<p style="margin:0 0 6px;font-weight:600;color:#1d2327;"><?php echo wp_kses_post( get_the_title( $subscription_id ) ); ?></p>

			<table cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:separate;border-spacing:0;border:1px solid #e3e3e8;border-radius:6px;overflow:hidden;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:14px;">
				<tbody>
					<tr>
						<th scope="row" style="<?php echo esc_attr( $subscrpt_th_style ); ?>"><?php esc_html_e( 'Item', 'subscription' ); ?></th>
						<td style="<?php echo esc_attr( $subscrpt_td_style ); ?>">
							<a href="<?php echo esc_url( get_permalink( $item->get_product_id() ) ); ?>" style="color:#7f54b3;"><?php echo wp_kses_post( $item->get_name() ); ?></a>
							<strong class="product-quantity">&times;&nbsp;<?php echo esc_html( $item->get_quantity() ); ?></strong>
						</td>
					</tr>
					<tr>
						<th scope="row" style="<?php echo esc_attr( $subscrpt_th_style . $subscrpt_divider ); ?>"><?php esc_html_e( 'Status', 'subscription' ); ?></th>
						<td style="<?php echo esc_attr( $subscrpt_td_style . $subscrpt_divider ); ?>"><?php echo esc_html( $subscription_status_object->label ); ?></td>
					</tr>
					<tr>
						<th scope="row" style="<?php echo esc_attr( $subscrpt_th_style . $subscrpt_divider ); ?>"><?php esc_html_e( 'Recurring amount', 'subscription' ); ?></th>
						<td style="<?php echo esc_attr( $subscrpt_td_style . $subscrpt_divider ); ?>">
							<?php
							// Strikes the original amount when a discount carries into renewals.
							echo wp_kses_post(
								SpringDevs\Subscription\Illuminate\Helper::get_subscription_recurring_price_html(
									$subscription_id,
									$item,
									[ 'del_style' => 'color: #999999;' ]
								)
							);
							?>
						</td>
					</tr>
					<?php if ( $has_trial ) : ?>
						<tr>
							<th scope="row" style="<?php echo esc_attr( $subscrpt_th_style . $subscrpt_divider ); ?>"><?php esc_html_e( 'Trial', 'subscription' ); ?></th>
							<td style="<?php echo esc_attr( $subscrpt_td_style . $subscrpt_divider ); ?>"><?php echo esc_html( $item_meta['trial'] ); ?></td>
						</tr>
						<tr>
							<th scope="row" style="<?php echo esc_attr( $subscrpt_th_style . $subscrpt_divider ); ?>"><?php esc_html_e( 'First billing on', 'subscription' ); ?></th>
							<td style="<?php echo esc_attr( $subscrpt_td_style . $subscrpt_divider ); ?>"><?php echo ! empty( $start_date ) ? esc_html( wp_date( 'F d, Y', $start_date ) ) : '-'; ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	endforeach;
	?>
</div>
