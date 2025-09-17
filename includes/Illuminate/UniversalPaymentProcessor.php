<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Universal Payment Processor
 * 
 * This class works with ANY WooCommerce payment gateway
 * by using standard WooCommerce subscription hooks and APIs
 */
class UniversalPaymentProcessor {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'subscrpt_after_create_renew_order', array( $this, 'process_renewal_payment' ), 10, 3 );
		add_action( 'woocommerce_order_status_pending_to_processing', array( $this, 'maybe_process_subscription_payment' ), 10, 1 );
		add_action( 'woocommerce_order_status_pending_to_completed', array( $this, 'maybe_process_subscription_payment' ), 10, 1 );
	}

	/**
	 * Process renewal payment using the original order's payment gateway
	 *
	 * @param \WC_Order $new_order New renewal order
	 * @param \WC_Order $old_order Original order
	 * @param int       $subscription_id Subscription ID
	 */
	public function process_renewal_payment( $new_order, $old_order, $subscription_id ) {
		wp_subscrpt_write_debug_log( "Processing renewal payment for order #{$new_order->get_id()} using gateway from order #{$old_order->get_id()}" );

		// Get the payment gateway from the original order
		$payment_method = $old_order->get_payment_method();
		$gateway = WC()->payment_gateways()->payment_gateways()[ $payment_method ];

		if ( ! $gateway ) {
			wp_subscrpt_write_debug_log( "Payment gateway '{$payment_method}' not found for renewal order #{$new_order->get_id()}" );
			return;
		}

		// Check if gateway supports subscriptions
		if ( ! in_array( 'subscriptions', $gateway->supports ) ) {
			wp_subscrpt_write_debug_log( "Payment gateway '{$payment_method}' does not support subscriptions for renewal order #{$new_order->get_id()}" );
			return;
		}

		// Clone payment method and metadata from original order
		$this->clone_payment_data( $new_order, $old_order );

		// Use the gateway's scheduled_subscription_payment method if available
		if ( method_exists( $gateway, 'scheduled_subscription_payment' ) ) {
			wp_subscrpt_write_debug_log( "Using scheduled_subscription_payment for gateway '{$payment_method}' on renewal order #{$new_order->get_id()}" );
			
			// Call the gateway's subscription payment method
			$result = $gateway->scheduled_subscription_payment( $new_order->get_total(), $new_order );
			
			if ( $result ) {
				wp_subscrpt_write_debug_log( "Gateway '{$payment_method}' successfully processed renewal order #{$new_order->get_id()}" );
			} else {
				wp_subscrpt_write_debug_log( "Gateway '{$payment_method}' failed to process renewal order #{$new_order->get_id()}" );
			}
		} else {
			wp_subscrpt_write_debug_log( "Gateway '{$payment_method}' does not have scheduled_subscription_payment method for renewal order #{$new_order->get_id()}" );
		}
	}

	/**
	 * Maybe process subscription payment when order status changes
	 *
	 * @param int $order_id Order ID
	 */
	public function maybe_process_subscription_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if this is a renewal order
		global $wpdb;
		$subscription = $wpdb->get_row( $wpdb->prepare(
			"SELECT subscription_id FROM {$wpdb->prefix}subscrpt_order_relation WHERE order_id = %d AND type = 'renew'",
			$order_id
		) );

		if ( ! $subscription ) {
			return;
		}

		wp_subscrpt_write_debug_log( "Order #{$order_id} is a renewal order for subscription #{$subscription->subscription_id}" );

		// Get the payment gateway
		$payment_method = $order->get_payment_method();
		$gateway = WC()->payment_gateways()->payment_gateways()[ $payment_method ];

		if ( ! $gateway ) {
			return;
		}

		// Use the gateway's process_payment method
		if ( method_exists( $gateway, 'process_payment' ) ) {
			wp_subscrpt_write_debug_log( "Processing payment for renewal order #{$order_id} using gateway '{$payment_method}'" );
			
			$result = $gateway->process_payment( $order_id );
			
			if ( isset( $result['result'] ) && $result['result'] === 'success' ) {
				wp_subscrpt_write_debug_log( "Gateway '{$payment_method}' successfully processed renewal order #{$order_id}" );
			} else {
				wp_subscrpt_write_debug_log( "Gateway '{$payment_method}' failed to process renewal order #{$order_id}: " . ( isset( $result['message'] ) ? $result['message'] : 'Unknown error' ) );
			}
		}
	}

	/**
	 * Clone payment data from original order to renewal order
	 *
	 * @param \WC_Order $new_order New order
	 * @param \WC_Order $old_order Original order
	 */
	private function clone_payment_data( $new_order, $old_order ) {
		// Set payment method
		$new_order->set_payment_method( $old_order->get_payment_method() );
		$new_order->set_payment_method_title( $old_order->get_payment_method_title() );

		// Clone all payment-related metadata
		$meta_keys = array(
			'_stripe_customer_id',
			'_stripe_source_id',
			'_stripe_payment_intent_id',
			'_stripe_charge_id',
			'_stripe_subscription_id',
			'_paypal_subscription_id',
			'_paypal_transaction_id',
			'_authorize_net_customer_id',
			'_authorize_net_payment_profile_id',
			'_square_customer_id',
			'_square_payment_id',
			// Add more gateway-specific meta keys as needed
		);

		foreach ( $meta_keys as $meta_key ) {
			$value = $old_order->get_meta( $meta_key );
			if ( ! empty( $value ) ) {
				$new_order->update_meta_data( $meta_key, $value );
			}
		}

		$new_order->save();

		wp_subscrpt_write_debug_log( "Payment data cloned from order #{$old_order->get_id()} to renewal order #{$new_order->get_id()}" );
	}

	/**
	 * Get supported payment gateways for subscriptions
	 *
	 * @return array Array of gateway IDs that support subscriptions
	 */
	public static function get_supported_gateways() {
		$gateways = WC()->payment_gateways()->payment_gateways();
		$supported = array();

		foreach ( $gateways as $gateway ) {
			if ( in_array( 'subscriptions', $gateway->supports ) ) {
				$supported[] = $gateway->id;
			}
		}

		return $supported;
	}
}
