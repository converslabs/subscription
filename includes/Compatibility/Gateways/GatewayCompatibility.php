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
		// Get available payment gateways
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		
		foreach ( $available_gateways as $gateway_id => $gateway ) {
			if ( $this->is_gateway_subscription_compatible( $gateway ) ) {
				// Add subscription support to gateway
				$gateway->supports[] = 'subscriptions';
				$gateway->supports[] = 'subscription_cancellation';
				$gateway->supports[] = 'subscription_suspension';
				$gateway->supports[] = 'subscription_reactivation';
				$gateway->supports[] = 'subscription_amount_changes';
				$gateway->supports[] = 'subscription_date_changes';
				$gateway->supports[] = 'subscription_payment_method_change_admin';
				$gateway->supports[] = 'subscription_payment_method_change_customer';
				$gateway->supports[] = 'multiple_subscriptions';
				
				// Add scheduled payment hook
				add_action( "woocommerce_scheduled_subscription_payment_{$gateway_id}", array( $this, 'handle_scheduled_payment' ), 10, 2 );
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
		// Check if gateway already supports subscriptions
		if ( method_exists( $gateway, 'supports' ) && $gateway->supports( 'subscriptions' ) ) {
			return true;
		}
		
		// Check specific gateway types that we know support subscriptions
		$compatible_gateways = array(
			'stripe_cc',
			'stripe',
			'paypal',
			'ppec_paypal',
			'square',
			'razorpay',
			'mollie',
			'authorize_net',
			'stripe_apple_pay',
			'stripe_google_pay',
		);
		
		return in_array( $gateway->id, $compatible_gateways, true );
	}

	/**
	 * Handle scheduled subscription payment
	 *
	 * @param float    $amount Payment amount
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function handle_scheduled_payment( $amount, $order ) {
		wp_subscrpt_write_debug_log( "GatewayCompatibility: Handling scheduled payment for order #{$order->get_id()}, amount: {$amount}" );

		// Get subscription ID from order
		$subscription_id = self::get_subscription_from_order( $order );
		if ( ! $subscription_id ) {
			wp_subscrpt_write_debug_log( "GatewayCompatibility: No subscription found for order #{$order->get_id()}" );
			return;
		}

		// Get payment method
		$payment_method = PaymentMethodManager::get_payment_method( $subscription_id, $order->get_payment_method() );
		if ( ! $payment_method ) {
			wp_subscrpt_write_debug_log( "GatewayCompatibility: No payment method found for subscription #{$subscription_id}" );
			$order->update_status( 'failed', 'No payment method found for renewal' );
			return;
		}

		// Set payment method token on order
		$order->update_meta_data( '_payment_method_token', $payment_method['payment_method_token'] );
		if ( ! empty( $payment_method['gateway_customer_id'] ) ) {
			$order->update_meta_data( '_gateway_customer_id', $payment_method['gateway_customer_id'] );
		}
		$order->save();

		// Process payment using the gateway's method
		$gateway = WC()->payment_gateways()->payment_gateways()[ $order->get_payment_method() ];
		if ( $gateway && method_exists( $gateway, 'scheduled_subscription_payment' ) ) {
			try {
				$result = $gateway->scheduled_subscription_payment( $amount, $order );
				
				if ( $result ) {
					wp_subscrpt_write_debug_log( "GatewayCompatibility: Payment processed successfully for order #{$order->get_id()}" );
					do_action( 'subscrpt_payment_success', $subscription_id, $order->get_id(), $order->get_payment_method() );
				} else {
					wp_subscrpt_write_debug_log( "GatewayCompatibility: Payment failed for order #{$order->get_id()}" );
					do_action( 'subscrpt_payment_failed', $subscription_id, $order->get_id(), $order->get_payment_method(), 'Gateway returned false' );
				}
			} catch ( Exception $e ) {
				wp_subscrpt_write_debug_log( "GatewayCompatibility: Payment error for order #{$order->get_id()}: " . $e->getMessage() );
				do_action( 'subscrpt_payment_failed', $subscription_id, $order->get_id(), $order->get_payment_method(), $e->getMessage() );
			}
		}
	}

	/**
	 * Get subscription ID from order
	 *
	 * @param \WC_Order $order Order object
	 * @return int|false Subscription ID or false if not found
	 */
	private static function get_subscription_from_order( $order ) {
		// Check if order has subscription meta
		$subscription_id = $order->get_meta( '_subscription_id' );
		if ( $subscription_id ) {
			return $subscription_id;
		}

		// Check order relation table
		global $wpdb;
		$subscription_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT subscription_id FROM {$wpdb->prefix}subscrpt_order_relation WHERE order_id = %d",
			$order->get_id()
		) );

		return $subscription_id ? (int) $subscription_id : false;
	}

	/**
	 * Handle payment complete
	 *
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function handle_payment_complete( $order ) {
		$subscription_id = self::get_subscription_from_order( $order );
		if ( $subscription_id ) {
			do_action( 'subscrpt_payment_success', $subscription_id, $order->get_id(), $order->get_payment_method() );
		}
	}

	/**
	 * Handle payment failed
	 *
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function handle_payment_failed( $order ) {
		$subscription_id = self::get_subscription_from_order( $order );
		if ( $subscription_id ) {
			do_action( 'subscrpt_payment_failed', $subscription_id, $order->get_id(), $order->get_payment_method(), 'Payment failed' );
		}
	}

	/**
	 * Handle renewal payment complete
	 *
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function handle_renewal_payment_complete( $order ) {
		$subscription_id = self::get_subscription_from_order( $order );
		if ( $subscription_id ) {
			do_action( 'subscrpt_payment_success', $subscription_id, $order->get_id(), $order->get_payment_method() );
		}
	}

	/**
	 * Handle renewal payment failed
	 *
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function handle_renewal_payment_failed( $order ) {
		$subscription_id = self::get_subscription_from_order( $order );
		if ( $subscription_id ) {
			do_action( 'subscrpt_payment_failed', $subscription_id, $order->get_id(), $order->get_payment_method(), 'Renewal payment failed' );
		}
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
