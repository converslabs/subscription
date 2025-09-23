<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Webhook Handler
 *
 * Handles real-time payment status updates from various payment gateways
 * and processes subscription-related events.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class WebhookHandler {

	/**
	 * Supported gateways
	 *
	 * @var array
	 */
	private $supported_gateways = array(
		'stripe_cc' => 'Stripe',
		'paypal'    => 'PayPal',
		'square'    => 'Square',
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize webhook handler
	 *
	 * @return void
	 */
	private function init() {
		// Register webhook endpoints
		$this->register_webhook_endpoints();
		
		// Register webhook processing hooks
		$this->register_webhook_hooks();
	}

	/**
	 * Register webhook endpoints
	 *
	 * @return void
	 */
	private function register_webhook_endpoints() {
		// Stripe webhook
		add_action( 'woocommerce_api_wc_stripe_webhook', array( $this, 'handle_stripe_webhook' ) );
		
		// PayPal webhook
		add_action( 'woocommerce_api_wc_paypal_webhook', array( $this, 'handle_paypal_webhook' ) );
		
		// Square webhook
		add_action( 'woocommerce_api_wc_square_webhook', array( $this, 'handle_square_webhook' ) );
		
		// Generic webhook handler
		add_action( 'woocommerce_api_wc_subscription_webhook', array( $this, 'handle_generic_webhook' ) );
	}

	/**
	 * Register webhook processing hooks
	 *
	 * @return void
	 */
	private function register_webhook_hooks() {
		add_action( 'subscrpt_webhook_received', array( $this, 'log_webhook_event' ), 10, 3 );
		add_action( 'subscrpt_webhook_processed', array( $this, 'log_webhook_processing' ), 10, 2 );
	}

	/**
	 * Handle Stripe webhook
	 *
	 * @return void
	 */
	public function handle_stripe_webhook() {
		$payload = @file_get_contents( 'php://input' );
		$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

		wp_subscrpt_write_debug_log( 'WebhookHandler: Processing Stripe webhook' );

		// Trigger webhook received action
		do_action( 'subscrpt_webhook_received', 'stripe_cc', 'stripe_webhook', $payload );

		// Process webhook
		$result = $this->process_webhook( 'stripe_cc', $payload, array( 'signature' => $sig_header ) );

		// Trigger webhook processed action
		do_action( 'subscrpt_webhook_processed', 'stripe_webhook_' . time(), $result );

		if ( $result ) {
			wp_die( 'Webhook processed successfully', 'Success', array( 'response' => 200 ) );
		} else {
			wp_die( 'Webhook processing failed', 'Error', array( 'response' => 400 ) );
		}
	}

	/**
	 * Handle PayPal webhook
	 *
	 * @return void
	 */
	public function handle_paypal_webhook() {
		$payload = @file_get_contents( 'php://input' );
		$headers = getallheaders();

		wp_subscrpt_write_debug_log( 'WebhookHandler: Processing PayPal webhook' );

		// Trigger webhook received action
		do_action( 'subscrpt_webhook_received', 'paypal', 'paypal_webhook', $payload );

		// Process webhook
		$result = $this->process_webhook( 'paypal', $payload, $headers );

		// Trigger webhook processed action
		do_action( 'subscrpt_webhook_processed', 'paypal_webhook_' . time(), $result );

		if ( $result ) {
			wp_die( 'Webhook processed successfully', 'Success', array( 'response' => 200 ) );
		} else {
			wp_die( 'Webhook processing failed', 'Error', array( 'response' => 400 ) );
		}
	}

	/**
	 * Handle Square webhook
	 *
	 * @return void
	 */
	public function handle_square_webhook() {
		$payload = @file_get_contents( 'php://input' );
		$signature = $_SERVER['HTTP_X_SQUARE_SIGNATURE'] ?? '';

		wp_subscrpt_write_debug_log( 'WebhookHandler: Processing Square webhook' );

		// Trigger webhook received action
		do_action( 'subscrpt_webhook_received', 'square', 'square_webhook', $payload );

		// Process webhook
		$result = $this->process_webhook( 'square', $payload, array( 'signature' => $signature ) );

		// Trigger webhook processed action
		do_action( 'subscrpt_webhook_processed', 'square_webhook_' . time(), $result );

		if ( $result ) {
			wp_die( 'Webhook processed successfully', 'Success', array( 'response' => 200 ) );
		} else {
			wp_die( 'Webhook processing failed', 'Error', array( 'response' => 400 ) );
		}
	}

	/**
	 * Handle generic webhook
	 *
	 * @return void
	 */
	public function handle_generic_webhook() {
		$payload = @file_get_contents( 'php://input' );
		$gateway = $_GET['gateway'] ?? '';

		wp_subscrpt_write_debug_log( "WebhookHandler: Processing generic webhook for gateway: {$gateway}" );

		// Trigger webhook received action
		do_action( 'subscrpt_webhook_received', $gateway, 'generic_webhook', $payload );

		// Process webhook
		$result = $this->process_webhook( $gateway, $payload, $_SERVER );

		// Trigger webhook processed action
		do_action( 'subscrpt_webhook_processed', 'generic_webhook_' . time(), $result );

		if ( $result ) {
			wp_die( 'Webhook processed successfully', 'Success', array( 'response' => 200 ) );
		} else {
			wp_die( 'Webhook processing failed', 'Error', array( 'response' => 400 ) );
		}
	}

	/**
	 * Process webhook event
	 *
	 * @param string $gateway_id Gateway ID
	 * @param string $payload Webhook payload
	 * @param array  $headers Request headers
	 * @return bool True on success, false on failure
	 */
	private function process_webhook( $gateway_id, $payload, $headers ) {
		try {
			// Store webhook event
			$webhook_id = $this->store_webhook_event( $gateway_id, $payload, $headers );

			// Parse webhook data
			$webhook_data = $this->parse_webhook_data( $gateway_id, $payload );

			if ( is_wp_error( $webhook_data ) ) {
				wp_subscrpt_write_debug_log( "WebhookHandler: Error parsing webhook data: " . $webhook_data->get_error_message() );
				return false;
			}

			// Process webhook event
			$result = $this->process_webhook_event( $gateway_id, $webhook_data );

			// Mark webhook as processed
			$this->mark_webhook_processed( $webhook_id, $result );

			return $result;

		} catch ( \Exception $e ) {
			wp_subscrpt_write_debug_log( "WebhookHandler: Error processing webhook: " . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Store webhook event in database
	 *
	 * @param string $gateway_id Gateway ID
	 * @param string $payload Webhook payload
	 * @param array  $headers Request headers
	 * @return int Webhook event ID
	 */
	private function store_webhook_event( $gateway_id, $payload, $headers ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_webhook_events';

		// Parse event type from payload
		$event_data = json_decode( $payload, true );
		$event_type = $this->get_event_type( $gateway_id, $event_data );

		// Extract subscription and order IDs
		$subscription_id = $this->extract_subscription_id( $gateway_id, $event_data );
		$order_id = $this->extract_order_id( $gateway_id, $event_data );

		$data = array(
			'gateway_id'      => $gateway_id,
			'event_type'      => $event_type,
			'event_id'        => $this->get_event_id( $gateway_id, $event_data ),
			'subscription_id' => $subscription_id,
			'order_id'        => $order_id,
			'payload'         => $payload,
			'processed'       => false,
			'created_at'      => current_time( 'mysql' ),
		);

		$wpdb->insert( $table_name, $data );
		return $wpdb->insert_id;
	}

	/**
	 * Parse webhook data based on gateway
	 *
	 * @param string $gateway_id Gateway ID
	 * @param string $payload Webhook payload
	 * @return array|WP_Error Parsed data or error
	 */
	private function parse_webhook_data( $gateway_id, $payload ) {
		$data = json_decode( $payload, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'invalid_json', 'Invalid JSON payload' );
		}

		switch ( $gateway_id ) {
			case 'stripe_cc':
				return $this->parse_stripe_webhook( $data );
			case 'paypal':
				return $this->parse_paypal_webhook( $data );
			case 'square':
				return $this->parse_square_webhook( $data );
			default:
				return $data;
		}
	}

	/**
	 * Parse Stripe webhook data
	 *
	 * @param array $data Webhook data
	 * @return array Parsed data
	 */
	private function parse_stripe_webhook( $data ) {
		return array(
			'event_type' => $data['type'] ?? '',
			'event_id'   => $data['id'] ?? '',
			'data'       => $data['data']['object'] ?? array(),
			'created'    => $data['created'] ?? time(),
		);
	}

	/**
	 * Parse PayPal webhook data
	 *
	 * @param array $data Webhook data
	 * @return array Parsed data
	 */
	private function parse_paypal_webhook( $data ) {
		return array(
			'event_type' => $data['event_type'] ?? '',
			'event_id'   => $data['id'] ?? '',
			'data'       => $data['resource'] ?? array(),
			'created'    => $data['create_time'] ?? time(),
		);
	}

	/**
	 * Parse Square webhook data
	 *
	 * @param array $data Webhook data
	 * @return array Parsed data
	 */
	private function parse_square_webhook( $data ) {
		return array(
			'event_type' => $data['type'] ?? '',
			'event_id'   => $data['event_id'] ?? '',
			'data'       => $data['data']['object'] ?? array(),
			'created'    => $data['created_at'] ?? time(),
		);
	}

	/**
	 * Process webhook event
	 *
	 * @param string $gateway_id Gateway ID
	 * @param array  $webhook_data Webhook data
	 * @return bool True on success, false on failure
	 */
	private function process_webhook_event( $gateway_id, $webhook_data ) {
		$event_type = $webhook_data['event_type'] ?? '';

		wp_subscrpt_write_debug_log( "WebhookHandler: Processing {$gateway_id} event: {$event_type}" );

		// Get event handlers
		$handlers = apply_filters( 'subscrpt_webhook_event_handlers', array(), $event_type );

		$success = true;

		foreach ( $handlers as $handler ) {
			if ( is_callable( $handler ) ) {
				$result = call_user_func( $handler, $gateway_id, $webhook_data );
				if ( ! $result ) {
					$success = false;
				}
			}
		}

		// Process based on event type
		switch ( $event_type ) {
			case 'payment_intent.succeeded':
			case 'PAYMENT.SALE.COMPLETED':
			case 'payment.updated':
				$success = $this->handle_payment_success( $gateway_id, $webhook_data );
				break;

			case 'payment_intent.payment_failed':
			case 'PAYMENT.SALE.DENIED':
				$success = $this->handle_payment_failure( $gateway_id, $webhook_data );
				break;

			case 'customer.subscription.updated':
			case 'BILLING.SUBSCRIPTION.UPDATED':
				$success = $this->handle_subscription_updated( $gateway_id, $webhook_data );
				break;

			case 'customer.subscription.deleted':
			case 'BILLING.SUBSCRIPTION.CANCELLED':
				$success = $this->handle_subscription_cancelled( $gateway_id, $webhook_data );
				break;

			default:
				wp_subscrpt_write_debug_log( "WebhookHandler: Unhandled event type: {$event_type}" );
		}

		return $success;
	}

	/**
	 * Handle payment success
	 *
	 * @param string $gateway_id Gateway ID
	 * @param array  $webhook_data Webhook data
	 * @return bool True on success
	 */
	private function handle_payment_success( $gateway_id, $webhook_data ) {
		$order_id = $this->extract_order_id( $gateway_id, $webhook_data['data'] );
		if ( ! $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// Update order if not already completed
		if ( ! $order->is_paid() ) {
			$transaction_id = $this->extract_transaction_id( $gateway_id, $webhook_data['data'] );
			$order->payment_complete( $transaction_id );
			$order->add_order_note( 'Payment confirmed via ' . $gateway_id . ' webhook' );
		}

		wp_subscrpt_write_debug_log( "WebhookHandler: Payment success processed for order #{$order_id}" );
		return true;
	}

	/**
	 * Handle payment failure
	 *
	 * @param string $gateway_id Gateway ID
	 * @param array  $webhook_data Webhook data
	 * @return bool True on success
	 */
	private function handle_payment_failure( $gateway_id, $webhook_data ) {
		$order_id = $this->extract_order_id( $gateway_id, $webhook_data['data'] );
		if ( ! $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$error_message = $this->extract_error_message( $gateway_id, $webhook_data['data'] );
		$order->update_status( 'failed', 'Payment failed via ' . $gateway_id . ' webhook: ' . $error_message );

		wp_subscrpt_write_debug_log( "WebhookHandler: Payment failure processed for order #{$order_id}: {$error_message}" );
		return true;
	}

	/**
	 * Handle subscription updated
	 *
	 * @param string $gateway_id Gateway ID
	 * @param array  $webhook_data Webhook data
	 * @return bool True on success
	 */
	private function handle_subscription_updated( $gateway_id, $webhook_data ) {
		wp_subscrpt_write_debug_log( "WebhookHandler: Subscription updated via {$gateway_id} webhook" );
		return true;
	}

	/**
	 * Handle subscription cancelled
	 *
	 * @param string $gateway_id Gateway ID
	 * @param array  $webhook_data Webhook data
	 * @return bool True on success
	 */
	private function handle_subscription_cancelled( $gateway_id, $webhook_data ) {
		wp_subscrpt_write_debug_log( "WebhookHandler: Subscription cancelled via {$gateway_id} webhook" );
		return true;
	}

	/**
	 * Get event type from webhook data
	 *
	 * @param string $gateway_id Gateway ID
	 * @param array  $data Webhook data
	 * @return string Event type
	 */
	private function get_event_type( $gateway_id, $data ) {
		switch ( $gateway_id ) {
			case 'stripe_cc':
				return $data['type'] ?? 'unknown';
			case 'paypal':
				return $data['event_type'] ?? 'unknown';
			case 'square':
				return $data['type'] ?? 'unknown';
			default:
				return 'unknown';
		}
	}

	/**
	 * Get event ID from webhook data
	 *
	 * @param string $gateway_id Gateway ID
	 * @param array  $data Webhook data
	 * @return string Event ID
	 */
	private function get_event_id( $gateway_id, $data ) {
		switch ( $gateway_id ) {
			case 'stripe_cc':
				return $data['id'] ?? '';
			case 'paypal':
				return $data['id'] ?? '';
			case 'square':
				return $data['event_id'] ?? '';
			default:
				return '';
		}
	}

	/**
	 * Extract subscription ID from webhook data
	 *
	 * @param string $gateway_id Gateway ID
	 * @param array  $data Webhook data
	 * @return int|false Subscription ID or false
	 */
	private function extract_subscription_id( $gateway_id, $data ) {
		// Check metadata first
		if ( isset( $data['metadata']['subscription_id'] ) ) {
			return (int) $data['metadata']['subscription_id'];
		}

		// Check for subscription ID in various locations
		$subscription_id = $data['subscription_id'] ?? $data['id'] ?? null;
		if ( $subscription_id ) {
			return (int) $subscription_id;
		}

		return false;
	}

	/**
	 * Extract order ID from webhook data
	 *
	 * @param string $gateway_id Gateway ID
	 * @param array  $data Webhook data
	 * @return int|false Order ID or false
	 */
	private function extract_order_id( $gateway_id, $data ) {
		// Check metadata first
		if ( isset( $data['metadata']['order_id'] ) ) {
			return (int) $data['metadata']['order_id'];
		}

		// Check for order ID in various locations
		$order_id = $data['order_id'] ?? $data['reference_id'] ?? null;
		if ( $order_id ) {
			// Remove 'order_' prefix if present
			$order_id = str_replace( 'order_', '', $order_id );
			return (int) $order_id;
		}

		return false;
	}

	/**
	 * Extract transaction ID from webhook data
	 *
	 * @param string $gateway_id Gateway ID
	 * @param array  $data Webhook data
	 * @return string Transaction ID
	 */
	private function extract_transaction_id( $gateway_id, $data ) {
		switch ( $gateway_id ) {
			case 'stripe_cc':
				return $data['id'] ?? '';
			case 'paypal':
				return $data['id'] ?? '';
			case 'square':
				return $data['id'] ?? '';
			default:
				return '';
		}
	}

	/**
	 * Extract error message from webhook data
	 *
	 * @param string $gateway_id Gateway ID
	 * @param array  $data Webhook data
	 * @return string Error message
	 */
	private function extract_error_message( $gateway_id, $data ) {
		switch ( $gateway_id ) {
			case 'stripe_cc':
				return $data['last_payment_error']['message'] ?? 'Payment failed';
			case 'paypal':
				return $data['reason_code'] ?? 'Payment failed';
			case 'square':
				return $data['detail'] ?? 'Payment failed';
			default:
				return 'Payment failed';
		}
	}

	/**
	 * Mark webhook as processed
	 *
	 * @param int  $webhook_id Webhook ID
	 * @param bool $success Processing success
	 * @return void
	 */
	private function mark_webhook_processed( $webhook_id, $success ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_webhook_events';

		$wpdb->update(
			$table_name,
			array( 'processed' => $success ? 1 : 0 ),
			array( 'id' => $webhook_id )
		);
	}

	/**
	 * Log webhook event
	 *
	 * @param string $gateway_id Gateway ID
	 * @param string $event_type Event type
	 * @param string $payload Webhook payload
	 * @return void
	 */
	public function log_webhook_event( $gateway_id, $event_type, $payload ) {
		wp_subscrpt_write_debug_log( "WebhookHandler: Received {$event_type} from {$gateway_id}" );
	}

	/**
	 * Log webhook processing
	 *
	 * @param string $webhook_id Webhook ID
	 * @param bool   $success Processing success
	 * @return void
	 */
	public function log_webhook_processing( $webhook_id, $success ) {
		$status = $success ? 'success' : 'failed';
		wp_subscrpt_write_debug_log( "WebhookHandler: Webhook {$webhook_id} processing {$status}" );
	}
}
