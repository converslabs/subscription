<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Cron
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Cron {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'subscrpt_daily_cron', array( $this, 'daily_cron_task' ) );
	}

	/**
	 * Run daily cron task to check if subscription expired.
	 */
	public function daily_cron_task() {
		// Trigger after cron
		do_action( 'before_subscription_update_cron' );

		// Expire subscriptions
		$this->update_subscription_statusses();

		// Trigger after cron
		do_action( 'after_subscription_update_cron' );
	}

	/**
	 * Update subscription statusses
	 */
	public function update_subscription_statusses() {
		$args = array(
			'post_type'   => 'subscrpt_order',
			'post_status' => array( 'active', 'pe_cancelled' ),
			'fields'      => 'ids',
			'meta_query'  => array(
				'relation' => 'OR',
				array(
					'key'     => '_subscrpt_next_date',
					'value'   => time(),
					'compare' => '<=',
				),
				array(
					'relation' => 'AND',
					array(
						'key'     => '_subscrpt_trial',
						'value'   => null,
						'compare' => '!=',
					),
					array(
						'key'     => '_subscrpt_start_date',
						'value'   => time(),
						'compare' => '<=',
					),
				),
			),
		);

		$expired_subscriptions = get_posts( $args );

		if ( $expired_subscriptions && count( $expired_subscriptions ) > 0 ) {
			// Initialize WooCommerce mailer before processing
			if ( function_exists( 'WC' ) && WC()->mailer() ) {
				foreach ( $expired_subscriptions as $subscription ) {
					if ( 'pe_cancelled' === get_post_status( $subscription ) ) {
						Action::status( 'cancelled', $subscription );
					} else {
						Action::status( 'expired', $subscription );
					}
				}
			}
		}
	}
}
