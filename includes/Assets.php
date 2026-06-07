<?php

namespace SpringDevs\Subscription;

/**
 * Scripts and Styles Class
 */
class Assets {

	/**
	 * Assets constructor.
	 */
	public function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'register' ), 5 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_tailwind_css' ), 5 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_components' ), 6 );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'register' ), 5 );
		}
	}

	/**
	 * Register our app scripts and styles
	 *
	 * @return void
	 */
	public function register() {
		$this->register_scripts( $this->get_scripts() );
		$this->register_styles( $this->get_styles() );
	}

	/**
	 * Register scripts
	 *
	 * @param array $scripts scripts.
	 *
	 * @return void
	 */
	private function register_scripts( $scripts ) {
		foreach ( $scripts as $handle => $script ) {
			$deps      = isset( $script['deps'] ) ? $script['deps'] : false;
			$in_footer = isset( $script['in_footer'] ) ? $script['in_footer'] : false;
			$version   = isset( $script['version'] ) ? $script['version'] : SUBSCRPT_VERSION;

			wp_register_script( $handle, $script['src'], $deps, $version, $in_footer );

			// Register script translations for scripts that use wp-i18n (e.g., block assets).
			if ( function_exists( 'wp_set_script_translations' ) && 'sdevs_subscrpt_cart_block' === $handle ) {
				wp_set_script_translations( $handle, 'subscription', SUBSCRPT_PATH . '/languages' );
			}
		}
	}

	/**
	 * Register styles
	 *
	 * @param array $styles styles.
	 *
	 * @return void
	 */
	public function register_styles( $styles ) {
		foreach ( $styles as $handle => $style ) {
			$deps = isset( $style['deps'] ) ? $style['deps'] : false;

			wp_register_style( $handle, $style['src'], $deps, SUBSCRPT_VERSION );
		}
	}

	/**
	 * Get all registered scripts
	 *
	 * @return array
	 */
	public function get_scripts() {
		$plugin_js_assets_path = SUBSCRPT_ASSETS . '/js/';

		$block_script_asset_path = SUBSCRPT_PATH . '/build/index.asset.php';
		$block_script_asset      = file_exists( $block_script_asset_path )
			? require $block_script_asset_path
			: array(
				'dependencies' => false,
				'version'      => SUBSCRPT_VERSION,
			);

		$scripts = array(
			'sdevs_subscription_admin'  => array(
				'src'       => $plugin_js_assets_path . 'admin.js',
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
			),
			'subscrpt_admin_components' => array(
				'src'       => $plugin_js_assets_path . 'admin-components.js',
				'deps'      => array(),
				'in_footer' => true,
			),
			'sdevs_installer'           => array(
				'src'       => $plugin_js_assets_path . 'installer.js',
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
			),
			'sdevs_subscrpt_cart_block' => array(
				'src'       => SUBSCRPT_URL . '/build/index.js',
				'deps'      => $block_script_asset['dependencies'],
				'version'   => $block_script_asset['version'],
				'in_footer' => true,
			),
		);

		return $scripts;
	}

	/**
	 * Get registered styles
	 *
	 * @return array
	 */
	public function get_styles() {
		$plugin_css_assets_path = SUBSCRPT_ASSETS . '/css/';

		$styles = array(
			'subscrpt_admin_css'        => array(
				'src' => $plugin_css_assets_path . 'admin.css',
			),
			'subscrpt_admin_components' => array(
				'src' => $plugin_css_assets_path . 'admin-components.css',
			),
			'subscrpt_status_css'       => array(
				'src' => $plugin_css_assets_path . 'status.css',
			),
			'sdevs_installer'           => array(
				'src' => $plugin_css_assets_path . 'installer.css',
			),
		);

		return $styles;
	}

	/**
	 * Enqueue Tailwind CSS for admin pages of WPSubscription.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_tailwind_css( $hook ) {
		$is_wpsubs_admin_page = str_starts_with( $hook, 'wp-subscription_page' );
		$is_wpsubs_admin_page && subscrpt_include_tailwind_css();
	}

	/**
	 * Enqueue admin component styles on all WPSubscription admin pages.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_components( $hook ) {
		$is_main_page = 'toplevel_page_wp-subscription' === $hook;
		$is_subs_page = str_starts_with( $hook, 'wpsubscription_page' );

		if ( $is_main_page || $is_subs_page ) {
			wp_enqueue_style( 'subscrpt_admin_components' );
			wp_enqueue_script( 'subscrpt_admin_components' );
		}
	}
}
