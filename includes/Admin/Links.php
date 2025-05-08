<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Plugin action links
 *
 * Class Links
 *
 * @package SpringDevs\Subscription\Admin
 */
class Links {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_filter( 'plugin_action_links_' . plugin_basename( WP_SUBSCRIPTION_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add plugin action links
	 *
	 * @param array $links Plugin Links.
	 */
	public function plugin_action_links( $links ) {
		if ( ! wp_subscription_pro_activated() ) {
			$links[] = '<a href="https://wpsubscription.co" target="_blank" style="color:#3db634;">' . __( 'Upgrade to premium', 'wp_subscription' ) . '</a>';
		}
		$links[] = '<a href="https://wordpress.org/support/plugin/subscription" target="_blank">' . __( 'Support', 'wp_subscription' ) . '</a>';
		$links[] = '<a href="https://wordpress.org/support/plugin/subscription/reviews/?rate=5#new-post" target="_blank">' . __( 'Review', 'wp_subscription' ) . '</a>';
		return $links;
	}
}
