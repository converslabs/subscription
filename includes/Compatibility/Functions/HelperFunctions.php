<?php
/**
 * WooCommerce Subscriptions Helper Functions Compatibility
 *
 * This class provides compatibility with WooCommerce Subscriptions helper functions
 * by mapping them to WPSubscription's functionality.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Compatibility\Functions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper functions compatibility class
 */
class HelperFunctions {

	/**
	 * Register all helper functions
	 *
	 * @return void
	 */
	public static function register() {
		// Check if WooCommerce Subscriptions is not already active
		if ( class_exists( 'WC_Subscriptions' ) || function_exists( 'wcs_get_subscription_period_strings' ) ) {
			return;
		}

		// Register helper functions
		self::register_formatting_functions();
		self::register_time_functions();
		self::register_conditional_functions();
		self::register_user_functions();
	}

	/**
	 * Register formatting functions
	 *
	 * @return void
	 */
	private static function register_formatting_functions() {
		if ( ! function_exists( 'wcs_get_subscription_period_strings' ) ) {
			/**
			 * Get subscription period strings
			 *
			 * @return array
			 */
			function wcs_get_subscription_period_strings() {
				return array(
					'day'   => __( 'day', 'woocommerce-subscriptions' ),
					'week'  => __( 'week', 'woocommerce-subscriptions' ),
					'month' => __( 'month', 'woocommerce-subscriptions' ),
					'year'  => __( 'year', 'woocommerce-subscriptions' ),
				);
			}
		}

		if ( ! function_exists( 'wcs_get_subscription_length_string' ) ) {
			/**
			 * Get subscription length string
			 *
			 * @param int $subscription_length Subscription length
			 * @param string $period Period
			 * @return string
			 */
			function wcs_get_subscription_length_string( $subscription_length, $period ) {
				$periods = wcs_get_subscription_period_strings();
				$period_string = isset( $periods[ $period ] ) ? $periods[ $period ] : $period;
				
				if ( 0 === $subscription_length ) {
					return __( 'Never expires', 'woocommerce-subscriptions' );
				} elseif ( 1 === $subscription_length ) {
					return sprintf( __( '1 %s', 'woocommerce-subscriptions' ), $period_string );
				} else {
					return sprintf( __( '%d %ss', 'woocommerce-subscriptions' ), $subscription_length, $period_string );
				}
			}
		}

		if ( ! function_exists( 'wcs_get_subscription_interval_string' ) ) {
			/**
			 * Get subscription interval string
			 *
			 * @param int $interval Interval
			 * @param string $period Period
			 * @return string
			 */
			function wcs_get_subscription_interval_string( $interval, $period ) {
				$periods = wcs_get_subscription_period_strings();
				$period_string = isset( $periods[ $period ] ) ? $periods[ $period ] : $period;
				
				if ( 1 === $interval ) {
					return sprintf( __( 'Every %s', 'woocommerce-subscriptions' ), $period_string );
				} else {
					return sprintf( __( 'Every %d %ss', 'woocommerce-subscriptions' ), $interval, $period_string );
				}
			}
		}
	}

	/**
	 * Register time functions
	 *
	 * @return void
	 */
	private static function register_time_functions() {
		if ( ! function_exists( 'wcs_add_time' ) ) {
			/**
			 * Add time to a date
			 *
			 * @param string $date Date
			 * @param int $interval Interval
			 * @param string $period Period
			 * @return string
			 */
			function wcs_add_time( $date, $interval, $period ) {
				$timestamp = strtotime( $date );
				
				switch ( $period ) {
					case 'day':
						$timestamp += ( $interval * DAY_IN_SECONDS );
						break;
					case 'week':
						$timestamp += ( $interval * WEEK_IN_SECONDS );
						break;
					case 'month':
						$timestamp = strtotime( '+' . $interval . ' months', $timestamp );
						break;
					case 'year':
						$timestamp = strtotime( '+' . $interval . ' years', $timestamp );
						break;
				}
				
				return date( 'Y-m-d H:i:s', $timestamp );
			}
		}

		if ( ! function_exists( 'wcs_subtract_time' ) ) {
			/**
			 * Subtract time from a date
			 *
			 * @param string $date Date
			 * @param int $interval Interval
			 * @param string $period Period
			 * @return string
			 */
			function wcs_subtract_time( $date, $interval, $period ) {
				$timestamp = strtotime( $date );
				
				switch ( $period ) {
					case 'day':
						$timestamp -= ( $interval * DAY_IN_SECONDS );
						break;
					case 'week':
						$timestamp -= ( $interval * WEEK_IN_SECONDS );
						break;
					case 'month':
						$timestamp = strtotime( '-' . $interval . ' months', $timestamp );
						break;
					case 'year':
						$timestamp = strtotime( '-' . $interval . ' years', $timestamp );
						break;
				}
				
				return date( 'Y-m-d H:i:s', $timestamp );
			}
		}

		if ( ! function_exists( 'wcs_get_time_from_period' ) ) {
			/**
			 * Get time in seconds from period
			 *
			 * @param int $interval Interval
			 * @param string $period Period
			 * @return int
			 */
			function wcs_get_time_from_period( $interval, $period ) {
				switch ( $period ) {
					case 'day':
						return $interval * DAY_IN_SECONDS;
					case 'week':
						return $interval * WEEK_IN_SECONDS;
					case 'month':
						return $interval * MONTH_IN_SECONDS;
					case 'year':
						return $interval * YEAR_IN_SECONDS;
					default:
						return 0;
				}
			}
		}
	}

	/**
	 * Register conditional functions
	 *
	 * @return void
	 */
	private static function register_conditional_functions() {
		if ( ! function_exists( 'wcs_is_woocommerce_pre' ) ) {
			/**
			 * Check if WooCommerce version is pre-3.0
			 *
			 * @return bool
			 */
			function wcs_is_woocommerce_pre( $version ) {
				return version_compare( WC()->version, $version, '<' );
			}
		}

		if ( ! function_exists( 'wcs_is_woocommerce_post' ) ) {
			/**
			 * Check if WooCommerce version is post-3.0
			 *
			 * @return bool
			 */
			function wcs_is_woocommerce_post( $version ) {
				return version_compare( WC()->version, $version, '>=' );
			}
		}

		if ( ! function_exists( 'wcs_is_subscription_product' ) ) {
			/**
			 * Check if product is subscription product
			 *
			 * @param mixed $product Product object or ID
			 * @return bool
			 */
			function wcs_is_subscription_product( $product ) {
				if ( is_numeric( $product ) ) {
					$product = wc_get_product( $product );
				}

				if ( ! $product ) {
					return false;
				}

				return $product->get_meta( '_subscription', true ) === 'yes';
			}
		}

		if ( ! function_exists( 'wcs_is_subscription_variable_product' ) ) {
			/**
			 * Check if product is subscription variable product
			 *
			 * @param mixed $product Product object or ID
			 * @return bool
			 */
			function wcs_is_subscription_variable_product( $product ) {
				if ( is_numeric( $product ) ) {
					$product = wc_get_product( $product );
				}

				if ( ! $product ) {
					return false;
				}

				return $product->is_type( 'variable-subscription' );
			}
		}

		if ( ! function_exists( 'wcs_is_subscription_simple_product' ) ) {
			/**
			 * Check if product is subscription simple product
			 *
			 * @param mixed $product Product object or ID
			 * @return bool
			 */
			function wcs_is_subscription_simple_product( $product ) {
				if ( is_numeric( $product ) ) {
					$product = wc_get_product( $product );
				}

				if ( ! $product ) {
					return false;
				}

				return $product->is_type( 'subscription' );
			}
		}
	}

	/**
	 * Register user functions
	 *
	 * @return void
	 */
	private static function register_user_functions() {
		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			/**
			 * Get user subscriptions
			 *
			 * @param int $user_id User ID
			 * @return array
			 */
			function wcs_get_users_subscriptions( $user_id ) {
				$subscriptions = get_posts( array(
					'post_type'      => 'shop_subscription',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'meta_query'     => array(
						array(
							'key'   => '_customer_user',
							'value' => $user_id,
						),
					),
				) );

				$user_subscriptions = array();
				foreach ( $subscriptions as $subscription_post ) {
					$user_subscriptions[] = wcs_get_subscription( $subscription_post->ID );
				}

				return $user_subscriptions;
			}
		}

		if ( ! function_exists( 'wcs_user_has_subscription' ) ) {
			/**
			 * Check if user has subscription
			 *
			 * @param int $user_id User ID
			 * @param int $product_id Product ID
			 * @param string $status Subscription status
			 * @return bool
			 */
			function wcs_user_has_subscription( $user_id, $product_id = '', $status = 'any' ) {
				$subscriptions = wcs_get_users_subscriptions( $user_id );

				foreach ( $subscriptions as $subscription ) {
					if ( 'any' === $status || $subscription->get_status() === $status ) {
						if ( empty( $product_id ) ) {
							return true;
						}

						foreach ( $subscription->get_items() as $item ) {
							if ( $item->get_product_id() === $product_id ) {
								return true;
							}
						}
					}
				}

				return false;
			}
		}

		if ( ! function_exists( 'wcs_get_user_subscription_count' ) ) {
			/**
			 * Get user subscription count
			 *
			 * @param int $user_id User ID
			 * @param string $status Subscription status
			 * @return int
			 */
			function wcs_get_user_subscription_count( $user_id, $status = 'any' ) {
				$subscriptions = wcs_get_users_subscriptions( $user_id );
				$count = 0;

				foreach ( $subscriptions as $subscription ) {
					if ( 'any' === $status || $subscription->get_status() === $status ) {
						$count++;
					}
				}

				return $count;
			}
		}

		if ( ! function_exists( 'wcs_get_user_subscription_revenue' ) ) {
			/**
			 * Get user subscription revenue
			 *
			 * @param int $user_id User ID
			 * @param string $status Subscription status
			 * @return float
			 */
			function wcs_get_user_subscription_revenue( $user_id, $status = 'any' ) {
				$subscriptions = wcs_get_users_subscriptions( $user_id );
				$revenue = 0;

				foreach ( $subscriptions as $subscription ) {
					if ( 'any' === $status || $subscription->get_status() === $status ) {
						$revenue += $subscription->get_total();
					}
				}

				return $revenue;
			}
		}
	}
}
