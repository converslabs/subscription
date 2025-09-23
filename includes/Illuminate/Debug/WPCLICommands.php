<?php

namespace SpringDevs\Subscription\Illuminate\Debug;

/**
 * WP-CLI Commands for Debug and Testing
 *
 * Provides WP-CLI commands for testing and debugging the auto-renewal payment system.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class WPCLICommands {

	/**
	 * Run all diagnostic tests
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt test all
	 *     wp subscrpt test all --format=json
	 *
	 * @when after_wp_load
	 */
	public function test_all( $args, $assoc_args ) {
		\WP_CLI::log( 'Running WPSubscription diagnostic tests...' );

		$results = QuickTest::run_all_tests();
		$report = QuickTest::generate_report( $results );

		// Display results based on format
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( $format === 'json' ) {
			\WP_CLI::log( wp_json_encode( $results, JSON_PRETTY_PRINT ) );
		} else {
			\WP_CLI::log( $report );
		}

		// Exit with appropriate code
		if ( $results['summary']['failed'] > 0 ) {
			\WP_CLI::error( 'Some tests failed' );
		} else {
			\WP_CLI::success( 'All tests passed' );
		}
	}

	/**
	 * Test payment method operations
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt test payment-methods
	 *
	 * @when after_wp_load
	 */
	public function test_payment_methods( $args, $assoc_args ) {
		\WP_CLI::log( 'Testing payment method operations...' );

		$results = QuickTest::test_payment_methods();

		\WP_CLI::log( "Test: {$results['name']}" );
		\WP_CLI::log( "Status: {$results['status']}" );
		\WP_CLI::log( "Message: {$results['message']}" );

		if ( $results['status'] === 'failed' ) {
			\WP_CLI::error( 'Payment method test failed' );
		} else {
			\WP_CLI::success( 'Payment method test passed' );
		}
	}

	/**
	 * Test webhook processing
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt test webhooks
	 *
	 * @when after_wp_load
	 */
	public function test_webhooks( $args, $assoc_args ) {
		\WP_CLI::log( 'Testing webhook processing...' );

		$results = QuickTest::test_webhook_processing();

		\WP_CLI::log( "Test: {$results['name']}" );
		\WP_CLI::log( "Status: {$results['status']}" );
		\WP_CLI::log( "Message: {$results['message']}" );

		if ( $results['status'] === 'failed' ) {
			\WP_CLI::error( 'Webhook test failed' );
		} else {
			\WP_CLI::success( 'Webhook test passed' );
		}
	}

	/**
	 * Test scheduled payments
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt test scheduled
	 *
	 * @when after_wp_load
	 */
	public function test_scheduled( $args, $assoc_args ) {
		\WP_CLI::log( 'Testing scheduled payment processing...' );

		$results = QuickTest::test_scheduled_payments();

		\WP_CLI::log( "Test: {$results['name']}" );
		\WP_CLI::log( "Status: {$results['status']}" );
		\WP_CLI::log( "Message: {$results['message']}" );

		if ( $results['status'] === 'failed' ) {
			\WP_CLI::error( 'Scheduled payment test failed' );
		} else {
			\WP_CLI::success( 'Scheduled payment test passed' );
		}
	}

	/**
	 * Show system status
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt status
	 *     wp subscrpt status --format=json
	 *
	 * @when after_wp_load
	 */
	public function status( $args, $assoc_args ) {
		\WP_CLI::log( 'WPSubscription System Status' );
		\WP_CLI::log( str_repeat( '=', 30 ) );

		$system_info = DebugHelpers::get_system_debug_info();

		foreach ( $system_info as $key => $value ) {
			if ( is_array( $value ) ) {
				\WP_CLI::log( "{$key}:" );
				foreach ( $value as $sub_key => $sub_value ) {
					\WP_CLI::log( "  {$sub_key}: " . ( is_bool( $sub_value ) ? ( $sub_value ? 'Yes' : 'No' ) : $sub_value ) );
				}
			} else {
				\WP_CLI::log( "{$key}: {$value}" );
			}
		}
	}

	/**
	 * Show debug logs
	 *
	 * ## OPTIONS
	 *
	 * [--lines=<number>]
	 * : Number of lines to show (default: 50)
	 *
	 * [--level=<level>]
	 * : Filter by log level (info, warning, error)
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt logs
	 *     wp subscrpt logs --lines=100
	 *     wp subscrpt logs --level=error
	 *
	 * @when after_wp_load
	 */
	public function logs( $args, $assoc_args ) {
		$lines = isset( $assoc_args['lines'] ) ? intval( $assoc_args['lines'] ) : 50;
		$level = isset( $assoc_args['level'] ) ? $assoc_args['level'] : null;

		$log_file = DebugConfig::get_log_file_path();

		if ( ! file_exists( $log_file ) ) {
			\WP_CLI::error( 'Debug log file not found' );
		}

		$log_content = file_get_contents( $log_file );
		$log_entries = explode( "\n", $log_content );
		$log_entries = array_filter( $log_entries );
		$log_entries = array_slice( $log_entries, -$lines );

		if ( $level ) {
			$log_entries = array_filter( $log_entries, function( $entry ) use ( $level ) {
				return strpos( $entry, "[{$level}]" ) !== false;
			} );
		}

		foreach ( $log_entries as $entry ) {
			\WP_CLI::log( $entry );
		}
	}

	/**
	 * Clear debug logs
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt logs clear
	 *
	 * @when after_wp_load
	 */
	public function logs_clear( $args, $assoc_args ) {
		$log_file = DebugConfig::get_log_file_path();

		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
			\WP_CLI::success( 'Debug logs cleared' );
		} else {
			\WP_CLI::warning( 'Debug log file not found' );
		}
	}

	/**
	 * Export debug logs
	 *
	 * ## OPTIONS
	 *
	 * [--output=<file>]
	 * : Output file path
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt logs export
	 *     wp subscrpt logs export --output=/tmp/debug-logs.txt
	 *
	 * @when after_wp_load
	 */
	public function logs_export( $args, $assoc_args ) {
		$log_file = DebugConfig::get_log_file_path();
		$output_file = isset( $assoc_args['output'] ) ? $assoc_args['output'] : 'subscrpt-debug-logs-' . date( 'Y-m-d-H-i-s' ) . '.txt';

		if ( ! file_exists( $log_file ) ) {
			\WP_CLI::error( 'Debug log file not found' );
		}

		$log_content = file_get_contents( $log_file );
		file_put_contents( $output_file, $log_content );

		\WP_CLI::success( "Debug logs exported to: {$output_file}" );
	}

	/**
	 * Show subscription debug info
	 *
	 * ## OPTIONS
	 *
	 * <subscription_id>
	 * : Subscription ID
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt subscription 123
	 *
	 * @when after_wp_load
	 */
	public function subscription( $args, $assoc_args ) {
		$subscription_id = intval( $args[0] );

		if ( ! $subscription_id ) {
			\WP_CLI::error( 'Please provide a valid subscription ID' );
		}

		$debug_info = DebugHelpers::get_subscription_debug_info( $subscription_id );

		if ( isset( $debug_info['error'] ) ) {
			\WP_CLI::error( $debug_info['error'] );
		}

		\WP_CLI::log( "Debug info for subscription #{$subscription_id}:" );
		\WP_CLI::log( str_repeat( '=', 40 ) );

		foreach ( $debug_info as $key => $value ) {
			if ( is_array( $value ) ) {
				\WP_CLI::log( "{$key}:" );
				foreach ( $value as $sub_key => $sub_value ) {
					\WP_CLI::log( "  {$sub_key}: " . ( is_array( $sub_value ) ? wp_json_encode( $sub_value ) : $sub_value ) );
				}
			} else {
				\WP_CLI::log( "{$key}: {$value}" );
			}
		}
	}

	/**
	 * Show order debug info
	 *
	 * ## OPTIONS
	 *
	 * <order_id>
	 * : Order ID
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt order 456
	 *
	 * @when after_wp_load
	 */
	public function order( $args, $assoc_args ) {
		$order_id = intval( $args[0] );

		if ( ! $order_id ) {
			\WP_CLI::error( 'Please provide a valid order ID' );
		}

		$debug_info = DebugHelpers::get_order_debug_info( $order_id );

		if ( isset( $debug_info['error'] ) ) {
			\WP_CLI::error( $debug_info['error'] );
		}

		\WP_CLI::log( "Debug info for order #{$order_id}:" );
		\WP_CLI::log( str_repeat( '=', 30 ) );

		foreach ( $debug_info as $key => $value ) {
			if ( is_array( $value ) ) {
				\WP_CLI::log( "{$key}:" );
				foreach ( $value as $sub_key => $sub_value ) {
					\WP_CLI::log( "  {$sub_key}: " . ( is_array( $sub_value ) ? wp_json_encode( $sub_value ) : $sub_value ) );
				}
			} else {
				\WP_CLI::log( "{$key}: {$value}" );
			}
		}
	}

	/**
	 * Process scheduled renewals manually
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt process-renewals
	 *
	 * @when after_wp_load
	 */
	public function process_renewals( $args, $assoc_args ) {
		\WP_CLI::log( 'Processing scheduled renewals...' );

		$processor = new \SpringDevs\Subscription\Illuminate\ScheduledPaymentProcessor();
		$result = $processor->process_scheduled_renewal();

		if ( $result ) {
			\WP_CLI::success( 'Scheduled renewals processed successfully' );
		} else {
			\WP_CLI::error( 'Failed to process scheduled renewals' );
		}
	}

	/**
	 * Clean up old debug logs
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt cleanup
	 *
	 * @when after_wp_load
	 */
	public function cleanup( $args, $assoc_args ) {
		\WP_CLI::log( 'Cleaning up old debug logs...' );

		DebugConfig::cleanup_old_logs();

		\WP_CLI::success( 'Old debug logs cleaned up' );
	}

	/**
	 * Reset debug settings to defaults
	 *
	 * ## EXAMPLES
	 *
	 *     wp subscrpt reset-settings
	 *
	 * @when after_wp_load
	 */
	public function reset_settings( $args, $assoc_args ) {
		\WP_CLI::log( 'Resetting debug settings to defaults...' );

		DebugConfig::reset_to_defaults();

		\WP_CLI::success( 'Debug settings reset to defaults' );
	}
}
