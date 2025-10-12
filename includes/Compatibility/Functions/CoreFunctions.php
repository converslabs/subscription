<?php
/**
 * Core WooCommerce Subscriptions Functions
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

// Global function registry for verification.
global $wpsubscription_compat_functions;
$wpsubscription_compat_functions = array();

/**
 * Register a compatibility function.
 *
 * @since 1.0.0
 * @param string $function_name Function name.
 */
function wpsubscription_compat_register_function( $function_name ) {
	global $wpsubscription_compat_functions;
	if ( ! in_array( $function_name, $wpsubscription_compat_functions, true ) ) {
		$wpsubscription_compat_functions[] = $function_name;
	}
}

/**
 * Get registered compatibility functions.
 *
 * @since  1.0.0
 * @return array
 */
function wpsubscription_compat_get_functions() {
	global $wpsubscription_compat_functions;
	return $wpsubscription_compat_functions ?? array();
}

// 1. wcs_is_subscription().
if ( ! function_exists( 'wcs_is_subscription' ) ) {
	/**
	 * Check if an order is a subscription.
	 *
	 * @since  1.0.0
	 * @param  mixed $order Order ID, object, or WC_Subscription.
	 * @return bool
	 */
	function wcs_is_subscription( $order ) {
		if ( is_a( $order, 'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscription' ) ) {
			return true;
		}

		if ( is_a( $order, 'WC_Subscription' ) ) {
			return true;
		}

		if ( is_numeric( $order ) ) {
			$post_type = get_post_type( $order );
			return 'subscrpt_order' === $post_type;
		}

		return false;
	}
	wpsubscription_compat_register_function( 'wcs_is_subscription' );
}

// 2. wcs_order_contains_subscription().
if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
	/**
	 * Check if an order contains subscription products.
	 *
	 * @since  1.0.0
	 * @param  mixed $order Order ID or object.
	 * @return bool
	 */
	function wcs_order_contains_subscription( $order ) {
		return SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Order::order_contains_subscription( $order );
	}
	wpsubscription_compat_register_function( 'wcs_order_contains_subscription' );
}

// 3. wcs_order_contains_renewal().
if ( ! function_exists( 'wcs_order_contains_renewal' ) ) {
	/**
	 * Check if an order is a renewal order.
	 *
	 * @since  1.0.0
	 * @param  mixed $order Order ID or object.
	 * @return bool
	 */
	function wcs_order_contains_renewal( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return false;
		}

		// Check if order has renewal meta.
		$subscription_renewal = $order->get_meta( '_subscription_renewal' );
		return ! empty( $subscription_renewal );
	}
	wpsubscription_compat_register_function( 'wcs_order_contains_renewal' );
}

// 4. wcs_get_subscription().
if ( ! function_exists( 'wcs_get_subscription' ) ) {
	/**
	 * Get a subscription object.
	 *
	 * @since  1.0.0
	 * @param  int $subscription_id Subscription ID.
	 * @return SpringDevs\Subscription\Compatibility\Classes\WC_Subscription|null
	 */
	function wcs_get_subscription( $subscription_id ) {
		if ( get_post_type( $subscription_id ) !== 'subscrpt_order' ) {
			return null;
		}

		return new SpringDevs\Subscription\Compatibility\Classes\WC_Subscription( $subscription_id );
	}
	wpsubscription_compat_register_function( 'wcs_get_subscription' );
}

// 5. wcs_get_subscriptions_for_order().
if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
	/**
	 * Get subscriptions for an order.
	 *
	 * @since  1.0.0
	 * @param  mixed $order Order ID or object.
	 * @return array
	 */
	function wcs_get_subscriptions_for_order( $order ) {
		$subscription_ids = SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Order::get_subscription_ids_for_order( $order );
		$subscriptions    = array();

		foreach ( $subscription_ids as $subscription_id ) {
			$subscriptions[ $subscription_id ] = wcs_get_subscription( $subscription_id );
		}

		return $subscriptions;
	}
	wpsubscription_compat_register_function( 'wcs_get_subscriptions_for_order' );
}

// 6. wcs_get_users_subscriptions().
if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
	/**
	 * Get user's subscriptions.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id User ID (0 for current user).
	 * @param  string $status Subscription status.
	 * @return array
	 */
	function wcs_get_users_subscriptions( $user_id = 0, $status = 'any' ) {
		return SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Manager::get_users_subscriptions( $user_id, $status );
	}
	wpsubscription_compat_register_function( 'wcs_get_users_subscriptions' );
}

// 7. wcs_is_subscription_product().
if ( ! function_exists( 'wcs_is_subscription_product' ) ) {
	/**
	 * Check if a product is a subscription product.
	 *
	 * @since  1.0.0
	 * @param  mixed $product Product ID or object.
	 * @return bool
	 */
	function wcs_is_subscription_product( $product ) {
		return SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Product::is_subscription( $product );
	}
	wpsubscription_compat_register_function( 'wcs_is_subscription_product' );
}

// 8. wcs_get_subscription_period_strings().
if ( ! function_exists( 'wcs_get_subscription_period_strings' ) ) {
	/**
	 * Get subscription period strings.
	 *
	 * @since  1.0.0
	 * @param  int    $number Number of periods.
	 * @param  string $period Period type.
	 * @return string
	 */
	function wcs_get_subscription_period_strings( $number = 1, $period = '' ) {
		$period_strings = array(
			'day'   => _n( 'day', 'days', $number, 'wp_subscription' ),
			'week'  => _n( 'week', 'weeks', $number, 'wp_subscription' ),
			'month' => _n( 'month', 'months', $number, 'wp_subscription' ),
			'year'  => _n( 'year', 'years', $number, 'wp_subscription' ),
		);

		if ( empty( $period ) ) {
			return $period_strings;
		}

		return isset( $period_strings[ $period ] ) ? $period_strings[ $period ] : $period;
	}
	wpsubscription_compat_register_function( 'wcs_get_subscription_period_strings' );
}

// 9. wcs_is_manual_renewal_enabled().
if ( ! function_exists( 'wcs_is_manual_renewal_enabled' ) ) {
	/**
	 * Check if manual renewal is enabled.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	function wcs_is_manual_renewal_enabled() {
		// Check WPSubscription settings.
		$auto_renew_enabled = get_option( 'subscrpt_auto_renew_enable', 'no' );
		return 'no' === $auto_renew_enabled;
	}
	wpsubscription_compat_register_function( 'wcs_is_manual_renewal_enabled' );
}

// 10. wcs_cart_contains_renewal().
if ( ! function_exists( 'wcs_cart_contains_renewal' ) ) {
	/**
	 * Check if cart contains renewal.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	function wcs_cart_contains_renewal() {
		return SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Cart::cart_contains_renewal();
	}
	wpsubscription_compat_register_function( 'wcs_cart_contains_renewal' );
}

// 11. wcs_user_has_subscription().
if ( ! function_exists( 'wcs_user_has_subscription' ) ) {
	/**
	 * Check if user has any subscription.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id User ID (0 for current user).
	 * @param  string $product_id Product ID to check.
	 * @param  string $status Subscription status.
	 * @return bool
	 */
	function wcs_user_has_subscription( $user_id = 0, $product_id = '', $status = 'any' ) {
		$subscriptions = wcs_get_users_subscriptions( $user_id, $status );

		if ( empty( $subscriptions ) ) {
			return false;
		}

		if ( empty( $product_id ) ) {
			return true;
		}

		// Check if user has subscription for specific product.
		foreach ( $subscriptions as $subscription ) {
			$subscription_product_id = get_post_meta( $subscription->get_id(), '_subscrpt_product_id', true );
			if ( absint( $subscription_product_id ) === absint( $product_id ) ) {
				return true;
			}
		}

		return false;
	}
	wpsubscription_compat_register_function( 'wcs_user_has_subscription' );
}

// 12. wcs_get_subscription_statuses().
if ( ! function_exists( 'wcs_get_subscription_statuses' ) ) {
	/**
	 * Get all subscription statuses.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	function wcs_get_subscription_statuses() {
		return array(
			'wc-active'         => _x( 'Active', 'Subscription status', 'wp_subscription' ),
			'wc-on-hold'        => _x( 'On hold', 'Subscription status', 'wp_subscription' ),
			'wc-cancelled'      => _x( 'Cancelled', 'Subscription status', 'wp_subscription' ),
			'wc-pending-cancel' => _x( 'Pending Cancellation', 'Subscription status', 'wp_subscription' ),
			'wc-expired'        => _x( 'Expired', 'Subscription status', 'wp_subscription' ),
			'wc-pending'        => _x( 'Pending', 'Subscription status', 'wp_subscription' ),
		);
	}
	wpsubscription_compat_register_function( 'wcs_get_subscription_statuses' );
}

// 13. wcs_get_subscription_status_name().
if ( ! function_exists( 'wcs_get_subscription_status_name' ) ) {
	/**
	 * Get subscription status name.
	 *
	 * @since  1.0.0
	 * @param  string $status Status slug.
	 * @return string
	 */
	function wcs_get_subscription_status_name( $status ) {
		$statuses = wcs_get_subscription_statuses();
		$status   = 'wc-' . str_replace( 'wc-', '', $status );
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
	}
	wpsubscription_compat_register_function( 'wcs_get_subscription_status_name' );
}

// 14. wcs_cart_contains_resubscribe().
if ( ! function_exists( 'wcs_cart_contains_resubscribe' ) ) {
	/**
	 * Check if cart contains resubscribe.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	function wcs_cart_contains_resubscribe() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['subscription_resubscribe'] ) ) {
				return true;
			}
		}

		return false;
	}
	wpsubscription_compat_register_function( 'wcs_cart_contains_resubscribe' );
}

// 15. wcs_can_user_resubscribe_to().
if ( ! function_exists( 'wcs_can_user_resubscribe_to' ) ) {
	/**
	 * Check if user can resubscribe to a subscription.
	 *
	 * @since  1.0.0
	 * @param  int $subscription_id Subscription ID.
	 * @param  int $user_id User ID.
	 * @return bool
	 */
	function wcs_can_user_resubscribe_to( $subscription_id, $user_id = 0 ) {
		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return false;
		}

		$status = $subscription->get_status();
		return in_array( $status, array( 'cancelled', 'expired' ), true );
	}
	wpsubscription_compat_register_function( 'wcs_can_user_resubscribe_to' );
}
