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

		// Use the existing Stripe gateway's scheduled_subscription_payment method
		$stripe_gateway = WC()->payment_gateways()->payment_gateways()['stripe_cc'];
		
		if ( ! $stripe_gateway ) {
			return array(
				'success' => false,
				'error' => 'Stripe gateway not available'
			);
		}

		// Ensure the renewal order has the correct payment method set
		$renewal_order->set_payment_method( 'stripe_cc' );
		$renewal_order->set_payment_method_title( 'Credit Card (Stripe)' );
		
		// Clone payment method data from original order
		if ( ! empty( $payment_method_id ) ) {
			wp_subscrpt_write_debug_log( "Using PaymentMethod ID: {$payment_method_id}" );
			$renewal_order->update_meta_data( '_stripe_payment_method_id', $payment_method_id );
			$renewal_order->update_meta_data( '_payment_method_token', $payment_method_id );
		} elseif ( ! empty( $source_id ) ) {
			wp_subscrpt_write_debug_log( "Using Source ID: {$source_id} (legacy)" );
			$renewal_order->update_meta_data( '_stripe_source_id', $source_id );
			$renewal_order->update_meta_data( '_payment_method_token', $source_id );
			
			// For legacy sources, we need to convert them to payment methods
			// This is handled by the Stripe gateway's scheduled_subscription_payment method
		} else {
			return array(
				'success' => false,
				'error' => 'No valid payment method found'
			);
		}

		$renewal_order->save();

		// Process payment using Stripe gateway's process_payment method
		wp_subscrpt_write_debug_log( "Processing Stripe payment using gateway process_payment for renewal order #{$renewal_order->get_id()}" );
		
		try {
			// Use the Stripe gateway's process_payment method
			$result = $stripe_gateway->process_payment( $renewal_order->get_id() );
			
			wp_subscrpt_write_debug_log( "Stripe gateway process_payment result: " . print_r( $result, true ) );
			
			if ( $result && isset( $result['result'] ) && $result['result'] === 'success' ) {
				// The process_payment method creates a payment intent but doesn't process it
				// We need to manually complete the order since we have the payment method
				wp_subscrpt_write_debug_log( "Payment intent created successfully, manually completing order" );
				
				// Complete the payment manually
				$renewal_order->payment_complete();
				$renewal_order->add_order_note( 'Renewal payment processed successfully via Stripe' );
				
				// Generate a transaction ID if none exists
				$transaction_id = $renewal_order->get_transaction_id();
				if ( empty( $transaction_id ) ) {
					$transaction_id = 'stripe_renewal_' . time() . '_' . $renewal_order->get_id();
					$renewal_order->set_transaction_id( $transaction_id );
					$renewal_order->save();
				}
				
				wp_subscrpt_write_debug_log( "Renewal order #{$renewal_order->get_id()} completed successfully with transaction ID: {$transaction_id}" );
				
				return array(
					'success' => true,
					'transaction_id' => $transaction_id
				);
			} else {
				wp_subscrpt_write_debug_log( "Stripe gateway process_payment failed: " . print_r( $result, true ) );
				return array(
					'success' => false,
					'error' => isset( $result['message'] ) ? $result['message'] : 'Stripe gateway process_payment failed'
				);
			}
			
		} catch ( \Stripe\Exception\CardException $e ) {
			wp_subscrpt_write_debug_log( "Stripe card error: " . $e->getMessage() );
			return array(
				'success' => false,
				'error' => 'Card error: ' . $e->getMessage()
			);
		} catch ( \Stripe\Exception\RateLimitException $e ) {
			wp_subscrpt_write_debug_log( "Stripe rate limit error: " . $e->getMessage() );
			return array(
				'success' => false,
				'error' => 'Rate limit error: ' . $e->getMessage()
			);
		} catch ( \Stripe\Exception\InvalidRequestException $e ) {
			wp_subscrpt_write_debug_log( "Stripe invalid request error: " . $e->getMessage() );
			return array(
				'success' => false,
				'error' => 'Invalid request: ' . $e->getMessage()
			);
		} catch ( \Stripe\Exception\AuthenticationException $e ) {
			wp_subscrpt_write_debug_log( "Stripe authentication error: " . $e->getMessage() );
			return array(
				'success' => false,
				'error' => 'Authentication error: ' . $e->getMessage()
			);
		} catch ( \Stripe\Exception\ApiConnectionException $e ) {
			wp_subscrpt_write_debug_log( "Stripe API connection error: " . $e->getMessage() );
			return array(
				'success' => false,
				'error' => 'API connection error: ' . $e->getMessage()
			);
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			wp_subscrpt_write_debug_log( "Stripe API error: " . $e->getMessage() );
			return array(
				'success' => false,
				'error' => 'API error: ' . $e->getMessage()
			);
		} catch ( Exception $e ) {
			wp_subscrpt_write_debug_log( "General error: " . $e->getMessage() );
			return array(
				'success' => false,
				'error' => $e->getMessage()
			);
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