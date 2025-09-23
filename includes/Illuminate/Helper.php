<?php

namespace SpringDevs\Subscription\Illuminate;

// HPOS: This file is compatible with WooCommerce High-Performance Order Storage (HPOS).
// All WooCommerce order data is accessed via WooCommerce CRUD methods (wc_get_order, wc_get_orders, etc.).
// All direct post meta access is for subscription data only, not WooCommerce order data.
// If you add new order data access, use WooCommerce CRUD for HPOS compatibility.

/**
 * Class Helper || Some Helper Methods
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Helper {

	/**
	 * Get type's singular or plural from time_per.
	 *
	 * @param int    $number timing_per.
	 * @param string $typo timing_option.
	 *
	 * @return string
	 */
	public static function get_typos( $number, $typo ) {
		if ( 1 === (int) $number && 'days' === $typo ) {
			return ucfirst( __( 'day', 'wp_subscription' ) );
		} elseif ( 1 === (int) $number && 'weeks' === $typo ) {
			return ucfirst( __( 'week', 'wp_subscription' ) );
		} elseif ( 1 === (int) $number && 'months' === $typo ) {
			return ucfirst( __( 'month', 'wp_subscription' ) );
		} elseif ( 1 === (int) $number && 'years' === $typo ) {
			return ucfirst( __( 'year', 'wp_subscription' ) );
		} else {
			return ucfirst( $typo );
		}
	}

	/**
	 * Generate start date
	 *
	 * @param null|string $trial Trial.
	 *
	 * @return string
	 */
	public static function start_date( $trial = null ) {
		if ( null === $trial ) {
			$start_date = time();
		} else {
			$start_date = strtotime( $trial );
		}
		return wp_date( get_option( 'date_format' ), $start_date );
	}

	/**
	 * Generate next date
	 *
	 * @param string      $time Time.
	 * @param null|string $trial Trial.
	 *
	 * @return string
	 */
	public static function next_date( $time, $trial = null ) {
		if ( null === $trial ) {
			$start_date = time();
		} else {
			$start_date = strtotime( $trial );
		}
		return wp_date( get_option( 'date_format' ), strtotime( $time, $start_date ) );
	}

	/**
	 * Check subscription exists by product ID.
	 *
	 * @param int          $product_id Product ID.
	 * @param string|array $status Status.
	 *
	 * @return \WP_Post | false
	 */
	public static function subscription_exists( int $product_id, $status ) {
		if ( 0 === get_current_user_id() ) {
			return false;
		}

		$args = array(
			'post_type'   => 'subscrpt_order',
			'post_status' => $status,
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'key'   => '_subscrpt_product_id',
					'value' => $product_id,
				),
			),
			'author'      => get_current_user_id(),
		);

		$posts = get_posts( $args );
		return count( $posts ) > 0 ? $posts[0] : false;
	}

	/**
	 * Check if product trial exixts for an user.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return boolean
	 */
	public static function check_trial( int $product_id ): bool {
		return ! self::subscription_exists( $product_id, array( 'expired', 'pending', 'active', 'on-hold', 'pe_cancelled', 'cancelled' ) );
	}

	/**
	 * Rewew when expired.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public static function renew( int $subscription_id ) {
		$trial = get_post_meta( $subscription_id, '_subscrpt_trial', true );
		if ( null !== $trial ) {
			update_post_meta( $subscription_id, '_subscrpt_trial', null );
		}

		do_action( 'subscrpt_when_product_expired', $subscription_id, true );
	}

	/**
	 * Get Subscriptions Histories
	 *
	 * @param int $order_id Order ID.
	 */
	public static function get_subscriptions_from_order( $order_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		$histories  = $wpdb->get_results(
			$wpdb->prepare(
				// @phpcs:ignore
				'SELECT * FROM %i WHERE order_id=%d',
				array( $table_name, $order_id )
			)
		);

		return $histories;
	}

	/**
	 * Get Subscriptions Histories
	 *
	 * @param int $order_item_id Order item ID.
	 */
	public static function get_subscription_from_order_item_id( $order_item_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		return $wpdb->get_row(
			$wpdb->prepare(
				// @phpcs:ignore
				'SELECT * FROM %i WHERE order_item_id=%d',
				array( $table_name, $order_item_id )
			)
		);
	}

	/**
	 * Format price with Subscription
	 *
	 * @param string $price Price.
	 * @param int    $subscription_id Subscription ID.
	 * @param bool   $display_trial True/False.
	 *
	 * @return string
	 */
	public static function format_price_with_subscription( $price, $subscription_id, $display_trial = false ) {
		$order_id      = get_post_meta( $subscription_id, '_subscrpt_order_id', true );
		$order_item_id = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
		$item_meta     = wc_get_order_item_meta( $order_item_id, '_subscrpt_meta', true );

		$order = wc_get_order( $order_id );
		$time  = '1' === $item_meta['time'] ? null : $item_meta['time'] . ' ';
		$type  = self::get_typos( $item_meta['time'], $item_meta['type'] );

		$formatted_price = wc_price(
			$price,
			array(
				'currency' => $order->get_currency(),
			)
		) . ' / ' . $time . $type;

		if ( $display_trial ) {
			$trial     = $item_meta['trial'];
			$has_trial = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;

			if ( $has_trial ) {
				$trial_html       = '<br/><small> + Got ' . $trial . ' free trial!</small>';
				$formatted_price .= $trial_html;
			}
		}

		return apply_filters( 'subscrpt_format_price_with_subscription', $formatted_price, $price, $subscription_id );
	}

	/**
	 * Format price with order item
	 *
	 * @param string $price Price.
	 * @param int    $item_id Item Id.
	 * @param bool   $display_trial display trial?.
	 *
	 * @return string
	 */
	public static function format_price_with_order_item( $price, $item_id, $display_trial = false ) {
		$order_id = wc_get_order_id_by_order_item_id( $item_id );
		$order    = wc_get_order( $order_id );

		$item_meta = wc_get_order_item_meta( $item_id, '_subscrpt_meta', true );

		if ( ! $item_meta || ! is_array( $item_meta ) ) {
			return false;
		}

		$time = 1 === (int) $item_meta['time'] ? null : $item_meta['time'] . '-';
		$type = self::get_typos( $item_meta['time'], $item_meta['type'] );

		$formatted_price = wc_price(
			$price,
			array(
				'currency' => $order->get_currency(),
			)
		) . ' / ' . $time . $type;

		if ( $display_trial ) {
			$trial     = $item_meta['trial'];
			$has_trial = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;

			if ( $has_trial ) {
				$trial_html       = '<br/><small> + Got ' . $trial . ' free trial!</small>';
				$formatted_price .= $trial_html;
			}
		}

		return apply_filters( 'subscrpt_format_price_with_subscription', $formatted_price, $price, $item_id );
	}

	/**
	 * Get total subscriptions by product ID.
	 *
	 * @param int            $product_id Product ID.
	 * @param string | array $status Status.
	 *
	 * @return \WP_Post | false
	 */
	public static function get_total_subscriptions_from_product( int $product_id, $status = array( 'active', 'pending', 'expired', 'pe_cancelled', 'cancelled' ) ) {
		$args = array(
			'post_type'   => 'subscrpt_order',
			'post_status' => $status,
			'fields'      => 'ids',
			'meta_query'  => array(
				array(
					'key'   => '_subscrpt_product_id',
					'value' => $product_id,
				),
			),
		);

		$posts = get_posts( $args );

		return count( $posts );
	}

	/**
	 * Process renewal on order.
	 *
	 * @param int $subscription_id Subscription Id.
	 * @param int $order_id Order Id.
	 * @param int $order_item_id Order Item Id.
	 *
	 * @return void
	 */
	public static function process_order_renewal( $subscription_id, $order_id, $order_item_id ) {
		global $wpdb;
		$history_table = $wpdb->prefix . 'subscrpt_order_relation';

		// Check if this is a split payment subscription
		$payment_type = function_exists( 'subscrpt_get_payment_type' ) ? subscrpt_get_payment_type( $subscription_id ) : 'recurring';
		$max_payments = function_exists( 'subscrpt_get_max_payments' ) ? subscrpt_get_max_payments( $subscription_id ) : 0;
		$payments_made = function_exists( 'subscrpt_count_payments_made' ) ? subscrpt_count_payments_made( $subscription_id ) : 0;
		
		$comment_content = '';
		$activity_type = '';
		
		if ( 'split_payment' === $payment_type && $max_payments ) {
			$comment_content = sprintf(
				/* translators: %1$s: order id, %2$d: payment number, %3$d: total payments */
				__( 'Split payment installment %2$d of %3$d. Order %1$s created for subscription.', 'wp_subscription' ),
				$order_id,
				$payments_made + 1, // +1 because this is a new renewal
				$max_payments
			);
			$activity_type = __( 'Split Payment - Renewal', 'wp_subscription' );
		} else {
			$comment_content = sprintf(
				/* translators: order id. */
				__( 'The order %s has been created for the subscription', 'wp_subscription' ),
				$order_id
			);
			$activity_type = __( 'Renewal Order', 'wp_subscription' );
		}
		
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => $comment_content,
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', $activity_type );

		$wpdb->insert(
			$history_table,
			array(
				'subscription_id' => $subscription_id,
				'order_id'        => $order_id,
				'order_item_id'   => $order_item_id,
				'type'            => 'renew',
			)
		);
		
		// Fire action when split payment is renewed
		do_action( 'subscrpt_split_payment_renewed', $subscription_id, $order_id, $order_item_id );
	}

	/**
	 * Process new subscription on order.
	 *
	 * @param \WC_Order_Item $order_item Order Item.
	 * @param string         $post_status status.
	 * @param \WC_Product    $product Product.
	 *
	 * @return int
	 */
	public static function process_new_subscription_order( $order_item, $post_status, $product ) {
		global $wpdb;
		$history_table = $wpdb->prefix . 'subscrpt_order_relation';

		// Prepare split payment arguments
		$split_payment_args = array(
			'product_id'    => $product->get_id(),
			'order_id'      => $order_item->get_order_id(),
			'order_item_id' => $order_item->get_id(),
			'post_status'   => $post_status,
			'max_payments'  => $product->get_meta( '_subscrpt_max_no_payment' ),
			'timing_per'    => $product->get_meta( '_subscrpt_timing_per' ),
			'timing_option' => $product->get_meta( '_subscrpt_timing_option' ),
			'price'         => $product->get_price(),
		);
		
		// Allow modification of split payment arguments
		$split_payment_args = apply_filters( 'subscrpt_split_payment_args', $split_payment_args, $order_item, $product );

		$args            = array(
			'post_title'  => 'Subscription',
			'post_type'   => 'subscrpt_order',
			'post_status' => $split_payment_args['post_status'],
		);
		$subscription_id = wp_insert_post( $args );
		wp_update_post(
			array(
				'ID'         => $subscription_id,
				'post_title' => "Subscription #{$subscription_id}",
			)
		);
		// Check if this is a split payment subscription
		$payment_type = $product->get_meta( '_subscrpt_payment_type' ) ?: 'recurring';
		$max_payments = $product->get_meta( '_subscrpt_max_no_payment' );
		
		$comment_content = '';
		$activity_type = '';
		
		if ( 'split_payment' === $payment_type && $max_payments ) {
			$comment_content = sprintf(
				/* translators: %1$s: order id, %2$d: max payments */
				__( 'Split payment subscription created successfully. Order: %1$s. Total installments: %2$d.', 'wp_subscription' ),
				$order_item->get_order_id(),
				$max_payments
			);
			$activity_type = __( 'Split Payment - New Subscription', 'wp_subscription' );
		} else {
			$comment_content = sprintf(
				/* translators: Order Id. */
				__( 'Subscription successfully created. Order is %s', 'wp_subscription' ),
				$order_item->get_order_id()
			);
			$activity_type = __( 'New Subscription', 'wp_subscription' );
		}
		
		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => $comment_content,
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', $activity_type );

		update_post_meta( $subscription_id, '_subscrpt_product_id', $product->get_id() );

		$wpdb->insert(
			$history_table,
			array(
				'subscription_id' => $subscription_id,
				'order_id'        => $order_item->get_order_id(),
				'order_item_id'   => $order_item->get_id(),
				'type'            => 'new',
			)
		);
		
		// Fire action when split payment plan is created
		do_action( 'subscrpt_split_payment_created', $subscription_id, $split_payment_args, $order_item );

		return $subscription_id;
	}

	/**
	 * Get recurrings items from cart items.
	 *
	 * @param array $cart_items Cart items.
	 *
	 * @return array
	 */
	public static function get_recurrs_for_cart( $cart_items ) {
		$recurrs = array();
		foreach ( $cart_items as $key => $cart_item ) {
			$product = $cart_item['data'];
			if ( $product->is_type( 'simple' ) && isset( $cart_item['subscription'] ) ) {
				$cart_subscription = $cart_item['subscription'];
				$type              = $cart_subscription['type'];

				$price_html      = wc_price( $cart_subscription['per_cost'] * $cart_item['quantity'] ) . '/ ' . $type;
				$recurrs[ $key ] = array(
					'trial_status'    => ! is_null( $cart_subscription['trial'] ),
					'price_html'      => $price_html,
					'start_date'      => self::start_date( $cart_subscription['trial'] ),
					'next_date'       => self::next_date( ( $cart_subscription['time'] ?? 1 ) . ' ' . $cart_subscription['type'], $cart_subscription['trial'] ),
					'can_user_cancel' => $cart_item['data']->get_meta( '_subscrpt_user_cancel' ),
					'max_no_payment'  => $cart_item['data']->get_meta( '_subscrpt_max_no_payment' ),
				);
			}
		}

		return apply_filters( 'subscrpt_cart_recurring_items', $recurrs, $cart_items );
	}

	/**
	 * Create renewal order when subscription expired. [wip]
	 *
	 * @param  int $subscription_id Subscription ID.
	 * @throws \WC_Data_Exception Exception.
	 * @throws \Exception Exception.
	 */
	public static function create_renewal_order( $subscription_id ) {
		// Check if maximum payment limit has been reached
		if ( subscrpt_is_max_payments_reached( $subscription_id ) ) {
			// Mark subscription as expired due to limit reached
			wp_update_post( array(
				'ID'          => $subscription_id,
				'post_status' => 'expired'
			) );
			
			error_log( "WPS: Maximum payment limit reached for subscription #{$subscription_id}. No renewal order created." );
			return false;
		}
		
		$order_item_id = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
		$order_id      = wc_get_order_id_by_order_item_id( $order_item_id );
		$old_order     = self::check_order_for_renewal( $order_id );

		if ( ! $old_order ) {
			return;
		}

		$order_item         = $old_order->get_item( $order_item_id );
		$subscription_price = get_post_meta( $subscription_id, '_subscrpt_price', true );
		$product_args       = array(
			'name'     => $order_item->get_name(),
			'subtotal' => $subscription_price,
			'total'    => $subscription_price,
		);

		// creating new order.
		$new_order_data = self::create_new_order_for_renewal( $old_order, $order_item, $product_args );
		if ( ! $new_order_data ) {
			return;
		}
		$new_order         = $new_order_data['order'];
		$new_order_item_id = $new_order_data['order_item_id'];

		self::create_renewal_history( $subscription_id, $new_order->get_id(), $new_order_item_id );
		update_post_meta( $subscription_id, '_subscrpt_order_id', $new_order->get_id() );
		update_post_meta( $subscription_id, '_subscrpt_order_item_id', $new_order_item_id );

		self::clone_order_metadata( $new_order, $old_order );
		self::clone_stripe_metadata_for_renewal( $subscription_id, $old_order, $new_order );

		// Store Stripe subscription ID if available
		if ( $old_order->get_payment_method() === 'stripe_cc' ) {
			$stripe_subscription_id = $old_order->get_meta('_stripe_subscription_id');
			if ( $stripe_subscription_id ) {
				$new_order->update_meta_data('_stripe_subscription_id', $stripe_subscription_id);
				$new_order->save();
			}
		}

		$new_order->calculate_totals();
		$new_order->save();
		if ( ! is_admin() && function_exists( 'wc_add_notice' ) ) {
			$message = 'Renewal Order(#' . $new_order->get_id() . ') Created.';
			if ( $new_order->has_status( 'pending' ) ) {
				$message .= 'Please <a href="' . $new_order->get_checkout_payment_url() . '">Pay now</a>';
			}
			wc_add_notice( $message, 'success' );
		}

		do_action( 'subscrpt_after_create_renew_order', $new_order, $old_order, $subscription_id, false );
	}

	/**
	 * Clone stripe metadata from old order.
	 *
	 * @param int       $subscription_id Subscription Id.
	 * @param \WC_Order $old_order Old Order Object.
	 * @param \WC_Order $new_order New Order Object.
	 *
	 * @return void
	 */
	public static function clone_stripe_metadata_for_renewal( $subscription_id, $old_order, $new_order ) {
		$is_auto_renew = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		if ( empty( $is_auto_renew ) && subscrpt_is_auto_renew_enabled() ) {
			$is_auto_renew = true;
			update_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		}

		$stripe_enabled = ( 'stripe_cc' === $old_order->get_payment_method() && in_array( $is_auto_renew, array( '1', 1, true ), true ) && subscrpt_is_auto_renew_enabled() && '1' === get_option( 'subscrpt_stripe_auto_renew', '1' ) );

		if ( $stripe_enabled ) {
			$new_order->update_meta_data( '_stripe_customer_id', $old_order->get_meta( '_stripe_customer_id' ) );
			$new_order->update_meta_data( '_stripe_source_id', $old_order->get_meta( '_stripe_source_id' ) );
			$new_order->set_payment_method( $old_order->get_payment_method() );
			$new_order->set_payment_method_title( $old_order->get_payment_method_title() );

			// Add debug log.
			wp_subscrpt_write_debug_log( "Stripe metadata cloned for renewal order #{$new_order->get_id()} from old order #{$old_order->get_id()}" );
		} else {
			wp_subscrpt_write_debug_log( "Stripe metadata did not clone for renewal order #{$new_order->get_id()} from old order #{$old_order->get_id()}" );
		}
	}

	/**
	 * Create history for renewal.
	 *
	 * @param int $subscription_id Subscription Id.
	 * @param int $new_order_id New Order Id.
	 * @param int $new_order_item_id New Order Item Id.
	 *
	 * @return void
	 */
	public static function create_renewal_history( $subscription_id, $new_order_id, $new_order_item_id ) {
		global $wpdb;
		$history_table = $wpdb->prefix . 'subscrpt_order_relation';
		$wpdb->insert(
			$history_table,
			array(
				'subscription_id' => $subscription_id,
				'order_id'        => $new_order_id,
				'order_item_id'   => $new_order_item_id,
				'type'            => 'renew',
			)
		);

		$comment_id = wp_insert_comment(
			array(
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => sprintf( 'Subscription Renewal order successfully created.	order is %s', $new_order_id ),
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			)
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', 'Renewal Order' );
	}

	/**
	 * Get a subscription data.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return array|null
	 */
	public static function get_subscription_data( int $subscription_id ): ?array {
		if ( empty( get_post_meta( $subscription_id ) ) ) {
			return null;
		}

		$status = get_post_status( $subscription_id );
		$price  = get_post_meta( $subscription_id, '_subscrpt_price', true );

		$signup_fee = get_post_meta( $subscription_id, '_subscrpt_signup_fee', true );
		$signup_fee = ! empty( $signup_fee ) ? $signup_fee : 0;

		$product_id = get_post_meta( $subscription_id, '_subscrpt_product_id', true );
		$product_id = ! empty( $product_id ) ? (int) $product_id : 0;

		$variation_id = get_post_meta( $subscription_id, '_subscrpt_variation_id', true );
		$variation_id = ! empty( $variation_id ) ? (int) $variation_id : 0;

		$order_id      = get_post_meta( $subscription_id, '_subscrpt_order_id', true );
		$order_item_id = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );

		$can_user_cancel = get_post_meta( $subscription_id, '_subscrpt_user_cancel', true ) === 'yes';

		$start_date = get_post_meta( $subscription_id, '_subscrpt_start_date', true );
		$start_date = ! empty( $start_date ) ? gmdate( DATE_RFC2822, $start_date ) : null;

		$next_date = get_post_meta( $subscription_id, '_subscrpt_next_date', true );
		$next_date = ! empty( $next_date ) ? gmdate( DATE_RFC2822, $next_date ) : null;

		$timing_per    = get_post_meta( $subscription_id, '_subscrpt_timing_per', true );
		$timing_option = get_post_meta( $subscription_id, '_subscrpt_timing_option', true );

		$is_auto_renew = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );

		$subscription_data = [
			'id'              => $subscription_id,
			'status'          => $status,
			'schedule'        => [
				'timing_per'    => $timing_per,
				'timing_option' => $timing_option,
			],
			'price'           => $price,
			'signup_fee'      => $signup_fee,
			'start_date'      => $start_date,
			'next_date'       => $next_date,
			'product'         => [
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
			],
			'order'           => [
				'order_id'      => $order_id,
				'order_item_id' => $order_item_id,
			],
			'can_user_cancel' => $can_user_cancel,
			'is_auto_renew'   => (bool) $is_auto_renew,
		];

		return $subscription_data;
	}

	/**
	 * Get related orders of a subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return array
	 */
	public static function get_related_orders( int $subscription_id ): array {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';

		// @phpcs:ignore
		$order_histories = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT order_id, order_item_id, type FROM %i WHERE subscription_id=%d ORDER BY id DESC',
				[
					$table_name,
					$subscription_id,
				]
			)
		);

		return $order_histories;
	}

	/**
	 * Create new order for renewal.
	 *
	 * @param \WC_Order      $old_order Old Order Object.
	 * @param \WC_Order_Item $order_item Old Order Item Object.
	 * @param array          $product_args Product args for add product.
	 *
	 * @return array|false
	 */
	public static function create_new_order_for_renewal( \WC_Order $old_order, \WC_Order_Item $order_item, array $product_args ) {
		$product      = $order_item->get_product();
		$user_id      = $old_order->get_user_id();
		$new_order    = wc_create_order(
			array(
				'customer_id' => $user_id,
				'status'      => 'pending',
			)
		);
		$product_meta = apply_filters( 'subscrpt_renewal_item_meta', wc_get_order_item_meta( $order_item->get_id(), '_subscrpt_meta', true ), $product, $order_item );
		$product_args = apply_filters( 'subscrpt_renewal_product_args', $product_args, $product, $order_item );
		if ( ! $product_args ) {
			return false;
		}

		// Ensure product_meta is an array
		if ( ! is_array( $product_meta ) ) {
			$product_meta = array(
				'time'  => 1,
				'type'  => 'days',
				'trial' => null,
			);
		}

		$new_order_item_id = $new_order->add_product(
			$product,
			$order_item->get_quantity(),
			$product_args
		);
		wc_update_order_item_meta(
			$new_order_item_id,
			'_subscrpt_meta',
			array(
				'time'  => isset( $product_meta['time'] ) ? $product_meta['time'] : 1,
				'type'  => isset( $product_meta['type'] ) ? $product_meta['type'] : 'days',
				'trial' => null,
			)
		);

		// Add debug log.
		wp_subscrpt_write_debug_log( "Renewal order #{$new_order->get_id()} created for old order #{$old_order->get_id()}" );

		return array(
			'order'         => $new_order,
			'order_item_id' => $new_order_item_id,
		);
	}

	/**
	 * Write debug log
	 *
	 * @param string $message Debug message
	 * @param mixed  $data Optional data to log
	 * @param string $level Log level (info, warning, error)
	 * @return void
	 */
	public static function wp_subscrpt_write_debug_log( $message, $data = null, $level = 'info' ) {
		if ( defined( 'WP_SUBSCRIPTION_DEBUG' ) && WP_SUBSCRIPTION_DEBUG ) {
			// Use enhanced debug logging
			\SpringDevs\Subscription\Illuminate\Debug\DebugHelpers::log( $message, $data, $level );
		}
	}

	/**
	 * Check if old order is completed or deleted!
	 *
	 * @param mixed $old_order_id Old Order Id.
	 *
	 * @return \WC_Order|false
	 */
	public static function check_order_for_renewal( $old_order_id ) {
		$old_order = wc_get_order( $old_order_id );
		if ( ! $old_order || 'completed' !== $old_order->get_status() ) {
			if ( ! is_admin() && function_exists( 'wc_add_notice' ) ) {
				return wc_add_notice( __( 'Subscription renewal isn\'t possible due to previous order not completed or deletion.', 'wp_subscription' ), 'error' );
			}
			return false;
		}

		return $old_order;
	}
	
	/**
	 * Save meta-data from old order
	 *
	 * @param \WC_Order $new_order new order object.
	 * @param \WC_Order $old_order old order object.
	 *
	 * @return void
	 */
	public static function clone_order_metadata( $new_order, $old_order ) {
		$new_order->set_customer_id( $old_order->get_customer_id() );
		$new_order->set_currency( $old_order->get_currency() );

		// 3 Add Billing Fields
		$customer = new \WC_Customer( $old_order->get_customer_id() );
		$new_order->set_billing_city( $customer->get_billing_city() );
		$new_order->set_billing_state( $customer->get_billing_state() );
		$new_order->set_billing_postcode( $customer->get_billing_postcode() );
		$new_order->set_billing_email( $customer->get_billing_email() );
		$new_order->set_billing_phone( $customer->get_billing_phone() );
		$new_order->set_billing_address_1( $customer->get_billing_address_1() );
		$new_order->set_billing_address_2( $customer->get_billing_address_2() );
		$new_order->set_billing_country( $customer->get_billing_country() );
		$new_order->set_billing_first_name( $customer->get_billing_first_name() );
		$new_order->set_billing_last_name( $customer->get_billing_last_name() );
		$new_order->set_billing_company( $customer->get_billing_company() );

		// 4 Add Shipping Fields
		$new_order->set_shipping_country( $customer->get_shipping_country() );
		$new_order->set_shipping_first_name( $customer->get_shipping_first_name() );
		$new_order->set_shipping_last_name( $customer->get_shipping_last_name() );
		$new_order->set_shipping_company( $customer->get_shipping_company() );
		$new_order->set_shipping_address_1( $customer->get_shipping_address_1() );
		$new_order->set_shipping_address_2( $customer->get_shipping_address_2() );
		$new_order->set_shipping_city( $customer->get_shipping_city() );
		$new_order->set_shipping_state( $customer->get_shipping_state() );
		$new_order->set_shipping_postcode( $customer->get_shipping_postcode() );
	}
}

// HPOS: All order data access below uses WooCommerce CRUD and is HPOS compatible.
