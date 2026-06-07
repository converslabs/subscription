<?php

namespace SpringDevs\Subscription;

/**
 * The Ajax class
 *
 * @package SpringDevs\Subscription
 */
class Ajax {


	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'wp_ajax_subscrpt_install_woocommerce_plugin', array( $this, 'install_woocommerce_plugin' ) );
		add_action( 'wp_ajax_subscrpt_activate_woocommerce_plugin', array( $this, 'wps_subscription_activate_woocommerce_plugin' ) );
		add_action( 'wp_ajax_subscrpt_install_integration_plugin', array( $this, 'install_integration_plugin' ) );
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
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		}
		if ( ! class_exists( 'Plugin_Installer_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-plugin-installer-skin.php';
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
			wp_die( esc_html( $api->get_error_message() ) );
		}

		$title = sprintf(
			// translators: Plugin name and version.
			__( 'Installing Plugin: %s', 'subscription' ),
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

	/**
	 * Install and activate a payment gateway plugin from the integrations page.
	 */
	public function install_integration_plugin() {
		check_ajax_referer( 'subscrpt_integration_install_nonce', 'nonce' );

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'subscription' ) ), 403 );
		}

		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_slug'] ) ) : '';

		if ( empty( $plugin_slug ) ) {
			wp_send_json_error( array( 'message' => __( 'Plugin slug is required.', 'subscription' ) ), 400 );
		}

		include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		include_once ABSPATH . 'wp-admin/includes/file.php';
		include_once ABSPATH . 'wp-admin/includes/misc.php';

		$cache_key = 'subscrpt_plugin_api_' . sanitize_key( $plugin_slug );
		$api       = get_transient( $cache_key );

		if ( false === $api ) {
			// Buffer output so any PHP warnings from the API call don't corrupt the JSON response.
			ob_start();

			$api = plugins_api(
				'plugin_information',
				array(
					'slug'   => $plugin_slug,
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

			ob_end_clean();

			if ( is_wp_error( $api ) ) {
				wp_send_json_error( array( 'message' => $api->get_error_message() ), 400 );
			}

			set_transient( $cache_key, $api, 12 * HOUR_IN_SECONDS );
		}

		$upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
		$result   = $upgrader->install( $api->download_link );

		ob_end_clean();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Plugin installation failed.', 'subscription' ) ), 500 );
		}

		$plugin_file = $upgrader->plugin_info();

		if ( ! $plugin_file ) {
			wp_send_json_success( array( 'warning' => __( 'Plugin installed but could not be activated automatically.', 'subscription' ) ) );
		}

		$activate = activate_plugin( $plugin_file );

		if ( is_wp_error( $activate ) ) {
			wp_send_json_success( array( 'warning' => __( 'Plugin installed but could not be activated automatically.', 'subscription' ) ) );
		}

		wp_send_json_success();
	}
}
