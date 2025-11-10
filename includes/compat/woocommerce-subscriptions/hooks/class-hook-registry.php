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


// phpcs:disable WordPress.NamingConventions.ValidClassName
/**
 * Register compatibility hooks bridging to WPSubscription.
 *
 * @since 1.0.0
 */
class Hook_Registry {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Hook_Registry
	 */
	private static $instance;

	/**
	 * Retrieve the instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Hook_Registry
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
		add_action( 'woocommerce_scheduled_subscription_payment_stripe', array( $this, 'handle_stripe_scheduled_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_status_changed', array( $this, 'handle_subscription_status_changed' ), 10, 3 );
		add_action( 'woocommerce_subscription_cancelled', array( $this, 'handle_subscription_cancelled' ), 10, 1 );
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'handle_subscription_renewal_payment_complete' ), 10, 2 );
		add_action( 'woocommerce_subscription_renewal_payment_failed', array( $this, 'handle_subscription_renewal_payment_failed' ), 10, 2 );
		add_action( 'woocommerce_subscription_payment_failed', array( $this, 'handle_subscription_payment_failed' ), 10, 1 );
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
		/**
		 * Triggered when a Stripe subscription renewal is processed through the compatibility layer.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $subscription_id Subscription identifier.
		 * @param float $amount          Amount due.
		 */
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
		/**
		 * Triggered when a subscription status changes via the compatibility layer.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Subscription $subscription Subscription instance.
		 * @param string           $new_status   New WooCommerce status.
		 * @param string           $old_status   Previous WooCommerce status.
		 */
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
		/**
		 * Triggered when a subscription is cancelled via the compatibility layer.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Subscription $subscription Subscription instance.
		 */
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
		/**
		 * Triggered when a subscription renewal payment completes via the compatibility layer.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Subscription $subscription  Subscription instance.
		 * @param \WC_Order        $renewal_order Renewal order instance.
		 */
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
		/**
		 * Triggered when a subscription renewal payment fails via the compatibility layer.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Subscription $subscription  Subscription instance.
		 * @param \WC_Order        $renewal_order Renewal order instance.
		 */
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
		/**
		 * Triggered when a subscription payment fails via the compatibility layer.
		 *
		 * @since 1.0.0
		 *
		 * @param \WC_Subscription $subscription Subscription instance.
		 */
		do_action( 'wps_wcs_subscription_payment_failed', $subscription );
	}
}

// phpcs:enable WordPress.NamingConventions.ValidClassName
