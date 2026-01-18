<?php

namespace SpringDevs\Subscription\Illuminate;

use SpringDevs\Subscription\Illuminate\Gateways\Stripe\Stripe;

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
	 * @param bool   $translate Whether to translate the output.
	 * @return string
	 */
	public static function get_typos( $number, $typo, $translate = false ) {
		switch ( strtolower( $typo ) ) {
			case 'day':
			case 'days':
				return $translate
					? _n( 'day', 'days', $number, 'subscription' )
					: ( (int) $number === 1 ? 'day' : 'days' );

			case 'week':
			case 'weeks':
				return $translate
					? _n( 'week', 'weeks', $number, 'subscription' )
					: ( (int) $number === 1 ? 'week' : 'weeks' );

			case 'month':
			case 'months':
				return $translate
					? _n( 'month', 'months', $number, 'subscription' )
					: ( (int) $number === 1 ? 'month' : 'months' );

			case 'year':
			case 'years':
				return $translate
					? _n( 'year', 'years', $number, 'subscription' )
					: ( (int) $number === 1 ? 'year' : 'years' );

			default:
				return $typo;
		}
	}

	/**
	 * Get verbose status from status slug.
	 *
	 * @param string $status Status.
	 */
	public static function get_verbose_status( $status ): string {
		$statuses = array(
			'pending'      => __( 'Pending', 'subscription' ),
			'active'       => __( 'Active', 'subscription' ),
			'on-hold'      => __( 'On Hold', 'subscription' ),
			'expired'      => __( 'Expired', 'subscription' ),
			'pe_cancelled' => __( 'Pending Cancellation', 'subscription' ),
			'cancelled'    => __( 'Cancelled', 'subscription' ),
			'draft'        => __( 'Draft', 'subscription' ),
			'trash'        => __( 'Trash', 'subscription' ),
		);

		$status = strtolower( $status );
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : '';
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
	 * Get Subscriptions
	 *
	 * Args:
	 * - status         => [ any, active, pending, expired, pe_cancelled, cancelled, trash ]
	 * - user_id        => user_id, -1 for all users.
	 * - posts_per_page => limit number of subscriptions.
	 * - return         => return data: ids, post, subscription_data
	 *
	 * @param array $args Args.
	 */
	public static function get_subscriptions( array $args = array() ) {
		$default_args = array(
			'post_type'      => 'subscrpt_order',
			'post_status'    => 'active',
			'author'         => get_current_user_id(),
			'posts_per_page' => -1,
			'fields'         => 'all',
			'return'         => 'post',
		);

		// Normalize some args.
		if ( isset( $args['status'] ) ) {
			$args['post_status'] = $args['status'];
			unset( $args['status'] );
		}
		if ( isset( $args['user_id'] ) ) {
			$args['author'] = $args['user_id'];
			unset( $args['user_id'] );
		}

		// Merge default args with provided args.
		$final_args = wp_parse_args( $args, $default_args );

		if ( isset( $args['author'] ) ) {
			if ( $args['author'] === -1 ) {
				unset( $final_args['author'] );
			} else {
				$final_args['author'] = (int) $args['author'];
			}
		}

		if ( isset( $args['product_id'] ) ) {
			$final_args['meta_query'] = array(
				array(
					'key'   => '_subscrpt_product_id',
					'value' => (int) $args['product_id'],
				),
			);
			unset( $final_args['product_id'] );
		}

		// Fields check
		$only_ids = false;
		if ( $final_args['fields'] === 'ids' || $final_args['return'] === 'ids' ) {
			$final_args['fields'] = 'all';
			$only_ids             = true;
		}

		// Status check
		$statuses                  = $final_args['post_status'];
		$final_args['post_status'] = 'any';

		// Get all subscriptions.
		$subscriptions = get_posts( $final_args );

		// Fallback filtering.
		// ? Sometime status filtering not works properly. So, we need to filter manually.
		$filtered_subscriptions = [];

		// Filter by status.
		foreach ( $subscriptions as $subscription ) {
			if ( ( is_array( $statuses ) && in_array( 'any', $statuses, true ) ) || $statuses === 'any' ) {
				$filtered_subscriptions[] = $subscription;
				continue;
			}

			if ( ( is_array( $statuses ) && in_array( $subscription->post_status, $statuses, true ) ) || $subscription->post_status === $statuses ) {
				$filtered_subscriptions[] = $subscription;
			}
		}

		// Final filtering (only ids, post, or full data)
		$subscriptions = [];
		foreach ( $filtered_subscriptions as $subscription ) {
			if ( $only_ids ) {
				$subscriptions[] = $subscription->ID;
			} elseif ( $final_args['return'] === 'subscription_data' ) {
				$subs_id           = $subscription->ID;
				$subscription_data = self::get_subscription_data( $subs_id );
				$subscriptions[]   = $subscription_data;
			} else {
				$subscriptions[] = $subscription;
			}
		}

		return $subscriptions;
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
			'post_status' => $status,
			'fields'      => 'ids',
			'product_id'  => $product_id,
		);

		$posts = self::get_subscriptions( $args );
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
		$type = self::get_typos( $item_meta['time'], $item_meta['type'], true );

		$formatted_price = wc_price(
			$price,
			array(
				'currency' => $order->get_currency(),
			)
		) . ' / ' . $time . ucfirst( $type );

		if ( $display_trial ) {
			$has_trial = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;
			$trial     = $item_meta['trial'] ?? '';

			if ( $has_trial ) {
				// translators: %s: trial period.
				$trial_html       = '<br/><small> ' . sprintf( __( '+ %s free trial!', 'subscription' ), $trial ) . '</small>';
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
		$payment_type  = function_exists( 'subscrpt_get_payment_type' ) ? subscrpt_get_payment_type( $subscription_id ) : 'recurring';
		$max_payments  = function_exists( 'subscrpt_get_max_payments' ) ? subscrpt_get_max_payments( $subscription_id ) : 0;
		$payments_made = function_exists( 'subscrpt_count_payments_made' ) ? subscrpt_count_payments_made( $subscription_id ) : 0;

		$comment_content = '';
		$activity_type   = '';

		if ( 'split_payment' === $payment_type && $max_payments ) {
			$comment_content = sprintf(
				/* translators: %1$s: order id, %2$d: payment number, %3$d: total payments */
				__( 'Split payment installment %2$d of %3$d. Order %1$s created for subscription.', 'subscription' ),
				$order_id,
				$payments_made + 1, // +1 because this is a new renewal
				$max_payments
			);
			$activity_type = __( 'Split Payment - Renewal', 'subscription' );
		} else {
			$comment_content = sprintf(
				/* translators: order id. */
				__( 'The order %s has been created for the subscription', 'subscription' ),
				$order_id
			);
			$activity_type = __( 'Renewal Order', 'subscription' );
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
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'renewal_order' );

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
		$activity_type   = '';

		if ( 'split_payment' === $payment_type && $max_payments ) {
			$comment_content = sprintf(
				/* translators: %1$s: order id, %2$d: max payments */
				__( 'Split payment subscription created successfully. Order: %1$s. Total installments: %2$d.', 'subscription' ),
				$order_item->get_order_id(),
				$max_payments
			);
			$activity_type = __( 'Split Payment - New Subscription', 'subscription' );
		} else {
			$comment_content = sprintf(
				/* translators: Order Id. */
				__( 'Subscription successfully created. Order is %s', 'subscription' ),
				$order_item->get_order_id()
			);
			$activity_type = __( 'New Subscription', 'subscription' );
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
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'subs_created' );

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
	public static function get_recurrs_from_cart( $cart_items ) {
		$recurrs = array();
		foreach ( $cart_items as $key => $cart_item ) {
			$product = $cart_item['data'];
			if ( $product->is_type( 'simple' ) && isset( $cart_item['subscription'] ) ) {
				$cart_subscription = $cart_item['subscription'];
				$type              = $cart_subscription['type'];

				// Total amount with tax
				$total_amount = wc_get_price_including_tax( $product, [ 'qty' => 1 ] );
				$price_html   = wc_price( (float) $total_amount ) . '/ ' . $type;

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

		return apply_filters( 'wpsubs_cart_recurring_items', $recurrs, $cart_items );
	}

	/**
	 * Check if the order has subscription item.
	 *
	 * @param \WC_Order|int $order Order object.
	 */
	public static function order_has_subscription_item( $order ) {
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		$is_subscription_order = false;
		foreach ( $order->get_items() as $item ) {
			$item_data         = $item->get_data() ?? array();
			$item_product_id   = $item_data['product_id'] ?? 0;
			$item_variation_id = $item_data['variation_id'] ?? 0;

			$product_id = $item_variation_id ? $item_variation_id : $item_product_id;
			$product    = sdevs_get_subscription_product( $product_id );

			if ( $product && $product->is_enabled() ) {
				$is_subscription_order = true;
				break;
			}
		}
		return $is_subscription_order;
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
			Action::status( 'expired', $subscription_id );

			error_log( "WPS: Maximum payment limit reached for subscription #{$subscription_id}. No renewal order created." );
			return false;
		}

		$order_item_id = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
		$order_id      = wc_get_order_id_by_order_item_id( $order_item_id );
		$old_order     = self::check_order_for_renewal( $order_id );

		if ( ! $old_order ) {
			wp_subscrpt_write_log( "Old order not found for renewal. Skipping creating renewal order. [ Subscription ID: {$subscription_id} ]" );
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
			wp_subscrpt_write_log( "Failed to create renewal order. [ Subscription ID: {$subscription_id} ]" );
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
		$stripe_supported_methods = Stripe::WPSUBS_SUPPORTED_METHODS;
		if ( in_array( $old_order->get_payment_method(), $stripe_supported_methods, true ) ) {
			$stripe_subscription_id = $old_order->get_meta( '_stripe_subscription_id' );
			if ( $stripe_subscription_id ) {
				$new_order->update_meta_data( '_stripe_subscription_id', $stripe_subscription_id );
				$new_order->save();
			}
		}

		// Allow modification of the renewal order before saving.
		$new_order = apply_filters( 'subscrpt_before_saving_renewal_order', $new_order, $old_order, $subscription_id );

		// Save the new order.
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

		return $new_order;
	}

	/**
	 * Get subscription total price.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return float
	 */
	public static function get_subscription_total( $subscription_id ) {
		return (float) get_post_meta( $subscription_id, '_subscrpt_price', true );
	}

	/**
	 * Get subscription status.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return string
	 */
	public static function get_subscription_status( $subscription_id ) {
		return get_post_status( $subscription_id );
	}

	/**
	 * Check if subscription has status.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $status Status to check.
	 * @return bool
	 */
	public static function subscription_has_status( $subscription_id, $status ) {
		return self::get_subscription_status( $subscription_id ) === $status;
	}

	/**
	 * Check if subscription needs payment.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return bool
	 */
	public static function subscription_needs_payment( $subscription_id ) {
		return true; // Always true for now
	}

	/**
	 * Get product period (timing option).
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_product_period( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? $product->get_meta( '_subscrpt_timing_option' ) : '';
	}

	/**
	 * Get product interval (timing per).
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function get_product_interval( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? (int) $product->get_meta( '_subscrpt_timing_per' ) : 1;
	}

	/**
	 * Get product length (max payments).
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function get_product_length( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? (int) $product->get_meta( '_subscrpt_max_no_payment' ) : 0;
	}

	/**
	 * Get product trial length.
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public static function get_product_trial_length( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? (int) $product->get_meta( '_subscrpt_trial_timing_per' ) : 0;
	}

	/**
	 * Get product signup fee.
	 *
	 * @param int $product_id Product ID.
	 * @return float
	 */
	public static function get_product_signup_fee( $product_id ) {
		$product = wc_get_product( $product_id );
		return $product ? (float) $product->get_meta( '_subscrpt_signup_fee' ) : 0.0;
	}

	/**
	 * Get first renewal payment time.
	 *
	 * @param int $product_id Product ID.
	 * @return int Timestamp
	 */
	public static function get_first_renewal_payment_time( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return 0;
		}

		$trial_period = $product->get_meta( '_subscrpt_trial_timing_per' );
		$trial_option = $product->get_meta( '_subscrpt_trial_timing_option' );

		if ( ! empty( $trial_period ) && ! empty( $trial_option ) ) {
			return strtotime( "+{$trial_period} {$trial_option}" );
		}

		return 0;
	}

	/**
	 * Update subscription next payment date.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $new_date New Date string.
	 * @return void
	 */
	public static function update_subscription_next_payment_date( $subscription_id, $new_date ) {
		update_post_meta( $subscription_id, '_subscrpt_next_date', strtotime( $new_date ) );
	}

	/**
	 * Cancel subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public static function cancel_subscription( $subscription_id ) {
		Action::status( 'cancelled', $subscription_id );
	}

	/**
	 * Pause subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public static function pause_subscription( $subscription_id ) {
		Action::status( 'on-hold', $subscription_id );
	}

	/**
	 * Resume subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 * @return void
	 */
	public static function resume_subscription( $subscription_id ) {
		Action::status( 'active', $subscription_id );
	}

	/**
	 * Mark subscription payment as complete.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $payment_id Payment/Transaction ID.
	 * @return void
	 */
	public static function subscription_payment_complete( $subscription_id, $payment_id ) {
		if ( 'active' !== get_post_status( $subscription_id ) ) {
			Action::status( 'active', $subscription_id );
		}

		// Allow payment gateways to add their own comments/notes
		do_action( 'subscrpt_subscription_payment_completed', $subscription_id, $payment_id );
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

		$is_auto_renew = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		$is_auto_renew = in_array( $is_auto_renew, array( 1, '1' ), true );

		$is_global_auto_renew = get_option( 'wp_subscription_stripe_auto_renew', '1' );
		$is_global_auto_renew = in_array( $is_global_auto_renew, array( 1, '1' ), true );

		$stripe_supported_methods = Stripe::WPSUBS_SUPPORTED_METHODS;
		$old_method               = $old_order->get_payment_method();
		$is_stripe_pm             = ! empty( $old_method ) && in_array( $old_method, $stripe_supported_methods, true );

		$has_stripe_meta = ! empty( $old_order->get_meta( '_stripe_customer_id' ) ) || ! empty( $old_order->get_meta( '_stripe_source_id' ) );

		$stripe_enabled = ( ( $is_stripe_pm || $has_stripe_meta ) && $is_auto_renew && $is_global_auto_renew && subscrpt_is_auto_renew_enabled() );

		if ( $stripe_enabled ) {
			$new_order->update_meta_data( '_stripe_customer_id', $old_order->get_meta( '_stripe_customer_id' ) );
			$new_order->update_meta_data( '_stripe_source_id', $old_order->get_meta( '_stripe_source_id' ) );
			$new_order->set_payment_method( $old_order->get_payment_method() );
			$new_order->set_payment_method_title( $old_order->get_payment_method_title() );

			// Add debug log.
			wp_subscrpt_write_debug_log( "Stripe metadata cloned for renewal order #{$new_order->get_id()} from old order #{$old_order->get_id()}" );
		} else {
			wp_subscrpt_write_log( "Stripe metadata not processed. Auto renewal may fail. [ Renewal order #{$new_order->get_id()}, Old order #{$old_order->get_id()} ]" );
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
		update_comment_meta( $comment_id, '_subscrpt_activity_type', 'renewal_order' );
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

		$subs_post = get_post( $subscription_id );
		$user_id   = (int) $subs_post->post_author ?? 0;

		$product_id = get_post_meta( $subscription_id, '_subscrpt_product_id', true );
		$product_id = ! empty( $product_id ) ? (int) $product_id : 0;

		$variation_id = get_post_meta( $subscription_id, '_subscrpt_variation_id', true );
		$variation_id = ! empty( $variation_id ) ? (int) $variation_id : 0;

		$chk_product_id = $variation_id ? $variation_id : $product_id;

		$status = get_post_status( $subscription_id );
		$price  = get_post_meta( $subscription_id, '_subscrpt_price', true );

		$signup_fee = get_post_meta( $subscription_id, '_subscrpt_signup_fee', true );
		$signup_fee = ! empty( $signup_fee ) ? $signup_fee : 0;

		$order_id      = get_post_meta( $subscription_id, '_subscrpt_order_id', true );
		$order_item_id = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );

		$can_user_cancel = in_array( get_post_meta( $subscription_id, '_subscrpt_user_cancel', true ), array( 1, '1', 'true', 'yes' ), true );

		$start_datetime = (int) get_post_meta( $subscription_id, '_subscrpt_start_date', true );
		$start_date     = ! empty( $start_datetime ) ? gmdate( DATE_RFC2822, $start_datetime ) : null;

		$next_datetime = (int) get_post_meta( $subscription_id, '_subscrpt_next_date', true );
		$next_date     = ! empty( $next_datetime ) ? gmdate( DATE_RFC2822, $next_datetime ) : null;

		$timing_per = get_post_meta( $subscription_id, '_subscrpt_timing_per', true );
		$timing_per = empty( $timing_per ) ? get_post_meta( $chk_product_id, '_subscrpt_timing_per', true ) : $timing_per;

		$timing_option = get_post_meta( $subscription_id, '_subscrpt_timing_option', true );
		$timing_option = empty( $timing_option ) ? get_post_meta( $chk_product_id, '_subscrpt_timing_option', true ) : $timing_option;

		$trial_timing_per = get_post_meta( $subscription_id, '_subscrpt_trial_timing_per', true );
		$trial_timing_per = empty( $trial_timing_per ) ? get_post_meta( $chk_product_id, '_subscrpt_trial_timing_per', true ) : $trial_timing_per;

		$trial_timing_option = get_post_meta( $subscription_id, '_subscrpt_trial_timing_option', true );
		$trial_timing_option = empty( $trial_timing_option ) ? get_post_meta( $chk_product_id, '_subscrpt_trial_timing_option', true ) : $trial_timing_option;

		$is_auto_renew = in_array( get_post_meta( $subscription_id, '_subscrpt_auto_renew', true ), array( 1, '1', 'true', 'yes' ), true );

		$default_grace_period = (int) get_option( 'subscrpt_default_payment_grace_period', '7' );
		$default_grace_period = subscrpt_pro_activated() ? $default_grace_period : 0;
		$grace_end_datetime   = $next_datetime + ( $default_grace_period * DAY_IN_SECONDS );
		$grace_end_date       = gmdate( DATE_RFC2822, $grace_end_datetime );
		$grace_remaining_days = ceil( max( 0, $grace_end_datetime - time() ) / DAY_IN_SECONDS );

		$subscription_data = array(
			'id'              => $subscription_id,
			'status'          => $status,
			'schedule'        => array(
				'timing_per'    => $timing_per,
				'timing_option' => $timing_option,
			),
			'price'           => $price,
			'signup_fee'      => $signup_fee,
			'start_date'      => $start_date,
			'next_date'       => $next_date,
			'product'         => array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
			),
			'order'           => array(
				'order_id'      => $order_id,
				'order_item_id' => $order_item_id,
			),
			'can_user_cancel' => $can_user_cancel,
			'is_auto_renew'   => (bool) $is_auto_renew,
			'user_id'         => $user_id,
		);

		if ( ! empty( $trial_timing_per ) ) {
			$subscription_data['trial'] = array(
				'timing_per'    => $trial_timing_per,
				'timing_option' => $trial_timing_option,
			);
		}

		if (
			! in_array( strtolower( $status ), array( 'cancelled', 'pending' ), true )
			&& $next_datetime - time() <= 0
			&& (int) $default_grace_period > 0
		) {
			$subscription_data['grace_period'] = array(
				'remaining_days' => $grace_remaining_days,
				'end_date'       => $grace_end_date,
			);
		}

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
				array(
					$table_name,
					$subscription_id,
				)
			)
		);

		return $order_histories;
	}

	/**
	 * Get parent order from subscription.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public static function get_parent_order( int $subscription_id ) {
		$related_orders = self::get_related_orders( $subscription_id );
		$last_order     = end( $related_orders );

		if ( ! $last_order || strtolower( $last_order->type ?? '' ) !== 'new' ) {
			foreach ( $related_orders as $order ) {
				if ( strtolower( $order->type ?? '' ) === 'new' ) {
					$last_order = $order;
					break;
				}
			}
		}

		$parent_order_id = $last_order->order_id ?? 0;
		if ( $parent_order_id ) {
			$parent_order = wc_get_order( $parent_order_id );
		}
		return $parent_order_id ? $parent_order : null;
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

		$new_order_item_id = $new_order->add_product(
			$product,
			$order_item->get_quantity(),
			$product_args
		);
		wc_update_order_item_meta(
			$new_order_item_id,
			'_subscrpt_meta',
			array(
				'time'  => $product_meta['time'],
				'type'  => $product_meta['type'],
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
				return wc_add_notice( __( 'Subscription renewal isn\'t possible due to previous order not completed or deletion.', 'subscription' ), 'error' );
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
