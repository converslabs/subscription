<?php
/*
STYLE GUIDE FOR WP SUBSCRIPTION ADMIN PAGES:
- Use .wp-subscription-admin-content for main content area.
- Use .wp-subscription-admin-box for white card/box with shadow and 6-8px border-radius.
- Use compact, modern, visually unified design for all sections.
- Use Georgia, serif for titles, system sans-serif for body.
- All new UI/UX changes must follow these conventions.
*/
?>
<div class="notice notice-error sdevs-install-plugin">
	<div class="sdevs-notice-icon">
		<img src="<?php echo WP_SUBSCRIPTION_ASSETS . '/images/logo.png'; ?>" alt="woocommerce-logo" />
	</div>
	<div class="sdevs-notice-content">
		<h2><?php _e( 'Thanks for using Subscription for WooCommerce', 'wp_subscription' ); ?></h2>
		<p>You must have <a href="https://wordpress.org/plugins/woocommerce/" target="_blank">Woocommerce </a> installed and activated on this website in order to use this plugin.</p>
	</div>
	<div class="sdevs-install-notice-button">
		<a class="button-primary <?php echo $id; ?>" href="javascript:void(0);"><svg xmlns="http://www.w3.org/2000/svg" class="sdevs-loading-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
			</svg> <?php echo $label; ?></a>
	</div>
</div>