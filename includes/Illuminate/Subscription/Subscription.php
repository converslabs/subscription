<?php
/**
 * Main subscription class.
 *
 * TODO: Refactor and move all subscription related logic from Helper class into this class.
 *
 * @package Subscription
 */

namespace SpringDevs\Subscription\Illuminate\Subscription;

/**
 * Class Subscription
 *
 * @package Illuminate\Subscription
 */
class Subscription {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_settings_advanced', [ $this, 'add_subscription_permalink_settings' ] );
	}

	/**
	 * Add subscription permalink settings to WooCommerce settings.
	 *
	 * @param array $settings Existing WooCommerce advanced settings.
	 */
	public function add_subscription_permalink_settings( $settings ) {
		$subscription_settings = [
			[
				'title' => __( 'Subscription Endpoints', 'wp_subscription' ),
				'type'  => 'title',
				'desc'  => __( 'Configure the permalinks for WPSubscription\'s subscription-related pages.', 'wp_subscription' ),
				'id'    => 'wpsubs_permalinks_options',
			],
			[
				'title'    => __( 'Subscriptions', 'wp_subscription' ),
				'id'       => 'wpsubs_custom_subscriptions_endpoint',
				'type'     => 'text',
				'default'  => 'subscription',
				'desc_tip' => __( 'Endpoint for the "my-account -> subscriptions" page.', 'wp_subscription' ),
			],
			[
				'title'    => __( 'View Subscription', 'wp_subscription' ),
				'id'       => 'wpsubs_custom_view_subscription_endpoint',
				'type'     => 'text',
				'default'  => 'view-subscription',
				'desc_tip' => __( 'Endpoint for the "my-account -> subscriptions -> view-subscription" page.', 'wp_subscription' ),
			],
			[
				'type' => 'sectionend',
				'id'   => 'wpsubs_permalinks_options',
			],
		];

		return array_merge( $settings, $subscription_settings );
	}
}
