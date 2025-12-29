<?php
/**
 * Guest Checkout File
 *
 * @package SpringDevs\Subscription\Illuminate
 */

namespace SpringDevs\Subscription\Illuminate;

/**
 * Class GuestCheckout
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class GuestCheckout {
	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Add guest checkout settings
		add_filter( 'subscrpt_settings_fields', [ $this, 'add_guest_checkout_settings_fields' ] );
		add_action( 'subscrpt_register_settings', [ $this, 'register_settings' ] );

		// Show warning if guest checkout is disabled in WooCommerce settings.
		add_action( 'admin_notices', [ $this, 'check_woocommerce_checkout_settings' ] );

		// Guest checkout validation.
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_guest_checkout' ] );
		add_action( 'woocommerce_store_api_cart_errors', [ $this, 'validate_guest_checkout_storeapi' ] );

		// Enforce Login/Registration in checkout
		add_action( 'woocommerce_checkout_process', [ $this, 'require_account_creation' ] );
		add_filter( 'woocommerce_store_api_checkout_update_order_from_request', [ $this,'require_account_creation_store_api' ], 10, 2 );
	}

	/**
	 * Add Guest Checkout Settings Fields
	 *
	 * @param array $settings_fields Settings fields.
	 * @return array
	 */
	public function add_guest_checkout_settings_fields( $settings_fields ) {
		$guest_checkout_fields = [
			[
				'type'       => 'heading',
				'group'      => 'guest_checkout',
				'priority'   => 1,
				'field_data' => [
					'title'       => __( 'Guest Checkout', 'subscription' ),
					'description' => __( 'Manage guest checkout settings for subscriptions.', 'subscription' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'guest_checkout',
				'priority'   => 1,
				'field_data' => [
					'id'          => 'wp_subscription_allow_guest_checkout',
					'title'       => __( 'Allow Guest Checkout', 'subscription' ),
					'description' => __( 'Allow customers to checkout without logging in.', 'subscription' ) . '<br/><sub>' . __( 'Note: You will need to enable <strong>Guest checkout</strong> and <strong>Allow customers to create an account during checkout</strong> options in WooCommerce settings for this to work properly.', 'subscription' ) . '</sub>',
					'value'       => '1',
					'checked'     => '1' === get_option( 'wp_subscription_allow_guest_checkout', '0' ),
				],
			],
			[
				'type'       => 'toggle',
				'group'      => 'guest_checkout',
				'priority'   => 2,
				'field_data' => [
					'id'          => 'wp_subscription_enforce_login',
					'title'       => __( 'Enforce Login', 'subscription' ),
					'description' => __( 'Force customers to login or check the "Create account" checkbox before checking out.', 'subscription' ),
					'value'       => '1',
					'checked'     => '1' === get_option( 'wp_subscription_enforce_login', '1' ),
				],
			],
		];

		return array_merge( $settings_fields, $guest_checkout_fields );
	}

	/**
	 * Register settings option.
	 */
	public function register_settings() {
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_allow_guest_checkout',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'wp_subscription_settings',
			'wp_subscription_enforce_login',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
	}

	/**
	 * Is guest checkout allowed
	 */
	public static function is_guest_checkout_allowed() {
		return in_array( get_option( 'wp_subscription_allow_guest_checkout', '0' ), [ 1, '1' ], true );
	}

	/**
	 * Is guest login enforced
	 */
	public static function is_guest_login_enforced() {
		return in_array( get_option( 'wp_subscription_enforce_login', '1' ), [ 1, '1' ], true );
	}

	/**
	 * Check if cart/order has subscription and guest checkout are allowed.
	 */
	public function is_subs_and_guest_checkout_allowed() {
		$is_user_logged_in         = is_user_logged_in();
		$is_guest_checkout_allowed = self::is_guest_checkout_allowed();
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
	 * Check WooCommerce checkout settings and show admin notice if guest checkout is disabled.
	 */
	public function check_woocommerce_checkout_settings() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$subs_guest_checkout_allowed = self::is_guest_checkout_allowed();
		if ( ! $subs_guest_checkout_allowed ) {
			return;
		}

		$guest_checkout_enabled  = in_array( get_option( 'woocommerce_enable_guest_checkout' ), [ 1, '1', 'yes', 'on' ], true );
		$account_during_checkout = in_array( get_option( 'woocommerce_enable_signup_and_login_from_checkout' ), [ 1, '1', 'yes', 'on' ], true );
		$account_after_checkout  = in_array( get_option( 'woocommerce_enable_delayed_account_creation' ), [ 1, '1', 'yes', 'on' ], true );

		$issues = [];
		if ( ! $guest_checkout_enabled ) {
			$issues[] = 'Guest checkout.';
		}
		if ( ! $account_during_checkout ) {
			$issues[] = 'Account creation during checkout.';
		}

		if ( ! empty( $issues ) ) {
			$settings_url = admin_url( 'admin.php?page=wc-settings&tab=account' );

			$list_html = '';
			foreach ( $issues as $issue ) {
				$list_html .= <<<HTML
					<li>
						<span class="dashicons dashicons-arrow-right"></span>
						<strong>{$issue}</strong>
					</li>
				HTML;
			}

			$requirement_html = <<<HTML
			<div class="notice notice-error is-dismissible">
				<p>
					To ensure WPSubscription guest checkout functions correctly, please enable the following settings in WooCommerce.
					Click <a href="$settings_url">here</a> to go to the settings.
				</p>
				<ul>
					{$list_html}
				</ul>
			</div>
			HTML;

			echo wp_kses_post( $requirement_html );
		}

		if ( $account_after_checkout ) {
			$settings_url = admin_url( 'admin.php?page=wc-settings&tab=account' );

			$requirement_html = <<<HTML
			<div class="notice notice-warning is-dismissible">
				<p>
					Enabling <strong>Account creation after checkout</strong> in WooCommerce settings may lead to issues with subscription orders for guest users. 
				</p>
				<p>It's recommended to disable this option for optimal functionality with WPSubscription. Click <a href="$settings_url">here</a> to go to the settings.</p>
			</div>
			HTML;

			echo wp_kses_post( $requirement_html );
		}
	}

	/**
	 * Validate guest checkout.
	 */
	public function validate_guest_checkout() {
		if ( ! $this->is_subs_and_guest_checkout_allowed() ) {
			wc_add_notice( __( 'You are trying to buy a subscription. You must be logged in to continue.', 'subscription' ), 'error' );
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
			$errors->add( 'wp_subscription_login_required', __( 'You are trying to buy a subscription. You must be logged in to continue.', 'subscription' ) );
			return $errors;
		}
	}

	/**
	 * Enforce account creation in checkout.
	 */
	public function require_account_creation() {
		if ( is_user_logged_in() || ! self::is_guest_checkout_allowed() ) {
			return;
		}

		// Check cart for subscriptions.
		$cart_have_subscription = false;
		if ( function_exists( 'WC' ) ) {
			$cart_items             = WC()->cart->get_cart();
			$recurrs                = Helper::get_recurrs_from_cart( $cart_items );
			$cart_have_subscription = count( $recurrs ) > 0;
		}

		if ( ! $cart_have_subscription ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$is_create_account = isset( $_POST['createaccount'] ) ? (bool) sanitize_text_field( wp_unslash( $_POST['createaccount'] ) ) : false;
		$is_login_enforced = self::is_guest_login_enforced();

		if ( $is_login_enforced && ! $is_create_account ) {
			wc_add_notice(
				wp_kses_post(
					__( 'You are ordering a subscription product. You must be either <strong>logged in</strong> or check the "<strong>Create an account</strong>" option to continue the checkout.', 'subscription' )
				),
				'error'
			);
		}
	}

	/**
	 * Enforce account creation in Store API checkout.
	 *
	 * @param WC_Order        $order Order object.
	 * @param WP_REST_Request $request Request object.
	 *
	 * @throws \WC_Data_Exception If account creation is required but not selected.
	 */
	public function require_account_creation_store_api( $order, $request ) {
		if ( is_user_logged_in() || ! self::is_guest_checkout_allowed() ) {
			return;
		}

		$is_subscription_order = Helper::order_has_subscription_item( $order );
		if ( ! $is_subscription_order ) {
			return;
		}

		$request_body      = $request->get_json_params();
		$is_create_account = $request_body['create_account'] ?? false;
		$is_login_enforced = self::is_guest_login_enforced();

		if ( $is_login_enforced && ! $is_create_account ) {
			throw new \WC_Data_Exception(
				'wp_subscription_account_required',
				wp_kses_post(
					__( 'You are ordering a subscription product. You must be either <strong>logged in</strong> or check the "<strong>Create an account</strong>" option to continue the checkout.', 'subscription' )
				),
				400
			);
		}
	}
}
