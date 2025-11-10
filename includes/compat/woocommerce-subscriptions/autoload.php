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

		$filename = str_replace( '_', '-', $filename );
		$filename = preg_replace( '/(?<!^)([A-Z])/', '-$1', $filename );
		$filename = strtolower( $filename );
		$filename = preg_replace( '/-+/', '-', $filename );

		$directories = array();

		foreach ( $parts as $part ) {
			$part = str_replace( '_', '-', $part );
			$part = preg_replace( '/(?<!^)([A-Z])/', '-$1', $part );
			$part = strtolower( $part );
			$part = preg_replace( '/-+/', '-', $part );

			$directories[] = $part;
		}

		$path = implode( '/', $directories );

		$file = __DIR__ . '/' . ( $path ? $path . '/' : '' ) . 'class-' . $filename . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);
