<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Payment Retry Manager
 *
 * Handles intelligent payment retry system with configurable retry attempts,
 * exponential backoff, and failure reason tracking.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class PaymentRetryManager {

	/**
	 * Retry configuration
	 *
	 * @var array
	 */
	private $retry_config;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize retry manager
	 *
	 * @return void
	 */
	private function init() {
		// Set default retry configuration
		$this->retry_config = array(
			'max_attempts'     => get_option( 'subscrpt_max_retry_attempts', 3 ),
			'retry_intervals'  => array( 1, 3, 7 ), // Days
			'backoff_multiplier' => 1.5,
			'retry_reasons'    => array(
				'insufficient_funds',
				'card_declined',
				'expired_card',
				'processing_error',
				'network_error',
			),
		);

		// Register hooks
		$this->register_hooks();
	}

	/**
	 * Register retry manager hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Payment failure handling
		add_action( 'subscrpt_payment_failed', array( $this, 'handle_payment_failure' ), 10, 4 );
		
		// Retry processing
		add_action( 'subscrpt_process_payment_retries', array( $this, 'process_payment_retries' ) );
		
		// Admin notifications
		add_action( 'subscrpt_payment_retry_exhausted', array( $this, 'notify_admin_retry_exhausted' ), 10, 2 );
	}

	/**
	 * Handle payment failure
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param int    $order_id Order ID
	 * @param string $gateway_id Gateway ID
	 * @param string $error_message Error message
	 * @return void
	 */
	public function handle_payment_failure( $subscription_id, $order_id, $gateway_id, $error_message ) {
		wp_subscrpt_write_debug_log( "PaymentRetryManager: Handling payment failure for subscription #{$subscription_id}, order #{$order_id}" );

		// Get current retry count
		$retry_count = $this->get_retry_count( $subscription_id );
		$max_attempts = $this->get_max_attempts( $subscription_id );

		// Check if we should retry
		if ( $retry_count >= $max_attempts ) {
			wp_subscrpt_write_debug_log( "PaymentRetryManager: Max retry attempts reached for subscription #{$subscription_id}" );
			$this->handle_retry_exhausted( $subscription_id, $order_id, $error_message );
			return;
		}

		// Check if error is retryable
		if ( ! $this->is_retryable_error( $error_message ) ) {
			wp_subscrpt_write_debug_log( "PaymentRetryManager: Error not retryable for subscription #{$subscription_id}: {$error_message}" );
			$this->handle_non_retryable_error( $subscription_id, $order_id, $error_message );
			return;
		}

		// Schedule retry
		$this->schedule_retry( $subscription_id, $order_id, $retry_count + 1 );
	}

	/**
	 * Process payment retries
	 *
	 * @return void
	 */
	public function process_payment_retries() {
		wp_subscrpt_write_debug_log( 'PaymentRetryManager: Processing payment retries' );

		// Get subscriptions with pending retries
		$subscriptions = $this->get_subscriptions_with_pending_retries();

		foreach ( $subscriptions as $subscription_id ) {
			$this->process_subscription_retry( $subscription_id );
		}
	}

	/**
	 * Process retry for specific subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @return bool True on success
	 */
	private function process_subscription_retry( $subscription_id ) {
		wp_subscrpt_write_debug_log( "PaymentRetryManager: Processing retry for subscription #{$subscription_id}" );

		// Get retry data
		$retry_data = $this->get_retry_data( $subscription_id );
		if ( ! $retry_data ) {
			return false;
		}

		// Check if retry is due
		if ( ! $this->is_retry_due( $retry_data ) ) {
			return false;
		}

		// Get payment method
		$payment_method = PaymentMethodManager::get_payment_method( $subscription_id );
		if ( ! $payment_method ) {
			wp_subscrpt_write_debug_log( "PaymentRetryManager: No payment method found for subscription #{$subscription_id}" );
			$this->handle_retry_failed( $subscription_id, 'No payment method found' );
			return false;
		}

		// Create new renewal order
		$renewal_order = $this->create_retry_order( $subscription_id, $retry_data );
		if ( ! $renewal_order ) {
			wp_subscrpt_write_debug_log( "PaymentRetryManager: Failed to create retry order for subscription #{$subscription_id}" );
			$this->handle_retry_failed( $subscription_id, 'Failed to create retry order' );
			return false;
		}

		// Process payment
		$result = $this->process_retry_payment( $renewal_order, $payment_method );

		if ( $result['success'] ) {
			$this->handle_retry_success( $subscription_id, $renewal_order );
		} else {
			$this->handle_retry_failure( $subscription_id, $renewal_order, $result['error'] );
		}

		return $result['success'];
	}

	/**
	 * Schedule retry
	 *
	 * @param int $subscription_id Subscription ID
	 * @param int $order_id Order ID
	 * @param int $attempt_number Attempt number
	 * @return void
	 */
	private function schedule_retry( $subscription_id, $order_id, $attempt_number ) {
		// Calculate retry delay
		$delay_hours = $this->calculate_retry_delay( $attempt_number );
		$retry_time = time() + ( $delay_hours * HOUR_IN_SECONDS );

		// Store retry data
		$retry_data = array(
			'subscription_id' => $subscription_id,
			'order_id'        => $order_id,
			'attempt_number'  => $attempt_number,
			'retry_time'      => $retry_time,
			'status'          => 'pending',
			'created_at'      => current_time( 'mysql' ),
		);

		$this->store_retry_data( $subscription_id, $retry_data );

		// Schedule WordPress cron event
		wp_schedule_single_event( $retry_time, 'subscrpt_process_payment_retries' );

		wp_subscrpt_write_debug_log( "PaymentRetryManager: Scheduled retry #{$attempt_number} for subscription #{$subscription_id} in {$delay_hours} hours" );
	}

	/**
	 * Calculate retry delay
	 *
	 * @param int $attempt_number Attempt number
	 * @return int Delay in hours
	 */
	private function calculate_retry_delay( $attempt_number ) {
		$intervals = $this->retry_config['retry_intervals'];
		$multiplier = $this->retry_config['backoff_multiplier'];

		$base_delay = $intervals[ min( $attempt_number - 1, count( $intervals ) - 1 ) ];
		$delay = $base_delay * pow( $multiplier, $attempt_number - 1 );

		return max( 1, (int) $delay );
	}

	/**
	 * Create retry order
	 *
	 * @param int   $subscription_id Subscription ID
	 * @param array $retry_data Retry data
	 * @return \WC_Order|false Order object or false
	 */
	private function create_retry_order( $subscription_id, $retry_data ) {
		// Use existing Helper method to create renewal order
		$result = Helper::create_renewal_order( $subscription_id );
		
		if ( $result ) {
			$order_id = get_post_meta( $subscription_id, '_subscrpt_order_id', true );
			$order = wc_get_order( $order_id );
			
			if ( $order ) {
				// Mark as retry order
				$order->update_meta_data( '_is_retry_order', true );
				$order->update_meta_data( '_retry_attempt', $retry_data['attempt_number'] );
				$order->update_meta_data( '_original_order_id', $retry_data['order_id'] );
				$order->save();
				
				return $order;
			}
		}

		return false;
	}

	/**
	 * Process retry payment
	 *
	 * @param \WC_Order $order Order object
	 * @param array     $payment_method Payment method data
	 * @return array Payment result
	 */
	private function process_retry_payment( $order, $payment_method ) {
		$gateway_id = $payment_method['gateway_id'];
		$token = $payment_method['payment_method_token'];

		// Set payment method on order
		$order->set_payment_method( $gateway_id );
		$order->update_meta_data( '_payment_method_token', $token );
		$order->save();

		// Get gateway
		$gateway = WC()->payment_gateways()->payment_gateways()[ $gateway_id ];
		if ( ! $gateway ) {
			return array(
				'success' => false,
				'error'   => "Gateway {$gateway_id} not found",
			);
		}

		// Process payment using gateway
		if ( method_exists( $gateway, 'scheduled_subscription_payment' ) ) {
			try {
				$result = $gateway->scheduled_subscription_payment( $order->get_total(), $order );
				
				if ( $result ) {
					$order->payment_complete();
					$order->add_order_note( 'Payment retry successful' );
					
					return array(
						'success' => true,
						'order_id' => $order->get_id(),
					);
				} else {
					return array(
						'success' => false,
						'error'   => 'Gateway returned false',
					);
				}
			} catch ( \Exception $e ) {
				return array(
					'success' => false,
					'error'   => $e->getMessage(),
				);
			}
		} else {
			return array(
				'success' => false,
				'error'   => "Gateway {$gateway_id} does not support scheduled payments",
			);
		}
	}

	/**
	 * Handle retry success
	 *
	 * @param int       $subscription_id Subscription ID
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	private function handle_retry_success( $subscription_id, $order ) {
		// Update subscription
		update_post_meta( $subscription_id, 'last_payment_date', current_time( 'Y-m-d H:i:s' ) );
		update_post_meta( $subscription_id, 'last_order_id', $order->get_id() );

		// Clear retry data
		$this->clear_retry_data( $subscription_id );

		// Reset retry count
		delete_post_meta( $subscription_id, '_subscrpt_payment_retry_count' );

		wp_subscrpt_write_debug_log( "PaymentRetryManager: Retry successful for subscription #{$subscription_id}, order #{$order->get_id()}" );
	}

	/**
	 * Handle retry failure
	 *
	 * @param int       $subscription_id Subscription ID
	 * @param \WC_Order $order Order object
	 * @param string    $error_message Error message
	 * @return void
	 */
	private function handle_retry_failure( $subscription_id, $order, $error_message ) {
		// Increment retry count
		$retry_count = $this->get_retry_count( $subscription_id );
		$this->set_retry_count( $subscription_id, $retry_count + 1 );

		// Update retry data
		$this->update_retry_data( $subscription_id, array(
			'status' => 'failed',
			'last_error' => $error_message,
			'last_attempt' => current_time( 'mysql' ),
		) );

		// Check if we should retry again
		$max_attempts = $this->get_max_attempts( $subscription_id );
		if ( $retry_count + 1 >= $max_attempts ) {
			$this->handle_retry_exhausted( $subscription_id, $order->get_id(), $error_message );
		} else {
			// Schedule next retry
			$this->schedule_retry( $subscription_id, $order->get_id(), $retry_count + 2 );
		}

		wp_subscrpt_write_debug_log( "PaymentRetryManager: Retry failed for subscription #{$subscription_id}: {$error_message}" );
	}

	/**
	 * Handle retry exhausted
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param int    $order_id Order ID
	 * @param string $error_message Error message
	 * @return void
	 */
	private function handle_retry_exhausted( $subscription_id, $order_id, $error_message ) {
		// Suspend subscription
		wp_update_post( array(
			'ID'          => $subscription_id,
			'post_status' => 'pe_cancelled',
		) );

		update_post_meta( $subscription_id, '_subscrpt_suspended_reason', 'payment_retry_exhausted' );
		update_post_meta( $subscription_id, '_subscrpt_last_payment_failure', current_time( 'timestamp' ) );

		// Clear retry data
		$this->clear_retry_data( $subscription_id );

		// Notify admin
		do_action( 'subscrpt_payment_retry_exhausted', $subscription_id, $error_message );

		wp_subscrpt_write_debug_log( "PaymentRetryManager: Retry exhausted for subscription #{$subscription_id}" );
	}

	/**
	 * Handle non-retryable error
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param int    $order_id Order ID
	 * @param string $error_message Error message
	 * @return void
	 */
	private function handle_non_retryable_error( $subscription_id, $order_id, $error_message ) {
		// Suspend subscription immediately
		wp_update_post( array(
			'ID'          => $subscription_id,
			'post_status' => 'pe_cancelled',
		) );

		update_post_meta( $subscription_id, '_subscrpt_suspended_reason', 'non_retryable_error' );
		update_post_meta( $subscription_id, '_subscrpt_last_payment_failure', current_time( 'timestamp' ) );

		wp_subscrpt_write_debug_log( "PaymentRetryManager: Non-retryable error for subscription #{$subscription_id}: {$error_message}" );
	}

	/**
	 * Check if error is retryable
	 *
	 * @param string $error_message Error message
	 * @return bool True if retryable
	 */
	private function is_retryable_error( $error_message ) {
		$retryable_patterns = array(
			'insufficient_funds',
			'card_declined',
			'processing_error',
			'network_error',
			'timeout',
			'temporarily_unavailable',
		);

		foreach ( $retryable_patterns as $pattern ) {
			if ( stripos( $error_message, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get retry count for subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @return int Retry count
	 */
	private function get_retry_count( $subscription_id ) {
		return (int) get_post_meta( $subscription_id, '_subscrpt_payment_retry_count', true );
	}

	/**
	 * Set retry count for subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @param int $count Retry count
	 * @return void
	 */
	private function set_retry_count( $subscription_id, $count ) {
		update_post_meta( $subscription_id, '_subscrpt_payment_retry_count', $count );
	}

	/**
	 * Get max retry attempts for subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @return int Max attempts
	 */
	private function get_max_attempts( $subscription_id ) {
		return apply_filters( 'subscrpt_max_retry_attempts', $this->retry_config['max_attempts'], $subscription_id );
	}

	/**
	 * Get subscriptions with pending retries
	 *
	 * @return array Array of subscription IDs
	 */
	private function get_subscriptions_with_pending_retries() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_retries';

		$subscription_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT subscription_id FROM {$table_name} 
			WHERE status = 'pending' 
			AND retry_time <= %d",
			time()
		) );

		return array_map( 'intval', $subscription_ids );
	}

	/**
	 * Get retry data for subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @return array|false Retry data or false
	 */
	private function get_retry_data( $subscription_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_retries';

		$data = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE subscription_id = %d AND status = 'pending'",
			$subscription_id
		), ARRAY_A );

		return $data ?: false;
	}

	/**
	 * Store retry data
	 *
	 * @param int   $subscription_id Subscription ID
	 * @param array $retry_data Retry data
	 * @return void
	 */
	private function store_retry_data( $subscription_id, $retry_data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_retries';

		$wpdb->replace( $table_name, $retry_data );
	}

	/**
	 * Update retry data
	 *
	 * @param int   $subscription_id Subscription ID
	 * @param array $updates Update data
	 * @return void
	 */
	private function update_retry_data( $subscription_id, $updates ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_retries';

		$wpdb->update(
			$table_name,
			$updates,
			array( 'subscription_id' => $subscription_id )
		);
	}

	/**
	 * Clear retry data
	 *
	 * @param int $subscription_id Subscription ID
	 * @return void
	 */
	private function clear_retry_data( $subscription_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_retries';

		$wpdb->delete( $table_name, array( 'subscription_id' => $subscription_id ) );
	}

	/**
	 * Notify admin of retry exhaustion
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param string $error_message Error message
	 * @return void
	 */
	public function notify_admin_retry_exhausted( $subscription_id, $error_message ) {
		// Send admin notification
		$admin_email = get_option( 'admin_email' );
		$subject = sprintf( 'Payment retry exhausted for subscription #%d', $subscription_id );
		$message = sprintf(
			'Payment retry attempts have been exhausted for subscription #%d. Last error: %s',
			$subscription_id,
			$error_message
		);

		wp_mail( $admin_email, $subject, $message );

		wp_subscrpt_write_debug_log( "PaymentRetryManager: Admin notified of retry exhaustion for subscription #{$subscription_id}" );
	}
}
