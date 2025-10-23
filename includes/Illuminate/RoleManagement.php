<?php
/**
 * Role Management File
 *
 * @package SpringDevs\Subscription\Illuminate
 */

namespace SpringDevs\Subscription\Illuminate;

/**
 * RoleManagement [ helper class ]
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class RoleManagement {
	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'subscrpt_subscription_activated', [ $this, 'maybe_change_user_role_on_subscription_activation' ] );

		add_action( 'subscrpt_subscription_expired', [ $this, 'maybe_change_user_role_on_subscription_deactivation' ] );
		add_action( 'subscrpt_subscription_cancelled', [ $this, 'maybe_change_user_role_on_subscription_deactivation' ] );
	}

	/**
	 * Get default active role
	 */
	public static function get_default_active_role(): string {
		$default_active_role = get_option( 'wp_subscription_active_role' );

		// Migrate legacy option if needed.
		if ( empty( $default_active_role ) ) {
			$default_active_role = get_option( 'subscrpt_active_role', 'subscriber' );
			update_option( 'wp_subscription_active_role', $default_active_role );
		}

		return $default_active_role;
	}

	/**
	 * Get default inactive role
	 */
	public static function get_default_inactive_role(): string {
		$default_inactive_role = get_option( 'wp_subscription_unactive_role' ); // ? Yeap! it is a typo from the ancient times. Too lazy to change!

		// Migrate legacy option if needed.
		if ( empty( $default_inactive_role ) ) {
			$default_inactive_role = get_option( 'subscrpt_unactive_role', 'customer' );
			update_option( 'wp_subscription_unactive_role', $default_inactive_role );
		}

		return $default_inactive_role;
	}

	/**
	 * Maybe change user role on subscription activation
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function maybe_change_user_role_on_subscription_activation( $subscription_id ) {
		// Get the subscription owner's user ID
		$user_id = get_post_field( 'post_author', (int) $subscription_id );

		if ( ! $user_id ) {
			return;
		}

		$user = new \WP_User( $user_id );

		// Don't change roles for administrators
		if ( in_array( 'administrator', $user->roles ?? [], true ) ) {
			return;
		}

		$default_active_role = self::get_default_active_role();

		$user->set_role( $default_active_role );
	}

	/**
	 * Maybe change user role on subscription deactivation
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function maybe_change_user_role_on_subscription_deactivation( $subscription_id ) {
		// Get the subscription owner's user ID
		$user_id = get_post_field( 'post_author', (int) $subscription_id );

		if ( ! $user_id ) {
			return;
		}

		$user = new \WP_User( $user_id );

		// Don't change roles for administrators
		if ( in_array( 'administrator', $user->roles ?? [], true ) ) {
			return;
		}

		$args              = [
			'author' => $user_id,
			'status' => 'active',
		];
		$all_subscriptions = Helper::get_subscriptions( $args );

		// Don't change role if user has other active subscriptions
		if ( count( $all_subscriptions ) > 0 ) {
			return;
		}

		$default_inactive_role = self::get_default_inactive_role();

		$user->set_role( $default_inactive_role );
	}
}
