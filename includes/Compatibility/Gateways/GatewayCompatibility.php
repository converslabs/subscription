<?php
/**
 * WooCommerce Subscriptions Gateway Compatibility
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
 * Gateway compatibility class
 */
class GatewayCompatibility {

	/**
	 * Instance of this class
	 *
	 * @var GatewayCompatibility
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return GatewayCompatibility
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
	 * Initialize gateway compatibility
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
		add_action( 'woocommerce_payment_gateways', array( $this, 'add_subscription_support' ) );
		add_action( 'woocommerce_subscription_payment_gateways', array( $this, 'add_subscription_support' ) );
		
		// Payment hooks
		add_action( 'woocommerce_subscription_payment_complete', array( $this, 'handle_payment_complete' ) );
		add_action( 'woocommerce_subscription_payment_failed', array( $this, 'handle_payment_failed' ) );
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'handle_renewal_payment_complete' ) );
		add_action( 'woocommerce_subscription_renewal_payment_failed', array( $this, 'handle_renewal_payment_failed' ) );
	}

	/**
	 * Add subscription support to gateways
	 *
	 * @param array $gateways Gateways array
	 * @return array
	 */
	public function add_subscription_support( $gateways ) {
		foreach ( $gateways as $gateway ) {
			if ( method_exists( $gateway, 'supports' ) ) {
				$gateway->supports[] = 'subscriptions';
			}
		}
		return $gateways;
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
	 * Check if gateway supports subscriptions
	 *
	 * @param string $gateway_id Gateway ID
	 * @return bool
	 */
	public function gateway_supports_subscriptions( $gateway_id ) {
		$gateway = WC()->payment_gateways()->get_available_payment_gateway( $gateway_id );
		if ( ! $gateway ) {
			return false;
		}

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

		return in_array( $gateway_id, $compatible_gateways, true );
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
			if ( $this->gateway_supports_subscriptions( $gateway_id ) ) {
				$gateways[ $gateway_id ] = $gateway;
			}
		}
		
		return $gateways;
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
			'supports_subscriptions' => $this->gateway_supports_subscriptions( $gateway_id ),
			'gateway_id'             => $gateway_id,
			'gateway_title'          => $gateway->get_title(),
			'gateway_description'    => $gateway->get_description(),
		);

		return $settings;
	}
}
