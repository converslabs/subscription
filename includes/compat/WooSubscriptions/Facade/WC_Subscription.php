<?php
/**
 * Plugin Name - WooCommerce Subscription Facade
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

use SpringDevs\Subscription\Compat\WooSubscriptions\Services\SubscriptionLocator;

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
	 * Stored meta data.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected $meta = array();

	/**
	 * Related WP_Post instance.
	 *
	 * @since 1.0.0
	 *
	 * @var \WP_Post|null
	 */
	protected $post = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Optional initial data.
	 */
	public function __construct( $data = array() ) {
		$this->data = $data;
		$this->meta = isset( $data['meta'] ) && is_array( $data['meta'] ) ? $data['meta'] : array();
		$this->post = $data['post'] ?? null;
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
		$locator = new SubscriptionLocator();

		return $locator->get_subscriptions_by_user( $user_id );
	}

	/**
	 * Provide access to underlying data/meta.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Data key.
	 *
	 * @return mixed|null
	 */
	public function get( $key ) {
		if ( array_key_exists( $key, $this->data ) ) {
			return $this->data[ $key ];
		}

		if ( array_key_exists( $key, $this->meta ) ) {
			return $this->meta[ $key ];
		}

		return null;
	}

	/**
	 * Retrieve the subscription identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_id() {
		if ( isset( $this->data['id'] ) ) {
			return (int) $this->data['id'];
		}

		return $this->post ? (int) $this->post->ID : 0;
	}

	/**
	 * Retrieve the subscription status.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_status() {
		if ( isset( $this->data['status'] ) ) {
			return $this->data['status'];
		}

		return $this->post ? $this->post->post_status : '';
	}

	/**
	 * Retrieve the subscription customer identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_customer_id() {
		if ( isset( $this->data['customer_id'] ) ) {
			return (int) $this->data['customer_id'];
		}

		return $this->post ? (int) $this->post->post_author : 0;
	}

	/**
	 * Retrieve subscription meta value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key    Meta key.
	 * @param bool   $single Whether to return a single value.
	 *
	 * @return mixed
	 */
	public function get_meta( $key, $single = true ) {
		if ( ! array_key_exists( $key, $this->meta ) ) {
			return $single ? null : array();
		}

		$value = $this->meta[ $key ];

		if ( $single ) {
			return $value;
		}

		return is_array( $value ) ? $value : array( $value );
	}
}
