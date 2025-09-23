<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Payment Method Manager
 *
 * Handles secure storage and retrieval of payment method tokens
 * for automatic subscription renewals.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class PaymentMethodManager {

	/**
	 * Save payment method for subscription
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param string $gateway_id Gateway ID (e.g., 'stripe_cc', 'paypal')
	 * @param string $payment_method_token Payment method token
	 * @param string $customer_id Customer ID
	 * @param string $gateway_customer_id Gateway-specific customer ID
	 * @param bool   $is_default Whether this is the default payment method
	 * @return bool|int Payment method ID on success, false on failure
	 */
	public static function save_payment_method( $subscription_id, $gateway_id, $payment_method_token, $customer_id = '', $gateway_customer_id = '', $is_default = false ) {
		// Validate inputs
		if ( empty( $subscription_id ) || empty( $gateway_id ) || empty( $payment_method_token ) ) {
			wp_subscrpt_write_debug_log( 'PaymentMethodManager: Invalid parameters for save_payment_method' );
			return false;
		}

		// Encrypt the payment method token
		$encrypted_token = self::encrypt_token( $payment_method_token );

		// Prepare data
		$data = array(
			'subscription_id'      => $subscription_id,
			'gateway_id'           => $gateway_id,
			'payment_method_token' => $encrypted_token,
			'customer_id'          => $customer_id,
			'gateway_customer_id'  => $gateway_customer_id,
			'is_default'           => $is_default ? 1 : 0,
			'created_at'           => current_time( 'mysql' ),
			'updated_at'           => current_time( 'mysql' ),
		);

		// Save to database
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_methods';
		
		$result = $wpdb->insert( $table_name, $data );
		
		if ( $result === false ) {
			wp_subscrpt_write_debug_log( 'PaymentMethodManager: Failed to save payment method - ' . $wpdb->last_error );
			return false;
		}

		$payment_method_id = $wpdb->insert_id;

		// If this is the default, unset other defaults for this subscription
		if ( $is_default ) {
			self::unset_other_defaults( $subscription_id, $payment_method_id );
		}

		// Trigger action
		do_action( 'subscrpt_payment_method_saved', $subscription_id, $data );

		wp_subscrpt_write_debug_log( "PaymentMethodManager: Payment method saved for subscription #{$subscription_id}, gateway: {$gateway_id}" );

		return $payment_method_id;
	}

	/**
	 * Get payment method for subscription
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param string $gateway_id Optional gateway ID filter
	 * @return array|false Payment method data or false if not found
	 */
	public static function get_payment_method( $subscription_id, $gateway_id = null ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_methods';

		$where_clause = 'subscription_id = %d';
		$where_values = array( $subscription_id );

		if ( $gateway_id ) {
			$where_clause .= ' AND gateway_id = %s';
			$where_values[] = $gateway_id;
		}

		$query = $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY is_default DESC, created_at DESC LIMIT 1",
			$where_values
		);

		$result = $wpdb->get_row( $query, ARRAY_A );

		if ( ! $result ) {
			return false;
		}

		// Decrypt the payment method token
		$result['payment_method_token'] = self::decrypt_token( $result['payment_method_token'] );

		return $result;
	}

	/**
	 * Get all payment methods for subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @return array Array of payment methods
	 */
	public static function get_payment_methods( $subscription_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_methods';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE subscription_id = %d ORDER BY is_default DESC, created_at DESC",
				$subscription_id
			),
			ARRAY_A
		);

		// Decrypt tokens
		foreach ( $results as &$result ) {
			$result['payment_method_token'] = self::decrypt_token( $result['payment_method_token'] );
		}

		return $results;
	}

	/**
	 * Update payment method
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param string $gateway_id Gateway ID
	 * @param string $new_token New payment method token
	 * @param string $gateway_customer_id Optional gateway customer ID
	 * @return bool True on success, false on failure
	 */
	public static function update_payment_method( $subscription_id, $gateway_id, $new_token, $gateway_customer_id = '' ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_methods';

		$data = array(
			'payment_method_token' => self::encrypt_token( $new_token ),
			'updated_at'           => current_time( 'mysql' ),
		);

		if ( ! empty( $gateway_customer_id ) ) {
			$data['gateway_customer_id'] = $gateway_customer_id;
		}

		$result = $wpdb->update(
			$table_name,
			$data,
			array(
				'subscription_id' => $subscription_id,
				'gateway_id'      => $gateway_id,
			)
		);

		if ( $result === false ) {
			wp_subscrpt_write_debug_log( 'PaymentMethodManager: Failed to update payment method - ' . $wpdb->last_error );
			return false;
		}

		// Trigger action
		do_action( 'subscrpt_payment_method_updated', $subscription_id, $gateway_id, $new_token );

		wp_subscrpt_write_debug_log( "PaymentMethodManager: Payment method updated for subscription #{$subscription_id}, gateway: {$gateway_id}" );

		return true;
	}

	/**
	 * Delete payment method
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param string $gateway_id Gateway ID
	 * @return bool True on success, false on failure
	 */
	public static function delete_payment_method( $subscription_id, $gateway_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_methods';

		$result = $wpdb->delete(
			$table_name,
			array(
				'subscription_id' => $subscription_id,
				'gateway_id'      => $gateway_id,
			)
		);

		if ( $result === false ) {
			wp_subscrpt_write_debug_log( 'PaymentMethodManager: Failed to delete payment method - ' . $wpdb->last_error );
			return false;
		}

		// Trigger action
		do_action( 'subscrpt_payment_method_deleted', $subscription_id, $gateway_id );

		wp_subscrpt_write_debug_log( "PaymentMethodManager: Payment method deleted for subscription #{$subscription_id}, gateway: {$gateway_id}" );

		return true;
	}

	/**
	 * Get customer payment methods
	 *
	 * @param int    $customer_id Customer ID
	 * @param string $gateway_id Optional gateway ID filter
	 * @return array Array of payment methods
	 */
	public static function get_customer_payment_methods( $customer_id, $gateway_id = null ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_methods';

		$where_clause = 'customer_id = %s';
		$where_values = array( $customer_id );

		if ( $gateway_id ) {
			$where_clause .= ' AND gateway_id = %s';
			$where_values[] = $gateway_id;
		}

		$query = $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE {$where_clause} ORDER BY is_default DESC, created_at DESC",
			$where_values
		);

		$results = $wpdb->get_results( $query, ARRAY_A );

		// Decrypt tokens
		foreach ( $results as &$result ) {
			$result['payment_method_token'] = self::decrypt_token( $result['payment_method_token'] );
		}

		return $results;
	}

	/**
	 * Set default payment method
	 *
	 * @param int $payment_method_id Payment method ID
	 * @return bool True on success, false on failure
	 */
	public static function set_default_payment_method( $payment_method_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_methods';

		// Get payment method details
		$payment_method = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $payment_method_id ),
			ARRAY_A
		);

		if ( ! $payment_method ) {
			return false;
		}

		// Unset other defaults for this subscription
		self::unset_other_defaults( $payment_method['subscription_id'], $payment_method_id );

		// Set this as default
		$result = $wpdb->update(
			$table_name,
			array( 'is_default' => 1 ),
			array( 'id' => $payment_method_id )
		);

		return $result !== false;
	}

	/**
	 * Unset other default payment methods for subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @param int $exclude_id Payment method ID to exclude
	 * @return void
	 */
	private static function unset_other_defaults( $subscription_id, $exclude_id = 0 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_methods';

		$where_clause = 'subscription_id = %d AND is_default = 1';
		$where_values = array( $subscription_id );

		if ( $exclude_id > 0 ) {
			$where_clause .= ' AND id != %d';
			$where_values[] = $exclude_id;
		}

		$wpdb->update(
			$table_name,
			array( 'is_default' => 0 ),
			$where_values
		);
	}

	/**
	 * Encrypt payment method token
	 *
	 * @param string $token Payment method token
	 * @return string Encrypted token
	 */
	private static function encrypt_token( $token ) {
		// Use WordPress's built-in encryption if available
		if ( function_exists( 'wp_encrypt' ) ) {
			return wp_encrypt( $token );
		}

		// Fallback to base64 encoding (not secure, but better than plain text)
		return base64_encode( $token );
	}

	/**
	 * Decrypt payment method token
	 *
	 * @param string $encrypted_token Encrypted token
	 * @return string Decrypted token
	 */
	private static function decrypt_token( $encrypted_token ) {
		// Use WordPress's built-in decryption if available
		if ( function_exists( 'wp_decrypt' ) ) {
			return wp_decrypt( $encrypted_token );
		}

		// Fallback to base64 decoding
		return base64_decode( $encrypted_token );
	}

	/**
	 * Create payment methods table
	 *
	 * @return void
	 */
	public static function create_table() {
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
	 * Drop payment methods table
	 *
	 * @return void
	 */
	public static function drop_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_methods';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}
