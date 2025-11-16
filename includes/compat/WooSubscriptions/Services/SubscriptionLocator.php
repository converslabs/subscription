<?php
/**
 * Plugin Name - WooCommerce Subscriptions Compat Subscription Locator
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compat\WooSubscriptions\Services;

use SpringDevs\Subscription\Compat\WooSubscriptions\Data\StatusMapper;
use SpringDevs\Subscription\Illuminate\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Locate subscriptions for compatibility helpers.
 *
 * @since 1.0.0
 */
class SubscriptionLocator {

	/**
	 * Retrieve subscriptions for a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id User identifier.
	 * @param array $args    Lookup modifiers.
	 *
	 * @return array
	 */
	public function get_subscriptions_by_user( $user_id = 0, $args = array() ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();

		if ( ! $user_id ) {
			return array();
		}

		$args = wp_parse_args(
			$args,
			array(
				'status'     => 'any',
				'limit'      => -1,
				'product_id' => null,
			)
		);

		$query_args = array(
			'post_status'    => StatusMapper::normalize_filter( $args['status'] ),
			'posts_per_page' => $this->normalize_limit( $args['limit'] ),
			'author'         => $user_id,
			'fields'         => 'all',
		);

		if ( isset( $args['product_id'] ) && null !== $args['product_id'] ) {
			$query_args['product_id'] = (int) $args['product_id'];
		}

		$subscriptions = Helper::get_subscriptions( $query_args );
		$results       = array();

		foreach ( $subscriptions as $subscription_post ) {
			$subscription_id = (int) $subscription_post->ID;
			$meta_raw        = get_post_meta( $subscription_id );
			$meta            = array();

			foreach ( $meta_raw as $key => $values ) {
				$meta[ $key ] = maybe_unserialize( $values[0] );
			}

			$data = array(
				'id'          => $subscription_id,
				'status'      => StatusMapper::to_wcs( $subscription_post->post_status ),
				'customer_id' => (int) $subscription_post->post_author,
				'post'        => $subscription_post,
				'meta'        => $this->prepare_meta( $meta ),
			);

			$results[ $subscription_id ] = new \WC_Subscription( $data );
		}

		return apply_filters( 'wps_wcs_get_users_subscriptions', $results, $user_id, $args );
	}

	/**
	 * Normalize the limit argument.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Requested limit.
	 *
	 * @return int
	 */
	private function normalize_limit( $limit ) {
		$limit = (int) $limit;

		if ( $limit <= 0 ) {
			return -1;
		}

		return $limit;
	}

	/**
	 * Prepare meta data with WooCommerce aliases.
	 *
	 * @since 1.0.0
	 *
	 * @param array $meta Meta data.
	 *
	 * @return array
	 */
	private function prepare_meta( array $meta ) {
		$aliases = array(
			'_subscrpt_billing_period'   => '_billing_period',
			'_subscrpt_billing_interval' => '_billing_interval',
			'_subscrpt_start_date'       => '_schedule_start',
			'_subscrpt_next_date'        => '_schedule_next_payment',
			'_subscrpt_end_date'         => '_schedule_end',
			'_subscrpt_trial_ended'      => '_schedule_trial_end',
		);

		foreach ( $aliases as $source => $target ) {
			if ( isset( $meta[ $source ] ) && ! isset( $meta[ $target ] ) ) {
				$meta[ $target ] = $meta[ $source ];
			}
		}

		$integer_keys = array(
			'_schedule_start',
			'_schedule_next_payment',
			'_schedule_end',
			'_schedule_trial_end',
			'_billing_interval',
		);

		foreach ( $integer_keys as $key ) {
			if ( isset( $meta[ $key ] ) && is_numeric( $meta[ $key ] ) ) {
				$meta[ $key ] = (int) $meta[ $key ];
			}
		}

		return $meta;
	}
}
