<?php
/**
 * WC_Subscriptions_Change_Payment_Gateway Compatibility Class
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Classes;

/**
 * WC_Subscriptions_Change_Payment_Gateway class.
 *
 * Helper for payment gateway change detection.
 *
 * @package SpringDevs\Subscription\Compatibility\Classes
 * @since   1.0.0
 */
class WC_Subscriptions_Change_Payment_Gateway {

	/**
	 * Flag to indicate if request is to change payment method.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public static $is_request_to_change_payment = false;

	/**
	 * Initialize.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'detect_payment_change_request' ) );
	}

	/**
	 * Detect if current request is for payment method change.
	 *
	 * @since 1.0.0
	 */
	public static function detect_payment_change_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['change_payment_method'] ) ) {
			self::$is_request_to_change_payment = true;
		}
		// phpcs:enable
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
			'property_exists' => property_exists( __CLASS__, 'is_request_to_change_payment' ),
		);
	}
}

// Initialize the class.
WC_Subscriptions_Change_Payment_Gateway::init();
