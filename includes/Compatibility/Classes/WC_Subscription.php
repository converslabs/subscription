<?php
/**
 * WooCommerce Subscription Compatibility Class
 *
 * This class provides compatibility with WooCommerce Subscriptions WC_Subscription class
 * by mapping it to WPSubscription's subscription functionality.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Compatibility\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Subscription compatibility class
 */
class WC_Subscription extends \WC_Order {

	/**
	 * Order type
	 *
	 * @var string
	 */
	public $order_type = 'shop_subscription';

	/**
	 * Data store name
	 *
	 * @var string
	 */
	protected $data_store_name = 'subscription';

	/**
	 * Object type
	 *
	 * @var string
	 */
	protected $object_type = 'subscription';

	/**
	 * Extra data for this object
	 *
	 * @var array
	 */
	protected $extra_data = array(
		'billing_period'          => '',
		'billing_interval'        => 1,
		'suspension_count'        => 0,
		'requires_manual_renewal' => true,
		'cancelled_email_sent'    => false,
		'trial_period'            => '',
		'last_order_date_created' => null,
		'schedule_trial_end'      => null,
		'schedule_next_payment'   => null,
		'schedule_cancelled'      => null,
		'schedule_end'            => null,
		'schedule_payment_retry'  => null,
		'schedule_start'          => null,
		'switch_data'             => array(),
	);

	/**
	 * Valid date types
	 *
	 * @var array
	 */
	protected $valid_date_types = array(
		'start',
		'trial_end',
		'next_payment',
		'end',
		'last_payment',
		'cancelled',
		'payment_retry',
	);

	/**
	 * WPSubscription subscription ID
	 *
	 * @var int
	 */
	private $wp_subscription_id = null;

	/**
	 * Constructor
	 *
	 * @param int $subscription_id Subscription ID
	 */
	public function __construct( $subscription_id = 0 ) {
		// Map to WPSubscription subscription
		$this->wp_subscription_id = $this->map_to_wp_subscription( $subscription_id );
		
		// Initialize parent with mapped ID
		parent::__construct( $this->wp_subscription_id );
		
		// Set order type
		$this->set_order_type( 'shop_subscription' );
	}

	/**
	 * Map WooCommerce Subscriptions ID to WPSubscription ID
	 *
	 * @param int $subscription_id Subscription ID
	 * @return int
	 */
	private function map_to_wp_subscription( $subscription_id ) {
		if ( $subscription_id <= 0 ) {
			return 0;
		}

		// Check if this is already a WPSubscription subscription
		if ( get_post_type( $subscription_id ) === 'subscrpt_order' ) {
			return $subscription_id;
		}

		// Try to find corresponding WPSubscription subscription
		$wp_subscription_id = $this->find_wp_subscription_by_wcs_id( $subscription_id );
		
		if ( ! $wp_subscription_id ) {
			// Create new WPSubscription subscription
			$wp_subscription_id = $this->create_wp_subscription_from_wcs( $subscription_id );
		}

		return $wp_subscription_id;
	}

	/**
	 * Find WPSubscription subscription by WooCommerce Subscriptions ID
	 *
	 * @param int $wcs_id WooCommerce Subscriptions ID
	 * @return int|false
	 */
	private function find_wp_subscription_by_wcs_id( $wcs_id ) {
		$posts = get_posts( array(
			'post_type'      => 'subscrpt_order',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => '_wcs_original_id',
					'value' => $wcs_id,
				),
			),
		) );

		return ! empty( $posts ) ? $posts[0]->ID : false;
	}

	/**
	 * Create WPSubscription subscription from WooCommerce Subscriptions data
	 *
	 * @param int $wcs_id WooCommerce Subscriptions ID
	 * @return int|false
	 */
	private function create_wp_subscription_from_wcs( $wcs_id ) {
		// This would need to be implemented based on your WPSubscription structure
		// For now, return the original ID
		return $wcs_id;
	}

	/**
	 * Get subscription status
	 *
	 * @return string
	 */
	public function get_status() {
		$status = parent::get_status();
		
		// Map WooCommerce Subscriptions statuses to WPSubscription statuses
		$status_map = array(
			'wc-pending'    => 'pending',
			'wc-active'     => 'active',
			'wc-on-hold'    => 'on-hold',
			'wc-cancelled'  => 'cancelled',
			'wc-expired'    => 'expired',
			'wc-pending-cancel' => 'pending-cancel',
		);

		return isset( $status_map[ $status ] ) ? $status_map[ $status ] : $status;
	}

	/**
	 * Set subscription status
	 *
	 * @param string $new_status New status
	 * @param string $note Optional note
	 */
	public function update_status( $new_status, $note = '' ) {
		// Map WPSubscription statuses to WooCommerce Subscriptions statuses
		$status_map = array(
			'pending'        => 'wc-pending',
			'active'         => 'wc-active',
			'on-hold'        => 'wc-on-hold',
			'cancelled'      => 'wc-cancelled',
			'expired'        => 'wc-expired',
			'pending-cancel' => 'wc-pending-cancel',
		);

		$wc_status = isset( $status_map[ $new_status ] ) ? $status_map[ $new_status ] : $new_status;
		
		parent::update_status( $wc_status, $note );
	}

	/**
	 * Get billing period
	 *
	 * @return string
	 */
	public function get_billing_period() {
		return $this->get_meta( '_billing_period', true ) ?: 'month';
	}

	/**
	 * Set billing period
	 *
	 * @param string $period Billing period
	 */
	public function set_billing_period( $period ) {
		$this->update_meta_data( '_billing_period', $period );
	}

	/**
	 * Get billing interval
	 *
	 * @return int
	 */
	public function get_billing_interval() {
		return (int) $this->get_meta( '_billing_interval', true ) ?: 1;
	}

	/**
	 * Set billing interval
	 *
	 * @param int $interval Billing interval
	 */
	public function set_billing_interval( $interval ) {
		$this->update_meta_data( '_billing_interval', (int) $interval );
	}

	/**
	 * Get trial period
	 *
	 * @return string
	 */
	public function get_trial_period() {
		return $this->get_meta( '_trial_period', true ) ?: '';
	}

	/**
	 * Set trial period
	 *
	 * @param string $period Trial period
	 */
	public function set_trial_period( $period ) {
		$this->update_meta_data( '_trial_period', $period );
	}

	/**
	 * Get next payment date
	 *
	 * @return string
	 */
	public function get_date( $date_type ) {
		if ( ! in_array( $date_type, $this->valid_date_types, true ) ) {
			return '';
		}

		$meta_key = '_' . $date_type . '_date';
		return $this->get_meta( $meta_key, true );
	}

	/**
	 * Set date
	 *
	 * @param string $date_type Date type
	 * @param string $date Date
	 */
	public function set_date( $date_type, $date ) {
		if ( ! in_array( $date_type, $this->valid_date_types, true ) ) {
			return;
		}

		$meta_key = '_' . $date_type . '_date';
		$this->update_meta_data( $meta_key, $date );
	}

	/**
	 * Get parent order
	 *
	 * @return \WC_Order|null
	 */
	public function get_parent() {
		$parent_id = $this->get_meta( '_parent_order_id', true );
		return $parent_id ? wc_get_order( $parent_id ) : null;
	}

	/**
	 * Set parent order
	 *
	 * @param int $order_id Parent order ID
	 */
	public function set_parent( $order_id ) {
		$this->update_meta_data( '_parent_order_id', (int) $order_id );
	}

	/**
	 * Get payment method
	 *
	 * @return string
	 */
	public function get_payment_method() {
		return $this->get_meta( '_payment_method', true );
	}

	/**
	 * Set payment method
	 *
	 * @param string $method Payment method
	 */
	public function set_payment_method( $method ) {
		$this->update_meta_data( '_payment_method', $method );
	}

	/**
	 * Get payment method title
	 *
	 * @return string
	 */
	public function get_payment_method_title() {
		return $this->get_meta( '_payment_method_title', true );
	}

	/**
	 * Set payment method title
	 *
	 * @param string $title Payment method title
	 */
	public function set_payment_method_title( $title ) {
		$this->update_meta_data( '_payment_method_title', $title );
	}

	/**
	 * Check if subscription requires manual renewal
	 *
	 * @return bool
	 */
	public function get_requires_manual_renewal() {
		return 'true' === $this->get_meta( '_requires_manual_renewal', true );
	}

	/**
	 * Set requires manual renewal
	 *
	 * @param bool $requires_manual_renewal Requires manual renewal
	 */
	public function set_requires_manual_renewal( $requires_manual_renewal ) {
		$this->update_meta_data( '_requires_manual_renewal', $requires_manual_renewal ? 'true' : 'false' );
	}

	/**
	 * Get suspension count
	 *
	 * @return int
	 */
	public function get_suspension_count() {
		return (int) $this->get_meta( '_suspension_count', true );
	}

	/**
	 * Set suspension count
	 *
	 * @param int $count Suspension count
	 */
	public function set_suspension_count( $count ) {
		$this->update_meta_data( '_suspension_count', (int) $count );
	}

	/**
	 * Get cancelled email sent status
	 *
	 * @return bool
	 */
	public function get_cancelled_email_sent() {
		return 'true' === $this->get_meta( '_cancelled_email_sent', true );
	}

	/**
	 * Set cancelled email sent status
	 *
	 * @param bool $sent Cancelled email sent
	 */
	public function set_cancelled_email_sent( $sent ) {
		$this->update_meta_data( '_cancelled_email_sent', $sent ? 'true' : 'false' );
	}

	/**
	 * Get switch data
	 *
	 * @return array
	 */
	public function get_switch_data() {
		$data = $this->get_meta( '_switch_data', true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Set switch data
	 *
	 * @param array $data Switch data
	 */
	public function set_switch_data( $data ) {
		$this->update_meta_data( '_switch_data', $data );
	}

	/**
	 * Get payment count
	 *
	 * @return int
	 */
	public function get_payment_count() {
		return (int) $this->get_meta( '_payment_count', true );
	}

	/**
	 * Set payment count
	 *
	 * @param int $count Payment count
	 */
	public function set_payment_count( $count ) {
		$this->update_meta_data( '_payment_count', (int) $count );
	}

	/**
	 * Get WPSubscription subscription ID
	 *
	 * @return int
	 */
	public function get_wp_subscription_id() {
		return $this->wp_subscription_id;
	}
}
