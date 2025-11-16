<?php
/**
 * Plugin Name - WooCommerce Subscriptions Generic Gateway Adapter
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compat\WooSubscriptions\Gateways;

use SpringDevs\Subscription\Illuminate\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generic adapter for any payment gateway that integrates with WooCommerce Subscriptions.
 *
 * This adapter serves as a fallback for gateways that don't have specific adapters.
 * It ensures renewal orders are created and hooks are fired so gateway plugins
 * can process payments normally.
 *
 * @since 1.0.0
 */
class GenericGatewayAdapter {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var GenericGatewayAdapter
	 */
	private static $instance;

	/**
	 * List of gateways that have specific adapters (skip generic handling).
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $specific_adapters = array(
		'stripe',
		'stripe_cc',
		'stripe_ideal',
		'stripe_sepa',
		'sepa_debit',
		'stripe_bancontact',
		'razorpay',
		'razorpay_subscriptions',
		'mollie_wc_gateway_',
		'payoneer',
		'payoneer_checkout',
	);

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return GenericGatewayAdapter
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Listen to the general renewal payment hook.
		// This runs with low priority (30) so specific adapters can handle first.
		add_action( 'wps_wcs_process_renewal_payment', array( $this, 'handle_renewal_payment' ), 30, 1 );

		// Also listen dynamically to any gateway-specific hooks that aren't handled.
		$this->register_dynamic_gateway_hooks();
	}

	/**
	 * Dynamically register hooks for gateway-specific scheduled payments.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_dynamic_gateway_hooks() {
		// Get all payment gateways.
		$gateways = WC()->payment_gateways()->payment_gateways();

		if ( empty( $gateways ) ) {
			return;
		}

		foreach ( $gateways as $gateway_id => $gateway ) {
			// Skip if gateway doesn't support subscriptions.
			if ( ! is_array( $gateway->supports ) || ! in_array( 'subscriptions', $gateway->supports, true ) ) {
				continue;
			}

			// Skip if this gateway has a specific adapter.
			if ( $this->has_specific_adapter( $gateway_id ) ) {
				continue;
			}

			// Register hook for this gateway's scheduled payment.
			$hook_name = 'woocommerce_scheduled_subscription_payment_' . $gateway_id;

			if ( ! has_action( $hook_name ) ) {
				add_action( $hook_name, array( $this, 'handle_gateway_scheduled_payment' ), 10, 2 );
			}
		}
	}

	/**
	 * Check if a gateway has a specific adapter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $gateway_id Gateway ID.
	 *
	 * @return bool
	 */
	private function has_specific_adapter( $gateway_id ) {
		foreach ( $this->specific_adapters as $specific ) {
			if ( strpos( $gateway_id, $specific ) === 0 || $gateway_id === $specific ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle gateway-specific scheduled payment hook.
	 *
	 * This is called when a gateway plugin's scheduled payment hook fires.
	 * We ensure the renewal order exists and let the gateway plugin handle the rest.
	 *
	 * @since 1.0.0
	 *
	 * @param float    $amount         Amount due.
	 * @param \WC_Order $renewal_order Renewal order (may be order ID or object).
	 *
	 * @return void
	 */
	public function handle_gateway_scheduled_payment( $amount, $renewal_order ) {
		// Normalize renewal_order to WC_Order object.
		if ( ! is_a( $renewal_order, 'WC_Order' ) ) {
			$renewal_order = wc_get_order( $renewal_order );
		}

		if ( ! $renewal_order ) {
			return;
		}

		// Find the WPSubscription subscription ID.
		$subscription_id = $this->find_subscription_from_order( $renewal_order->get_id() );

		if ( ! $subscription_id ) {
			return;
		}

		/**
		 * Trigger generic gateway renewal processing.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Order $renewal_order Renewal order.
		 * @param int       $subscription_id WPSubscription ID.
		 * @param float     $amount         Amount due.
		 */
		do_action( 'wps_wcs_generic_gateway_scheduled_payment', $renewal_order, $subscription_id, $amount );
	}

	/**
	 * Handle renewal payment processing (fallback for any gateway).
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id WPSubscription ID.
	 *
	 * @return void
	 */
	public function handle_renewal_payment( $subscription_id ) {
		// Resolve to WPSubscription ID if needed.
		$wps_subscription_id = $this->resolve_wps_subscription_id( $subscription_id );

		if ( ! $wps_subscription_id ) {
			return;
		}

		$subscription = get_post( $wps_subscription_id );

		if ( ! $subscription || 'subscrpt_order' !== $subscription->post_type ) {
			return;
		}

		// Only process if subscription is still active.
		if ( ! in_array( $subscription->post_status, array( 'active', 'pe_cancelled' ), true ) ) {
			return;
		}

		// Get payment method.
		$payment_method = get_post_meta( $wps_subscription_id, '_subscrpt_payment_method', true );

		// Skip if this gateway has a specific adapter (should have been handled already).
		if ( $this->has_specific_adapter( $payment_method ) ) {
			return;
		}

		// Get or create renewal order.
		$renewal_order = $this->get_or_create_renewal_order( $wps_subscription_id );

		if ( ! $renewal_order ) {
			return;
		}

		// Trigger the gateway-specific hook so the gateway plugin can handle payment.
		// This allows gateway plugins to work as they normally would with WCS.
		$amount = $renewal_order->get_total();

		/**
		 * Trigger gateway-specific scheduled payment hook.
		 *
		 * This mimics WooCommerce Subscriptions' behavior, allowing gateway plugins
		 * to process payments normally.
		 *
		 * @since 1.0.0
		 *
		 * @param float    $amount         Amount due.
		 * @param \WC_Order $renewal_order Renewal order.
		 */
		do_action( 'woocommerce_scheduled_subscription_payment_' . $payment_method, $amount, $renewal_order );
	}

	/**
	 * Get or create renewal order for subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id WPSubscription ID.
	 *
	 * @return \WC_Order|null
	 */
	private function get_or_create_renewal_order( $subscription_id ) {
		// Get the original order.
		$original_order_id = (int) get_post_meta( $subscription_id, '_subscrpt_order_id', true );

		if ( ! $original_order_id ) {
			return null;
		}

		$original_order = wc_get_order( $original_order_id );

		if ( ! $original_order ) {
			return null;
		}

		// Check if renewal order already exists.
		$existing_renewals = $this->get_pending_renewal_orders( $subscription_id );

		if ( ! empty( $existing_renewals ) ) {
			return wc_get_order( $existing_renewals[0] );
		}

		// Create renewal order via WPSubscription Helper.
		Helper::create_renewal_order( $subscription_id );

		// Get the newly created renewal order.
		$new_order_id = (int) get_post_meta( $subscription_id, '_subscrpt_order_id', true );

		if ( $new_order_id && $new_order_id !== $original_order_id ) {
			return wc_get_order( $new_order_id );
		}

		return null;
	}

	/**
	 * Get pending renewal orders for subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id WPSubscription ID.
	 *
	 * @return array
	 */
	private function get_pending_renewal_orders( $subscription_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$relations = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT order_id FROM {$table_name} WHERE subscription_id = %d AND type = 'renew' ORDER BY id DESC LIMIT 1",
				$subscription_id
			)
		);

		if ( empty( $relations ) ) {
			return array();
		}

		$pending_orders = array();

		foreach ( $relations as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( $order && in_array( $order->get_status(), array( 'pending', 'processing', 'on-hold' ), true ) ) {
				$pending_orders[] = $order_id;
			}
		}

		return $pending_orders;
	}

	/**
	 * Find WPSubscription ID from order ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return int|null
	 */
	private function find_subscription_from_order( $order_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$relation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT subscription_id FROM {$table_name} WHERE order_id = %d AND type = 'renew' ORDER BY id DESC LIMIT 1",
				$order_id
			)
		);

		if ( $relation && isset( $relation->subscription_id ) ) {
			return (int) $relation->subscription_id;
		}

		return null;
	}

	/**
	 * Resolve subscription ID to WPSubscription ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID (may be WCS or WPSubscription).
	 *
	 * @return int|null
	 */
	private function resolve_wps_subscription_id( $subscription_id ) {
		// If it's already a WPSubscription ID, return it.
		if ( 'subscrpt_order' === get_post_type( $subscription_id ) ) {
			return $subscription_id;
		}

		// If it's a shop_subscription ID, find the WPSubscription ID.
		if ( 'shop_subscription' === get_post_type( $subscription_id ) ) {
			$wps_id = (int) get_post_meta( $subscription_id, '_wps_subscription_id', true );

			if ( $wps_id && 'subscrpt_order' === get_post_type( $wps_id ) ) {
				return $wps_id;
			}
		}

		// Try to find by mapping meta.
		$wps_id = (int) get_post_meta( $subscription_id, '_wcs_wps_id', true );

		if ( $wps_id && 'subscrpt_order' === get_post_type( $wps_id ) ) {
			return $wps_id;
		}

		return null;
	}
}

