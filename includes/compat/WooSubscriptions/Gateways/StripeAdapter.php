<?php
/**
 * Plugin Name - WooCommerce Subscriptions Stripe Gateway Adapter
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compat\WooSubscriptions\Gateways;

use SpringDevs\Subscription\Illuminate\Helper;
use SpringDevs\Subscription\Illuminate\Gateways\Stripe\Stripe as WPSubscription_Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adapter bridging WooCommerce Subscriptions Stripe hooks to WPSubscription Stripe integration.
 *
 * @since 1.0.0
 */
class StripeAdapter {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var StripeAdapter
	 */
	private static $instance;

	/**
	 * WPSubscription Stripe gateway instance.
	 *
	 * @since 1.0.0
	 *
	 * @var WPSubscription_Stripe|null
	 */
	private $stripe_gateway;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return StripeAdapter
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
		// Hook into WPSubscription's WCS compatibility actions.
		add_action( 'wps_wcs_gateway_stripe_scheduled_payment', array( $this, 'handle_scheduled_payment' ), 10, 2 );
		add_action( 'wps_wcs_process_renewal_payment', array( $this, 'handle_renewal_payment' ), 10, 1 );

		// Initialize Stripe gateway if available.
		$this->init_stripe_gateway();
	}

	/**
	 * Initialize WPSubscription Stripe gateway instance.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_stripe_gateway() {
		if ( ! class_exists( '\WC_Stripe_Payment_Gateway' ) ) {
			return;
		}

		if ( ! class_exists( 'SpringDevs\Subscription\Illuminate\Gateways\Stripe\Stripe' ) ) {
			return;
		}

		// Get Stripe gateway instance from WooCommerce.
		$gateways = WC()->payment_gateways()->payment_gateways();
		$stripe   = isset( $gateways['stripe'] ) ? $gateways['stripe'] : null;

		if ( ! $stripe || ! $stripe instanceof \WC_Stripe_Payment_Gateway ) {
			return;
		}

		// Create WPSubscription Stripe adapter instance.
		$this->stripe_gateway = new WPSubscription_Stripe();
	}

	/**
	 * Handle WooCommerce Subscriptions-style scheduled payment for Stripe.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $subscription_id Subscription ID (may be WPSubscription or WCS ID).
	 * @param float $amount          Amount due.
	 *
	 * @return void
	 */
	public function handle_scheduled_payment( $subscription_id, $amount = 0.0 ) {
		// Resolve to WPSubscription ID if needed.
		$wps_subscription_id = $this->resolve_wps_subscription_id( $subscription_id );

		if ( ! $wps_subscription_id ) {
			return;
		}

		$subscription = get_post( $wps_subscription_id );

		if ( ! $subscription || 'subscrpt_order' !== $subscription->post_type ) {
			return;
		}

		// Check if subscription is still active.
		if ( ! in_array( $subscription->post_status, array( 'active', 'pe_cancelled' ), true ) ) {
			return;
		}

		// Process renewal through WPSubscription.
		$this->process_renewal( $wps_subscription_id );
	}

	/**
	 * Handle renewal payment processing.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id WPSubscription ID.
	 *
	 * @return void
	 */
	public function handle_renewal_payment( $subscription_id ) {
		$this->process_renewal( $subscription_id );
	}

	/**
	 * Process subscription renewal.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id WPSubscription ID.
	 *
	 * @return void
	 */
	private function process_renewal( $subscription_id ) {
		if ( ! $this->stripe_gateway ) {
			return;
		}

		// Check if Stripe payment method is enabled.
		$payment_method = get_post_meta( $subscription_id, '_subscrpt_payment_method', true );

		if ( ! $this->is_stripe_payment_method( $payment_method ) ) {
			return;
		}

		// Check if auto-renew is enabled.
		$is_auto_renew = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		$is_auto_renew = in_array( $is_auto_renew, array( 1, '1' ), true );

		$is_global_auto_renew = get_option( 'wp_subscription_stripe_auto_renew', '1' );
		$is_global_auto_renew = in_array( $is_global_auto_renew, array( 1, '1' ), true );

		if ( ! $is_auto_renew || ! $is_global_auto_renew || ! subscrpt_is_auto_renew_enabled() ) {
			return;
		}

		// Get the last renewal order or create one.
		$renewal_order = $this->get_or_create_renewal_order( $subscription_id );

		if ( ! $renewal_order ) {
			return;
		}

		// Process payment via WPSubscription Stripe gateway.
		$this->stripe_gateway->pay_renew_order( $renewal_order );
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

		// Check if renewal order already exists and is pending/processing.
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
	 * Check if payment method is a Stripe method.
	 *
	 * @since 1.0.0
	 *
	 * @param string $payment_method Payment method ID.
	 *
	 * @return bool
	 */
	private function is_stripe_payment_method( $payment_method ) {
		$stripe_methods = array(
			'stripe',
			'stripe_cc',
			'stripe_ideal',
			'stripe_sepa',
			'sepa_debit',
			'stripe_bancontact',
		);

		return in_array( $payment_method, $stripe_methods, true );
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

