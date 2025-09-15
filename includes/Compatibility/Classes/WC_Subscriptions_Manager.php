<?php
/**
 * WooCommerce Subscriptions Manager Compatibility Class
 *
 * This class provides compatibility with WooCommerce Subscriptions WC_Subscriptions_Manager class
 * by mapping it to WPSubscription's functionality.
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
 * WC_Subscriptions_Manager compatibility class
 */
class WC_Subscriptions_Manager {

	/**
	 * Instance of this class
	 *
	 * @var WC_Subscriptions_Manager
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return WC_Subscriptions_Manager
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
	 * Initialize manager
	 *
	 * @return void
	 */
	private function init() {
		// Initialize subscription management functionality
		add_action( 'init', array( $this, 'register_hooks' ) );
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Subscription lifecycle hooks
		add_action( 'woocommerce_subscription_status_changed', array( $this, 'handle_status_changed' ), 10, 3 );
		add_action( 'woocommerce_subscription_payment_complete', array( $this, 'handle_payment_complete' ) );
		add_action( 'woocommerce_subscription_payment_failed', array( $this, 'handle_payment_failed' ) );
		
		// Order hooks
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_changed' ), 10, 3 );
		add_action( 'woocommerce_payment_complete', array( $this, 'handle_payment_complete' ) );
	}

	/**
	 * Handle subscription status changed
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @param string $new_status New status
	 * @param string $old_status Old status
	 * @return void
	 */
	public function handle_status_changed( $subscription, $new_status, $old_status ) {
		// Map to WPSubscription functionality
		do_action( 'wp_subscription_status_changed', $subscription, $new_status, $old_status );
	}

	/**
	 * Handle subscription payment complete
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @return void
	 */
	public function handle_payment_complete( $subscription ) {
		// Map to WPSubscription functionality
		do_action( 'wp_subscription_payment_complete', $subscription );
	}

	/**
	 * Handle subscription payment failed
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @return void
	 */
	public function handle_payment_failed( $subscription ) {
		// Map to WPSubscription functionality
		do_action( 'wp_subscription_payment_failed', $subscription );
	}

	/**
	 * Handle order status changed
	 *
	 * @param int $order_id Order ID
	 * @param string $old_status Old status
	 * @param string $new_status New status
	 * @return void
	 */
	public function handle_order_status_changed( $order_id, $old_status, $new_status ) {
		// Check if order contains subscription products
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product && function_exists( 'wcs_is_subscription_product' ) && wcs_is_subscription_product( $product ) ) {
				// Handle subscription order status change
				do_action( 'wp_subscription_order_status_changed', $order, $old_status, $new_status );
				break;
			}
		}
	}

	/**
	 * Create subscription from order
	 *
	 * @param \WC_Order $order Order object
	 * @return \WC_Subscription|false
	 */
	public function create_subscription_from_order( $order ) {
		// Check if order contains subscription products
		$has_subscription = false;
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product && function_exists( 'wcs_is_subscription_product' ) && wcs_is_subscription_product( $product ) ) {
				$has_subscription = true;
				break;
			}
		}

		if ( ! $has_subscription ) {
			return false;
		}

		// Create subscription using WPSubscription functionality
		// This would need to be implemented based on your WPSubscription structure
		return false;
	}

	/**
	 * Get subscription by order
	 *
	 * @param int $order_id Order ID
	 * @return \WC_Subscription|false
	 */
	public function get_subscription_by_order( $order_id ) {
		// Find subscription by order ID
		$subscriptions = get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => '_parent_order_id',
					'value' => $order_id,
				),
			),
		) );

		if ( empty( $subscriptions ) ) {
			return false;
		}

		return new \WC_Subscription( $subscriptions[0]->ID );
	}

	/**
	 * Get subscriptions by customer
	 *
	 * @param int $customer_id Customer ID
	 * @param string $status Subscription status
	 * @return array
	 */
	public function get_subscriptions_by_customer( $customer_id, $status = 'any' ) {
		$args = array(
			'post_type'      => 'shop_subscription',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_customer_user',
					'value' => $customer_id,
				),
			),
		);

		if ( 'any' !== $status ) {
			$args['post_status'] = $status;
		}

		$subscriptions = get_posts( $args );
		$subscription_objects = array();

		foreach ( $subscriptions as $subscription_post ) {
			$subscription_objects[] = new \WC_Subscription( $subscription_post->ID );
		}

		return $subscription_objects;
	}

	/**
	 * Get active subscriptions by customer
	 *
	 * @param int $customer_id Customer ID
	 * @return array
	 */
	public function get_active_subscriptions_by_customer( $customer_id ) {
		return $this->get_subscriptions_by_customer( $customer_id, 'wc-active' );
	}

	/**
	 * Get subscriptions by product
	 *
	 * @param int $product_id Product ID
	 * @param string $status Subscription status
	 * @return array
	 */
	public function get_subscriptions_by_product( $product_id, $status = 'any' ) {
		$args = array(
			'post_type'      => 'shop_subscription',
			'post_status'    => 'any',
			'posts_per_page' => -1,
		);

		if ( 'any' !== $status ) {
			$args['post_status'] = $status;
		}

		$subscriptions = get_posts( $args );
		$subscription_objects = array();

		foreach ( $subscriptions as $subscription_post ) {
			$subscription = new \WC_Subscription( $subscription_post->ID );
			
			// Check if subscription contains the product
			foreach ( $subscription->get_items() as $item ) {
				if ( method_exists( $item, 'get_product_id' ) && $item->get_product_id() === $product_id ) {
					$subscription_objects[] = $subscription;
					break;
				}
			}
		}

		return $subscription_objects;
	}

	/**
	 * Get subscription count by status
	 *
	 * @param string $status Subscription status
	 * @return int
	 */
	public function get_subscription_count_by_status( $status ) {
		$subscriptions = get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => $status,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		return count( $subscriptions );
	}

	/**
	 * Get total subscription count
	 *
	 * @return int
	 */
	public function get_total_subscription_count() {
		$subscriptions = get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		return count( $subscriptions );
	}

	/**
	 * Get subscription revenue
	 *
	 * @param string $status Subscription status
	 * @return float
	 */
	public function get_subscription_revenue( $status = 'wc-active' ) {
		$subscriptions = get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => $status,
			'posts_per_page' => -1,
		) );

		$revenue = 0;
		foreach ( $subscriptions as $subscription_post ) {
			$subscription = new \WC_Subscription( $subscription_post->ID );
			$revenue += $subscription->get_total();
		}

		return $revenue;
	}

	/**
	 * Get subscription statistics
	 *
	 * @return array
	 */
	public function get_subscription_statistics() {
		$stats = array(
			'total'           => $this->get_total_subscription_count(),
			'active'          => $this->get_subscription_count_by_status( 'wc-active' ),
			'pending'         => $this->get_subscription_count_by_status( 'wc-pending' ),
			'on_hold'         => $this->get_subscription_count_by_status( 'wc-on-hold' ),
			'cancelled'       => $this->get_subscription_count_by_status( 'wc-cancelled' ),
			'expired'         => $this->get_subscription_count_by_status( 'wc-expired' ),
			'pending_cancel'  => $this->get_subscription_count_by_status( 'wc-pending-cancel' ),
			'revenue'         => $this->get_subscription_revenue(),
		);

		return $stats;
	}
}
