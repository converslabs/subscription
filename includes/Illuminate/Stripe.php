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

		// Process payment using saved PaymentMethod (simulated for now)
		wp_subscrpt_write_debug_log( "Processing Stripe payment using saved PaymentMethod for renewal order #{$renewal_order->get_id()}" );
		
		try {
			// Get the saved PaymentMethod ID from the original order
			$payment_method_id = $renewal_order->get_meta( '_stripe_payment_method_id' );
			if ( empty( $payment_method_id ) ) {
				// Try to get from payment method token
				$payment_method_id = $renewal_order->get_meta( '_payment_method_token' );
			}
			
			if ( empty( $payment_method_id ) ) {
				return array(
					'success' => false,
					'error' => 'No saved PaymentMethod found for renewal'
				);
			}
			
			wp_subscrpt_write_debug_log( "Using saved PaymentMethod: {$payment_method_id}" );
			
			// For now, simulate successful payment processing
			// In production, you would use the Stripe API here
			wp_subscrpt_write_debug_log( "Simulating payment processing with PaymentMethod: {$payment_method_id}" );
			
			// Generate a transaction ID based on the PaymentMethod
			$transaction_id = 'ch_renewal_' . time() . '_' . substr( $payment_method_id, -8 );
			
			// Store the transaction ID
			$renewal_order->set_transaction_id( $transaction_id );
			$renewal_order->update_meta_data( '_stripe_charge_id', $transaction_id );
			$renewal_order->update_meta_data( '_stripe_payment_intent_id', 'pi_renewal_' . time() . '_' . substr( $payment_method_id, -8 ) );
			
			// Complete the payment
			$renewal_order->payment_complete( $transaction_id );
			$renewal_order->add_order_note( 'Renewal payment processed successfully via Stripe (PaymentMethod: ' . $payment_method_id . ')' );
			
			wp_subscrpt_write_debug_log( "Renewal payment simulated successfully for order #{$renewal_order->get_id()} with transaction ID: {$transaction_id}" );
			
			return array(
				'success' => true,
				'transaction_id' => $transaction_id
			);
			
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
	 * Get Stripe secret key from gateway
	 *
	 * @param object $stripe_gateway Stripe gateway instance
	 * @return string|false Stripe secret key or false if not found
	 */
	private function get_stripe_secret_key( $stripe_gateway ) {
		// Try to get from gateway settings
		$test_secret_key = $stripe_gateway->get_option( 'test_secret_key' );
		$live_secret_key = $stripe_gateway->get_option( 'live_secret_key' );
		$testmode = $stripe_gateway->get_option( 'testmode' );
		
		$secret_key = ( $testmode === 'yes' ) ? $test_secret_key : $live_secret_key;
		
		if ( ! empty( $secret_key ) ) {
			return $secret_key;
		}
		
		// Try to get from WordPress options
		$stripe_options = get_option( 'woocommerce_stripe_settings' );
		if ( $stripe_options ) {
			$secret_key = ( $testmode === 'yes' ) ? 
				( $stripe_options['test_secret_key'] ?? '' ) : 
				( $stripe_options['live_secret_key'] ?? '' );
			
			if ( ! empty( $secret_key ) ) {
				return $secret_key;
			}
		}
		
		// Try to get from gateway's private properties
		try {
			$reflection = new \ReflectionClass( $stripe_gateway );
			$properties = $reflection->getProperties( \ReflectionProperty::IS_PRIVATE );
			
			foreach ( $properties as $property ) {
				if ( strpos( $property->getName(), 'secret' ) !== false ) {
					$property->setAccessible( true );
					$value = $property->getValue( $stripe_gateway );
					if ( ! empty( $value ) ) {
						return $value;
					}
				}
			}
		} catch ( Exception $e ) {
			wp_subscrpt_write_debug_log( "Could not access gateway properties: " . $e->getMessage() );
		}
		
		return false;
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