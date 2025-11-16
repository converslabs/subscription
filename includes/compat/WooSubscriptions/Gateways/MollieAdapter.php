<?php
/**
 * Plugin Name - WooCommerce Subscriptions Mollie Gateway Adapter
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
 * Adapter bridging WooCommerce Subscriptions Mollie hooks to WPSubscription integration.
 *
 * @since 1.0.0
 */
class MollieAdapter {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var MollieAdapter
	 */
	private static $instance;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return MollieAdapter
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
		add_action( 'wps_wcs_gateway_stripe_scheduled_payment', array( $this, 'handle_scheduled_payment' ), 20, 2 );
		add_action( 'wps_wcs_process_renewal_payment', array( $this, 'handle_renewal_payment' ), 20, 1 );

		// Also listen to Mollie-specific scheduled payment hook if available.
		if ( ! has_action( 'woocommerce_scheduled_subscription_payment_mollie_wc_gateway_' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_mollie_wc_gateway_', array( $this, 'handle_mollie_scheduled_payment' ), 10, 2 );
		}
	}

	/**
	 * Handle WooCommerce Subscriptions-style scheduled payment for Mollie.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $subscription_id Subscription ID (may be WPSubscription or WCS ID).
	 * @param float $amount          Amount due.
	 *
	 * @return void
	 */
	public function handle_scheduled_payment( $subscription_id, $amount = 0.0 ) {
		// Only handle if this is a Mollie subscription.
		if ( ! $this->is_mollie_subscription( $subscription_id ) ) {
			return;
		}

		$this->process_renewal( $subscription_id );
	}

	/**
	 * Handle Mollie-specific scheduled payment hook.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $subscription_id Subscription ID.
	 * @param float $amount          Amount due.
	 *
	 * @return void
	 */
	public function handle_mollie_scheduled_payment( $subscription_id, $amount = 0.0 ) {
		$this->process_renewal( $subscription_id );
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
		if ( ! $this->is_mollie_subscription( $subscription_id ) ) {
			return;
		}

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

		// Get or create renewal order.
		$renewal_order = $this->get_or_create_renewal_order( $wps_subscription_id );

		if ( ! $renewal_order ) {
			return;
		}

		/**
		 * Trigger Mollie renewal payment processing.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Order $renewal_order Renewal order.
		 * @param int       $subscription_id WPSubscription ID.
		 */
		do_action( 'wps_mollie_process_renewal_payment', $renewal_order, $wps_subscription_id );

		// If Mollie plugin is available, try to process payment directly.
		if ( class_exists( 'Mollie\WooCommerce\Gateway\MollieSubscriptionGateway' ) ) {
			$this->process_mollie_renewal( $renewal_order, $wps_subscription_id );
		}
	}

	/**
	 * Process Mollie renewal payment.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order $renewal_order Renewal order.
	 * @param int       $subscription_id WPSubscription ID.
	 *
	 * @return void
	 */
	private function process_mollie_renewal( $renewal_order, $subscription_id ) {
		// Check if Mollie customer ID exists.
		$mollie_customer_id = get_post_meta( $subscription_id, '_mollie_customer_id', true );

		if ( ! $mollie_customer_id ) {
			// Try to get from original order.
			$original_order_id = (int) get_post_meta( $subscription_id, '_subscrpt_order_id', true );

			if ( $original_order_id ) {
				$original_order = wc_get_order( $original_order_id );

				if ( $original_order ) {
					$mollie_customer_id = $original_order->get_meta( '_mollie_customer_id' );

					if ( $mollie_customer_id ) {
						// Clone to renewal order.
						$renewal_order->update_meta_data( '_mollie_customer_id', $mollie_customer_id );
						$renewal_order->save();
					}
				}
			}
		}

		// Check for mandate ID (required for SEPA/credit card sequences).
		$mandate_id = get_post_meta( $subscription_id, '_mollie_mandate_id', true );

		if ( ! $mandate_id ) {
			$original_order_id = (int) get_post_meta( $subscription_id, '_subscrpt_order_id', true );

			if ( $original_order_id ) {
				$original_order = wc_get_order( $original_order_id );

				if ( $original_order ) {
					$mandate_id = $original_order->get_meta( '_mollie_mandate_id' );

					if ( $mandate_id ) {
						$renewal_order->update_meta_data( '_mollie_mandate_id', $mandate_id );
						$renewal_order->save();
					}
				}
			}
		}

		if ( ! $mollie_customer_id || ! $mandate_id ) {
			/**
			 * Trigger action for manual Mollie renewal processing.
			 *
			 * @since 1.0.0
			 *
			 * @param \WC_Order $renewal_order Renewal order.
			 * @param int       $subscription_id WPSubscription ID.
			 */
			do_action( 'wps_mollie_renewal_requires_manual_processing', $renewal_order, $subscription_id );
			return;
		}

		// Mollie payments are typically processed via webhooks, but we can trigger
		// the renewal order creation here. The actual payment will be handled by
		// Mollie's subscription service when the mandate is active.
	}

	/**
	 * Check if subscription uses Mollie payment method.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return bool
	 */
	private function is_mollie_subscription( $subscription_id ) {
		$wps_id = $this->resolve_wps_subscription_id( $subscription_id );

		if ( ! $wps_id ) {
			return false;
		}

		$payment_method = get_post_meta( $wps_id, '_subscrpt_payment_method', true );

		$mollie_methods = array(
			'mollie_wc_gateway_',
			'mollie_wc_gateway_creditcard',
			'mollie_wc_gateway_sepa',
			'mollie_wc_gateway_ideal',
			'mollie_wc_gateway_bancontact',
		);

		foreach ( $mollie_methods as $method ) {
			if ( strpos( $payment_method, $method ) === 0 ) {
				return true;
			}
		}

		return false;
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

