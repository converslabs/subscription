<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Class Settings
 *
 * @package SpringDevs\Subscription\Admin
 */
class Settings {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 30 );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wc_admin_styles' ) );
	}

	/**
	 * Register submenu on `Subscriptions` menu.
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=subscrpt_order',
			__( 'WP Subscription Settings', 'wp_subscription' ),
			__( 'Settings', 'wp_subscription' ),
			'manage_options',
			'wp_subscription_settings',
			array( $this, 'settings_content' ),
			40
		);
	}

	/**
	 * Register settings options.
	 **/
	public function register_settings() {
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_renewal_process',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_manual_renew_cart_notice',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_active_role',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_unactive_role',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_stripe_auto_renew',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
		register_setting(
			'wp_subscription_settings',
			'wp_subscription_auto_renewal_toggle',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		do_action( 'subscrpt_register_settings', 'subscrpt_settings' );
	}

	/**
	 * Settings HTML.
	 */
	public function settings_content() {
		include 'views/settings.php';
	}

	/**
	 * Enqueue WooCommerce admin styles for settings page.
	 */
	public function enqueue_wc_admin_styles( $hook ) {
		// Only load on our settings page
		if ( isset( $_GET['post_type'] ) && strpos( $_GET['post_type'], 'subscrpt_order' ) !== false ) {
			// WooCommerce admin styles
			wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), WP_SUBSCRIPTION_VERSION );
			// Optional: WooCommerce enhanced select2
			wp_enqueue_style( 'woocommerce_admin_select2', WC()->plugin_url() . '/assets/css/select2.css', array(), WP_SUBSCRIPTION_VERSION );
			wp_enqueue_script( 'select2' );
		}
	}
}
