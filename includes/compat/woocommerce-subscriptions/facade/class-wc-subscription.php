<?php
/**
 * Plugin Name - WooCommerce Subscription Facade
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

use SpringDevs\Subscription\Compat\WooSubscriptions\Services\Subscription_Locator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Subscription', false ) ) {
	return;
}

/**
 * Minimal WooCommerce Subscriptions facade for compatibility tests.
 *
 * @since 1.0.0
 */
class WC_Subscription {

	/**
	 * Stored meta data.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $data = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Optional initial data.
	 */
	public function __construct( $data = array() ) {
		$this->data = $data;
	}

	/**
	 * Retrieve subscriptions for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User identifier.
	 *
	 * @return array
	 */
	public static function get_users_subscriptions( $user_id = 0 ) {
		$locator = new Subscription_Locator();

		return $locator->get_subscriptions_by_user( $user_id );
	}

	/**
	 * Provide access to underlying data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Data key.
	 *
	 * @return mixed|null
	 */
	public function get( $key ) {
		return $this->data[ $key ] ?? null;
	}
}
