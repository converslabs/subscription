<?php
/**
 * Plugin Name - Compatibility Layer Tests
 *
 * @package   WPSubscription\Tests
 * @copyright Copyright (c)
 * @license   GPL-2.0+
 * @since     1.0.0
 */

use WP_UnitTestCase;

/**
 * Compatibility layer expectations derived from WooCommerce Subscriptions.
 *
 * @group compat
 */
class WPSubscription_Compat_Layer_Tests extends WP_UnitTestCase {

	/**
	 * Ensure WooCommerce Subscriptions facade class is available.
	 */
	public function test_wc_subscription_facade_class_exists() {
		$this->assertTrue(
			class_exists( 'WC_Subscription' ),
			'Expected WC_Subscription facade class to exist for compatibility.'
		);
	}

	/**
	 * Ensure core helper function resolves subscriptions for a user.
	 */
	public function test_wcs_get_users_subscriptions_function_exists() {
		$this->assertTrue(
			function_exists( 'wcs_get_users_subscriptions' ),
			'Expected wcs_get_users_subscriptions() helper to exist.'
		);
	}

	/**
	 * Ensure compatibility hook is registered for scheduled payments.
	 */
	public function test_gateway_scheduled_payment_hook_registered() {
		$hook_name = 'woocommerce_scheduled_subscription_payment_stripe';

		$this->assertNotFalse(
			has_action( $hook_name ),
			"Expected action {$hook_name} to be registered for compatibility."
		);
	}
}
