<?php
/**
 * WC_Subscriptions_Manager Compatibility Class
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Classes;

use SpringDevs\Subscription\Illuminate\Action;

/**
 * WC_Subscriptions_Manager class.
 *
 * Static helper methods for subscription management.
 *
 * @package SpringDevs\Subscription\Compatibility\Classes
 * @since   1.0.0
 */
class WC_Subscriptions_Manager {

	/**
	 * Activate a subscription.
	 *
	 * @since 1.0.0
	 * @param int $subscription_id Subscription ID.
	 * @return bool
	 */
	public static function activate_subscription( $subscription_id ) {
		Action::status( 'active', $subscription_id );
		return true;
	}

	/**
	 * Process subscription payments on order.
	 *
	 * @since 1.0.0
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function process_subscription_payments_on_order( $order_id ) {
		// This is called during checkout completion.
		do_action( 'wpsubscription_process_subscription_payment', $order_id );
	}

	/**
	 * Process subscription payment.
	 *
	 * @since 1.0.0
	 * @param int    $subscription_id Subscription ID.
	 * @param float  $amount Amount to charge.
	 * @param string $payment_gateway Gateway ID.
	 * @return bool
	 */
	public static function process_subscription_payment( $subscription_id, $amount, $payment_gateway = '' ) {
		// Trigger scheduled payment hook.
		if ( $payment_gateway ) {
			do_action( 'woocommerce_scheduled_subscription_payment_' . $payment_gateway, $amount, null );
		}
		return true;
	}

	/**
	 * Get user's subscriptions.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id User ID.
	 * @param  string $status Subscription status.
	 * @return array
	 */
	public static function get_users_subscriptions( $user_id = 0, $status = 'any' ) {
		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		$args = array(
			'post_type'   => 'subscrpt_order',
			'post_status' => 'any' === $status ? array( 'active', 'on-hold', 'cancelled', 'expired' ) : $status,
			'numberposts' => -1,
			'meta_query'  => array(
				array(
					'key'     => '_customer_user',
					'value'   => $user_id,
					'compare' => '=',
				),
			),
		);

		$subscriptions = get_posts( $args );
		$result        = array();

		foreach ( $subscriptions as $subscription_post ) {
			$result[ $subscription_post->ID ] = new WC_Subscription( $subscription_post->ID );
		}

		return $result;
	}

	/**
	 * Self-test method.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function test() {
		return array(
			'class_exists'            => true,
			'activate_subscription'   => method_exists( __CLASS__, 'activate_subscription' ),
			'get_users_subscriptions' => method_exists( __CLASS__, 'get_users_subscriptions' ),
		);
	}
}
