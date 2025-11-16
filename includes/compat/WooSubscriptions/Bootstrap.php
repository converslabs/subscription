<?php
/**
 * Plugin Name - WooCommerce Subscriptions Compat Bootstrap Loader
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

use SpringDevs\Subscription\Compat\WooSubscriptions\Bootstrap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

Bootstrap::init();
