<?php
/**
 * WooCommerce Subscriptions Compatibility Logger
 *
 * This class provides logging functionality for the compatibility layer.
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
 * Logger class
 */
class Logger {

	/**
	 * Log levels
	 */
	const LEVEL_DEBUG = 'debug';
	const LEVEL_INFO = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR = 'error';

	/**
	 * Log file path
	 *
	 * @var string
	 */
	private static $log_file = null;

	/**
	 * Get log file path
	 *
	 * @return string
	 */
	private static function get_log_file() {
		if ( null === self::$log_file ) {
			$upload_dir = wp_upload_dir();
			self::$log_file = $upload_dir['basedir'] . '/wp-subscription-compatibility.log';
		}
		return self::$log_file;
	}

	/**
	 * Log a message
	 *
	 * @param string $message Log message
	 * @param string $level Log level
	 * @param array $context Additional context
	 * @return void
	 */
	public static function log( $message, $level = self::LEVEL_INFO, $context = array() ) {
		if ( ! defined( 'WP_SUBSCRIPTION_COMPATIBILITY_DEBUG' ) || ! WP_SUBSCRIPTION_COMPATIBILITY_DEBUG ) {
			return;
		}

		$timestamp = current_time( 'Y-m-d H:i:s' );
		$context_string = ! empty( $context ) ? ' ' . wp_json_encode( $context ) : '';
		$log_entry = sprintf( '[%s] %s: %s%s', $timestamp, strtoupper( $level ), $message, $context_string ) . PHP_EOL;

		// Write to log file
		error_log( $log_entry, 3, self::get_log_file() );
	}

	/**
	 * Log debug message
	 *
	 * @param string $message Log message
	 * @param array $context Additional context
	 * @return void
	 */
	public static function debug( $message, $context = array() ) {
		self::log( $message, self::LEVEL_DEBUG, $context );
	}

	/**
	 * Log info message
	 *
	 * @param string $message Log message
	 * @param array $context Additional context
	 * @return void
	 */
	public static function info( $message, $context = array() ) {
		self::log( $message, self::LEVEL_INFO, $context );
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Log message
	 * @param array $context Additional context
	 * @return void
	 */
	public static function warning( $message, $context = array() ) {
		self::log( $message, self::LEVEL_WARNING, $context );
	}

	/**
	 * Log error message
	 *
	 * @param string $message Log message
	 * @param array $context Additional context
	 * @return void
	 */
	public static function error( $message, $context = array() ) {
		self::log( $message, self::LEVEL_ERROR, $context );
	}

	/**
	 * Clear log file
	 *
	 * @return bool
	 */
	public static function clear_log() {
		$log_file = self::get_log_file();
		if ( file_exists( $log_file ) ) {
			return unlink( $log_file );
		}
		return true;
	}

	/**
	 * Get log file size
	 *
	 * @return int
	 */
	public static function get_log_size() {
		$log_file = self::get_log_file();
		if ( file_exists( $log_file ) ) {
			return filesize( $log_file );
		}
		return 0;
	}

	/**
	 * Get log file contents
	 *
	 * @param int $lines Number of lines to return
	 * @return string
	 */
	public static function get_log_contents( $lines = 100 ) {
		$log_file = self::get_log_file();
		if ( ! file_exists( $log_file ) ) {
			return '';
		}

		$file_lines = file( $log_file );
		if ( false === $file_lines ) {
			return '';
		}

		$file_lines = array_reverse( $file_lines );
		$file_lines = array_slice( $file_lines, 0, $lines );
		$file_lines = array_reverse( $file_lines );

		return implode( '', $file_lines );
	}
}
