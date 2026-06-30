<?php
/**
 * Subscription cancellation handler.
 *
 * Owns the conversion of a pending-cancellation (`pe_cancelled`) subscription
 * into a fully `cancelled` one. This is intentionally kept separate from the
 * hourly *expiry* check in {@see Cron} so cancellation-related behaviour can grow
 * here independently.
 *
 * Default flow (free): when a subscription enters `pe_cancelled` it is scheduled
 * to be cancelled 24 hours later. The exact moment is filterable via
 * `subscrpt_cancellation_time`, which the Pro plugin uses to offer "immediately",
 * "after 24 hours", or "at the end of the billing period".
 *
 * @package SpringDevs\Subscription\Illuminate
 */

namespace SpringDevs\Subscription\Illuminate;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Class Cancellation
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Cancellation {

	/**
	 * Post meta storing the timestamp at which a pending cancellation becomes final.
	 *
	 * @var string
	 */
	const CANCEL_AT_META = '_subscrpt_cancel_at';

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'subscrpt_subscription_pending_cancellation', [ $this, 'schedule_cancellation' ] );
		add_action( 'subscrpt_hourly_cron', [ $this, 'process_due_cancellations' ] );
		add_action( 'subscrpt_subscription_resumed', [ $this, 'clear_scheduled_cancellation' ] );
		add_action( 'before_single_subscrpt_content', [ $this, 'display_pending_cancellation_notice' ] );
	}

	/**
	 * Get Settings
	 *
	 * @param string $id Setting ID.
	 */
	public static function get_settings( $id = '' ) {
		$settings = [
			'subscrpt_cancellation_delay' => subscrpt_pro_activated() ? get_option( 'subscrpt_cancellation_delay', '24h' ) : '24h',
		];
		return ! empty( $id ) ? $settings[ $id ] ?? false : $settings;
	}

	/**
	 * Record when a pending cancellation should become final.
	 *
	 * Runs whenever a subscription enters `pe_cancelled` (frontend, admin, or REST).
	 * If the resolved time is already due, the subscription is cancelled immediately.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public function schedule_cancellation( $subscription_id ) {
		$subscription_id = (int) $subscription_id;

		/**
		 * Filter the timestamp at which a pending cancellation becomes a full cancellation.
		 *
		 * Return a Unix timestamp. A value at or before the current time cancels the
		 * subscription immediately. Defaults to 24 hours from now.
		 *
		 * @param int $cancel_at       Unix timestamp for final cancellation.
		 * @param int $subscription_id Subscription ID.
		 */
		$cancel_at = (int) apply_filters( 'subscrpt_cancellation_time', time() + DAY_IN_SECONDS, $subscription_id );

		if ( $cancel_at <= time() ) {
			$this->cancel( $subscription_id );
			return;
		}

		update_post_meta( $subscription_id, self::CANCEL_AT_META, $cancel_at );
	}

	/**
	 * Hourly sweep: finalise any pending cancellations whose time has come.
	 *
	 * Picks up subscriptions whose `_subscrpt_cancel_at` is due, plus legacy
	 * `pe_cancelled` subscriptions (created before this meta existed) whose billing
	 * period has ended.
	 *
	 * @return void
	 */
	public function process_due_cancellations() {
		$subscriptions = get_posts(
			[
				'post_type'   => 'subscrpt_order',
				'post_status' => [ 'pe_cancelled' ],
				'fields'      => 'ids',
				'numberposts' => -1,
				'meta_query'  => [
					'relation' => 'OR',
					[
						'key'     => self::CANCEL_AT_META,
						'value'   => time(),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					],
					[
						'relation' => 'AND',
						[
							'key'     => self::CANCEL_AT_META,
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => '_subscrpt_next_date',
							'value'   => time(),
							'compare' => '<=',
							'type'    => 'NUMERIC',
						],
					],
				],
			]
		);

		if ( empty( $subscriptions ) ) {
			return;
		}

		// Ensure the mailer is ready so the cancellation email can be sent.
		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			foreach ( $subscriptions as $subscription_id ) {
				$this->cancel( (int) $subscription_id );
			}
		}
	}

	/**
	 * Finalise the cancellation of a single subscription.
	 *
	 * Guards against subscriptions that are no longer pending (e.g. reactivated).
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public function cancel( $subscription_id ) {
		$subscription_id = (int) $subscription_id;

		if ( 'pe_cancelled' === get_post_status( $subscription_id ) ) {
			Action::status( 'cancelled', $subscription_id );
		}

		delete_post_meta( $subscription_id, self::CANCEL_AT_META );
	}

	/**
	 * Drop a scheduled cancellation when a subscription is reactivated.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public function clear_scheduled_cancellation( $subscription_id ) {
		delete_post_meta( (int) $subscription_id, self::CANCEL_AT_META );
	}

	/**
	 * Show a notice on the subscription details page when a cancellation is pending.
	 *
	 * Uses `_subscrpt_cancel_at` (the resolved final-cancellation time), falling back
	 * to the next renewal date for legacy subscriptions.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public function display_pending_cancellation_notice( $subscription_id ) {
		if ( 'pe_cancelled' !== get_post_status( $subscription_id ) ) {
			return;
		}

		$cancel_at = (int) get_post_meta( $subscription_id, self::CANCEL_AT_META, true );
		if ( ! $cancel_at ) {
			$cancel_at = (int) get_post_meta( $subscription_id, '_subscrpt_next_date', true );
		}

		wp_enqueue_style( 'subscrpt_cancellation_css', SUBSCRPT_ASSETS . '/css/cancellation.css', [], SUBSCRPT_VERSION );
		?>
		<div class="subscrpt-pending-cancel-notice" role="status">
			<span class="subscrpt-pending-cancel-notice__icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
			</span>
			<div class="subscrpt-pending-cancel-notice__body">
				<p class="subscrpt-pending-cancel-notice__text">
					<?php
					if ( $cancel_at ) {
						$effective = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cancel_at );
						printf(
							/* translators: %s: cancellation date and time. */
							esc_html__( 'This subscription is scheduled to be cancelled on %s. You can continue accessing it until then.', 'subscription' ),
							'<strong>' . esc_html( $effective ) . '</strong>'
						);
					} else {
						esc_html_e( 'This subscription is scheduled to be cancelled at the end of the current billing period. You can continue accessing it until then.', 'subscription' );
					}
					?>
				</p>
			</div>
		</div>
		<?php
	}
}
