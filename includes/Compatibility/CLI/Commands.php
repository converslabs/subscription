<?php
/**
 * WP-CLI Commands
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\CLI;

use SpringDevs\Subscription\Compatibility\Bootstrap;
use SpringDevs\Subscription\Compatibility\Utils\CompatibilityChecker;

/**
 * WP-CLI commands for WPSubscription compatibility layer.
 *
 * @package SpringDevs\Subscription\Compatibility\CLI
 * @since   1.0.0
 */
class Commands {

	/**
	 * Test WooCommerce Subscriptions compatibility layer.
	 *
	 * ## OPTIONS
	 *
	 * [--component=<component>]
	 * : Test specific component (all, functions, classes, hooks, gateways)
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpsubscription compat test
	 *     wp wpsubscription compat test --component=functions
	 *
	 * @since  1.0.0
	 * @param  array $args Command arguments.
	 * @param  array $assoc_args Command associative arguments.
	 * @return void
	 */
	public function test( $args, $assoc_args ) {
		$component = isset( $assoc_args['component'] ) ? $assoc_args['component'] : 'all';

		\WP_CLI::log( 'Running WPSubscription compatibility tests...' );
		\WP_CLI::log( '' );

		$results = CompatibilityChecker::run_all_checks();

		foreach ( $results as $name => $result ) {
			if ( 'all' !== $component && $name !== $component ) {
				continue;
			}

			$icon  = $result['passed'] ? '✓' : '✗';
			$color = $result['passed'] ? '%G' : '%R';

			\WP_CLI::log(
				\WP_CLI::colorize(
					sprintf(
						'%s [%s%s%%n] %s: %s',
						$icon,
						$color,
						strtoupper( $name ),
						$result['message']
					)
				)
			);
		}

		\WP_CLI::log( '' );

		$passed = array_filter(
			$results,
			function ( $r ) {
				return $r['passed'];
			}
		);

		if ( count( $passed ) === count( $results ) ) {
			\WP_CLI::success( 'All compatibility tests passed!' );
		} else {
			\WP_CLI::error( sprintf( '%d/%d tests failed', count( $results ) - count( $passed ), count( $results ) ) );
		}
	}

	/**
	 * Show compatibility status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpsubscription compat status
	 *
	 * @since  1.0.0
	 * @param  array $args Command arguments.
	 * @param  array $assoc_args Command associative arguments.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		$status = Bootstrap::get_status();

		\WP_CLI::log( 'WPSubscription Compatibility Status:' );
		\WP_CLI::log( sprintf( '  Version: %s', $status['version'] ) );
		\WP_CLI::log( sprintf( '  Healthy: %s', $status['is_healthy'] ? 'Yes' : 'No' ) );
		\WP_CLI::log( '' );

		if ( ! empty( $status['loaded_components'] ) ) {
			\WP_CLI::log( 'Loaded Components:' );
			foreach ( $status['loaded_components'] as $key => $value ) {
				if ( is_bool( $value ) ) {
					\WP_CLI::log( sprintf( '  %s: %s', $key, $value ? 'Yes' : 'No' ) );
				} else {
					\WP_CLI::log( sprintf( '  %s: %s', $key, $value ) );
				}
			}
		}

		if ( ! empty( $status['errors'] ) ) {
			\WP_CLI::log( '' );
			\WP_CLI::warning( 'Errors:' );
			foreach ( $status['errors'] as $error ) {
				\WP_CLI::log( sprintf( '  - %s', $error ) );
			}
		}
	}
}

// Register WP-CLI commands if WP-CLI is available.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'wpsubscription compat', __NAMESPACE__ . '\\Commands' );
}
