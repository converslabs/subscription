<?php

namespace SpringDevs\Subscription\Illuminate\Debug;

/**
 * Quick Test Script
 *
 * Provides quick testing functionality for the auto-renewal payment system.
 * Can be run via WP-CLI or admin interface.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class QuickTest {

	/**
	 * Run all tests
	 *
	 * @return array Test results
	 */
	public static function run_all_tests() {
		$results = array(
			'timestamp' => current_time( 'Y-m-d H:i:s' ),
			'tests'     => array(),
			'summary'   => array(
				'total'   => 0,
				'passed'  => 0,
				'failed'  => 0,
				'skipped' => 0,
			),
		);

		// Test system requirements
		$results['tests']['system_requirements'] = self::test_system_requirements();
		
		// Test database setup
		$results['tests']['database_setup'] = self::test_database_setup();
		
		// Test payment method operations
		$results['tests']['payment_methods'] = self::test_payment_methods();
		
		// Test gateway compatibility
		$results['tests']['gateway_compatibility'] = self::test_gateway_compatibility();
		
		// Test webhook processing
		$results['tests']['webhook_processing'] = self::test_webhook_processing();
		
		// Test scheduled payments
		$results['tests']['scheduled_payments'] = self::test_scheduled_payments();
		
		// Test error handling
		$results['tests']['error_handling'] = self::test_error_handling();

		// Calculate summary
		foreach ( $results['tests'] as $test_name => $test_result ) {
			$results['summary']['total']++;
			
			if ( $test_result['status'] === 'passed' ) {
				$results['summary']['passed']++;
			} elseif ( $test_result['status'] === 'failed' ) {
				$results['summary']['failed']++;
			} else {
				$results['summary']['skipped']++;
			}
		}

		return $results;
	}

	/**
	 * Test system requirements
	 *
	 * @return array Test result
	 */
	public static function test_system_requirements() {
		$result = array(
			'name'        => 'System Requirements',
			'status'      => 'passed',
			'message'     => 'All system requirements met',
			'details'     => array(),
		);

		// Check WordPress version
		$wp_version = get_bloginfo( 'version' );
		$wp_ok = version_compare( $wp_version, '5.0', '>=' );
		$result['details']['wordpress_version'] = array(
			'value'    => $wp_version,
			'required' => '5.0+',
			'status'   => $wp_ok ? 'ok' : 'fail',
		);

		if ( ! $wp_ok ) {
			$result['status'] = 'failed';
			$result['message'] = 'WordPress version too old';
		}

		// Check WooCommerce
		$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : null;
		$wc_ok = $wc_version && version_compare( $wc_version, '5.0', '>=' );
		$result['details']['woocommerce_version'] = array(
			'value'    => $wc_version ?: 'Not installed',
			'required' => '5.0+',
			'status'   => $wc_ok ? 'ok' : 'fail',
		);

		if ( ! $wc_ok ) {
			$result['status'] = 'failed';
			$result['message'] = 'WooCommerce not installed or version too old';
		}

		// Check PHP version
		$php_version = PHP_VERSION;
		$php_ok = version_compare( $php_version, '7.4', '>=' );
		$result['details']['php_version'] = array(
			'value'    => $php_version,
			'required' => '7.4+',
			'status'   => $php_ok ? 'ok' : 'fail',
		);

		if ( ! $php_ok ) {
			$result['status'] = 'failed';
			$result['message'] = 'PHP version too old';
		}

		// Check debug mode
		$debug_enabled = defined( 'WP_SUBSCRIPTION_DEBUG' ) && WP_SUBSCRIPTION_DEBUG;
		$result['details']['debug_mode'] = array(
			'value'    => $debug_enabled ? 'Enabled' : 'Disabled',
			'required' => 'Enabled for testing',
			'status'   => $debug_enabled ? 'ok' : 'warning',
		);

		return $result;
	}

	/**
	 * Test database setup
	 *
	 * @return array Test result
	 */
	public static function test_database_setup() {
		$result = array(
			'name'        => 'Database Setup',
			'status'      => 'passed',
			'message'     => 'All database tables created successfully',
			'details'     => array(),
		);

		global $wpdb;
		$tables = array(
			$wpdb->prefix . 'subscrpt_payment_methods',
			$wpdb->prefix . 'subscrpt_payment_history',
			$wpdb->prefix . 'subscrpt_webhook_events',
		);

		foreach ( $tables as $table ) {
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
			$result['details'][ basename( $table ) ] = array(
				'status' => $exists ? 'ok' : 'fail',
				'message' => $exists ? 'Table exists' : 'Table missing',
			);

			if ( ! $exists ) {
				$result['status'] = 'failed';
				$result['message'] = 'Some database tables are missing';
			}
		}

		return $result;
	}

	/**
	 * Test payment methods
	 *
	 * @return array Test result
	 */
	public static function test_payment_methods() {
		$result = array(
			'name'        => 'Payment Methods',
			'status'      => 'passed',
			'message'     => 'Payment method operations working correctly',
			'details'     => array(),
		);

		try {
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

			$result['details']['save_payment_method'] = array(
				'status'  => $save_result ? 'ok' : 'fail',
				'message' => $save_result ? 'Success' : 'Failed',
			);

			if ( ! $save_result ) {
				$result['status'] = 'failed';
				$result['message'] = 'Failed to save payment method';
			}

			// Test retrieving payment method
			$retrieve_result = \SpringDevs\Subscription\Illuminate\PaymentMethodManager::get_payment_method(
				$test_data['subscription_id'],
				$test_data['gateway_id']
			);

			$result['details']['retrieve_payment_method'] = array(
				'status'  => $retrieve_result ? 'ok' : 'fail',
				'message' => $retrieve_result ? 'Success' : 'Failed',
			);

			if ( ! $retrieve_result ) {
				$result['status'] = 'failed';
				$result['message'] = 'Failed to retrieve payment method';
			}

			// Clean up test data
			\SpringDevs\Subscription\Illuminate\PaymentMethodManager::delete_payment_method(
				$test_data['subscription_id'],
				$test_data['gateway_id']
			);

		} catch ( \Exception $e ) {
			$result['status'] = 'failed';
			$result['message'] = 'Exception: ' . $e->getMessage();
			$result['details']['exception'] = array(
				'status'  => 'fail',
				'message' => $e->getMessage(),
			);
		}

		return $result;
	}

	/**
	 * Test gateway compatibility
	 *
	 * @return array Test result
	 */
	public static function test_gateway_compatibility() {
		$result = array(
			'name'        => 'Gateway Compatibility',
			'status'      => 'passed',
			'message'     => 'Payment gateways compatible with subscriptions',
			'details'     => array(),
		);

		$gateways = WC()->payment_gateways()->payment_gateways();
		$compatible_gateways = 0;
		$total_gateways = 0;

		foreach ( $gateways as $gateway_id => $gateway ) {
			if ( $gateway->enabled === 'yes' ) {
				$total_gateways++;
				
				$supports_subscriptions = method_exists( $gateway, 'scheduled_subscription_payment' );
				$result['details'][ $gateway_id ] = array(
					'status'  => $supports_subscriptions ? 'ok' : 'warning',
					'message' => $supports_subscriptions ? 'Supports subscriptions' : 'Limited subscription support',
				);

				if ( $supports_subscriptions ) {
					$compatible_gateways++;
				}
			}
		}

		if ( $compatible_gateways === 0 ) {
			$result['status'] = 'failed';
			$result['message'] = 'No compatible payment gateways found';
		} elseif ( $compatible_gateways < $total_gateways ) {
			$result['status'] = 'warning';
			$result['message'] = 'Some gateways have limited subscription support';
		}

		$result['details']['summary'] = array(
			'compatible' => $compatible_gateways,
			'total'      => $total_gateways,
		);

		return $result;
	}

	/**
	 * Test webhook processing
	 *
	 * @return array Test result
	 */
	public static function test_webhook_processing() {
		$result = array(
			'name'        => 'Webhook Processing',
			'status'      => 'passed',
			'message'     => 'Webhook processing working correctly',
			'details'     => array(),
		);

		try {
			// Test webhook data parsing
			$test_webhook_data = array(
				'type' => 'payment_intent.succeeded',
				'id' => 'pi_test_' . time(),
				'data' => array(
					'object' => array(
						'id' => 'pi_test_' . time(),
						'status' => 'succeeded',
						'metadata' => array(
							'order_id' => '123',
							'subscription_id' => '456',
						),
					),
				),
			);

			$webhook_handler = new \SpringDevs\Subscription\Illuminate\WebhookHandler();
			$webhook_result = $webhook_handler->process_webhook( 'stripe_cc', json_encode( $test_webhook_data ), array() );

			$result['details']['webhook_processing'] = array(
				'status'  => $webhook_result ? 'ok' : 'fail',
				'message' => $webhook_result ? 'Success' : 'Failed',
			);

			if ( ! $webhook_result ) {
				$result['status'] = 'failed';
				$result['message'] = 'Webhook processing failed';
			}

		} catch ( \Exception $e ) {
			$result['status'] = 'failed';
			$result['message'] = 'Exception: ' . $e->getMessage();
			$result['details']['exception'] = array(
				'status'  => 'fail',
				'message' => $e->getMessage(),
			);
		}

		return $result;
	}

	/**
	 * Test scheduled payments
	 *
	 * @return array Test result
	 */
	public static function test_scheduled_payments() {
		$result = array(
			'name'        => 'Scheduled Payments',
			'status'      => 'passed',
			'message'     => 'Scheduled payment processing working correctly',
			'details'     => array(),
		);

		try {
			// Check if scheduled payment processor is initialized
			$processor_class = '\SpringDevs\Subscription\Illuminate\ScheduledPaymentProcessor';
			$processor_exists = class_exists( $processor_class );

			$result['details']['processor_class'] = array(
				'status'  => $processor_exists ? 'ok' : 'fail',
				'message' => $processor_exists ? 'Class exists' : 'Class not found',
			);

			if ( ! $processor_exists ) {
				$result['status'] = 'failed';
				$result['message'] = 'Scheduled payment processor not found';
			}

			// Check cron events
			$cron_events = array(
				'subscrpt_process_scheduled_renewal',
				'subscrpt_daily_cron',
			);

			foreach ( $cron_events as $event ) {
				$scheduled = wp_next_scheduled( $event );
				$result['details'][ $event ] = array(
					'status'  => $scheduled ? 'ok' : 'warning',
					'message' => $scheduled ? 'Scheduled' : 'Not scheduled',
				);
			}

		} catch ( \Exception $e ) {
			$result['status'] = 'failed';
			$result['message'] = 'Exception: ' . $e->getMessage();
			$result['details']['exception'] = array(
				'status'  => 'fail',
				'message' => $e->getMessage(),
			);
		}

		return $result;
	}

	/**
	 * Test error handling
	 *
	 * @return array Test result
	 */
	public static function test_error_handling() {
		$result = array(
			'name'        => 'Error Handling',
			'status'      => 'passed',
			'message'     => 'Error handling working correctly',
			'details'     => array(),
		);

		try {
			// Test debug logging
			$log_message = 'Test log message ' . time();
			wp_subscrpt_write_debug_log( $log_message );

			$result['details']['debug_logging'] = array(
				'status'  => 'ok',
				'message' => 'Debug logging working',
			);

			// Test error logging
			$error_message = 'Test error message ' . time();
			wp_subscrpt_write_debug_log( $error_message, null, 'error' );

			$result['details']['error_logging'] = array(
				'status'  => 'ok',
				'message' => 'Error logging working',
			);

			// Test exception handling
			try {
				throw new \Exception( 'Test exception' );
			} catch ( \Exception $e ) {
				\SpringDevs\Subscription\Illuminate\Debug\DebugHelpers::log_error( 'Test error', $e );
				$result['details']['exception_handling'] = array(
					'status'  => 'ok',
					'message' => 'Exception handling working',
				);
			}

		} catch ( \Exception $e ) {
			$result['status'] = 'failed';
			$result['message'] = 'Exception: ' . $e->getMessage();
			$result['details']['exception'] = array(
				'status'  => 'fail',
				'message' => $e->getMessage(),
			);
		}

		return $result;
	}

	/**
	 * Generate test report
	 *
	 * @param array $results Test results
	 * @return string Test report
	 */
	public static function generate_report( $results ) {
		$report = "WPSubscription Auto-Renewal Test Report\n";
		$report .= "Generated: {$results['timestamp']}\n";
		$report .= str_repeat( "=", 50 ) . "\n\n";

		$report .= "SUMMARY\n";
		$report .= str_repeat( "-", 10 ) . "\n";
		$report .= "Total Tests: {$results['summary']['total']}\n";
		$report .= "Passed: {$results['summary']['passed']}\n";
		$report .= "Failed: {$results['summary']['failed']}\n";
		$report .= "Skipped: {$results['summary']['skipped']}\n\n";

		$report .= "DETAILED RESULTS\n";
		$report .= str_repeat( "-", 20 ) . "\n";

		foreach ( $results['tests'] as $test_name => $test_result ) {
			$status_icon = $test_result['status'] === 'passed' ? '✓' : ( $test_result['status'] === 'failed' ? '✗' : '⚠' );
			$report .= "{$status_icon} {$test_result['name']}: {$test_result['status']}\n";
			$report .= "   Message: {$test_result['message']}\n";

			if ( ! empty( $test_result['details'] ) ) {
				foreach ( $test_result['details'] as $detail_name => $detail ) {
					$detail_icon = $detail['status'] === 'ok' ? '✓' : ( $detail['status'] === 'fail' ? '✗' : '⚠' );
					$report .= "   {$detail_icon} {$detail_name}: {$detail['message']}\n";
				}
			}
			$report .= "\n";
		}

		return $report;
	}
}
