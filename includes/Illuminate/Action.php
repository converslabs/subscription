<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Action [ helper class ]
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Action {

	/**
	 * Did when status changes.
	 *
	 * @param string $status Status.
	 * @param int    $subscription_id Subscription ID.
	 * @param bool   $write_comment Write comment?.
	 */
	public static function status( string $status, int $subscription_id, bool $write_comment = true ) {
		$old_status = get_post_status( $subscription_id );

		wp_update_post(
			array(
				'ID'          => $subscription_id,
				'post_status' => $status,
			)
		);

		if ( $write_comment ) {
			self::write_comment( $status, $subscription_id );
		}

		self::user( $subscription_id );

		// Trigger status change action
		do_action( 'subscrpt_subscription_status_changed', $subscription_id, $old_status, $status );

		// Trigger resumption event if subscription is being activated from cancelled or pending cancellation
		if ( $status === 'active' && in_array( $old_status, array( 'cancelled', 'pe_cancelled' ) ) ) {
			do_action( 'subscrpt_subscription_resumed', $subscription_id, $old_status );
		}
	}

	/**
	 * Write Comment based on status.
	 *
	 * @param string $status Status.
	 * @param Int    $subscription_id Subscription ID.
	 */
	public static function write_comment( string $status, int $subscription_id ) {
		switch ( $status ) {
			case 'expired':
				self::expired( $subscription_id );
				break;
			case 'active':
				self::active( $subscription_id );
				break;
			case 'pending':
				self::pending( $subscription_id );
				break;
			case 'cancelled':
				self::cancelled( $subscription_id );
				break;
			case 'pe_cancelled':
				self::pe_cancelled( $subscription_id );
				break;
		}
	}

	/**
	 * Write Comment About expired Subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function expired( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription is Expired',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Expired' );

		do_action( 'subscrpt_subscription_expired', $subscription_id );
	}

	/**
	 * Write Comment About Active Subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function active( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription activated.Next payment due date set.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Activated' );
		do_action( 'subscrpt_subscription_activated', $subscription_id );
	}

	/**
	 * Write Comment About Subscription Pending.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function pending( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription is pending.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Pending' );
		do_action( 'subscrpt_subscription_pending', $subscription_id );
	}

	/**
	 * Write Comment About cancelled Subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function cancelled( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription is Cancelled.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Cancelled' );

		WC()->mailer();
		do_action( 'subscrpt_subscription_cancelled_email_notification', $subscription_id );
		do_action( 'subscrpt_subscription_cancelled', $subscription_id );

		// Fire split payment cancelled action
		do_action( 'subscrpt_split_payment_cancelled', $subscription_id );
	}

	/**
	 * Write Comment About Pending Cancellation.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	private static function pe_cancelled( int $subscription_id ) {
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => 'Subscription is Pending Cancellation.',
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Subscription Pending Cancellation' );
		do_action( 'subscrpt_subscription_pending_cancellation', $subscription_id );
	}

	/**
	 * Update user role.
	 *
	 * @param Int $subscription_id Subscription ID.
	 */
	private static function user( $subscription_id ) {
		// Get the subscription owner's user ID
		$user_id = get_post_field( 'post_author', (int) $subscription_id );

		if ( ! $user_id ) {
			return;
		}

		$user = new \WP_User( $user_id );

		// Don't change roles for administrators
		if ( ! empty( $user->roles ) && is_array( $user->roles ) && in_array( 'administrator', $user->roles, true ) ) {
			return;
		}

		$current_status = get_post_status( (int) $subscription_id );

		// Get role settings from options with legacy support
		$active_role = get_option( 'wp_subscription_active_role' );
		if ( false === $active_role ) {
			// Legacy fallback
			$active_role = get_option( 'subscrpt_active_role', 'subscriber' );
			if ( false !== $active_role ) {
				_doing_it_wrong(
					'Action::user()',
					'The option "subscrpt_active_role" is deprecated. Use "wp_subscription_active_role" instead.',
					'1.5.3'
				);
			} else {
				$active_role = 'subscriber';
			}
		}

		$inactive_role = get_option( 'wp_subscription_unactive_role' );
		if ( false === $inactive_role ) {
			// Legacy fallback
			$inactive_role = get_option( 'subscrpt_unactive_role', 'customer' );
			if ( false !== $inactive_role ) {
				_doing_it_wrong(
					'Action::user()',
					'The option "subscrpt_unactive_role" is deprecated. Use "wp_subscription_unactive_role" instead.',
					'1.5.3'
				);
			} else {
				$inactive_role = 'customer';
			}
		}

		// Assign role based on subscription status
		if ( 'active' === $current_status ) {
			$user->set_role( $active_role );
		} elseif ( in_array( $current_status, array( 'cancelled', 'expired', 'pe_cancelled' ), true ) ) {
			$user->set_role( $inactive_role );
		}
	}
}
