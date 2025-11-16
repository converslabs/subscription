<?php
/**
 * Plugin Name - WooCommerce Subscriptions Compat Bootstrap Class
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compat\WooSubscriptions;

use SpringDevs\Subscription\Compat\WooSubscriptions\Data\SyncService;
use SpringDevs\Subscription\Compat\WooSubscriptions\Data\ActionScheduler;
use SpringDevs\Subscription\Compat\WooSubscriptions\Hooks\HookRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Initialize the WooCommerce Subscriptions compatibility layer.
 *
 * @since 1.0.0
 */
class Bootstrap {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Bootstrap
	 */
	private static $instance;

	/**
	 * Instantiate the bootstrapper.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		if ( $this->should_abort() ) {
			return;
		}

		$this->register_hooks();
		$this->register_services();
		$this->load_facades();
	}

	/**
	 * Retrieve the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Bootstrap
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Determine whether the compatibility layer should abort loading.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function should_abort() {
		if ( class_exists( 'WC_Subscription', false ) ) {
			return true;
		}

		if ( defined( 'WPS_DISABLE_WCS_COMPAT' ) && true === WPS_DISABLE_WCS_COMPAT ) {
			return true;
		}

		return false;
	}

	/**
	 * Register core compatibility hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_hooks() {
		HookRegistry::instance()->register();
	}

	/**
	 * Register compatibility services.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_services() {
		SyncService::instance();
		ActionScheduler::instance();
		$this->register_gateway_adapters();
	}

	/**
	 * Register gateway adapter classes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_gateway_adapters() {
		// Initialize Stripe adapter.
		\SpringDevs\Subscription\Compat\WooSubscriptions\Gateways\StripeAdapter::instance();
	}

	/**
	 * Ensure global facades are available.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_facades() {
		// Facade is autoloaded via Composer files list.
	}
}
