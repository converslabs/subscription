<?php

namespace SpringDevs\Subscription\Illuminate\Gateways\Paypal;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Paypal Blocks Integration Class
 * This class integrates the PayPal payment gateway with WooCommerce Blocks.
 *
 * @package SpringDevs\Subscription\Illuminate\Gateways\Paypal
 */
final class Paypal_Blocks_Integration extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var Paypal
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'wp_subscription_paypal';

	/**
	 * Initialize the gateway.
	 *
	 * @return void
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_wp_subscription_paypal_settings', [] );
		$this->gateway  = new Paypal();
	}

	/**
	 * Check if the gateway is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Get the payment method title.
	 *
	 * @return string
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'wp_subscription_paypal-blocks-integration',
			WP_SUBSCRIPTION_URL . '/assets/js/wp_subscription_paypal-block.js',
			[ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ],
			1,
			true
		);
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wp_subscription_paypal-blocks-integration', 'wp_subscription', WP_SUBSCRIPTION_PATH . '/languages' );
		}

		return array( 'wp_subscription_paypal-blocks-integration' );
	}

	/**
	 * Get the payment method title.
	 *
	 * @return string
	 */
	public function get_payment_method_data() {
		return [
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
			'icon'        => $this->gateway->icon,
			'features'    => $this->gateway->supports,
		];
	}
}
