<?php
/**
 * Plugin Name - WooCommerce Subscriptions Compat Subscription Locator
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compat\WooSubscriptions\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Locate subscriptions for compatibility helpers.
 *
 * @since 1.0.0
 */
class Subscription_Locator {

	/**
	 * Retrieve subscriptions for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id User identifier.
	 * @param array $args    Lookup modifiers.
	 *
	 * @return array
	 */
	public function get_subscriptions_by_user( $user_id = 0, $args = array() ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return array();
		}

		/**
		 * Filter the subscriptions list retrieved for a user.
		 *
		 * @since 1.0.0
		 *
		 * @param array $subscriptions Subscription collection.
		 * @param int   $user_id       User identifier.
		 * @param array $args          Lookup modifiers.
		 */
		return apply_filters( 'wps_wcs_get_users_subscriptions', array(), $user_id, $args );
	}
}
