<?php
/**
 * Action Hooks Translation
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Hooks;

/**
 * ActionHooks class.
 *
 * Translates WooCommerce Subscriptions action hooks to WPSubscription.
 *
 * @package SpringDevs\Subscription\Compatibility\Hooks
 * @since   1.0.0
 */
class ActionHooks {

	/**
	 * Initialize action hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		$instance = new self();
		$instance->register_hooks();
	}

	/**
	 * Register all action hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks() {
		// Subscription status changed.
		HookRegistry::register_action(
			'subscrpt_status_changed',
			array( $this, 'on_subscription_status_changed' ),
			10,
			3
		);

		// Subscription created.
		HookRegistry::register_action(
			'subscrpt_created',
			array( $this, 'on_subscription_created' ),
			10,
			2
		);

		// Payment success/failure.
		HookRegistry::register_action(
			'subscrpt_payment_success',
			array( $this, 'on_payment_success' ),
			10,
			2
		);

		HookRegistry::register_action(
			'subscrpt_payment_failed',
			array( $this, 'on_payment_failed' ),
			10,
			2
		);

		// Order completed with subscription.
		HookRegistry::register_action(
			'woocommerce_order_status_completed',
			array( $this, 'on_order_completed' ),
			10,
			1
		);

		// Checkout order processed.
		HookRegistry::register_action(
			'woocommerce_checkout_order_processed',
			array( $this, 'on_checkout_order_processed' ),
			10,
			3
		);
	}

	/**
	 * Handle subscription status changed.
	 *
	 * @since 1.0.0
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @param int    $subscription_id Subscription ID.
	 */
	public function on_subscription_status_changed( $new_status, $old_status, $subscription_id ) {
		// Dispatch WCS-style hook.
		do_action( 'woocommerce_subscription_status_updated', $subscription_id, $new_status, $old_status );
		do_action( 'woocommerce_subscription_status_' . $new_status, $subscription_id );
	}

	/**
	 * Handle subscription created.
	 *
	 * @since 1.0.0
	 * @param int $subscription_id Subscription ID.
	 * @param int $order_id Order ID.
	 */
	public function on_subscription_created( $subscription_id, $order_id ) {
		$order = wc_get_order( $order_id );
		do_action( 'woocommerce_checkout_subscription_created', $subscription_id, $order );
	}

	/**
	 * Handle payment success.
	 *
	 * @since 1.0.0
	 * @param int $subscription_id Subscription ID.
	 * @param int $order_id Order ID.
	 */
	public function on_payment_success( $subscription_id, $order_id ) {
		$order = wc_get_order( $order_id );
		do_action( 'woocommerce_subscription_renewal_payment_complete', $order );
	}

	/**
	 * Handle payment failed.
	 *
	 * @since 1.0.0
	 * @param int $subscription_id Subscription ID.
	 * @param int $order_id Order ID.
	 */
	public function on_payment_failed( $subscription_id, $order_id ) {
		$order = wc_get_order( $order_id );
		do_action( 'woocommerce_subscription_renewal_payment_failed', $order );
	}

	/**
	 * Handle order completed.
	 *
	 * @since 1.0.0
	 * @param int $order_id Order ID.
	 */
	public function on_order_completed( $order_id ) {
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$order = wc_get_order( $order_id );
			do_action( 'woocommerce_subscriptions_paid_for_order', $order );
		}
	}

	/**
	 * Handle checkout order processed.
	 *
	 * @since 1.0.0
	 * @param int      $order_id Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order Order object.
	 */
	public function on_checkout_order_processed( $order_id, $posted_data, $order ) {
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			do_action( 'woocommerce_checkout_subscription_process', $order_id, $posted_data, $order );
		}
	}

	/**
	 * Test if hooks are registered.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function test_hooks() {
		$hooks = array(
			'woocommerce_subscription_status_updated',
			'woocommerce_checkout_subscription_created',
			'woocommerce_subscription_renewal_payment_complete',
			'woocommerce_subscription_renewal_payment_failed',
			'woocommerce_subscriptions_paid_for_order',
		);

		$tests = array();
		foreach ( $hooks as $hook ) {
			$tests[ $hook ] = HookRegistry::test_hook_exists( $hook );
		}

		return $tests;
	}
}
