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

		// Guest account creation.
		add_action( 'woocommerce_checkout_create_order', [ $this, 'check_guest_and_maybe_assign_user' ], 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', [ $this, 'check_guest_and_maybe_assign_user_storeapi' ], 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', [ $this, 'ensure_user_for_blocks_checkout' ], 10, 1 );

		// Guest account logout after checkout.
		add_action( 'template_redirect', [ $this, 'maybe_logout_guest' ] );

		add_action( 'woocommerce_checkout_order_processed', [ $this, 'create_subscription_after_checkout' ] );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'create_subscription_after_checkout_storeapi' ] );
		add_action( 'woocommerce_resume_order', [ $this, 'remove_subscriptions' ] );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_order_item_product_meta' ], 10, 3 );
	}

	/**
	 * Check if cart/order has subscription and guest checkout are allowed.
	 */
	public function is_subs_and_guest_checkout_allowed() {
		$is_user_logged_in         = is_user_logged_in();
		$is_guest_checkout_allowed = in_array( get_option( 'wp_subscription_allow_guest_checkout', '0' ), [ 1, '1', 'yes', 'on' ], true );
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
	 * Check guest and maybe assign user.
	 *
	 * @param \WC_Order $order Order object.
	 * @param array     $data Order data.
	 */
	public function check_guest_and_maybe_assign_user( $order, $data ) {
		// Don't proceed if user logged in.
		if ( is_user_logged_in() ) {
			return;
		}

		// Check if order is valid.
		if ( ! $order ) {
			return;
		}

		$this->maybe_assign_user_to_order( $order->get_id() );
	}

	/**
	 * Check guest and maybe assign user on storeAPI.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function check_guest_and_maybe_assign_user_storeapi( $order ) {
		// Don't proceed if user logged in.
		if ( is_user_logged_in() ) {
			return;
		}

		// Check if order is valid.
		if ( ! $order ) {
			return;
		}

		$this->maybe_assign_user_to_order( $order->get_id() );
	}

	/**
	 * Ensure a WP user exists early in the Blocks checkout lifecycle.
	 * This allows the Stripe gateway to create/attach a Stripe customer on the PaymentIntent
	 * for redirect methods (e.g., iDEAL) even for guests.
	 *
	 * @param \WC_Customer $customer Customer object.
	 * @return void
	 */
	public function ensure_user_for_blocks_checkout( $customer ) {
		// Don't proceed if user logged in.
		if ( is_user_logged_in() ) {
			return;
		}

		// If already associated with a user, nothing to do.
		if ( $customer->get_id() ) {
			return;
		}

		$billing_email = $customer->get_billing_email();
		if ( empty( $billing_email ) ) {
			// Cannot proceed without an email.
			return;
		}

		// Build and maybe create a user.
		$user_info = $this->build_user_info( $customer );
		$user_id   = $this->maybe_create_user( $user_info );

		if ( $user_id ) {
			$customer->set_id( $user_id );
			$customer->save();
		}
	}

	/**
	 * Build user info from order or customer object.
	 *
	 * @param \WC_Order|\WC_Customer $order_or_customer Order or Customer object.
	 */
	public function build_user_info( $order_or_customer ): array {
		$user_info = [];

		// Billing info.
		$user_info['billing_first_name'] = $order_or_customer->get_billing_first_name();
		$user_info['billing_last_name']  = $order_or_customer->get_billing_last_name();
		$user_info['billing_company']    = $order_or_customer->get_billing_company();
		$user_info['billing_address_1']  = $order_or_customer->get_billing_address_1();
		$user_info['billing_address_2']  = $order_or_customer->get_billing_address_2();
		$user_info['billing_city']       = $order_or_customer->get_billing_city();
		$user_info['billing_postcode']   = $order_or_customer->get_billing_postcode();
		$user_info['billing_country']    = $order_or_customer->get_billing_country();
		$user_info['billing_state']      = $order_or_customer->get_billing_state();
		$user_info['billing_email']      = $order_or_customer->get_billing_email();
		$user_info['billing_phone']      = $order_or_customer->get_billing_phone();

		// Shipping info.
		$user_info['shipping_first_name'] = ! empty( $order_or_customer->get_shipping_first_name() ) ? $order_or_customer->get_shipping_first_name() : $user_info['billing_first_name'];
		$user_info['shipping_last_name']  = ! empty( $order_or_customer->get_shipping_last_name() ) ? $order_or_customer->get_shipping_last_name() : $user_info['billing_last_name'];
		$user_info['shipping_company']    = ! empty( $order_or_customer->get_shipping_company() ) ? $order_or_customer->get_shipping_company() : $user_info['billing_company'];
		$user_info['shipping_address_1']  = ! empty( $order_or_customer->get_shipping_address_1() ) ? $order_or_customer->get_shipping_address_1() : $user_info['billing_address_1'];
		$user_info['shipping_address_2']  = ! empty( $order_or_customer->get_shipping_address_2() ) ? $order_or_customer->get_shipping_address_2() : $user_info['billing_address_2'];
		$user_info['shipping_city']       = ! empty( $order_or_customer->get_shipping_city() ) ? $order_or_customer->get_shipping_city() : $user_info['billing_city'];
		$user_info['shipping_postcode']   = ! empty( $order_or_customer->get_shipping_postcode() ) ? $order_or_customer->get_shipping_postcode() : $user_info['billing_postcode'];
		$user_info['shipping_country']    = ! empty( $order_or_customer->get_shipping_country() ) ? $order_or_customer->get_shipping_country() : $user_info['billing_country'];
		$user_info['shipping_state']      = ! empty( $order_or_customer->get_shipping_state() ) ? $order_or_customer->get_shipping_state() : $user_info['billing_state'];
		$user_info['shipping_phone']      = ! empty( $order_or_customer->get_shipping_phone() ) ? $order_or_customer->get_shipping_phone() : $user_info['billing_phone'];

		return $user_info;
	}

	/**
	 * Maybe create user from user info.
	 *
	 * @param array $user_info User info array.
	 */
	public function maybe_create_user( $user_info ): ?int {
		// Don't proceed if guest checkout is not allowed.
		$is_guest_checkout_allowed = in_array( get_option( 'wp_subscription_allow_guest_checkout', '0' ), [ 1, '1', 'yes', 'on' ], true );
		if ( ! $is_guest_checkout_allowed ) {
			return null;
		}

		// Check if user exists with email.
		$user    = get_user_by( 'email', $user_info['billing_email'] );
		$user_id = $user ? $user->ID : 0;

		if ( ! $user_id ) {
			$username = sanitize_user( current( explode( '@', $user_info['billing_email'] ) ), true );
			if ( username_exists( $username ) ) {
				$username .= '_' . wp_generate_password( 4, false );
			}

			// Create user.
			$args = [
				'user_login'   => $username,
				'user_email'   => $user_info['billing_email'],
				'first_name'   => $user_info['billing_first_name'],
				'last_name'    => $user_info['billing_last_name'],
				'display_name' => trim( "{$user_info['billing_first_name']} {$user_info['billing_last_name']}" ),
				'role'         => 'customer',
			];

			$user_id = wp_insert_user( $args );

			if ( is_wp_error( $user_id ) ) {
				wp_subscrpt_write_log( 'Failed to auto-create user during checkout. Error: ' . $user_id->get_error_message() );
				return null;
			}

			// Set billing info.
			update_user_meta( $user_id, 'billing_first_name', $user_info['billing_first_name'] );
			update_user_meta( $user_id, 'billing_last_name', $user_info['billing_last_name'] );
			update_user_meta( $user_id, 'billing_company', $user_info['billing_company'] );
			update_user_meta( $user_id, 'billing_address_1', $user_info['billing_address_1'] );
			update_user_meta( $user_id, 'billing_address_2', $user_info['billing_address_2'] );
			update_user_meta( $user_id, 'billing_city', $user_info['billing_city'] );
			update_user_meta( $user_id, 'billing_postcode', $user_info['billing_postcode'] );
			update_user_meta( $user_id, 'billing_country', $user_info['billing_country'] );
			update_user_meta( $user_id, 'billing_state', $user_info['billing_state'] );
			update_user_meta( $user_id, 'billing_email', $user_info['billing_email'] );
			update_user_meta( $user_id, 'billing_phone', $user_info['billing_phone'] );

			// Set shipping info.
			update_user_meta( $user_id, 'shipping_first_name', $user_info['shipping_first_name'] );
			update_user_meta( $user_id, 'shipping_last_name', $user_info['shipping_last_name'] );
			update_user_meta( $user_id, 'shipping_company', $user_info['shipping_company'] );
			update_user_meta( $user_id, 'shipping_address_1', $user_info['shipping_address_1'] );
			update_user_meta( $user_id, 'shipping_address_2', $user_info['shipping_address_2'] );
			update_user_meta( $user_id, 'shipping_city', $user_info['shipping_city'] );
			update_user_meta( $user_id, 'shipping_postcode', $user_info['shipping_postcode'] );
			update_user_meta( $user_id, 'shipping_country', $user_info['shipping_country'] );
			update_user_meta( $user_id, 'shipping_state', $user_info['shipping_state'] );
			update_user_meta( $user_id, 'shipping_phone', $user_info['shipping_phone'] );

			// User creation notification.
			do_action( 'woocommerce_created_customer', $user_id, [], true );
		}

		// Login the user.
		// ? this is temporary, user will be logged out after the checkout.
		if ( ! is_user_logged_in() ) {
			// Log the user in
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id );

			// Optional: trigger login action
			do_action( 'wp_login', get_userdata( $user_id )->user_login, get_userdata( $user_id ) );

			// Set flag for manual login tracking.
			set_transient( 'subscrpt_manual_login_' . $user_id, true, 15 * MINUTE_IN_SECONDS );

			wp_subscrpt_write_debug_log( 'Auto-logged in user ID: ' . $user_id . ' after checkout.' );
		}

		return $user_id;
	}

	/**
	 * Maybe logout guest user after checkout.
	 *
	 * @return void
	 */
	public function maybe_logout_guest() {
		$user_id = get_current_user_id();
		if ( $user_id && get_transient( 'subscrpt_manual_login_' . $user_id ) ) {
			wp_logout();
			delete_transient( 'subscrpt_manual_login_' . $user_id );

			wp_subscrpt_write_debug_log( 'Auto-logged out user ID: ' . $user_id . ' after checkout.' );
		}
	}

	/**
	 * Maybe assign user to order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_assign_user_to_order( $order_id ) {
		// Don't proceed if guest checkout is not allowed.
		$is_guest_checkout_allowed = in_array( get_option( 'wp_subscription_allow_guest_checkout', '0' ), [ 1, '1', 'yes', 'on' ], true );
		if ( ! $is_guest_checkout_allowed ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Don't proceed if order already have an user.
		if ( $order->get_customer_id() ) {
			return;
		}

		// Build and maybe create a user.
		$user_info = $this->build_user_info( $order );
		$user_id   = $this->maybe_create_user( $user_info );

		if ( $user_id ) {
			$order->set_customer_id( $user_id );
			$order->save();
		}
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
