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
}
