<?php
/**
 * Webhook Handler
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Gateways;

/**
 * WebhookHandler class.
 *
 * Handles payment gateway webhooks.
 *
 * @package SpringDevs\Subscription\Compatibility\Gateways
 * @since   1.0.0
 */
class WebhookHandler {

	/**
	 * Initialize webhook handlers.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Gateways handle their own webhooks.
		// This class can provide additional coordination if needed.
	}
}
