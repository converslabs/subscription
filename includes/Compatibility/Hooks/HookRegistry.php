<?php
/**
 * Hook Registry
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Hooks;

/**
 * HookRegistry class.
 *
 * Tracks all registered compatibility hooks.
 *
 * @package SpringDevs\Subscription\Compatibility\Hooks
 * @since   1.0.0
 */
class HookRegistry {

	/**
	 * Registered action hooks.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	private static $registered_actions = array();

	/**
	 * Registered filter hooks.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	private static $registered_filters = array();

	/**
	 * Register an action hook.
	 *
	 * @since 1.0.0
	 * @param string   $tag Hook name.
	 * @param callable $function Callback function.
	 * @param int      $priority Priority.
	 * @param int      $accepted_args Number of arguments.
	 */
	public static function register_action( $tag, $function, $priority = 10, $accepted_args = 1 ) {
		if ( ! isset( self::$registered_actions[ $tag ] ) ) {
			self::$registered_actions[ $tag ] = array();
		}

		self::$registered_actions[ $tag ][] = array(
			'function'      => $function,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		add_action( $tag, $function, $priority, $accepted_args );
	}

	/**
	 * Register a filter hook.
	 *
	 * @since 1.0.0
	 * @param string   $tag Hook name.
	 * @param callable $function Callback function.
	 * @param int      $priority Priority.
	 * @param int      $accepted_args Number of arguments.
	 */
	public static function register_filter( $tag, $function, $priority = 10, $accepted_args = 1 ) {
		if ( ! isset( self::$registered_filters[ $tag ] ) ) {
			self::$registered_filters[ $tag ] = array();
		}

		self::$registered_filters[ $tag ][] = array(
			'function'      => $function,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);

		add_filter( $tag, $function, $priority, $accepted_args );
	}

	/**
	 * Get all registered hooks.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	public static function get_registered_hooks() {
		return array(
			'actions' => self::$registered_actions,
			'filters' => self::$registered_filters,
		);
	}

	/**
	 * Test if a hook exists in WordPress.
	 *
	 * @since  1.0.0
	 * @param  string $tag Hook name.
	 * @return bool
	 */
	public static function test_hook_exists( $tag ) {
		global $wp_filter;
		return isset( $wp_filter[ $tag ] );
	}

	/**
	 * Get action hooks count.
	 *
	 * @since  1.0.0
	 * @return int
	 */
	public static function get_action_count() {
		return count( self::$registered_actions );
	}

	/**
	 * Get filter hooks count.
	 *
	 * @since  1.0.0
	 * @return int
	 */
	public static function get_filter_count() {
		return count( self::$registered_filters );
	}
}
