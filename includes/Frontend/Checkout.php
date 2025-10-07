<?php
/**
 * Checkout handlers for subscription plugin.
 *
 * @package wp_subscription
 */

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Checkout class
 */
class Checkout {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Guest checkout validation.
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_guest_checkout' ] );
		add_action( 'woocommerce_store_api_cart_errors', [ $this, 'validate_guest_checkout_storeapi' ] );

		add_action( 'woocommerce_checkout_order_processed', array( $this, 'create_subscription_after_checkout' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'create_subscription_after_checkout_storeapi' ) );
		add_action( 'woocommerce_resume_order', array( $this, 'remove_subscriptions' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_product_meta' ), 10, 3 );
	}

	/**
	 * Check if cart/order has subscription and guest checkout are allowed.
	 */
	public function is_subs_and_guest_checkout_allowed() {
		$is_user_logged_in         = is_user_logged_in();
		$is_guest_checkout_allowed = in_array( get_option( 'wp_subscription_allow_guest_checkout', '1' ), [ 1, '1', 'yes', 'on' ], true );
		$cart_have_subscription    = false;

		// Check in cart.
		if ( function_exists( 'WC' ) ) {
			$cart_items             = WC()->cart->get_cart();
			$recurrs                = Helper::get_recurrs_from_cart( $cart_items );
			$cart_have_subscription = count( $recurrs ) > 0;
		}

		if ( $cart_have_subscription ) {
			return $is_user_logged_in || $is_guest_checkout_allowed;
		} else {
			return true;
		}
	}

	/**
	 * Validate guest checkout.
	 */
	public function validate_guest_checkout() {
		if ( ! $this->is_subs_and_guest_checkout_allowed() ) {
			wc_add_notice( __( 'You must be logged in to subscribe.', 'wp_subscription' ), 'error' );
			return;
		}
	}

	/**
	 * Validate guest checkout on storeAPI.
	 *
	 * @param \WP_Error $errors Errors object.
	 * @return \WP_Error
	 */
	public function validate_guest_checkout_storeapi( $errors ) {
		if ( ! $this->is_subs_and_guest_checkout_allowed() ) {
			$errors->add( 'wp_subscription_login_required', __( 'You must be logged in to subscribe.', 'wp_subscription' ) );
			return $errors;
		}
	}

	/**
	 * Create subscription during checkout on storeAPI.
	 *
	 * @param \WC_Order $order Order Object.
	 */
	public function create_subscription_after_checkout_storeapi( $order ) {
		$this->create_subscription_after_checkout( $order->get_id() );
	}

	/**
	 * Create subscription during checkout.
	 *
	 * @param int $order_id Order ID.
	 */
	public function create_subscription_after_checkout( $order_id ) {
		// Assign user to order if needed.
		$this->maybe_assign_user_to_order( $order_id );

		$order = wc_get_order( $order_id );

		// Grab the post status based on order status.
		$post_status = 'active';
		switch ( $order->get_status() ) {
			case 'on-hold':
			case 'pending':
				$post_status = 'pending';
				break;

			case 'failed':
			case 'cancelled':
				$post_status = 'cancelled';
				break;

			default:
				break;
		}

		// Create subscription for order items.
		$order_items = $order->get_items();
		foreach ( $order_items as $order_item ) {
			$product = sdevs_get_subscription_product( $order_item['product_id'] );

			if ( $product->is_type( 'simple' ) && ! subscrpt_pro_activated() ) {
				if ( $product->is_enabled() ) {
					$is_renew = isset( $order_item['renew_subscrpt'] );

					$timing_option = $product->get_timing_option();
					$trial         = $product->get_trial();

					wc_update_order_item_meta(
						$order_item->get_id(),
						'_subscrpt_meta',
						array(
							'time'  => 1,
							'type'  => $timing_option,
							'trial' => $trial,
						)
					);

					// Renew subscription if need!
					$renew_subscription_id    = Helper::subscription_exists( $product->get_id(), 'expired' );
					$selected_subscription_id = null;
					if ( $is_renew && $renew_subscription_id && 'cancelled' !== $post_status ) {
						$selected_subscription_id = $renew_subscription_id;
						Helper::process_order_renewal(
							$selected_subscription_id,
							$order_id,
							$order_item->get_id()
						);
					} else {
						$selected_subscription_id = Helper::process_new_subscription_order( $order_item, $post_status, $product );
					}

					if ( $selected_subscription_id ) {
						// product related.
						update_post_meta( $selected_subscription_id, '_subscrpt_timing_option', $timing_option );
						update_post_meta( $selected_subscription_id, '_subscrpt_price', $product->get_price() * $order_item['quantity'] );
						update_post_meta( $selected_subscription_id, '_subscrpt_user_cancel', $product->get_meta( '_subscrpt_user_cancel' ) );

						// order related.
						update_post_meta( $selected_subscription_id, '_subscrpt_order_id', $order_id );
						update_post_meta( $selected_subscription_id, '_subscrpt_order_item_id', $order_item->get_id() );

						// subscription related.
						update_post_meta( $selected_subscription_id, '_subscrpt_trial', $trial );

						do_action( 'subscrpt_order_checkout', $selected_subscription_id, $order_item );
					}
				}
			}

			do_action( 'subscrpt_product_checkout', $order_item, $product, $post_status );
		}
	}

	/**
	 * Maybe assign user to order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_assign_user_to_order( $order_id ) {
		// Don't proceed if guest checkout is not allowed.
		$is_guest_checkout_allowed = in_array( get_option( 'wp_subscription_allow_guest_checkout', '1' ), [ 1, '1', 'yes', 'on' ], true );
		if ( ! $is_guest_checkout_allowed ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Don't proceed if order already have an user.
		if ( $order->get_customer_id() ) {
			return;
		}

		// Order not have an user. Proceed to create/assign user.
		$email      = $order->get_billing_email();
		$first_name = $order->get_billing_first_name();
		$last_name  = $order->get_billing_last_name();

		// Check if user exists with email.
		$user    = get_user_by( 'email', $email );
		$user_id = $user ? $user->ID : 0;

		if ( ! $user ) {
			$username = sanitize_user( current( explode( '@', $email ) ), true );
			if ( username_exists( $username ) ) {
				$username .= '_' . wp_generate_password( 4, false );
			}

			// Create user.
			$args = [
				'user_login'   => $username,
				'user_email'   => $email,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => trim( "$first_name $last_name" ),
				'role'         => 'customer',
			];

			$user_id = wp_insert_user( $args );

			if ( is_wp_error( $user_id ) ) {
				// Translators: %s: Error message.
				$order->add_order_note( __( 'Failed to auto-create user. Please assign a user to the order manually. Otherwise, renewals may not work properly.', 'wp_subscription' ) );

				wp_subscrpt_write_log( 'Failed to auto-create user during checkout. Error: ' . $user_id->get_error_message() );
				return;
			}

			$order->add_order_note( __( 'A user account auto created and assigned to the order.', 'wp_subscription' ) );

			// User creation notification.
			do_action( 'woocommerce_created_customer', $user_id, [], true );

			// Copy billing data to user meta.
			$billing_first_name = $order->get_billing_first_name();
			$billing_last_name  = $order->get_billing_last_name();
			$billing_company    = $order->get_billing_company();
			$billing_address_1  = $order->get_billing_address_1();
			$billing_address_2  = $order->get_billing_address_2();
			$billing_city       = $order->get_billing_city();
			$billing_postcode   = $order->get_billing_postcode();
			$billing_country    = $order->get_billing_country();
			$billing_state      = $order->get_billing_state();
			$billing_email      = $order->get_billing_email();
			$billing_phone      = $order->get_billing_phone();

			update_user_meta( $user_id, 'billing_first_name', $billing_first_name );
			update_user_meta( $user_id, 'billing_last_name', $billing_last_name );
			update_user_meta( $user_id, 'billing_company', $billing_company );
			update_user_meta( $user_id, 'billing_address_1', $billing_address_1 );
			update_user_meta( $user_id, 'billing_address_2', $billing_address_2 );
			update_user_meta( $user_id, 'billing_city', $billing_city );
			update_user_meta( $user_id, 'billing_postcode', $billing_postcode );
			update_user_meta( $user_id, 'billing_country', $billing_country );
			update_user_meta( $user_id, 'billing_state', $billing_state );
			update_user_meta( $user_id, 'billing_email', $billing_email );
			update_user_meta( $user_id, 'billing_phone', $billing_phone );

			// Copy shipping data to user meta.
			$shipping_first_name = ! empty( $order->get_shipping_first_name() ) ? $order->get_shipping_first_name() : $billing_first_name;
			$shipping_last_name  = ! empty( $order->get_shipping_last_name() ) ? $order->get_shipping_last_name() : $billing_last_name;
			$shipping_company    = ! empty( $order->get_shipping_company() ) ? $order->get_shipping_company() : $billing_company;
			$shipping_address_1  = ! empty( $order->get_shipping_address_1() ) ? $order->get_shipping_address_1() : $billing_address_1;
			$shipping_address_2  = ! empty( $order->get_shipping_address_2() ) ? $order->get_shipping_address_2() : $billing_address_2;
			$shipping_city       = ! empty( $order->get_shipping_city() ) ? $order->get_shipping_city() : $billing_city;
			$shipping_postcode   = ! empty( $order->get_shipping_postcode() ) ? $order->get_shipping_postcode() : $billing_postcode;
			$shipping_country    = ! empty( $order->get_shipping_country() ) ? $order->get_shipping_country() : $billing_country;
			$shipping_state      = ! empty( $order->get_shipping_state() ) ? $order->get_shipping_state() : $billing_state;
			$shipping_phone      = ! empty( $order->get_shipping_phone() ) ? $order->get_shipping_phone() : $billing_phone;

			update_user_meta( $user_id, 'shipping_first_name', $shipping_first_name );
			update_user_meta( $user_id, 'shipping_last_name', $shipping_last_name );
			update_user_meta( $user_id, 'shipping_company', $shipping_company );
			update_user_meta( $user_id, 'shipping_address_1', $shipping_address_1 );
			update_user_meta( $user_id, 'shipping_address_2', $shipping_address_2 );
			update_user_meta( $user_id, 'shipping_city', $shipping_city );
			update_user_meta( $user_id, 'shipping_postcode', $shipping_postcode );
			update_user_meta( $user_id, 'shipping_country', $shipping_country );
			update_user_meta( $user_id, 'shipping_state', $shipping_state );
			update_user_meta( $user_id, 'shipping_phone', $shipping_phone );
		}

		$order->set_customer_id( $user_id );
		$order->save();
	}

	/**
	 * Remove subscriptions for resumed orders.
	 *
	 * @param int $order_id Order id.
	 *
	 * @return void
	 */
	public function remove_subscriptions( $order_id ) {
		global $wpdb;
		// delete subscriptions & order item meta.
		$histories = Helper::get_subscriptions_from_order( $order_id );
		foreach ( $histories as $history ) {
			$table_name = $wpdb->prefix . 'subscrpt_order_relation';
			// @phpcs:ignore
			$relation_count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE subscription_id=%d', array( $table_name, $history->subscription_id ) ) );
			if ( 1 === (int) $relation_count ) {
				wp_delete_post( $history->subscription_id, true );
			}
			wc_delete_order_item_meta( $history->order_item_id, '_subscrpt_meta' );
		}

		// delete order subscription relation.
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		// phpcs:ignore
		$wpdb->delete( $table_name, array( 'order_id' => $order_id ), array( '%d' ) );
	}

	/**
	 * Save renew meta
	 *
	 * @param object $item Item.
	 * @param string $cart_item_key Cart Item Key.
	 * @param array  $cart_item Cart Item.
	 */
	public function save_order_item_product_meta( $item, $cart_item_key, $cart_item ) {
		if ( isset( $cart_item['renew_subscrpt'] ) ) {
			$item->update_meta_data( '_renew_subscrpt', $cart_item['renew_subscrpt'] );
		}

		if ( ! empty( $cart_item['wp_subs_switch'] ?? null ) && ! empty( $cart_item['switch_context'] ?? null ) ) {
			$switch_context = $cart_item['switch_context'];

			// Add switch context data to order item meta.
			$item->update_meta_data( '_wp_subs_switch', true, true );
			$item->update_meta_data( '_wp_subs_switch_context', $switch_context, true );
		}
	}
}
