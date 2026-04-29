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
		add_action( 'subscrpt_hourly_cron', array( $this, 'hourly_cron_task' ) );

		// ? Dev Note: This is a backward compatibility measure. Remove following action and maybe_reschedule_cron() method after 1 Jan, 2027.
		// Safety net: if the old WP-Cron event fires before migration clears it, still process subscriptions.
		add_action( 'subscrpt_daily_cron', array( $this, 'hourly_cron_task' ) );
		$this->maybe_reschedule_cron();
	}

	/**
	 * Migrate to subscrpt_hourly_cron and ensure correct interval.
	 *
	 * Skips rescheduling when running inside wp-cron.php (DOING_CRON) to avoid
	 * a race condition where WP temporarily removes an event from the queue before
	 * firing it — without the guard, we would reschedule at midnight and corrupt
	 * WP-Cron's own next-run timestamp.
	 *
	 * @return void
	 */
	private function maybe_reschedule_cron() {
		// Remove legacy event name — no-op once migration is complete.
		wp_clear_scheduled_hook( 'subscrpt_daily_cron' );

		// Do not interfere with WP-Cron's own event lifecycle when running via wp-cron.php.
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return;
		}

		$event = wp_get_scheduled_event( 'subscrpt_hourly_cron' );
		if ( $event && 'hourly' === $event->schedule ) {
			return;
		}

		wp_clear_scheduled_hook( 'subscrpt_hourly_cron' );
		wp_schedule_event( strtotime( 'tomorrow midnight' ), 'hourly', 'subscrpt_hourly_cron' );
	}

	/**
	 * Run hourly cron task to check if subscriptions have expired.
	 */
	public function hourly_cron_task() {
		do_action( 'before_subscription_update_cron' );

		$this->update_subscription_statusses();

		do_action( 'after_subscription_update_cron' );
	}

	/**
	 * Update subscription statuses.
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
