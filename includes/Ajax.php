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
		add_action( 'wp_ajax_subscrpt_save_wizard_page2', array( $this, 'save_wizard_page2' ) );
		add_action( 'wp_ajax_subscrpt_reset_wizard', array( $this, 'reset_wizard' ) );
		add_action( 'wp_ajax_subscrpt_get_product_variations', array( $this, 'get_product_variations' ) );
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

	/**
	 * Save wizard Page 2 data to session and create/update product.
	 * Used for SPA-style navigation (no page reload).
	 */
	public function save_wizard_page2() {
		check_ajax_referer( 'subscrpt_onboarding_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'subscription' ) ), 403 );
		}

		// Start session if not started
		if ( ! session_id() ) {
			session_start();
		}

		$product_mode     = isset( $_POST['product_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['product_mode'] ) ) : 'new';
		$product_name     = isset( $_POST['product_name'] ) ? sanitize_text_field( wp_unslash( $_POST['product_name'] ) ) : '';
		$product_price    = isset( $_POST['product_price'] ) ? sanitize_text_field( wp_unslash( $_POST['product_price'] ) ) : '';
		$existing_product = isset( $_POST['existing_product_id'] ) ? absint( $_POST['existing_product_id'] ) : 0;
		$variation_id     = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;
		$timing_option    = isset( $_POST['timing_option'] ) ? sanitize_text_field( wp_unslash( $_POST['timing_option'] ) ) : 'never';
		$billing_per      = isset( $_POST['billing_per'] ) ? absint( $_POST['billing_per'] ) : 1;
		$billing_period   = isset( $_POST['billing_period'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_period'] ) ) : 'month';
		$trial_timing_per = isset( $_POST['trial_timing_per'] ) ? absint( $_POST['trial_timing_per'] ) : 0;
		$signup_fee       = isset( $_POST['signup_fee'] ) ? sanitize_text_field( wp_unslash( $_POST['signup_fee'] ) ) : '';
		$trial_enabled    = isset( $_POST['trial_enabled'] ) ? (int) $_POST['trial_enabled'] : 0;
		$trial_timing_opt = isset( $_POST['trial_timing_option'] ) ? sanitize_text_field( wp_unslash( $_POST['trial_timing_option'] ) ) : 'days';
		$length_enabled   = isset( $_POST['length_enabled'] ) ? (int) $_POST['length_enabled'] : 0;
		$length_per       = isset( $_POST['length_per'] ) ? absint( $_POST['length_per'] ) : 0;
		$length_option    = isset( $_POST['length_option'] ) ? sanitize_text_field( wp_unslash( $_POST['length_option'] ) ) : 'months';

		// Store in session — product creation implemented in next phase
		$_SESSION['subscrpt_onboarding_wizard'] = array(
			'page'                => 3,
			'product_id'          => 0,
			'product_mode'        => $product_mode,
			'product_name'        => $product_name,
			'product_price'       => $product_price,
			'existing_product'    => $existing_product,
			'variation_id'        => $variation_id,
			'timing_option'       => $timing_option,
			'billing_per'         => $billing_per,
			'billing_period'      => $billing_period,
			'trial_timing_per'    => $trial_timing_per,
			'signup_fee'          => $signup_fee,
			'trial_enabled'       => $trial_enabled,
			'trial_timing_option' => $trial_timing_opt,
			'length_enabled'      => $length_enabled,
			'length_per'          => $length_per,
			'length_option'       => $length_option,
		);

		wp_send_json_success( array( 'product_id' => 0 ) );
	}

	/**
	 * Return available variations for a variable product (wizard use).
	 *
	 * @return void Sends JSON.
	 */
	public function get_product_variations() {
		check_ajax_referer( 'subscrpt_onboarding_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'subscription' ) ), 403 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'subscription' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || 'variable' !== $product->get_type() ) {
			wp_send_json_error( array( 'message' => __( 'Not a variable product.', 'subscription' ) ) );
		}

		$available = $product->get_available_variations();
		$result    = array();

		foreach ( $available as $variation ) {
			$v_id = $variation['variation_id'];

			// Build human-readable attribute label.
			$attr_labels = array();
			foreach ( $variation['attributes'] as $attr_key => $attr_value ) {
				if ( '' === $attr_value ) {
					$attr_labels[] = __( 'Any', 'subscription' );
					continue;
				}
				$taxonomy = str_replace( 'attribute_', '', $attr_key );
				if ( taxonomy_exists( $taxonomy ) ) {
					$term          = get_term_by( 'slug', $attr_value, $taxonomy );
					$attr_labels[] = $term ? $term->name : ucfirst( str_replace( '-', ' ', $attr_value ) );
				} else {
					$attr_labels[] = ucfirst( str_replace( '-', ' ', $attr_value ) );
				}
			}
			$label = implode( ' / ', $attr_labels );
			if ( ! $label ) {
				/* translators: %d: variation ID */
				$label = sprintf( __( 'Variation #%d', 'subscription' ), $v_id );
			}

			$result[] = array(
				'id'             => $v_id,
				'label'          => $label,
				'price'          => $variation['display_price'],
				'sku'            => $variation['sku'],
				'billing_period' => get_post_meta( $v_id, '_subscrpt_timing_option', true ),
				'billing_per'    => get_post_meta( $v_id, '_subscrpt_timing_per', true ) ?: 1,
				'trial_per'      => get_post_meta( $v_id, '_subscrpt_trial_timing_per', true ),
				'signup_fee'     => get_post_meta( $v_id, '_subscrpt_signup_fee', true ),
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Reset wizard session (for "Add another" action).
	 */
	public function reset_wizard() {
		check_ajax_referer( 'subscrpt_onboarding_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'subscription' ) ), 403 );
		}

		if ( ! session_id() ) {
			session_start();
		}

		unset( $_SESSION['subscrpt_onboarding_wizard'] );

		wp_send_json_success();
	}
}
