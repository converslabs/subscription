<?php

namespace SpringDevs\Subscription\Illuminate\Gateways;

/**
 * Square Gateway Integration
 *
 * Provides Square integration for subscription payments
 * including Square Payments API and webhook handling.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class Square {

	/**
	 * Square API instance
	 *
	 * @var \Square\SquareClient
	 */
	private $square_client;

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
	 * Initialize Square integration
	 *
	 * @return void
	 */
	private function init() {
		// Get Square gateway settings
		$square_gateway = WC()->payment_gateways()->payment_gateways()['square'] ?? null;
		if ( $square_gateway ) {
			$this->settings = $square_gateway->settings;
		}

		// Initialize Square API if available
		if ( class_exists( '\Square\SquareClient' ) && $this->get_access_token() ) {
			$this->square_client = new \Square\SquareClient( array(
				'accessToken' => $this->get_access_token(),
				'environment' => $this->is_sandbox() ? \Square\Environment::SANDBOX : \Square\Environment::PRODUCTION,
			) );
		}

		// Register hooks
		$this->register_hooks();
	}

	/**
	 * Register Square-specific hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Webhook handling
		add_action( 'woocommerce_api_wc_square_webhook', array( $this, 'handle_webhook' ) );
		
		// Payment processing
		add_action( 'woocommerce_scheduled_subscription_payment_square', array( $this, 'process_scheduled_payment' ), 10, 2 );
		
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
		wp_subscrpt_write_debug_log( "Square: Processing scheduled payment for order #{$order->get_id()}, amount: {$amount}" );

		try {
			// Get payment method token (card ID)
			$card_id = $order->get_meta( '_payment_method_token' );
			if ( empty( $card_id ) ) {
				return new \WP_Error( 'no_payment_method', 'No card ID found' );
			}

			// Create payment
			$payment = $this->create_payment( $order, $amount, $card_id );

			if ( is_wp_error( $payment ) ) {
				return $payment;
			}

			// Handle payment result
			if ( $payment->getPayment()->getStatus() === 'COMPLETED' ) {
				$transaction_id = $payment->getPayment()->getId();
				$order->payment_complete( $transaction_id );
				$order->add_order_note( 'Square payment processed successfully' );
				
				wp_subscrpt_write_debug_log( "Square: Payment successful for order #{$order->get_id()}, transaction: {$transaction_id}" );
				return true;
			} else {
				$order->update_status( 'failed', 'Square payment failed' );
				return new \WP_Error( 'payment_failed', 'Payment was not completed' );
			}

		} catch ( \Exception $e ) {
			wp_subscrpt_write_debug_log( "Square: Error processing payment for order #{$order->get_id()}: " . $e->getMessage() );
			$order->update_status( 'failed', 'Square payment error: ' . $e->getMessage() );
			return new \WP_Error( 'square_error', $e->getMessage() );
		}
	}

	/**
	 * Create payment
	 *
	 * @param \WC_Order $order Order object
	 * @param float     $amount Payment amount
	 * @param string    $card_id Card ID
	 * @return \Square\Models\CreatePaymentResponse|WP_Error
	 */
	private function create_payment( $order, $amount, $card_id ) {
		try {
			$payments_api = $this->square_client->getPaymentsApi();

			$money = new \Square\Models\Money();
			$money->setAmount( $this->get_square_amount( $amount, $order->get_currency() ) );
			$money->setCurrency( strtoupper( $order->get_currency() ) );

			$source_id = $card_id;
			$idempotency_key = uniqid();

			$body = new \Square\Models\CreatePaymentRequest(
				$source_id,
				$idempotency_key,
				$money
			);

			$body->setReferenceId( 'order_' . $order->get_id() );
			$body->setNote( sprintf( 'Subscription renewal for order #%s', $order->get_id() ) );

			$result = $payments_api->createPayment( $body );

			if ( $result->isSuccess() ) {
				wp_subscrpt_write_debug_log( "Square: Created payment {$result->getPayment()->getId()} for order #{$order->get_id()}" );
				return $result;
			} else {
				$errors = $result->getErrors();
				$error_message = ! empty( $errors ) ? $errors[0]->getDetail() : 'Unknown error';
				return new \WP_Error( 'square_api_error', $error_message );
			}

		} catch ( \Exception $e ) {
			wp_subscrpt_write_debug_log( "Square: Error creating payment: " . $e->getMessage() );
			return new \WP_Error( 'square_error', $e->getMessage() );
		}
	}

	/**
	 * Handle Square webhook
	 *
	 * @return void
	 */
	public function handle_webhook() {
		$payload = @file_get_contents( 'php://input' );
		$signature = $_SERVER['HTTP_X_SQUARE_SIGNATURE'] ?? '';

		// Verify webhook signature
		$is_valid = $this->verify_webhook( $payload, $signature );
		if ( ! $is_valid ) {
			wp_die( 'Invalid webhook signature', 'Webhook Error', array( 'response' => 400 ) );
		}

		// Process webhook event
		$event = json_decode( $payload, true );
		$this->process_webhook_event( $event );
	}

	/**
	 * Verify webhook signature
	 *
	 * @param string $payload Webhook payload
	 * @param string $signature Signature header
	 * @return bool True if valid
	 */
	private function verify_webhook( $payload, $signature ) {
		$webhook_secret = $this->get_webhook_secret();
		if ( ! $webhook_secret ) {
			return false;
		}

		$expected_signature = base64_encode( hash_hmac( 'sha256', $payload, $webhook_secret, true ) );
		return hash_equals( $expected_signature, $signature );
	}

	/**
	 * Process webhook event
	 *
	 * @param array $event Webhook event data
	 * @return void
	 */
	private function process_webhook_event( $event ) {
		$event_type = $event['type'] ?? '';
		wp_subscrpt_write_debug_log( "Square: Processing webhook event {$event_type}" );

		switch ( $event_type ) {
			case 'payment.updated':
				$this->handle_payment_updated( $event );
				break;

			default:
				wp_subscrpt_write_debug_log( "Square: Unhandled webhook event type: {$event_type}" );
		}
	}

	/**
	 * Handle payment updated
	 *
	 * @param array $event Webhook event data
	 * @return void
	 */
	private function handle_payment_updated( $event ) {
		$payment_data = $event['data']['object']['payment'] ?? array();
		$payment_id = $payment_data['id'] ?? null;
		$status = $payment_data['status'] ?? '';

		if ( ! $payment_id ) {
			return;
		}

		// Find order by payment ID
		$orders = wc_get_orders( array(
			'meta_key'   => '_transaction_id',
			'meta_value' => $payment_id,
			'limit'      => 1,
		) );

		if ( empty( $orders ) ) {
			return;
		}

		$order = $orders[0];

		if ( $status === 'COMPLETED' && ! $order->is_paid() ) {
			$order->payment_complete( $payment_id );
			$order->add_order_note( 'Payment confirmed via Square webhook' );
		} elseif ( $status === 'FAILED' ) {
			$order->update_status( 'failed', 'Square payment failed' );
		}

		wp_subscrpt_write_debug_log( "Square: Payment updated for order #{$order->get_id()}, status: {$status}" );
	}

	/**
	 * On payment method saved
	 *
	 * @param int   $subscription_id Subscription ID
	 * @param array $payment_method_data Payment method data
	 * @return void
	 */
	public function on_payment_method_saved( $subscription_id, $payment_method_data ) {
		if ( $payment_method_data['gateway_id'] !== 'square' ) {
			return;
		}

		wp_subscrpt_write_debug_log( "Square: Payment method saved for subscription #{$subscription_id}" );
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
		if ( $gateway_id !== 'square' ) {
			return;
		}

		wp_subscrpt_write_debug_log( "Square: Payment method updated for subscription #{$subscription_id}" );
	}

	/**
	 * Get Square access token
	 *
	 * @return string|false Access token or false if not found
	 */
	private function get_access_token() {
		if ( ! $this->settings ) {
			return false;
		}

		$testmode = $this->settings['testmode'] ?? 'yes';
		$access_token = ( $testmode === 'yes' ) ? 
			( $this->settings['test_access_token'] ?? '' ) : 
			( $this->settings['live_access_token'] ?? '' );

		return ! empty( $access_token ) ? $access_token : false;
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

	/**
	 * Convert amount to Square format (cents)
	 *
	 * @param float  $amount Amount
	 * @param string $currency Currency code
	 * @return int Amount in cents
	 */
	private function get_square_amount( $amount, $currency ) {
		// Zero decimal currencies
		$zero_decimal_currencies = array( 'JPY', 'KRW' );
		
		if ( in_array( strtoupper( $currency ), $zero_decimal_currencies, true ) ) {
			return absint( $amount );
		}

		return absint( wc_format_decimal( $amount, 2 ) * 100 );
	}
}
