<?php
/**
 * Plugin Name - WooCommerce Subscriptions Action Scheduler Integration
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compat\WooSubscriptions\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps WPSubscription schedule data to Action Scheduler tasks for WooCommerce Subscriptions compatibility.
 *
 * @since 1.0.0
 */
class ActionScheduler {

	/**
	 * Action Scheduler group name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const ACTION_GROUP = 'wc_subscription_scheduled_event';

	/**
	 * Meta key to track if Action Scheduler is enabled for a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	const AS_ENABLED_META = '_wps_wcs_as_enabled';

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var ActionScheduler
	 */
	private static $instance;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return ActionScheduler
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'save_post_subscrpt_order', array( $this, 'schedule_events' ), 30, 2 );
		add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'handle_renewal_payment' ), 10, 1 );
		add_action( 'woocommerce_scheduled_subscription_trial_end', array( $this, 'handle_trial_end' ), 10, 1 );
		add_action( 'woocommerce_scheduled_subscription_expiration', array( $this, 'handle_expiration' ), 10, 1 );
		add_action( 'subscrpt_after_create_renew_order', array( $this, 'reschedule_next_payment' ), 10, 4 );
	}

	/**
	 * Schedule Action Scheduler events for a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Subscription post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function schedule_events( $post_id, $post ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		if ( 'subscrpt_order' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Clear existing scheduled actions for this subscription.
		$this->unschedule_events( $post_id );

		// Only schedule for active subscriptions.
		if ( ! in_array( $post->post_status, array( 'active', 'pe_cancelled' ), true ) ) {
			return;
		}

		$subscription_id = $post_id;

		// Sync start date meta for compatibility.
		$start_date = (int) get_post_meta( $subscription_id, '_subscrpt_start_date', true );
		if ( $start_date ) {
			update_post_meta( $subscription_id, '_schedule_start', gmdate( 'Y-m-d H:i:s', $start_date ) );
			$this->sync_schedule_meta_to_wcs( $subscription_id, '_schedule_start', $start_date );
		}

		// Schedule next payment.
		$next_payment = (int) get_post_meta( $subscription_id, '_subscrpt_next_date', true );
		if ( $next_payment && $next_payment > time() ) {
			as_schedule_single_action(
				$next_payment,
				'woocommerce_scheduled_subscription_payment',
				array( 'subscription_id' => $subscription_id ),
				self::ACTION_GROUP
			);

			// Update schedule meta for compatibility.
			update_post_meta( $subscription_id, '_schedule_next_payment', gmdate( 'Y-m-d H:i:s', $next_payment ) );
			$this->sync_schedule_meta_to_wcs( $subscription_id, '_schedule_next_payment', $next_payment );
		}

		// Schedule trial end if applicable.
		$trial_end = (int) get_post_meta( $subscription_id, '_subscrpt_trial_ended', true );
		if ( $trial_end && $trial_end > time() && $trial_end > $next_payment ) {
			as_schedule_single_action(
				$trial_end,
				'woocommerce_scheduled_subscription_trial_end',
				array( 'subscription_id' => $subscription_id ),
				self::ACTION_GROUP
			);

			update_post_meta( $subscription_id, '_schedule_trial_end', gmdate( 'Y-m-d H:i:s', $trial_end ) );
			$this->sync_schedule_meta_to_wcs( $subscription_id, '_schedule_trial_end', $trial_end );
		}

		// Schedule expiration if applicable.
		$end_date = (int) get_post_meta( $subscription_id, '_subscrpt_end_date', true );
		if ( $end_date && $end_date > time() ) {
			as_schedule_single_action(
				$end_date,
				'woocommerce_scheduled_subscription_expiration',
				array( 'subscription_id' => $subscription_id ),
				self::ACTION_GROUP
			);

			update_post_meta( $subscription_id, '_schedule_end', gmdate( 'Y-m-d H:i:s', $end_date ) );
			$this->sync_schedule_meta_to_wcs( $subscription_id, '_schedule_end', $end_date );
		}

		update_post_meta( $subscription_id, self::AS_ENABLED_META, true );
	}

	/**
	 * Handle scheduled renewal payment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	public function handle_renewal_payment( $subscription_id ) {
		if ( 'subscrpt_order' !== get_post_type( $subscription_id ) ) {
			// If it's a shop_subscription ID, find the WPSubscription ID.
			$wps_id = (int) get_post_meta( $subscription_id, SyncService::WCS_MAP_META_KEY, true );
			if ( $wps_id ) {
				$subscription_id = $wps_id;
			} else {
				return;
			}
		}

		$subscription = get_post( $subscription_id );

		if ( ! $subscription || 'subscrpt_order' !== $subscription->post_type ) {
			return;
		}

		// Only process if subscription is still active.
		if ( ! in_array( $subscription->post_status, array( 'active', 'pe_cancelled' ), true ) ) {
			return;
		}

		/**
		 * Trigger WooCommerce Subscriptions-style renewal payment hook.
		 *
		 * @since 1.0.0
		 *
		 * @param int $subscription_id Subscription ID.
		 */
		do_action( 'wps_wcs_process_renewal_payment', $subscription_id );

		// Reschedule next payment after renewal.
		$this->reschedule_next_payment( null, null, $subscription_id, null );
	}

	/**
	 * Handle scheduled trial end.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	public function handle_trial_end( $subscription_id ) {
		if ( 'subscrpt_order' !== get_post_type( $subscription_id ) ) {
			$wps_id = (int) get_post_meta( $subscription_id, SyncService::WCS_MAP_META_KEY, true );
			if ( $wps_id ) {
				$subscription_id = $wps_id;
			} else {
				return;
			}
		}

		/**
		 * Trigger WooCommerce Subscriptions-style trial end hook.
		 *
		 * @since 1.0.0
		 *
		 * @param int $subscription_id Subscription ID.
		 */
		do_action( 'wps_wcs_subscription_trial_ended', $subscription_id );
	}

	/**
	 * Handle scheduled subscription expiration.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	public function handle_expiration( $subscription_id ) {
		if ( 'subscrpt_order' !== get_post_type( $subscription_id ) ) {
			$wps_id = (int) get_post_meta( $subscription_id, SyncService::WCS_MAP_META_KEY, true );
			if ( $wps_id ) {
				$subscription_id = $wps_id;
			} else {
				return;
			}
		}

		$subscription = get_post( $subscription_id );

		if ( ! $subscription || 'subscrpt_order' !== $subscription->post_type ) {
			return;
		}

		/**
		 * Trigger WooCommerce Subscriptions-style expiration hook.
		 *
		 * @since 1.0.0
		 *
		 * @param int $subscription_id Subscription ID.
		 */
		do_action( 'wps_wcs_subscription_expired', $subscription_id );
	}

	/**
	 * Reschedule next payment after renewal order creation.
	 *
	 * @since 1.0.0
	 *
	 * @param \WC_Order|null $new_order      New renewal order.
	 * @param \WC_Order|null $old_order      Original order.
	 * @param int            $subscription_id Subscription ID.
	 * @param bool|null      $is_early       Whether this is an early renewal.
	 *
	 * @return void
	 */
	public function reschedule_next_payment( $new_order, $old_order, $subscription_id, $is_early ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$subscription = get_post( $subscription_id );

		if ( ! $subscription || 'subscrpt_order' !== $subscription->post_type ) {
			return;
		}

		// Only reschedule for active subscriptions.
		if ( ! in_array( $subscription->post_status, array( 'active', 'pe_cancelled' ), true ) ) {
			return;
		}

		$billing_period  = get_post_meta( $subscription_id, '_subscrpt_billing_period', true );
		$billing_interval = (int) get_post_meta( $subscription_id, '_subscrpt_billing_interval', true );
		$current_next    = (int) get_post_meta( $subscription_id, '_subscrpt_next_date', true );

		if ( ! $billing_period || ! $billing_interval || ! $current_next ) {
			return;
		}

		// Calculate next payment date.
		$next_timestamp = $this->calculate_next_payment( $current_next, $billing_period, $billing_interval );

		if ( $next_timestamp && $next_timestamp > time() ) {
			// Unschedule old next payment.
			as_unschedule_all_actions(
				'woocommerce_scheduled_subscription_payment',
				array( 'subscription_id' => $subscription_id ),
				self::ACTION_GROUP
			);

			// Schedule new next payment.
			as_schedule_single_action(
				$next_timestamp,
				'woocommerce_scheduled_subscription_payment',
				array( 'subscription_id' => $subscription_id ),
				self::ACTION_GROUP
			);

			// Update next payment meta.
			update_post_meta( $subscription_id, '_subscrpt_next_date', $next_timestamp );
			update_post_meta( $subscription_id, '_schedule_next_payment', gmdate( 'Y-m-d H:i:s', $next_timestamp ) );
			$this->sync_schedule_meta_to_wcs( $subscription_id, '_schedule_next_payment', $next_timestamp );
		}
	}

	/**
	 * Sync schedule meta to the mirrored WooCommerce subscription post.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $subscription_id WPSubscription ID.
	 * @param string $meta_key        Schedule meta key (e.g., '_schedule_next_payment').
	 * @param int    $timestamp       Unix timestamp.
	 *
	 * @return void
	 */
	private function sync_schedule_meta_to_wcs( $subscription_id, $meta_key, $timestamp ) {
		$wcs_id = (int) get_post_meta( $subscription_id, SyncService::MAP_META_KEY, true );

		if ( $wcs_id > 0 ) {
			update_post_meta( $wcs_id, $meta_key, $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '' );
		}
	}

	/**
	 * Calculate next payment timestamp.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $current_timestamp Current next payment timestamp.
	 * @param string $billing_period    Billing period (day, week, month, year).
	 * @param int    $billing_interval  Billing interval.
	 *
	 * @return int|null
	 */
	private function calculate_next_payment( $current_timestamp, $billing_period, $billing_interval ) {
		if ( ! $current_timestamp || ! $billing_period || ! $billing_interval ) {
			return null;
		}

		$period_map = array(
			'day'   => 'days',
			'week'  => 'weeks',
			'month' => 'months',
			'year'  => 'years',
		);

		$period = isset( $period_map[ $billing_period ] ) ? $period_map[ $billing_period ] : 'months';

		return strtotime( '+' . $billing_interval . ' ' . $period, $current_timestamp );
	}

	/**
	 * Unschedule all Action Scheduler events for a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	private function unschedule_events( $subscription_id ) {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		$action_args = array( 'subscription_id' => $subscription_id );

		as_unschedule_all_actions( 'woocommerce_scheduled_subscription_payment', $action_args, self::ACTION_GROUP );
		as_unschedule_all_actions( 'woocommerce_scheduled_subscription_trial_end', $action_args, self::ACTION_GROUP );
		as_unschedule_all_actions( 'woocommerce_scheduled_subscription_expiration', $action_args, self::ACTION_GROUP );
	}
}

