<?php
/**
 * WooCommerce Subscriptions Compatibility Test
 *
 * This file provides a simple test to verify the compatibility layer is working.
 * It should be removed in production.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Test compatibility layer
 */
function wp_subscription_test_compatibility() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="notice notice-info">';
	echo '<h3>WPSubscription WooCommerce Subscriptions Compatibility Test</h3>';
	
	// Test 1: Check if compatibility layer is active
	$bootstrap = \SpringDevs\Subscription\Compatibility\Bootstrap::get_instance();
	echo '<p><strong>Compatibility Layer Active:</strong> ' . ( $bootstrap->is_active() ? 'Yes' : 'No' ) . '</p>';
	
	// Test 2: Check if classes are available
	echo '<p><strong>WC_Subscription Class:</strong> ' . ( class_exists( 'WC_Subscription' ) ? 'Available' : 'Not Available' ) . '</p>';
	echo '<p><strong>WC_Subscriptions_Manager Class:</strong> ' . ( class_exists( 'WC_Subscriptions_Manager' ) ? 'Available' : 'Not Available' ) . '</p>';
	echo '<p><strong>WC_Subscriptions_Product Class:</strong> ' . ( class_exists( 'WC_Subscriptions_Product' ) ? 'Available' : 'Not Available' ) . '</p>';
	echo '<p><strong>WC_Subscriptions_Order Class:</strong> ' . ( class_exists( 'WC_Subscriptions_Order' ) ? 'Available' : 'Not Available' ) . '</p>';
	
	// Test 3: Check if functions are available
	echo '<p><strong>wcs_is_subscription Function:</strong> ' . ( function_exists( 'wcs_is_subscription' ) ? 'Available' : 'Not Available' ) . '</p>';
	echo '<p><strong>wcs_get_subscription Function:</strong> ' . ( function_exists( 'wcs_get_subscription' ) ? 'Available' : 'Not Available' ) . '</p>';
	echo '<p><strong>wcs_get_subscriptions Function:</strong> ' . ( function_exists( 'wcs_get_subscriptions' ) ? 'Available' : 'Not Available' ) . '</p>';
	
	// Test 4: Check compatibility status
	$status = $bootstrap->get_status();
	echo '<h4>Compatibility Status:</h4>';
	echo '<ul>';
	echo '<li><strong>WooCommerce Active:</strong> ' . ( $status['woocommerce'] ? 'Yes' : 'No' ) . '</li>';
	echo '<li><strong>WooCommerce Subscriptions Active:</strong> ' . ( $status['wcs_active'] ? 'Yes' : 'No' ) . '</li>';
	echo '<li><strong>WPSubscription Active:</strong> ' . ( $status['wp_subscription'] ? 'Yes' : 'No' ) . '</li>';
	echo '<li><strong>Compatibility Version:</strong> ' . $status['version'] . '</li>';
	echo '</ul>';
	
	// Test 5: Check if post type is registered
	echo '<p><strong>shop_subscription Post Type:</strong> ' . ( post_type_exists( 'shop_subscription' ) ? 'Registered' : 'Not Registered' ) . '</p>';
	
	echo '</div>';
}

// Add test to admin notices
add_action( 'admin_notices', 'wp_subscription_test_compatibility' );
