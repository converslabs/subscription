<?php
/**
 * WooCommerce PayPal Payments integration for subscription auto-renewals.
 *
 * PayPal Payments saves the buyer's PayPal account in the PayPal vault at checkout
 * ("Save PayPal and Venmo"). Its own renewal code only runs for WooCommerce
 * Subscriptions, so this class charges the saved account for our renewal orders.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Illuminate\Gateways\PaypalPayments;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Class PaypalPayments
 *
 * @package SpringDevs\Subscription\Illuminate\Gateways\PaypalPayments
 */
class PaypalPayments {

	/**
	 * PayPal Payments' PayPal gateway id.
	 */
	public const GATEWAY_ID = 'ppcp-gateway';

	/**
	 * Order meta holding the PayPal vault id the order was paid with.
	 */
	public const VAULT_ID_META = '_wpsubs_ppcp_vault_id';

	/**
	 * Order meta holding the vault id's payment source (paypal, venmo, card, apple_pay).
	 */
	public const VAULT_SOURCE_META = '_wpsubs_ppcp_vault_source';

	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Remember which vaulted account paid for a subscription order.
		add_action( 'woocommerce_paypal_payments_after_order_processor', [ $this, 'store_vault_id' ], 20, 2 );

		// Carry the vaulted account to the renewal order, then charge it.
		add_filter( 'subscrpt_before_saving_renewal_order', [ $this, 'copy_vault_metadata' ], 10, 3 );
		add_action( 'subscrpt_after_create_renew_order', [ $this, 'after_create_renew_order' ], 10, 3 );
	}

	/**
	 * Store the vault id PayPal returned for an order that started or renews a subscription.
	 *
	 * @param \WC_Order $wc_order     WooCommerce order.
	 * @param object    $paypal_order PayPal Payments' order entity.
	 */
	public function store_vault_id( $wc_order, $paypal_order ) {
		if ( ! $wc_order instanceof \WC_Order || self::GATEWAY_ID !== $wc_order->get_payment_method() ) {
			return;
		}

		if ( empty( Helper::get_subscriptions_from_order( $wc_order->get_id() ) ) ) {
			return;
		}

		$payment_source = is_object( $paypal_order ) && method_exists( $paypal_order, 'payment_source' ) ? $paypal_order->payment_source() : null;
		if ( ! $payment_source ) {
			return;
		}

		$vault_id = $payment_source->properties()->attributes->vault->id ?? '';
		if ( ! $vault_id ) {
			subscrpt_write_log( "PayPal Payments: order #{$wc_order->get_id()} was not saved to the PayPal vault. Its renewals cannot be charged automatically; check that \"Save PayPal and Venmo\" is enabled." );
			return;
		}

		$wc_order->update_meta_data( self::VAULT_ID_META, $vault_id );
		$wc_order->update_meta_data( self::VAULT_SOURCE_META, $payment_source->name() );
		$wc_order->save();
	}

	/**
	 * Copy the vaulted account and payment method from the previous order to the renewal order.
	 *
	 * @param \WC_Order $new_order       Renewal order.
	 * @param \WC_Order $old_order       Previous order.
	 * @param int       $subscription_id Subscription ID.
	 *
	 * @return \WC_Order
	 */
	public function copy_vault_metadata( $new_order, $old_order, $subscription_id ) {
		if ( self::GATEWAY_ID !== $old_order->get_payment_method() ) {
			return $new_order;
		}

		$vault_id = $old_order->get_meta( self::VAULT_ID_META );
		if ( ! $vault_id ) {
			return $new_order;
		}

		$new_order->set_payment_method( $old_order->get_payment_method() );
		$new_order->set_payment_method_title( $old_order->get_payment_method_title() );
		$new_order->update_meta_data( self::VAULT_ID_META, $vault_id );
		$new_order->update_meta_data( self::VAULT_SOURCE_META, $old_order->get_meta( self::VAULT_SOURCE_META ) );

		return $new_order;
	}

	/**
	 * Charge the vaulted account for a new renewal order.
	 *
	 * @param \WC_Order $new_order       Renewal order.
	 * @param \WC_Order $old_order       Previous order.
	 * @param int       $subscription_id Subscription ID.
	 */
	public function after_create_renew_order( $new_order, $old_order, $subscription_id ) {
		if ( self::GATEWAY_ID !== $new_order->get_payment_method() || ! $new_order->get_meta( self::VAULT_ID_META ) ) {
			return;
		}

		// No per-subscription choice yet means the store default, as for Stripe.
		$is_auto_renew = get_post_meta( $subscription_id, '_subscrpt_auto_renew', true );
		$is_auto_renew = '' === $is_auto_renew || in_array( $is_auto_renew, [ 1, '1', true ], true );

		if ( ! $is_auto_renew || ! subscrpt_is_auto_renew_enabled() ) {
			subscrpt_write_log( "PayPal Payments auto renewal not enabled. [ Subscription: {$subscription_id}, Order #{$new_order->get_id()} ]" );
			return;
		}

		$this->pay_renew_order( $new_order );
	}

	/**
	 * Charge a renewal order to its vaulted PayPal account, merchant-initiated.
	 *
	 * @param \WC_Order $renewal_order Renewal order.
	 */
	public function pay_renew_order( $renewal_order ) {
		if ( ! $renewal_order->needs_payment() ) {
			return;
		}

		// Never charge twice: a PayPal order already on the renewal means a charge was attempted.
		if ( $renewal_order->get_meta( '_ppcp_paypal_order_id' ) ) {
			return;
		}

		subscrpt_write_log( "Processing renewal order #{$renewal_order->get_id()} with PayPal Payments." );

		try {
			$container = \WooCommerce\PayPalCommerce\PPCP::container();

			$purchase_unit       = $container->get( 'api.factory.purchase-unit' )->from_wc_order( $renewal_order );
			$payer               = $container->get( 'api.factory.payer' )->from_wc_order( $renewal_order );
			$shipping_preference = $container->get( 'api.factory.shipping-preference' )->from_state( $purchase_unit, 'renewal' );

			$source_name = $renewal_order->get_meta( self::VAULT_SOURCE_META );
			$source_name = $source_name ? $source_name : 'paypal';
			$properties  = [ 'vault_id' => $renewal_order->get_meta( self::VAULT_ID_META ) ];
			// Cards (including Apple Pay) need the merchant-initiated recurring flags.
			if ( in_array( $source_name, [ 'card', 'apple_pay' ], true ) ) {
				$properties['stored_credential'] = [
					'payment_initiator' => 'MERCHANT',
					'payment_type'      => 'RECURRING',
					'usage'             => 'SUBSEQUENT',
				];
			}
			$payment_source = new \WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource( $source_name, (object) $properties );

			// An empty payment method keeps PayPal Payments' checkout filters off the request body.
			$paypal_order = $container->get( 'api.endpoint.order' )->create( [ $purchase_unit ], $shipping_preference, $payer, '', [], $payment_source );

			// The same meta PayPal Payments writes, so its refunds and order screen work.
			$renewal_order->update_meta_data( '_ppcp_paypal_order_id', $paypal_order->id() );
			$renewal_order->update_meta_data( '_ppcp_paypal_intent', $paypal_order->intent() );
			$renewal_order->update_meta_data( '_ppcp_paypal_payment_mode', $container->get( 'settings.environment' )->is_sandbox() ? 'sandbox' : 'live' );
			$renewal_order->save();

			$this->handle_paypal_order( $renewal_order, $paypal_order );
		} catch ( \Throwable $e ) {
			$message = method_exists( $e, 'get_details' ) ? $e->get_details( $e->getMessage() ) : $e->getMessage();

			subscrpt_write_log( "Error processing renewal order #{$renewal_order->get_id()} with PayPal Payments: {$message}" );

			/* translators: %s: error message. */
			$renewal_order->update_status( 'failed', sprintf( __( 'PayPal renewal payment failed: %s', 'subscription' ), $message ) );
			$this->trigger_renewal_payment_failed( $renewal_order );
		}
	}

	/**
	 * Mark the renewal order from the PayPal order PayPal returned.
	 *
	 * @param \WC_Order $renewal_order Renewal order.
	 * @param object    $paypal_order  PayPal Payments' order entity.
	 *
	 * @throws \RuntimeException When PayPal declined the payment.
	 */
	private function handle_paypal_order( $renewal_order, $paypal_order ) {
		$purchase_units = $paypal_order->purchase_units();
		$payments       = ! empty( $purchase_units ) ? $purchase_units[0]->payments() : null;

		if ( 'AUTHORIZE' === $paypal_order->intent() ) {
			$authorizations = $payments ? $payments->authorizations() : [];
			$authorization  = ! empty( $authorizations ) ? $authorizations[0] : null;
			if ( ! $authorization || in_array( $authorization->status()->name(), [ 'DENIED', 'VOIDED', 'EXPIRED' ], true ) ) {
				throw new \RuntimeException( esc_html__( 'PayPal did not authorize the renewal payment.', 'subscription' ) );
			}

			$renewal_order->set_transaction_id( $authorization->id() );
			$renewal_order->update_meta_data( '_ppcp_paypal_captured', 'false' );
			$renewal_order->update_status( 'on-hold', __( 'PayPal authorized the renewal payment. Capture it from the order to complete the renewal.', 'subscription' ) );
			return;
		}

		$captures = $payments ? $payments->captures() : [];
		$capture  = ! empty( $captures ) ? $captures[0] : null;
		$status   = $capture ? $capture->status()->name() : '';

		if ( 'COMPLETED' === $status ) {
			$renewal_order->payment_complete( $capture->id() );
			/* translators: %s: PayPal capture id. */
			$renewal_order->add_order_note( sprintf( __( 'Renewal paid with the payment method saved in PayPal (capture %s).', 'subscription' ), $capture->id() ) );
			return;
		}

		if ( 'PENDING' === $status ) {
			$renewal_order->set_transaction_id( $capture->id() );
			$renewal_order->update_status( 'on-hold', __( 'PayPal is holding the renewal payment for review.', 'subscription' ) );
			return;
		}

		/* translators: %s: PayPal capture status. */
		throw new \RuntimeException( sprintf( esc_html__( 'PayPal returned capture status "%s".', 'subscription' ), esc_html( $status ? $status : 'none' ) ) );
	}

	/**
	 * Fire the failed renewal payment action, unless the customer paid early by choice.
	 *
	 * @param \WC_Order $renewal_order Renewal order.
	 */
	private function trigger_renewal_payment_failed( $renewal_order ) {
		$subscription    = Helper::get_subscriptions_from_order( $renewal_order->get_id() ?? 0 );
		$subscription    = reset( $subscription );
		$subscription_id = $subscription->subscription_id ?? 0;

		// An early renewal is paid before the subscription is due. Its customer is sent to
		// the pay page, so a failed charge is not a missed renewal and must not start dunning.
		if ( isset( $subscription->type ) && 'early-renew' === $subscription->type ) {
			return;
		}

		do_action( 'subscrpt_subscription_payment_failed', $subscription_id );
	}
}
