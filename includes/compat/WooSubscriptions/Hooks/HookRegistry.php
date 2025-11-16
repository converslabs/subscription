<?php
/**
 * Plugin Name - WooCommerce Subscriptions Compat Hook Registry
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compat\WooSubscriptions\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register compatibility hooks bridging to WPSubscription.
 *
 * @since 1.0.0
 */
class HookRegistry {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var HookRegistry
	 */
	private static $instance;

	/**
	 * Retrieve the instance.
	 *
	 * @since 1.0.0
	 *
	 * @return HookRegistry
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register gateway-related compatibility hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		// Payment hooks.
		add_action( 'woocommerce_scheduled_subscription_payment_stripe', array( $this, 'handle_stripe_scheduled_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'handle_subscription_renewal_payment_complete' ), 10, 2 );
		add_action( 'woocommerce_subscription_renewal_payment_failed', array( $this, 'handle_subscription_renewal_payment_failed' ), 10, 2 );
		add_action( 'woocommerce_subscription_payment_failed', array( $this, 'handle_subscription_payment_failed' ), 10, 1 );

		// Status change hooks.
		add_action( 'woocommerce_subscription_status_changed', array( $this, 'handle_subscription_status_changed' ), 10, 3 );
		add_action( 'woocommerce_subscription_status_updated', array( $this, 'handle_subscription_status_updated' ), 10, 3 );
		add_action( 'woocommerce_subscription_status_active', array( $this, 'handle_subscription_status_active' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_on-hold', array( $this, 'handle_subscription_status_on_hold' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_cancelled', array( $this, 'handle_subscription_cancelled' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_expired', array( $this, 'handle_subscription_expired' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_pending', array( $this, 'handle_subscription_status_pending' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_pending-cancel', array( $this, 'handle_subscription_pending_cancel' ), 10, 1 );

		// Lifecycle hooks.
		add_action( 'woocommerce_scheduled_subscription_trial_end', array( $this, 'handle_subscription_trial_end' ), 10, 1 );
		add_action( 'woocommerce_scheduled_subscription_expiration', array( $this, 'handle_subscription_expiration' ), 10, 1 );
		add_action( 'woocommerce_scheduled_subscription_end_of_prepaid_term', array( $this, 'handle_subscription_end_of_prepaid_term' ), 10, 1 );

		// Switching hooks (if subscription switching is supported).
		add_action( 'woocommerce_subscriptions_switched_item', array( $this, 'handle_subscription_switched_item' ), 10, 3 );
		add_action( 'woocommerce_subscription_item_switched', array( $this, 'handle_subscription_item_switched' ), 10, 3 );
		add_action( 'woocommerce_subscriptions_switch_completed', array( $this, 'handle_subscription_switch_completed' ), 10, 2 );

		// Retry hooks.
		add_action( 'woocommerce_subscription_payment_retry', array( $this, 'handle_subscription_payment_retry' ), 10, 2 );
		add_action( 'woocommerce_subscription_before_retry', array( $this, 'handle_subscription_before_retry' ), 10, 1 );
		add_action( 'woocommerce_subscription_retry_payment_complete', array( $this, 'handle_subscription_retry_payment_complete' ), 10, 2 );
		add_action( 'woocommerce_subscription_retry_payment_failed', array( $this, 'handle_subscription_retry_payment_failed' ), 10, 2 );

		// Reactivation hooks.
		add_action( 'woocommerce_subscription_status_on-hold_to_active', array( $this, 'handle_subscription_reactivated' ), 10, 1 );
		add_action( 'woocommerce_subscription_reactivated', array( $this, 'handle_subscription_reactivated' ), 10, 1 );

		// Suspension hooks.
		add_action( 'woocommerce_subscription_status_active_to_on-hold', array( $this, 'handle_subscription_suspended' ), 10, 1 );
		add_action( 'woocommerce_subscription_suspended', array( $this, 'handle_subscription_suspended' ), 10, 1 );
	}

	/**
	 * Bridge Stripe scheduled payments into WPSubscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $subscription_id Subscription identifier.
	 * @param float $amount          Amount due.
	 *
	 * @return void
	 */
	public function handle_stripe_scheduled_payment( $subscription_id, $amount = 0.0 ) {
		do_action( 'wps_wcs_gateway_stripe_scheduled_payment', $subscription_id, $amount );
	}

	/**
	 * Bridge subscription status changes into WPSubscription.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 * @param string           $new_status   New WooCommerce status.
	 * @param string           $old_status   Previous WooCommerce status.
	 *
	 * @return void
	 */
	public function handle_subscription_status_changed( $subscription, $new_status, $old_status ) {
		do_action( 'wps_wcs_subscription_status_changed', $subscription, $new_status, $old_status );
	}

	/**
	 * Bridge subscription cancellations into WPSubscription.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 *
	 * @return void
	 */
	public function handle_subscription_cancelled( $subscription ) {
		do_action( 'wps_wcs_subscription_cancelled', $subscription );
	}

	/**
	 * Bridge subscription renewal payment completion.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription  Subscription instance.
	 * @param \WC_Order        $renewal_order Renewal order instance.
	 *
	 * @return void
	 */
	public function handle_subscription_renewal_payment_complete( $subscription, $renewal_order ) {
		do_action( 'wps_wcs_subscription_renewal_payment_complete', $subscription, $renewal_order );
	}

	/**
	 * Bridge subscription renewal payment failure.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription  Subscription instance.
	 * @param \WC_Order        $renewal_order Renewal order instance.
	 *
	 * @return void
	 */
	public function handle_subscription_renewal_payment_failed( $subscription, $renewal_order ) {
		do_action( 'wps_wcs_subscription_renewal_payment_failed', $subscription, $renewal_order );
	}

	/**
	 * Bridge generic subscription payment failure.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 *
	 * @return void
	 */
	public function handle_subscription_payment_failed( $subscription ) {
		do_action( 'wps_wcs_subscription_payment_failed', $subscription );
	}

	/**
	 * Bridge subscription status updated hook.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 * @param string           $new_status   New status.
	 * @param string           $old_status   Old status.
	 *
	 * @return void
	 */
	public function handle_subscription_status_updated( $subscription, $new_status, $old_status ) {
		do_action( 'wps_wcs_subscription_status_updated', $subscription, $new_status, $old_status );
	}

	/**
	 * Bridge subscription status active.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 *
	 * @return void
	 */
	public function handle_subscription_status_active( $subscription ) {
		do_action( 'wps_wcs_subscription_status_active', $subscription );
	}

	/**
	 * Bridge subscription status on-hold.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 *
	 * @return void
	 */
	public function handle_subscription_status_on_hold( $subscription ) {
		do_action( 'wps_wcs_subscription_status_on_hold', $subscription );
	}

	/**
	 * Bridge subscription expired.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 *
	 * @return void
	 */
	public function handle_subscription_expired( $subscription ) {
		do_action( 'wps_wcs_subscription_expired', $subscription );
	}

	/**
	 * Bridge subscription status pending.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 *
	 * @return void
	 */
	public function handle_subscription_status_pending( $subscription ) {
		do_action( 'wps_wcs_subscription_status_pending', $subscription );
	}

	/**
	 * Bridge subscription pending cancel.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 *
	 * @return void
	 */
	public function handle_subscription_pending_cancel( $subscription ) {
		do_action( 'wps_wcs_subscription_pending_cancel', $subscription );
	}

	/**
	 * Bridge subscription trial end.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	public function handle_subscription_trial_end( $subscription_id ) {
		do_action( 'wps_wcs_subscription_trial_ended', $subscription_id );
	}

	/**
	 * Bridge subscription expiration.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	public function handle_subscription_expiration( $subscription_id ) {
		do_action( 'wps_wcs_subscription_expired', $subscription_id );
	}

	/**
	 * Bridge subscription end of prepaid term.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	public function handle_subscription_end_of_prepaid_term( $subscription_id ) {
		do_action( 'wps_wcs_subscription_end_of_prepaid_term', $subscription_id );
	}

	/**
	 * Bridge subscription switched item.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order_Item_Product $new_order_item New order item.
	 * @param \WC_Order_Item_Product $old_order_item Old order item.
	 * @param int                    $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	public function handle_subscription_switched_item( $new_order_item, $old_order_item, $subscription_id ) {
		do_action( 'wps_wcs_subscription_switched_item', $new_order_item, $old_order_item, $subscription_id );
	}

	/**
	 * Bridge subscription item switched.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order_Item_Product $new_order_item New order item.
	 * @param \WC_Order_Item_Product $old_order_item Old order item.
	 * @param int                    $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	public function handle_subscription_item_switched( $new_order_item, $old_order_item, $subscription_id ) {
		do_action( 'wps_wcs_subscription_item_switched', $new_order_item, $old_order_item, $subscription_id );
	}

	/**
	 * Bridge subscription switch completed.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $new_subscription New subscription.
	 * @param \WC_Subscription $old_subscription Old subscription.
	 *
	 * @return void
	 */
	public function handle_subscription_switch_completed( $new_subscription, $old_subscription ) {
		do_action( 'wps_wcs_subscription_switch_completed', $new_subscription, $old_subscription );
	}

	/**
	 * Bridge subscription payment retry.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 * @param \WC_Order        $order        Retry order.
	 *
	 * @return void
	 */
	public function handle_subscription_payment_retry( $subscription, $order ) {
		do_action( 'wps_wcs_subscription_payment_retry', $subscription, $order );
	}

	/**
	 * Bridge subscription before retry.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 *
	 * @return void
	 */
	public function handle_subscription_before_retry( $subscription ) {
		do_action( 'wps_wcs_subscription_before_retry', $subscription );
	}

	/**
	 * Bridge subscription retry payment complete.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 * @param \WC_Order        $order        Retry order.
	 *
	 * @return void
	 */
	public function handle_subscription_retry_payment_complete( $subscription, $order ) {
		do_action( 'wps_wcs_subscription_retry_payment_complete', $subscription, $order );
	}

	/**
	 * Bridge subscription retry payment failed.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 * @param \WC_Order        $order        Retry order.
	 *
	 * @return void
	 */
	public function handle_subscription_retry_payment_failed( $subscription, $order ) {
		do_action( 'wps_wcs_subscription_retry_payment_failed', $subscription, $order );
	}

	/**
	 * Bridge subscription reactivated.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 *
	 * @return void
	 */
	public function handle_subscription_reactivated( $subscription ) {
		do_action( 'wps_wcs_subscription_reactivated', $subscription );
	}

	/**
	 * Bridge subscription suspended.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Subscription $subscription Subscription instance.
	 *
	 * @return void
	 */
	public function handle_subscription_suspended( $subscription ) {
		do_action( 'wps_wcs_subscription_suspended', $subscription );
	}
}
