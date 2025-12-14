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
		$is_pro = subscrpt_pro_activated();

		switch ( strtolower( $view ) ) {
			case 'view_subs':
				$default_endpoint = 'view-subscription';
				$endpoint         = $is_pro ? get_option( 'wpsubs_custom_view_subscription_endpoint', $default_endpoint ) : $default_endpoint;
				break;

			case 'subs_list':
			default:
				$default_endpoint = 'subscriptions';
				$endpoint         = $is_pro ? get_option( 'wpsubs_custom_subscriptions_endpoint', $default_endpoint ) : $default_endpoint;
				break;
		}

		return untrailingslashit( $endpoint );
	}
}
