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
			$version   = isset( $script['version'] ) ? $script['version'] : WP_SUBSCRIPTION_VERSION;

			wp_register_script( $handle, $script['src'], $deps, $version, $in_footer );

			// Register script translations for scripts that use wp-i18n (e.g., block assets).
			if ( function_exists( 'wp_set_script_translations' ) && 'sdevs_subscrpt_cart_block' === $handle ) {
				wp_set_script_translations( $handle, 'wp_subscription', WP_SUBSCRIPTION_PATH . '/languages' );
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

			wp_register_style( $handle, $style['src'], $deps, WP_SUBSCRIPTION_VERSION );
		}
	}

	/**
	 * Get all registered scripts
	 *
	 * @return array
	 */
	public function get_scripts() {
		$plugin_js_assets_path = WP_SUBSCRIPTION_ASSETS . '/js/';

		$block_script_asset_path = WP_SUBSCRIPTION_PATH . '/build/index.asset.php';
		$block_script_asset      = file_exists( $block_script_asset_path )
			? require $block_script_asset_path
			: array(
				'dependencies' => false,
				'version'      => WP_SUBSCRIPTION_VERSION,
			);

		$scripts = array(
			'sdevs_subscription_admin'  => array(
				'src'       => $plugin_js_assets_path . 'admin.js',
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
			),
			'sdevs_installer'           => array(
				'src'       => $plugin_js_assets_path . 'installer.js',
				'deps'      => array( 'jquery' ),
				'in_footer' => true,
			),
			'sdevs_subscrpt_cart_block' => array(
				'src'       => WP_SUBSCRIPTION_URL . '/build/index.js',
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
		$plugin_css_assets_path = WP_SUBSCRIPTION_ASSETS . '/css/';

		$styles = array(
			'subscrpt_admin_css'  => array(
				'src' => $plugin_css_assets_path . 'admin.css',
			),
			'subscrpt_status_css' => array(
				'src' => $plugin_css_assets_path . 'status.css',
			),
			'sdevs_installer'     => array(
				'src' => $plugin_css_assets_path . 'installer.css',
			),
		);

		return $styles;
	}
}
