git status
<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Database Schema Manager
 *
 * Handles creation and management of database tables
 * for the auto-renewal payment system.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class DatabaseSchema {

	/**
	 * Create all required tables
	 *
	 * @return void
	 */
	public static function create_tables() {
		self::create_payment_methods_table();
		self::create_payment_history_table();
		self::create_webhook_events_table();
	}

	/**
	 * Create payment methods table
	 *
	 * @return void
	 */
	public static function create_payment_methods_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'subscrpt_payment_methods';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id INT(11) NOT NULL AUTO_INCREMENT,
			subscription_id INT(11) NOT NULL,
			gateway_id VARCHAR(50) NOT NULL,
			payment_method_token TEXT NOT NULL,
			customer_id VARCHAR(100) DEFAULT '',
			gateway_customer_id VARCHAR(100) DEFAULT '',
			is_default BOOLEAN DEFAULT FALSE,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_subscription_id (subscription_id),
			INDEX idx_gateway_id (gateway_id),
			INDEX idx_customer_id (customer_id),
			INDEX idx_is_default (is_default)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create payment history table
	 *
	 * @return void
	 */
	public static function create_payment_history_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'subscrpt_payment_history';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id INT(11) NOT NULL AUTO_INCREMENT,
			subscription_id INT(11) NOT NULL,
			order_id INT(11) DEFAULT NULL,
			gateway_id VARCHAR(50) NOT NULL,
			payment_method_token VARCHAR(255) DEFAULT '',
			amount DECIMAL(10,2) NOT NULL,
			currency VARCHAR(3) NOT NULL,
			status ENUM('pending', 'success', 'failed', 'cancelled') NOT NULL,
			gateway_transaction_id VARCHAR(255) DEFAULT '',
			failure_reason TEXT DEFAULT '',
			processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_subscription_id (subscription_id),
			INDEX idx_order_id (order_id),
			INDEX idx_status (status),
			INDEX idx_processed_at (processed_at),
			INDEX idx_gateway_id (gateway_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Create webhook events table
	 *
	 * @return void
	 */
	public static function create_webhook_events_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'subscrpt_webhook_events';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id INT(11) NOT NULL AUTO_INCREMENT,
			gateway_id VARCHAR(50) NOT NULL,
			event_type VARCHAR(100) NOT NULL,
			event_id VARCHAR(255) UNIQUE,
			subscription_id INT(11) DEFAULT NULL,
			order_id INT(11) DEFAULT NULL,
			payload TEXT NOT NULL,
			processed BOOLEAN DEFAULT FALSE,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			INDEX idx_gateway_id (gateway_id),
			INDEX idx_event_type (event_type),
			INDEX idx_subscription_id (subscription_id),
			INDEX idx_processed (processed),
			INDEX idx_event_id (event_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop all tables
	 *
	 * @return void
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'subscrpt_payment_methods',
			$wpdb->prefix . 'subscrpt_payment_history',
			$wpdb->prefix . 'subscrpt_webhook_events',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
	}

	/**
	 * Check if tables exist
	 *
	 * @return bool True if all tables exist, false otherwise
	 */
	public static function tables_exist() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'subscrpt_payment_methods',
			$wpdb->prefix . 'subscrpt_payment_history',
			$wpdb->prefix . 'subscrpt_webhook_events',
		);

		foreach ( $tables as $table ) {
			$result = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
			if ( $result !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get table version
	 *
	 * @return string Table version
	 */
	public static function get_table_version() {
		return '1.6.0';
	}

	/**
	 * Check if tables need update
	 *
	 * @return bool True if update needed, false otherwise
	 */
	public static function needs_update() {
		$current_version = get_option( 'subscrpt_db_version', '0.0.0' );
		$required_version = self::get_table_version();
		
		return version_compare( $current_version, $required_version, '<' );
	}

	/**
	 * Update table version
	 *
	 * @return void
	 */
	public static function update_table_version() {
		update_option( 'subscrpt_db_version', self::get_table_version() );
	}
}
