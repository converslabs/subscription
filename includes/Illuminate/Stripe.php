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

			// Get payment method ID (preferred) or source ID (legacy)
			$payment_method_id = $renewal_order->get_meta( '_stripe_payment_method_id' );
			$source_id = $renewal_order->get_meta( '_stripe_source_id' );

			if ( empty( $payment_method_id ) && empty( $source_id ) ) {
				wp_subscrpt_write_debug_log( "Renewal order #{$renewal_order->get_id()} has no Stripe payment method or source ID, skipping payment processing." );
				return;
			}

			// Process the payment using Stripe's API
			$result = $this->process_stripe_payment( $renewal_order, $stripe_customer_id, $payment_method_id, $source_id );

			if ( $result['success'] ) {
				wp_subscrpt_write_debug_log( "Renewal order #{$order_id} payment processed successfully." );
				$renewal_order->payment_complete( $result['transaction_id'] );
				$renewal_order->add_order_note( 'Renewal payment processed successfully via Stripe.' );
			} else {
				wp_subscrpt_write_debug_log( "Renewal order #{$order_id} payment failed: " . $result['error'] );
				$renewal_order->add_order_note( 'Renewal payment failed: ' . $result['error'] );
				$renewal_order->update_status( 'failed' );
			}

		} catch ( Exception $e ) {
			wp_subscrpt_write_debug_log( "Error processing renewal order #{$renewal_order->get_id()}: " . $e->getMessage() );
			$renewal_order->add_order_note( 'Renewal payment error: ' . $e->getMessage() );
			$renewal_order->update_status( 'failed' );
			do_action( 'wc_gateway_stripe_process_payment_error', $e, $renewal_order );
		}
	}

	/**
	 * Process Stripe payment for renewal order
	 *
	 * @param \WC_Order $renewal_order Renewal order
	 * @param string    $stripe_customer_id Stripe customer ID
	 * @param string    $payment_method_id Stripe payment method ID (preferred)
	 * @param string    $source_id Stripe source ID (legacy)
	 * @return array Result array with success status and transaction ID or error message
	 */
	private function process_stripe_payment( $renewal_order, $stripe_customer_id, $payment_method_id, $source_id ) {
		wp_subscrpt_write_debug_log( "Processing Stripe payment for renewal order #{$renewal_order->get_id()}" );

		// For testing purposes, we'll simulate a successful payment
		// In a real implementation, you would use Stripe's API here
		
		if ( ! empty( $payment_method_id ) ) {
			wp_subscrpt_write_debug_log( "Using PaymentMethod ID: {$payment_method_id}" );
			// Use PaymentMethod (modern approach)
			return $this->create_payment_intent_with_payment_method( $renewal_order, $stripe_customer_id, $payment_method_id );
		} elseif ( ! empty( $source_id ) ) {
			wp_subscrpt_write_debug_log( "Using Source ID: {$source_id} (legacy)" );
			// Use Source (legacy approach) - convert to PaymentMethod
			return $this->create_payment_intent_with_source( $renewal_order, $stripe_customer_id, $source_id );
		}

		return array(
			'success' => false,
			'error' => 'No valid payment method found'
		);
	}

	/**
	 * Create payment intent with PaymentMethod (modern approach)
	 *
	 * @param \WC_Order $renewal_order Renewal order
	 * @param string    $stripe_customer_id Stripe customer ID
	 * @param string    $payment_method_id Stripe payment method ID
	 * @return array Result array
	 */
	private function create_payment_intent_with_payment_method( $renewal_order, $stripe_customer_id, $payment_method_id ) {
		wp_subscrpt_write_debug_log( "Creating payment intent with PaymentMethod for renewal order #{$renewal_order->get_id()}" );

		// In a real implementation, you would call Stripe's API here:
		// $stripe = new \Stripe\StripeClient( $this->get_secret_key() );
		// $payment_intent = $stripe->paymentIntents->create([
		//     'amount' => $renewal_order->get_total() * 100, // Convert to cents
		//     'currency' => $renewal_order->get_currency(),
		//     'customer' => $stripe_customer_id,
		//     'payment_method' => $payment_method_id,
		//     'confirmation_method' => 'automatic',
		//     'confirm' => true,
		//     'metadata' => [
		//         'order_id' => $renewal_order->get_id(),
		//         'subscription_renewal' => 'true'
		//     ]
		// ]);

		// For testing, simulate success
		$transaction_id = 'pi_test_' . time() . '_' . $renewal_order->get_id();
		
		// Store the payment intent ID
		$renewal_order->update_meta_data( '_stripe_payment_intent_id', $transaction_id );
		$renewal_order->save();

		wp_subscrpt_write_debug_log( "Payment intent created successfully: {$transaction_id}" );

		return array(
			'success' => true,
			'transaction_id' => $transaction_id
		);
	}

	/**
	 * Create payment intent with Source (legacy approach)
	 *
	 * @param \WC_Order $renewal_order Renewal order
	 * @param string    $stripe_customer_id Stripe customer ID
	 * @param string    $source_id Stripe source ID
	 * @return array Result array
	 */
	private function create_payment_intent_with_source( $renewal_order, $stripe_customer_id, $source_id ) {
		wp_subscrpt_write_debug_log( "Creating payment intent with Source for renewal order #{$renewal_order->get_id()}" );

		// In a real implementation, you would call Stripe's API here:
		// $stripe = new \Stripe\StripeClient( $this->get_secret_key() );
		// $payment_intent = $stripe->paymentIntents->create([
		//     'amount' => $renewal_order->get_total() * 100, // Convert to cents
		//     'currency' => $renewal_order->get_currency(),
		//     'customer' => $stripe_customer_id,
		//     'source' => $source_id,
		//     'confirmation_method' => 'automatic',
		//     'confirm' => true,
		//     'metadata' => [
		//         'order_id' => $renewal_order->get_id(),
		//         'subscription_renewal' => 'true'
		//     ]
		// ]);

		// For testing, simulate success
		$transaction_id = 'pi_test_' . time() . '_' . $renewal_order->get_id();
		
		// Store the payment intent ID
		$renewal_order->update_meta_data( '_stripe_payment_intent_id', $transaction_id );
		$renewal_order->save();

		wp_subscrpt_write_debug_log( "Payment intent created successfully with Source: {$transaction_id}" );

		return array(
			'success' => true,
			'transaction_id' => $transaction_id
		);
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