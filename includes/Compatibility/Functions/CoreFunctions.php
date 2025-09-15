<?php
/**
 * WooCommerce Subscriptions Core Functions Compatibility
 *
 * This class provides compatibility with WooCommerce Subscriptions core functions
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
 * Core functions compatibility class
 */
class CoreFunctions {

	/**
	 * Register all core functions
	 *
	 * @return void
	 */
	public static function register() {
		// Check if WooCommerce Subscriptions is not already active
		if ( class_exists( 'WC_Subscriptions' ) || function_exists( 'wcs_is_subscription' ) ) {
			return;
		}

		// Register functions
		self::register_subscription_functions();
		self::register_order_functions();
		self::register_product_functions();
		self::register_cart_functions();
		self::register_user_functions();
		self::register_helper_functions();
	}

	/**
	 * Register subscription functions
	 *
	 * @return void
	 */
	private static function register_subscription_functions() {
		// Core subscription functions
		if ( ! function_exists( 'wcs_is_subscription' ) ) {
			/**
			 * Check if a given object is a WC_Subscription
			 *
			 * @param mixed $subscription A WC_Subscription object or an ID
			 * @return bool
			 */
			function wcs_is_subscription( $subscription ) {
				if ( is_object( $subscription ) && is_a( $subscription, 'WC_Subscription' ) ) {
					return true;
				} elseif ( is_numeric( $subscription ) && 'shop_subscription' === get_post_type( $subscription ) ) {
					return true;
				}
				return false;
			}
		}

		if ( ! function_exists( 'wcs_get_subscription' ) ) {
			/**
			 * Get subscription object
			 *
			 * @param int $subscription_id Subscription ID
			 * @return \WC_Subscription|false
			 */
			function wcs_get_subscription( $subscription_id ) {
				if ( ! wcs_is_subscription( $subscription_id ) ) {
					return false;
				}
				return new \WC_Subscription( $subscription_id );
			}
		}

		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			/**
			 * Get subscriptions
			 *
			 * @param array $args Query arguments
			 * @return array
			 */
			function wcs_get_subscriptions( $args = array() ) {
				$defaults = array(
					'post_type'      => 'shop_subscription',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'orderby'        => 'date',
					'order'          => 'DESC',
				);

				$args = wp_parse_args( $args, $defaults );
				$posts = get_posts( $args );
				$subscriptions = array();

				foreach ( $posts as $post ) {
					$subscriptions[] = wcs_get_subscription( $post->ID );
				}

				return $subscriptions;
			}
		}

		if ( ! function_exists( 'wcs_get_subscription_statuses' ) ) {
			/**
			 * Get subscription statuses
			 *
			 * @return array
			 */
			function wcs_get_subscription_statuses() {
				return array(
					'wc-pending'        => __( 'Pending', 'woocommerce-subscriptions' ),
					'wc-active'          => __( 'Active', 'woocommerce-subscriptions' ),
					'wc-on-hold'         => __( 'On Hold', 'woocommerce-subscriptions' ),
					'wc-cancelled'       => __( 'Cancelled', 'woocommerce-subscriptions' ),
					'wc-expired'         => __( 'Expired', 'woocommerce-subscriptions' ),
					'wc-pending-cancel'  => __( 'Pending Cancel', 'woocommerce-subscriptions' ),
				);
			}
		}
	}

	/**
	 * Register order functions
	 *
	 * @return void
	 */
	private static function register_order_functions() {
		if ( ! function_exists( 'wcs_get_subscription_orders' ) ) {
			/**
			 * Get subscription orders
			 *
			 * @param int $subscription_id Subscription ID
			 * @param string $order_type Order type
			 * @return array
			 */
			function wcs_get_subscription_orders( $subscription_id, $order_type = 'any' ) {
				$subscription = wcs_get_subscription( $subscription_id );
				if ( ! $subscription ) {
					return array();
				}

				$orders = array();
				
				// Get parent order
				$parent = $subscription->get_parent();
				if ( $parent && ( 'any' === $order_type || 'parent' === $order_type ) ) {
					$orders[] = $parent;
				}

				// Get renewal orders
				if ( 'any' === $order_type || 'renewal' === $order_type ) {
					$renewal_orders = get_posts( array(
						'post_type'      => 'shop_order',
						'post_status'    => 'any',
						'posts_per_page' => -1,
						'meta_query'     => array(
							array(
								'key'   => '_subscription_renewal',
								'value' => $subscription_id,
							),
						),
					) );

					foreach ( $renewal_orders as $order_post ) {
						$orders[] = wc_get_order( $order_post->ID );
					}
				}

				return $orders;
			}
		}

		if ( ! function_exists( 'wcs_create_renewal_order' ) ) {
			/**
			 * Create renewal order
			 *
			 * @param int $subscription_id Subscription ID
			 * @return \WC_Order|false
			 */
			function wcs_create_renewal_order( $subscription_id ) {
				$subscription = wcs_get_subscription( $subscription_id );
				if ( ! $subscription ) {
					return false;
				}

				// Create renewal order using WPSubscription functionality
				// This would need to be implemented based on your WPSubscription structure
				return false;
			}
		}
	}

	/**
	 * Register product functions
	 *
	 * @return void
	 */
	private static function register_product_functions() {
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

				// Check if product has subscription meta
				return $product->get_meta( '_subscription', true ) === 'yes';
			}
		}

		if ( ! function_exists( 'wcs_get_subscription_products' ) ) {
			/**
			 * Get subscription products
			 *
			 * @return array
			 */
			function wcs_get_subscription_products() {
				$products = get_posts( array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'meta_query'     => array(
						array(
							'key'   => '_subscription',
							'value' => 'yes',
						),
					),
				) );

				$subscription_products = array();
				foreach ( $products as $product_post ) {
					$subscription_products[] = wc_get_product( $product_post->ID );
				}

				return $subscription_products;
			}
		}
	}

	/**
	 * Register cart functions
	 *
	 * @return void
	 */
	private static function register_cart_functions() {
		if ( ! function_exists( 'wcs_cart_contains_subscription' ) ) {
			/**
			 * Check if cart contains subscription
			 *
			 * @return bool
			 */
			function wcs_cart_contains_subscription() {
				if ( ! WC()->cart ) {
					return false;
				}

				foreach ( WC()->cart->get_cart() as $cart_item ) {
					if ( wcs_is_subscription_product( $cart_item['data'] ) ) {
						return true;
					}
				}

				return false;
			}
		}

		if ( ! function_exists( 'wcs_cart_contains_renewal' ) ) {
			/**
			 * Check if cart contains renewal
			 *
			 * @return bool
			 */
			function wcs_cart_contains_renewal() {
				if ( ! WC()->cart ) {
					return false;
				}

				foreach ( WC()->cart->get_cart() as $cart_item ) {
					if ( ! empty( $cart_item['subscription_renewal'] ) ) {
						return true;
					}
				}

				return false;
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
	}

	/**
	 * Register helper functions
	 *
	 * @return void
	 */
	private static function register_helper_functions() {
		if ( ! function_exists( 'wcs_get_subscription_status_name' ) ) {
			/**
			 * Get subscription status name
			 *
			 * @param string $status Status
			 * @return string
			 */
			function wcs_get_subscription_status_name( $status ) {
				$statuses = wcs_get_subscription_statuses();
				return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
			}
		}

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

		if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
			/**
			 * Check if order contains subscription
			 *
			 * @param mixed $order Order object or ID
			 * @return bool
			 */
			function wcs_order_contains_subscription( $order ) {
				if ( is_numeric( $order ) ) {
					$order = wc_get_order( $order );
				}

				if ( ! $order ) {
					return false;
				}

				foreach ( $order->get_items() as $item ) {
					$product = $item->get_product();
					if ( $product && wcs_is_subscription_product( $product ) ) {
						return true;
					}
				}

				return false;
			}
		}

		if ( ! function_exists( 'wcs_order_contains_renewal' ) ) {
			/**
			 * Check if order contains renewal
			 *
			 * @param mixed $order Order object or ID
			 * @return bool
			 */
			function wcs_order_contains_renewal( $order ) {
				if ( is_numeric( $order ) ) {
					$order = wc_get_order( $order );
				}

				if ( ! $order ) {
					return false;
				}

				return $order->get_meta( '_subscription_renewal' ) === 'yes';
			}
		}

		if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			/**
			 * Get subscriptions for order
			 *
			 * @param mixed $order Order object or ID
			 * @return array
			 */
			function wcs_get_subscriptions_for_order( $order ) {
				if ( is_numeric( $order ) ) {
					$order = wc_get_order( $order );
				}

				if ( ! $order ) {
					return array();
				}

				$subscription_ids = $order->get_meta( '_subscription_id' );
				if ( ! $subscription_ids ) {
					return array();
				}

				$subscriptions = array();
				foreach ( (array) $subscription_ids as $subscription_id ) {
					$subscription = wcs_get_subscription( $subscription_id );
					if ( $subscription ) {
						$subscriptions[] = $subscription;
					}
				}

				return $subscriptions;
			}
		}

		if ( ! function_exists( 'wcs_is_manual_renewal_required' ) ) {
			/**
			 * Check if manual renewal is required
			 *
			 * @return bool
			 */
			function wcs_is_manual_renewal_required() {
				return false; // Not implemented yet
			}
		}
	}
}
