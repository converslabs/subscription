<?php
/**
 * Plugin Name - WooCommerce Subscriptions Compat Autoloader
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register(
	static function ( $class ) {
		if ( strpos( $class, 'SpringDevs\\Subscription\\Compat\\WooSubscriptions\\' ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( 'SpringDevs\\Subscription\\Compat\\WooSubscriptions\\' ) );
		$relative = str_replace( '\\', '/', $relative );
		$parts    = explode( '/', $relative );
		$filename = array_pop( $parts );
		$filename = 'class-' . strtolower( str_replace( '_', '-', $filename ) ) . '.php';

		$directories = array();

		foreach ( $parts as $part ) {
			$directories[] = strtolower( str_replace( '_', '-', $part ) );
		}

		$path = implode( '/', $directories );

		$file = __DIR__ . '/' . ( $path ? $path . '/' : '' ) . $filename;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
