<?php
/**
 * Gateway Detector
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Gateways;

/**
 * GatewayDetector class.
 *
 * Scans and detects payment gateways with subscription support.
 *
 * @package SpringDevs\Subscription\Compatibility\Gateways
 * @since   1.0.0
 */
class GatewayDetector {

	/**
	 * Compatible gateway IDs.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	private static $compatible_gateways = array(
		'stripe_cc',
		'stripe',
		'stripe_apple_pay',
		'stripe_google_pay',
		'paypal',
		'ppec_paypal',
		'mollie_wc_gateway_creditcard',
		'mollie_wc_gateway_ideal',
		'razorpay',
		'square_credit_card',
		'woocommerce_payments',
	);

	/**
	 * Scan all available payment gateways.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function scan_gateways() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return array();
		}

		$available_gateways = WC()->payment_gateways()->payment_gateways();
		$supported          = array();

		foreach ( $available_gateways as $gateway ) {
			$supported[ $gateway->id ] = array(
				'id'                        => $gateway->id,
				'title'                     => $gateway->get_title(),
				'enabled'                   => 'yes' === $gateway->enabled,
				'has_subscriptions_support' => $gateway->supports( 'subscriptions' ),
				'is_compatible'             => self::is_gateway_compatible( $gateway->id ),
				'plugin_file'               => self::detect_plugin_file( $gateway ),
			);
		}

		return $supported;
	}

	/**
	 * Check if gateway is compatible with subscriptions.
	 *
	 * @since  1.0.0
	 * @param  string $gateway_id Gateway ID.
	 * @return bool
	 */
	public static function is_gateway_compatible( $gateway_id ) {
		return in_array( $gateway_id, self::$compatible_gateways, true );
	}

	/**
	 * Test if gateway supports scheduled payments.
	 *
	 * @since  1.0.0
	 * @param  string $gateway_id Gateway ID.
	 * @return bool
	 */
	public static function test_gateway_support( $gateway_id ) {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return false;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( ! isset( $gateways[ $gateway_id ] ) ) {
			return false;
		}

		return $gateways[ $gateway_id ]->supports( 'subscriptions' );
	}

	/**
	 * Detect plugin file for a gateway.
	 *
	 * @since  1.0.0
	 * @param  WC_Payment_Gateway $gateway Gateway object.
	 * @return string|null
	 */
	private static function detect_plugin_file( $gateway ) {
		// Map of gateway IDs to their likely plugin files.
		$plugin_map = array(
			'stripe_cc'                    => 'woo-stripe-payment/stripe-payments.php',
			'stripe'                       => 'woo-stripe-payment/stripe-payments.php',
			'paypal'                       => 'woocommerce/woocommerce.php',
			'ppec_paypal'                  => 'woocommerce-gateway-paypal-express-checkout/woocommerce-gateway-paypal-express-checkout.php',
			'mollie_wc_gateway_creditcard' => 'mollie-payments-for-woocommerce/mollie-payments-for-woocommerce.php',
			'razorpay'                     => 'woo-razorpay/woo-razorpay.php',
			'square_credit_card'           => 'woocommerce-square/woocommerce-square.php',
			'woocommerce_payments'         => 'woocommerce-payments/woocommerce-payments.php',
		);

		$gateway_id = $gateway->id;
		return isset( $plugin_map[ $gateway_id ] ) ? $plugin_map[ $gateway_id ] : null;
	}

	/**
	 * Get list of enabled compatible gateways.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function get_enabled_compatible_gateways() {
		$all_gateways = self::scan_gateways();
		$enabled      = array();

		foreach ( $all_gateways as $gateway ) {
			if ( $gateway['enabled'] && $gateway['is_compatible'] ) {
				$enabled[] = $gateway;
			}
		}

		return $enabled;
	}
}
