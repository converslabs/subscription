<?php

namespace SpringDevs\Subscription\Illuminate\Gateways;

/**
 * PayPal Gateway Integration
 *
 * Provides comprehensive PayPal integration for subscription payments
 * including PayPal Subscriptions API and webhook handling.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class PayPal {

	/**
	 * PayPal API instance
	 *
	 * @var \PayPal\Rest\ApiContext
	 */
	private $api_context;

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
	 * Initialize PayPal integration
	 *
	 * @return void
	 */
	private function init() {
		// Get PayPal gateway settings
		$paypal_gateway = WC()->payment_gateways()->payment_gateways()['paypal'] ?? null;
		if ( $paypal_gateway ) {
			$this->settings = $paypal_gateway->settings;
		}

		// Initialize PayPal API if available
		if ( class_exists( '\PayPal\Rest\ApiContext' ) && $this->get_client_id() ) {
			$this->api_context = $this->create_api_context();
		}

		// Register hooks
		$this->register_hooks();
	}

	/**
	 * Register PayPal-specific hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Webhook handling
		add_action( 'woocommerce_api_wc_paypal_webhook', array( $this, 'handle_webhook' ) );
		
		// Payment processing
		add_action( 'woocommerce_scheduled_subscription_payment_paypal', array( $this, 'process_scheduled_payment' ), 10, 2 );
		
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
		wp_subscrpt_write_debug_log( "PayPal: Processing scheduled payment for order #{$order->get_id()}, amount: {$amount}" );

		try {
			// Get payment method token (billing agreement ID)
			$billing_agreement_id = $order->get_meta( '_payment_method_token' );
			if ( empty( $billing_agreement_id ) ) {
				return new \WP_Error( 'no_payment_method', 'No billing agreement ID found' );
			}

			// Create payment using billing agreement
			$payment = $this->create_payment_from_billing_agreement( $order, $amount, $billing_agreement_id );

			if ( is_wp_error( $payment ) ) {
				return $payment;
			}

			// Execute payment
			$executed_payment = $this->execute_payment( $payment );

			if ( is_wp_error( $executed_payment ) ) {
				return $executed_payment;
			}

			// Handle payment result
			if ( $executed_payment->getState() === 'approved' ) {
				$transaction_id = $executed_payment->getId();
				$order->payment_complete( $transaction_id );
				$order->add_order_note( 'PayPal payment processed successfully via billing agreement' );
				
				wp_subscrpt_write_debug_log( "PayPal: Payment successful for order #{$order->get_id()}, transaction: {$transaction_id}" );
				return true;
			} else {
				$order->update_status( 'failed', 'PayPal payment failed' );
				return new \WP_Error( 'payment_failed', 'Payment was not approved' );
			}

		} catch ( \Exception $e ) {
			wp_subscrpt_write_debug_log( "PayPal: Error processing payment for order #{$order->get_id()}: " . $e->getMessage() );
			$order->update_status( 'failed', 'PayPal payment error: ' . $e->getMessage() );
			return new \WP_Error( 'paypal_error', $e->getMessage() );
		}
	}

	/**
	 * Create payment from billing agreement
	 *
	 * @param \WC_Order $order Order object
	 * @param float     $amount Payment amount
	 * @param string    $billing_agreement_id Billing agreement ID
	 * @return \PayPal\Api\Payment|WP_Error
	 */
	private function create_payment_from_billing_agreement( $order, $amount, $billing_agreement_id ) {
		try {
			$payer = new \PayPal\Api\Payer();
			$payer->setPaymentMethod( 'paypal' );

			$amount_obj = new \PayPal\Api\Amount();
			$amount_obj->setCurrency( $order->get_currency() );
			$amount_obj->setTotal( number_format( $amount, 2, '.', '' ) );

			$transaction = new \PayPal\Api\Transaction();
			$transaction->setAmount( $amount_obj );
			$transaction->setDescription( sprintf( 'Subscription renewal for order #%s', $order->get_id() ) );

			$payment = new \PayPal\Api\Payment();
			$payment->setIntent( 'sale' );
			$payment->setPayer( $payer );
			$payment->setTransactions( array( $transaction ) );

			// Set billing agreement
			$billing_agreement = new \PayPal\Api\BillingAgreementToken();
			$billing_agreement->setId( $billing_agreement_id );
			$payment->setBillingAgreementToken( $billing_agreement );

			$payment->create( $this->api_context );

			wp_subscrpt_write_debug_log( "PayPal: Created payment {$payment->getId()} for order #{$order->get_id()}" );

			return $payment;

		} catch ( \PayPal\Exception\PayPalConnectionException $e ) {
			wp_subscrpt_write_debug_log( "PayPal: Connection error creating payment: " . $e->getMessage() );
			return new \WP_Error( 'paypal_connection_error', $e->getMessage() );
		} catch ( \Exception $e ) {
			wp_subscrpt_write_debug_log( "PayPal: Error creating payment: " . $e->getMessage() );
			return new \WP_Error( 'paypal_error', $e->getMessage() );
		}
	}

	/**
	 * Execute payment
	 *
	 * @param \PayPal\Api\Payment $payment Payment object
	 * @return \PayPal\Api\Payment|WP_Error
	 */
	private function execute_payment( $payment ) {
		try {
			$execution = new \PayPal\Api\PaymentExecution();
			$execution->setPayerId( $payment->getPayer()->getPayerInfo()->getPayerId() );

			$result = $payment->execute( $execution, $this->api_context );

			wp_subscrpt_write_debug_log( "PayPal: Executed payment {$payment->getId()}, state: {$result->getState()}" );

			return $result;

		} catch ( \PayPal\Exception\PayPalConnectionException $e ) {
			wp_subscrpt_write_debug_log( "PayPal: Connection error executing payment: " . $e->getMessage() );
			return new \WP_Error( 'paypal_connection_error', $e->getMessage() );
		} catch ( \Exception $e ) {
			wp_subscrpt_write_debug_log( "PayPal: Error executing payment: " . $e->getMessage() );
			return new \WP_Error( 'paypal_error', $e->getMessage() );
		}
	}

	/**
	 * Handle PayPal webhook
	 *
	 * @return void
	 */
	public function handle_webhook() {
		$payload = @file_get_contents( 'php://input' );
		$headers = getallheaders();

		// Verify webhook signature
		$event = $this->verify_webhook( $payload, $headers );
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
	 * @param array  $headers Request headers
	 * @return \PayPal\Api\WebhookEvent|WP_Error
	 */
	private function verify_webhook( $payload, $headers ) {
		$webhook_id = $this->get_webhook_id();
		if ( ! $webhook_id ) {
			return new \WP_Error( 'no_webhook_id', 'Webhook ID not configured' );
		}

		try {
			$event = \PayPal\Api\WebhookEvent::get( $headers['PAYPAL-TRANSMISSION-ID'], $this->api_context );
			return $event;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'webhook_verification_error', $e->getMessage() );
		}
	}

	/**
	 * Process webhook event
	 *
	 * @param \PayPal\Api\WebhookEvent $event PayPal webhook event
	 * @return void
	 */
	private function process_webhook_event( $event ) {
		wp_subscrpt_write_debug_log( "PayPal: Processing webhook event {$event->getEventType()}" );

		switch ( $event->getEventType() ) {
			case 'PAYMENT.SALE.COMPLETED':
				$this->handle_payment_completed( $event );
				break;

			case 'PAYMENT.SALE.DENIED':
				$this->handle_payment_denied( $event );
				break;

			case 'BILLING.SUBSCRIPTION.CANCELLED':
				$this->handle_subscription_cancelled( $event );
				break;

			case 'BILLING.SUBSCRIPTION.SUSPENDED':
				$this->handle_subscription_suspended( $event );
				break;

			default:
				wp_subscrpt_write_debug_log( "PayPal: Unhandled webhook event type: {$event->getEventType()}" );
		}
	}

	/**
	 * Handle payment completed
	 *
	 * @param \PayPal\Api\WebhookEvent $event Webhook event
	 * @return void
	 */
	private function handle_payment_completed( $event ) {
		$resource = $event->getResource();
		$transaction_id = $resource->getId() ?? null;

		if ( ! $transaction_id ) {
			return;
		}

		// Find order by transaction ID
		$orders = wc_get_orders( array(
			'meta_key'   => '_transaction_id',
			'meta_value' => $transaction_id,
			'limit'      => 1,
		) );

		if ( empty( $orders ) ) {
			return;
		}

		$order = $orders[0];
		if ( ! $order->is_paid() ) {
			$order->payment_complete( $transaction_id );
			$order->add_order_note( 'Payment confirmed via PayPal webhook' );
		}

		wp_subscrpt_write_debug_log( "PayPal: Payment completed for order #{$order->get_id()}" );
	}

	/**
	 * Handle payment denied
	 *
	 * @param \PayPal\Api\WebhookEvent $event Webhook event
	 * @return void
	 */
	private function handle_payment_denied( $event ) {
		$resource = $event->getResource();
		$transaction_id = $resource->getId() ?? null;

		if ( ! $transaction_id ) {
			return;
		}

		// Find order by transaction ID
		$orders = wc_get_orders( array(
			'meta_key'   => '_transaction_id',
			'meta_value' => $transaction_id,
			'limit'      => 1,
		) );

		if ( empty( $orders ) ) {
			return;
		}

		$order = $orders[0];
		$reason = $resource->getReasonCode() ?? 'Payment denied';
		$order->update_status( 'failed', 'PayPal payment denied: ' . $reason );

		wp_subscrpt_write_debug_log( "PayPal: Payment denied for order #{$order->get_id()}: {$reason}" );
	}

	/**
	 * Handle subscription cancelled
	 *
	 * @param \PayPal\Api\WebhookEvent $event Webhook event
	 * @return void
	 */
	private function handle_subscription_cancelled( $event ) {
		$resource = $event->getResource();
		$subscription_id = $resource->getId() ?? null;

		if ( ! $subscription_id ) {
			return;
		}

		wp_subscrpt_write_debug_log( "PayPal: Subscription cancelled: {$subscription_id}" );
	}

	/**
	 * Handle subscription suspended
	 *
	 * @param \PayPal\Api\WebhookEvent $event Webhook event
	 * @return void
	 */
	private function handle_subscription_suspended( $event ) {
		$resource = $event->getResource();
		$subscription_id = $resource->getId() ?? null;

		if ( ! $subscription_id ) {
			return;
		}

		wp_subscrpt_write_debug_log( "PayPal: Subscription suspended: {$subscription_id}" );
	}

	/**
	 * On payment method saved
	 *
	 * @param int   $subscription_id Subscription ID
	 * @param array $payment_method_data Payment method data
	 * @return void
	 */
	public function on_payment_method_saved( $subscription_id, $payment_method_data ) {
		if ( $payment_method_data['gateway_id'] !== 'paypal' ) {
			return;
		}

		wp_subscrpt_write_debug_log( "PayPal: Payment method saved for subscription #{$subscription_id}" );
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
		if ( $gateway_id !== 'paypal' ) {
			return;
		}

		wp_subscrpt_write_debug_log( "PayPal: Payment method updated for subscription #{$subscription_id}" );
	}

	/**
	 * Create PayPal API context
	 *
	 * @return \PayPal\Rest\ApiContext
	 */
	private function create_api_context() {
		$api_context = new \PayPal\Rest\ApiContext(
			new \PayPal\Auth\OAuthTokenCredential(
				$this->get_client_id(),
				$this->get_client_secret()
			)
		);

		$api_context->setConfig( array(
			'mode' => $this->is_sandbox() ? 'sandbox' : 'live',
		) );

		return $api_context;
	}

	/**
	 * Get PayPal client ID
	 *
	 * @return string|false Client ID or false if not found
	 */
	private function get_client_id() {
		if ( ! $this->settings ) {
			return false;
		}

		$testmode = $this->settings['testmode'] ?? 'yes';
		$client_id = ( $testmode === 'yes' ) ? 
			( $this->settings['test_client_id'] ?? '' ) : 
			( $this->settings['live_client_id'] ?? '' );

		return ! empty( $client_id ) ? $client_id : false;
	}

	/**
	 * Get PayPal client secret
	 *
	 * @return string|false Client secret or false if not found
	 */
	private function get_client_secret() {
		if ( ! $this->settings ) {
			return false;
		}

		$testmode = $this->settings['testmode'] ?? 'yes';
		$client_secret = ( $testmode === 'yes' ) ? 
			( $this->settings['test_client_secret'] ?? '' ) : 
			( $this->settings['live_client_secret'] ?? '' );

		return ! empty( $client_secret ) ? $client_secret : false;
	}

	/**
	 * Get webhook ID
	 *
	 * @return string|false Webhook ID or false if not found
	 */
	private function get_webhook_id() {
		if ( ! $this->settings ) {
			return false;
		}

		$testmode = $this->settings['testmode'] ?? 'yes';
		$webhook_id = ( $testmode === 'yes' ) ? 
			( $this->settings['test_webhook_id'] ?? '' ) : 
			( $this->settings['live_webhook_id'] ?? '' );

		return ! empty( $webhook_id ) ? $webhook_id : false;
	}

	/**
	 * Check if sandbox mode
	 *
	 * @return bool True if sandbox mode
	 */
	private function is_sandbox() {
		if ( ! $this->settings ) {
			return true;
		}

		return ( $this->settings['testmode'] ?? 'yes' ) === 'yes';
	}
}
