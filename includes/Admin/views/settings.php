<?php
/**
 * Subscription settings admin view.
 *
 * @package wp_subscription
 */

use SpringDevs\Subscription\Admin\SettingsHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Add Tailwind CSS for styling.
subscrpt_include_tailwind_css();

// Add admin settings styles.
wp_enqueue_style( 'wp-subscription-admin-settings', WP_SUBSCRIPTION_ASSETS . '/css/admin-settings.css', [], WP_SUBSCRIPTION_VERSION );

// Add admin settings scripts.
wp_enqueue_script( 'wp-subscription-admin-settings', WP_SUBSCRIPTION_ASSETS . '/js/admin-settings.js', [ 'jquery' ], WP_SUBSCRIPTION_VERSION, true );

?>
<div class="woocommerce wpsubs-tw-root">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Subscription Settings', 'wp_subscription' ); ?></h1>
	<hr class="wp-header-end"><br/>

	<form method="post" action="options.php" class="border border-gray-200 rounded-lg p-5">
		<!-- Settings nonce and other requirements -->
		<?php settings_fields( 'wp_subscription_settings' ); ?>
		<?php do_settings_sections( 'wp_subscription_settings' ); ?>
		
		<!-- Fields -->
		<?php
		SettingsHelper::render_text_field(
			[
				'id'          => 'test_text_field',
				'title'       => __( 'Test Text Field', 'wp_subscription' ),
				'description' => __( 'This is a test text field rendered by SettingsHelper.', 'wp_subscription' ),
				'value'       => 'Default value',
				'placeholder' => 'Placeholder text',
				'disabled'    => false,
			]
		);

		echo wp_kses_post( '<div class="my-5 border-t border-gray-100"></div>' );

		SettingsHelper::render_switch_field(
			[
				'id'          => 'test_switch_field',
				'title'       => __( 'Test Switch Field', 'wp_subscription' ),
				'label'       => __( 'Click to toggle', 'wp_subscription' ),
				'description' => __( 'This is a test switch field rendered by SettingsHelper.', 'wp_subscription' ),
				'value'       => '1',
				'checked'     => true,
				'disabled'    => false,
			]
		);

		echo wp_kses_post( '<div class="my-5 border-t border-gray-100"></div>' );

		SettingsHelper::render_select_field(
			[
				'id'          => 'test_select_field',
				'title'       => __( 'Test Select Field', 'wp_subscription' ),
				'description' => __( 'This is a test select field rendered by SettingsHelper.', 'wp_subscription' ),
				'options'     => [
					'option_1' => __( 'Option 1', 'wp_subscription' ),
					'option_2' => __( 'Option 2', 'wp_subscription' ),
					'option_3' => __( 'Option 3', 'wp_subscription' ),
				],
				'selected'    => 'option_1',
				'disabled'    => false,
			]
		);

		echo wp_kses_post( '<div class="my-5 border-t border-gray-100"></div>' );

		SettingsHelper::render_joined_field(
			[
				'title'       => __( 'Test Join Field', 'wp_subscription' ),
				'description' => __( 'This is a test join field rendered by SettingsHelper.', 'wp_subscription' ),
				'elements'    => [
					SettingsHelper::inp_text_element(
						[
							'id'          => 'test_text_field',
							'title'       => __( 'Test Text Field', 'wp_subscription' ),
							'description' => __( 'This is a test text field rendered by SettingsHelper.', 'wp_subscription' ),
							'value'       => 'Default value',
							'placeholder' => 'Placeholder text',
							'disabled'    => false,
						],
						true
					),
					SettingsHelper::inp_select_element(
						[
							'id'          => 'test_select_field',
							'title'       => __( 'Test Select Field', 'wp_subscription' ),
							'description' => __( 'This is a test select field rendered by SettingsHelper.', 'wp_subscription' ),
							'options'     => [
								'option_1' => __( 'Option 1', 'wp_subscription' ),
								'option_2' => __( 'Option 2', 'wp_subscription' ),
								'option_3' => __( 'Option 3', 'wp_subscription' ),
							],
							'selected'    => 'option_1',
							'disabled'    => false,
						],
						true
					),
					'<button class="btn join-item">Button</button>',
				],
			]
		);
		?>

		<!-- Submit Button -->
		<div class="my-5 border-t border-gray-100"></div>
		<div>
			<input 
				type="submit" 
				value="<?php esc_attr_e( 'Save changes', 'wp_subscription' ); ?>" 
				class="button button-primary px-3! py-1! rounded-md!"
			/>
		</div>
	</form>
</div>
	

<div class="woocommerce">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Subscription Settings', 'wp_subscription' ); ?></h1>
	<hr class="wp-header-end"><br/>

	<form method="post" action="options.php" class="woocommerce-settings">
		<div class="woocommerce-card">
			<?php settings_fields( 'wp_subscription_settings' ); ?>
			<?php do_settings_sections( 'wp_subscription_settings' ); ?>
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<label for="wp_subscription_renewal_process">
								<?php esc_html_e( 'Renewal Process', 'wp_subscription' ); ?>
							</label>
						</th>
						<td>
							<select name="wp_subscription_renewal_process" id="wp_subscription_renewal_process" class="wc-enhanced-select">
								<option value="auto" <?php selected( get_option( 'wp_subscription_renewal_process', 'auto' ), 'auto' ); ?>><?php esc_html_e( 'Automatic', 'wp_subscription' ); ?></option>
								<option value="manual" <?php selected( get_option( 'wp_subscription_renewal_process', 'auto' ), 'manual' ); ?>><?php esc_html_e( 'Manual', 'wp_subscription' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'How renewal process will be done after Subscription Expired.', 'wp_subscription' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wp_subscription_manual_renew_cart_notice">
								<?php esc_html_e( 'Renewal Cart Notice', 'wp_subscription' ); ?>
							</label>
						</th>
						<td>
							<input id="wp_subscription_manual_renew_cart_notice" name="wp_subscription_manual_renew_cart_notice" value="<?php echo esc_attr( get_option( 'wp_subscription_manual_renew_cart_notice' ) ); ?>" type="text"/>
							<p class="description"><?php esc_html_e( 'Display Notice when Renewal Subscription product add to cart. Only available for Manual Renewal Process.', 'wp_subscription' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wp_subscription_active_role">
								<?php esc_html_e( 'Subscriber Default Role', 'wp_subscription' ); ?>
							</label>
						</th>
						<td>
							<select name="wp_subscription_active_role" id="wp_subscription_active_role" class="wc-enhanced-select">
								<?php wp_dropdown_roles( get_option( 'wp_subscription_active_role', 'subscriber' ) ); ?>
							</select>
							<p class="description"><?php esc_html_e( 'When a subscription is activated, either manually or after a successful purchase, new users will be assigned this role.', 'wp_subscription' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wp_subscription_unactive_role">
								<?php esc_html_e( 'Subscriber Inactive Role', 'wp_subscription' ); ?>
							</label>
						</th>
						<td>
							<select name="wp_subscription_unactive_role" id="wp_subscription_unactive_role" class="wc-enhanced-select">
								<?php wp_dropdown_roles( get_option( 'wp_subscription_unactive_role', 'customer' ) ); ?>
							</select>
							<p class="description"><?php esc_html_e( "If a subscriber's subscription is manually cancelled or expires, they will be assigned this role.", 'wp_subscription' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc"><?php esc_html_e( 'Stripe Auto Renewal', 'wp_subscription' ); ?></th>
						<td class="forminp forminp-checkbox">
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( 'Accept Stripe Auto Renewals', 'wp_subscription' ); ?></span></legend>
								<label for="wp_subscription_stripe_auto_renew">
								<input class="wp-subscription-toggle" name="wp_subscription_stripe_auto_renew" id="wp_subscription_stripe_auto_renew" type="checkbox" value="1" <?php checked( get_option( 'wp_subscription_stripe_auto_renew', '1' ), '1' ); ?> />
								<span class="wp-subscription-toggle-ui" aria-hidden="true"></span>
									<?php esc_html_e( 'Accept Stripe Auto Renewals', 'wp_subscription' ); ?>
								</label>
								<p class="description">
									<?php
									echo wp_kses_post(
										sprintf(
											/* translators: HTML tags */
											__( '%1$s WooCommerce Stripe Payment Gateway %2$s plugin is required!', 'wp_subscription' ),
											'<a href="https://wordpress.org/plugins/woocommerce-gateway-stripe/" target="_blank">',
											'</a>'
										)
									);
									?>
								</p>
							</fieldset>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row" class="titledesc"><?php esc_html_e( 'Auto Renewal Toggle', 'wp_subscription' ); ?></th>
						<td class="forminp forminp-checkbox">
							<fieldset>
								<legend class="screen-reader-text"><span><?php esc_html_e( 'Auto Renewal Toggle', 'wp_subscription' ); ?></span></legend>
								<label for="wp_subscription_auto_renewal_toggle">
								<input class="wp-subscription-toggle" name="wp_subscription_auto_renewal_toggle" id="wp_subscription_auto_renewal_toggle" type="checkbox" value="1" <?php checked( get_option( 'wp_subscription_auto_renewal_toggle', '1' ), '1' ); ?> />
								<span class="wp-subscription-toggle-ui" aria-hidden="true"></span>
									<?php esc_html_e( 'Display the auto renewal toggle', 'wp_subscription' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Allow customers to turn on and off automatic renewals from their Subscription details page.', 'wp_subscription' ); ?></p>
							</fieldset>
						</td>
					</tr>

					<!-- Allow other modules to add their settings fields. -->
					<?php do_action( 'wp_subscription_setting_fields' ); ?>

					<!-- Pro Gimmicks -->
					<?php if ( ! class_exists( 'Sdevs_Wc_Subscription_Pro' ) ) : ?>
						<!-- PRO Features (subtle, grayed out) -->
						<tr class="wp-subscription-pro-setting-row" style="opacity:0.55;pointer-events:none;">
							<th scope="row">
								<label><?php esc_html_e( 'Variable Product Options', 'wp_subscription' ); ?></label>
							</th>
							<td>
								<input type="text" disabled placeholder="Available in PRO" style="width:220px;" />
								<p class="description">Set flexible options for variable subscription products <span style="color:#2196f3;font-weight:600;">PRO</span></p>
							</td>
						</tr>
						<tr class="wp-subscription-pro-setting-row" style="opacity:0.55;pointer-events:none;">
							<th scope="row">
								<label><?php esc_html_e( 'Delivery Schedule', 'wp_subscription' ); ?></label>
							</th>
							<td>
								<select disabled><option><?php esc_html_e( 'Available in PRO', 'wp_subscription' ); ?></option></select>
								<p class="description">Custom delivery intervals for subscriptions <span style="color:#2196f3;font-weight:600;">PRO</span></p>
							</td>
						</tr>
						<tr class="wp-subscription-pro-setting-row" style="opacity:0.55;pointer-events:none;">
							<th scope="row">
								<label><?php esc_html_e( 'Subscription History', 'wp_subscription' ); ?></label>
							</th>
							<td>
								<input type="text" disabled placeholder="Available in PRO" style="width:220px;" />
								<p class="description">View detailed subscription change history <span style="color:#2196f3;font-weight:600;">PRO</span></p>
							</td>
						</tr>
						<tr class="wp-subscription-pro-setting-row" style="opacity:0.55;pointer-events:none;">
							<th scope="row">
								<label><?php esc_html_e( 'More Subscription Durations', 'wp_subscription' ); ?></label>
							</th>
							<td>
								<input type="text" disabled placeholder="Available in PRO" style="width:220px;" />
								<p class="description">Offer more flexible/custom subscription periods <span style="color:#2196f3;font-weight:600;">PRO</span></p>
							</td>
						</tr>
						<tr class="wp-subscription-pro-setting-row" style="opacity:0.55;pointer-events:none;">
							<th scope="row">
								<label><?php esc_html_e( 'Sign Up Fee', 'wp_subscription' ); ?></label>
							</th>
							<td>
								<input type="number" disabled placeholder="Available in PRO" style="width:120px;" />
								<p class="description">Charge a one-time sign up fee <span style="color:#2196f3;font-weight:600;">PRO</span></p>
							</td>
						</tr>
						<tr class="wp-subscription-pro-setting-row" style="opacity:0.55;pointer-events:none;">
							<th scope="row">
								<label><?php esc_html_e( 'Early Renewal', 'wp_subscription' ); ?></label>
							</th>
							<td>
								<input class="wp-subscription-toggle" type="checkbox" disabled />
								<span class="wp-subscription-toggle-ui" aria-hidden="true"></span>
								<p class="description">Allow early renewal for subscriptions <span style="color:#2196f3;font-weight:600;">PRO</span></p>
							</td>
						</tr>
						<tr class="wp-subscription-pro-setting-row" style="opacity:0.55;pointer-events:none;">
							<th scope="row">
								<label><?php esc_html_e( 'Renewal Price', 'wp_subscription' ); ?></label>
							</th>
							<td>
								<input type="number" disabled placeholder="Available in PRO" style="width:120px;" />
								<p class="description">Set a different price for renewals <span style="color:#2196f3;font-weight:600;">PRO</span></p>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			
			<div style="margin-top: 20px;">
				<?php submit_button( __( 'Save changes', 'wp_subscription' ), 'primary', 'submit', false ); ?>
			</div>
		</div>
	</form>
</div>
