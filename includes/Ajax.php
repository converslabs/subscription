<?php

namespace SpringDevs\Subscription;

/**
 * The Ajax class
 */
class Ajax {


	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'wp_ajax_install_woocommerce_plugin', array( $this, 'install_woocommerce_plugin' ) );
		add_action( 'wp_ajax_wps_subscription_activate_woocommerce_plugin', array( $this, 'wps_subscription_activate_woocommerce_plugin' ) );

		add_action( 'wp_ajax_activate_woocommerce_plugin', array( $this, 'activate_woocommerce_plugin' ) );
	}

	/**
	 * Install the WooCommerce Plugin.
	 */
	public function install_woocommerce_plugin() {
		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/misc.php';

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			include ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		}
		if ( ! class_exists( 'Plugin_Installer_Skin' ) ) {
			include ABSPATH . 'wp-admin/includes/class-plugin-installer-skin.php';
		}

		$plugin = 'woocommerce';

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => $plugin,
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			)
		);

		if ( is_wp_error( $api ) ) {
			wp_die( $api );
		}

		$title = sprintf(
			// translators: Plugin name and version.
			__( 'Installing Plugin: %s', 'wp_subscription' ),
			$api->name . ' ' . $api->version
		);
		$nonce = 'install-plugin_' . $plugin;
		$url   = 'update.php?action=install-plugin&plugin=' . urlencode( $plugin );

		$upgrader = new \Plugin_Upgrader( new \Plugin_Installer_Skin( compact( 'title', 'url', 'nonce', 'plugin', 'api' ) ) );
		$upgrader->install( $api->download_link );
		wp_send_json(
			array(
				'msg' => 'Installed successfully !!',
			)
		);
	}

	/**
	 * Active WooComerce Plugin.
	 */
	public function activate_woocommerce_plugin() {
		// add Deprecated notice
		_deprecated_function( 'Ajax::activate_woocommerce_plugin', '1.5.3', 'Ajax::wps_subscription_activate_woocommerce_plugin' );
		return $this->wps_subscription_activate_woocommerce_plugin();
	}

	public function wps_subscription_activate_woocommerce_plugin() {
		activate_plugin( 'woocommerce/woocommerce.php' );
		wp_send_json(
			array(
				'msg' => 'Activated successfully !!',
			)
		);
	}
}
