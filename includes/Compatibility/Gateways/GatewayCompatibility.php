<?php
/**
 * Gateway Compatibility
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Gateways;

/**
 * GatewayCompatibility class.
 *
 * Adds subscription support to payment gateways.
 *
 * @package SpringDevs\Subscription\Compatibility\Gateways
 * @since   1.0.0
 */
class GatewayCompatibility {

	/**
	 * Initialize gateway compatibility.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		$instance = new self();
		$instance->setup_hooks();
	}

	/**
	 * Setup hooks.
	 *
	 * @since 1.0.0
	 */
	private function setup_hooks() {
		// Add subscription support to compatible gateways.
		add_action( 'woocommerce_payment_gateways_init', array( $this, 'add_subscription_support' ), 20 );

		// Hook into renewal payment processing.
		add_action( 'wpsubscription_process_renewal_payment', array( $this, 'process_renewal_payment' ), 10, 2 );
	}

	/**
	 * Add subscription support to compatible gateways.
	 *
	 * @since 1.0.0
	 */
	public function add_subscription_support() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		foreach ( $gateways as $gateway ) {
			if ( ! GatewayDetector::is_gateway_compatible( $gateway->id ) ) {
				continue;
			}

			// Add subscription support features.
			$subscription_features = array(
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change_admin',
				'subscription_payment_method_change_customer',
				'multiple_subscriptions',
			);

			foreach ( $subscription_features as $feature ) {
				if ( ! $gateway->supports( $feature ) ) {
					$gateway->supports[] = $feature;
				}
			}

			// Hook into scheduled subscription payment for this gateway.
			$hook_name = 'woocommerce_scheduled_subscription_payment_' . $gateway->id;
			if ( ! has_action( $hook_name, array( $this, 'handle_scheduled_payment' ) ) ) {
				add_action( $hook_name, array( $this, 'handle_scheduled_payment' ), 10, 2 );
			}
		}
	}

	/**
	 * Handle scheduled subscription payment.
	 *
	 * @since 1.0.0
	 * @param float    $amount Amount to charge.
	 * @param WC_Order $order Renewal order object.
	 */
	public function handle_scheduled_payment( $amount, $order ) {
		if ( ! $order ) {
			return;
		}

		// Get subscription ID from order.
		$subscription_id = $this->get_subscription_from_renewal_order( $order );

		if ( ! $subscription_id ) {
			return;
		}

		// Get payment method from subscription's parent order.
		$parent_order_id = get_post_meta( $subscription_id, '_subscrpt_order_id', true );
		if ( ! $parent_order_id ) {
			return;
		}

		$parent_order = wc_get_order( $parent_order_id );
		if ( ! $parent_order ) {
			return;
		}

		// Copy payment method to renewal order.
		$order->set_payment_method( $parent_order->get_payment_method() );
		$order->set_payment_method_title( $parent_order->get_payment_method_title() );

		// Copy payment meta (tokens, customer IDs, etc.).
		$this->copy_payment_meta( $parent_order, $order );

		$order->save();

		// Get the gateway and process payment.
		$gateway_id = $order->get_payment_method();
		$gateways   = WC()->payment_gateways()->payment_gateways();

		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			$order->add_order_note(
				__( 'Renewal payment failed: Payment gateway not found.', 'wp_subscription' )
			);
			return;
		}

		$gateway = $gateways[ $gateway_id ];

		// Check if gateway has scheduled_subscription_payment method.
		if ( method_exists( $gateway, 'scheduled_subscription_payment' ) ) {
			try {
				$result = $gateway->scheduled_subscription_payment( $amount, $order );

				if ( is_wp_error( $result ) ) {
					// Payment failed.
					$order->update_status( 'failed', $result->get_error_message() );
					do_action( 'subscrpt_payment_failed', $subscription_id, $order->get_id() );
				} else {
					// Payment successful.
					do_action( 'subscrpt_payment_success', $subscription_id, $order->get_id() );
				}
			} catch ( \Exception $e ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: error message */
						__( 'Renewal payment error: %s', 'wp_subscription' ),
						$e->getMessage()
					)
				);
			}
		}
	}

	/**
	 * Process renewal payment (alternative entry point).
	 *
	 * @since 1.0.0
	 * @param int $subscription_id Subscription ID.
	 * @param int $order_id Renewal order ID.
	 */
	public function process_renewal_payment( $subscription_id, $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$amount     = $order->get_total();
		$gateway_id = $order->get_payment_method();

		// Trigger the scheduled payment hook.
		do_action( 'woocommerce_scheduled_subscription_payment_' . $gateway_id, $amount, $order );
	}

	/**
	 * Get subscription ID from renewal order.
	 *
	 * @since  1.0.0
	 * @param  WC_Order $order Order object.
	 * @return int|null
	 */
	private function get_subscription_from_renewal_order( $order ) {
		// Check for subscription renewal meta.
		$subscription_id = $order->get_meta( '_subscription_renewal' );

		if ( ! $subscription_id ) {
			// Try to find subscription by order ID.
			$subscriptions   = \SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Order::get_subscription_ids_for_order( $order->get_id() );
			$subscription_id = ! empty( $subscriptions ) ? reset( $subscriptions ) : null;
		}

		return $subscription_id;
	}

	/**
	 * Copy payment meta from parent to renewal order.
	 *
	 * @since 1.0.0
	 * @param WC_Order $from_order Source order.
	 * @param WC_Order $to_order Destination order.
	 */
	private function copy_payment_meta( $from_order, $to_order ) {
		$payment_method = $from_order->get_payment_method();

		// Meta keys to copy based on gateway.
		$meta_keys_to_copy = array();

		// Stripe meta keys.
		if ( in_array( $payment_method, array( 'stripe_cc', 'stripe' ), true ) ) {
			$meta_keys_to_copy = array(
				'_stripe_customer_id',
				'_stripe_source_id',
				'_stripe_payment_method_id',
				'_payment_method_token',
			);
		}

		// PayPal meta keys.
		if ( in_array( $payment_method, array( 'paypal', 'ppec_paypal' ), true ) ) {
			$meta_keys_to_copy = array(
				'_paypal_billing_agreement_id',
				'_paypal_subscription_id',
			);
		}

		// Mollie meta keys.
		if ( strpos( $payment_method, 'mollie' ) !== false ) {
			$meta_keys_to_copy = array(
				'_mollie_customer_id',
				'_mollie_mandate_id',
			);
		}

		// Razorpay meta keys.
		if ( 'razorpay' === $payment_method ) {
			$meta_keys_to_copy = array(
				'_razorpay_subscription_id',
				'_razorpay_customer_id',
			);
		}

		// Copy the meta.
		foreach ( $meta_keys_to_copy as $meta_key ) {
			$meta_value = $from_order->get_meta( $meta_key );
			if ( $meta_value ) {
				$to_order->update_meta_data( $meta_key, $meta_value );
			}
		}
	}
}
