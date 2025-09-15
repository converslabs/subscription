<?php
/**
 * WooCommerce Subscriptions Cart Compatibility Class
 *
 * This class provides compatibility with WooCommerce Subscriptions WC_Subscriptions_Cart class
 * by mapping it to WPSubscription's cart functionality.
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
 * WC_Subscriptions_Cart compatibility class
 */
class WC_Subscriptions_Cart {

	/**
	 * Instance of this class
	 *
	 * @var WC_Subscriptions_Cart
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return WC_Subscriptions_Cart
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
	 * Initialize cart functionality
	 *
	 * @return void
	 */
	private function init() {
		// Initialize cart hooks
		add_action( 'init', array( $this, 'register_hooks' ) );
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Cart hooks
		add_action( 'woocommerce_add_to_cart', array( $this, 'handle_add_to_cart' ), 10, 6 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'handle_cart_item_removed' ) );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'handle_cart_item_restored' ) );
		add_action( 'woocommerce_cart_updated', array( $this, 'handle_cart_updated' ) );
		
		// Checkout hooks
		add_action( 'woocommerce_checkout_process', array( $this, 'handle_checkout_process' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'handle_checkout_order_processed' ) );
		
		// Display hooks
		add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'display_subscription_totals' ) );
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'display_subscription_totals' ) );
	}

	/**
	 * Handle add to cart
	 *
	 * @param string $cart_item_key Cart item key
	 * @param int $product_id Product ID
	 * @param int $quantity Quantity
	 * @param int $variation_id Variation ID
	 * @param array $variation Variation data
	 * @param array $cart_item_data Cart item data
	 * @return void
	 */
	public function handle_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		// Check if product is subscription product
		if ( wcs_is_subscription_product( $product ) ) {
			// Handle subscription product add to cart
			do_action( 'wp_subscription_add_to_cart', $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data );
		}
	}

	/**
	 * Handle cart item removed
	 *
	 * @param string $cart_item_key Cart item key
	 * @param \WC_Cart $cart Cart object
	 * @return void
	 */
	public function handle_cart_item_removed( $cart_item_key, $cart ) {
		// Handle subscription cart item removal
		do_action( 'wp_subscription_cart_item_removed', $cart_item_key, $cart );
	}

	/**
	 * Handle cart item restored
	 *
	 * @param string $cart_item_key Cart item key
	 * @param \WC_Cart $cart Cart object
	 * @return void
	 */
	public function handle_cart_item_restored( $cart_item_key, $cart ) {
		// Handle subscription cart item restoration
		do_action( 'wp_subscription_cart_item_restored', $cart_item_key, $cart );
	}

	/**
	 * Handle cart updated
	 *
	 * @return void
	 */
	public function handle_cart_updated() {
		// Handle subscription cart updates
		do_action( 'wp_subscription_cart_updated' );
	}

	/**
	 * Handle checkout process
	 *
	 * @return void
	 */
	public function handle_checkout_process() {
		// Handle subscription checkout process
		do_action( 'wp_subscription_checkout_process' );
	}

	/**
	 * Handle checkout order processed
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function handle_checkout_order_processed( $order_id ) {
		// Handle subscription checkout order processing
		do_action( 'wp_subscription_checkout_order_processed', $order_id );
	}

	/**
	 * Display subscription totals
	 *
	 * @return void
	 */
	public function display_subscription_totals() {
		if ( ! $this->cart_contains_subscription() ) {
			return;
		}

		// Display subscription totals
		// This would be implemented based on your WPSubscription display functionality
		echo '<tr class="subscription-totals">';
		echo '<th>' . __( 'Subscription Total', 'woocommerce-subscriptions' ) . '</th>';
		echo '<td>' . wc_price( $this->get_subscription_total() ) . '</td>';
		echo '</tr>';
	}

	/**
	 * Check if cart contains subscription
	 *
	 * @return bool
	 */
	public function cart_contains_subscription_instance() {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( wcs_is_subscription_product( $cart_item['data'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get subscription total from cart
	 *
	 * @return float
	 */
	public function get_subscription_total() {
		if ( ! WC()->cart ) {
			return 0;
		}

		$total = 0;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( wcs_is_subscription_product( $cart_item['data'] ) ) {
				$total += $cart_item['line_total'];
			}
		}

		return $total;
	}

	/**
	 * Get subscription count from cart
	 *
	 * @return int
	 */
	public function get_subscription_count() {
		if ( ! WC()->cart ) {
			return 0;
		}

		$count = 0;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( wcs_is_subscription_product( $cart_item['data'] ) ) {
				$count += $cart_item['quantity'];
			}
		}

		return $count;
	}

	/**
	 * Get subscription items from cart
	 *
	 * @return array
	 */
	public function get_subscription_items() {
		if ( ! WC()->cart ) {
			return array();
		}

		$items = array();
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( wcs_is_subscription_product( $cart_item['data'] ) ) {
				$items[ $cart_item_key ] = $cart_item;
			}
		}

		return $items;
	}

	/**
	 * Get subscription products from cart
	 *
	 * @return array
	 */
	public function get_subscription_products() {
		if ( ! WC()->cart ) {
			return array();
		}

		$products = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( wcs_is_subscription_product( $cart_item['data'] ) ) {
				$products[] = $cart_item['data'];
			}
		}

		return $products;
	}

	/**
	 * Get subscription product IDs from cart
	 *
	 * @return array
	 */
	public function get_subscription_product_ids() {
		if ( ! WC()->cart ) {
			return array();
		}

		$product_ids = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( wcs_is_subscription_product( $cart_item['data'] ) ) {
				$product_ids[] = $cart_item['product_id'];
			}
		}

		return $product_ids;
	}

	/**
	 * Check if cart contains renewal
	 *
	 * @return bool
	 */
	public function cart_contains_renewal_instance() {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['subscription_renewal'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Static method to check if cart contains subscription
	 *
	 * @return bool
	 */
	public static function cart_contains_subscription() {
		$instance = self::get_instance();
		return $instance->cart_contains_subscription_instance();
	}

	/**
	 * Static method to check if cart contains renewal
	 *
	 * @return bool
	 */
	public static function cart_contains_renewal() {
		$instance = self::get_instance();
		return $instance->cart_contains_renewal_instance();
	}

	/**
	 * Static method to check if cart contains free trial
	 *
	 * @return bool
	 */
	public static function cart_contains_free_trial() {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( wcs_is_subscription_product( $cart_item['data'] ) ) {
				$trial_length = $cart_item['data']->get_meta( '_subscription_trial_length' );
				if ( $trial_length && $trial_length > 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get renewal subscription ID from cart
	 *
	 * @return int|false
	 */
	public function get_renewal_subscription_id() {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['subscription_renewal'] ) ) {
				return $cart_item['subscription_renewal'];
			}
		}

		return false;
	}

	/**
	 * Add renewal to cart
	 *
	 * @param int $subscription_id Subscription ID
	 * @param int $quantity Quantity
	 * @return bool
	 */
	public function add_renewal_to_cart( $subscription_id, $quantity = 1 ) {
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return false;
		}

		// Add renewal to cart
		// This would be implemented based on your WPSubscription renewal functionality
		return false;
	}

	/**
	 * Remove renewal from cart
	 *
	 * @param int $subscription_id Subscription ID
	 * @return bool
	 */
	public function remove_renewal_from_cart( $subscription_id ) {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item['subscription_renewal'] ) && $cart_item['subscription_renewal'] === $subscription_id ) {
				WC()->cart->remove_cart_item( $cart_item_key );
				return true;
			}
		}

		return false;
	}
}
