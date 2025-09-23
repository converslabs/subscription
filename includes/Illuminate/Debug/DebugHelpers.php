<?php

namespace SpringDevs\Subscription\Illuminate\Debug;

/**
 * Debug Helper Functions
 *
 * Provides utility functions for debugging the auto-renewal payment system.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class DebugHelpers {

	/**
	 * Enhanced debug logging function
	 *
	 * @param string $message Debug message
	 * @param mixed  $data Optional data to log
	 * @param string $level Log level (info, warning, error)
	 * @return void
	 */
	public static function log( $message, $data = null, $level = 'info' ) {
		if ( ! defined( 'WP_SUBSCRIPTION_DEBUG' ) || ! WP_SUBSCRIPTION_DEBUG ) {
			return;
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$log_entry = "[{$timestamp}] [{$level}] {$message}";

		if ( $data !== null ) {
			$log_entry .= " | Data: " . wp_json_encode( $data );
		}

		// Add stack trace for errors
		if ( $level === 'error' ) {
			$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
			$log_entry .= " | Trace: " . wp_json_encode( $trace );
		}

		$log_entry .= "\n";

		// Log to file
		$upload_dir = wp_upload_dir();
		$log_file = $upload_dir['basedir'] . '/subscrpt-debug.log';
		file_put_contents( $log_file, $log_entry, FILE_APPEND | LOCK_EX );

		// Also log to WordPress debug log if enabled
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( "WPSubscription: {$message}" );
		}
	}

	/**
	 * Log payment method operations
	 *
	 * @param string $operation Operation type
	 * @param int    $subscription_id Subscription ID
	 * @param array  $data Operation data
	 * @return void
	 */
	public static function log_payment_method_operation( $operation, $subscription_id, $data = array() ) {
		$message = "Payment Method {$operation} for subscription #{$subscription_id}";
		self::log( $message, $data );
	}

	/**
	 * Log payment processing
	 *
	 * @param string $status Payment status
	 * @param int    $order_id Order ID
	 * @param array  $data Payment data
	 * @return void
	 */
	public static function log_payment_processing( $status, $order_id, $data = array() ) {
		$message = "Payment {$status} for order #{$order_id}";
		self::log( $message, $data );
	}

	/**
	 * Log webhook processing
	 *
	 * @param string $gateway_id Gateway ID
	 * @param string $event_type Event type
	 * @param array  $data Webhook data
	 * @return void
	 */
	public static function log_webhook_processing( $gateway_id, $event_type, $data = array() ) {
		$message = "Webhook {$event_type} from {$gateway_id}";
		self::log( $message, $data );
	}

	/**
	 * Log subscription events
	 *
	 * @param string $event Event type
	 * @param int    $subscription_id Subscription ID
	 * @param array  $data Event data
	 * @return void
	 */
	public static function log_subscription_event( $event, $subscription_id, $data = array() ) {
		$message = "Subscription {$event} for subscription #{$subscription_id}";
		self::log( $message, $data );
	}

	/**
	 * Log gateway operations
	 *
	 * @param string $gateway_id Gateway ID
	 * @param string $operation Operation type
	 * @param array  $data Operation data
	 * @return void
	 */
	public static function log_gateway_operation( $gateway_id, $operation, $data = array() ) {
		$message = "Gateway {$gateway_id} {$operation}";
		self::log( $message, $data );
	}

	/**
	 * Log database operations
	 *
	 * @param string $operation Database operation
	 * @param string $table Table name
	 * @param array  $data Operation data
	 * @return void
	 */
	public static function log_database_operation( $operation, $table, $data = array() ) {
		$message = "Database {$operation} on {$table}";
		self::log( $message, $data );
	}

	/**
	 * Log performance metrics
	 *
	 * @param string $operation Operation name
	 * @param float  $execution_time Execution time in seconds
	 * @param array  $metrics Additional metrics
	 * @return void
	 */
	public static function log_performance( $operation, $execution_time, $metrics = array() ) {
		$data = array_merge( array( 'execution_time' => $execution_time ), $metrics );
		$message = "Performance: {$operation} took {$execution_time}s";
		self::log( $message, $data );
	}

	/**
	 * Log error with context
	 *
	 * @param string $message Error message
	 * @param \Exception $exception Exception object
	 * @param array  $context Additional context
	 * @return void
	 */
	public static function log_error( $message, $exception = null, $context = array() ) {
		$data = $context;
		
		if ( $exception ) {
			$data['exception'] = array(
				'message' => $exception->getMessage(),
				'code'    => $exception->getCode(),
				'file'    => $exception->getFile(),
				'line'    => $exception->getLine(),
			);
		}

		self::log( $message, $data, 'error' );
	}

	/**
	 * Get debug information for subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @return array Debug information
	 */
	public static function get_subscription_debug_info( $subscription_id ) {
		$subscription = get_post( $subscription_id );
		if ( ! $subscription ) {
			return array( 'error' => 'Subscription not found' );
		}

		$debug_info = array(
			'subscription_id'    => $subscription_id,
			'post_status'        => $subscription->post_status,
			'post_type'          => $subscription->post_type,
			'created_date'       => $subscription->post_date,
			'modified_date'      => $subscription->post_modified,
		);

		// Get subscription meta
		$meta_keys = array(
			'next_date',
			'status',
			'product_id',
			'order_id',
			'last_payment_date',
			'last_order_id',
			'_subscrpt_payment_failure_count',
			'_subscrpt_suspended_reason',
		);

		foreach ( $meta_keys as $key ) {
			$value = get_post_meta( $subscription_id, $key, true );
			if ( $value ) {
				$debug_info[ $key ] = $value;
			}
		}

		// Get payment methods
		$payment_methods = \SpringDevs\Subscription\Illuminate\PaymentMethodManager::get_payment_methods( $subscription_id );
		$debug_info['payment_methods'] = $payment_methods;

		// Get related orders
		$orders = wc_get_orders( array(
			'meta_key'   => '_subscription_id',
			'meta_value' => $subscription_id,
			'limit'      => 10,
		) );

		$debug_info['related_orders'] = array();
		foreach ( $orders as $order ) {
			$debug_info['related_orders'][] = array(
				'order_id'        => $order->get_id(),
				'status'          => $order->get_status(),
				'payment_method'  => $order->get_payment_method(),
				'total'           => $order->get_total(),
				'created_date'    => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
			);
		}

		return $debug_info;
	}

	/**
	 * Get debug information for order
	 *
	 * @param int $order_id Order ID
	 * @return array Debug information
	 */
	public static function get_order_debug_info( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'error' => 'Order not found' );
		}

		$debug_info = array(
			'order_id'         => $order_id,
			'status'           => $order->get_status(),
			'payment_method'   => $order->get_payment_method(),
			'total'            => $order->get_total(),
			'currency'         => $order->get_currency(),
			'customer_id'      => $order->get_customer_id(),
			'created_date'     => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
		);

		// Get order meta
		$meta_keys = array(
			'_subscription_id',
			'_payment_method_token',
			'_stripe_customer_id',
			'_stripe_payment_method_id',
			'_stripe_source_id',
			'_gateway_customer_id',
			'_is_retry_order',
			'_retry_attempt',
			'_original_order_id',
		);

		foreach ( $meta_keys as $key ) {
			$value = $order->get_meta( $key );
			if ( $value ) {
				$debug_info[ $key ] = $value;
			}
		}

		// Get order items
		$debug_info['items'] = array();
		foreach ( $order->get_items() as $item ) {
			$debug_info['items'][] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => $item->get_total(),
			);
		}

		return $debug_info;
	}

	/**
	 * Get system debug information
	 *
	 * @return array System debug information
	 */
	public static function get_system_debug_info() {
		global $wpdb;

		$debug_info = array(
			'wordpress_version'    => get_bloginfo( 'version' ),
			'woocommerce_version'  => defined( 'WC_VERSION' ) ? WC_VERSION : 'Not Installed',
			'php_version'          => PHP_VERSION,
			'memory_limit'         => ini_get( 'memory_limit' ),
			'max_execution_time'   => ini_get( 'max_execution_time' ),
			'debug_mode'           => defined( 'WP_SUBSCRIPTION_DEBUG' ) && WP_SUBSCRIPTION_DEBUG,
		);

		// Check database tables
		$tables = array(
			$wpdb->prefix . 'subscrpt_payment_methods',
			$wpdb->prefix . 'subscrpt_payment_history',
			$wpdb->prefix . 'subscrpt_webhook_events',
		);

		$debug_info['database_tables'] = array();
		foreach ( $tables as $table ) {
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
			$debug_info['database_tables'][ basename( $table ) ] = $exists;
		}

		// Get subscription counts
		$status_counts = wp_count_posts( 'subscrpt_order' );
		$debug_info['subscription_counts'] = (array) $status_counts;

		// Get payment gateway status
		$gateways = WC()->payment_gateways()->payment_gateways();
		$debug_info['payment_gateways'] = array();
		foreach ( $gateways as $gateway_id => $gateway ) {
			$debug_info['payment_gateways'][ $gateway_id ] = array(
				'enabled'  => $gateway->enabled === 'yes',
				'title'    => $gateway->get_title(),
				'supports' => $gateway->supports,
			);
		}

		return $debug_info;
	}

	/**
	 * Test payment method operations
	 *
	 * @return array Test results
	 */
	public static function test_payment_method_operations() {
		$results = array();

		// Test saving payment method
		$test_data = array(
			'subscription_id'      => 999999,
			'gateway_id'           => 'test_gateway',
			'payment_method_token' => 'test_token_' . time(),
			'customer_id'          => 'test_customer_' . time(),
			'gateway_customer_id'  => 'test_gateway_customer_' . time(),
			'is_default'           => true,
		);

		$save_result = \SpringDevs\Subscription\Illuminate\PaymentMethodManager::save_payment_method(
			$test_data['subscription_id'],
			$test_data['gateway_id'],
			$test_data['payment_method_token'],
			$test_data['customer_id'],
			$test_data['gateway_customer_id'],
			$test_data['is_default']
		);

		$results['save_payment_method'] = $save_result ? 'SUCCESS' : 'FAILED';

		// Test retrieving payment method
		$retrieve_result = \SpringDevs\Subscription\Illuminate\PaymentMethodManager::get_payment_method(
			$test_data['subscription_id'],
			$test_data['gateway_id']
		);

		$results['retrieve_payment_method'] = $retrieve_result ? 'SUCCESS' : 'FAILED';

		// Test updating payment method
		$update_result = \SpringDevs\Subscription\Illuminate\PaymentMethodManager::update_payment_method(
			$test_data['subscription_id'],
			$test_data['gateway_id'],
			'updated_token_' . time()
		);

		$results['update_payment_method'] = $update_result ? 'SUCCESS' : 'FAILED';

		// Test deleting payment method
		$delete_result = \SpringDevs\Subscription\Illuminate\PaymentMethodManager::delete_payment_method(
			$test_data['subscription_id'],
			$test_data['gateway_id']
		);

		$results['delete_payment_method'] = $delete_result ? 'SUCCESS' : 'FAILED';

		return $results;
	}

	/**
	 * Test database operations
	 *
	 * @return array Test results
	 */
	public static function test_database_operations() {
		$results = array();

		// Test table creation
		\SpringDevs\Subscription\Illuminate\DatabaseSchema::create_tables();
		$results['create_tables'] = 'SUCCESS';

		// Test table existence
		$tables_exist = \SpringDevs\Subscription\Illuminate\DatabaseSchema::tables_exist();
		$results['tables_exist'] = $tables_exist ? 'SUCCESS' : 'FAILED';

		return $results;
	}

	/**
	 * Generate debug report
	 *
	 * @return string Debug report
	 */
	public static function generate_debug_report() {
		$report = "WPSubscription Debug Report\n";
		$report .= "Generated: " . current_time( 'Y-m-d H:i:s' ) . "\n";
		$report .= str_repeat( "=", 50 ) . "\n\n";

		// System information
		$report .= "SYSTEM INFORMATION\n";
		$report .= str_repeat( "-", 20 ) . "\n";
		$system_info = self::get_system_debug_info();
		foreach ( $system_info as $key => $value ) {
			$report .= "{$key}: " . ( is_array( $value ) ? wp_json_encode( $value ) : $value ) . "\n";
		}
		$report .= "\n";

		// Test results
		$report .= "PAYMENT METHOD TESTS\n";
		$report .= str_repeat( "-", 20 ) . "\n";
		$test_results = self::test_payment_method_operations();
		foreach ( $test_results as $test => $result ) {
			$report .= "{$test}: {$result}\n";
		}
		$report .= "\n";

		// Database tests
		$report .= "DATABASE TESTS\n";
		$report .= str_repeat( "-", 20 ) . "\n";
		$db_results = self::test_database_operations();
		foreach ( $db_results as $test => $result ) {
			$report .= "{$test}: {$result}\n";
		}
		$report .= "\n";

		return $report;
	}
}
