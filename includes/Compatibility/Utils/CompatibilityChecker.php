<?php
/**
 * WooCommerce Subscriptions Compatibility Checker
 *
 * This class provides compatibility checking functionality for the compatibility layer.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Compatibility\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility checker class
 */
class CompatibilityChecker {

	/**
	 * Check if compatibility is possible
	 *
	 * @return bool
	 */
	public static function is_compatibility_possible() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		// Check if WooCommerce Subscriptions is not active
		if ( class_exists( 'WC_Subscriptions' ) ) {
			return false;
		}

		// Check if WPSubscription is active
		if ( ! class_exists( 'SpringDevs\Subscription\Illuminate' ) ) {
			return false;
		}

		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			return false;
		}

		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get compatibility status
	 *
	 * @return array
	 */
	public static function get_compatibility_status() {
		$status = array(
			'compatible' => true,
			'issues'     => array(),
		);

		// Check WooCommerce
		if ( ! class_exists( 'WooCommerce' ) ) {
			$status['compatible'] = false;
			$status['issues'][] = __( 'WooCommerce is not active', 'wp-subscription' );
		}

		// Check WooCommerce Subscriptions
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$status['compatible'] = false;
			$status['issues'][] = __( 'WooCommerce Subscriptions is already active', 'wp-subscription' );
		}

		// Check WPSubscription
		if ( ! class_exists( 'SpringDevs\Subscription\Illuminate' ) ) {
			$status['compatible'] = false;
			$status['issues'][] = __( 'WPSubscription is not active', 'wp-subscription' );
		}

		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
			$status['compatible'] = false;
			$status['issues'][] = __( 'WordPress version is too old (requires 6.0+)', 'wp-subscription' );
		}

		// Check PHP version
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			$status['compatible'] = false;
			$status['issues'][] = __( 'PHP version is too old (requires 7.4+)', 'wp-subscription' );
		}

		// Check WooCommerce version
		if ( class_exists( 'WooCommerce' ) && version_compare( WC()->version, '6.0', '<' ) ) {
			$status['compatible'] = false;
			$status['issues'][] = __( 'WooCommerce version is too old (requires 6.0+)', 'wp-subscription' );
		}

		return $status;
	}

	/**
	 * Check if specific feature is compatible
	 *
	 * @param string $feature Feature name
	 * @return bool
	 */
	public static function is_feature_compatible( $feature ) {
		switch ( $feature ) {
			case 'subscriptions':
				return self::is_compatibility_possible();
			case 'renewals':
				return self::is_compatibility_possible() && function_exists( 'wp_schedule_event' );
			case 'switching':
				return self::is_compatibility_possible() && class_exists( 'WC_Product_Variable' );
			case 'gifting':
				return self::is_compatibility_possible() && function_exists( 'wp_mail' );
			default:
				return false;
		}
	}

	/**
	 * Get system information
	 *
	 * @return array
	 */
	public static function get_system_info() {
		global $wp_version;

		return array(
			'wordpress_version'    => $wp_version,
			'php_version'          => PHP_VERSION,
			'woocommerce_version'  => class_exists( 'WooCommerce' ) ? WC()->version : 'Not active',
			'wp_subscription_version' => defined( 'WP_SUBSCRIPTION_VERSION' ) ? WP_SUBSCRIPTION_VERSION : 'Unknown',
			'wcs_active'           => class_exists( 'WC_Subscriptions' ),
			'memory_limit'         => ini_get( 'memory_limit' ),
			'max_execution_time'   => ini_get( 'max_execution_time' ),
			'upload_max_filesize'  => ini_get( 'upload_max_filesize' ),
			'post_max_size'        => ini_get( 'post_max_size' ),
		);
	}

	/**
	 * Check for conflicts
	 *
	 * @return array
	 */
	public static function check_conflicts() {
		$conflicts = array();

		// Check for conflicting plugins
		$conflicting_plugins = array(
			'woocommerce-subscriptions/woocommerce-subscriptions.php' => 'WooCommerce Subscriptions',
			'woocommerce-subscriptions-core/woocommerce-subscriptions-core.php' => 'WooCommerce Subscriptions Core',
		);

		$active_plugins = get_option( 'active_plugins', array() );
		foreach ( $conflicting_plugins as $plugin => $name ) {
			if ( in_array( $plugin, $active_plugins, true ) ) {
				$conflicts[] = sprintf( __( '%s is active and may conflict with compatibility layer', 'wp-subscription' ), $name );
			}
		}

		// Check for conflicting themes
		$current_theme = wp_get_theme();
		if ( strpos( $current_theme->get_template(), 'subscription' ) !== false ) {
			$conflicts[] = __( 'Current theme may have subscription-related functionality that could conflict', 'wp-subscription' );
		}

		return $conflicts;
	}

	/**
	 * Get recommendations
	 *
	 * @return array
	 */
	public static function get_recommendations() {
		$recommendations = array();

		// Check memory limit
		$memory_limit = ini_get( 'memory_limit' );
		if ( wp_convert_hr_to_bytes( $memory_limit ) < wp_convert_hr_to_bytes( '256M' ) ) {
			$recommendations[] = __( 'Consider increasing PHP memory limit to 256M or higher for better performance', 'wp-subscription' );
		}

		// Check execution time
		$max_execution_time = ini_get( 'max_execution_time' );
		if ( $max_execution_time > 0 && $max_execution_time < 300 ) {
			$recommendations[] = __( 'Consider increasing PHP max execution time to 300 seconds or higher', 'wp-subscription' );
		}

		// Check WooCommerce version
		if ( class_exists( 'WooCommerce' ) && version_compare( WC()->version, '8.0', '<' ) ) {
			$recommendations[] = __( 'Consider updating WooCommerce to version 8.0 or higher for better compatibility', 'wp-subscription' );
		}

		return $recommendations;
	}
}
