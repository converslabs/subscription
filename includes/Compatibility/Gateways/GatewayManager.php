<?php
/**
 * WooCommerce Subscriptions Gateway Manager Compatibility
 *
 * This class provides compatibility with WooCommerce Subscriptions payment gateways
 * by mapping them to WPSubscription's gateway functionality.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Compatibility\Gateways;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gateway manager compatibility class
 */
class GatewayManager {

	/**
	 * Instance of this class
	 *
	 * @var GatewayManager
	 */
	private static $instance = null;

	/**
	 * Registered gateways
	 *
	 * @var array
	 */
	private $registered_gateways = array();

	/**
	 * Get instance of this class
	 *
	 * @return GatewayManager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize gateway manager
	 *
	 * @return void
	 */
	private function init() {
		// Initialize gateway hooks
		add_action( 'init', array( $this, 'register_hooks' ) );
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Gateway hooks
		add_action( 'woocommerce_payment_gateways', array( $this, 'register_subscription_gateways' ) );
		add_action( 'woocommerce_subscription_payment_gateways', array( $this, 'register_subscription_gateways' ) );
		
		// Payment hooks
		add_action( 'woocommerce_subscription_payment_complete', array( $this, 'handle_payment_complete' ) );
		add_action( 'woocommerce_subscription_payment_failed', array( $this, 'handle_payment_failed' ) );
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'handle_renewal_payment_complete' ) );
		add_action( 'woocommerce_subscription_renewal_payment_failed', array( $this, 'handle_renewal_payment_failed' ) );
	}

	/**
	 * Register subscription gateways
	 *
	 * @param array $gateways Gateways array
	 * @return array
	 */
	public function register_subscription_gateways( $gateways ) {
		// Get available payment gateways
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		
		foreach ( $available_gateways as $gateway_id => $gateway ) {
			if ( $this->is_gateway_subscription_compatible( $gateway ) ) {
				$gateways[ $gateway_id ] = $gateway;
			}
		}
		
		return $gateways;
	}

	/**
	 * Check if gateway is subscription compatible
	 *
	 * @param \WC_Payment_Gateway $gateway Gateway object
	 * @return bool
	 */
	private function is_gateway_subscription_compatible( $gateway ) {
		// Check if gateway supports subscriptions
		if ( method_exists( $gateway, 'supports' ) && $gateway->supports( 'subscriptions' ) ) {
			return true;
		}
		
		// Check if gateway supports recurring payments
		if ( method_exists( $gateway, 'supports' ) && $gateway->supports( 'recurring_payments' ) ) {
			return true;
		}
		
		// Check specific gateway types
		$compatible_gateways = array(
			'stripe',
			'paypal',
			'authorize_net',
			'square',
			'razorpay',
			'mollie',
		);
		
		return in_array( $gateway->id, $compatible_gateways, true );
	}

	/**
	 * Handle payment complete
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @return void
	 */
	public function handle_payment_complete( $subscription ) {
		// Map to WPSubscription functionality
		do_action( 'wp_subscription_payment_complete', $subscription );
	}

	/**
	 * Handle payment failed
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @return void
	 */
	public function handle_payment_failed( $subscription ) {
		// Map to WPSubscription functionality
		do_action( 'wp_subscription_payment_failed', $subscription );
	}

	/**
	 * Handle renewal payment complete
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @param \WC_Order $renewal_order Renewal order
	 * @return void
	 */
	public function handle_renewal_payment_complete( $subscription, $renewal_order ) {
		// Map to WPSubscription functionality
		do_action( 'wp_subscription_renewal_payment_complete', $subscription, $renewal_order );
	}

	/**
	 * Handle renewal payment failed
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @param \WC_Order $renewal_order Renewal order
	 * @return void
	 */
	public function handle_renewal_payment_failed( $subscription, $renewal_order ) {
		// Map to WPSubscription functionality
		do_action( 'wp_subscription_renewal_payment_failed', $subscription, $renewal_order );
	}

	/**
	 * Initialize gateway compatibility
	 *
	 * @return void
	 */
	public function init_gateway_compatibility() {
		// Initialize gateway-specific compatibility features
		$this->init_stripe_compatibility();
		$this->init_paypal_compatibility();
		$this->init_other_gateways_compatibility();
	}

	/**
	 * Initialize Stripe compatibility
	 *
	 * @return void
	 */
	private function init_stripe_compatibility() {
		if ( ! class_exists( 'WC_Stripe' ) ) {
			return;
		}

		// Add Stripe subscription support
		add_filter( 'woocommerce_stripe_payment_intent_args', array( $this, 'add_stripe_subscription_support' ), 10, 2 );
		add_filter( 'woocommerce_stripe_payment_method_args', array( $this, 'add_stripe_subscription_support' ), 10, 2 );
	}

	/**
	 * Initialize PayPal compatibility
	 *
	 * @return void
	 */
	private function init_paypal_compatibility() {
		if ( ! class_exists( 'WC_Gateway_Paypal' ) ) {
			return;
		}

		// Add PayPal subscription support
		add_filter( 'woocommerce_paypal_args', array( $this, 'add_paypal_subscription_support' ), 10, 2 );
	}

	/**
	 * Initialize other gateways compatibility
	 *
	 * @return void
	 */
	private function init_other_gateways_compatibility() {
		// Add support for other payment gateways
		// This would be implemented based on your WPSubscription gateway support
	}

	/**
	 * Add Stripe subscription support
	 *
	 * @param array $args Payment arguments
	 * @param \WC_Order $order Order object
	 * @return array
	 */
	public function add_stripe_subscription_support( $args, $order ) {
		// Add subscription-specific arguments for Stripe
		// This would be implemented based on your WPSubscription Stripe integration
		return $args;
	}

	/**
	 * Add PayPal subscription support
	 *
	 * @param array $args Payment arguments
	 * @param \WC_Order $order Order object
	 * @return array
	 */
	public function add_paypal_subscription_support( $args, $order ) {
		// Add subscription-specific arguments for PayPal
		// This would be implemented based on your WPSubscription PayPal integration
		return $args;
	}

	/**
	 * Get subscription gateways
	 *
	 * @return array
	 */
	public function get_subscription_gateways() {
		$gateways = array();
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		
		foreach ( $available_gateways as $gateway_id => $gateway ) {
			if ( $this->is_gateway_subscription_compatible( $gateway ) ) {
				$gateways[ $gateway_id ] = $gateway;
			}
		}
		
		return $gateways;
	}

	/**
	 * Check if gateway supports subscriptions
	 *
	 * @param string $gateway_id Gateway ID
	 * @return bool
	 */
	public function gateway_supports_subscriptions( $gateway_id ) {
		$gateway = WC()->payment_gateways()->get_available_payment_gateway( $gateway_id );
		return $gateway && $this->is_gateway_subscription_compatible( $gateway );
	}

	/**
	 * Get gateway subscription settings
	 *
	 * @param string $gateway_id Gateway ID
	 * @return array
	 */
	public function get_gateway_subscription_settings( $gateway_id ) {
		$gateway = WC()->payment_gateways()->get_available_payment_gateway( $gateway_id );
		if ( ! $gateway ) {
			return array();
		}

		$settings = array(
			'supports_subscriptions' => $this->is_gateway_subscription_compatible( $gateway ),
			'gateway_id'             => $gateway_id,
			'gateway_title'          => $gateway->get_title(),
			'gateway_description'    => $gateway->get_description(),
		);

		return $settings;
	}
}
