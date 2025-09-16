<?php
/**
 * Subscription Creator
 *
 * This class handles the creation of subscriptions when orders with subscription products are processed.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscription Creator class
 */
class SubscriptionCreator {

	/**
	 * Instance of this class
	 *
	 * @var SubscriptionCreator
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return SubscriptionCreator
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
		// Wait for functions to be available
		add_action( 'init', array( $this, 'init' ), 20 );
	}

	/**
	 * Initialize subscription creator
	 *
	 * @return void
	 */
	public function init() {
		// Only initialize if functions are available
		if ( ! function_exists( 'wcs_order_contains_subscription' ) || ! function_exists( 'wcs_is_subscription_product' ) ) {
			return;
		}

		// Hook into order processing
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_create_subscriptions' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_create_subscriptions' ), 10, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_create_subscriptions' ), 10, 1 );
	}

	/**
	 * Maybe create subscriptions for order
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function maybe_create_subscriptions( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if order contains subscription products
		if ( ! function_exists( 'wcs_order_contains_subscription' ) || ! wcs_order_contains_subscription( $order ) ) {
			return;
		}

		// Create subscriptions for each subscription product in the order
		$this->create_subscriptions_for_order( $order );
	}

	/**
	 * Create subscriptions for order
	 *
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	private function create_subscriptions_for_order( $order ) {
		$subscription_ids = array();

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || ! function_exists( 'wcs_is_subscription_product' ) || ! wcs_is_subscription_product( $product ) ) {
				continue;
			}

			// Create subscription for this product
			$subscription_id = $this->create_subscription( $order, $item, $product );
			if ( $subscription_id ) {
				$subscription_ids[] = $subscription_id;
			}
		}

		// Store subscription IDs in order meta
		if ( ! empty( $subscription_ids ) ) {
			$order->update_meta_data( '_subscription_id', $subscription_ids );
			$order->save();
		}
	}

	/**
	 * Create subscription for order item
	 *
	 * @param \WC_Order $order Order object
	 * @param \WC_Order_Item $item Order item
	 * @param \WC_Product $product Product object
	 * @return int|false Subscription ID or false on failure
	 */
	private function create_subscription( $order, $item, $product ) {
		// Create subscription post
		$subscription_data = array(
			'post_type'     => 'shop_subscription',
			'post_status'   => 'wc-pending',
			'post_author'   => $order->get_user_id(),
			'post_parent'   => $order->get_id(),
			'post_title'    => sprintf( __( 'Subscription &ndash; %s', 'woocommerce-subscriptions' ), strftime( _x( '%b %d, %Y @ %I:%M %p', 'Order date parsed by strftime', 'woocommerce-subscriptions' ) ) ),
		);

		$subscription_id = wp_insert_post( $subscription_data );
		if ( is_wp_error( $subscription_id ) ) {
			return false;
		}

		// Create subscription object
		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			return false;
		}
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return false;
		}

		// Set subscription data
		$this->set_subscription_data( $subscription, $order, $item, $product );

		// Add subscription item
		$this->add_subscription_item( $subscription, $item, $product );

		// Set subscription status based on order status
		$this->set_subscription_status( $subscription, $order );

		// Save subscription
		$subscription->save();

		// Trigger action
		do_action( 'woocommerce_subscription_order_created', $subscription, $order );

		return $subscription_id;
	}

	/**
	 * Set subscription data
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @param \WC_Order $order Order object
	 * @param \WC_Order_Item $item Order item
	 * @param \WC_Product $product Product object
	 * @return void
	 */
	private function set_subscription_data( $subscription, $order, $item, $product ) {
		// Basic data
		$subscription->set_currency( $order->get_currency() );
		$subscription->set_customer_id( $order->get_customer_id() );
		$subscription->set_payment_method( $order->get_payment_method() );
		$subscription->set_payment_method_title( $order->get_payment_method_title() );

		// Billing data
		$subscription->set_billing_first_name( $order->get_billing_first_name() );
		$subscription->set_billing_last_name( $order->get_billing_last_name() );
		$subscription->set_billing_company( $order->get_billing_company() );
		$subscription->set_billing_address_1( $order->get_billing_address_1() );
		$subscription->set_billing_address_2( $order->get_billing_address_2() );
		$subscription->set_billing_city( $order->get_billing_city() );
		$subscription->set_billing_state( $order->get_billing_state() );
		$subscription->set_billing_postcode( $order->get_billing_postcode() );
		$subscription->set_billing_country( $order->get_billing_country() );
		$subscription->set_billing_email( $order->get_billing_email() );
		$subscription->set_billing_phone( $order->get_billing_phone() );

		// Shipping data
		$subscription->set_shipping_first_name( $order->get_shipping_first_name() );
		$subscription->set_shipping_last_name( $order->get_shipping_last_name() );
		$subscription->set_shipping_company( $order->get_shipping_company() );
		$subscription->set_shipping_address_1( $order->get_shipping_address_1() );
		$subscription->set_shipping_address_2( $order->get_shipping_address_2() );
		$subscription->set_shipping_city( $order->get_shipping_city() );
		$subscription->set_shipping_state( $order->get_shipping_state() );
		$subscription->set_shipping_postcode( $order->get_shipping_postcode() );
		$subscription->set_shipping_country( $order->get_shipping_country() );

		// Subscription specific data
		$subscription->set_billing_period( $product->get_meta( '_subscription_period', true ) ?: 'month' );
		$subscription->set_billing_interval( (int) $product->get_meta( '_subscription_interval', true ) ?: 1 );
		$subscription->set_length( (int) $product->get_meta( '_subscription_length', true ) ?: 0 );

		// Trial data
		$trial_length = (int) $product->get_meta( '_subscription_trial_length', true ) ?: 0;
		$trial_period = $product->get_meta( '_subscription_trial_period', true ) ?: 'day';
		$subscription->set_trial_length( $trial_length );
		$subscription->set_trial_period( $trial_period );

		// Dates
		$subscription->set_date_created( $order->get_date_created() );
		$subscription->set_date_modified( current_time( 'mysql' ) );
		$subscription->set_date_paid( $order->get_date_paid() );

		// Calculate next payment date
		$this->set_next_payment_date( $subscription, $trial_length, $trial_period );
	}

	/**
	 * Add subscription item
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @param \WC_Order_Item $item Order item
	 * @param \WC_Product $product Product object
	 * @return void
	 */
	private function add_subscription_item( $subscription, $item, $product ) {
		$subscription_item = new \WC_Order_Item_Product();
		$subscription_item->set_props( array(
			'name'         => $item->get_name(),
			'quantity'     => $item->get_quantity(),
			'tax_class'    => $item->get_tax_class(),
			'product_id'   => $item->get_product_id(),
			'variation_id' => $item->get_variation_id(),
			'subtotal'     => $item->get_subtotal(),
			'total'        => $item->get_total(),
			'taxes'        => $item->get_taxes(),
		) );

		$subscription->add_item( $subscription_item );
	}

	/**
	 * Set subscription status based on order status
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	private function set_subscription_status( $subscription, $order ) {
		$order_status = $order->get_status();
		
		switch ( $order_status ) {
			case 'completed':
			case 'processing':
				$subscription->set_status( 'active' );
				break;
			case 'pending':
				$subscription->set_status( 'pending' );
				break;
			case 'on-hold':
				$subscription->set_status( 'on-hold' );
				break;
			case 'cancelled':
				$subscription->set_status( 'cancelled' );
				break;
			default:
				$subscription->set_status( 'pending' );
				break;
		}
	}

	/**
	 * Set next payment date
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @param int $trial_length Trial length
	 * @param string $trial_period Trial period
	 * @return void
	 */
	private function set_next_payment_date( $subscription, $trial_length, $trial_period ) {
		if ( $trial_length > 0 ) {
			// Has trial period
			if ( function_exists( 'wcs_add_time' ) ) {
				$trial_end = wcs_add_time( $trial_length, $trial_period, $subscription->get_date_created() );
				$subscription->set_trial_end( $trial_end );
				$subscription->set_next_payment( $trial_end );
			}
		} else {
			// No trial period, next payment is based on billing period
			$billing_interval = $subscription->get_billing_interval();
			$billing_period = $subscription->get_billing_period();
			if ( function_exists( 'wcs_add_time' ) ) {
				$next_payment = wcs_add_time( $billing_interval, $billing_period, $subscription->get_date_created() );
				$subscription->set_next_payment( $next_payment );
			}
		}
	}
}
