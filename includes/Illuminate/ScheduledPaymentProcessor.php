<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Scheduled Payment Processor
 *
 * Handles automatic processing of subscription renewal payments
 * using saved payment methods.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class ScheduledPaymentProcessor {

	/**
	 * Process all scheduled payments
	 *
	 * @return array Results of payment processing
	 */
	public static function process_scheduled_payments() {
		wp_subscrpt_write_debug_log( 'ScheduledPaymentProcessor: Starting scheduled payment processing' );

		$results = array(
			'processed' => 0,
			'successful' => 0,
			'failed' => 0,
			'errors' => array(),
		);

		// Get subscriptions due for renewal
		$subscriptions = self::get_subscriptions_due_for_renewal();

		foreach ( $subscriptions as $subscription_id ) {
			try {
				$result = self::process_subscription_renewal( $subscription_id );
				
				$results['processed']++;
				
				if ( $result['success'] ) {
					$results['successful']++;
				} else {
					$results['failed']++;
					$results['errors'][] = "Subscription #{$subscription_id}: " . $result['error'];
				}
			} catch ( \Exception $e ) {
				$results['failed']++;
				$results['errors'][] = "Subscription #{$subscription_id}: " . $e->getMessage();
				wp_subscrpt_write_debug_log( "ScheduledPaymentProcessor: Error processing subscription #{$subscription_id}: " . $e->getMessage() );
			}
		}

		wp_subscrpt_write_debug_log( "ScheduledPaymentProcessor: Completed processing. Processed: {$results['processed']}, Successful: {$results['successful']}, Failed: {$results['failed']}" );

		return $results;
	}

	/**
	 * Process subscription renewal
	 *
	 * @param int $subscription_id Subscription ID
	 * @return array Result of processing
	 */
	public static function process_subscription_renewal( $subscription_id ) {
		wp_subscrpt_write_debug_log( "ScheduledPaymentProcessor: Processing renewal for subscription #{$subscription_id}" );

		// Get subscription data
		$subscription = get_post( $subscription_id );
		if ( ! $subscription || $subscription->post_type !== 'subscrpt_order' ) {
			return array(
				'success' => false,
				'error' => 'Invalid subscription',
			);
		}

		// Check if subscription is active
		if ( $subscription->post_status !== 'active' ) {
			return array(
				'success' => false,
				'error' => 'Subscription is not active',
			);
		}

		// Get payment method
		$payment_method = PaymentMethodManager::get_payment_method( $subscription_id );
		if ( ! $payment_method ) {
			return array(
				'success' => false,
				'error' => 'No payment method found',
			);
		}

		// Create renewal order
		$renewal_order = self::create_renewal_order( $subscription_id );
		if ( ! $renewal_order ) {
			return array(
				'success' => false,
				'error' => 'Failed to create renewal order',
			);
		}

		// Process payment
		$payment_result = self::process_payment( $renewal_order, $payment_method );
		
		if ( $payment_result['success'] ) {
			// Update subscription next payment date
			self::update_subscription_next_payment( $subscription_id );
			
			// Trigger success action
			do_action( 'subscrpt_scheduled_payment_processed', $subscription_id, $renewal_order->get_id() );
			
			wp_subscrpt_write_debug_log( "ScheduledPaymentProcessor: Successfully processed renewal for subscription #{$subscription_id}, order #{$renewal_order->get_id()}" );
		} else {
			// Handle payment failure
			self::handle_payment_failure( $subscription_id, $renewal_order, $payment_result['error'] );
			
			wp_subscrpt_write_debug_log( "ScheduledPaymentProcessor: Failed to process renewal for subscription #{$subscription_id}: {$payment_result['error']}" );
		}

		return $payment_result;
	}

	/**
	 * Get subscriptions due for renewal
	 *
	 * @return array Array of subscription IDs
	 */
	private static function get_subscriptions_due_for_renewal() {
		$args = array(
			'post_type'      => 'subscrpt_order',
			'post_status'    => 'active',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => 'next_date',
					'value'   => current_time( 'Y-m-d H:i:s' ),
					'compare' => '<=',
					'type'    => 'DATETIME',
				),
			),
		);

		$subscriptions = get_posts( $args );

		wp_subscrpt_write_debug_log( "ScheduledPaymentProcessor: Found " . count( $subscriptions ) . " subscriptions due for renewal" );

		return $subscriptions;
	}

	/**
	 * Create renewal order
	 *
	 * @param int $subscription_id Subscription ID
	 * @return \WC_Order|false Order object or false on failure
	 */
	private static function create_renewal_order( $subscription_id ) {
		// Use existing Helper method
		$result = Helper::create_renewal_order( $subscription_id );
		
		if ( $result ) {
			// Get the created order
			$order_id = get_post_meta( $subscription_id, '_subscrpt_order_id', true );
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Process payment for renewal order
	 *
	 * @param \WC_Order $order Renewal order
	 * @param array     $payment_method Payment method data
	 * @return array Payment result
	 */
	private static function process_payment( $order, $payment_method ) {
		$gateway_id = $payment_method['gateway_id'];
		$token = $payment_method['payment_method_token'];

		wp_subscrpt_write_debug_log( "ScheduledPaymentProcessor: Processing payment for order #{$order->get_id()} using gateway {$gateway_id}" );

		// Get gateway
		$gateway = WC()->payment_gateways()->payment_gateways()[ $gateway_id ];
		if ( ! $gateway ) {
			return array(
				'success' => false,
				'error' => "Gateway {$gateway_id} not found",
			);
		}

		// Check if gateway supports scheduled payments
		if ( ! method_exists( $gateway, 'scheduled_subscription_payment' ) ) {
			return array(
				'success' => false,
				'error' => "Gateway {$gateway_id} does not support scheduled payments",
			);
		}

		// Set payment method on order
		$order->set_payment_method( $gateway_id );
		$order->set_payment_method_title( $gateway->get_title() );
		$order->update_meta_data( '_payment_method_token', $token );
		$order->save();

		// Process payment using gateway
		try {
			$result = $gateway->scheduled_subscription_payment( $order->get_total(), $order );
			
			if ( $result ) {
				// Payment successful
				$order->payment_complete();
				$order->add_order_note( 'Automatic renewal payment processed successfully' );
				
				// Trigger success action
				do_action( 'subscrpt_payment_success', $order->get_id(), $order->get_id(), $gateway_id );
				
				return array(
					'success' => true,
					'order_id' => $order->get_id(),
				);
			} else {
				// Payment failed
				$order->update_status( 'failed', 'Automatic renewal payment failed' );
				
				// Trigger failure action
				do_action( 'subscrpt_payment_failed', $order->get_id(), $order->get_id(), $gateway_id, 'Gateway returned false' );
				
				return array(
					'success' => false,
					'error' => 'Gateway returned false',
				);
			}
		} catch ( \Exception $e ) {
			// Payment error
			$order->update_status( 'failed', 'Automatic renewal payment error: ' . $e->getMessage() );
			
			// Trigger failure action
			do_action( 'subscrpt_payment_failed', $order->get_id(), $order->get_id(), $gateway_id, $e->getMessage() );
			
			return array(
				'success' => false,
				'error' => $e->getMessage(),
			);
		}
	}

	/**
	 * Update subscription next payment date
	 *
	 * @param int $subscription_id Subscription ID
	 * @return void
	 */
	private static function update_subscription_next_payment( $subscription_id ) {
		// Get subscription timing
		$timing_per = get_post_meta( $subscription_id, 'timing_per', true );
		$timing_option = get_post_meta( $subscription_id, 'timing_option', true );

		if ( empty( $timing_per ) || empty( $timing_option ) ) {
			$timing_per = 1;
			$timing_option = 'months';
		}

		// Calculate next payment date
		$next_date = strtotime( "+{$timing_per} {$timing_option}" );
		$next_date_formatted = date( 'Y-m-d H:i:s', $next_date );

		// Update subscription
		update_post_meta( $subscription_id, 'next_date', $next_date_formatted );
		update_post_meta( $subscription_id, 'last_payment_date', current_time( 'Y-m-d H:i:s' ) );

		wp_subscrpt_write_debug_log( "ScheduledPaymentProcessor: Updated next payment date for subscription #{$subscription_id} to {$next_date_formatted}" );
	}

	/**
	 * Handle payment failure
	 *
	 * @param int       $subscription_id Subscription ID
	 * @param \WC_Order $order Failed order
	 * @param string    $error Error message
	 * @return void
	 */
	private static function handle_payment_failure( $subscription_id, $order, $error ) {
		// Increment failure count
		$failure_count = get_post_meta( $subscription_id, '_subscrpt_payment_failure_count', true ) ?: 0;
		$failure_count++;
		update_post_meta( $subscription_id, '_subscrpt_payment_failure_count', $failure_count );
		update_post_meta( $subscription_id, '_subscrpt_last_payment_failure', current_time( 'timestamp' ) );

		// Check if we should suspend the subscription
		$max_failures = get_option( 'subscrpt_max_payment_failures', 3 );
		if ( $failure_count >= $max_failures ) {
			// Suspend subscription
			wp_update_post( array(
				'ID'          => $subscription_id,
				'post_status' => 'pe_cancelled',
			) );
			
			update_post_meta( $subscription_id, '_subscrpt_suspended_reason', 'payment_failure' );
			
			wp_subscrpt_write_debug_log( "ScheduledPaymentProcessor: Suspended subscription #{$subscription_id} due to {$failure_count} payment failures" );
		}

		// Trigger failure action
		do_action( 'subscrpt_subscription_payment_failed', $subscription_id );
	}

	/**
	 * Schedule payment processing cron job
	 *
	 * @return void
	 */
	public static function schedule_cron() {
		if ( ! wp_next_scheduled( 'subscrpt_process_scheduled_payments' ) ) {
			wp_schedule_event( time(), 'hourly', 'subscrpt_process_scheduled_payments' );
		}
	}

	/**
	 * Unschedule payment processing cron job
	 *
	 * @return void
	 */
	public static function unschedule_cron() {
		$timestamp = wp_next_scheduled( 'subscrpt_process_scheduled_payments' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'subscrpt_process_scheduled_payments' );
		}
	}

	/**
	 * Initialize cron job
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'subscrpt_process_scheduled_payments', array( __CLASS__, 'process_scheduled_payments' ) );
		self::schedule_cron();
	}
}
