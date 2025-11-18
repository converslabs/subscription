<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Admin\Integrations;
use SpringDevs\Subscription\Admin\Required;
use SpringDevs\Subscription\Admin\Links;
use SpringDevs\Subscription\Admin\Menu;
use SpringDevs\Subscription\Admin\Order as AdminOrder;
use SpringDevs\Subscription\Admin\Product;
use SpringDevs\Subscription\Admin\ProductList;
use SpringDevs\Subscription\Admin\Settings;
use SpringDevs\Subscription\Admin\Subscriptions;
use SpringDevs\Subscription\Illuminate\Comments;

/**
 * The admin class
 */
class Admin {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		$this->dispatch_actions();
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		// Only show required plugins notice if Pro is NOT active
		if ( ! is_plugin_active( 'subscription-pro/subscription-pro.php' ) ) {
			if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				// Show required notice only, do not load other admin content
				new Required();
				return;
			} else {
				new Required();
			}
		}
		// Only load admin content if WooCommerce is active
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			new Menu();
			new Integrations();
			new Product();
			new ProductList();
			new Subscriptions();
			new AdminOrder();
			new Comments();
			new Settings();
			new Links();
		}
	}

	/**
	 * Dispatch and bind actions
	 *
	 * @return void
	 */
	public function dispatch_actions() {
	}
}
