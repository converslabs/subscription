<?php
/**
 * Guest Checkout File
 *
 * @package SpringDevs\Subscription\Illuminate
 */

namespace SpringDevs\Subscription\Illuminate;

use PHP_CodeSniffer\Generators\HTML;

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
		add_action( 'wp_subscription_setting_fields', [ $this, 'render_guest_checkout_setting_field' ], 7 );
		add_action( 'subscrpt_register_settings', array( $this, 'register_settings' ) );

		// Guest checkout validation.
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_guest_checkout' ] );
		add_action( 'woocommerce_store_api_cart_errors', [ $this, 'validate_guest_checkout_storeapi' ] );

		// Show warning if guest checkout is disabled in WooCommerce settings.
		add_action( 'admin_notices', [ $this, 'check_woocommerce_checkout_settings' ] );
	}

	/**
	 * Render Guest Checkout Setting Field
	 */
	public function render_guest_checkout_setting_field() {
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Allow Guest Checkout', 'wp_subscription' ); ?></th>
			<td class="forminp forminp-checkbox">
				<fieldset>
					<legend class="screen-reader-text"><span><?php esc_html_e( 'Allow Guest Checkout', 'wp_subscription' ); ?></span></legend>
					<label for="wp_subscription_allow_guest_checkout">
					<input class="wp-subscription-toggle" name="wp_subscription_allow_guest_checkout" id="wp_subscription_allow_guest_checkout" type="checkbox" value="1" <?php checked( get_option( 'wp_subscription_allow_guest_checkout', '0' ), '1' ); ?> />
					<span class="wp-subscription-toggle-ui" aria-hidden="true"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Allow customers to checkout without logging in.', 'wp_subscription' ); ?>
						<br/>
						<sub>
							<?php echo wp_kses_post( __( 'Note: You will need to enable <strong>Guest checkout</strong> and <strong>Allow customers to create an account during checkout</strong> options in WooCommerce settings for this to work properly.', 'wp_subscription' ) ); ?>
						</sub>
					</p>
				</fieldset>
			</td>
		</tr>

		<tr valign="top">
			<th scope="row" class="titledesc"><?php esc_html_e( 'Enforce Login', 'wp_subscription' ); ?></th>
			<td class="forminp forminp-checkbox">
				<fieldset>
					<legend class="screen-reader-text"><span><?php esc_html_e( 'Enforce Login', 'wp_subscription' ); ?></span></legend>
					<label for="wp_subscription_enforce_login">
					<input class="wp-subscription-toggle" name="wp_subscription_enforce_login" id="wp_subscription_enforce_login" type="checkbox" value="1" <?php checked( get_option( 'wp_subscription_enforce_login', '1' ), '1' ); ?> />
					<span class="wp-subscription-toggle-ui" aria-hidden="true"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Force customers to login or check the create account checkbox in the checkout.', 'wp_subscription' ); ?>
					</p>
				</fieldset>
			</td>
		</tr>
		<?php
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
}
