<?php
/**
 * WooCommerce Subscriptions Subscription Data Store Compatibility
 *
 * This class provides compatibility with WooCommerce Subscriptions subscription data store
 * by mapping it to WPSubscription's data structure.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Compatibility\DataStores;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscription data store compatibility class
 */
class SubscriptionDataStore extends \WC_Order_Data_Store_CPT {

	/**
	 * Data stored in meta keys, passed as $this->internal_meta_keys.
	 *
	 * @var array
	 */
	protected $internal_meta_keys = array(
		'_billing_period',
		'_billing_interval',
		'_suspension_count',
		'_requires_manual_renewal',
		'_cancelled_email_sent',
		'_trial_period',
		'_last_order_date_created',
		'_schedule_trial_end',
		'_schedule_next_payment',
		'_schedule_cancelled',
		'_schedule_end',
		'_schedule_payment_retry',
		'_schedule_start',
		'_switch_data',
		'_payment_count',
		'_parent_order_id',
		'_wcs_original_id',
	);

	/**
	 * Get order type
	 *
	 * @param \WC_Order $order Order object
	 * @return string
	 */
	public function get_order_type( $order ) {
		return 'shop_subscription';
	}

	/**
	 * Read subscription data
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @return void
	 */
	public function read( &$subscription ) {
		parent::read( $subscription );
		
		// Read subscription-specific data
		$subscription->set_billing_period( $subscription->get_meta( '_billing_period', true ) ?: 'month' );
		$subscription->set_billing_interval( (int) $subscription->get_meta( '_billing_interval', true ) ?: 1 );
		$subscription->set_suspension_count( (int) $subscription->get_meta( '_suspension_count', true ) ?: 0 );
		$subscription->set_requires_manual_renewal( 'true' === $subscription->get_meta( '_requires_manual_renewal', true ) );
		$subscription->set_cancelled_email_sent( 'true' === $subscription->get_meta( '_cancelled_email_sent', true ) );
		$subscription->set_trial_period( $subscription->get_meta( '_trial_period', true ) ?: '' );
		$subscription->set_switch_data( $subscription->get_meta( '_switch_data', true ) ?: array() );
		$subscription->set_payment_count( (int) $subscription->get_meta( '_payment_count', true ) ?: 0 );
	}

	/**
	 * Save subscription data
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @return void
	 */
	public function update( &$subscription ) {
		parent::update( $subscription );
		
		// Save subscription-specific data
		$subscription->update_meta_data( '_billing_period', $subscription->get_billing_period() );
		$subscription->update_meta_data( '_billing_interval', $subscription->get_billing_interval() );
		$subscription->update_meta_data( '_suspension_count', $subscription->get_suspension_count() );
		$subscription->update_meta_data( '_requires_manual_renewal', $subscription->get_requires_manual_renewal() ? 'true' : 'false' );
		$subscription->update_meta_data( '_cancelled_email_sent', $subscription->get_cancelled_email_sent() ? 'true' : 'false' );
		$subscription->update_meta_data( '_trial_period', $subscription->get_trial_period() );
		$subscription->update_meta_data( '_switch_data', $subscription->get_switch_data() );
		$subscription->update_meta_data( '_payment_count', $subscription->get_payment_count() );
	}

	/**
	 * Create subscription
	 *
	 * @param \WC_Subscription $subscription Subscription object
	 * @return void
	 */
	public function create( &$subscription ) {
		parent::create( $subscription );
		
		// Set subscription-specific data
		$subscription->set_billing_period( $subscription->get_billing_period() ?: 'month' );
		$subscription->set_billing_interval( $subscription->get_billing_interval() ?: 1 );
		$subscription->set_suspension_count( $subscription->get_suspension_count() ?: 0 );
		$subscription->set_requires_manual_renewal( $subscription->get_requires_manual_renewal() ?: true );
		$subscription->set_cancelled_email_sent( $subscription->get_cancelled_email_sent() ?: false );
		$subscription->set_trial_period( $subscription->get_trial_period() ?: '' );
		$subscription->set_switch_data( $subscription->get_switch_data() ?: array() );
		$subscription->set_payment_count( $subscription->get_payment_count() ?: 0 );
	}

	/**
	 * Get subscription by order
	 *
	 * @param int $order_id Order ID
	 * @return int|false
	 */
	public function get_subscription_by_order( $order_id ) {
		$subscriptions = get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => '_parent_order_id',
					'value' => $order_id,
				),
			),
		) );

		return ! empty( $subscriptions ) ? $subscriptions[0]->ID : false;
	}

	/**
	 * Get subscriptions by customer
	 *
	 * @param int $customer_id Customer ID
	 * @param string $status Subscription status
	 * @return array
	 */
	public function get_subscriptions_by_customer( $customer_id, $status = 'any' ) {
		$args = array(
			'post_type'      => 'shop_subscription',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_customer_user',
					'value' => $customer_id,
				),
			),
		);

		if ( 'any' !== $status ) {
			$args['post_status'] = $status;
		}

		$subscriptions = get_posts( $args );
		$subscription_ids = array();

		foreach ( $subscriptions as $subscription_post ) {
			$subscription_ids[] = $subscription_post->ID;
		}

		return $subscription_ids;
	}

	/**
	 * Get subscription count by status
	 *
	 * @param string $status Subscription status
	 * @return int
	 */
	public function get_subscription_count_by_status( $status ) {
		$subscriptions = get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => $status,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		return count( $subscriptions );
	}

	/**
	 * Get total subscription count
	 *
	 * @return int
	 */
	public function get_total_subscription_count() {
		$subscriptions = get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		return count( $subscriptions );
	}

	/**
	 * Get subscription revenue
	 *
	 * @param string $status Subscription status
	 * @return float
	 */
	public function get_subscription_revenue( $status = 'wc-active' ) {
		$subscriptions = get_posts( array(
			'post_type'      => 'shop_subscription',
			'post_status'    => $status,
			'posts_per_page' => -1,
		) );

		$revenue = 0;
		foreach ( $subscriptions as $subscription_post ) {
			$subscription = wc_get_order( $subscription_post->ID );
			if ( $subscription ) {
				$revenue += $subscription->get_total();
			}
		}

		return $revenue;
	}

	/**
	 * Get subscription statistics
	 *
	 * @return array
	 */
	public function get_subscription_statistics() {
		$stats = array(
			'total'           => $this->get_total_subscription_count(),
			'active'          => $this->get_subscription_count_by_status( 'wc-active' ),
			'pending'         => $this->get_subscription_count_by_status( 'wc-pending' ),
			'on_hold'         => $this->get_subscription_count_by_status( 'wc-on-hold' ),
			'cancelled'       => $this->get_subscription_count_by_status( 'wc-cancelled' ),
			'expired'         => $this->get_subscription_count_by_status( 'wc-expired' ),
			'pending_cancel'  => $this->get_subscription_count_by_status( 'wc-pending-cancel' ),
			'revenue'         => $this->get_subscription_revenue(),
		);

		return $stats;
	}
}
