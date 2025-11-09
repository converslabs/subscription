<?php
/**
 * Plugin Name - WooCommerce Subscriptions Compat Functions
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

use SpringDevs\Subscription\Compat\WooSubscriptions\Services\Subscription_Locator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
	/**
	 * Retrieve subscriptions for a user in a WooCommerce Subscriptions compatible format.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id User identifier.
	 * @param array $args    Optional lookup arguments.
	 *
	 * @return array
	 */
	function wcs_get_users_subscriptions( $user_id = 0, $args = array() ) {
		$locator = new Subscription_Locator();

		return $locator->get_subscriptions_by_user( $user_id, $args );
	}
}
