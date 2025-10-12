<?php
/**
 * WC_Subscriptions_Product Compatibility Class
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Classes;

/**
 * WC_Subscriptions_Product class.
 *
 * Product-related subscription helper methods.
 *
 * @package SpringDevs\Subscription\Compatibility\Classes
 * @since   1.0.0
 */
class WC_Subscriptions_Product {

	/**
	 * Check if product is a subscription.
	 *
	 * @since  1.0.0
	 * @param  mixed $product Product ID or object.
	 * @return bool
	 */
	public static function is_subscription( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return false;
		}

		$enabled = $product->get_meta( '_subscrpt_enabled', true );
		return '1' === $enabled || 'yes' === $enabled;
	}

	/**
	 * Get billing period.
	 *
	 * @since  1.0.0
	 * @param  mixed $product Product ID or object.
	 * @return string
	 */
	public static function get_period( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return '';
		}

		return $product->get_meta( '_subscrpt_timing_option', true );
	}

	/**
	 * Get billing interval.
	 *
	 * @since  1.0.0
	 * @param  mixed $product Product ID or object.
	 * @return int
	 */
	public static function get_interval( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return 1;
		}

		$interval = $product->get_meta( '_subscrpt_timing_per', true );
		return ! empty( $interval ) ? absint( $interval ) : 1;
	}

	/**
	 * Get subscription price.
	 *
	 * @since  1.0.0
	 * @param  mixed $product Product ID or object.
	 * @return float
	 */
	public static function get_price( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return 0;
		}

		return floatval( $product->get_price() );
	}

	/**
	 * Get sign-up fee.
	 *
	 * @since  1.0.0
	 * @param  mixed $product Product ID or object.
	 * @return float
	 */
	public static function get_sign_up_fee( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return 0;
		}

		$signup_fee = $product->get_meta( '_subscrpt_signup_fee', true );
		return ! empty( $signup_fee ) ? floatval( $signup_fee ) : 0;
	}

	/**
	 * Get trial period.
	 *
	 * @since  1.0.0
	 * @param  mixed $product Product ID or object.
	 * @return string
	 */
	public static function get_trial_period( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return '';
		}

		$trial = $product->get_meta( '_subscrpt_trial', true );
		if ( is_array( $trial ) && isset( $trial['type'] ) ) {
			return $trial['type'];
		}

		return '';
	}

	/**
	 * Get trial length.
	 *
	 * @since  1.0.0
	 * @param  mixed $product Product ID or object.
	 * @return int
	 */
	public static function get_trial_length( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return 0;
		}

		$trial = $product->get_meta( '_subscrpt_trial', true );
		if ( is_array( $trial ) && isset( $trial['time'] ) ) {
			return absint( $trial['time'] );
		}

		return 0;
	}

	/**
	 * Self-test method.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function test() {
		return array(
			'class_exists'    => true,
			'is_subscription' => method_exists( __CLASS__, 'is_subscription' ),
			'get_period'      => method_exists( __CLASS__, 'get_period' ),
			'get_interval'    => method_exists( __CLASS__, 'get_interval' ),
		);
	}
}
