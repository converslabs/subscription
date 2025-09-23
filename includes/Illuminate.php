<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Frontend\Checkout;
use SpringDevs\Subscription\Illuminate\AutoRenewal;
use SpringDevs\Subscription\Illuminate\Block;
use SpringDevs\Subscription\Illuminate\Cron;
use SpringDevs\Subscription\Illuminate\Email;
use SpringDevs\Subscription\Illuminate\Order;
use SpringDevs\Subscription\Illuminate\Post;
use SpringDevs\Subscription\Illuminate\UniversalPaymentProcessor;
use SpringDevs\Subscription\Illuminate\PaymentMethodManager;
use SpringDevs\Subscription\Illuminate\ScheduledPaymentProcessor;
use SpringDevs\Subscription\Illuminate\DatabaseSchema;
use SpringDevs\Subscription\Illuminate\Gateways\Stripe;
use SpringDevs\Subscription\Illuminate\Gateways\PayPal;
use SpringDevs\Subscription\Illuminate\Gateways\Square;
use SpringDevs\Subscription\Illuminate\WebhookHandler;
use SpringDevs\Subscription\Illuminate\PaymentRetryManager;
use SpringDevs\Subscription\Illuminate\Analytics\PaymentAnalytics;
use SpringDevs\Subscription\Illuminate\Monitoring\SubscriptionHealth;
use SpringDevs\Subscription\Illuminate\Debug\DebugManager;

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
		new UniversalPaymentProcessor();
		
		// Initialize auto-renewal payment system
		$this->init_auto_renewal_system();
		
		// Initialize payment gateways
		$this->init_payment_gateways();
		
		// Initialize advanced features
		$this->init_advanced_features();
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
	 * Initialize auto-renewal payment system
	 *
	 * @return void
	 */
	private function init_auto_renewal_system() {
		// Create database tables if needed
		if ( DatabaseSchema::needs_update() ) {
			DatabaseSchema::create_tables();
			DatabaseSchema::update_table_version();
		}

		// Initialize scheduled payment processor
		ScheduledPaymentProcessor::init();
	}

	/**
	 * Initialize payment gateways
	 *
	 * @return void
	 */
	private function init_payment_gateways() {
		// Initialize Stripe integration
		if ( class_exists( '\Stripe\StripeClient' ) ) {
			new Stripe();
		}

		// Initialize PayPal integration
		if ( class_exists( '\PayPal\Rest\ApiContext' ) ) {
			new PayPal();
		}

		// Initialize Square integration
		if ( class_exists( '\Square\SquareClient' ) ) {
			new Square();
		}
	}

	/**
	 * Initialize advanced features
	 *
	 * @return void
	 */
	private function init_advanced_features() {
		// Initialize webhook handler
		new WebhookHandler();
		
		// Initialize payment retry manager
		new PaymentRetryManager();
		
		// Initialize analytics and monitoring
		new PaymentAnalytics();
		new SubscriptionHealth();
		
		// Initialize debug manager
		new DebugManager();
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
