<?php
/**
 * Plugin Name - WooCommerce Subscriptions Compat Bootstrap
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

use SpringDevs\Subscription\Compat\WooSubscriptions\Bootstrap;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/compat-functions.php';

Bootstrap::init();
