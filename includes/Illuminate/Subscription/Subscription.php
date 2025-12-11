<?php
/**
 * Main subscription class.
 *
 * TODO: Refactor and move all subscription related logic from Helper class into this class.
 *
 * @package Subscription
 */

namespace SpringDevs\Subscription\Illuminate\Subscription;

/**
 * Class Subscription
 *
 * @package Illuminate\Subscription
 */
class Subscription {
	/**
	 * Constructor.
	 */
	public function __construct() {
	}

	/**
	 * Get subscription endpoint.
	 *
	 * @param string $view View type.
	 * @return string
	 */
	public static function get_user_endpoint( string $view = 'subs_list' ) {
		switch ( strtolower( $view ) ) {
			case 'view_subs':
				$endpoint = get_option( 'wpsubs_custom_view_subscription_endpoint', 'view-subscription' );
				break;

			case 'subs_list':
			default:
				$endpoint = get_option( 'wpsubs_custom_subscriptions_endpoint', 'subscriptions' );
				break;
		}

		return untrailingslashit( $endpoint );
	}
}
