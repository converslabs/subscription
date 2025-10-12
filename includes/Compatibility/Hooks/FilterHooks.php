<?php
/**
 * Filter Hooks Translation
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Hooks;

/**
 * FilterHooks class.
 *
 * Translates WooCommerce Subscriptions filter hooks to WPSubscription.
 *
 * @package SpringDevs\Subscription\Compatibility\Hooks
 * @since   1.0.0
 */
class FilterHooks {

	/**
	 * Initialize filter hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		$instance = new self();
		$instance->register_hooks();
	}

	/**
	 * Register all filter hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks() {
		// Subscription product price string.
		HookRegistry::register_filter(
			'woocommerce_subscriptions_product_price_string',
			array( $this, 'format_product_price_string' ),
			10,
			2
		);

		// Subscription payment meta.
		HookRegistry::register_filter(
			'woocommerce_subscription_payment_meta',
			array( $this, 'get_subscription_payment_meta' ),
			10,
			2
		);

		// Can item be removed from subscription.
		HookRegistry::register_filter(
			'wcs_can_item_be_removed',
			array( $this, 'can_item_be_removed' ),
			10,
			2
		);

		// Subscription statuses.
		HookRegistry::register_filter(
			'wcs_subscription_statuses',
			array( $this, 'get_subscription_statuses' ),
			10,
			1
		);
	}

	/**
	 * Format product price string.
	 *
	 * @since  1.0.0
	 * @param  string     $price_string Price string.
	 * @param  WC_Product $product Product object.
	 * @return string
	 */
	public function format_product_price_string( $price_string, $product ) {
		return apply_filters( 'subscrpt_product_price_string', $price_string, $product );
	}

	/**
	 * Get subscription payment meta.
	 *
	 * @since  1.0.0
	 * @param  array $meta Payment meta.
	 * @param  int   $subscription_id Subscription ID.
	 * @return array
	 */
	public function get_subscription_payment_meta( $meta, $subscription_id ) {
		return $meta;
	}

	/**
	 * Check if item can be removed from subscription.
	 *
	 * @since  1.0.0
	 * @param  bool  $can_remove Whether item can be removed.
	 * @param  array $item Item data.
	 * @return bool
	 */
	public function can_item_be_removed( $can_remove, $item ) {
		return $can_remove;
	}

	/**
	 * Get subscription statuses.
	 *
	 * @since  1.0.0
	 * @param  array $statuses Statuses.
	 * @return array
	 */
	public function get_subscription_statuses( $statuses ) {
		if ( function_exists( 'wcs_get_subscription_statuses' ) ) {
			return wcs_get_subscription_statuses();
		}
		return $statuses;
	}

	/**
	 * Test if hooks are registered.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function test_hooks() {
		$hooks = array(
			'woocommerce_subscriptions_product_price_string',
			'woocommerce_subscription_payment_meta',
			'wcs_can_item_be_removed',
			'wcs_subscription_statuses',
		);

		$tests = array();
		foreach ( $hooks as $hook ) {
			$tests[ $hook ] = HookRegistry::test_hook_exists( $hook );
		}

		return $tests;
	}
}
