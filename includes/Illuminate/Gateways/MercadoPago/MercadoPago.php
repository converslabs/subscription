<?php
/**
 * Mercado Pago payment gateway.
 *
 * Recurring billing uses the Mercado Pago "preapproval" (subscriptions) API:
 * the customer approves the recurring charge once on Mercado Pago, and
 * Mercado Pago bills every cycle and notifies us by webhook.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Illuminate\Gateways\MercadoPago;

use SpringDevs\Subscription\Illuminate\Action;
use SpringDevs\Subscription\Illuminate\Helper;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Class MercadoPago
 *
 * @package SpringDevs\Subscription\Illuminate\Gateways\MercadoPago
 */
class MercadoPago extends \WC_Payment_Gateway {

	/**
	 * Mercado Pago REST API base.
	 */
	const API_BASE = 'https://api.mercadopago.com';

	/**
	 * Currencies Mercado Pago can bill in.
	 */
	const SUPPORTED_CURRENCIES = [ 'ARS', 'BRL', 'CLP', 'COP', 'MXN', 'PEN', 'UYU' ];

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Access token.
	 *
	 * @var string
	 */
	protected $access_token;

	/**
	 * Webhook signature secret.
	 *
	 * @var string
	 */
	protected $webhook_secret;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'wp_subscription_mercadopago';
		$this->has_fields         = false;
		$this->method_title       = __( 'Mercado Pago for WPSubscription', 'subscription' );
		$this->method_description = __( 'Accept WPSubscription recurring payments through Mercado Pago. Only WPSubscription is supported.', 'subscription' );
		$this->supports           = [ 'products', 'subscriptions' ];
		$this->icon               = apply_filters( 'wp_subscription_mercadopago_icon', SUBSCRPT_URL . '/assets/images/integrations/mercadopago.svg' );

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled        = $this->get_option( 'enabled' );
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->access_token   = trim( (string) $this->get_option( 'access_token' ) );
		$this->webhook_secret = trim( (string) $this->get_option( 'webhook_secret' ) );

		if ( null === self::$instance ) {
			self::$instance = $this;
		}

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'woocommerce_thankyou', [ $this, 'order_received_page' ] );
		add_filter( 'woocommerce_available_payment_gateways', [ $this, 'remove_gateway_without_subscription' ] );
		add_action( 'woocommerce_api_' . $this->id, [ $this, 'process_webhook' ] );
		add_action( 'subscrpt_subscription_expired', [ $this, 'handle_subscription_cancellation' ] );
		add_action( 'subscrpt_subscription_cancelled', [ $this, 'handle_subscription_cancellation' ] );
	}

	/**
	 * Get the singleton gateway instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled'        => [
				'title'   => __( 'Enable/Disable', 'subscription' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Mercado Pago for WPSubscription', 'subscription' ),
				'default' => 'no',
			],
			'title'          => [
				'title'       => __( 'Title', 'subscription' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'subscription' ),
				'default'     => __( 'Mercado Pago', 'subscription' ),
				'desc_tip'    => true,
			],
			'description'    => [
				'title'       => __( 'Description', 'subscription' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'subscription' ),
				'default'     => __( 'Pay and renew automatically with Mercado Pago.', 'subscription' ),
				'desc_tip'    => true,
			],
			'access_token'   => [
				'title'       => __( 'Access Token', 'subscription' ),
				'type'        => 'password',
				'description' => __( 'Your Mercado Pago application access token. Use a test-user token to test, a production token to go live.', 'subscription' ),
				'default'     => '',
			],
			'webhook_secret' => [
				'title'       => __( 'Webhook Secret', 'subscription' ),
				'type'        => 'password',
				'description' => __( 'The secret signature from your Mercado Pago application webhooks settings. Webhooks without a valid signature are rejected.', 'subscription' ),
				'default'     => '',
			],
			'webhook_url'    => [
				'title'       => __( 'Webhook URL', 'subscription' ),
				'type'        => 'text',
				'description' => __( 'Add this URL in your Mercado Pago application (Webhooks) and subscribe to "Plans and subscriptions" events (subscription_preapproval and subscription_authorized_payment).', 'subscription' ),
				'default'     => $this->get_webhook_url(),
				'custom_attributes' => [ 'readonly' => 'readonly' ],
			],
		];
	}

	/**
	 * Webhook URL.
	 *
	 * @return string
	 */
	public function get_webhook_url(): string {
		return add_query_arg( 'wc-api', 'wp_subscription_mercadopago', trailingslashit( get_home_url() ) );
	}

	/**
	 * Available only with a token and a supported currency.
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available()
			&& ! empty( $this->access_token )
			&& in_array( get_woocommerce_currency(), self::SUPPORTED_CURRENCIES, true );
	}

	/**
	 * Hide the gateway unless the cart holds a subscription product.
	 *
	 * @param array $gateways Available gateways.
	 * @return array
	 */
	public function remove_gateway_without_subscription( $gateways ) {
		if ( ! is_checkout() || ! is_array( $gateways ) || ! isset( $gateways[ $this->id ] ) || ! WC()->cart ) {
			return $gateways;
		}

		foreach ( WC()->cart->cart_contents as $cart_item ) {
			if ( isset( $cart_item['subscription'] ) || $cart_item['data']->get_meta( '_subscrpt_enabled' ) ) {
				return $gateways;
			}
		}

		unset( $gateways[ $this->id ] );
		return $gateways;
	}

	/**
	 * Create the preapproval and send the customer to Mercado Pago.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$items = $order ? $order->get_items() : [];
		$item  = ! empty( $items ) ? reset( $items ) : null;

		if ( ! $item instanceof WC_Order_Item_Product ) {
			wc_add_notice( __( 'Mercado Pago payment failed: invalid product in order.', 'subscription' ), 'error' );
			return [ 'result' => 'failure' ];
		}

		$product = $item->get_variation_id() ? wc_get_product( $item->get_variation_id() ) : wc_get_product( $item->get_product_id() );
		if ( ! $product ) {
			wc_add_notice( __( 'Mercado Pago payment failed: invalid product in order.', 'subscription' ), 'error' );
			return [ 'result' => 'failure' ];
		}

		$return_url = $this->get_return_url( $order );
		$return_url = wp_http_validate_url( $return_url ) ? $return_url : home_url( $return_url );

		$preapproval = $this->api_request(
			'POST',
			'/preapproval',
			$this->build_preapproval_data( $order, $product, $return_url )
		);

		if ( empty( $preapproval['id'] ) || empty( $preapproval['init_point'] ) ) {
			$message = is_array( $preapproval ) && ! empty( $preapproval['message'] ) ? $preapproval['message'] : __( 'Failed to create the subscription.', 'subscription' );
			subscrpt_write_log( 'Mercado Pago preapproval failed: ' . $message );
			// translators: %s: error message from Mercado Pago.
			wc_add_notice( sprintf( __( 'Mercado Pago payment failed: %s', 'subscription' ), $message ), 'error' );
			return [ 'result' => 'failure' ];
		}

		$order->update_meta_data( '_wp_subs_mercadopago_preapproval_id', sanitize_text_field( $preapproval['id'] ) );
		$order->update_status( 'on-hold', __( 'Awaiting Mercado Pago subscription approval.', 'subscription' ) );
		$order->save();

		return [
			'result'   => 'success',
			'redirect' => esc_url_raw( $preapproval['init_point'] ),
		];
	}

	/**
	 * Build the preapproval request body from a product's billing schedule.
	 *
	 * @param WC_Order   $order      Order.
	 * @param WC_Product $product    Product or variation.
	 * @param string     $return_url Return URL.
	 * @return array
	 */
	public function build_preapproval_data( WC_Order $order, WC_Product $product, string $return_url ): array {
		$wpsubs_product = Subscription::get_subs_product( $product );
		list( $frequency, $frequency_type ) = $this->convert_interval( (int) $wpsubs_product->get_timing_per(), (string) $wpsubs_product->get_timing_option() );

		$auto_recurring = [
			'frequency'          => $frequency,
			'frequency_type'     => $frequency_type,
			'transaction_amount' => (float) number_format( (float) $order->get_total(), 2, '.', '' ),
			'currency_id'        => $order->get_currency(),
		];

		$trial_length = (int) $wpsubs_product->get_trial_timing_per();
		if ( $trial_length > 0 ) {
			list( $trial_frequency, $trial_type ) = $this->convert_interval( $trial_length, (string) $wpsubs_product->get_trial_timing_option() );
			$auto_recurring['free_trial']         = [
				'frequency'      => $trial_frequency,
				'frequency_type' => $trial_type,
			];
		}

		$max_payments = (int) $product->get_meta( '_subscrpt_max_no_payment' );
		if ( $max_payments > 0 ) {
			$auto_recurring['repetitions'] = $max_payments;
		}

		return [
			'reason'             => $this->truncate( $product->get_name(), 200 ),
			'external_reference' => (string) $order->get_id(),
			'payer_email'        => $order->get_billing_email(),
			'back_url'           => $return_url,
			'auto_recurring'     => $auto_recurring,
			'status'             => 'pending',
		];
	}

	/**
	 * Convert a plugin interval to Mercado Pago's days/months units.
	 *
	 * @param int    $count Count.
	 * @param string $unit  Unit (day, week, month, year; singular or plural).
	 * @return array [ frequency, frequency_type ].
	 */
	public function convert_interval( int $count, string $unit ): array {
		$count = max( 1, $count );

		switch ( strtolower( $unit ) ) {
			case 'day':
			case 'days':
				return [ $count, 'days' ];
			case 'week':
			case 'weeks':
				return [ $count * 7, 'days' ];
			case 'year':
			case 'years':
				return [ $count * 12, 'months' ];
			default:
				return [ $count, 'months' ];
		}
	}

	/**
	 * Thank-you page: the preapproval may already be authorized.
	 *
	 * @param int $order_id Order ID.
	 */
	public function order_received_page( $order_id ) {
		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order || $order->get_payment_method() !== $this->id || $order->has_status( 'completed' ) ) {
			return;
		}

		$preapproval_id = $order->get_meta( '_wp_subs_mercadopago_preapproval_id' );
		if ( empty( $preapproval_id ) ) {
			return;
		}

		$preapproval = $this->api_request( 'GET', '/preapproval/' . rawurlencode( $preapproval_id ) );
		if ( is_array( $preapproval ) && 'authorized' === ( $preapproval['status'] ?? '' ) ) {
			$this->activate_order( $order, $preapproval_id );
		}
	}

	/**
	 * Complete the first order of an authorized preapproval.
	 *
	 * @param WC_Order $order          Order.
	 * @param string   $preapproval_id Preapproval ID.
	 */
	protected function activate_order( WC_Order $order, string $preapproval_id ) {
		if ( $order->has_status( 'completed' ) ) {
			return;
		}

		$order->update_status( 'completed', __( 'Mercado Pago subscription authorized.', 'subscription' ) );

		$subscription = $this->get_subscription_by_order( $order );
		if ( $subscription ) {
			update_post_meta( (int) $subscription->subscription_id, '_wp_subs_mercadopago_preapproval_id', $preapproval_id );
		}
	}

	/**
	 * Find the plugin subscription row of an order.
	 *
	 * @param WC_Order $order Order.
	 * @return object|null
	 */
	protected function get_subscription_by_order( WC_Order $order ) {
		$subscriptions = Helper::get_subscriptions_from_order( $order->get_id() );
		if ( ! empty( $subscriptions ) ) {
			return reset( $subscriptions );
		}

		foreach ( $order->get_items() as $item ) {
			$found = Helper::get_subscription_from_order_item_id( $item->get_id() );
			if ( ! empty( $found ) ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * Resolve the first order of a preapproval.
	 *
	 * @param string $preapproval_id Preapproval ID.
	 * @return WC_Order|null
	 */
	protected function get_order_by_preapproval( string $preapproval_id ) {
		$orders = wc_get_orders(
			[
				'meta_key'   => '_wp_subs_mercadopago_preapproval_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $preapproval_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'limit'      => 1,
				'orderby'    => 'ID',
				'order'      => 'ASC',
			]
		);

		return ! empty( $orders ) ? reset( $orders ) : null;
	}

	/**
	 * Verify the x-signature header.
	 *
	 * @param string $data_id    Notification data id.
	 * @param string $signature  x-signature header.
	 * @param string $request_id x-request-id header.
	 * @return bool
	 */
	public function verify_signature( string $data_id, string $signature, string $request_id ): bool {
		if ( empty( $this->webhook_secret ) || empty( $signature ) ) {
			return false;
		}

		$ts = '';
		$v1 = '';
		foreach ( explode( ',', $signature ) as $part ) {
			$pair = array_map( 'trim', explode( '=', $part, 2 ) );
			if ( 2 === count( $pair ) ) {
				if ( 'ts' === $pair[0] ) {
					$ts = $pair[1];
				} elseif ( 'v1' === $pair[0] ) {
					$v1 = $pair[1];
				}
			}
		}

		if ( '' === $ts || '' === $v1 ) {
			return false;
		}

		$manifest = 'id:' . strtolower( $data_id ) . ';request-id:' . $request_id . ';ts:' . $ts . ';';

		return hash_equals( hash_hmac( 'sha256', $manifest, $this->webhook_secret ), $v1 );
	}

	/**
	 * Respond to a webhook and stop.
	 *
	 * @param string $message Message.
	 * @param int    $code    HTTP status.
	 */
	protected function respond( string $message, int $code = 200 ) {
		subscrpt_write_log( 'Mercado Pago webhook: ' . $message );
		wp_die( esc_html( $message ), absint( $code ), [ 'response' => absint( $code ) ] );
	}

	/**
	 * Process a Mercado Pago webhook.
	 */
	public function process_webhook() {
		$raw_body = file_get_contents( 'php://input' );
		$payload  = json_decode( (string) $raw_body, true );
		$payload  = is_array( $payload ) ? $payload : [];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Verified by HMAC signature below.
		$topic   = sanitize_text_field( wp_unslash( $_GET['type'] ?? ( $_GET['topic'] ?? ( $payload['type'] ?? '' ) ) ) );
		$data_id = sanitize_text_field( wp_unslash( $_GET['data_id'] ?? ( $_GET['data.id'] ?? ( $payload['data']['id'] ?? '' ) ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$signature  = isset( $_SERVER['HTTP_X_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_SIGNATURE'] ) ) : '';
		$request_id = isset( $_SERVER['HTTP_X_REQUEST_ID'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REQUEST_ID'] ) ) : '';

		if ( '' === $data_id || ! $this->verify_signature( $data_id, $signature, $request_id ) ) {
			$this->respond( 'Invalid or missing signature.', 401 );
		}

		// Never trust the payload: fetch the resource from Mercado Pago.
		if ( 'subscription_preapproval' === $topic ) {
			$this->handle_preapproval_event( $data_id );
		} elseif ( 'subscription_authorized_payment' === $topic ) {
			$this->handle_authorized_payment_event( $data_id );
		}

		$this->respond( sprintf( 'Event [%s] ignored.', $topic ) );
	}

	/**
	 * A preapproval changed status.
	 *
	 * @param string $preapproval_id Preapproval ID.
	 */
	protected function handle_preapproval_event( string $preapproval_id ) {
		$preapproval = $this->api_request( 'GET', '/preapproval/' . rawurlencode( $preapproval_id ) );
		if ( empty( $preapproval['status'] ) ) {
			$this->respond( 'Could not fetch preapproval.', 502 );
		}

		$order = $this->get_order_by_preapproval( $preapproval_id );
		if ( ! $order ) {
			$this->respond( 'Order not found. Queuing for retry.', 425 );
		}

		$subscription = $this->get_subscription_by_order( $order );
		$status       = $preapproval['status'];

		if ( 'authorized' === $status ) {
			$this->activate_order( $order, $preapproval_id );
		} elseif ( 'cancelled' === $status && $subscription ) {
			$wpsubs_id = (int) $subscription->subscription_id;
			if ( ! in_array( get_post_status( $wpsubs_id ), [ 'cancelled', 'expired' ], true ) ) {
				update_post_meta( $wpsubs_id, '_wp_subs_mercadopago_status', 'cancelled' );
				Action::status( 'cancelled', $wpsubs_id );
			}
		}

		$this->respond( sprintf( 'Preapproval %s is %s.', $preapproval_id, $status ) );
	}

	/**
	 * A recurring charge was attempted.
	 *
	 * @param string $authorized_payment_id Authorized payment ID.
	 */
	protected function handle_authorized_payment_event( string $authorized_payment_id ) {
		$authorized = $this->api_request( 'GET', '/authorized_payments/' . rawurlencode( $authorized_payment_id ) );
		if ( empty( $authorized['preapproval_id'] ) ) {
			$this->respond( 'Could not fetch authorized payment.', 502 );
		}

		$payment_status = $authorized['payment']['status'] ?? '';
		$transaction_id = isset( $authorized['payment']['id'] ) ? (string) $authorized['payment']['id'] : (string) $authorized_payment_id;

		if ( 'processed' !== ( $authorized['status'] ?? '' ) || 'approved' !== $payment_status ) {
			$first = $this->get_order_by_preapproval( (string) $authorized['preapproval_id'] );
			if ( $first ) {
				// translators: %s: payment status.
				$first->add_order_note( sprintf( __( 'Mercado Pago charge not approved (status: %s).', 'subscription' ), $payment_status ? $payment_status : ( $authorized['status'] ?? 'unknown' ) ) );
			}
			$this->respond( 'Charge not approved; no action taken.' );
		}

		// Duplicate delivery.
		$duplicates = wc_get_orders(
			[
				'transaction_id' => $transaction_id,
				'limit'          => 1,
			]
		);
		if ( ! empty( $duplicates ) ) {
			$this->respond( 'Transaction already processed.' );
		}

		$first = $this->get_order_by_preapproval( (string) $authorized['preapproval_id'] );
		if ( ! $first ) {
			$this->respond( 'Order not found. Queuing for retry.', 425 );
		}

		$subscription = $this->get_subscription_by_order( $first );
		if ( ! $subscription ) {
			$this->respond( 'Subscription not found. Queuing for retry.', 425 );
		}

		$wpsubs_id = (int) $subscription->subscription_id;
		update_post_meta( $wpsubs_id, '_wp_subs_mercadopago_preapproval_id', (string) $authorized['preapproval_id'] );

		// First charge of the cycle belongs to the first order; later charges are renewals.
		if ( '' === (string) $first->get_transaction_id() ) {
			$order = $first;
		} else {
			$order = Helper::create_renewal_order( $wpsubs_id );
			if ( ! $order instanceof WC_Order ) {
				$this->respond( 'Failed to create renewal order.', 500 );
			}
			$order->add_order_note( __( 'Renewal order created by Mercado Pago webhook.', 'subscription' ) );
		}

		$order->set_payment_method( $this->id );
		$order->set_transaction_id( $transaction_id );
		$order->update_status( 'completed', __( 'Payment completed by Mercado Pago webhook.', 'subscription' ) );
		$order->save();

		$this->respond( 'Payment completed.' );
	}

	/**
	 * Cancel the Mercado Pago preapproval when the subscription ends here.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function handle_subscription_cancellation( int $subscription_id ) {
		if ( 'cancelled' === get_post_meta( $subscription_id, '_wp_subs_mercadopago_status', true ) ) {
			return;
		}

		$order = wc_get_order( get_post_meta( $subscription_id, '_subscrpt_order_id', true ) );
		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		$preapproval_id = get_post_meta( $subscription_id, '_wp_subs_mercadopago_preapproval_id', true );
		if ( empty( $preapproval_id ) ) {
			// The preapproval lives on the first order; walk the subscription's orders to find it.
			foreach ( (array) Helper::get_related_orders( $subscription_id ) as $row ) {
				$related = wc_get_order( (int) $row->order_id );
				$found   = $related ? $related->get_meta( '_wp_subs_mercadopago_preapproval_id' ) : '';
				if ( ! empty( $found ) ) {
					$preapproval_id = $found;
					break;
				}
			}
		}
		if ( empty( $preapproval_id ) ) {
			return;
		}

		$result = $this->api_request( 'PUT', '/preapproval/' . rawurlencode( $preapproval_id ), [ 'status' => 'cancelled' ] );
		if ( is_array( $result ) && 'cancelled' === ( $result['status'] ?? '' ) ) {
			update_post_meta( $subscription_id, '_wp_subs_mercadopago_status', 'cancelled' );
			subscrpt_write_log( "Subscription #{$subscription_id} cancelled in Mercado Pago." );
		} else {
			subscrpt_write_log( "Failed to cancel subscription #{$subscription_id} in Mercado Pago." );
		}
	}

	/**
	 * Call the Mercado Pago API.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   Path.
	 * @param array|null $body   JSON body.
	 * @return array|null Decoded response, null on transport failure.
	 */
	protected function api_request( string $method, string $path, ?array $body = null ) {
		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->access_token,
				'Content-Type'  => 'application/json',
			],
		];
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::API_BASE . $path, $args );
		if ( is_wp_error( $response ) ) {
			subscrpt_write_log( 'Mercado Pago API error: ' . $response->get_error_message() );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Truncate a string.
	 *
	 * @param string $text Text.
	 * @param int    $max  Max length.
	 * @return string
	 */
	protected function truncate( string $text, int $max ): string {
		return mb_strlen( $text ) > $max ? mb_substr( $text, 0, $max - 1 ) . '…' : $text;
	}
}
