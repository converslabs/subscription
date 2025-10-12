<?php
/**
 * Compatibility Logger
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Utils;

/**
 * Logger class.
 *
 * Logs compatibility layer events.
 *
 * @package SpringDevs\Subscription\Compatibility\Utils
 * @since   1.0.0
 */
class Logger {

	/**
	 * Log a message.
	 *
	 * @since 1.0.0
	 * @param string $message Message to log.
	 * @param string $level Log level (info, warning, error).
	 */
	public static function log( $message, $level = 'info' ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( 'error_log' ) ) {
			error_log( sprintf( '[WPSubscription Compat][%s] %s', strtoupper( $level ), $message ) );
		}
	}
}
