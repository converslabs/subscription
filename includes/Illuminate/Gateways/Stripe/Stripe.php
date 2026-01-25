<?php
/**
 * Stripe integration helpers for subscription auto-renewals.
 *
 * Ensures payment methods are saved with mandates (SEPA, etc.) so that
 * off-session renewals can be charged automatically by Stripe.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Illuminate\Gateways\Stripe;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Class Stripe
 *
 * @package SpringDevs\SubscriptionPro\Illuminate
 */
class Stripe extends \WC_Stripe_Payment_Gateway {

	/**
	 * WPSubscription supported Stripe payment methods.
	 */
	public const WPSUBS_SUPPORTED_METHODS = [ 'stripe', 'stripe_ideal', 'stripe_sepa', 'sepa_debit', 'stripe_bancontact' ];

	/**
	 * Mandate needed methods.
	 */
	public const WPSUBS_MANDATE_NEEDED_METHODS = [ 'stripe_ideal', 'stripe_sepa', 'sepa_debit', 'stripe_bancontact' ];

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'subscrpt_after_create_renew_order', array( $this, 'after_create_renew_order' ), 10, 3 );
		add_filter( 'wc_stripe_payment_metadata', array( $this, 'add_payment_metadata' ), 10, 2 );

		// Ensure a reusable payment method is stored for subscription checkouts (needed for iDEAL/SEPA auto-renewals).
		add_filter( 'wc_stripe_force_save_payment_method', array( $this, 'force_save_payment_method_for_subscriptions' ), 10, 2 );

		// Modify create intent request to add setup_future_usage and customer when needed.
		add_filter( 'wc_stripe_generate_create_intent_request', [ $this, 'modify_create_intent_request_for_subscriptions' ], 20, 3 );
	}

	/**
	 * Process stripe auto renewal process.
	 *
	 * @param \WC_Order $new_order       New Order.
	 * @param \WC_Order $old_order       Old Order.
	 * @param int       $subscription_id Subscription ID.
	 */
	public function after_create_renew_order( $new_order, $old_order, $subscription_id ) {
		$is_auto_renew = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		$is_auto_renew = in_array( $is_auto_renew, [ 1,'1' ], true );

		$is_global_auto_renew = get_option( 'wp_subscription_stripe_auto_renew', '1' );
		$is_global_auto_renew = in_array( $is_global_auto_renew, [ 1,'1' ], true );

		$stripe_supported_methods = self::WPSUBS_SUPPORTED_METHODS;
		$old_method               = $old_order->get_payment_method();
		$is_stripe_pm             = ! empty( $old_method ) && in_array( $old_method, $stripe_supported_methods, true );

		$has_stripe_meta = ! empty( $old_order->get_meta( '_stripe_customer_id' ) ) || ! empty( $old_order->get_meta( '_stripe_source_id' ) );

		$stripe_enabled = ( ( $is_stripe_pm || $has_stripe_meta ) && $is_auto_renew && $is_global_auto_renew && subscrpt_is_auto_renew_enabled() );

		if ( ! $stripe_enabled ) {
			$log_message = "Stripe auto renewal not enabled. [ Subscription: {$subscription_id}, Order #{$new_order->get_id()} ]";
			wp_subscrpt_write_log( $log_message );

			// Log details for debugging.
			wp_subscrpt_write_debug_log( $log_message );
			wp_subscrpt_write_debug_log( 'is_auto_renew: ' . ( $is_auto_renew ? 'true' : 'false' ) );
			wp_subscrpt_write_debug_log( 'is_global_auto_renew: ' . ( $is_global_auto_renew ? 'true' : 'false' ) );
			wp_subscrpt_write_debug_log( 'is_stripe_payment_method: ' . ( $is_stripe_pm ? 'true' : 'false' ) . ' (old method: ' . $old_method . ')' );
			wp_subscrpt_write_debug_log( 'has_stripe_meta: ' . ( $has_stripe_meta ? 'true' : 'false' ) );
			return;
		}

		$this->pay_renew_order( $new_order );
	}

	/**
	 * Pay renewal Order
	 *
	 * @param \WC_Order $renewal_order Renewal order.
	 * @throws \WC_Stripe_Exception $e exception.
	 */
	public function pay_renew_order( $renewal_order ) {
		wp_subscrpt_write_log( "Processing renewal order #{$renewal_order->get_id()} for payment." );
		wp_subscrpt_write_debug_log( "Processing renewal order #{$renewal_order->get_id()} for payment." );

		try {
			$stripe_order_helper = new \WC_Stripe_Order_Helper();
			$stripe_order_helper->validate_minimum_order_amount( $renewal_order );

			$amount   = $renewal_order->get_total();
			$order_id = $renewal_order->get_id();

			// Get source from order.
			$prepared_source = $this->prepare_order_source( $renewal_order );
			if ( ! $prepared_source->customer ) {
				wp_subscrpt_write_log( "Customer not found for renewal order #{$renewal_order->get_id()}. Skipping payment." );
				return new \WP_Error( 'stripe_error', __( 'Customer not found', 'subscription' ) );
			}

			\WC_Stripe_Logger::info( "Begin processing subscription payment for order {$order_id} for the amount of {$amount}" );

			$intent = $this->create_intent( $renewal_order, $prepared_source );

			if ( empty( $intent->error ) ) {
				$stripe_order_helper->lock_order_payment( $renewal_order, $intent );
				// Only confirm if Stripe still requires confirmation.
				if ( \WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION === $intent->status ) {
					$intent = $this->confirm_intent( $intent, $renewal_order, $prepared_source );
				}
			}

			if ( ! empty( $intent->error ) ) {
				$this->maybe_remove_non_existent_customer( $intent->error, $renewal_order );

				$stripe_order_helper->unlock_order_payment( $renewal_order );
				$this->throw_localized_message( $intent, $renewal_order );
			}

			if ( ! empty( $intent ) ) {
				// Use the last charge within the intent to proceed.
				$response = $this->get_latest_charge_from_intent( $intent );
				$this->process_response( $response, $renewal_order );
			}
			$stripe_order_helper->unlock_order_payment( $renewal_order );

		} catch ( \WC_Stripe_Exception $e ) {
			\WC_Stripe_Logger::error( 'Error: ' . $e->getMessage() );

			$log_message = "Error processing renewal order #{$renewal_order->get_id()}: " . $e->getMessage();
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );

			do_action( 'wc_gateway_stripe_process_payment_error', $e, $renewal_order );

			// Get subscription ID.
			$subscription    = Helper::get_subscriptions_from_order( $renewal_order->get_id() ?? 0 );
			$subscription    = reset( $subscription );
			$subscription_id = $subscription->subscription_id ?? 0;

			// Trigger failed payment mail.
			do_action( 'subscrpt_payment_failure_email_notification', $subscription_id );
		}
	}

	/**
	 * Confirms an intent if it is the `requires_confirmation` state with SEPA mandate support.
	 *
	 * @param object    $intent The intent to confirm.
	 * @param \WC_Order $order The order that the intent is associated with.
	 * @param object    $prepared_source The source that is being charged.
	 * @return object Either an error or the updated intent.
	 */
	public function confirm_intent( $intent, $order, $prepared_source ) {
		if ( \WC_Stripe_Intent_Status::REQUIRES_CONFIRMATION !== $intent->status ) {
			return $intent;
		}

		// Build confirm request and include SEPA mandate_data when needed.
		$confirm_request = \WC_Stripe_Helper::add_payment_method_to_request_array( $prepared_source->source, array() );

		$payment_method_types = array();
		if ( isset( $intent->payment_method_types ) && is_array( $intent->payment_method_types ) ) {
			$payment_method_types = $intent->payment_method_types;
		} elseif ( isset( $prepared_source->source_object->type ) ) {
			$payment_method_types = array( $prepared_source->source_object->type );
		}

		if ( in_array( 'sepa_debit', $payment_method_types, true ) ) {
			$confirm_request['mandate_data'] = array(
				'customer_acceptance' => array(
					'type' => 'offline',
				),
			);
		}

		$level3_data      = $this->get_level3_data_from_order( $order );
		$confirmed_intent = \WC_Stripe_API::request_with_level3_data(
			$confirm_request,
			"payment_intents/$intent->id/confirm",
			$level3_data,
			$order
		);

		if ( ! empty( $confirmed_intent->error ) ) {
			return $confirmed_intent;
		}

		// Save a note about the status of the intent.
		$order_id = $order->get_id();
		if ( \WC_Stripe_Intent_Status::SUCCEEDED === $confirmed_intent->status ) {
			\WC_Stripe_Logger::log( "Stripe PaymentIntent $intent->id succeeded for order $order_id" );
		} elseif ( \WC_Stripe_Intent_Status::REQUIRES_ACTION === $confirmed_intent->status ) {
			\WC_Stripe_Logger::log( "Stripe PaymentIntent $intent->id requires authentication for order $order_id" );
		}

		return $confirmed_intent;
	}

	/**
	 * Generates the request when creating a new payment intent.
	 *
	 * @param \WC_Order $order           The order that is being paid for.
	 * @param object    $prepared_source The source that is used for the payment.
	 * @return array                    The arguments for the request.
	 */
	public function generate_create_intent_request( $order, $prepared_source ) {
		// The request for a charge contains metadata for the intent.
		$full_request = $this->generate_payment_request( $order, $prepared_source );

		$payment_method_types = array( 'card' );
		if ( isset( $prepared_source->source_object->type ) ) {
			$payment_method_types = array( $prepared_source->source_object->type );
		}

		// Determine capture method safely; default to 'automatic'.
		$requires_automatic_capture = in_array( 'sepa_debit', $payment_method_types, true );
		$capture_method             = 'automatic';
		if ( ! $requires_automatic_capture && isset( $full_request['capture'] ) ) {
			$capture_method = ( 'true' === $full_request['capture'] ) ? 'automatic' : 'manual';
		}

		$currency = strtolower( $order->get_currency() );

		$request = array(
			'amount'               => \WC_Stripe_Helper::get_stripe_amount( $order->get_total(), $currency ),
			'currency'             => $currency,
			'description'          => $full_request['description'],
			'metadata'             => $full_request['metadata'],
			'capture_method'       => $capture_method,
			'payment_method_types' => $payment_method_types,
		);

		$request = \WC_Stripe_Helper::add_payment_method_to_request_array( $prepared_source->source, $request );

		$force_save_source = apply_filters( 'wc_stripe_force_save_payment_method', false, $order->get_id() );

		// Only ask Stripe to set up future usage when we actually have a Stripe customer
		// (logged-in user or a customer created for this order). For guest + iDEAL, this can
		// leave orders pending if webhooks are not completing the flow.
		$has_stripe_customer = ! empty( $prepared_source->customer );

		if ( $has_stripe_customer && ( $this->save_payment_method_requested() || $this->has_subscription( $order->get_id() ) || $force_save_source ) ) {
			$request['setup_future_usage']              = 'off_session';
			$request['metadata']['save_payment_method'] = 'true';
		}

		// For renewal orders, do not set setup_future_usage to avoid mandate_data requirement on confirmation.
		if ( $this->is_subscription_renewal_order( $order->get_id() ) && isset( $request['setup_future_usage'] ) ) {
			unset( $request['setup_future_usage'] );
		}

		if ( $prepared_source->customer ) {
			$request['customer'] = $prepared_source->customer;
		}

		if ( isset( $full_request['statement_descriptor_suffix'] ) ) {
			$request['statement_descriptor_suffix'] = $full_request['statement_descriptor_suffix'];
		}

		if ( isset( $full_request['shipping'] ) ) {
			$request['shipping'] = $full_request['shipping'];
		}

		if ( isset( $full_request['receipt_email'] ) ) {
			$request['receipt_email'] = $full_request['receipt_email'];
		}

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_create_intent_request.
		 *
		 * @since 3.1.0
		 * @param array $request
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters( 'wc_stripe_generate_create_intent_request', $request, $order, $prepared_source );
	}

	/**
	 * Add metadata to stripe payment.
	 *
	 * @param array     $metadata Metadata.
	 * @param \WC_Order $order Order.
	 *
	 * @return array
	 */
	public function add_payment_metadata( array $metadata, \WC_Order $order ): array {

		if ( ! subscrpt_is_auto_renew_enabled() ) {
			return $metadata;
		}

		global $wpdb;
		$recurring     = false;
		$renewal_limit = null;
		foreach ( $order->get_items() as $order_item ) {
			$table_name = $wpdb->prefix . 'subscrpt_order_relation';
			// @phpcs:ignore
			$relation = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id=%d AND order_item_id=%d', array( $table_name, $order->get_id(), $order_item->get_id() ) ) );

			if ( 0 < count( $relation ) ) {
				$relation      = $relation[0];
				$is_auto_renew = get_post_meta( (int) $relation->subscription_id, '_subscrpt_auto_renew', true );

				// Get renewal limit from product meta (handles variations)
				$max_payments  = subscrpt_get_max_payments( (int) $relation->subscription_id );
				$renewal_limit = $max_payments ? $max_payments : 0;

				if ( in_array( $is_auto_renew, array( 1, '1' ), true ) && in_array( $relation->type, array( 'early-renew', 'renew' ), true ) ) {
					$recurring = true;
					break;
				}
			}
		}

		if ( $recurring ) {
			$metadata += array(
				'payment_type' => 'recurring',
			);
			if ( null !== $renewal_limit ) {
				$metadata['renewal_limit'] = $renewal_limit;
			}
		}

		return $metadata;
	}

	/**
	 * Mirror of the above for gateways using wc_stripe_force_save_payment_method filter.
	 *
	 * @param bool $force    Whether to force save the payment method.
	 * @param int  $order_id Order ID if available during confirmation.
	 * @return bool
	 */
	public function force_save_payment_method_for_subscriptions( $force, $order_id = 0 ) {
		if ( $this->cart_has_subscription_items() ) {
			return true;
		}
		if ( $order_id && $this->order_has_subscription_relation( (int) $order_id ) ) {
			return true;
		}
		return $force;
	}

	/**
	 * Check if current cart contains subscription items added by this plugin.
	 *
	 * @return bool
	 */
	private function cart_has_subscription_items(): bool {
		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart_items = WC()->cart->get_cart_contents() ?? [];
			$recurs     = Helper::get_recurrs_from_cart( $cart_items );

			if ( ! empty( $recurs ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Determine if a given order has subscription relation rows in our mapping table.
	 *
	 * @param int $order_id The WooCommerce order ID to check.
	 * @return bool
	 */
	private function order_has_subscription_relation( int $order_id ): bool {
		$histories = Helper::get_subscriptions_from_order( $order_id );
		return ! empty( $histories );
	}

	/**
	 * Detect if given order id is a renewal order created by this plugin.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	private function is_subscription_renewal_order( $order_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_order_relation';
		// @phpcs:ignore
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT type FROM %i WHERE order_id=%d ORDER BY id DESC', array( $table_name, $order_id ) ) );
		return ( $row && isset( $row->type ) && 'renew' === $row->type );
	}

	/**
	 * Modify create intent request to add setup_future_usage and customer when needed.
	 *
	 * @param array     $request         The arguments for the request.
	 * @param \WC_Order $order           The order that is being paid for.
	 * @param object    $prepared_source The source that is used for the payment.
	 */
	public function modify_create_intent_request_for_subscriptions( $request, $order, $prepared_source ) {
		$is_subscription_order = $this->order_has_subscription_relation( $order->get_id() );
		if ( ! $is_subscription_order ) {
			return $request;
		}

		$request['setup_future_usage']              = 'off_session';
		$request['metadata']['save_payment_method'] = 'true';

		// Ensure we have a customer for future payments
		if ( ! empty( $prepared_source->customer ) ) {
			$request['customer'] = $prepared_source->customer;
		}

		if ( isset( $request['confirm'] ) && true === $request['confirm'] ) {
			if ( in_array( $order->get_payment_method(), self::WPSUBS_MANDATE_NEEDED_METHODS, true ) ) {
				$request['mandate_data'] = [
					'customer_acceptance' => [
						'type' => 'offline',
					],
				];
			}
		}

		return $request;
	}
}
