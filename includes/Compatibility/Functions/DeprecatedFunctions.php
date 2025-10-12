<?php
/**
 * Deprecated WooCommerce Subscriptions Functions
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

// Backward compatibility for deprecated WooCommerce Subscriptions functions.

if ( ! function_exists( 'wcs_get_subscription_from_order' ) ) {
	/**
	 * Get subscription from order (deprecated).
	 *
	 * @deprecated Use wcs_get_subscriptions_for_order() instead.
	 * @since      1.0.0
	 * @param      mixed $order Order ID or object.
	 * @return     SpringDevs\Subscription\Compatibility\Classes\WC_Subscription|null
	 */
	function wcs_get_subscription_from_order( $order ) {
		$subscriptions = wcs_get_subscriptions_for_order( $order );
		return ! empty( $subscriptions ) ? reset( $subscriptions ) : null;
	}
	wpsubscription_compat_register_function( 'wcs_get_subscription_from_order' );
}

if ( ! function_exists( 'wcs_get_subscription_id_from_order' ) ) {
	/**
	 * Get subscription ID from order (deprecated).
	 *
	 * @deprecated Use wcs_get_subscriptions_for_order() instead.
	 * @since      1.0.0
	 * @param      mixed $order Order ID or object.
	 * @return     int|null
	 */
	function wcs_get_subscription_id_from_order( $order ) {
		$subscription_ids = SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Order::get_subscription_ids_for_order( $order );
		return ! empty( $subscription_ids ) ? reset( $subscription_ids ) : null;
	}
	wpsubscription_compat_register_function( 'wcs_get_subscription_id_from_order' );
}

if ( ! function_exists( 'wcs_get_subscription_products' ) ) {
	/**
	 * Get all subscription products.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	function wcs_get_subscription_products() {
		$args = array(
			'type'       => array( 'simple', 'variable' ),
			'status'     => 'publish',
			'limit'      => -1,
			'meta_query' => array(
				array(
					'key'     => '_subscrpt_enabled',
					'value'   => '1',
					'compare' => '=',
				),
			),
		);

		return wc_get_products( $args );
	}
	wpsubscription_compat_register_function( 'wcs_get_subscription_products' );
}
