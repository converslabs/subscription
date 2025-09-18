<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\Checkout;
use SpringDevs\Subscription\Illuminate\AutoRenewal;
use SpringDevs\Subscription\Illuminate\Block;
use SpringDevs\Subscription\Illuminate\Cron;
use SpringDevs\Subscription\Illuminate\Email;
use SpringDevs\Subscription\Illuminate\Order;
use SpringDevs\Subscription\Illuminate\Post;
use SpringDevs\Subscription\Illuminate\Gateways\Stripe\Stripe;

/**
 * Globally Load Scripts.
 */
class Illuminate {

	/**
	 * Initialize the Class.
	 */
	public function __construct() {
		$this->stripe_initialization();
		$this->paypal_initialization();
		new Order();
		new Cron();
		new Post();
		new Block();
		new Checkout();
		new AutoRenewal();
		new Email();
	}

	/**
	 * Stripe Initialization.
	 *
	 * @return void
	 */
	public function stripe_initialization() {
		if ( function_exists( 'woocommerce_gateway_stripe' ) ) {
			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/compat/trait-wc-stripe-subscriptions-utilities.php';
			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/compat/trait-wc-stripe-pre-orders.php';
			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/compat/trait-wc-stripe-subscriptions.php';
			include_once dirname( WC_STRIPE_MAIN_FILE ) . '/includes/abstracts/abstract-wc-stripe-payment-gateway.php';

			new Stripe();
		}
	}

	/**
	 * PayPal Gateway Initialization.
	 *
	 * @return void
	 */
	public function paypal_initialization() {
		// Forcefully enable PayPal integration if the option is not set.
		update_option( 'wp_subs_paypal_integration_enabled', 'on' );

		$is_paypal_integration_enabled = 'on' === get_option( 'wp_subs_paypal_integration_enabled', 'off' );

		// Register the PayPal gateway with WooCommerce.
		if ( $is_paypal_integration_enabled ) {
			add_filter( 'woocommerce_payment_gateways', array( $this, 'register_paypal_gateway' ) );
		}
	}

	/**
	 * Register our custom PayPal gateway with WooCommerce
	 *
	 * @param array $gateways Payment gateways.
	 * @return array
	 */
	public function register_paypal_gateway( $gateways ) {
		$gateways[] = 'SpringDevs\\Subscription\\Illuminate\\Gateways\\Paypal\\Paypal';
		return $gateways;
	}
}
