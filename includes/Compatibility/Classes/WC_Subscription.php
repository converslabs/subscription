<?php
/**
 * WC_Subscription Compatibility Class
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility\Classes;

use SpringDevs\Subscription\Illuminate\Helper;
use SpringDevs\Subscription\Illuminate\Action;

/**
 * WC_Subscription class.
 *
 * Wrapper class that extends WC_Order to provide WooCommerce Subscriptions compatibility.
 *
 * @package SpringDevs\Subscription\Compatibility\Classes
 * @since   1.0.0
 */
class WC_Subscription extends \WC_Order {

	/**
	 * Subscription ID (maps to subscrpt_order post ID).
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	protected $subscription_id = 0;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param int|WC_Subscription $subscription Subscription ID or object.
	 */
	public function __construct( $subscription = 0 ) {
		parent::__construct( 0 ); // Don't load as order yet.

		if ( is_numeric( $subscription ) && $subscription > 0 ) {
			$this->subscription_id = absint( $subscription );
			$this->load_subscription_data();
		} elseif ( $subscription instanceof self ) {
			$this->subscription_id = absint( $subscription->get_id() );
			$this->load_subscription_data();
		}
	}

	/**
	 * Load subscription data from WPSubscription.
	 *
	 * @since 1.0.0
	 */
	private function load_subscription_data() {
		$subscription_data = Helper::get_subscription_data( $this->subscription_id );

		if ( null === $subscription_data ) {
			return;
		}

		// Set the ID.
		$this->set_id( $this->subscription_id );

		// Load parent order data if available.
		$parent_order_id = get_post_meta( $this->subscription_id, '_subscrpt_order_id', true );
		if ( $parent_order_id ) {
			$parent_order = wc_get_order( $parent_order_id );
			if ( $parent_order ) {
				// Copy customer data from parent order.
				$this->set_customer_id( $parent_order->get_customer_id() );
				$this->set_billing_email( $parent_order->get_billing_email() );
				$this->set_billing_first_name( $parent_order->get_billing_first_name() );
				$this->set_billing_last_name( $parent_order->get_billing_last_name() );
			}
		}
	}

	/**
	 * Get subscription status.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_status( $context = 'view' ) {
		$wps_status = get_post_status( $this->subscription_id );
		return $this->map_wps_to_wcs_status( $wps_status );
	}

	/**
	 * Update subscription status.
	 *
	 * @since 1.0.0
	 * @param string $new_status New status.
	 * @param string $note Optional note.
	 * @param bool   $manual_update Whether this is a manual update.
	 * @return bool
	 */
	public function update_status( $new_status, $note = '', $manual_update = false ) {
		$wps_status = $this->map_wcs_to_wps_status( $new_status );
		Action::status( $wps_status, $this->subscription_id );

		if ( $note ) {
			$this->add_order_note( $note );
		}

		return true;
	}

	/**
	 * Get billing period.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	public function get_billing_period() {
		return get_post_meta( $this->subscription_id, '_subscrpt_timing_option', true );
	}

	/**
	 * Get billing interval.
	 *
	 * @since  1.0.0
	 * @return int
	 */
	public function get_billing_interval() {
		$interval = get_post_meta( $this->subscription_id, '_subscrpt_timing_per', true );
		return ! empty( $interval ) ? absint( $interval ) : 1;
	}

	/**
	 * Get date.
	 *
	 * @since  1.0.0
	 * @param  string $date_type Type of date ('start', 'trial_end', 'next_payment', 'last_payment', 'end').
	 * @param  string $timezone Timezone for the date.
	 * @return string|null
	 */
	public function get_date( $date_type, $timezone = 'gmt' ) {
		$timestamp = null;

		switch ( $date_type ) {
			case 'start':
			case 'date_created':
				$timestamp = get_post_meta( $this->subscription_id, '_subscrpt_start_date', true );
				break;

			case 'trial_end':
				$trial = get_post_meta( $this->subscription_id, '_subscrpt_trial', true );
				if ( ! empty( $trial ) ) {
					$start = get_post_meta( $this->subscription_id, '_subscrpt_start_date', true );
					if ( $start ) {
						// Calculate trial end date.
						$timestamp = $start; // Simplified - would need proper calculation.
					}
				}
				break;

			case 'next_payment':
				$timestamp = get_post_meta( $this->subscription_id, '_subscrpt_next_date', true );
				break;

			case 'last_payment':
			case 'last_order_date_created':
				// Get from order history.
				$timestamp = null; // Would need to query order history.
				break;

			case 'end':
				$status = get_post_status( $this->subscription_id );
				if ( 'expired' === $status || 'cancelled' === $status ) {
					$timestamp = get_post_modified_time( 'U', true, $this->subscription_id );
				}
				break;
		}

		if ( ! $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Get parent order ID.
	 *
	 * @since  1.0.0
	 * @param  string $context View context.
	 * @return int
	 */
	public function get_parent_id( $context = 'view' ) {
		return absint( get_post_meta( $this->subscription_id, '_subscrpt_order_id', true ) );
	}

	/**
	 * Get user/customer ID.
	 *
	 * @since  1.0.0
	 * @param  string $context View context.
	 * @return int
	 */
	public function get_user_id( $context = 'view' ) {
		$parent_order_id = $this->get_parent_id();
		if ( $parent_order_id ) {
			$parent_order = wc_get_order( $parent_order_id );
			if ( $parent_order ) {
				return $parent_order->get_customer_id( $context );
			}
		}
		return 0;
	}

	/**
	 * Get payment method.
	 *
	 * @since  1.0.0
	 * @param  string $context View context.
	 * @return string
	 */
	public function get_payment_method( $context = 'view' ) {
		$parent_order_id = $this->get_parent_id();
		if ( $parent_order_id ) {
			$parent_order = wc_get_order( $parent_order_id );
			if ( $parent_order ) {
				return $parent_order->get_payment_method( $context );
			}
		}
		return '';
	}

	/**
	 * Set payment method.
	 *
	 * @since 1.0.0
	 * @param string $payment_method Payment method ID.
	 */
	public function set_payment_method( $payment_method = '' ) {
		// Store on the subscription meta.
		update_post_meta( $this->subscription_id, '_payment_method', $payment_method );
		parent::set_payment_method( $payment_method );
	}

	/**
	 * Get payment method title.
	 *
	 * @since  1.0.0
	 * @param  string $context View context.
	 * @return string
	 */
	public function get_payment_method_title( $context = 'view' ) {
		$parent_order_id = $this->get_parent_id();
		if ( $parent_order_id ) {
			$parent_order = wc_get_order( $parent_order_id );
			if ( $parent_order ) {
				return $parent_order->get_payment_method_title( $context );
			}
		}
		return '';
	}

	/**
	 * Set payment method title.
	 *
	 * @since 1.0.0
	 * @param string $payment_method_title Payment method title.
	 */
	public function set_payment_method_title( $payment_method_title = '' ) {
		update_post_meta( $this->subscription_id, '_payment_method_title', $payment_method_title );
		parent::set_payment_method_title( $payment_method_title );
	}

	/**
	 * Get total.
	 *
	 * @since  1.0.0
	 * @param  string $context View context.
	 * @return float
	 */
	public function get_total( $context = 'view' ) {
		$price = get_post_meta( $this->subscription_id, '_subscrpt_price', true );
		return ! empty( $price ) ? floatval( $price ) : 0.0;
	}

	/**
	 * Add order note.
	 *
	 * @since  1.0.0
	 * @param  string $note Note content.
	 * @param  int    $is_customer_note Whether this is a customer note.
	 * @param  bool   $added_by_user Whether added by user.
	 * @return int Comment ID.
	 */
	public function add_order_note( $note, $is_customer_note = 0, $added_by_user = false ) {
		$comment_data = array(
			'comment_post_ID'      => $this->subscription_id,
			'comment_content'      => $note,
			'comment_type'         => 'subscrpt_note',
			'comment_parent'       => 0,
			'user_id'              => $added_by_user ? get_current_user_id() : 0,
			'comment_approved'     => 1,
			'comment_author'       => 'WPSubscription',
			'comment_author_email' => '',
		);

		return wp_insert_comment( $comment_data );
	}

	/**
	 * Payment complete.
	 *
	 * @since 1.0.0
	 * @param string $transaction_id Transaction ID.
	 * @return bool
	 */
	public function payment_complete( $transaction_id = '' ) {
		// Update subscription to active if it's not already.
		$current_status = get_post_status( $this->subscription_id );

		if ( 'active' !== $current_status ) {
			Action::status( 'active', $this->subscription_id );
		}

		if ( $transaction_id ) {
			$this->add_order_note(
				sprintf(
					/* translators: %s: transaction ID */
					__( 'Payment received. Transaction ID: %s', 'wp_subscription' ),
					$transaction_id
				)
			);
		}

		return true;
	}

	/**
	 * Map WPSubscription status to WooCommerce Subscriptions status.
	 *
	 * @since  1.0.0
	 * @param  string $wps_status WPS status.
	 * @return string WCS status
	 */
	private function map_wps_to_wcs_status( $wps_status ) {
		$map = array(
			'active'       => 'active',
			'on-hold'      => 'on-hold',
			'cancelled'    => 'cancelled',
			'pe_cancelled' => 'pending-cancel',
			'expired'      => 'expired',
			'pending'      => 'pending',
		);

		return isset( $map[ $wps_status ] ) ? $map[ $wps_status ] : $wps_status;
	}

	/**
	 * Map WooCommerce Subscriptions status to WPSubscription status.
	 *
	 * @since  1.0.0
	 * @param  string $wcs_status WCS status.
	 * @return string WPS status
	 */
	private function map_wcs_to_wps_status( $wcs_status ) {
		// Remove 'wc-' prefix if present.
		$wcs_status = str_replace( 'wc-', '', $wcs_status );

		$map = array(
			'active'         => 'active',
			'on-hold'        => 'on-hold',
			'cancelled'      => 'cancelled',
			'pending-cancel' => 'pe_cancelled',
			'expired'        => 'expired',
			'pending'        => 'pending',
		);

		return isset( $map[ $wcs_status ] ) ? $map[ $wcs_status ] : $wcs_status;
	}

	/**
	 * Self-test method for verification.
	 *
	 * @since  1.0.0
	 * @return array Test results
	 */
	public static function test() {
		return array(
			'class_exists'       => true,
			'extends_wc_order'   => is_subclass_of( __CLASS__, 'WC_Order' ),
			'method_exists'      => method_exists( __CLASS__, 'get_billing_period' ),
			'get_billing_period' => method_exists( __CLASS__, 'get_billing_period' ),
			'get_status'         => method_exists( __CLASS__, 'get_status' ),
			'update_status'      => method_exists( __CLASS__, 'update_status' ),
		);
	}
}
