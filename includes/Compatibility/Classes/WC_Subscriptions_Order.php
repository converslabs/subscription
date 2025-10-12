<?php
/**
 * WC_Subscriptions_Order Compatibility Class
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Classes;

/**
 * WC_Subscriptions_Order class.
 *
 * Order-related subscription helper methods.
 *
 * @package SpringDevs\Subscription\Compatibility\Classes
 * @since   1.0.0
 */
class WC_Subscriptions_Order {

	/**
	 * Check if order contains subscription.
	 *
	 * @since  1.0.0
	 * @param  mixed $order Order ID or object.
	 * @return bool
	 */
	public static function order_contains_subscription( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		// Check if any order items are subscription products.
		foreach ( $order->get_items() as $item ) {
			if ( method_exists( $item, 'get_product_id' ) ) {
				$product_id = $item->get_product_id();
				if ( $product_id && WC_Subscriptions_Product::is_subscription( $product_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get subscription IDs for an order.
	 *
	 * @since  1.0.0
	 * @param  mixed $order Order ID or object.
	 * @return array
	 */
	public static function get_subscription_ids_for_order( $order ) {
		if ( is_numeric( $order ) ) {
			$order_id = $order;
		} elseif ( is_object( $order ) ) {
			$order_id = $order->get_id();
		} else {
			return array();
		}

		// Query subscriptions by parent order ID.
		$args = array(
			'post_type'   => 'subscrpt_order',
			'post_status' => 'any',
			'numberposts' => -1,
			'meta_query'  => array(
				array(
					'key'     => '_subscrpt_order_id',
					'value'   => $order_id,
					'compare' => '=',
				),
			),
		);

		$subscriptions    = get_posts( $args );
		$subscription_ids = array();

		foreach ( $subscriptions as $subscription ) {
			$subscription_ids[] = $subscription->ID;
		}

		return $subscription_ids;
	}

	/**
	 * Self-test method.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function test() {
		return array(
			'class_exists'                   => true,
			'order_contains_subscription'    => method_exists( __CLASS__, 'order_contains_subscription' ),
			'get_subscription_ids_for_order' => method_exists( __CLASS__, 'get_subscription_ids_for_order' ),
		);
	}
}
