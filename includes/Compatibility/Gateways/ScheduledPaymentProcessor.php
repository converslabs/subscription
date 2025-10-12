<?php
/**
 * Scheduled Payment Processor
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Gateways;

/**
 * ScheduledPaymentProcessor class.
 *
 * Processes scheduled renewal payments.
 *
 * @package SpringDevs\Subscription\Compatibility\Gateways
 * @since   1.0.0
 */
class ScheduledPaymentProcessor {

	/**
	 * Process scheduled renewal.
	 *
	 * @since 1.0.0
	 * @param int $subscription_id Subscription ID.
	 */
	public static function process_scheduled_renewal( $subscription_id ) {
		// This will be called by WPSubscription's cron system.
		// Create renewal order and trigger payment.
		do_action( 'wpsubscription_process_scheduled_renewal', $subscription_id );
	}
}
