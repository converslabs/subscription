<?php
/**
 * Payment Gateway Compatibility Test
 *
 * This file tests the compatibility with payment gateways
 * by checking if all required functions and classes are available.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test payment gateway compatibility
 */
function test_payment_gateway_compatibility() {
	$tests = array();
	
	// Test core functions
	$tests['wcs_is_subscription'] = function_exists( 'wcs_is_subscription' );
	$tests['wcs_order_contains_subscription'] = function_exists( 'wcs_order_contains_subscription' );
	$tests['wcs_order_contains_renewal'] = function_exists( 'wcs_order_contains_renewal' );
	$tests['wcs_get_subscriptions_for_order'] = function_exists( 'wcs_get_subscriptions_for_order' );
	$tests['wcs_cart_contains_renewal'] = function_exists( 'wcs_cart_contains_renewal' );
	$tests['wcs_is_manual_renewal_required'] = function_exists( 'wcs_is_manual_renewal_required' );
	
	// Test classes
	$tests['WC_Subscriptions_Cart'] = class_exists( 'WC_Subscriptions_Cart' );
	$tests['WC_Subscriptions_Product'] = class_exists( 'WC_Subscriptions_Product' );
	$tests['WC_Subscriptions_Change_Payment_Gateway'] = class_exists( 'WC_Subscriptions_Change_Payment_Gateway' );
	
	// Test static methods
	$tests['WC_Subscriptions_Cart::cart_contains_subscription'] = method_exists( 'WC_Subscriptions_Cart', 'cart_contains_subscription' );
	$tests['WC_Subscriptions_Cart::cart_contains_renewal'] = method_exists( 'WC_Subscriptions_Cart', 'cart_contains_renewal' );
	$tests['WC_Subscriptions_Cart::cart_contains_free_trial'] = method_exists( 'WC_Subscriptions_Cart', 'cart_contains_free_trial' );
	$tests['WC_Subscriptions_Product::is_subscription'] = method_exists( 'WC_Subscriptions_Product', 'is_subscription' );
	$tests['WC_Subscriptions_Product::get_trial_length'] = method_exists( 'WC_Subscriptions_Product', 'get_trial_length' );
	
	// Test payment gateway detection
	$tests['PayPal Plugin Active'] = class_exists( 'PaymentPlugins\PPCP\WooCommerceSubscriptions\Package' );
	$tests['Stripe Plugin Active'] = class_exists( 'PaymentPlugins\Stripe\WooCommerceSubscriptions\Package' );
	
	// Display results
	echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px 0; border-radius: 5px;">';
	echo '<h3>Payment Gateway Compatibility Test Results</h3>';
	echo '<table style="width: 100%; border-collapse: collapse;">';
	echo '<tr style="background: #ddd;"><th style="padding: 10px; text-align: left;">Test</th><th style="padding: 10px; text-align: left;">Status</th></tr>';
	
	foreach ( $tests as $test => $result ) {
		$status = $result ? '<span style="color: green;">✓ PASS</span>' : '<span style="color: red;">✗ FAIL</span>';
		echo '<tr><td style="padding: 8px; border-bottom: 1px solid #ccc;">' . esc_html( $test ) . '</td><td style="padding: 8px; border-bottom: 1px solid #ccc;">' . $status . '</td></tr>';
	}
	
	echo '</table>';
	
	$passed = array_sum( $tests );
	$total = count( $tests );
	$percentage = round( ( $passed / $total ) * 100, 2 );
	
	echo '<p><strong>Overall: ' . $passed . '/' . $total . ' tests passed (' . $percentage . '%)</strong></p>';
	
	if ( $percentage >= 90 ) {
		echo '<p style="color: green;"><strong>✅ Payment gateway compatibility is excellent!</strong></p>';
	} elseif ( $percentage >= 70 ) {
		echo '<p style="color: orange;"><strong>⚠️ Payment gateway compatibility is good but needs improvement.</strong></p>';
	} else {
		echo '<p style="color: red;"><strong>❌ Payment gateway compatibility needs significant work.</strong></p>';
	}
	
	echo '</div>';
}

// Run test if in admin and debug mode is enabled
if ( is_admin() && defined( 'WP_SUBSCRIPTION_COMPATIBILITY_DEBUG' ) && WP_SUBSCRIPTION_COMPATIBILITY_DEBUG ) {
	add_action( 'admin_notices', 'test_payment_gateway_compatibility' );
}
