<?php
/**
 * WooCommerce Subscriptions Order Data Store Compatibility
 *
 * This class provides compatibility with WooCommerce Subscriptions order data store
 * by mapping it to WPSubscription's order functionality.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Compatibility\DataStores;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order data store compatibility class
 */
class OrderDataStore extends \WC_Order_Data_Store_CPT {

	/**
	 * Data stored in meta keys, passed as $this->internal_meta_keys.
	 *
	 * @var array
	 */
	protected $internal_meta_keys = array(
		'_subscription_renewal',
		'_subscription_switch',
		'_subscription_resubscribe',
		'_subscription_payment_method',
		'_subscription_payment_method_title',
	);

	/**
	 * Get order type
	 *
	 * @return string
	 */
	public function get_order_type() {
		return 'shop_order';
	}

	/**
	 * Read order data
	 *
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function read( &$order ) {
		parent::read( $order );
		
		// Read subscription-specific data
		$order->set_meta_data( '_subscription_renewal', $this->get_meta( $order->get_id(), '_subscription_renewal', true ) );
		$order->set_meta_data( '_subscription_switch', $this->get_meta( $order->get_id(), '_subscription_switch', true ) );
		$order->set_meta_data( '_subscription_resubscribe', $this->get_meta( $order->get_id(), '_subscription_resubscribe', true ) );
		$order->set_meta_data( '_subscription_payment_method', $this->get_meta( $order->get_id(), '_subscription_payment_method', true ) );
		$order->set_meta_data( '_subscription_payment_method_title', $this->get_meta( $order->get_id(), '_subscription_payment_method_title', true ) );
	}

	/**
	 * Save order data
	 *
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function update( &$order ) {
		parent::update( $order );
		
		// Save subscription-specific data
		$this->update_meta( $order->get_id(), '_subscription_renewal', $order->get_meta( '_subscription_renewal', true ) );
		$this->update_meta( $order->get_id(), '_subscription_switch', $order->get_meta( '_subscription_switch', true ) );
		$this->update_meta( $order->get_id(), '_subscription_resubscribe', $order->get_meta( '_subscription_resubscribe', true ) );
		$this->update_meta( $order->get_id(), '_subscription_payment_method', $order->get_meta( '_subscription_payment_method', true ) );
		$this->update_meta( $order->get_id(), '_subscription_payment_method_title', $order->get_meta( '_subscription_payment_method_title', true ) );
	}

	/**
	 * Create order
	 *
	 * @param \WC_Order $order Order object
	 * @return void
	 */
	public function create( &$order ) {
		parent::create( $order );
		
		// Set subscription-specific data
		$order->set_meta_data( '_subscription_renewal', $order->get_meta( '_subscription_renewal', true ) ?: '' );
		$order->set_meta_data( '_subscription_switch', $order->get_meta( '_subscription_switch', true ) ?: '' );
		$order->set_meta_data( '_subscription_resubscribe', $order->get_meta( '_subscription_resubscribe', true ) ?: '' );
		$order->set_meta_data( '_subscription_payment_method', $order->get_meta( '_subscription_payment_method', true ) ?: '' );
		$order->set_meta_data( '_subscription_payment_method_title', $order->get_meta( '_subscription_payment_method_title', true ) ?: '' );
	}

	/**
	 * Get renewal orders by subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @return array
	 */
	public function get_renewal_orders_by_subscription( $subscription_id ) {
		$orders = get_posts( array(
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_subscription_renewal',
					'value' => $subscription_id,
				),
			),
		) );

		$order_ids = array();
		foreach ( $orders as $order_post ) {
			$order_ids[] = $order_post->ID;
		}

		return $order_ids;
	}

	/**
	 * Get switch orders by subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @return array
	 */
	public function get_switch_orders_by_subscription( $subscription_id ) {
		$orders = get_posts( array(
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_subscription_switch',
					'value' => $subscription_id,
				),
			),
		) );

		$order_ids = array();
		foreach ( $orders as $order_post ) {
			$order_ids[] = $order_post->ID;
		}

		return $order_ids;
	}

	/**
	 * Get resubscribe orders by subscription
	 *
	 * @param int $subscription_id Subscription ID
	 * @return array
	 */
	public function get_resubscribe_orders_by_subscription( $subscription_id ) {
		$orders = get_posts( array(
			'post_type'      => 'shop_order',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_subscription_resubscribe',
					'value' => $subscription_id,
				),
			),
		) );

		$order_ids = array();
		foreach ( $orders as $order_post ) {
			$order_ids[] = $order_post->ID;
		}

		return $order_ids;
	}

	/**
	 * Get subscription from order
	 *
	 * @param int $order_id Order ID
	 * @return int|false
	 */
	public function get_subscription_from_order( $order_id ) {
		// Check for renewal order
		$renewal_subscription = $this->get_meta( $order_id, '_subscription_renewal', true );
		if ( $renewal_subscription ) {
			return $renewal_subscription;
		}

		// Check for switch order
		$switch_subscription = $this->get_meta( $order_id, '_subscription_switch', true );
		if ( $switch_subscription ) {
			return $switch_subscription;
		}

		// Check for resubscribe order
		$resubscribe_subscription = $this->get_meta( $order_id, '_subscription_resubscribe', true );
		if ( $resubscribe_subscription ) {
			return $resubscribe_subscription;
		}

		// Check for parent order
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

		return ! empty( $subscriptions ) ? $subscriptions[0]->ID : false;
	}

	/**
	 * Check if order is renewal order
	 *
	 * @param int $order_id Order ID
	 * @return bool
	 */
	public function is_renewal_order( $order_id ) {
		return ! empty( $this->get_meta( $order_id, '_subscription_renewal', true ) );
	}

	/**
	 * Check if order is switch order
	 *
	 * @param int $order_id Order ID
	 * @return bool
	 */
	public function is_switch_order( $order_id ) {
		return ! empty( $this->get_meta( $order_id, '_subscription_switch', true ) );
	}

	/**
	 * Check if order is resubscribe order
	 *
	 * @param int $order_id Order ID
	 * @return bool
	 */
	public function is_resubscribe_order( $order_id ) {
		return ! empty( $this->get_meta( $order_id, '_subscription_resubscribe', true ) );
	}

	/**
	 * Check if order contains subscription
	 *
	 * @param int $order_id Order ID
	 * @return bool
	 */
	public function order_contains_subscription( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		foreach ( $order->get_items() as $item ) {
			if ( wcs_is_subscription_product( $item->get_product() ) ) {
				return true;
			}
		}

		return false;
	}
}
