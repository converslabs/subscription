<?php
/**
 * Helper WooCommerce Subscriptions Functions
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

// Additional helper functions for compatibility.

if ( ! function_exists( 'wcs_get_subscription_period_interval_strings' ) ) {
	/**
	 * Get subscription period interval strings.
	 *
	 * @since  1.0.0
	 * @param  string $period Period type.
	 * @return string
	 */
	function wcs_get_subscription_period_interval_strings( $period = '' ) {
		$intervals = array();
		foreach ( range( 1, 6 ) as $i ) {
			$intervals[ $i ] = wcs_get_subscription_period_strings( $i, $period );
		}
		return $intervals;
	}
	wpsubscription_compat_register_function( 'wcs_get_subscription_period_interval_strings' );
}

if ( ! function_exists( 'wcs_get_price_string' ) ) {
	/**
	 * Get subscription price string.
	 *
	 * @since  1.0.0
	 * @param  array $atts Attributes.
	 * @return string
	 */
	function wcs_get_price_string( $atts ) {
		$defaults = array(
			'recurring_amount'      => '',
			'subscription_period'   => '',
			'subscription_interval' => 1,
		);

		$atts = wp_parse_args( $atts, $defaults );

		if ( empty( $atts['recurring_amount'] ) ) {
			return '';
		}

		$period_string = wcs_get_subscription_period_strings( $atts['subscription_interval'], $atts['subscription_period'] );

		return sprintf(
			/* translators: 1: amount 2: interval 3: period */
			__( '%1$s every %2$s %3$s', 'wp_subscription' ),
			$atts['recurring_amount'],
			$atts['subscription_interval'] > 1 ? $atts['subscription_interval'] : '',
			$period_string
		);
	}
	wpsubscription_compat_register_function( 'wcs_get_price_string' );
}

if ( ! function_exists( 'wcs_estimate_next_payment_date' ) ) {
	/**
	 * Estimate next payment date for subscription.
	 *
	 * @since  1.0.0
	 * @param  int    $subscription_id Subscription ID.
	 * @param  string $timezone Timezone.
	 * @return string|null
	 */
	function wcs_estimate_next_payment_date( $subscription_id, $timezone = 'gmt' ) {
		$subscription = wcs_get_subscription( $subscription_id );
		if ( ! $subscription ) {
			return null;
		}

		return $subscription->get_date( 'next_payment', $timezone );
	}
	wpsubscription_compat_register_function( 'wcs_estimate_next_payment_date' );
}
