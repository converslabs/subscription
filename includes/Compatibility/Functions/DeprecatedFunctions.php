<?php
/**
 * WooCommerce Subscriptions Deprecated Functions Compatibility
 *
 * This class provides compatibility with WooCommerce Subscriptions deprecated functions
 * by mapping them to current functionality.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Compatibility\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deprecated functions compatibility class
 */
class DeprecatedFunctions {

	/**
	 * Register all deprecated functions
	 *
	 * @return void
	 */
	public static function register() {
		// Check if WooCommerce Subscriptions is not already active
		if ( class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		// Register deprecated functions
		self::register_deprecated_subscription_functions();
		self::register_deprecated_order_functions();
		self::register_deprecated_product_functions();
		self::register_deprecated_user_functions();
	}

	/**
	 * Register deprecated subscription functions
	 *
	 * @return void
	 */
	private static function register_deprecated_subscription_functions() {
		if ( ! function_exists( 'WC_Subscriptions_Manager' ) ) {
	/**
	 * Get WC_Subscriptions_Manager instance
	 *
	 * @deprecated Use WC_Subscriptions_Manager::get_instance() instead
	 * @return \SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Manager
	 */
	function WC_Subscriptions_Manager() {
		wc_deprecated_function( __FUNCTION__, '2.0', 'WC_Subscriptions_Manager::get_instance()' );
		return \SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Manager::get_instance();
	}
		}

		if ( ! function_exists( 'WC_Subscriptions_Product' ) ) {
	/**
	 * Get WC_Subscriptions_Product instance
	 *
	 * @deprecated Use WC_Subscriptions_Product::get_instance() instead
	 * @return \SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Product
	 */
	function WC_Subscriptions_Product() {
		wc_deprecated_function( __FUNCTION__, '2.0', 'WC_Subscriptions_Product::get_instance()' );
		return \SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Product::get_instance();
	}
		}

		if ( ! function_exists( 'WC_Subscriptions_Order' ) ) {
	/**
	 * Get WC_Subscriptions_Order instance
	 *
	 * @deprecated Use WC_Subscriptions_Order::get_instance() instead
	 * @return \SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Order
	 */
	function WC_Subscriptions_Order() {
		wc_deprecated_function( __FUNCTION__, '2.0', 'WC_Subscriptions_Order::get_instance()' );
		return \SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Order::get_instance();
	}
		}
	}

	/**
	 * Register deprecated order functions
	 *
	 * @return void
	 */
	private static function register_deprecated_order_functions() {
		if ( ! function_exists( 'wcs_get_subscription_from_order' ) ) {
	/**
	 * Get subscription from order
	 *
	 * @deprecated Use wcs_get_subscription_by_order() instead
	 * @param int $order_id Order ID
	 * @return \WC_Subscription|false
	 */
	function wcs_get_subscription_from_order( $order_id ) {
		wc_deprecated_function( __FUNCTION__, '2.0', 'wcs_get_subscription_by_order()' );
		if ( function_exists( 'wcs_get_subscription_by_order' ) ) {
			return wcs_get_subscription_by_order( $order_id );
		}
		return false;
	}
		}

		if ( ! function_exists( 'wcs_get_subscription_id_from_order' ) ) {
	/**
	 * Get subscription ID from order
	 *
	 * @deprecated Use wcs_get_subscription_by_order() instead
	 * @param int $order_id Order ID
	 * @return int|false
	 */
	function wcs_get_subscription_id_from_order( $order_id ) {
		wc_deprecated_function( __FUNCTION__, '2.0', 'wcs_get_subscription_by_order()' );
		if ( function_exists( 'wcs_get_subscription_by_order' ) ) {
			$subscription = wcs_get_subscription_by_order( $order_id );
			return $subscription ? $subscription->get_id() : false;
		}
		return false;
	}
		}
	}

	/**
	 * Register deprecated product functions
	 *
	 * @return void
	 */
	private static function register_deprecated_product_functions() {
		if ( ! function_exists( 'wcs_get_subscription_product' ) ) {
			/**
			 * Get subscription product
			 *
			 * @deprecated Use wc_get_product() instead
			 * @param int $product_id Product ID
			 * @return WC_Product|false
			 */
			function wcs_get_subscription_product( $product_id ) {
				wc_deprecated_function( __FUNCTION__, '2.0', 'wc_get_product()' );
				return wc_get_product( $product_id );
			}
		}

		if ( ! function_exists( 'wcs_get_subscription_products' ) ) {
			/**
			 * Get subscription products
			 *
			 * @deprecated Use wcs_get_subscription_products() instead
			 * @return array
			 */
			function wcs_get_subscription_products() {
				wc_deprecated_function( __FUNCTION__, '2.0', 'wcs_get_subscription_products()' );
				return wcs_get_subscription_products();
			}
		}
	}

	/**
	 * Register deprecated user functions
	 *
	 * @return void
	 */
	private static function register_deprecated_user_functions() {
		if ( ! function_exists( 'wcs_get_user_subscriptions' ) ) {
			/**
			 * Get user subscriptions
			 *
			 * @deprecated Use wcs_get_users_subscriptions() instead
			 * @param int $user_id User ID
			 * @return array
			 */
			function wcs_get_user_subscriptions( $user_id ) {
				wc_deprecated_function( __FUNCTION__, '2.0', 'wcs_get_users_subscriptions()' );
				return wcs_get_users_subscriptions( $user_id );
			}
		}

		if ( ! function_exists( 'wcs_user_has_subscription' ) ) {
			/**
			 * Check if user has subscription
			 *
			 * @deprecated Use wcs_user_has_subscription() instead
			 * @param int $user_id User ID
			 * @param int $product_id Product ID
			 * @param string $status Subscription status
			 * @return bool
			 */
			function wcs_user_has_subscription( $user_id, $product_id = '', $status = 'any' ) {
				wc_deprecated_function( __FUNCTION__, '2.0', 'wcs_user_has_subscription()' );
				return wcs_user_has_subscription( $user_id, $product_id, $status );
			}
		}
	}
}
