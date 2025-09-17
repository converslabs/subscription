<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Stripe
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Stripe extends \WC_Payment_Gateway_Stripe_CC {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'subscrpt_after_create_renew_order', array( $this, 'after_create_renew_order' ), 10, 3 );
	}

	/**
	 * After create renew order
	 *
	 * @param \WC_Order $new_order New order.
	 * @param \WC_Order $old_order Old order.
	 * @param int       $subscription_id Subscription ID.
	 */
	public function after_create_renew_order( $new_order, $old_order, $subscription_id ) {
		$is_auto_renew  = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		$stripe_enabled = ( 'stripe_cc' === $old_order->get_payment_method() && in_array( $is_auto_renew, array( 1, '1' ), true ) && subscrpt_is_auto_renew_enabled() && '1' === get_option( 'subscrpt_stripe_auto_renew', '1' ) );

		if ( ! $stripe_enabled ) {
			return;
		}

		$this->pay_renew_order( $new_order );
	}

	/**
	 * Pay renewal Order
	 *
	 * @param \WC_Order $renewal_order Renewal order.
	 */
	public function pay_renew_order( $renewal_order ) {
		wp_subscrpt_write_debug_log( "Processing renewal order #{$renewal_order->get_id()} for payment." );

		try {
			// Simple validation
			if ( $renewal_order->get_total() <= 0 ) {
				wp_subscrpt_write_debug_log( "Renewal order #{$renewal_order->get_id()} has zero total, skipping payment." );
				return;
			}

			$amount   = $renewal_order->get_total();
			$order_id = $renewal_order->get_id();

			wp_subscrpt_write_debug_log( "Info: Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );

			// Check if payment method is set
			if ( ! $renewal_order->get_payment_method() ) {
				wp_subscrpt_write_debug_log( "Renewal order #{$renewal_order->get_id()} has no payment method, skipping payment processing." );
				return;
			}

			// Check if we have Stripe customer ID
			$stripe_customer_id = $renewal_order->get_meta( '_stripe_customer_id' );
			if ( empty( $stripe_customer_id ) ) {
				wp_subscrpt_write_debug_log( "Renewal order #{$renewal_order->get_id()} has no Stripe customer ID, skipping payment processing." );
				return;
			}

			// For now, just log that we would process the payment
			// In a real implementation, you would use Stripe's API to charge the customer
			wp_subscrpt_write_debug_log( "Renewal order #{$order_id} would be processed for payment with Stripe customer ID: {$stripe_customer_id}" );
			
			// TODO: Implement actual Stripe payment processing here
			// This would involve:
			// 1. Creating a payment intent with the customer's saved payment method
			// 2. Confirming the payment intent
			// 3. Updating the order status based on the result

		} catch ( Exception $e ) {
			wp_subscrpt_write_debug_log( "Error processing renewal order #{$renewal_order->get_id()}: " . $e->getMessage() );
			do_action( 'wc_gateway_stripe_process_payment_error', $e, $renewal_order );
		}
	}

	/**
	 * Check if order has subscription
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	private function has_subscription( $order_id ) {
		// Check if this order is related to a subscription
		global $wpdb;
		$subscription = $wpdb->get_var( $wpdb->prepare(
			"SELECT subscription_id FROM {$wpdb->prefix}subscrpt_order_relation WHERE order_id = %d",
			$order_id
		) );
		
		return ! empty( $subscription );
	}
}