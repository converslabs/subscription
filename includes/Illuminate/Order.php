<?php

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class Order
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class Order {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'woocommerce_admin_order_item_headers', array( $this, 'register_custom_column' ) );
		add_action( 'woocommerce_admin_order_item_values', array( $this, 'add_column_value' ), 10, 2 );
		add_action( 'woocommerce_before_order_itemmeta', array( $this, 'add_order_item_data' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_changed' ) );
		add_action( 'woocommerce_before_delete_order', array( $this, 'delete_the_subscription' ) );
		add_action( 'subscrpt_subscription_activated', array( $this, 'generate_dates_for_subscription' ) );

		// Fix order delivery info.
		add_action( 'subscrpt_queue_order_delivery_info_recovery', [ Helper::class,'recover_order_delivery_info' ], 10, 2 );
	}

	/**
	 * Generate start, next and trial dates.
	 *
	 * @param int $subscription_id Subscription Id.
	 *
	 * @return void
	 */
	public function generate_dates_for_subscription( $subscription_id ) {
		$order_item_id        = get_post_meta( $subscription_id, '_subscrpt_order_item_id', true );
		$subscription_history = Helper::get_subscription_from_order_item_id( $order_item_id );

		$order_item_meta = wc_get_order_item_meta( $order_item_id, '_subscrpt_meta' );
		$type            = Helper::get_typos( 1, $order_item_meta['type'] );
		$trial           = get_post_meta( $subscription_id, '_subscrpt_trial', true );
		$recurr_timing   = ( $order_item_meta['time'] ?? 1 ) . ' ' . $type;

		if ( 'new' === $subscription_history->type ) {
			$start_date = time();
			$next_date  = sdevs_wp_strtotime( $recurr_timing, $start_date );

			if ( $trial && ! empty( $trial ) ) {
				$trial_started = get_post_meta( $subscription_id, '_subscrpt_trial_started', true );
				$trial_ended   = get_post_meta( $subscription_id, '_subscrpt_trial_ended', true );

				if ( empty( $trial_started ) && empty( $trial_ended ) ) {
					$start_date = sdevs_wp_strtotime( $trial );
					$next_date  = $start_date;

					update_post_meta( $subscription_id, '_subscrpt_trial_started', time() );
					update_post_meta( $subscription_id, '_subscrpt_trial_ended', $start_date );
					update_post_meta( $subscription_id, '_subscrpt_trial_mode', 'on' );
				}
			}

			update_post_meta( $subscription_id, '_subscrpt_start_date', $start_date );

		} elseif ( 'renew' === $subscription_history->type ) {
			if ( $trial ) {
				delete_post_meta( $subscription_id, '_subscrpt_trial' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_mode' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_started' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_ended' );
			}

			$next_date = sdevs_wp_strtotime( $recurr_timing, time() );

		} elseif ( 'early-renew' === $subscription_history->type ) {
			if ( $trial ) {
				delete_post_meta( $subscription_id, '_subscrpt_trial' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_mode' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_started' );
				delete_post_meta( $subscription_id, '_subscrpt_trial_ended' );
			}

			$next_date = sdevs_wp_strtotime( $recurr_timing, time() );
		}

		// Allow filtering of next due date logic
		$next_date = apply_filters( 'subscrpt_split_payment_next_due_date', $next_date, $subscription_id, $recurr_timing, $subscription_history->type );

		update_post_meta( $subscription_id, '_subscrpt_next_date', $next_date );
	}

	/**
	 * Add custom column on order item.
	 *
	 * @return void
	 */
	public function register_custom_column() {
		?>
		<th class="item_recurring sortable" data-sort="float"><?php esc_html_e( 'Recurring', 'subscription' ); ?></th>
		<?php
	}

	/**
	 * Display data for custom column.
	 *
	 * @param \WC_Product    $product Product Object.
	 * @param \WC_Order_Item $item Order Item.
	 *
	 * @return void
	 */
	public function add_column_value( $product, $item ) {
		if ( ! method_exists( $item, 'get_id' ) || ! method_exists( $item, 'get_subtotal' ) ) {
			return;
		}

		$subtotal        = '-';
		$item_id         = $item->get_id();
		$subscription_id = Helper::get_subscription_from_order_item_id( $item->get_id() );

		if ( ! $subscription_id ) {
			echo "<td class='item_recurring' width='15%'>-</td>";
			return;
		}
		$subscription_id = $subscription_id->subscription_id;

		$price    = get_post_meta( $subscription_id, '_subscrpt_price', true );
		$subtotal = Helper::format_price_with_order_item( $price, $item_id );
		?>
		<td class="item_recurring" width="15%">
			<div class="view">
				<?php echo wp_kses_post( $subtotal ); ?>
			</div>
		</td>
		<?php
	}

	public function add_order_item_data( $item_id, $item, $product ) {
		if ( ! $product ) {
			return;
		}

		$item_meta = wc_get_order_item_meta( $item_id, '_subscrpt_meta', true );

		if ( ! $item_meta || ! is_array( $item_meta ) ) {
			return false;
		}

		$trial     = $item_meta['trial'];
		$has_trial = isset( $item_meta['trial'] ) && strlen( $item_meta['trial'] ) > 2;

		if ( $has_trial ) {
			echo '<br/><small> + Got ' . $trial . ' free trial!</small>';
		}
	}

	/**
	 * Take some actions based on order status changed.
	 *
	 * @param int $order_id Order Id.
	 */
	public function order_status_changed( $order_id ) {
		$order       = wc_get_order( $order_id );
		$post_status = 'active';

		switch ( $order->get_status() ) {
			case 'on-hold':
			case 'pending':
				$post_status = 'pending';
				break;

			case 'refunded':
			case 'failed':
			case 'cancelled':
				$post_status = 'cancelled';
				break;

			default:
				$post_status = 'active';
				break;
		}
		$post_status = apply_filters( 'subscript_order_status_to_post_status', $post_status, $order );

		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		// @phpcs:ignore
		$histories = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id=%d', array( $table_name, $order_id ) ) );

		foreach ( $histories as $history ) {
			if ( 'new' === $history->type || 'renew' === $history->type ) {
				wp_update_post(
					array(
						'ID'          => $history->subscription_id,
						'post_status' => $post_status,
					)
				);

				// Increment renewal count for completed renewal orders (wps-pro)
				if ( 'renew' === $history->type && 'active' === $post_status && function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated() ) {
					if ( class_exists( '\\SpringDevs\\SubscriptionPro\\Illuminate\\LimitChecker' ) ) {
						\SpringDevs\SubscriptionPro\Illuminate\LimitChecker::increment_renewal_count( $history->subscription_id );
					}
				}

				// Add enhanced split payment activity logging
				$this->add_split_payment_activity_note( $history->subscription_id, $history->type, $post_status, $order );

				Action::write_comment( $post_status, $history->subscription_id );
			} else {
				do_action( 'subscrpt_order_status_changed', $order, $history );
			}
		}
	}

	/**
	 * Delete the subscription.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public function delete_the_subscription( $order_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';

		$histories = Helper::get_subscriptions_from_order( $order_id );
		foreach ( (array) $histories as $history ) {
			$subscription_order_id = get_post_meta( $history->subscription_id, '_subscrpt_order_id', true );
			if ( (int) $subscription_order_id === $order_id ) {
				wp_delete_post( $history->subscription_id, true );
			}
		}

		// phpcs:ignore
		$wpdb->delete( $table_name, array( 'order_id' => $order_id ), array( '%d' ) );
	}

	/**
	 * Add enhanced split payment activity note with payment progress and access information.
	 *
	 * @param int       $subscription_id Subscription ID.
	 * @param string    $history_type    History type (new, renew, etc.).
	 * @param string    $post_status     Post status.
	 * @param \WC_Order $order           WooCommerce order object.
	 */
	private function add_split_payment_activity_note( $subscription_id, $history_type, $post_status, $order ) {
		// Only add enhanced notes for active subscriptions
		if ( 'active' !== $post_status ) {
			return;
		}

		// Check if this is a split payment subscription
		if ( ! function_exists( 'subscrpt_get_payment_type' ) ) {
			return;
		}

		$payment_type = subscrpt_get_payment_type( $subscription_id );
		if ( 'split_payment' !== $payment_type ) {
			return;
		}

		// Get payment progress information
		$max_payments       = function_exists( 'subscrpt_get_max_payments' ) ? subscrpt_get_max_payments( $subscription_id ) : 0;
		$payments_made      = function_exists( 'subscrpt_count_payments_made' ) ? subscrpt_count_payments_made( $subscription_id ) : 0;
		$remaining_payments = function_exists( 'subscrpt_get_remaining_payments' ) ? subscrpt_get_remaining_payments( $subscription_id ) : 0;

		// Determine payment number for this order
		$payment_number = $payments_made;
		$order_total    = $order->get_total();
		$order_currency = $order->get_currency();

		// Create enhanced activity note
		$comment_content = '';
		$activity_type   = '';

		if ( 'new' === $history_type ) {
			$comment_content = sprintf(
				/* translators: %1$d: payment number, %2$d: total payments, %3$s: amount, %4$s: currency */
				__( 'Split payment %1$d of %2$d received (%3$s %4$s). Initial access granted.', 'subscription' ),
				$payment_number,
				$max_payments,
				$order_total,
				$order_currency
			);
			$activity_type = __( 'Split Payment - Initial', 'subscription' );
		} elseif ( 'renew' === $history_type ) {
			$comment_content = sprintf(
				/* translators: %1$d: payment number, %2$d: total payments, %3$s: amount, %4$s: currency, %5$d: remaining */
				__( 'Split payment %1$d of %2$d received (%3$s %4$s). %5$d payments remaining.', 'subscription' ),
				$payment_number,
				$max_payments,
				$order_total,
				$order_currency,
				$remaining_payments
			);
			$activity_type = __( 'Split Payment - Installment', 'subscription' );
		}

		// Add the enhanced activity note
		if ( $comment_content ) {
			$comment_id = wp_insert_comment(
				array(
					'comment_author'  => 'Subscription for WooCommerce',
					'comment_content' => $comment_content,
					'comment_post_ID' => $subscription_id,
					'comment_type'    => 'order_note',
				)
			);
			update_comment_meta( $comment_id, '_subscrpt_activity', $activity_type );
			update_comment_meta( $comment_id, '_subscrpt_activity_type', 'split_payment' );

			// Add order note with split payment context
			$order_note = sprintf(
				/* translators: %1$d: payment number, %2$d: total payments, %3$d: subscription id */
				__( 'Split payment %1$d of %2$d received for subscription #%3$d', 'subscription' ),
				$payment_number,
				$max_payments,
				$subscription_id
			);
			$order->add_order_note( $order_note );
		}

		// Add payment milestone notes for significant progress
		$this->add_payment_milestone_notes( $subscription_id, $payments_made, $max_payments );
	}

	/**
	 * Add payment milestone notes for significant progress (25%, 50%, 75%, 100%).
	 *
	 * @param int $subscription_id Subscription ID.
	 * @param int $payments_made   Number of payments made.
	 * @param int $max_payments    Maximum number of payments.
	 */
	private function add_payment_milestone_notes( $subscription_id, $payments_made, $max_payments ) {
		if ( ! $max_payments || $max_payments <= 0 ) {
			return;
		}

		$percentage    = round( ( $payments_made / $max_payments ) * 100 );
		$milestone_key = '_subscrpt_milestone_' . $percentage . '_logged';

		// Check if this milestone has already been logged
		if ( get_post_meta( $subscription_id, $milestone_key, true ) ) {
			return;
		}

		$milestone_note = '';
		$activity_type  = '';

		switch ( $percentage ) {
			case 25:
				$milestone_note = sprintf(
					/* translators: %1$d: payments made, %2$d: total payments */
					__( 'Payment milestone reached: %1$d of %2$d payments completed (25%%).', 'subscription' ),
					$payments_made,
					$max_payments
				);
				$activity_type = __( 'Payment Milestone - 25%', 'subscription' );
				break;

			case 50:
				$milestone_note = sprintf(
					/* translators: %1$d: payments made, %2$d: total payments */
					__( 'Payment milestone reached: %1$d of %2$d payments completed (50%%). Halfway there!', 'subscription' ),
					$payments_made,
					$max_payments
				);
				$activity_type = __( 'Payment Milestone - 50%', 'subscription' );
				break;

			case 75:
				$milestone_note = sprintf(
					/* translators: %1$d: payments made, %2$d: total payments */
					__( 'Payment milestone reached: %1$d of %2$d payments completed (75%%). Almost complete!', 'subscription' ),
					$payments_made,
					$max_payments
				);
				$activity_type = __( 'Payment Milestone - 75%', 'subscription' );
				break;

			case 100:
				$milestone_note = sprintf(
					/* translators: %1$d: payments made, %2$d: total payments */
					__( 'Payment milestone reached: %1$d of %2$d payments completed (100%%). Split payment plan complete!', 'subscription' ),
					$payments_made,
					$max_payments
				);
				$activity_type = __( 'Payment Milestone - 100%', 'subscription' );
				break;
		}

		// Add milestone note if applicable
		if ( $milestone_note ) {
			$comment_id = wp_insert_comment(
				array(
					'comment_author'  => 'Subscription for WooCommerce',
					'comment_content' => $milestone_note,
					'comment_post_ID' => $subscription_id,
					'comment_type'    => 'order_note',
				)
			);
			update_comment_meta( $comment_id, '_subscrpt_activity', $activity_type );
			update_comment_meta( $comment_id, '_subscrpt_activity_type', 'split_payment' );

			// Mark milestone as logged
			update_post_meta( $subscription_id, $milestone_key, current_time( 'timestamp' ) );
		}
	}
}
