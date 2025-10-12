<?php
/**
 * WC_Subscriptions_Cart Compatibility Class
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Classes;

/**
 * WC_Subscriptions_Cart class.
 *
 * Cart-related subscription helper methods.
 *
 * @package SpringDevs\Subscription\Compatibility\Classes
 * @since   1.0.0
 */
class WC_Subscriptions_Cart {

	/**
	 * Check if cart contains subscription product.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public static function cart_contains_subscription() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['product_id'] ) && WC_Subscriptions_Product::is_subscription( $cart_item['product_id'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if cart contains renewal.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public static function cart_contains_renewal() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['subscription_renewal'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if cart contains free trial.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public static function cart_contains_free_trial() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['product_id'] ) ) {
				$trial_length = WC_Subscriptions_Product::get_trial_length( $cart_item['product_id'] );
				if ( $trial_length > 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get cart subscription string for display.
	 *
	 * @since  1.0.0
	 * @param  WC_Product $product Product object.
	 * @return string
	 */
	public static function get_cart_subscription_string( $product ) {
		if ( ! WC_Subscriptions_Product::is_subscription( $product ) ) {
			return '';
		}

		$period   = WC_Subscriptions_Product::get_period( $product );
		$interval = WC_Subscriptions_Product::get_interval( $product );

		$period_strings = array(
			'day'   => _n( 'day', 'days', $interval, 'wp_subscription' ),
			'week'  => _n( 'week', 'weeks', $interval, 'wp_subscription' ),
			'month' => _n( 'month', 'months', $interval, 'wp_subscription' ),
			'year'  => _n( 'year', 'years', $interval, 'wp_subscription' ),
		);

		$period_string = isset( $period_strings[ $period ] ) ? $period_strings[ $period ] : $period;

		return sprintf(
			/* translators: 1: interval 2: period */
			__( 'every %1$s %2$s', 'wp_subscription' ),
			$interval > 1 ? $interval : '',
			$period_string
		);
	}

	/**
	 * Self-test method.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function test() {
		return array(
			'class_exists'               => true,
			'cart_contains_subscription' => method_exists( __CLASS__, 'cart_contains_subscription' ),
			'cart_contains_renewal'      => method_exists( __CLASS__, 'cart_contains_renewal' ),
			'cart_contains_free_trial'   => method_exists( __CLASS__, 'cart_contains_free_trial' ),
		);
	}
}
