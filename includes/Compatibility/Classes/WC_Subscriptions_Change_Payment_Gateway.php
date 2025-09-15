<?php
/**
 * WooCommerce Subscriptions Change Payment Gateway Compatibility Class
 *
 * This class provides compatibility with WooCommerce Subscriptions WC_Subscriptions_Change_Payment_Gateway class
 * by mapping it to WPSubscription's payment gateway change functionality.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Compatibility\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Subscriptions_Change_Payment_Gateway compatibility class
 */
class WC_Subscriptions_Change_Payment_Gateway {

	/**
	 * Flag to indicate if this is a request to change payment method
	 *
	 * @var bool
	 */
	public static $is_request_to_change_payment = false;

	/**
	 * Instance of this class
	 *
	 * @var WC_Subscriptions_Change_Payment_Gateway
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return WC_Subscriptions_Change_Payment_Gateway
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
	 * Initialize change payment gateway functionality
	 *
	 * @return void
	 */
	private function init() {
		// Initialize hooks
		add_action( 'init', array( $this, 'register_hooks' ) );
		add_action( 'woocommerce_subscriptions_pre_update_payment_method', array( $this, 'set_change_payment_flag' ) );
		add_action( 'woocommerce_subscriptions_updated_payment_method', array( $this, 'clear_change_payment_flag' ) );
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Check if this is a change payment method request
		add_action( 'wp_loaded', array( $this, 'check_change_payment_request' ) );
	}

	/**
	 * Check if this is a change payment method request
	 *
	 * @return void
	 */
	public function check_change_payment_request() {
		if ( is_admin() ) {
			return;
		}

		// Check if we're on the change payment method page
		if ( is_page() && get_the_ID() === wc_get_page_id( 'myaccount' ) ) {
			$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
			if ( 'change_payment_method' === $action ) {
				self::$is_request_to_change_payment = true;
			}
		}

		// Check if we're processing a change payment method request
		if ( isset( $_POST['woocommerce_change_payment_method'] ) ) {
			self::$is_request_to_change_payment = true;
		}
	}

	/**
	 * Set change payment flag
	 *
	 * @return void
	 */
	public function set_change_payment_flag() {
		self::$is_request_to_change_payment = true;
	}

	/**
	 * Clear change payment flag
	 *
	 * @return void
	 */
	public function clear_change_payment_flag() {
		self::$is_request_to_change_payment = false;
	}

	/**
	 * Check if this is a change payment method request
	 *
	 * @return bool
	 */
	public static function is_request_to_change_payment() {
		return self::$is_request_to_change_payment;
	}

	/**
	 * Get change payment method URL
	 *
	 * @param int $subscription_id Subscription ID
	 * @return string
	 */
	public static function get_change_payment_method_url( $subscription_id ) {
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return '';
		}

		$myaccount_url = wc_get_page_permalink( 'myaccount' );
		return add_query_arg( array(
			'action'           => 'change_payment_method',
			'subscription_id'  => $subscription_id,
		), $myaccount_url );
	}

	/**
	 * Process change payment method request
	 *
	 * @param int $subscription_id Subscription ID
	 * @param string $new_payment_method New payment method
	 * @return bool
	 */
	public static function process_change_payment_method( $subscription_id, $new_payment_method ) {
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return false;
		}

		// Update payment method
		$subscription->set_payment_method( $new_payment_method );
		$subscription->save();

		// Trigger action
		do_action( 'woocommerce_subscriptions_updated_payment_method', $subscription, $new_payment_method );

		return true;
	}

	/**
	 * Get available payment gateways for subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @return array
	 */
	public static function get_available_payment_gateways( $subscription_id ) {
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return array();
		}

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$subscription_gateways = array();

		foreach ( $available_gateways as $gateway ) {
			if ( $gateway->supports( 'subscriptions' ) ) {
				$subscription_gateways[ $gateway->id ] = $gateway;
			}
		}

		return $subscription_gateways;
	}

	/**
	 * Validate payment method for subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @param string $payment_method Payment method
	 * @return bool
	 */
	public static function validate_payment_method( $subscription_id, $payment_method ) {
		$available_gateways = self::get_available_payment_gateways( $subscription_id );
		return isset( $available_gateways[ $payment_method ] );
	}
}
