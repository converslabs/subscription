<?php

namespace SpringDevs\Subscription\Illuminate\Debug;

/**
 * Debug Configuration
 *
 * Manages debug settings and configuration for the auto-renewal payment system.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class DebugConfig {

	/**
	 * Debug settings
	 *
	 * @var array
	 */
	private static $settings = array();

	/**
	 * Initialize debug configuration
	 *
	 * @return void
	 */
	public static function init() {
		self::$settings = array(
			'debug_mode'           => defined( 'WP_SUBSCRIPTION_DEBUG' ) && WP_SUBSCRIPTION_DEBUG,
			'log_level'            => get_option( 'subscrpt_debug_log_level', 'info' ),
			'max_log_size'         => get_option( 'subscrpt_debug_max_log_size', 10485760 ), // 10MB
			'log_retention_days'   => get_option( 'subscrpt_debug_log_retention_days', 30 ),
			'enable_performance_logging' => get_option( 'subscrpt_debug_performance', true ),
			'enable_database_logging'    => get_option( 'subscrpt_debug_database', true ),
			'enable_webhook_logging'     => get_option( 'subscrpt_debug_webhooks', true ),
			'enable_payment_logging'     => get_option( 'subscrpt_debug_payments', true ),
			'log_file_path'         => self::get_log_file_path(),
			'backup_logs'           => get_option( 'subscrpt_debug_backup_logs', true ),
		);

		// Register settings
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Get debug setting
	 *
	 * @param string $key Setting key
	 * @param mixed  $default Default value
	 * @return mixed Setting value
	 */
	public static function get( $key, $default = null ) {
		return isset( self::$settings[ $key ] ) ? self::$settings[ $key ] : $default;
	}

	/**
	 * Set debug setting
	 *
	 * @param string $key Setting key
	 * @param mixed  $value Setting value
	 * @return void
	 */
	public static function set( $key, $value ) {
		self::$settings[ $key ] = $value;
	}

	/**
	 * Check if debug mode is enabled
	 *
	 * @return bool True if debug mode enabled
	 */
	public static function is_debug_enabled() {
		return self::get( 'debug_mode', false );
	}

	/**
	 * Check if specific logging is enabled
	 *
	 * @param string $type Logging type
	 * @return bool True if enabled
	 */
	public static function is_logging_enabled( $type ) {
		$key = "enable_{$type}_logging";
		return self::get( $key, true );
	}

	/**
	 * Get log file path
	 *
	 * @return string Log file path
	 */
	public static function get_log_file_path() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/subscrpt-debug.log';
	}

	/**
	 * Get backup log file path
	 *
	 * @return string Backup log file path
	 */
	public static function get_backup_log_file_path() {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/subscrpt-debug-backup.log';
	}

	/**
	 * Register debug settings
	 *
	 * @return void
	 */
	public static function register_settings() {
		// Register settings
		register_setting( 'subscrpt_debug_settings', 'subscrpt_debug_log_level' );
		register_setting( 'subscrpt_debug_settings', 'subscrpt_debug_max_log_size' );
		register_setting( 'subscrpt_debug_settings', 'subscrpt_debug_log_retention_days' );
		register_setting( 'subscrpt_debug_settings', 'subscrpt_debug_performance' );
		register_setting( 'subscrpt_debug_settings', 'subscrpt_debug_database' );
		register_setting( 'subscrpt_debug_settings', 'subscrpt_debug_webhooks' );
		register_setting( 'subscrpt_debug_settings', 'subscrpt_debug_payments' );
		register_setting( 'subscrpt_debug_settings', 'subscrpt_debug_backup_logs' );

		// Add settings section
		add_settings_section(
			'subscrpt_debug_section',
			'Debug Settings',
			array( __CLASS__, 'debug_section_callback' ),
			'subscrpt_debug_settings'
		);

		// Add settings fields
		self::add_settings_fields();
	}

	/**
	 * Add settings fields
	 *
	 * @return void
	 */
	private static function add_settings_fields() {
		$fields = array(
			array(
				'id'       => 'subscrpt_debug_log_level',
				'title'    => 'Log Level',
				'callback' => array( __CLASS__, 'log_level_field_callback' ),
			),
			array(
				'id'       => 'subscrpt_debug_max_log_size',
				'title'    => 'Max Log Size (bytes)',
				'callback' => array( __CLASS__, 'max_log_size_field_callback' ),
			),
			array(
				'id'       => 'subscrpt_debug_log_retention_days',
				'title'    => 'Log Retention (days)',
				'callback' => array( __CLASS__, 'log_retention_field_callback' ),
			),
			array(
				'id'       => 'subscrpt_debug_performance',
				'title'    => 'Enable Performance Logging',
				'callback' => array( __CLASS__, 'performance_logging_field_callback' ),
			),
			array(
				'id'       => 'subscrpt_debug_database',
				'title'    => 'Enable Database Logging',
				'callback' => array( __CLASS__, 'database_logging_field_callback' ),
			),
			array(
				'id'       => 'subscrpt_debug_webhooks',
				'title'    => 'Enable Webhook Logging',
				'callback' => array( __CLASS__, 'webhook_logging_field_callback' ),
			),
			array(
				'id'       => 'subscrpt_debug_payments',
				'title'    => 'Enable Payment Logging',
				'callback' => array( __CLASS__, 'payment_logging_field_callback' ),
			),
			array(
				'id'       => 'subscrpt_debug_backup_logs',
				'title'    => 'Backup Logs',
				'callback' => array( __CLASS__, 'backup_logs_field_callback' ),
			),
		);

		foreach ( $fields as $field ) {
			add_settings_field(
				$field['id'],
				$field['title'],
				$field['callback'],
				'subscrpt_debug_settings',
				'subscrpt_debug_section'
			);
		}
	}

	/**
	 * Debug section callback
	 *
	 * @return void
	 */
	public static function debug_section_callback() {
		echo '<p>Configure debug settings for the auto-renewal payment system.</p>';
	}

	/**
	 * Log level field callback
	 *
	 * @return void
	 */
	public static function log_level_field_callback() {
		$value = get_option( 'subscrpt_debug_log_level', 'info' );
		$levels = array(
			'debug'   => 'Debug (All messages)',
			'info'    => 'Info (Default)',
			'warning' => 'Warning (Warnings and errors only)',
			'error'   => 'Error (Errors only)',
		);

		echo '<select name="subscrpt_debug_log_level">';
		foreach ( $levels as $level => $label ) {
			$selected = selected( $value, $level, false );
			echo "<option value='{$level}' {$selected}>{$label}</option>";
		}
		echo '</select>';
	}

	/**
	 * Max log size field callback
	 *
	 * @return void
	 */
	public static function max_log_size_field_callback() {
		$value = get_option( 'subscrpt_debug_max_log_size', 10485760 );
		echo "<input type='number' name='subscrpt_debug_max_log_size' value='{$value}' min='1048576' step='1048576' />";
		echo '<p class="description">Maximum log file size in bytes (default: 10MB)</p>';
	}

	/**
	 * Log retention field callback
	 *
	 * @return void
	 */
	public static function log_retention_field_callback() {
		$value = get_option( 'subscrpt_debug_log_retention_days', 30 );
		echo "<input type='number' name='subscrpt_debug_log_retention_days' value='{$value}' min='1' max='365' />";
		echo '<p class="description">Number of days to keep log files (default: 30)</p>';
	}

	/**
	 * Performance logging field callback
	 *
	 * @return void
	 */
	public static function performance_logging_field_callback() {
		$value = get_option( 'subscrpt_debug_performance', true );
		$checked = checked( $value, true, false );
		echo "<input type='checkbox' name='subscrpt_debug_performance' value='1' {$checked} />";
		echo '<p class="description">Log performance metrics and execution times</p>';
	}

	/**
	 * Database logging field callback
	 *
	 * @return void
	 */
	public static function database_logging_field_callback() {
		$value = get_option( 'subscrpt_debug_database', true );
		$checked = checked( $value, true, false );
		echo "<input type='checkbox' name='subscrpt_debug_database' value='1' {$checked} />";
		echo '<p class="description">Log database operations and queries</p>';
	}

	/**
	 * Webhook logging field callback
	 *
	 * @return void
	 */
	public static function webhook_logging_field_callback() {
		$value = get_option( 'subscrpt_debug_webhooks', true );
		$checked = checked( $value, true, false );
		echo "<input type='checkbox' name='subscrpt_debug_webhooks' value='1' {$checked} />";
		echo '<p class="description">Log webhook processing and events</p>';
	}

	/**
	 * Payment logging field callback
	 *
	 * @return void
	 */
	public static function payment_logging_field_callback() {
		$value = get_option( 'subscrpt_debug_payments', true );
		$checked = checked( $value, true, false );
		echo "<input type='checkbox' name='subscrpt_debug_payments' value='1' {$checked} />";
		echo '<p class="description">Log payment processing and transactions</p>';
	}

	/**
	 * Backup logs field callback
	 *
	 * @return void
	 */
	public static function backup_logs_field_callback() {
		$value = get_option( 'subscrpt_debug_backup_logs', true );
		$checked = checked( $value, true, false );
		echo "<input type='checkbox' name='subscrpt_debug_backup_logs' value='1' {$checked} />";
		echo '<p class="description">Create backup copies of log files</p>';
	}

	/**
	 * Clean up old log files
	 *
	 * @return void
	 */
	public static function cleanup_old_logs() {
		if ( ! self::is_debug_enabled() ) {
			return;
		}

		$retention_days = self::get( 'log_retention_days', 30 );
		$cutoff_date = date( 'Y-m-d', strtotime( "-{$retention_days} days" ) );

		$log_file = self::get_log_file_path();
		$backup_file = self::get_backup_log_file_path();

		// Clean up main log file
		if ( file_exists( $log_file ) ) {
			$file_time = filemtime( $log_file );
			if ( $file_time < strtotime( $cutoff_date ) ) {
				unlink( $log_file );
			}
		}

		// Clean up backup log file
		if ( file_exists( $backup_file ) ) {
			$file_time = filemtime( $backup_file );
			if ( $file_time < strtotime( $cutoff_date ) ) {
				unlink( $backup_file );
			}
		}
	}

	/**
	 * Rotate log file if too large
	 *
	 * @return void
	 */
	public static function rotate_log_if_needed() {
		if ( ! self::is_debug_enabled() ) {
			return;
		}

		$log_file = self::get_log_file_path();
		$max_size = self::get( 'max_log_size', 10485760 );

		if ( file_exists( $log_file ) && filesize( $log_file ) > $max_size ) {
			$backup_file = self::get_backup_log_file_path();
			
			// Move current log to backup
			if ( file_exists( $backup_file ) ) {
				unlink( $backup_file );
			}
			
			rename( $log_file, $backup_file );
			
			// Create new log file
			file_put_contents( $log_file, '' );
		}
	}

	/**
	 * Get all settings
	 *
	 * @return array All debug settings
	 */
	public static function get_all_settings() {
		return self::$settings;
	}

	/**
	 * Reset settings to defaults
	 *
	 * @return void
	 */
	public static function reset_to_defaults() {
		$defaults = array(
			'subscrpt_debug_log_level'            => 'info',
			'subscrpt_debug_max_log_size'         => 10485760,
			'subscrpt_debug_log_retention_days'   => 30,
			'subscrpt_debug_performance'          => true,
			'subscrpt_debug_database'             => true,
			'subscrpt_debug_webhooks'             => true,
			'subscrpt_debug_payments'             => true,
			'subscrpt_debug_backup_logs'          => true,
		);

		foreach ( $defaults as $option => $value ) {
			update_option( $option, $value );
		}

		// Reinitialize settings
		self::init();
	}
}
