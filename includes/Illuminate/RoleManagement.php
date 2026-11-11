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

		// A failed renewal suspends the subscription rather than ending it, so the
		// role has to come down here too. Nothing listened to this before, which
		// left a customer who stopped paying holding their subscriber role for the
		// whole retry ladder. Reversible: a recovered payment fires
		// `subscrpt_subscription_activated`, which puts the role back.
		add_action( 'subscrpt_subscription_on_hold', [ $this, 'maybe_change_user_role_on_subscription_deactivation' ] );

		// A publishing-capable active role waits for a confirmed payment (see
		// is_active_role_grant_allowed()), which can arrive after activation.
		add_action( 'woocommerce_payment_complete', [ $this, 'maybe_grant_deferred_active_role' ] );
		add_action( 'woocommerce_order_status_completed', [ $this, 'maybe_grant_deferred_active_role' ] );
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
	 * Whether subscription status changes may set this user's role.
	 *
	 * Only users whose every role is one this feature hands out (or a plain
	 * customer/subscriber) are touched, so a shop manager, editor or any other
	 * staff account is never promoted or demoted by a subscription.
	 *
	 * @param \WP_User $user User.
	 */
	public static function is_role_managed_user( \WP_User $user ): bool {
		$managed_roles = apply_filters(
			'subscrpt_role_management_managed_roles',
			array_unique( [ 'customer', 'subscriber', self::get_default_active_role(), self::get_default_inactive_role() ] ),
			$user
		);

		return empty( array_diff( (array) $user->roles, (array) $managed_roles ) );
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

		if ( ! self::is_role_managed_user( $user ) ) {
			return;
		}

		$default_active_role = self::get_default_active_role();

		if ( ! self::is_active_role_grant_allowed( (int) $subscription_id, $default_active_role ) ) {
			return;
		}

		$user->set_role( $default_active_role );
	}

	/**
	 * Whether the active role may be granted for this subscription yet.
	 *
	 * A role that can publish (`edit_posts`) is only granted once a non-zero
	 * order of the subscription has a confirmed payment: paid through a gateway,
	 * or completed by an admin. A free trial, a zero-total order or an offline
	 * payment (COD, bank transfer, cheque) still in processing activates the
	 * subscription but not that role.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $role            Role about to be granted.
	 */
	public static function is_active_role_grant_allowed( int $subscription_id, string $role ): bool {
		$role_object = get_role( $role );
		$allowed     = ! $role_object || ! $role_object->has_cap( 'edit_posts' );

		if ( ! $allowed ) {
			$offline_methods = [ 'cod', 'bacs', 'cheque' ];

			foreach ( Helper::get_related_orders( $subscription_id ) as $history ) {
				$order = wc_get_order( $history->order_id );

				if ( ! $order || (float) $order->get_total() <= 0 ) {
					continue;
				}

				$gateway_paid = $order->is_paid() && $order->get_date_paid() && ! in_array( $order->get_payment_method(), $offline_methods, true );

				if ( $gateway_paid || $order->has_status( 'completed' ) ) {
					$allowed = true;
					break;
				}
			}
		}

		return (bool) apply_filters( 'subscrpt_active_role_grant_allowed', $allowed, $subscription_id, $role );
	}

	/**
	 * Grant an active role held back until payment, once an order is paid or completed.
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_grant_deferred_active_role( $order_id ) {
		foreach ( Helper::get_subscriptions_from_order( (int) $order_id ) as $history ) {
			if ( 'active' === get_post_status( (int) $history->subscription_id ) ) {
				$this->maybe_change_user_role_on_subscription_activation( (int) $history->subscription_id );
			}
		}
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

		if ( ! self::is_role_managed_user( $user ) ) {
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
