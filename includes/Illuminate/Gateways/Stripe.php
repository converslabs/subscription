<?php

namespace SpringDevs\Subscription\Illuminate\Gateways;

/**
 * Enhanced Stripe Gateway Integration
 *
 * Provides comprehensive Stripe integration for subscription payments
 * including PaymentIntents, SetupIntents, and webhook handling.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class Stripe {

	/**
	 * Stripe API instance
	 *
	 * @var \Stripe\StripeClient
	 */
	private $stripe;

	/**
	 * Gateway settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize Stripe integration
	 *
	 * @return void
	 */
	private function init() {
		// Get Stripe gateway settings
		$stripe_gateway = WC()->payment_gateways()->payment_gateways()['stripe_cc'] ?? null;
		if ( $stripe_gateway ) {
			$this->settings = $stripe_gateway->settings;
		}

		// Initialize Stripe API if available
		if ( class_exists( '\Stripe\StripeClient' ) && $this->get_secret_key() ) {
			$this->stripe = new \Stripe\StripeClient( $this->get_secret_key() );
		}

		// Register hooks
		$this->register_hooks();
	}

	/**
	 * Register Stripe-specific hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Webhook handling
		add_action( 'woocommerce_api_wc_stripe_webhook', array( $this, 'handle_webhook' ) );
		
		// Payment processing
		add_action( 'woocommerce_scheduled_subscription_payment_stripe_cc', array( $this, 'process_scheduled_payment' ), 10, 2 );
		
		// Payment method management
		add_action( 'subscrpt_payment_method_saved', array( $this, 'on_payment_method_saved' ), 10, 2 );
		add_action( 'subscrpt_payment_method_updated', array( $this, 'on_payment_method_updated' ), 10, 3 );
	}

	/**
	 * Process scheduled subscription payment
	 *
	 * @param float    $amount Payment amount
	 * @param \WC_Order $order Order object
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public function process_scheduled_payment( $amount, $order ) {
		wp_subscrpt_write_debug_log( "Stripe: Processing scheduled payment for order #{$order->get_id()}, amount: {$amount}" );

		try {
			// Get payment method token
			$payment_method_token = $order->get_meta( '_payment_method_token' );
			if ( empty( $payment_method_token ) ) {
				return new \WP_Error( 'no_payment_method', 'No payment method token found' );
			}

			// Get customer ID
			$customer_id = $order->get_meta( '_stripe_customer_id' );
			if ( empty( $customer_id ) ) {
				$customer_id = $this->get_or_create_customer( $order );
			}

			// Create PaymentIntent
			$payment_intent = $this->create_payment_intent( $order, $amount, $payment_method_token, $customer_id );

			if ( is_wp_error( $payment_intent ) ) {
				return $payment_intent;
			}

			// Confirm PaymentIntent
			$confirmed_intent = $this->confirm_payment_intent( $payment_intent->id, $payment_method_token );

			if ( is_wp_error( $confirmed_intent ) ) {
				return $confirmed_intent;
			}

			// Handle payment result
			if ( $confirmed_intent->status === 'succeeded' ) {
				$order->payment_complete( $confirmed_intent->id );
				$order->add_order_note( 'Stripe payment processed successfully via PaymentIntent' );
				
				wp_subscrpt_write_debug_log( "Stripe: Payment successful for order #{$order->get_id()}, PaymentIntent: {$confirmed_intent->id}" );
				return true;
			} else {
				$order->update_status( 'failed', 'Stripe payment failed: ' . $confirmed_intent->last_payment_error->message );
				return new \WP_Error( 'payment_failed', $confirmed_intent->last_payment_error->message );
			}

		} catch ( \Exception $e ) {
			wp_subscrpt_write_debug_log( "Stripe: Error processing payment for order #{$order->get_id()}: " . $e->getMessage() );
			$order->update_status( 'failed', 'Stripe payment error: ' . $e->getMessage() );
			return new \WP_Error( 'stripe_error', $e->getMessage() );
		}
	}

	/**
	 * Create PaymentIntent
	 *
	 * @param \WC_Order $order Order object
	 * @param float     $amount Payment amount
	 * @param string    $payment_method_token Payment method token
	 * @param string    $customer_id Customer ID
	 * @return \Stripe\PaymentIntent|WP_Error
	 */
	private function create_payment_intent( $order, $amount, $payment_method_token, $customer_id ) {
		try {
			$params = array(
				'amount'               => $this->get_stripe_amount( $amount, $order->get_currency() ),
				'currency'             => strtolower( $order->get_currency() ),
				'customer'             => $customer_id,
				'payment_method'       => $payment_method_token,
				'confirmation_method'  => 'manual',
				'confirm'              => false,
				'description'          => sprintf( 'Subscription renewal for order #%s', $order->get_id() ),
				'metadata'             => array(
					'order_id'       => $order->get_id(),
					'subscription_id' => $order->get_meta( '_subscription_id' ),
					'payment_type'   => 'subscription_renewal',
				),
			);

			$payment_intent = $this->stripe->paymentIntents->create( $params );

			wp_subscrpt_write_debug_log( "Stripe: Created PaymentIntent {$payment_intent->id} for order #{$order->get_id()}" );

			return $payment_intent;

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			wp_subscrpt_write_debug_log( "Stripe: Error creating PaymentIntent: " . $e->getMessage() );
			return new \WP_Error( 'stripe_api_error', $e->getMessage() );
		}
	}

	/**
	 * Confirm PaymentIntent
	 *
	 * @param string $payment_intent_id PaymentIntent ID
	 * @param string $payment_method_token Payment method token
	 * @return \Stripe\PaymentIntent|WP_Error
	 */
	private function confirm_payment_intent( $payment_intent_id, $payment_method_token ) {
		try {
			$payment_intent = $this->stripe->paymentIntents->confirm(
				$payment_intent_id,
				array(
					'payment_method' => $payment_method_token,
				)
			);

			wp_subscrpt_write_debug_log( "Stripe: Confirmed PaymentIntent {$payment_intent_id}, status: {$payment_intent->status}" );

			return $payment_intent;

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			wp_subscrpt_write_debug_log( "Stripe: Error confirming PaymentIntent: " . $e->getMessage() );
			return new \WP_Error( 'stripe_api_error', $e->getMessage() );
		}
	}

	/**
	 * Get or create Stripe customer
	 *
	 * @param \WC_Order $order Order object
	 * @return string|WP_Error Customer ID or error
	 */
	private function get_or_create_customer( $order ) {
		$customer_id = $order->get_meta( '_stripe_customer_id' );
		
		if ( $customer_id ) {
			return $customer_id;
		}

		try {
			$customer = $this->stripe->customers->create( array(
				'email' => $order->get_billing_email(),
				'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				'metadata' => array(
					'order_id' => $order->get_id(),
					'user_id'  => $order->get_user_id(),
				),
			) );

			$customer_id = $customer->id;
			$order->update_meta_data( '_stripe_customer_id', $customer_id );
			$order->save();

			wp_subscrpt_write_debug_log( "Stripe: Created customer {$customer_id} for order #{$order->get_id()}" );

			return $customer_id;

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			wp_subscrpt_write_debug_log( "Stripe: Error creating customer: " . $e->getMessage() );
			return new \WP_Error( 'stripe_customer_error', $e->getMessage() );
		}
	}

	/**
	 * Handle Stripe webhook
	 *
	 * @return void
	 */
	public function handle_webhook() {
		$payload = @file_get_contents( 'php://input' );
		$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

		// Verify webhook signature
		$event = $this->verify_webhook( $payload, $sig_header );
		if ( is_wp_error( $event ) ) {
			wp_die( $event->get_error_message(), 'Webhook Error', array( 'response' => 400 ) );
		}

		// Process webhook event
		$this->process_webhook_event( $event );
	}

	/**
	 * Verify webhook signature
	 *
	 * @param string $payload Webhook payload
	 * @param string $sig_header Signature header
	 * @return \Stripe\Event|WP_Error
	 */
	private function verify_webhook( $payload, $sig_header ) {
		$endpoint_secret = $this->get_webhook_secret();
		if ( ! $endpoint_secret ) {
			return new \WP_Error( 'no_webhook_secret', 'Webhook secret not configured' );
		}

		try {
			$event = \Stripe\Webhook::constructEvent( $payload, $sig_header, $endpoint_secret );
			return $event;
		} catch ( \UnexpectedValueException $e ) {
			return new \WP_Error( 'invalid_payload', 'Invalid payload' );
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			return new \WP_Error( 'invalid_signature', 'Invalid signature' );
		}
	}

	/**
	 * Process webhook event
	 *
	 * @param \Stripe\Event $event Stripe event
	 * @return void
	 */
	private function process_webhook_event( $event ) {
		wp_subscrpt_write_debug_log( "Stripe: Processing webhook event {$event->type}" );

		switch ( $event->type ) {
			case 'payment_intent.succeeded':
				$this->handle_payment_intent_succeeded( $event->data->object );
				break;

			case 'payment_intent.payment_failed':
				$this->handle_payment_intent_failed( $event->data->object );
				break;

			case 'customer.subscription.updated':
				$this->handle_subscription_updated( $event->data->object );
				break;

			case 'customer.subscription.deleted':
				$this->handle_subscription_deleted( $event->data->object );
				break;

			default:
				wp_subscrpt_write_debug_log( "Stripe: Unhandled webhook event type: {$event->type}" );
		}
	}

	/**
	 * Handle successful payment intent
	 *
	 * @param \Stripe\PaymentIntent $payment_intent PaymentIntent object
	 * @return void
	 */
	private function handle_payment_intent_succeeded( $payment_intent ) {
		$order_id = $payment_intent->metadata->order_id ?? null;
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Update order if not already completed
		if ( ! $order->is_paid() ) {
			$order->payment_complete( $payment_intent->id );
			$order->add_order_note( 'Payment confirmed via Stripe webhook' );
		}

		wp_subscrpt_write_debug_log( "Stripe: Payment succeeded for order #{$order_id}" );
	}

	/**
	 * Handle failed payment intent
	 *
	 * @param \Stripe\PaymentIntent $payment_intent PaymentIntent object
	 * @return void
	 */
	private function handle_payment_intent_failed( $payment_intent ) {
		$order_id = $payment_intent->metadata->order_id ?? null;
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$error_message = $payment_intent->last_payment_error->message ?? 'Payment failed';
		$order->update_status( 'failed', 'Stripe payment failed: ' . $error_message );

		wp_subscrpt_write_debug_log( "Stripe: Payment failed for order #{$order_id}: {$error_message}" );
	}

	/**
	 * Handle subscription updated
	 *
	 * @param \Stripe\Subscription $subscription Stripe subscription object
	 * @return void
	 */
	private function handle_subscription_updated( $subscription ) {
		// Handle Stripe subscription updates if needed
		wp_subscrpt_write_debug_log( "Stripe: Subscription updated: {$subscription->id}" );
	}

	/**
	 * Handle subscription deleted
	 *
	 * @param \Stripe\Subscription $subscription Stripe subscription object
	 * @return void
	 */
	private function handle_subscription_deleted( $subscription ) {
		// Handle Stripe subscription cancellation if needed
		wp_subscrpt_write_debug_log( "Stripe: Subscription deleted: {$subscription->id}" );
	}

	/**
	 * On payment method saved
	 *
	 * @param int   $subscription_id Subscription ID
	 * @param array $payment_method_data Payment method data
	 * @return void
	 */
	public function on_payment_method_saved( $subscription_id, $payment_method_data ) {
		if ( $payment_method_data['gateway_id'] !== 'stripe_cc' ) {
			return;
		}

		wp_subscrpt_write_debug_log( "Stripe: Payment method saved for subscription #{$subscription_id}" );
	}

	/**
	 * On payment method updated
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param string $gateway_id Gateway ID
	 * @param string $new_token New payment method token
	 * @return void
	 */
	public function on_payment_method_updated( $subscription_id, $gateway_id, $new_token ) {
		if ( $gateway_id !== 'stripe_cc' ) {
			return;
		}

		wp_subscrpt_write_debug_log( "Stripe: Payment method updated for subscription #{$subscription_id}" );
	}

	/**
	 * Get Stripe secret key
	 *
	 * @return string|false Secret key or false if not found
	 */
	private function get_secret_key() {
		if ( ! $this->settings ) {
			return false;
		}

		$testmode = $this->settings['testmode'] ?? 'yes';
		$secret_key = ( $testmode === 'yes' ) ? 
			( $this->settings['test_secret_key'] ?? '' ) : 
			( $this->settings['live_secret_key'] ?? '' );

		return ! empty( $secret_key ) ? $secret_key : false;
	}

	/**
	 * Get webhook secret
	 *
	 * @return string|false Webhook secret or false if not found
	 */
	private function get_webhook_secret() {
		if ( ! $this->settings ) {
			return false;
		}

		$testmode = $this->settings['testmode'] ?? 'yes';
		$webhook_secret = ( $testmode === 'yes' ) ? 
			( $this->settings['test_webhook_secret'] ?? '' ) : 
			( $this->settings['live_webhook_secret'] ?? '' );

		return ! empty( $webhook_secret ) ? $webhook_secret : false;
	}

	/**
	 * Convert amount to Stripe format
	 *
	 * @param float  $amount Amount
	 * @param string $currency Currency code
	 * @return int Amount in cents
	 */
	private function get_stripe_amount( $amount, $currency ) {
		// Zero decimal currencies
		$zero_decimal_currencies = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );
		
		if ( in_array( strtoupper( $currency ), $zero_decimal_currencies, true ) ) {
			return absint( $amount );
		}

		return absint( wc_format_decimal( $amount, 2 ) * 100 );
	}
}
