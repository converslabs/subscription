<?php
/**
 * Hook Manager
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Hooks;

/**
 * HookManager class.
 *
 * Orchestrates all hook translations.
 *
 * @package SpringDevs\Subscription\Compatibility\Hooks
 * @since   1.0.0
 */
class HookManager {

	/**
	 * Initialize the hook manager.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Action and Filter hooks are already initialized by Bootstrap.
		// This manager can provide additional coordination if needed.

		// Add debug hook to monitor all compatibility hooks.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'wpsubscription_compat_loaded', array( __CLASS__, 'log_hooks' ) );
		}
	}

	/**
	 * Log registered hooks (debug mode).
	 *
	 * @since 1.0.0
	 * @param array $components Loaded components.
	 */
	public static function log_hooks( $components ) {
		$hooks = HookRegistry::get_registered_hooks();

		if ( function_exists( 'error_log' ) ) {
			error_log( 'WPSubscription Compatibility Hooks Loaded: ' . count( $hooks['actions'] ) . ' actions, ' . count( $hooks['filters'] ) . ' filters' );
		}
	}

	/**
	 * Get all hook statistics.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function get_hook_stats() {
		return array(
			'action_count' => HookRegistry::get_action_count(),
			'filter_count' => HookRegistry::get_filter_count(),
			'total_count'  => HookRegistry::get_action_count() + HookRegistry::get_filter_count(),
		);
	}
}
