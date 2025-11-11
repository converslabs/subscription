<?php
/**
 * Plugin Name - WooCommerce Subscriptions Status Mapper
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compat\WooSubscriptions\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides status translation between WPSubscription and WooCommerce Subscriptions.
 *
 * @since 1.0.0
 */
class Status_Mapper {

	/**
	 * Map of WPSubscription statuses to WooCommerce statuses.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string,string>
	 */
	protected static $to_wcs = array(
		'active'         => 'wc-active',
		'on_hold'        => 'wc-on-hold',
		'on-hold'        => 'wc-on-hold',
		'paused'         => 'wc-on-hold',
		'cancelled'      => 'wc-cancelled',
		'expired'        => 'wc-expired',
		'pending'        => 'wc-pending',
		'pending-cancel' => 'wc-pending-cancel',
		'pe_cancelled'   => 'wc-pending-cancel',
		'trial'          => 'wc-pending',
	);

	/**
	 * Translate a WPSubscription status into WooCommerce status slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status WPSubscription status.
	 *
	 * @return string
	 */
	public static function to_wcs( $status ) {
		$status = strtolower( (string) $status );

		return self::$to_wcs[ $status ] ?? $status;
	}

	/**
	 * Translate a WooCommerce status back to WPSubscription status.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status WooCommerce Subscriptions status.
	 *
	 * @return string
	 */
	public static function to_internal( $status ) {
		$status = strtolower( (string) $status );

		foreach ( self::$to_wcs as $internal => $wcs_status ) {
			if ( $status === strtolower( $wcs_status ) ) {
				return $internal;
			}
		}

		return $status;
	}

	/**
	 * Normalize status filters that may include WooCommerce statuses.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array $statuses Status or list of statuses.
	 *
	 * @return string|array
	 */
	public static function normalize_filter( $statuses ) {
		if ( empty( $statuses ) || 'any' === $statuses ) {
			return 'any';
		}

		$statuses = (array) $statuses;
		$mapped   = array();

		foreach ( $statuses as $status ) {
			$mapped[] = self::to_internal( $status );
		}

		$mapped = array_filter( array_unique( $mapped ) );

		return empty( $mapped ) ? 'any' : $mapped;
	}
}
