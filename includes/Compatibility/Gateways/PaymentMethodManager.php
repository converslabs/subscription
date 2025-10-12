<?php
/**
 * Payment Method Manager
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Gateways;

/**
 * PaymentMethodManager class.
 *
 * Manages payment method tokens for subscriptions.
 *
 * @package SpringDevs\Subscription\Compatibility\Gateways
 * @since   1.0.0
 */
class PaymentMethodManager {

	/**
	 * Save payment method for subscription.
	 *
	 * @since 1.0.0
	 * @param int      $subscription_id Subscription ID.
	 * @param WC_Order $order Original order.
	 */
	public static function save_payment_method( $subscription_id, $order ) {
		// Save payment method details from order to subscription meta.
		$payment_method = $order->get_payment_method();
		update_post_meta( $subscription_id, '_payment_method', $payment_method );
		update_post_meta( $subscription_id, '_payment_method_title', $order->get_payment_method_title() );

		// Save gateway-specific tokens/IDs.
		$meta_keys = array(
			'_stripe_customer_id',
			'_stripe_source_id',
			'_stripe_payment_method_id',
			'_paypal_billing_agreement_id',
			'_mollie_customer_id',
			'_razorpay_subscription_id',
		);

		foreach ( $meta_keys as $key ) {
			$value = $order->get_meta( $key );
			if ( $value ) {
				update_post_meta( $subscription_id, $key, $value );
			}
		}
	}

	/**
	 * Get payment method for subscription.
	 *
	 * @since  1.0.0
	 * @param  int    $subscription_id Subscription ID.
	 * @param  string $gateway_id Gateway ID.
	 * @return array
	 */
	public static function get_payment_method( $subscription_id, $gateway_id ) {
		return array(
			'payment_method'       => get_post_meta( $subscription_id, '_payment_method', true ),
			'payment_method_title' => get_post_meta( $subscription_id, '_payment_method_title', true ),
		);
	}
}
