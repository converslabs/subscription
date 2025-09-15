<?php
/**
 * WooCommerce Subscriptions Order Compatibility Class
 *
 * This class provides compatibility with WooCommerce Subscriptions WC_Subscriptions_Order class
 * by mapping it to WPSubscription's order functionality.
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
 * WC_Subscriptions_Order compatibility class
 */
class WC_Subscriptions_Order {

	/**
	 * Instance of this class
	 *
	 * @var WC_Subscriptions_Order
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return WC_Subscriptions_Order
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
	 * Initialize order functionality
	 *
	 * @return void
	 */
	private function init() {
		// Initialize order hooks
		add_action( 'init', array( $this, 'register_hooks' ) );
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Order hooks
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_changed' ), 10, 3 );
		add_action( 'woocommerce_payment_complete', array( $this, 'handle_payment_complete' ) );
		add_action( 'woocommerce_order_item_added', array( $this, 'handle_order_item_added' ), 10, 3 );
		add_action( 'woocommerce_order_item_removed', array( $this, 'handle_order_item_removed' ), 10, 2 );
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
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if order contains subscription products
		if ( $this->order_contains_subscription( $order ) ) {
			// Handle subscription order status change
			do_action( 'wp_subscription_order_status_changed', $order, $old_status, $new_status );
		}
	}

	/**
	 * Handle payment complete
	 *
	 * @param int $order_id Order ID
	 * @return void
	 */
	public function handle_payment_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if order contains subscription products
		if ( $this->order_contains_subscription( $order ) ) {
			// Handle subscription payment completion
			do_action( 'wp_subscription_payment_complete', $order );
		}
	}

	/**
	 * Handle order item added
	 *
	 * @param int $item_id Item ID
	 * @param array $item Item data
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function handle_order_item_added( $item_id, $item, $order ) {
		// Handle subscription item addition
		do_action( 'wp_subscription_order_item_added', $item_id, $item, $order );
	}

	/**
	 * Handle order item removed
	 *
	 * @param int $item_id Item ID
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function handle_order_item_removed( $item_id, $order ) {
		// Handle subscription item removal
		do_action( 'wp_subscription_order_item_removed', $item_id, $order );
	}

	/**
	 * Check if order contains subscription
	 *
	 * @param \WC_Order $order Order object
	 * @return bool
	 */
	public function order_contains_subscription( $order ) {
		foreach ( $order->get_items() as $item ) {
			if ( wcs_is_subscription_product( $item->get_product() ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get subscription from order
	 *
	 * @param int $order_id Order ID
	 * @return \WC_Subscription|false
	 */
	public function get_subscription_from_order( $order_id ) {
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
	 * Create subscription from order
	 *
	 * @param \WC_Order $order Order object
	 * @return \WC_Subscription|false
	 */
	public function create_subscription_from_order( $order ) {
		if ( ! $this->order_contains_subscription( $order ) ) {
			return false;
		}

		// Create subscription using WPSubscription functionality
		// This would need to be implemented based on your WPSubscription structure
		return false;
	}

	/**
	 * Get subscription items from order
	 *
	 * @param \WC_Order $order Order object
	 * @return array
	 */
	public function get_subscription_items_from_order( $order ) {
		$subscription_items = array();

		foreach ( $order->get_items() as $item ) {
			if ( wcs_is_subscription_product( $item->get_product() ) ) {
				$subscription_items[] = $item;
			}
		}

		return $subscription_items;
	}

	/**
	 * Get subscription total from order
	 *
	 * @param \WC_Order $order Order object
	 * @return float
	 */
	public function get_subscription_total_from_order( $order ) {
		$total = 0;

		foreach ( $order->get_items() as $item ) {
			if ( wcs_is_subscription_product( $item->get_product() ) ) {
				$total += $item->get_total();
			}
		}

		return $total;
	}

	/**
	 * Get subscription count from order
	 *
	 * @param \WC_Order $order Order object
	 * @return int
	 */
	public function get_subscription_count_from_order( $order ) {
		$count = 0;

		foreach ( $order->get_items() as $item ) {
			if ( wcs_is_subscription_product( $item->get_product() ) ) {
				$count += $item->get_quantity();
			}
		}

		return $count;
	}

	/**
	 * Get subscription products from order
	 *
	 * @param \WC_Order $order Order object
	 * @return array
	 */
	public function get_subscription_products_from_order( $order ) {
		$products = array();

		foreach ( $order->get_items() as $item ) {
			if ( wcs_is_subscription_product( $item->get_product() ) ) {
				$products[] = $item->get_product();
			}
		}

		return $products;
	}

	/**
	 * Get subscription product IDs from order
	 *
	 * @param \WC_Order $order Order object
	 * @return array
	 */
	public function get_subscription_product_ids_from_order( $order ) {
		$product_ids = array();

		foreach ( $order->get_items() as $item ) {
			if ( wcs_is_subscription_product( $item->get_product() ) ) {
				$product_ids[] = $item->get_product_id();
			}
		}

		return $product_ids;
	}

	/**
	 * Get subscription customer from order
	 *
	 * @param \WC_Order $order Order object
	 * @return int
	 */
	public function get_subscription_customer_from_order( $order ) {
		return $order->get_customer_id();
	}

	/**
	 * Get subscription billing address from order
	 *
	 * @param \WC_Order $order Order object
	 * @return array
	 */
	public function get_subscription_billing_address_from_order( $order ) {
		return array(
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'company'    => $order->get_billing_company(),
			'address_1'  => $order->get_billing_address_1(),
			'address_2'  => $order->get_billing_address_2(),
			'city'       => $order->get_billing_city(),
			'state'      => $order->get_billing_state(),
			'postcode'   => $order->get_billing_postcode(),
			'country'    => $order->get_billing_country(),
			'email'      => $order->get_billing_email(),
			'phone'      => $order->get_billing_phone(),
		);
	}

	/**
	 * Get subscription shipping address from order
	 *
	 * @param \WC_Order $order Order object
	 * @return array
	 */
	public function get_subscription_shipping_address_from_order( $order ) {
		return array(
			'first_name' => $order->get_shipping_first_name(),
			'last_name'  => $order->get_shipping_last_name(),
			'company'    => $order->get_shipping_company(),
			'address_1'  => $order->get_shipping_address_1(),
			'address_2'  => $order->get_shipping_address_2(),
			'city'       => $order->get_shipping_city(),
			'state'      => $order->get_shipping_state(),
			'postcode'   => $order->get_shipping_postcode(),
			'country'    => $order->get_shipping_country(),
		);
	}

	/**
	 * Get subscription payment method from order
	 *
	 * @param \WC_Order $order Order object
	 * @return string
	 */
	public function get_subscription_payment_method_from_order( $order ) {
		return $order->get_payment_method();
	}

	/**
	 * Get subscription payment method title from order
	 *
	 * @param \WC_Order $order Order object
	 * @return string
	 */
	public function get_subscription_payment_method_title_from_order( $order ) {
		return $order->get_payment_method_title();
	}
}
