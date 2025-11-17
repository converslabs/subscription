<?php

namespace SpringDevs\Subscription\Admin;

/**
 * Class Settings
 *
 * @package SpringDevs\Subscription\Admin
 */
class Settings {
	/**
	 * Settings fields.
	 *
	 * @var array
	 */
	public $settings_fields = [];

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		// Load the settings helper.
		SettingsHelper::get_instance();

		// Initialize & process settings fields.
		$this->initiate_settings_fields();

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
	 * Initialize settings fields.
	 */
	public function initiate_settings_fields() {
		$settings_fields = [
			[
				'type'       => 'heading',
				'group'      => 'main',
				'priority'   => 0,
				'field_data' => [
					'title'       => __( 'Main', 'wp_subscription' ),
					'description' => __( 'This is a test text field rendered by SettingsHelper.', 'wp_subscription' ),
				],
			],
			[
				'type'       => 'input',
				'group'      => 'main',
				'priority'   => 2,
				'field_data' => [
					'id'          => 'wp_subscription_manual_renew_cart_notice',
					'title'       => __( 'Renewal Cart Notice', 'wp_subscription' ),
					'description' => __( 'Display Notice when Renewal Subscription product add to cart. Only available for Manual Renewal Process.', 'wp_subscription' ),
					'value'       => esc_attr( get_option( 'wp_subscription_manual_renew_cart_notice' ) ),
				],
			],
			[
				'type'       => 'select',
				'group'      => 'main',
				'priority'   => 1,
				'field_data' => [
					'id'          => 'wp_subscription_renewal_process',
					'title'       => __( 'Renewal Process', 'wp_subscription' ),
					'description' => __( 'How renewal process will be done after Subscription Expired.', 'wp_subscription' ),
					'options'     => [
						'auto'   => __( 'Automatic', 'wp_subscription' ),
						'manual' => __( 'Manual', 'wp_subscription' ),
					],
					'selected'    => esc_attr( get_option( 'wp_subscription_renewal_process', 'auto' ) ),
				],
			],
		];

		// $settings_fields = apply_filters( 'subscrpt_settings_fields', $settings_fields );

		$settings_fields = apply_filters( 'process_subscrpt_settings_fields', $settings_fields );

		// dd( '🔽 settings_fields', $settings_fields );
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
