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
<div class="wp-subscription-admin-box wpsubs-tw-root" style="max-width:1240px;margin:32px auto 0 auto;">
	<h1>TW Playground!</h1>

	<!-- Start Playground -->



<button data-popover-target="popover-top" data-popover-placement="top" type="button" class="text-white bg-brand box-border border border-transparent hover:bg-brand-strong focus:ring-4 focus:ring-brand-medium shadow-xs font-medium leading-5 rounded-base text-sm px-4 py-2.5 focus:outline-none">Top popover</button>

<div data-popover id="popover-top" role="tooltip" class="absolute z-10 invisible inline-block w-64 text-sm text-body transition-opacity duration-300 bg-neutral-primary-soft border border-default rounded-base shadow-xs opacity-0">
	<div class="px-3 py-2 bg-neutral-tertiary border-b border-default rounded-t-base">
		<h3 class="font-medium text-heading">Popover top</h3>
	</div>
	<div class="px-3 py-2">
		<p>And here's some amazing content. It's very engaging. Right?</p>
	</div>
	<div data-popper-arrow></div>
</div>


	<!-- End Playground -->
</div>



<div class="wp-subscription-admin-content" style="max-width:1240px;margin:32px auto 0 auto">
	<div class="wp-subscription-admin-box wpsubs-tw-root">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Subscription Settings', 'wp_subscription' ); ?></h1>
		<hr class="wp-header-end"><br/>

		<form method="post" action="options.php" class="border border-gray-200 rounded-lg p-5">
			<!-- Settings nonce and other requirements -->
			<?php settings_fields( 'wp_subscription_settings' ); ?>
			<?php do_settings_sections( 'wp_subscription_settings' ); ?>

			<!-- Settings Fields -->
			<?php
			foreach ( $settings_fields as $group_id => $group ) {
				foreach ( ( $group['fields'] ?? [] ) as $field ) {
					$field_type = $field['type'] ?? 'input';
					$field_data = $field['field_data'] ?? [];

					SettingsHelper::render_settings_field( $field_type, $field_data );

					echo wp_kses_post( '<div class="my-5 border-t border-gray-100"></div>' );
				}
			}
			?>

			<!-- Submit Button -->
			<div>
				<input 
					type="submit" 
					value="<?php esc_attr_e( 'Save changes', 'wp_subscription' ); ?>" 
					class="button button-primary px-3! py-1! rounded-md!"
				/>
			</div>
		</form>
	</div>
</div>

<?php return; ?>

<?php
/**
 * TODO: Refactor this section later
 *
 * phpcs:disable
 */
?>
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
