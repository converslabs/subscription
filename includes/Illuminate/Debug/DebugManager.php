<?php

namespace SpringDevs\Subscription\Illuminate\Debug;

/**
 * Debug Manager
 *
 * Provides comprehensive debugging tools for the auto-renewal payment system
 * including debug logging, testing utilities, and diagnostic tools.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class DebugManager {

	/**
	 * Debug mode flag
	 *
	 * @var bool
	 */
	private $debug_mode;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Initialize debug configuration
		DebugConfig::init();
		
		$this->debug_mode = DebugConfig::is_debug_enabled();
		$this->init();
	}

	/**
	 * Initialize debug manager
	 *
	 * @return void
	 */
	private function init() {
		if ( $this->debug_mode ) {
			$this->register_debug_hooks();
			$this->create_debug_tools();
		}
	}

	/**
	 * Register debug hooks
	 *
	 * @return void
	 */
	private function register_debug_hooks() {
		// Add debug menu to admin
		add_action( 'admin_menu', array( $this, 'add_debug_menu' ) );
		
		// Add debug actions
		add_action( 'wp_ajax_subscrpt_debug_test_payment', array( $this, 'ajax_test_payment' ) );
		add_action( 'wp_ajax_subscrpt_debug_test_webhook', array( $this, 'ajax_test_webhook' ) );
		add_action( 'wp_ajax_subscrpt_debug_test_scheduled', array( $this, 'ajax_test_scheduled' ) );
		add_action( 'wp_ajax_subscrpt_debug_run_all_tests', array( $this, 'ajax_run_all_tests' ) );
		add_action( 'wp_ajax_subscrpt_debug_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_subscrpt_debug_export_logs', array( $this, 'ajax_export_logs' ) );
		
		// Add debug info to admin notices
		add_action( 'admin_notices', array( $this, 'show_debug_notices' ) );
		
		// Register WP-CLI commands
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_wp_cli_commands();
		}
	}

	/**
	 * Add debug menu to admin
	 *
	 * @return void
	 */
	public function add_debug_menu() {
		add_submenu_page(
			'edit.php?post_type=subscrpt_order',
			'Debug Tools',
			'Debug Tools',
			'manage_options',
			'subscrpt-debug',
			array( $this, 'debug_page' )
		);
	}

	/**
	 * Debug page
	 *
	 * @return void
	 */
	public function debug_page() {
		?>
		<div class="wrap">
			<h1>WPSubscription Debug Tools</h1>
			
			<div class="subscrpt-debug-container">
				<div class="subscrpt-debug-section">
					<h2>System Status</h2>
					<?php $this->display_system_status(); ?>
				</div>

				<div class="subscrpt-debug-section">
					<h2>Database Tables</h2>
					<?php $this->display_database_status(); ?>
				</div>

				<div class="subscrpt-debug-section">
					<h2>Payment Methods</h2>
					<?php $this->display_payment_methods(); ?>
				</div>

				<div class="subscrpt-debug-section">
					<h2>Test Tools</h2>
					<?php $this->display_test_tools(); ?>
				</div>

				<div class="subscrpt-debug-section">
					<h2>Debug Logs</h2>
					<?php $this->display_debug_logs(); ?>
				</div>
			</div>

			<style>
				.subscrpt-debug-container {
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 20px;
					margin-top: 20px;
				}
				.subscrpt-debug-section {
					background: #fff;
					border: 1px solid #ccd0d4;
					padding: 20px;
					border-radius: 4px;
				}
				.subscrpt-debug-section h2 {
					margin-top: 0;
					border-bottom: 1px solid #eee;
					padding-bottom: 10px;
				}
				.subscrpt-status-item {
					display: flex;
					justify-content: space-between;
					padding: 5px 0;
					border-bottom: 1px solid #f0f0f0;
				}
				.subscrpt-status-ok {
					color: #46b450;
					font-weight: bold;
				}
				.subscrpt-status-error {
					color: #dc3232;
					font-weight: bold;
				}
				.subscrpt-test-button {
					background: #0073aa;
					color: white;
					padding: 8px 16px;
					border: none;
					border-radius: 3px;
					cursor: pointer;
					margin: 5px;
				}
				.subscrpt-test-button:hover {
					background: #005a87;
				}
				.subscrpt-log-entry {
					background: #f9f9f9;
					padding: 10px;
					margin: 5px 0;
					border-left: 4px solid #0073aa;
					font-family: monospace;
					font-size: 12px;
				}
			</style>
		</div>
		<?php
	}

	/**
	 * Display system status
	 *
	 * @return void
	 */
	private function display_system_status() {
		$status_items = array(
			'Debug Mode' => $this->debug_mode ? 'Enabled' : 'Disabled',
			'WordPress Version' => get_bloginfo( 'version' ),
			'WooCommerce Version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'Not Installed',
			'PHP Version' => PHP_VERSION,
			'Memory Limit' => ini_get( 'memory_limit' ),
			'Max Execution Time' => ini_get( 'max_execution_time' ),
		);

		foreach ( $status_items as $label => $value ) {
			$status_class = $this->get_status_class( $label, $value );
			echo "<div class='subscrpt-status-item'>";
			echo "<span>{$label}:</span>";
			echo "<span class='{$status_class}'>{$value}</span>";
			echo "</div>";
		}
	}

	/**
	 * Display database status
	 *
	 * @return void
	 */
	private function display_database_status() {
		global $wpdb;
		
		$tables = array(
			$wpdb->prefix . 'subscrpt_payment_methods',
			$wpdb->prefix . 'subscrpt_payment_history',
			$wpdb->prefix . 'subscrpt_webhook_events',
		);

		foreach ( $tables as $table ) {
			$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
			$status_class = $exists ? 'subscrpt-status-ok' : 'subscrpt-status-error';
			$status_text = $exists ? 'Exists' : 'Missing';
			
			echo "<div class='subscrpt-status-item'>";
			echo "<span>" . basename( $table ) . ":</span>";
			echo "<span class='{$status_class}'>{$status_text}</span>";
			echo "</div>";
		}
	}

	/**
	 * Display payment methods
	 *
	 * @return void
	 */
	private function display_payment_methods() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_methods';

		$methods = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 10" );

		if ( empty( $methods ) ) {
			echo "<p>No payment methods found.</p>";
			return;
		}

		echo "<table class='widefat'>";
		echo "<thead><tr><th>Subscription ID</th><th>Gateway</th><th>Customer ID</th><th>Created</th></tr></thead>";
		echo "<tbody>";
		
		foreach ( $methods as $method ) {
			echo "<tr>";
			echo "<td>{$method->subscription_id}</td>";
			echo "<td>{$method->gateway_id}</td>";
			echo "<td>{$method->customer_id}</td>";
			echo "<td>{$method->created_at}</td>";
			echo "</tr>";
		}
		
		echo "</tbody></table>";
	}

	/**
	 * Display test tools
	 *
	 * @return void
	 */
	private function display_test_tools() {
		?>
		<div>
			<button class="subscrpt-test-button" onclick="runAllTests()">Run All Tests</button>
			<button class="subscrpt-test-button" onclick="testPaymentProcessing()">Test Payment Processing</button>
			<button class="subscrpt-test-button" onclick="testWebhookProcessing()">Test Webhook Processing</button>
			<button class="subscrpt-test-button" onclick="testScheduledPayments()">Test Scheduled Payments</button>
			<button class="subscrpt-test-button" onclick="clearDebugLogs()">Clear Debug Logs</button>
			<button class="subscrpt-test-button" onclick="exportDebugLogs()">Export Debug Logs</button>
		</div>

		<script>
		function runAllTests() {
			if (confirm('This will run all diagnostic tests. Continue?')) {
				jQuery.post(ajaxurl, {
					action: 'subscrpt_debug_run_all_tests',
					nonce: '<?php echo wp_create_nonce( 'subscrpt_debug_nonce' ); ?>'
				}, function(response) {
					alert('All tests completed. Check debug logs for detailed results.');
					location.reload();
				});
			}
		}

		function testPaymentProcessing() {
			if (confirm('This will test payment processing. Continue?')) {
				jQuery.post(ajaxurl, {
					action: 'subscrpt_debug_test_payment',
					nonce: '<?php echo wp_create_nonce( 'subscrpt_debug_nonce' ); ?>'
				}, function(response) {
					alert('Test completed. Check debug logs for results.');
					location.reload();
				});
			}
		}

		function testWebhookProcessing() {
			if (confirm('This will test webhook processing. Continue?')) {
				jQuery.post(ajaxurl, {
					action: 'subscrpt_debug_test_webhook',
					nonce: '<?php echo wp_create_nonce( 'subscrpt_debug_nonce' ); ?>'
				}, function(response) {
					alert('Test completed. Check debug logs for results.');
					location.reload();
				});
			}
		}

		function testScheduledPayments() {
			if (confirm('This will test scheduled payment processing. Continue?')) {
				jQuery.post(ajaxurl, {
					action: 'subscrpt_debug_test_scheduled',
					nonce: '<?php echo wp_create_nonce( 'subscrpt_debug_nonce' ); ?>'
				}, function(response) {
					alert('Test completed. Check debug logs for results.');
					location.reload();
				});
			}
		}

		function clearDebugLogs() {
			if (confirm('This will clear all debug logs. Continue?')) {
				jQuery.post(ajaxurl, {
					action: 'subscrpt_debug_clear_logs',
					nonce: '<?php echo wp_create_nonce( 'subscrpt_debug_nonce' ); ?>'
				}, function(response) {
					alert('Debug logs cleared.');
					location.reload();
				});
			}
		}

		function exportDebugLogs() {
			window.open('<?php echo admin_url( 'admin-ajax.php?action=subscrpt_debug_export_logs&nonce=' . wp_create_nonce( 'subscrpt_debug_nonce' ) ); ?>');
		}
		</script>
		<?php
	}

	/**
	 * Display debug logs
	 *
	 * @return void
	 */
	private function display_debug_logs() {
		$log_file = $this->get_debug_log_file();
		
		if ( ! file_exists( $log_file ) ) {
			echo "<p>No debug logs found.</p>";
			return;
		}

		$logs = file_get_contents( $log_file );
		$log_entries = explode( "\n", $logs );
		$log_entries = array_filter( $log_entries );
		$log_entries = array_slice( $log_entries, -50 ); // Show last 50 entries

		echo "<div style='max-height: 400px; overflow-y: auto;'>";
		foreach ( $log_entries as $entry ) {
			if ( ! empty( trim( $entry ) ) ) {
				echo "<div class='subscrpt-log-entry'>" . esc_html( $entry ) . "</div>";
			}
		}
		echo "</div>";
	}

	/**
	 * Get status class for display
	 *
	 * @param string $label Label
	 * @param string $value Value
	 * @return string CSS class
	 */
	private function get_status_class( $label, $value ) {
		switch ( $label ) {
			case 'Debug Mode':
				return $value === 'Enabled' ? 'subscrpt-status-ok' : 'subscrpt-status-error';
			case 'WooCommerce Version':
				return $value !== 'Not Installed' ? 'subscrpt-status-ok' : 'subscrpt-status-error';
			default:
				return 'subscrpt-status-ok';
		}
	}

	/**
	 * AJAX test payment processing
	 *
	 * @return void
	 */
	public function ajax_test_payment() {
		check_ajax_referer( 'subscrpt_debug_nonce', 'nonce' );

		wp_subscrpt_write_debug_log( 'DebugManager: Starting payment processing test' );

		// Test payment method manager
		$test_subscription_id = 1;
		$test_gateway_id = 'stripe_cc';
		$test_token = 'test_token_' . time();
		$test_customer_id = 'test_customer_' . time();

		$result = \SpringDevs\Subscription\Illuminate\PaymentMethodManager::save_payment_method(
			$test_subscription_id,
			$test_gateway_id,
			$test_token,
			$test_customer_id,
			'test_gateway_customer',
			true
		);

		if ( $result ) {
			wp_subscrpt_write_debug_log( 'DebugManager: Payment method saved successfully' );
		} else {
			wp_subscrpt_write_debug_log( 'DebugManager: Failed to save payment method' );
		}

		// Test retrieval
		$retrieved = \SpringDevs\Subscription\Illuminate\PaymentMethodManager::get_payment_method( $test_subscription_id );
		if ( $retrieved ) {
			wp_subscrpt_write_debug_log( 'DebugManager: Payment method retrieved successfully' );
		} else {
			wp_subscrpt_write_debug_log( 'DebugManager: Failed to retrieve payment method' );
		}

		wp_die( 'Test completed' );
	}

	/**
	 * AJAX test scheduled payments
	 *
	 * @return void
	 */
	public function ajax_test_scheduled() {
		check_ajax_referer( 'subscrpt_debug_nonce', 'nonce' );

		wp_subscrpt_write_debug_log( 'DebugManager: Starting scheduled payment test' );

		// Test scheduled payment processor
		$processor = new \SpringDevs\Subscription\Illuminate\ScheduledPaymentProcessor();
		$result = $processor->process_scheduled_renewal();

		if ( $result ) {
			wp_subscrpt_write_debug_log( 'DebugManager: Scheduled payment test completed successfully' );
		} else {
			wp_subscrpt_write_debug_log( 'DebugManager: Scheduled payment test failed' );
		}

		wp_die( 'Test completed' );
	}

	/**
	 * AJAX run all tests
	 *
	 * @return void
	 */
	public function ajax_run_all_tests() {
		check_ajax_referer( 'subscrpt_debug_nonce', 'nonce' );

		wp_subscrpt_write_debug_log( 'DebugManager: Starting comprehensive test suite' );

		$results = \SpringDevs\Subscription\Illuminate\Debug\QuickTest::run_all_tests();
		$report = \SpringDevs\Subscription\Illuminate\Debug\QuickTest::generate_report( $results );

		wp_subscrpt_write_debug_log( 'DebugManager: Test suite completed', $results );
		wp_subscrpt_write_debug_log( 'DebugManager: Test Report', $report );

		wp_die( 'All tests completed' );
	}

	/**
	 * AJAX test webhook processing
	 *
	 * @return void
	 */
	public function ajax_test_webhook() {
		check_ajax_referer( 'subscrpt_debug_nonce', 'nonce' );

		wp_subscrpt_write_debug_log( 'DebugManager: Starting webhook processing test' );

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
		$result = $webhook_handler->process_webhook( 'stripe_cc', json_encode( $test_webhook_data ), array() );

		if ( $result ) {
			wp_subscrpt_write_debug_log( 'DebugManager: Webhook processing test completed successfully' );
		} else {
			wp_subscrpt_write_debug_log( 'DebugManager: Webhook processing test failed' );
		}

		wp_die( 'Test completed' );
	}

	/**
	 * AJAX clear debug logs
	 *
	 * @return void
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'subscrpt_debug_nonce', 'nonce' );

		$log_file = $this->get_debug_log_file();
		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
		}

		wp_die( 'Logs cleared' );
	}

	/**
	 * AJAX export debug logs
	 *
	 * @return void
	 */
	public function ajax_export_logs() {
		check_ajax_referer( 'subscrpt_debug_nonce', 'nonce' );

		$log_file = $this->get_debug_log_file();
		
		if ( ! file_exists( $log_file ) ) {
			wp_die( 'No logs to export' );
		}

		$logs = file_get_contents( $log_file );
		$filename = 'subscrpt-debug-logs-' . date( 'Y-m-d-H-i-s' ) . '.txt';

		header( 'Content-Type: text/plain' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $logs;
		exit;
	}

	/**
	 * Get debug log file path
	 *
	 * @return string Log file path
	 */
	private function get_debug_log_file() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/subscrpt-debug.log';
	}

	/**
	 * Create debug tools
	 *
	 * @return void
	 */
	private function create_debug_tools() {
		// Create debug log file if it doesn't exist
		$log_file = $this->get_debug_log_file();
		if ( ! file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
		}
	}

	/**
	 * Show debug notices
	 *
	 * @return void
	 */
	public function show_debug_notices() {
		if ( ! $this->debug_mode ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && $screen->id === 'subscrpt_order_page_subscrpt-debug' ) {
			return;
		}

		echo '<div class="notice notice-info">';
		echo '<p><strong>WPSubscription Debug Mode:</strong> Debug mode is enabled. <a href="' . admin_url( 'edit.php?post_type=subscrpt_order&page=subscrpt-debug' ) . '">View Debug Tools</a></p>';
		echo '</div>';
	}

	/**
	 * Register WP-CLI commands
	 *
	 * @return void
	 */
	private function register_wp_cli_commands() {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'subscrpt test all', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'test_all' ) );
		\WP_CLI::add_command( 'subscrpt test payment-methods', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'test_payment_methods' ) );
		\WP_CLI::add_command( 'subscrpt test webhooks', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'test_webhooks' ) );
		\WP_CLI::add_command( 'subscrpt test scheduled', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'test_scheduled' ) );
		\WP_CLI::add_command( 'subscrpt status', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'status' ) );
		\WP_CLI::add_command( 'subscrpt logs', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'logs' ) );
		\WP_CLI::add_command( 'subscrpt logs clear', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'logs_clear' ) );
		\WP_CLI::add_command( 'subscrpt logs export', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'logs_export' ) );
		\WP_CLI::add_command( 'subscrpt subscription', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'subscription' ) );
		\WP_CLI::add_command( 'subscrpt order', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'order' ) );
		\WP_CLI::add_command( 'subscrpt process-renewals', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'process_renewals' ) );
		\WP_CLI::add_command( 'subscrpt cleanup', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'cleanup' ) );
		\WP_CLI::add_command( 'subscrpt reset-settings', array( '\SpringDevs\Subscription\Illuminate\Debug\WPCLICommands', 'reset_settings' ) );
	}
}
