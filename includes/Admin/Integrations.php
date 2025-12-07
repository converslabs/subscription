<?php

namespace SpringDevs\Subscription\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrations class
 *
 * @package SpringDevs\Subscription\Admin
 */
class Integrations {
	/**
	 * Integrations list.
	 *
	 * @var array
	 */
	protected $integrations = [];

	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Initialize.
		add_action( 'init', [ $this, 'init' ], 10 );

		// Admin menu (sidebar).
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 20 );

		// WP Subscription navbar.
		add_filter( 'wp_subscription_admin_header_menu_items', [ $this, 'add_integrations_menu_item' ], 10, 2 );

		// Enqueue integrations scripts.
		// add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_integrations_scripts' ] );

		// Integrations AJAX handler.
		// add_action( 'admin_ajax_integrations_handler', [ $this,'integrations_handler_callback' ] );
		// add_action( 'admin_ajax_nopriv_integrations_handler', [ $this,'integrations_handler_callback' ] );
	}

	/**
	 * Initialize the integrations.
	 */
	public function init() {
		// Set integrations.
		$this->integrations = $this->get_integrations();
	}

	/**
	 * Register submenu under `subscriptions` menu.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		$parent_slug = 'wp-subscription';
		add_submenu_page(
			$parent_slug,
			__( 'Integrations', 'wp_subscription' ),
			__( 'Integrations', 'wp_subscription' ),
			'manage_options',
			'wp-subscription-integrations',
			[ $this, 'render_integrations_page' ],
			40
		);
	}

	/**
	 * Add Integrations link to the WP Subscription admin header menu.
	 *
	 * @param array  $menu_items Array of menu items.
	 * @param string $current Current active menu item slug.
	 */
	public function add_integrations_menu_item( $menu_items, $current ) {
		$menu_items[] = [
			'slug'  => 'wp-subscription-integrations',
			'label' => __( 'Integrations', 'wp_subscription' ),
			'url'   => admin_url( 'admin.php?page=wp-subscription-integrations' ),
		];
		return $menu_items;
	}

	/**
	 * Enqueue scripts for integrations page.
	 */
	public function enqueue_integrations_scripts() {
		wp_enqueue_script( 'wp-subs-integrations', WP_SUBSCRIPTION_ASSETS . '/js/integration_settings.js', [ 'jquery' ], WP_SUBSCRIPTION_VERSION, true );

		wp_localize_script(
			'wp-subs-integrations',
			'wpSubsIntegrations',
			array(
				'nonce'    => wp_create_nonce( 'wp_subs_integrations_nonce' ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);
	}

	/**
	 * Handle AJAX request for integrations.
	 */
	public function integrations_handler_callback() {
		check_ajax_referer( 'wp_subs_integrations_nonce', 'nonce' );

		$action_callback = ! empty( $_POST['action_callback'] ) ? sanitize_text_field( wp_unslash( $_POST['action_callback'] ) ) : '';

		dd( '🔽 action_callback', $action_callback );
	}

	/**
	 * Check if a payment gateway is installed and active.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @param bool   $partial_match Whether to allow partial match of gateway ID.
	 * @return bool
	 */
	public static function is_gateway_installed( $gateway_id, $partial_match = false ) {
		$installed_gateways = WC()->payment_gateways()->payment_gateways();

		// Partial match check. For gateways with dynamic IDs.
		if ( $partial_match ) {
			foreach ( $installed_gateways as $key => $gateway ) {
				if ( strpos( $key, $gateway_id ) !== false ) {
					return true;
				}
			}
			return false;
		}

		return isset( $installed_gateways[ $gateway_id ] );
	}

	/**
	 * Check if a payment gateway is enabled.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @param bool   $partial_match Whether to allow partial match of gateway ID.
	 * @return bool
	 */
	public static function is_gateway_enabled( $gateway_id, $partial_match = false ) {
		$installed_gateways = WC()->payment_gateways()->payment_gateways();

		// Partial match check. For gateways with dynamic IDs.
		if ( $partial_match ) {
			foreach ( $installed_gateways as $key => $gateway ) {
				if ( strpos( $key, $gateway_id ) !== false ) {
					return $gateway->is_available();
				}
			}
			return false;
		}

		if ( ! isset( $installed_gateways[ $gateway_id ] ) ) {
			return false;
		}
		return $installed_gateways[ $gateway_id ]->is_available();
	}



	/**
	 * Check if a gateway is installed by plugin file.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return bool
	 */
	protected function is_plugin_installed( $plugin_file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		return isset( $plugins[ $plugin_file ] );
	}

	/**
	 * Check if a gateway plugin is active.
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return bool
	 */
	protected function is_plugin_active( $plugin_file ) {
		return is_plugin_active( $plugin_file );
	}

	/**
	 * Check if a payment gateway is enabled.
	 *
	 * @param string $gateway_id Gateway ID.
	 */
	public function is_payment_gateway_enabled( $gateway_id ) {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		return isset( $gateways[ $gateway_id ] );
	}

	/**
	 * Get the list of integrations.
	 *
	 * @return array
	 */
	protected function get_integrations(): array {
		$integrations = [
			[
				'title'              => 'PayPal for WP Subscription',
				'description'        => 'Accept subscription payments via PayPal.',
				'icon_url'           => WP_SUBSCRIPTION_ASSETS . '/images/paypal.svg',
				'is_installed'       => 'on' === get_option( 'wp_subs_paypal_integration_enabled', 'off' ),
				'is_active'          => self::is_gateway_enabled( 'wp_subscription_paypal' ),
				'supports_recurring' => true,
				'actions'            => [
					// [
					// 'action'   => 'install',
					// 'label'    => 'Install Now',
					// 'type'     => 'function',
					// 'function' => 'wpSubsInstallPaypalIntegration()',
					// ],
					[
						'action' => 'settings',
						'label'  => 'Settings',
						'type'   => 'link',
						'url'    => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wp_subscription_paypal' ),
					],
					// [
					// 'action'   => 'uninstall',
					// 'label'    => 'Uninstall',
					// 'type'     => 'function',
					// 'function' => 'wpSubsUninstallPaypalIntegration()',
					// 'class'    => 'button button-primary wp-subs-button-danger',
					// ],
				],
			],
			[
				'title'              => 'Stripe',
				'description'        => 'Process subscription payments securely with Stripe.',
				'icon_url'           => 'https://ps.w.org/woocommerce-gateway-stripe/assets/icon-256x256.png',
				'is_installed'       => class_exists( 'WC_Stripe' ),
				'is_active'          => self::is_gateway_enabled( 'stripe' ),
				'supports_recurring' => true,
				'actions'            => [
					[
						'action' => 'install',
						'label'  => 'Install Now',
						'type'   => 'link',
						'url'    => admin_url( 'plugin-install.php?s=WooCommerce%2520Stripe%2520Payment%2520Gateway&tab=search&type=term' ),
					],
					[
						'action' => 'settings',
						'label'  => 'Settings',
						'type'   => 'link',
						'url'    => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=stripe&panel=settings' ),
					],
				],
			],
			[
				'title'              => 'Paddle',
				'description'        => 'Process subscription payments securely with Paddle.',
				'icon_url'           => WP_SUBSCRIPTION_ASSETS . '/images/paddle.svg',
				'is_installed'       => class_exists( 'SmartPayWoo\Gateways\Paddle\SmartPay_Paddle' ),
				'is_active'          => self::is_gateway_enabled( 'smartpay_paddle' ),
				'supports_recurring' => true,
				'actions'            => [
					[
						'action' => 'install',
						'label'  => 'Install Now',
						'type'   => 'link',
						'url'    => admin_url( 'plugin-install.php?s=WooCommerce%2520Stripe%2520Payment%2520Gateway&tab=search&type=term' ),
					],
					[
						'action'     => 'enable',
						'label'      => 'Enable Gateway',
						'type'       => 'toggle_option',
						'option_key' => 'woocommerce_enable_paddle_gateway',
						'value'      => true,
					],
					[
						'action' => 'settings',
						'label'  => 'Settings',
						'type'   => 'link',
						'url'    => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=smartpay_paddle&from=WCADMIN_PAYMENT_SETTINGS' ),
					],
					[
						'label' => 'More Details',
						'type'  => 'external_link',
						'url'   => 'https://wpsmartpay.com/paddle-for-woocommerce/',
					],
				],
			],
		];

		// Add more integrations as needed.
		$integrations = apply_filters( 'wpsubs_integrations', $integrations );

		return $integrations;
	}

	/**
	 * Filter actions.
	 *
	 * @param array $integrations Integrations array.
	 */
	protected function filter_integration_actions( array $integrations ): array {
		$cleaned_integrations = [];

		foreach ( $integrations as $integration ) {
			$is_installed = $integration['is_installed'] ?? false;
			$is_active    = $integration['is_active'] ?? false;

			$cleaned_actions = [];

			foreach ( $integration['actions'] as $integration_action ) {
				$action_tag = $integration_action['action'] ?? null;

				if ( 'install' === $action_tag ) {
					if ( ! $is_installed ) {
						$cleaned_actions[] = $integration_action;
					}
					continue;
				}
				if ( 'uninstall' === $action_tag ) {
					if ( $is_installed ) {
						$cleaned_actions[] = $integration_action;
					}
					continue;
				}
				if ( 'enable' === $action_tag ) {
					if ( $is_installed && ! $is_active ) {
						$cleaned_actions[] = $integration_action;
					}
					continue;
				}
				if ( 'settings' === $action_tag ) {
					if ( $is_installed ) {
						$cleaned_actions[] = $integration_action;
					}
					continue;
				}

				// Default.
				$cleaned_actions[] = $integration_action;
			}

			// Overwrite.
			$integration['actions'] = $cleaned_actions;
			$cleaned_integrations[] = $integration;
		}

		return $cleaned_integrations;
	}

	/**
	 * Render the Integrations admin page.
	 */
	public function render_integrations_page() {
		$integrations = $this->integrations;
		$integrations = $this->filter_integration_actions( $integrations );

		// Integrations styles.
		// wp_enqueue_style( 'wp-subs-integration-settings', WP_SUBSCRIPTION_ASSETS . '/css/integration_settings.css', [], WP_SUBSCRIPTION_VERSION, 'all' );

		$menu = new \SpringDevs\Subscription\Admin\Menu();
		$menu->render_admin_header();
		include 'views/integrations.php';
		$menu->render_admin_footer();
	}
}
