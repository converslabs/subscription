<?php
/**
 * PaypalDB - Database helper for PayPal gateway mappings.
 *
 * @package SpringDevs\Subscription\Illuminate\Gateways\Paypal
 */

namespace SpringDevs\Subscription\Illuminate\Gateways\Paypal;

/**
 * Database helper for PayPal gateway mappings.
 *
 * Responsible for ensuring the mapping table exists and providing
 * basic CRUD helpers for paypal_id <-> order_id / subscription_id mappings.
 */
class PaypalDB {

	/**
	 * Get the table name for the mapping table.
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'subscrpt_paypal_map';
	}

	/**
	 * Ensure the mapping table exists. If not, create it using dbDelta.
	 * Safe to call repeatedly.
	 *
	 * @return void
	 */
	public static function maybe_create_tables(): void {
		global $wpdb;

		$table = self::table_name();

		// Direct query required: SHOW TABLES has no WP abstraction. No caching: result must reflect actual DB state to avoid skipping table creation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( $exists === $table ) {
			return;
		}

		// Create table if missing.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
            paypal_id varchar(191) NOT NULL,
            order_id bigint unsigned NOT NULL DEFAULT 0,
            subscription_id bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (paypal_id),
            KEY order_id (order_id),
            KEY subscription_id (subscription_id)
        ) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Insert or replace a mapping row.
	 *
	 * @param string $paypal_id       PayPal billing agreement / subscription ID.
	 * @param int    $subscription_id WP Subscription post ID.
	 * @param int    $order_id        WooCommerce order ID.
	 * @return void
	 */
	public static function upsert_mapping( string $paypal_id, int $subscription_id = 0, int $order_id = 0 ): void {
		global $wpdb;
		$table = self::table_name();

		// Direct query required: no WP API for REPLACE INTO. No caching: write operation, caching not applicable.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->replace(
			$table,
			[
				'paypal_id'       => $paypal_id,
				'order_id'        => $order_id,
				'subscription_id' => $subscription_id,
			],
			[ '%s', '%d', '%d' ]
		);

		// Invalidate read caches for this paypal_id.
		$hash = md5( $paypal_id );
		wp_cache_delete( 'subscrpt_paypal_sub_' . $hash, 'subscrpt_paypal' );
		wp_cache_delete( 'subscrpt_paypal_order_' . $hash, 'subscrpt_paypal' );
	}

	/**
	 * Get subscription_id by paypal_id.
	 *
	 * @param string $paypal_id PayPal billing agreement / subscription ID.
	 * @return int|null
	 */
	public static function get_subscription_by_paypal_id( string $paypal_id ): ?int {
		global $wpdb;
		$table     = self::table_name();
		$cache_key = 'subscrpt_paypal_sub_' . md5( $paypal_id );

		$cached = wp_cache_get( $cache_key, 'subscrpt_paypal' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		// Direct query required: plugin-owned mapping table has no WP abstraction layer.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$val = $wpdb->get_var( $wpdb->prepare( 'SELECT subscription_id FROM %i WHERE paypal_id = %s LIMIT 1', $table, $paypal_id ) );

		if ( $val ) {
			wp_cache_set( $cache_key, (int) $val, 'subscrpt_paypal' );
			return (int) $val;
		}

		return null;
	}

	/**
	 * Get order_id by paypal_id.
	 *
	 * @param string $paypal_id PayPal billing agreement / subscription ID.
	 * @return int|null
	 */
	public static function get_order_by_paypal_id( string $paypal_id ): ?int {
		global $wpdb;
		$table     = self::table_name();
		$cache_key = 'subscrpt_paypal_order_' . md5( $paypal_id );

		$cached = wp_cache_get( $cache_key, 'subscrpt_paypal' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		// Direct query required: plugin-owned mapping table has no WP abstraction layer.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$val = $wpdb->get_var( $wpdb->prepare( 'SELECT order_id FROM %i WHERE paypal_id = %s LIMIT 1', $table, $paypal_id ) );

		if ( $val ) {
			wp_cache_set( $cache_key, (int) $val, 'subscrpt_paypal' );
			return (int) $val;
		}

		return null;
	}
}
