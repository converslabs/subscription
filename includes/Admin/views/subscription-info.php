<?php
use SpringDevs\Subscription\Illuminate\Helper;
/*
STYLE GUIDE FOR WP SUBSCRIPTION ADMIN PAGES:
- Use .wp-subscription-admin-content for main content area.
- Use .wp-subscription-admin-box for white card/box with shadow and 6-8px border-radius.
- Use .widefat.wp-subscription-list-table for tables, with no outer border, only row bottom borders.
- Use compact table cells: font-size 14px, padding 8px 10px.
- Use .button, .button-primary, .button-small for actions, with rounded corners, soft backgrounds, and subtle hover.
- Use pill-shaped status labels (e.g., .subscrpt-active) for status.
- Use Georgia, serif for titles, system sans-serif for body.
- Keep all sections visually consistent, compact, and modern.
- Avoid excessive spacing or large paddings.
- All new UI/UX changes must follow these conventions.
*/
if ( ! isset( $post ) || ! is_object( $post ) ) {
	global $post;
}
$order_id         = get_post_meta( $post->ID, '_subscrpt_order_id', true );
$order            = wc_get_order( $order_id );
$order_item_id    = get_post_meta( $post->ID, '_subscrpt_order_item_id', true );
$order_item       = $order ? $order->get_item( $order_item_id ) : null;
$product_name     = $order_item ? $order_item->get_name() : '-';
$cost             = $order_item ? Helper::format_price_with_order_item( get_post_meta( $post->ID, '_subscrpt_price', true ), $order_item_id ) : '-';
$qty              = $order_item ? 'x' . $order_item->get_quantity() : '-';
$customer         = $order ? $order->get_formatted_billing_full_name() : '-';
$customer_id      = $order ? $order->get_customer_id() : 0;
$customer_url     = $customer_id ? admin_url( 'user-edit.php?user_id=' . $customer_id ) : '';
$start_date       = get_post_meta( $post->ID, '_subscrpt_start_date', true );
$end_date         = get_post_meta( $post->ID, '_subscrpt_end_date', true );
$renewal_date     = get_post_meta( $post->ID, '_subscrpt_next_date', true );
$status_obj       = get_post_status_object( get_post_status( $post->ID ) );
$payment_method   = $order ? $order->get_payment_method_title() : '-';
$billing_address  = $order ? $order->get_formatted_billing_address() : '-';
$shipping_address = $order ? $order->get_formatted_shipping_address() : '-';


?>
<div class="wp-subscription-admin-content">
	<div class="wp-subscription-info-grid" style="display:flex;gap:28px;align-items:flex-start;flex-wrap:nowrap;">
		<!-- Left: Main Info -->
		<div class="wp-subscription-admin-box wp-subscription-info-left" style="    box-shadow: 0 1px 3px rgb(29 35 39 / 30%);flex:1 1 50%;max-width:50%;margin:25px 0 18px 18px;">
			<table class="widefat striped wp-subscription-list-table" style="border-radius:8px;overflow:hidden;margin-bottom:16px;">
				<tbody>
					<tr>
						<th style="width:120px;padding:8px 10px;">Product</th>
						<td style="padding:8px 10px;"><?php echo esc_html( $product_name ); ?></td>
					</tr>
					<tr>
						<th style="padding:8px 10px;">Cost</th>
						<td style="padding:8px 10px;"><?php echo $cost; ?></td>
					</tr>
					<tr>
						<th style="padding:8px 10px;">Qty</th>
						<td style="padding:8px 10px;"><?php echo esc_html( $qty ); ?></td>
					</tr>
					
					<?php
					// Display payment information if max_payments is set
					$product_id   = get_post_meta( $post->ID, '_subscrpt_product_id', true );
					$max_payments = subscrpt_get_max_payments( $post->ID ) ?: 0;

					// Get payment type information first (needed for condition)
					$payment_type = $product_id ? get_post_meta( $product_id, '_subscrpt_payment_type', true ) : 'recurring';
					$payment_type = $payment_type ?: 'recurring'; // Default to recurring if empty

					// Also check subscription's own meta data
					$subscription_payment_type = get_post_meta( $post->ID, '_subscrpt_payment_type', true );
					$subscription_max_payments = get_post_meta( $post->ID, '_subscrpt_max_no_payment', true );

					// Also check if this subscription has variation data
					$variation_id = get_post_meta( $post->ID, '_subscrpt_variation_id', true );
					if ( $variation_id ) {
						$variation_payment_type = get_post_meta( $variation_id, '_subscrpt_payment_type', true );
						$variation_max_payments = get_post_meta( $variation_id, '_subscrpt_max_no_payment', true );

						// Use variation data if product data is not available
						if ( empty( $payment_type ) || 'recurring' === $payment_type ) {
							if ( ! empty( $variation_payment_type ) ) {
								$payment_type = $variation_payment_type;
							}
						}
						if ( ! $max_payments && ! empty( $variation_max_payments ) ) {
							$max_payments = $variation_max_payments;
						}
					}

					// Use subscription's own data if available and more specific
					if ( ! empty( $subscription_payment_type ) ) {
						$payment_type = $subscription_payment_type;
					}
					if ( ! empty( $subscription_max_payments ) ) {
						$max_payments = $subscription_max_payments;
					}

					// Final fallback: Infer payment type from max_payments if not explicitly set
					if ( ( empty( $payment_type ) || 'recurring' === $payment_type ) && $max_payments > 0 ) {
						$payment_type = 'split_payment';
					}

					// Ensure max_payments is properly set for display
					$max_payments = (int) $max_payments;

					// Show Total Payments if conditions are met
					if ( ( ! empty( $max_payments ) && $max_payments > 0 ) || ( 'split_payment' === $payment_type && $max_payments > 0 ) ) :
						$payments_made = subscrpt_count_payments_made( $post->ID );
						?>
					<tr>
						<th style="padding:8px 10px;"><?php esc_html_e( 'Total Payments', 'wp_subscription' ); ?></th>
						<td style="padding:8px 10px;"><?php echo esc_html( $payments_made ) . ' / ' . esc_html( $max_payments ); ?></td>
					</tr>
					<?php endif; ?>
					
					<?php
					// Display Payment Type and Access Duration information
					$payment_type = $product_id ? get_post_meta( $product_id, '_subscrpt_payment_type', true ) : 'recurring';
					$payment_type = $payment_type ?: 'recurring'; // Default to recurring if empty

					// Also check subscription's own meta data
					$subscription_payment_type = get_post_meta( $post->ID, '_subscrpt_payment_type', true );
					$subscription_max_payments = get_post_meta( $post->ID, '_subscrpt_max_no_payment', true );

					// Also check if this subscription has variation data
					$variation_id = get_post_meta( $post->ID, '_subscrpt_variation_id', true );
					if ( $variation_id ) {
						$variation_payment_type = get_post_meta( $variation_id, '_subscrpt_payment_type', true );
						$variation_max_payments = get_post_meta( $variation_id, '_subscrpt_max_no_payment', true );

						// Use variation data if product data is not available
						if ( empty( $payment_type ) || 'recurring' === $payment_type ) {
							if ( ! empty( $variation_payment_type ) ) {
								$payment_type = $variation_payment_type;
							}
						}
						if ( ! $max_payments && ! empty( $variation_max_payments ) ) {
							$max_payments = $variation_max_payments;
						}
					}

					// Use subscription's own data if available and more specific
					if ( ! empty( $subscription_payment_type ) ) {
						$payment_type = $subscription_payment_type;
					}
					if ( ! empty( $subscription_max_payments ) ) {
						$max_payments = $subscription_max_payments;
					}

					// Final fallback: Infer payment type from max_payments if not explicitly set
					if ( ( empty( $payment_type ) || 'recurring' === $payment_type ) && $max_payments > 0 ) {
						$payment_type = 'split_payment';
					}

					// Ensure max_payments is properly set for display
					$max_payments = (int) $max_payments;
					?>
					<tr>
						<th style="padding:8px 10px;"><?php esc_html_e( 'Payment Type', 'wp_subscription' ); ?></th>
						<td style="padding:8px 10px;">
							<?php
							if ( 'split_payment' === $payment_type ) {
								esc_html_e( 'Split Payment', 'wp_subscription' );
							} else {
								esc_html_e( 'Recurring', 'wp_subscription' );
							}
							?>
						</td>
					</tr>
					
					<!-- Access Duration Information for Split Payments -->
					<?php if ( 'split_payment' === $payment_type && ! empty( $max_payments ) && $max_payments > 0 ) : ?>
						<?php
						$access_ends_timing   = get_post_meta( $product_id, '_subscrpt_access_ends_timing', true ) ?: 'after_full_duration';
						$custom_duration_time = get_post_meta( $product_id, '_subscrpt_custom_access_duration_time', true ) ?: 1;
						$custom_duration_type = get_post_meta( $product_id, '_subscrpt_custom_access_duration_type', true ) ?: 'months';

						// Calculate access end date if Pro version is available
						$access_end_date_string = null;
						if ( function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated() ) {
							if ( class_exists( '\SpringDevs\SubscriptionPro\Illuminate\SplitPaymentHandler' ) ) {
								$access_end_date_string = \SpringDevs\SubscriptionPro\Illuminate\SplitPaymentHandler::get_access_end_date_string( $post->ID );
							}
						}
						?>
						<tr>
							<th style="padding:8px 10px;"><?php esc_html_e( 'Access Duration', 'wp_subscription' ); ?></th>
							<td style="padding:8px 10px;">
								<?php
								switch ( $access_ends_timing ) {
									case 'lifetime':
										esc_html_e( 'Lifetime access after completion', 'wp_subscription' );
										break;
									case 'after_full_duration':
										esc_html_e( 'Full subscription duration', 'wp_subscription' );
										break;
									case 'custom_duration':
										printf(
											/* translators: %1$s: duration time, %2$s: duration type */
											esc_html__( '%1$s %2$s after first payment', 'wp_subscription' ),
											esc_html( $custom_duration_time ),
											esc_html( Helper::get_typos( $custom_duration_time, $custom_duration_type ) )
										);
										break;
									default:
										esc_html_e( 'Full subscription duration', 'wp_subscription' );
										break;
								}
								?>
							</td>
						</tr>
						
						<!-- Show calculated access end date if available -->
						<?php if ( $access_end_date_string ) : ?>
						<tr>
							<th style="padding:8px 10px;"><?php esc_html_e( 'Access Ends On', 'wp_subscription' ); ?></th>
							<td style="padding:8px 10px;"><?php echo esc_html( $access_end_date_string ); ?></td>
						</tr>
						<?php endif; ?>
					<?php endif; ?>
					
					<tr>
						<th style="padding:8px 10px;">Started date</th>
						<td style="padding:8px 10px;"><?php echo $start_date ? esc_html( gmdate( 'F d, Y', $start_date ) ) : '-'; ?></td>
					</tr>
					<tr>
						<th style="padding:8px 10px;">Payment due date</th>
						<td style="padding:8px 10px;"><?php echo $renewal_date ? esc_html( gmdate( 'F d, Y', $renewal_date ) ) : '-'; ?></td>
					</tr>
					<tr>
						<th style="padding:8px 10px;">Status</th>
						<td style="padding:8px 10px;"><span class="subscrpt-<?php echo esc_attr( $status_obj->name ); ?>"><?php echo esc_html( $status_obj->label ); ?></span></td>
					</tr>
					<tr>
						<th style="padding:8px 10px;">Payment Method</th>
						<td style="padding:8px 10px;"><?php echo esc_html( $payment_method ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<!-- Right: Billing & Shipping -->
		<div class="wp-subscription-admin-box wp-subscription-info-right" style="    box-shadow: 0 1px 3px rgb(29 35 39 / 30%);background:#fff;flex:1 1 50%;max-width:50%;margin:25px 0 18px 0;">
			<table class="widefat wp-subscription-list-table" style="border-radius:8px;overflow:hidden;margin-bottom:0;background:none;">
				<tbody>
					<tr>
						<th style="width:70px;padding:8px 10px;">Billing</th>
						<td style="padding:8px 10px;"><?php echo $billing_address ? wp_kses_post( $billing_address ) : '-'; ?></td>
					</tr>
					<tr>
						<th style="width:70px;padding:8px 10px;">Shipping</th>
						<td style="padding:8px 10px;"><?php echo $shipping_address ? wp_kses_post( $shipping_address ) : '-'; ?></td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
<style>
@media (max-width: 900px) {
	.wp-subscription-info-left, .wp-subscription-info-right {
	flex-basis: 100% !important;
	max-width: 100% !important;
	margin-left: 0 !important;
	margin-right: 0 !important;
	}
	.wp-subscription-info-grid {
	flex-direction: column !important;
	gap: 18px !important;
	}
}
</style>
