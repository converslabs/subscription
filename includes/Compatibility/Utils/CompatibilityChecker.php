<?php
/**
 * Compatibility Checker
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Utils;

use SpringDevs\Subscription\Compatibility\Bootstrap;

/**
 * CompatibilityChecker class.
 *
 * Checks compatibility layer health.
 *
 * @package SpringDevs\Subscription\Compatibility\Utils
 * @since   1.0.0
 */
class CompatibilityChecker {

	/**
	 * Run all compatibility checks.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function run_all_checks() {
		return array(
			'bootstrap' => self::check_bootstrap(),
			'functions' => self::check_functions(),
			'classes'   => self::check_classes(),
			'aliases'   => self::check_aliases(),
			'hooks'     => self::check_hooks(),
			'gateways'  => self::check_gateways(),
		);
	}

	/**
	 * Check if bootstrap is loaded.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	private static function check_bootstrap() {
		$status = Bootstrap::get_status();
		return array(
			'passed'  => $status['is_healthy'],
			'message' => $status['is_healthy'] ? 'Bootstrap loaded successfully' : 'Bootstrap has errors',
		);
	}

	/**
	 * Check if functions are loaded.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	private static function check_functions() {
		$functions = wpsubscription_compat_get_functions();
		$passed    = count( $functions ) >= 10;
		return array(
			'passed'  => $passed,
			'message' => sprintf( '%d functions loaded', count( $functions ) ),
		);
	}

	/**
	 * Check if classes are loaded.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	private static function check_classes() {
		$required_classes = array(
			'WC_Subscription',
			'WC_Subscriptions_Manager',
			'WC_Subscriptions_Product',
			'WC_Subscriptions_Order',
			'WC_Subscriptions_Cart',
			'WC_Subscriptions_Change_Payment_Gateway',
		);

		$loaded = 0;
		foreach ( $required_classes as $class ) {
			if ( class_exists( $class ) ) {
				++$loaded;
			}
		}

		return array(
			'passed'  => $loaded === count( $required_classes ),
			'message' => sprintf( '%d/%d classes loaded', $loaded, count( $required_classes ) ),
		);
	}

	/**
	 * Check if aliases are working.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	private static function check_aliases() {
		return self::check_classes(); // Same check.
	}

	/**
	 * Check if hooks are registered.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	private static function check_hooks() {
		$hooks = \SpringDevs\Subscription\Compatibility\Hooks\HookRegistry::get_registered_hooks();
		$total = count( $hooks['actions'] ) + count( $hooks['filters'] );
		return array(
			'passed'  => $total > 0,
			'message' => sprintf( '%d hooks registered', $total ),
		);
	}

	/**
	 * Check gateway integration.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	private static function check_gateways() {
		$gateways = \SpringDevs\Subscription\Compatibility\Gateways\GatewayDetector::get_enabled_compatible_gateways();
		return array(
			'passed'  => count( $gateways ) > 0,
			'message' => sprintf( '%d compatible gateways found', count( $gateways ) ),
		);
	}
}
