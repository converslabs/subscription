<?php
/**
 * Subscription Customer Details
 *
 * @package wp_subscription
 */

?>

<style>
.customer-details {
	font-size: 13px;
	line-height: 1.4;
}

.customer-details .info-group {
	margin-bottom: 12px;
	padding-bottom: 12px;
	border-bottom: 1px solid #f0f0f1;
}

.customer-details .info-group:last-child {
	border-bottom: none;
	margin-bottom: 0;
	padding-bottom: 0;
}

.customer-details .label {
	color: #646970;
	font-weight: 600;
	margin-bottom: 4px;
	display: block;
}

.customer-details .value {
	color: #2c3338;
}

.customer-details a {
	color: #2271b1;
	text-decoration: none;
	box-shadow: none;
}

.customer-details a:hover {
	color: #135e96;
}

.customer-details .address {
	color: #50575e;
	font-size: 12px;
	line-height: 1.5;
	margin: 4px 0;
}

.customer-details .actions {
	margin-top: 12px;
}

.customer-details .button {
	font-size: 12px;
	height: auto;
	line-height: 2;
	padding: 0 8px;
	margin: 0 4px 4px 0;
	display: inline-block;
}

.customer-details .button-secondary {
	color: #50575e;
}
</style>

<div class="customer-details">
	<div class="info-group">
		<span class="label"><?php esc_html_e( 'Customer', 'wp_subscription' ); ?></span>
		<div class="value">
			<?php if ( $order->get_customer_id() ) : ?>
				<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $order->get_customer_id() ) ); ?>" target="_blank">
					<?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?>
				</a>
			<?php else : ?>
				<?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?>
			<?php endif; ?>
			<br>
			<a href="mailto:<?php echo esc_attr( $order->get_billing_email() ); ?>">
				<?php echo esc_html( $order->get_billing_email() ); ?>
			</a>
			<?php if ( ! empty( $order->get_billing_phone() ) ) : ?>
				<br>
				<a href="tel:<?php echo esc_attr( $order->get_billing_phone() ); ?>">
					<?php echo esc_html( $order->get_billing_phone() ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>

	<div class="info-group">
		<span class="label"><?php esc_html_e( 'Billing', 'wp_subscription' ); ?></span>
		<div class="value">
			<div class="address">
				<?php echo wp_kses_post( $order->get_formatted_billing_address() ? $order->get_formatted_billing_address() : __( 'No billing address set.', 'wp_subscription' ) ); ?>
			</div>
		</div>
	</div>

	<div class="info-group">
		<span class="label"><?php esc_html_e( 'Shipping', 'wp_subscription' ); ?></span>
		<div class="value">
			<div class="address">
				<?php echo wp_kses_post( $order->get_formatted_shipping_address() ? $order->get_formatted_shipping_address() : __( 'No shipping address set.', 'wp_subscription' ) ); ?>
			</div>
		</div>
	</div>

	<div class="info-group">
		<div class="actions">
			<a class="button button-primary" target="_blank" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
				<?php esc_html_e( 'View Order', 'wp_subscription' ); ?>
			</a>
			<a class="button button-secondary" target="_blank" href="<?php echo esc_url( wc_get_endpoint_url( 'view-subscription', get_the_ID(), wc_get_page_permalink( 'myaccount' ) ) ); ?>">
				<?php esc_html_e( 'View Frontend', 'wp_subscription' ); ?>
			</a>
		</div>
	</div>
</div>
