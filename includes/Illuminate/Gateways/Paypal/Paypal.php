<?php

namespace SpringDevs\Subscription\Illuminate\Gateways\Paypal;

use Exception;
use PHPUnit\TextUI\Help;
use SpringDevs\Subscription\Illuminate\Action;
use SpringDevs\Subscription\Illuminate\Helper;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Class PayPal
 * PayPal Payment Gateway for Subscription Plugin
 *
 * @package SpringDevs\Subscription\Illuminate\Gateways
 */
class Paypal extends \WC_Payment_Gateway {

	/**
	 * Sandbox mode.
	 *
	 * @var bool
	 */
	public $sandbox_mode = false;

	/**
	 * PayPal Client ID.
	 *
	 * @var string
	 */
	protected $client_id;

	/**
	 * PayPal Client Secret.
	 *
	 * @var string
	 */
	protected $client_secret;

	/**
	 * PayPal Webhook ID.
	 *
	 * @var string
	 */
	protected $webhook_id;

	/**
	 * API endpoint for PayPal.
	 *
	 * @var string
	 */
	protected $api_endpoint;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'wp_subscription_paypal';
		$this->has_fields         = false;
		$this->method_title       = __( 'PayPal for WPSubscription', 'subscription' );
		$this->method_description = __( 'Accept wp subscription recurring payments through PayPal. Only WP Subscription is supported.', 'subscription' );
		$this->supports           = [ 'products', 'subscriptions', 'refunds' ];
		$this->icon               = apply_filters( 'wp_subscription_paypal_icon', WP_SUBSCRIPTION_URL . '/assets/images/paypal.svg' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Plugin variables.
		$this->enabled     = $this->get_option( 'enabled' );
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		// PayPal Credentials.
		$this->sandbox_mode = 'yes' === $this->get_option( 'testmode', 'no' );

		if ( $this->sandbox_mode ) {
			$this->client_id     = $this->get_option( 'sandbox_client_id' );
			$this->client_secret = $this->get_option( 'sandbox_client_secret' );
			$this->webhook_id    = $this->get_option( 'sandbox_webhook_id' );
		} else {
			$this->client_id     = $this->get_option( 'client_id' );
			$this->client_secret = $this->get_option( 'client_secret' );
			$this->webhook_id    = $this->get_option( 'webhook_id' );
		}

		// Set Webhook URL.
		$this->update_option( 'webhook_url', $this->get_webhook_url() );

		// Set API endpoint.
		$this->api_endpoint = $this->sandbox_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

		// Actions.
		$this->init_actions();
	}

	/**
	 * Initialize actions for the gateway.
	 */
	protected function init_actions() {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		// Process order after payment.
		add_action( 'woocommerce_thankyou', [ $this, 'order_received_page' ] );

		// Hide gateway if no wp_subscription products are available.
		add_filter( 'woocommerce_available_payment_gateways', [ $this,'remove_wp_subs_paypal_gateway' ] );

		// WooCommerce webhook.
		add_action( 'woocommerce_api_' . $this->id, [ $this, 'process_webhook' ] );

		// Cancel subscription.
		add_action( 'subscrpt_subscription_expired', [ $this, 'handle_subscription_cancellation' ] );
		add_action( 'subscrpt_subscription_cancelled_email_notification', [ $this, 'handle_subscription_cancellation' ] );
	}

	/**
	 * Initialize Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		// Gateway settings styles.
		wp_enqueue_style( 'wp-subscription-gateway-settings', WP_SUBSCRIPTION_ASSETS . '/css/gateway.css', [], WP_SUBSCRIPTION_VERSION, 'all' );

		// Settings JS.
		wp_enqueue_script( 'wp-subscription-gateway-settings-script', WP_SUBSCRIPTION_ASSETS . '/js/gateway.js', [ 'jquery' ], WP_SUBSCRIPTION_VERSION, true );

		// Live/Sandbox toggle script.
		wp_enqueue_script( 'wp-subscription-gateway-settings-toggle-script', WP_SUBSCRIPTION_ASSETS . '/js/gateway_options_toggler.js', [ 'jquery' ], WP_SUBSCRIPTION_VERSION, true );

		$this->form_fields = [
			'enabled'                    => [
				'title'       => __( 'Enable/Disable', 'subscription' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable PayPal for WPSubscription', 'subscription' ),
				'default'     => 'no',
				'description' => __( 'Enable or Disable PayPal for WPSubscription payment gateway', 'subscription' ),
				'desc_tip'    => true,
				'class'       => 'wpsubs-toggle',
			],
			'testmode'                   => [
				'title'       => __( 'Test Mode', 'subscription' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable PayPal Sandbox', 'subscription' ),
				'default'     => 'no',
				'description' => __( 'PayPal sandbox can be used to test payments without using real money.', 'subscription' ),
				'desc_tip'    => true,
				'class'       => 'wpsubs-toggle',
			],

			'title'                      => [
				'title'       => __( 'Title', 'subscription' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'subscription' ),
				'default'     => __( 'PayPal', 'subscription' ),
				'desc_tip'    => true,
			],
			'description'                => [
				'title'       => __( 'Description', 'subscription' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'subscription' ),
				'default'     => __( 'Pay via PayPal; you can pay with your credit card if you do not have a PayPal account.', 'subscription' ),
				'desc_tip'    => true,
				'css'         => 'width: 400px; height: 75px;',
			],

			'paypal_creds_title'         => [
				'title'       => __( 'PayPal Credentials', 'subscription' ),
				'type'        => 'title',
				'description' => '',
				'class'       => 'wpsubs-paypal-live-creds',
			],
			'paypal_sandbox_creds_title' => [
				'title'       => __( 'PayPal Sandbox Credentials', 'subscription' ),
				'type'        => 'title',
				'description' => '',
				'class'       => 'wpsubs-paypal-sandbox-creds',
			],

			'paypal_creds_desc'          => [
				'title'       => '',
				'type'        => 'title',
				'description' => sprintf(
					// Translators: %1$s is the link to PayPal developer account, %2$s is the link to My Apps & Credentials.
					__( 'Create a <a href="%1$s" target="_blank">PayPal developer account</a>, go to <a href="%2$s" target="_blank">My Apps & Credentials</a>, select the toggle ( Sandbox or Live ), create an app, and copy <b>Client ID</b> and <b>Secret</b>.', 'subscription' ),
					'https://developer.paypal.com',
					'https://developer.paypal.com/dashboard/applications'
				),
			],
			'email'                      => [
				'title'       => __( 'Email', 'subscription' ),
				'type'        => 'email',
				'description' => __( 'PayPal Email Address (used to receive payments)', 'subscription' ),
				'default'     => '',
				'desc_tip'    => true,
			],

			// Live Credentials.
			'client_id'                  => [
				'title'       => __( 'Client ID', 'subscription' ),
				'type'        => 'password',
				'description' => __( 'Enter your PayPal Client ID copied from PayPal Apps & Credentials.', 'subscription' ),
				'default'     => '',
				'desc_tip'    => true,
				'class'       => 'wpsubs-paypal-live-creds',
			],
			'client_secret'              => [
				'title'       => __( 'Secret', 'subscription' ),
				'type'        => 'password',
				'description' => __( 'Enter your PayPal Secret copied from PayPal Apps & Credentials.', 'subscription' ),
				'default'     => '',
				'desc_tip'    => true,
				'class'       => 'wpsubs-paypal-live-creds',
			],
			'webhook_id'                 => [
				'title'       => __( 'Webhook ID', 'subscription' ),
				'type'        => 'password',
				'description' => __( 'Enter your Webhook ID copied from PayPal Apps & Credentials for webhook validation.', 'subscription' ),
				'default'     => '',
				'desc_tip'    => true,
				'class'       => 'wpsubs-paypal-live-creds',
			],

			// Sandbox Credentials.
			'sandbox_client_id'          => [
				'title'       => __( 'Client ID', 'subscription' ),
				'type'        => 'password',
				'description' => __( 'Enter your PayPal Client ID copied from PayPal Apps & Credentials.', 'subscription' ),
				'default'     => '',
				'desc_tip'    => true,
				'class'       => 'wpsubs-paypal-sandbox-creds',
			],
			'sandbox_client_secret'      => [
				'title'       => __( 'Secret', 'subscription' ),
				'type'        => 'password',
				'description' => __( 'Enter your PayPal Secret copied from PayPal Apps & Credentials.', 'subscription' ),
				'default'     => '',
				'desc_tip'    => true,
				'class'       => 'wpsubs-paypal-sandbox-creds',
			],
			'sandbox_webhook_id'         => [
				'title'       => __( 'Webhook ID', 'subscription' ),
				'type'        => 'password',
				'description' => __( 'Enter your Webhook ID copied from PayPal Apps & Credentials for webhook validation.', 'subscription' ),
				'default'     => '',
				'desc_tip'    => true,
				'class'       => 'wpsubs-paypal-sandbox-creds',
			],

			'webhook_url'                => [
				'title'       => __( 'Webhook URL', 'subscription' ),
				'type'        => 'text',
				'description' => __( '<p>In the <strong style="color:#1d4ed8">Apps & Credentials</strong> page of PayPal developer account open the newly created application and click <strong style="color:#1d4ed8">Add Webhook</strong> button.<br> On the <strong>Webhook URL</strong> field use this webhook link', 'subscription' ),
				'default'     => $this->get_webhook_url(),
				'disabled'    => true,
				'class'       => 'wpsubs-webhook-url',
			],
		];
	}

	/**
	 * Check if paypal can be used for the currency selected in the store.
	 *
	 * @return boolean
	 */
	public function is_currency_supported() {
		return in_array(
			get_woocommerce_currency(),
			apply_filters(
				'wp_subs_paypal_supported_currencies',
				[ 'AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP', 'RUB', 'INR' ]
			),
			true
		);
	}

	/**
	 * Show admin options is valid for use.
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		if ( $this->is_currency_supported() ) {
			parent::admin_options();
		} else {
			$currency_not_supported_message = sprintf(
				// Translators: %s is the title of the payment gateway.
				__( '<strong>%s</strong> options are disabled. PayPal Standard does not support your store currency.', 'subscription' ),
				$this->title
			);

			?>
			<div class="inline error">
				<p>
					<?php echo wp_kses_post( $currency_not_supported_message ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Get webhook URL for PayPal.
	 */
	public function get_webhook_url(): string {
		return add_query_arg( 'wc-api', $this->id, trailingslashit( get_home_url() ) );
	}

	/**
	 * Process order after payment received.
	 *
	 * @param int $order_id Order ID.
	 */
	public function order_received_page( $order_id ) {
		if ( ! is_order_received_page() || empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Return if order is not valid.
		if ( ! $order || empty( $order ) ) {
			return;
		}
		// Return if the order is not using WPSUBS PayPal.
		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		// Return if the order is already completed.
		if ( 'completed' === $order->get_status() ) {
			// Translators: %d is the order ID.
			$log_message = sprintf( __( 'Order %d was already completed. Skipping PayPal check.', 'subscription' ), $order_id );
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );
			return;
		}

		$paypal_subscription_id = isset( $_GET['subscription_id'] ) ? sanitize_text_field( wp_unslash( $_GET['subscription_id'] ) ) : '';
		$paypal_ba_token        = isset( $_GET['ba_token'] ) ? sanitize_text_field( wp_unslash( $_GET['ba_token'] ) ) : '';
		$paypal_token           = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		$paypal_payment_approved = false;

		if ( empty( $paypal_subscription_id ) ) {
			$paypal_subscription_id = $order->get_meta( $this->get_meta_key( 'subscription_id' ), true );
		}

		// OLD key migration.
		// If no data check if the data exists with the old key. And update if necessary.
		// ? Dev note: Remove after JAN 1, 2026.
		if ( empty( $paypal_subscription_id ) ) {
			$paypal_subscription_id = $order->get_meta( '_wp_subs_paypal_subscription_id', true );

			if ( ! empty( $paypal_subscription_id ) ) {
				$order->update_meta_data( $this->get_meta_key( 'subscription_id' ), $paypal_subscription_id );
				$order->save();
			}
		}

		if ( ! empty( $paypal_subscription_id ) ) {
			$paypal_subscription_data = $this->get_paypal_subscription( $paypal_subscription_id );

			if ( $paypal_subscription_data && in_array( $paypal_subscription_data->status ?? '', [ 'ACTIVE', 'APPROVED' ], true ) ) {
				$paypal_payment_approved = true;
			}
		}

		// Fallback to check PayPal Order if Subscription is not available.
		if ( ! $paypal_payment_approved && ! empty( $paypal_token ) ) {
			$paypal_order_data = $this->get_paypal_order( $paypal_token );

			if ( $paypal_order_data && in_array( $paypal_order_data->status ?? '', [ 'APPROVED', 'COMPLETED' ], true ) ) {
				$paypal_payment_approved = true;
			}
		}

		if ( $paypal_payment_approved ) {
			$order->update_status( 'completed', __( 'PayPal payment completed successfully.', 'subscription' ) );
			$order->save();
		}
	}

	/**
	 * Remove PayPal gateway if no wp_subscription products are in checkout.
	 *
	 * @param array $available_gateways Available gateways.
	 */
	public function remove_wp_subs_paypal_gateway( $available_gateways ) {
		if ( ! is_checkout() || ! is_array( $available_gateways ) || empty( $available_gateways ) ) {
			return $available_gateways;
		}

		$has_subs_in_cart = false;
		$cart_items       = WC()->cart->cart_contents;
		foreach ( $cart_items as $cart_item ) {
			if (
				isset( $cart_item['subscription'] ) ||
				$cart_item['data']->get_meta( '_subscrpt_enabled' )
			) {
				$has_subs_in_cart = true;
				break;
			}
		}

		if ( ! $has_subs_in_cart && isset( $available_gateways[ $this->id ] ) ) {
			unset( $available_gateways[ $this->id ] );
		}

		return $available_gateways;
	}

	/**
	 * Process webhook from PayPal.
	 */
	public function process_webhook() {
		// Get raw webhook data.
		$raw_body = file_get_contents( 'php://input' );
		$headers  = function_exists( 'getallheaders' ) ? getallheaders() : [];

		if ( empty( $raw_body ) ) {
			wp_subscrpt_write_log( 'PayPal webhook data is empty.' );
			wp_subscrpt_write_debug_log( "PayPal - process_webhook EMPTY \n" . $raw_body );
			wp_die( 'PayPal webhook data is empty.', '400 Bad Request', [ 'response' => 400 ] );
		}

		// Verify webhook.
		$this->verify_webhook( $headers, $raw_body );

		// Decode webhook data.
		$webhook_data = json_decode( $raw_body, true );

		// Get event type from webhook data.
		$event = $webhook_data['event_type'] ?? '';

		// Supported transaction events.
		$transaction_events = [
			'PAYMENT.SALE.COMPLETED',
			'PAYMENT.SALE.REFUNDED',
		];

		// Supported subscription events.
		$subscription_events = [
			'BILLING.SUBSCRIPTION.ACTIVATED',
			'BILLING.SUBSCRIPTION.UPDATED',
			'BILLING.SUBSCRIPTION.EXPIRED',
			'BILLING.SUBSCRIPTION.SUSPENDED',
			'BILLING.SUBSCRIPTION.CANCELLED',
		];

		// Order object.
		$order = null;

		// Get transaction ID from webhook data.
		$transaction_id = $webhook_data['resource']['sale_id'] ?? $webhook_data['resource']['id'] ?? '';

		// Get order by Transaction ID.
		if ( ! empty( $transaction_id ) ) {
			$orders = wc_get_orders( [ 'transaction_id' => $transaction_id ] );

			if ( ! empty( $orders ) ) {
				$order = reset( $orders );
			}
		}

		// Get subscription ID from webhook data.
		$subscription_id = $webhook_data['resource']['billing_agreement_id'] ?? $webhook_data['resource']['id'] ?? null;

		// Get order by Subscription ID.
		if ( ! $order && ! empty( $subscription_id ) ) {
			$orders = wc_get_orders( [ 'subscription_id' => $subscription_id ] );

			if ( ! empty( $orders ) ) {
				$order = reset( $orders );
			}
		}

		// Get parent order if the order is a refund order action.
		if ( $order && strpos( get_class( $order ), 'OrderRefund' ) ) {
			$parent_id = $order->get_parent_id() ?? null;

			if ( $parent_id ) {
				$order = wc_get_order( $parent_id );
			}
		}

		if ( empty( $order ) ) {
			$log_message = sprintf(
				// translators: %1$s: alert name; %2$s: order id.
				__( 'PayPal webhook received [%s]. Order not found.', 'subscription' ),
				$event,
			);
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $webhook_data ) );
			wp_die( esc_html( $log_message ), '404 not found', array( 'response' => 404 ) );
		}

		// Finally, handle the webhook.
		if ( in_array( $event, $transaction_events, true ) ) {
			$this->handle_transaction_event( $webhook_data, $order, $transaction_id, $subscription_id );
		} elseif ( in_array( $event, $subscription_events, true ) ) {
			$this->handle_subscription_event( $webhook_data, $order, $transaction_id, $subscription_id );
		} else {
			$log_message = sprintf(
				// translators: %1$s: alert name; %2$s: order id.
				__( 'PayPal webhook received [%s]. No actions taken.', 'subscription' ),
				$event,
			);
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $webhook_data ) );
			wp_die( esc_html( $log_message ), '200 success', array( 'response' => 200 ) );
		}
	}

	/**
	 * Verify webhook data from PayPal.
	 *
	 * @param array  $headers       Headers from the request.
	 * @param string $raw_body Webhook raw body from PayPal.
	 */
	public function verify_webhook( array $headers, string $raw_body ) {
		// Get PayPal Access Token.
		$access_token = $this->get_paypal_access_token();
		if ( ! $access_token ) {
			wp_subscrpt_write_log( 'PayPal webhook: Access Token unavailable.' );
			wp_die( 'Error: Access token not available. Cannot verify webhook.', '401 Unauthorized', array( 'response' => 401 ) );
		}

		// Prepare the request data to verify the webhook.
		$payload = [
			'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'] ?? $headers['Paypal-Auth-Algo'] ?? '',
			'cert_url'          => $headers['PAYPAL-CERT-URL'] ?? $headers['Paypal-Cert-Url'] ?? '',
			'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'] ?? $headers['Paypal-Transmission-Id'] ?? '',
			'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'] ?? $headers['Paypal-Transmission-Sig'] ?? '',
			'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? $headers['Paypal-Transmission-Time'] ?? '',
			'webhook_id'        => $this->webhook_id ?? '',
			'webhook_event'     => $raw_body,
		];

		// Verify webhook via REST API.
		$verified = $this->verify_paypal_webhook_rest_api( $payload, $raw_body, $access_token );

		if ( ! $verified ) {
			// Fallback to manual method if REST API verification fails.
			wp_subscrpt_write_log( 'PayPal webhook REST API verification failed. Retrying with manual verification.' );

			$verified = $this->verify_paypal_webhook_manual( $payload, $raw_body );
		}

		if ( ! $verified ) {
			wp_subscrpt_write_log( 'PayPal webhook verification failed.' );
			wp_subscrpt_write_debug_log( 'Webhook verification failed for data: ' . $raw_body );
			wp_die( 'Error: PayPal webhook verification failed.', '403 Forbidden', array( 'response' => 403 ) );
		}
	}

	/**
	 * Verify PayPal webhook with REST API.
	 *
	 * @param array  $payload       Payload data for verification.
	 * @param string $raw_body Webhook raw body from PayPal.
	 * @param string $access_token  PayPal Access Token.
	 */
	protected function verify_paypal_webhook_rest_api( array $payload, string $raw_body, string $access_token ): bool {
		// Fix the webhook_event to be an array.
		$payload['webhook_event'] = json_decode( $raw_body, true );

		// Verify webhook signature via PayPal REST API.
		try {
			$url  = $this->api_endpoint . '/v1/notifications/verify-webhook-signature';
			$args = [
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			];

			$response            = wp_remote_post( $url, $args );
			$response_data       = json_decode( wp_remote_retrieve_body( $response ), true );
			$verification_status = $response_data['verification_status'] ?? null;

			if ( empty( $verification_status ) || 'success' !== strtolower( $verification_status ) ) {
				wp_subscrpt_write_debug_log( 'PayPal Webhook Verification: ' . wp_json_encode( $response_data ) );
				return false;
			}

			return ( 'success' === strtolower( $verification_status ) ) ? true : false;

		} catch ( Exception $e ) {
			$log_message = 'PayPal Webhook Verification Failed: ' . $e->getMessage();
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );
			return false;
		}
	}

	/**
	 * Verify PayPal webhook manually (self verification).
	 *
	 * @param array  $payload       Payload data for verification.
	 * @param string $raw_body Webhook raw body from PayPal.
	 */
	protected function verify_paypal_webhook_manual( array $payload, string $raw_body ): bool {
		// Enforce CRC32 for 32-bit systems (edge case)
		$crc = sprintf( '%u', crc32( $raw_body ) );

		// Build Message
		$message = implode(
			'|',
			[
				$payload['transmission_id'],
				$payload['transmission_time'],
				$payload['webhook_id'],
				$crc,
			]
		);

		// Fetch & cache cert
		$cert_url  = esc_url_raw( $payload['cert_url'] );
		$cache_key = 'paypal_cert_' . md5( $cert_url );

		$cert_pem = get_transient( $cache_key );

		if ( ! $cert_pem ) {
			$response = wp_remote_get( $cert_url, [ 'timeout' => 20 ] );
			if ( is_wp_error( $response ) ) {
				return false;
			}

			$cert_pem = wp_remote_retrieve_body( $response );
			set_transient( $cache_key, $cert_pem, DAY_IN_SECONDS );
		}

		if ( empty( $cert_pem ) ) {
			return false;
		}

		// Signature
		$signature = base64_decode( $payload['transmission_sig'], true );

		if ( false === $signature ) {
			return false;
		}

		// Final verification
		$verified = openssl_verify(
			$message,
			$signature,
			$cert_pem,
			OPENSSL_ALGO_SHA256
		);

		return ( 1 === $verified );
	}

	/**
	 * Process Payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		return $this->process_paypal_payment( $order );
	}

	/**
	 * Process payments in PayPal.
	 *
	 * @param WC_Order $order The order object.
	 */
	protected function process_paypal_payment( WC_Order $order ): array {
		// Get PayPal Access Token.
		$access_token = $this->get_paypal_access_token();
		if ( ! $access_token ) {
			return [
				'result'   => 'error',
				'redirect' => '',
				'response' => 'PayPal payment failed. Please try again.',
			];
		}

		// Get the first order item.
		// Based on the logic, the order sould contain only one subscription item.
		$order_items = $order->get_items();
		$order_item  = ! empty( $order_items ) ? reset( $order_items ) : null;

		// Get WooCommerce Product.
		$wc_product_id   = null;
		$wc_variation_id = null;
		$wc_product      = null;
		if ( $order_item && $order_item instanceof WC_Order_Item_Product ) {
			$wc_product_id   = $order_item->get_product_id();
			$wc_variation_id = $order_item->get_variation_id();
			$wc_product      = wc_get_product( $wc_product_id );
		}

		if ( ! $wc_product ) {
			return [
				'result'   => 'error',
				'redirect' => '',
				'response' => 'Invalid product in order. Please check the order details.',
			];
		}

		// Get PayPal Product ID.
		$paypal_product_id = $this->get_paypal_product_id( $wc_product_id, $access_token );

		if ( ! $paypal_product_id ) {
			return [
				'result'   => 'error',
				'redirect' => '',
				'response' => 'PayPal payment failed. Please try again. (Failed to get PayPal product ID)',
			];
		}

		// Get PayPal Plan ID.
		$paypal_plan_id = $this->get_paypal_plan_id( $wc_product_id, $wc_variation_id, $paypal_product_id, $access_token );

		if ( ! $paypal_plan_id ) {
			return [
				'result'   => 'error',
				'redirect' => '',
				'response' => 'PayPal payment failed. Please try again. (Failed to get PayPal plan ID)',
			];
		}

		// Create Subscription in PayPal.
		$paypal_subscription_data = [
			'plan_id'             => $paypal_plan_id,
			'application_context' => [
				'return_url' => $this->get_return_url( $order ),
				'cancel_url' => $order->get_cancel_order_url(),
			],
		];

		$paypal_subscription = $this->create_paypal_subscription( $paypal_subscription_data, $access_token );

		if ( empty( $paypal_subscription->id ?? null ) ) {
			return [
				'result'   => 'error',
				'redirect' => '',
				'response' => 'PayPal payment failed. Please try again. (Failed to create PayPal subscription)',
			];
		}

		// Save PayPal Subscription ID in order meta.
		$order->update_meta_data( $this->get_meta_key( 'subscription_id' ), $paypal_subscription->id );
		$order->save();

		// Get payment link.
		$paypal_subscription_pay_link = null;
		foreach ( ( $paypal_subscription->links ?? [] ) as $link_obj ) {
			if ( 'approve' === $link_obj->rel ) {
				$paypal_subscription_pay_link = $link_obj->href;
				break;
			}
		}

		if ( empty( $paypal_subscription_pay_link ) ) {
			return [
				'result'   => 'error',
				'redirect' => '',
				'response' => 'PayPal payment failed. Please try again. (Failed to get PayPal subscription approval link)',
			];
		} else {
			return [
				'result'   => 'success',
				'redirect' => $paypal_subscription_pay_link,
			];
		}
	}

	/**
	 * Get PayPal product ID.
	 *
	 * @param int    $wc_product_id WooCommerce Product ID.
	 * @param string $access_token  PayPal Access Token.
	 */
	public function get_paypal_product_id( int $wc_product_id, string $access_token ): ?string {
		$wc_product = wc_get_product( $wc_product_id );

		// Get data from product meta.
		// TODO: add home_url, wc_product_id etc to avoid duplication in paypal.
		$paypal_data = get_post_meta( $wc_product_id, $this->get_meta_key( 'product_data' ), true );

		$paypal_product_id = $paypal_data['product_id'] ?? null;
		$paypal_image_url  = $paypal_data['image_url'] ?? null;

		// If PayPal product ID is not available in meta, get or create a new PayPal product.
		if ( ! $paypal_product_id ) {
			$paypal_product = $this->get_or_create_paypal_product( $wc_product, $access_token );

			if ( $paypal_product ) {
				$paypal_product_id = $paypal_product->id;

				// Save PayPal product ID in WooCommerce product meta.
				$data = [
					'product_id' => $paypal_product->id,
					'image_url'  => $paypal_product->image_url ?? '',
					'home_url'   => $product_data->home_url ?? '',
				];
				update_post_meta( $wc_product_id, $this->get_meta_key( 'product_data' ), $data );
			}
		}

		// TODO: add logic to update image url if changed.
		// $current_image_url = $this->truncate_string( wp_get_attachment_url( $wc_product->get_image_id() ), 2000 );
		// if ( $paypal_product_id && $paypal_image_url !== $current_image_url ) {}

		// Return PayPal product ID or null if not found.
		return $paypal_product_id;
	}

	/**
	 * Get PayPal plan ID.
	 *
	 * @param int    $wc_product_id WooCommerce Product ID.
	 * @param int    $wc_variation_id WooCommerce Variation ID.
	 * @param string $paypal_product_id PayPal Product ID.
	 * @param string $access_token  PayPal Access Token.
	 */
	public function get_paypal_plan_id( int $wc_product_id, int $wc_variation_id, string $paypal_product_id, string $access_token ): ?string {
		$wc_product = wc_get_product( $wc_product_id );
		if ( 0 !== $wc_variation_id ) {
			$wc_product = wc_get_product( $wc_variation_id );
		}

		// Get data from product meta.
		$plan_id = get_post_meta( $wc_product_id, $this->get_meta_key( 'plan_id' ), true );
		// $plan_description = get_post_meta( $wc_product_id, $this->get_meta_key( 'plan_desc' ), true );

		if ( empty( $plan_id ) ) {
			$plan_id = null;
		}

		// Generate plan data.
		$plan_data = $this->generate_plan_data( $wc_product, $paypal_product_id );

		// TODO: implement logic to find and get plan.
		// ---------- .

		// Create plan if not available.
		if ( empty( $plan_id ) ) {
			$paypal_plan = $this->create_paypal_plan( $plan_data, $access_token );

			if ( $paypal_plan ) {
				$plan_id = $paypal_plan->id;

				// Save PayPal plan ID and description in WooCommerce product meta.
				update_post_meta( $wc_product_id, $this->get_meta_key( 'plan_id' ), $plan_id );
				update_post_meta( $wc_product_id, $this->get_meta_key( 'plan_desc' ), $paypal_plan->description ?? '' );
			}
		}

		// TODO: implement logic to update plan if changed.
		// ---------- .

		return $plan_id;
	}

	/**
	 * Get or create PayPal product.
	 *
	 * @param WC_Product $wc_product WooCommerce Product.
	 * @param string     $access_token PayPal Access Token.
	 */
	public function get_or_create_paypal_product( WC_Product $wc_product, string $access_token ) {
		// Prepare product data.
		$product_data = [
			'name'        => $this->truncate_string( $wc_product->get_name(), 126 ),
			'description' => $this->truncate_string( $wc_product->get_short_description(), 256 ),
			'type'        => $wc_product->get_virtual() ? 'DIGITAL' : 'PHYSICAL',
			'image_url'   => $wc_product->get_image_id() ? $this->truncate_string( wp_get_attachment_url( $wc_product->get_image_id() ), 2000 ) : '',
			'home_url'    => $this->truncate_string( get_permalink( $wc_product->get_id() ), 2000 ),
		];

		// TODO: implement logic to find existing PayPal product.
		// $paypal_product = $this->find_paypal_product( $product_data, $access_token );

		// If not found, create a new PayPal product.
		$paypal_product = $this->create_paypal_product( $product_data, $access_token );

		// Return PayPal product or null.
		return $paypal_product;
	}

	/**
	 * Handle transaction event from PayPal.
	 *
	 * @param array       $webhook_data Webhook data from PayPal.
	 * @param WC_Order    $order Order object.
	 * @param string|null $transaction_id Transaction ID from webhook data.
	 * @param string|null $subscription_id Subscription ID from webhook data.
	 */
	public function handle_transaction_event( array $webhook_data, WC_Order $order, ?string $transaction_id, ?string $subscription_id ) {
		// Get event type.
		$event = $webhook_data['event_type'] ?? 'N/A';

		if ( ! $order ) {
			$log_message = sprintf(
				// translators: %1$s: alert name; %2$s: subscription id.
				__( 'Transaction webhook received [%1$s]. No order found for subscription ID [%2$s].', 'subscription' ),
				$event,
				$subscription_id
			);
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $webhook_data ) );
			wp_die( esc_html( $log_message ), '404 not found', array( 'response' => 404 ) );
		}

		switch ( $event ) {
			case 'PAYMENT.SALE.COMPLETED':
				if ( $order->update_status( 'completed' ) ) {
					$order->set_transaction_id( $transaction_id );
					$order->add_order_note( __( 'Payment completed by paypal webhook.', 'subscription' ) );
					$order->save();

					wp_die( 'Order activated.', '200 Success', array( 'response' => 200 ) );
				} else {
					$order->add_order_note( __( 'Failed to complete payment. Requested by paypal webhook.', 'subscription' ) );
					$order->save();

					wp_die( 'Order activation failed.', '506 Internal Error', array( 'response' => 506 ) );
				}
				break;

			case 'PAYMENT.SALE.REFUNDED':
				if ( $order->update_status( 'refunded' ) ) {
					$order->add_order_note( __( 'Payment refunded by paypal webhook.', 'subscription' ) );
					$order->save();

					wp_die( 'Order refunded.', '200 Success', array( 'response' => 200 ) );
				} else {
					$order->add_order_note( __( 'Failed to refund payment. Requested by paypal webhook.', 'subscription' ) );
					$order->save();

					wp_die( 'Order refund failed.', '506 Internal Error', array( 'response' => 506 ) );
				}
				break;

			default:
				$log_message = sprintf(
					// translators: %s: alert name.
					__( 'Transaction webhook received [%s]. No actions taken.', 'subscription' ),
					$event,
				);
				wp_subscrpt_write_log( $log_message );
				wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $webhook_data ) );
				wp_die( esc_html( $log_message ), '200 success', array( 'response' => 200 ) );
		}
	}

	/**
	 * Handle subscription event from PayPal.
	 *
	 * @param array       $webhook_data Webhook data from PayPal.
	 * @param WC_Order    $order Order object.
	 * @param string|null $transaction_id Transaction ID from webhook data.
	 * @param string|null $paypal_subscription_id Subscription ID from webhook data.
	 */
	public function handle_subscription_event( array $webhook_data, WC_Order $order, ?string $transaction_id, ?string $paypal_subscription_id ) {
		// Get event type.
		$event = $webhook_data['event_type'] ?? 'N/A';

		// Subscription.
		$subscription = Helper::get_subscriptions_from_order( $order );

		// If no subscription, try to get from order item.
		if ( empty( $subscription ) ) {
			$log_message = __( 'Subscription not found. Attempting to get from order item.', 'subscription' );
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );

			$order_items = $order->get_items();
			foreach ( $order_items as $item ) {
				$tmp_subs = Helper::get_subscription_from_order_item_id( $item->get_id() );

				if ( ! empty( $tmp_subs ) ) {
					$subscription = $tmp_subs;
					break;
				}
			}
		}

		// If still no subscription, exit.
		if ( empty( $subscription ) || empty( $subscription->subscription_id ?? null ) ) {
			$log_message = sprintf(
					// translators: %s: alert name.
				__( 'Subscription webhook received [%s]. Subscription not found.', 'subscription' ),
				$event,
			);
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $webhook_data ) );
			wp_die( esc_html( $log_message ), '404 not found', array( 'response' => 404 ) );
		}

		switch ( $event ) {
			case 'BILLING.SUBSCRIPTION.ACTIVATED':
				if ( ! in_array( get_post_status( $subscription->subscription_id ), [ 'active' ], true ) ) {
					Action::status( 'active', $subscription->subscription_id );

					$log_message = __( 'Subscription activated by PayPal webhook.', 'subscription' );
					wp_subscrpt_write_log( $log_message );
					wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $webhook_data ) );
					wp_die( esc_html( $log_message ), '200 success', array( 'response' => 200 ) );
				}

				wp_die( esc_html( __( 'Subscription webhook received. No actions taken.', 'subscription' ) ), '200 success', array( 'response' => 200 ) );
				break;

			case 'BILLING.SUBSCRIPTION.EXPIRED':
				if ( in_array( get_post_status( $subscription->subscription_id ), [ 'active', 'pe_cancelled' ], true ) ) {
					Action::status( 'expired', $subscription->subscription_id );

					$log_message = __( 'Subscription expired by PayPal webhook.', 'subscription' );
					wp_subscrpt_write_log( $log_message );
					wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $webhook_data ) );
					wp_die( esc_html( $log_message ), '200 success', array( 'response' => 200 ) );
				}

				wp_die( esc_html( __( 'Subscription webhook received. No actions taken.', 'subscription' ) ), '200 success', array( 'response' => 200 ) );
				break;

			case 'BILLING.SUBSCRIPTION.CANCELLED':
				if ( ! in_array( get_post_status( $subscription->subscription_id ), [ 'cancelled' ], true ) ) {
					Action::status( 'cancelled', $subscription->subscription_id );

					$log_message = __( 'Subscription cancelled by PayPal webhook.', 'subscription' );
					wp_subscrpt_write_log( $log_message );
					wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $webhook_data ) );
					wp_die( esc_html( $log_message ), '200 success', array( 'response' => 200 ) );
				}

				wp_die( esc_html( __( 'Subscription webhook received. No actions taken.', 'subscription' ) ), '200 success', array( 'response' => 200 ) );
				break;

			default:
				$log_message = sprintf(
						// translators: %s: alert name.
					__( 'Subscription webhook received [%s]. No actions taken.', 'subscription' ),
					$event,
				);
				wp_subscrpt_write_log( $log_message );
				wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $webhook_data ) );
				wp_die( esc_html( $log_message ), '200 success', array( 'response' => 200 ) );
				break;
		}
	}

	/**
	 * Handle subscription cancellation.
	 *
	 * @param int $subscription_id Subscription ID.
	 */
	public function handle_subscription_cancellation( int $subscription_id ) {
		$order_id = get_post_meta( $subscription_id, '_subscrpt_order_id', true );
		$order    = wc_get_order( $order_id );

		// get order payment method
		$payment_method = $order->get_payment_method();
		if ( 'paypal' !== $payment_method ) {
			return;
		}

		// Get paypal subscription ID from order meta.
		$paypal_subscription_id = $order->get_meta( $this->get_meta_key( 'subscription_id' ) );

		if ( empty( $paypal_subscription_id ) ) {
			wp_subscrpt_write_log( 'PayPal subscription ID not found in order meta. Attempting to get from order history.' );

			global $wpdb;
			$table_name      = $wpdb->prefix . 'subscrpt_order_relation';
			$order_histories = $wpdb->get_results( // phpcs:ignore
				$wpdb->prepare(
					'SELECT * FROM %i WHERE subscription_id=%d ORDER BY order_id DESC',
					[ $table_name, $subscription_id ]
				)
			);

			foreach ( $order_histories as $history ) {
				// Get order ID from history.
				$order_id = $history->order_id ?? null;
				$order    = wc_get_order( $order_id );

				// Get PayPal subscription ID from order meta.
				$tmp_paypal_subs_id = $order->get_meta( $this->get_meta_key( 'subscription_id' ) );

				// OLD key migration.
				// If no data check if the data exists with the old key. And update if necessary.
				// ? Dev note: Remove after JAN 1, 2026.
				if ( empty( $tmp_paypal_subs_id ) ) {
					$tmp_paypal_subs_id = $order->get_meta( '_wp_subs_paypal_subscription_id', true );

					if ( ! empty( $tmp_paypal_subs_id ) ) {
						$order->update_meta_data( $this->get_meta_key( 'subscription_id' ), $tmp_paypal_subs_id );
						$order->save();
					}
				}

				if ( ! empty( $tmp_paypal_subs_id ) ) {
					$paypal_subscription_id = $tmp_paypal_subs_id;
					break;
				}
			}
		}

		// Get PayPal Access Token.
		$access_token = $this->get_paypal_access_token();
		if ( ! $access_token ) {
			wp_subscrpt_write_log( 'Access token not found. Trying to get again.' );

			$access_token = $this->get_paypal_access_token();

			if ( ! $access_token ) {
				wp_subscrpt_write_log( 'Subscription cancel failed.' );
				return;
			}
		}

		// Cancel subscription in PayPal.
		$this->cancel_paypal_subscription( $paypal_subscription_id, $access_token, 'Customer requested cancellation.' );
	}

	// * ------------------------------------------------------------------------ * //
	// * -------------------- Utility Methods [start] --------------------------- * //

	/**
	 * Truncate long string.
	 *
	 * @param string $long_string The long string to truncate.
	 * @param int    $max_length  The maximum length of the string.
	 * @return string The truncated string if it exceeds the maximum length, otherwise the original string
	 */
	public function truncate_string( string $long_string, int $max_length = 48 ): string {
		return strlen( $long_string ) <= $max_length ? $long_string : substr( $long_string, 0, $max_length );
	}

	/**
	 * Get Prefixed Meta Key.
	 * Prefix the key with '_wp_subs_' to avoid possible conflicts with other plugins.
	 *
	 * @param string      $key The key to prefix.
	 * @param string|null $mode_override Optional mode override (sandbox/live).
	 */
	public function get_meta_key( string $key, ?string $mode_override = null ): string {
		$keys         = [
			'product_data'    => 'product_data',
			'plan_id'         => 'plan_id',
			'plan_desc'       => 'plan_description',
			'subscription_id' => 'subscription_id',
		];
		$selected_key = $keys[ $key ] ?? $key;

		$mode_string = $this->sandbox_mode ? 'sandbox_' : 'live_';
		if ( ! empty( $mode_override ) ) {
			$mode_string = 'sandbox' === $mode_override ? 'sandbox_' : 'live_';
		}

		return '_wp_subs_paypal_' . $mode_string . $selected_key;
	}

	/**
	 * Function to remove thousands separator.
	 *
	 * @param string $price The price to format.
	 */
	public function wpsubs_format_price( string $price ): string {
		$thousand_separator = wc_get_price_thousand_separator();
		$decimal_separator  = wc_get_price_decimal_separator();

		// Remove thousand separators.
		$price = str_replace( $thousand_separator, '', $price );

		// Remove trailing zeros.
		if ( strpos( $price, $decimal_separator ) !== false ) {
			$parts = explode( $decimal_separator, $price );

			// Remove trailing zeros from the decimal part.
			$parts[1] = rtrim( $parts[1], '0' );

			// Rejoin the parts.
			$price = '' === $parts[1] ? $parts[0] : $parts[0] . $decimal_separator . $parts[1];
		}
		return $price;
	}

	/**
	 * Generate PayPal Plan Data.
	 *
	 * @param WC_Product $wc_product WooCommerce Product.
	 * @param string     $paypal_product_id PayPal Product ID.
	 */
	public function generate_plan_data( WC_Product $wc_product, string $paypal_product_id ): array {
		// Get WPSubscription wrapped product.
		// $wpsubs_product type WC_Product
		$wpsubs_product = Subscription::get_subs_product( $wc_product );

		// Name.
		$name = $this->truncate_string( $wc_product->get_name(), 126 );

		// Description.
		$description = $this->truncate_string( $wc_product->get_short_description(), 126 );

		// Price.
		$price = wc_get_price_including_tax( $wc_product );
		$price = $this->wpsubs_format_price( $price );

		// Convert plural interval to singular.
		// subscrpt_get_typos function of the plugin have translator on the intervals. PayPal will only accept english.
		$convert_interval = function ( $interval ) {
			switch ( strtolower( $interval ) ) {
				case 'day':
				case 'days':
					return 'DAY';
				case 'week':
				case 'weeks':
					return 'WEEK';
				case 'month':
				case 'months':
					return 'MONTH';
				case 'year':
				case 'years':
					return 'YEAR';
				default:
					return 'MONTH';
			}
		};

		// Recurring Details.
		$plan_length    = $wpsubs_product->get_timing_per();
		$plan_interval  = $convert_interval( $wpsubs_product->get_timing_option() );
		$trial_length   = $wpsubs_product->get_trial_timing_per();
		$trial_interval = $convert_interval( $wpsubs_product->get_trial_timing_option() );
		$signup_fee     = $wpsubs_product->get_signup_fee();

		// get value for total_cycles from _subscrpt_max_no_payment
		$total_cycles = $wc_product->get_meta( '_subscrpt_max_no_payment' ) ?: 0;
		$total_cycles = $total_cycles ?? 1;

		// Billing Cycles.
		$billing_cycles = [];

		// Add trial cycle in billing cycles if available.
		if ( (int) $trial_length > 0 ) {
			$billing_cycles[] = [
				'tenure_type'  => 'TRIAL',
				'sequence'     => 1,
				'total_cycles' => $total_cycles,
				'frequency'    => [
					'interval_unit'  => $trial_interval,
					'interval_count' => (int) $trial_length,
				],
			];
		}

		// Add regular cycle in billing cycles.
		$billing_cycles[] = [
			'tenure_type'    => 'REGULAR',
			'sequence'       => count( $billing_cycles ) + 1,
			'total_cycles'   => 0,
			'pricing_scheme' => [
				'fixed_price' => [
					'value'         => $price,
					'currency_code' => get_woocommerce_currency(),
				],
			],
			'frequency'      => [
				'interval_unit'  => $plan_interval,
				'interval_count' => (int) $plan_length,
			],
		];

		// Payment Preferences.
		$payment_preferences = [
			'auto_bill_outstanding'     => true,
			'setup_fee_failure_action'  => 'CANCEL',
			'payment_failure_threshold' => 3,
			'setup_fee'                 => [
				'value'         => (string) $signup_fee,
				'currency_code' => get_woocommerce_currency(),
			],
		];

		// Final Data.
		$plan_data = [
			'product_id'          => $paypal_product_id,
			'name'                => $name,
			'description'         => $description,
			'billing_cycles'      => $billing_cycles,
			'quantity_supported'  => false,
			'payment_preferences' => $payment_preferences,
		];
		return $plan_data;
	}

	// * -------------------- Utility Methods [end] --------------------------- * //
	// * ---------------------------------------------------------------------- * //


	// * ---------------------------------------------------------------- * //
	// * -------------------- API Operations [start] -------------------- * //
	// ? Keep this section strictly for API operations. No other logic like data extraction should be added here.

	/**
	 * Get PayPal Access Token.
	 */
	private function get_paypal_access_token(): ?string {
		try {
			$url  = $this->api_endpoint . '/v1/oauth2/token';
			$args = [
				'method'  => 'POST',
				'headers' => [
					'Accept'          => 'application/json',
					'Accept-Language' => 'en_US',
					'Authorization'   => 'Basic ' . base64_encode( $this->client_id . ':' . $this->client_secret ), // phpcs:ignore
				],
				'body'    => [
					'grant_type' => 'client_credentials',
				],
			];

			$response      = wp_remote_post( $url, $args );
			$response_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( isset( $response_data->error ) || ! isset( $response_data->access_token ) ) {
				$error_description = ! empty( $response_data ) ? $response_data->error_description ?? 'Unknown error' : 'Unknown error';
				$log_message       = 'Gateway Error : PayPal access token - ' . $error_description;
				wp_subscrpt_write_log( $log_message );
				wp_subscrpt_write_debug_log( $log_message );

				return null;
			}

			return $response_data->access_token;
		} catch ( Exception $e ) {
			$log_message = $e->getMessage();
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );

			return null;
		}
	}

	/**
	 * Create PayPal product.
	 *
	 * @param array  $product_data   Product data to create.
	 * @param string $access_token   PayPal Access Token.
	 */
	private function create_paypal_product( array $product_data, string $access_token ): ?object {
		if ( empty( $product_data['name'] ?? null ) || empty( $product_data['type'] ?? null ) ) {
			$log_message = __( 'PayPal Product Creation Error: Product data is incomplete. Name and type are required.', 'subscription' );
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );
			return null;
		}

		// Prepare the body for the API request.
		$body = [
			'name' => $product_data['name'],
			'type' => $product_data['type'],
		];
		if ( ! empty( $product_data['description'] ?? null ) ) {
			$body['description'] = $product_data['description'];
		}
		if ( ! empty( $product_data['category'] ?? null ) ) {
			$body['category'] = $product_data['category'];
		}
		if ( ! empty( $product_data['image_url'] ?? null ) && ! strpos( $product_data['image_url'], '.test' ) ) {
			$body['image_url'] = $product_data['image_url'];
		}
		if ( ! empty( $product_data['home_url'] ?? null ) && ! strpos( $product_data['home_url'], '.test' ) ) {
			$body['home_url'] = $product_data['home_url'];
		}

		try {
			$url  = $this->api_endpoint . '/v1/catalogs/products';
			$args = [
				'method'  => 'POST',
				'headers' => [
					'Authorization'     => 'Bearer ' . $access_token,
					'Content-Type'      => 'application/json',
					'Prefer'            => 'return=representation',
					'PayPal-Request-Id' => uniqid( 'wp-subs-paypal-', true ),
				],
				'body'    => wp_json_encode( $body ),
			];

			$response      = wp_remote_post( $url, $args );
			$response_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( empty( $response_data->id ?? null ) ) {
				$log_message = 'Error creating PayPal product: ' . ( $response_data->error_description ?? 'Unknown error' );
				wp_subscrpt_write_log( $log_message );
				wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $response_data ) );
				return null;
			}

			return $response_data;
		} catch ( Exception $e ) {
			$log_message = 'Error creating PayPal product: ' . $e->getMessage();
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );
			return null;
		}
	}

	/**
	 * Create PayPal plan.
	 *
	 * @param array  $plan_data     Plan data to create.
	 * @param string $access_token  PayPal Access Token.
	 */
	private function create_paypal_plan( array $plan_data, string $access_token ): ?object {
		// Prepare the body for the API request.
		$body = [
			'product_id'          => $plan_data['product_id'],
			'name'                => $plan_data['name'],
			'billing_cycles'      => $plan_data['billing_cycles'],
			'payment_preferences' => $plan_data['payment_preferences'],
		];
		if ( ! empty( $plan_data['description'] ?? null ) ) {
			$body['description'] = $plan_data['description'];
		}
		if ( ! empty( $plan_data['quantity_supported'] ?? null ) ) {
			$body['quantity_supported'] = $plan_data['quantity_supported'];
		}

		try {
			$url  = $this->api_endpoint . '/v1/billing/plans';
			$args = [
				'method'  => 'POST',
				'headers' => [
					'Authorization'     => 'Bearer ' . $access_token,
					'Content-Type'      => 'application/json',
					'Prefer'            => 'return=representation',
					'PayPal-Request-Id' => uniqid( 'wp-subs-paypal-', true ),
				],
				'body'    => wp_json_encode( $body ),
			];

			$response      = wp_remote_post( $url, $args );
			$response_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( empty( $response_data->id ?? null ) ) {
				$log_message = 'Error creating PayPal plan: ' . ( $response_data->error_description ?? $response_data->message ?? 'Unknown error' );
				wp_subscrpt_write_log( $log_message );
				wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $response_data ) );
				return null;
			}

			return $response_data;
		} catch ( Exception $e ) {
			$log_message = 'Error creating PayPal plan: ' . $e->getMessage();
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );
			return null;
		}
	}

	/**
	 * Create PayPal subscription.
	 *
	 * @param array  $paypal_subscription_data PayPal subscription data.
	 * @param string $access_token    PayPal Access Token.
	 */
	private function create_paypal_subscription( array $paypal_subscription_data, string $access_token ): ?object {
		// Prepare the body for the API request.
		$body = [
			'plan_id'             => $paypal_subscription_data['plan_id'],
			'application_context' => $paypal_subscription_data['application_context'],
		];

		try {
			$url  = $this->api_endpoint . '/v1/billing/subscriptions';
			$args = [
				'method'  => 'POST',
				'headers' => [
					'Authorization'     => 'Bearer ' . $access_token,
					'Content-Type'      => 'application/json',
					'Prefer'            => 'return=representation',
					'PayPal-Request-Id' => uniqid( 'wp-subs-paypal-', true ),
				],
				'body'    => wp_json_encode( $body ),
			];

			$response      = wp_remote_post( $url, $args );
			$response_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( empty( $response_data->id ?? null ) ) {
				$log_message = 'Error creating PayPal subscription: ' . ( $response_data->error_description ?? $response_data->message ?? 'Unknown error' );
				wp_subscrpt_write_log( $log_message );
				wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $response_data ) );
				return null;
			}

			return $response_data;
		} catch ( Exception $e ) {
			$log_message = 'Error creating PayPal subscription: ' . $e->getMessage();
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );
			return null;
		}
	}

	/**
	 * Cancel PayPal subscription.
	 *
	 * @param string $subscription_id PayPal Subscription ID.
	 * @param string $access_token    PayPal Access Token.
	 * @param string $reason          Reason for cancellation.
	 */
	private function cancel_paypal_subscription( string $subscription_id, string $access_token, string $reason = 'admin cancel' ): bool {
		// Prepare the body for the API request.
		$body = [
			'reason' => $reason,
		];

		try {
			$url = $this->api_endpoint . "/v1/billing/subscriptions/$subscription_id/cancel";

			$args = [
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $body ),
			];

			$response      = wp_remote_post( $url, $args );
			$response_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( ! empty( $response_data->message ?? null ) ) {
				$log_message = 'Error cancelling PayPal subscription: ' . ( $response_data->message ?? 'Unknown error' );
				wp_subscrpt_write_log( $log_message );
				wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $response_data ) );
				return false;
			}

			return true;
		} catch ( Exception $e ) {
			$log_message = 'Error cancelling PayPal subscription: ' . $e->getMessage();
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );
			return false;
		}
	}

	/**
	 * Get PayPal order details.
	 *
	 * @param string $order_id      PayPal Order ID.
	 */
	public function get_paypal_order( string $order_id ) {
		// Get PayPal Access Token.
		$access_token = $this->get_paypal_access_token();
		if ( ! $access_token ) {
			wp_subscrpt_write_log( 'Failed to get PayPal order; Access Token unavailable.' );
			return false;
		}

		try {
			$url  = $this->api_endpoint . "/v2/checkout/orders/$order_id";
			$args = [
				'method'  => 'GET',
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
			];

			$response      = wp_remote_get( $url, $args );
			$response_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( empty( $response_data->id ?? null ) ) {
				$log_message = 'Error getting PayPal order: ' . ( $response_data->error_description ?? $response_data->message ?? 'Unknown error' );
				wp_subscrpt_write_log( $log_message );
				wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $response_data ) );
				return null;
			}

			return $response_data;
		} catch ( Exception $e ) {
			$log_message = 'Failed to get PayPal order; ' . $e->getMessage();
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );
			return false;
		}
	}

	/**
	 * Get PayPal subscription details.
	 *
	 * @param string $subscription_id PayPal Subscription ID.
	 */
	public function get_paypal_subscription( string $subscription_id ): ?object {
		// Get PayPal Access Token.
		$access_token = $this->get_paypal_access_token();
		if ( ! $access_token ) {
			wp_subscrpt_write_log( 'Failed to get PayPal Subscription; Access Token unavailable.' );
			return null;
		}

		try {
			$url  = $this->api_endpoint . "/v1/billing/subscriptions/$subscription_id";
			$args = [
				'method'  => 'GET',
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
			];

			$response      = wp_remote_get( $url, $args );
			$response_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( empty( $response_data->id ?? null ) ) {
				$log_message = 'Error getting PayPal subscription: ' . ( $response_data->error_description ?? $response_data->message ?? 'Unknown error' );
				wp_subscrpt_write_log( $log_message );
				wp_subscrpt_write_debug_log( $log_message . ' ' . wp_json_encode( $response_data ) );
				return null;
			}

			return $response_data;
		} catch ( Exception $e ) {
			$log_message = 'Failed to get PayPal subscription; ' . $e->getMessage();
			wp_subscrpt_write_log( $log_message );
			wp_subscrpt_write_debug_log( $log_message );
			return null;
		}
	}

	// * -------------------- API Operations [end] -------------------- * //
	// * -------------------------------------------------------------- * //
}
