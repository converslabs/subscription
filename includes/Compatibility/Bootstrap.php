<?php
/**
 * WooCommerce Subscriptions Compatibility Bootstrap
 *
 * This class initializes the compatibility layer that makes WPSubscription
 * a drop-in replacement for WooCommerce Subscriptions.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Compatibility;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap class for WooCommerce Subscriptions compatibility
 */
class Bootstrap {

	/**
	 * Instance of this class
	 *
	 * @var Bootstrap
	 */
	private static $instance = null;

	/**
	 * Whether compatibility layer is active
	 *
	 * @var bool
	 */
	private $is_active = false;

	/**
	 * Compatibility version
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Get instance of this class
	 *
	 * @return Bootstrap
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize compatibility layer
	 *
	 * @return void
	 */
	private function init() {
		// Check if WooCommerce is active
		if ( ! $this->is_woocommerce_active() ) {
			return;
		}

		// Check if WooCommerce Subscriptions is not active
		if ( $this->is_woocommerce_subscriptions_active() ) {
			return;
		}

		// Initialize compatibility layer
		$this->setup_hooks();
		$this->load_compatibility_classes();
		$this->register_functions();
		$this->setup_class_aliases();
		$this->init_subscription_creator();
		
		$this->is_active = true;
		
		// Log compatibility activation
		$this->log_compatibility_activation();
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	/**
	 * Check if WooCommerce Subscriptions is active
	 *
	 * @return bool
	 */
	private function is_woocommerce_subscriptions_active() {
		return class_exists( 'WC_Subscriptions' ) || function_exists( 'wcs_is_subscription' );
	}

	/**
	 * Setup hooks for compatibility
	 *
	 * @return void
	 */
	private function setup_hooks() {
		// Early hooks to intercept WooCommerce Subscriptions
		add_action( 'plugins_loaded', array( $this, 'load_compatibility_layer' ), 5 );
		add_action( 'init', array( $this, 'register_post_types' ), 5 );
		add_action( 'init', array( $this, 'register_data_stores' ), 5 );
		
		// WooCommerce specific hooks
		add_action( 'woocommerce_init', array( $this, 'init_woocommerce_compatibility' ), 5 );
		add_action( 'woocommerce_loaded', array( $this, 'load_woocommerce_compatibility' ), 5 );
		
		// Admin hooks
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'init_admin_compatibility' ), 5 );
		}
	}

	/**
	 * Load compatibility classes
	 *
	 * @return void
	 */
	private function load_compatibility_classes() {
		$compatibility_path = WP_SUBSCRIPTION_INCLUDES . '/Compatibility/';
		
		// Core classes
		require_once $compatibility_path . 'Classes/WC_Subscription.php';
		require_once $compatibility_path . 'Classes/WC_Subscriptions_Manager.php';
		require_once $compatibility_path . 'Classes/WC_Subscriptions_Product.php';
		require_once $compatibility_path . 'Classes/WC_Subscriptions_Order.php';
		require_once $compatibility_path . 'Classes/WC_Subscriptions_Cart.php';
		require_once $compatibility_path . 'Classes/WC_Subscriptions_Change_Payment_Gateway.php';
		
		// Hook management
		require_once $compatibility_path . 'Hooks/HookManager.php';
		require_once $compatibility_path . 'Hooks/ActionHooks.php';
		require_once $compatibility_path . 'Hooks/FilterHooks.php';
		
		// Functions
		require_once $compatibility_path . 'Functions/CoreFunctions.php';
		require_once $compatibility_path . 'Functions/HelperFunctions.php';
		require_once $compatibility_path . 'Functions/DeprecatedFunctions.php';
		
		// Data stores
		require_once $compatibility_path . 'DataStores/SubscriptionDataStore.php';
		require_once $compatibility_path . 'DataStores/OrderDataStore.php';
		
		// Gateways
		require_once $compatibility_path . 'Gateways/GatewayManager.php';
		require_once $compatibility_path . 'Gateways/GatewayCompatibility.php';
		
		// Utils
		require_once $compatibility_path . 'Utils/CompatibilityChecker.php';
		require_once $compatibility_path . 'Utils/Logger.php';
		
		// Subscription Creator
		require_once $compatibility_path . 'SubscriptionCreator.php';
		
		// Test files (only in debug mode)
		if ( defined( 'WP_SUBSCRIPTION_COMPATIBILITY_DEBUG' ) && WP_SUBSCRIPTION_COMPATIBILITY_DEBUG ) {
			require_once $compatibility_path . 'test-compatibility.php';
			require_once $compatibility_path . 'test-payment-gateways.php';
		}
	}

	/**
	 * Register compatibility functions
	 *
	 * @return void
	 */
	private function register_functions() {
		// Register all WooCommerce Subscriptions functions
		Functions\CoreFunctions::register();
		Functions\HelperFunctions::register();
		Functions\DeprecatedFunctions::register();
	}

	/**
	 * Setup class aliases
	 *
	 * @return void
	 */
	private function setup_class_aliases() {
		// Create class aliases for WooCommerce Subscriptions classes
		class_alias( 'SpringDevs\Subscription\Compatibility\Classes\WC_Subscription', 'WC_Subscription' );
		class_alias( 'SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Manager', 'WC_Subscriptions_Manager' );
		class_alias( 'SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Product', 'WC_Subscriptions_Product' );
		class_alias( 'SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Order', 'WC_Subscriptions_Order' );
		class_alias( 'SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Cart', 'WC_Subscriptions_Cart' );
		class_alias( 'SpringDevs\Subscription\Compatibility\Classes\WC_Subscriptions_Change_Payment_Gateway', 'WC_Subscriptions_Change_Payment_Gateway' );
	}

	/**
	 * Initialize subscription creator
	 *
	 * @return void
	 */
	private function init_subscription_creator() {
		// Initialize subscription creator
		SubscriptionCreator::get_instance();
	}

	/**
	 * Load compatibility layer
	 *
	 * @return void
	 */
	public function load_compatibility_layer() {
		// Initialize hook manager
		Hooks\HookManager::get_instance();
		
		// Initialize gateway manager
		Gateways\GatewayManager::get_instance();
	}

	/**
	 * Register post types for compatibility
	 *
	 * @return void
	 */
	public function register_post_types() {
		// Register shop_subscription post type if not already registered
		if ( ! post_type_exists( 'shop_subscription' ) ) {
			register_post_type(
				'shop_subscription',
				array(
					'public'          => false,
					'show_ui'         => true,
					'show_in_menu'    => false,
					'supports'        => array( 'title', 'editor', 'custom-fields' ),
					'capability_type' => 'post',
					'capabilities'    => array(
						'create_posts' => false,
					),
					'map_meta_cap'    => true,
					'hpos'            => true,
				)
			);
		}
	}

	/**
	 * Register data stores for compatibility
	 *
	 * @return void
	 */
	public function register_data_stores() {
		// Register subscription data store
		add_filter( 'woocommerce_data_stores', array( $this, 'register_subscription_data_store' ) );
	}

	/**
	 * Register subscription data store
	 *
	 * @param array $stores Data stores array
	 * @return array
	 */
	public function register_subscription_data_store( $stores ) {
		$stores['subscription'] = 'SpringDevs\Subscription\Compatibility\DataStores\SubscriptionDataStore';
		return $stores;
	}

	/**
	 * Initialize WooCommerce compatibility
	 *
	 * @return void
	 */
	public function init_woocommerce_compatibility() {
		// Initialize WooCommerce specific compatibility features
		// HookManager will handle WooCommerce hooks automatically
	}

	/**
	 * Load WooCommerce compatibility
	 *
	 * @return void
	 */
	public function load_woocommerce_compatibility() {
		// Load WooCommerce specific compatibility features
		Gateways\GatewayManager::get_instance()->init_gateway_compatibility();
	}

	/**
	 * Initialize admin compatibility
	 *
	 * @return void
	 */
	public function init_admin_compatibility() {
		// Initialize admin specific compatibility features
		// HookManager will handle admin hooks automatically
	}

	/**
	 * Check if compatibility layer is active
	 *
	 * @return bool
	 */
	public function is_active() {
		return $this->is_active;
	}

	/**
	 * Get compatibility version
	 *
	 * @return string
	 */
	public function get_version() {
		return self::VERSION;
	}

	/**
	 * Log compatibility activation
	 *
	 * @return void
	 */
	private function log_compatibility_activation() {
		if ( defined( 'WP_SUBSCRIPTION_COMPATIBILITY_DEBUG' ) && WP_SUBSCRIPTION_COMPATIBILITY_DEBUG ) {
			Utils\Logger::log( 'WooCommerce Subscriptions compatibility layer activated', 'info' );
		}
	}

	/**
	 * Get compatibility status
	 *
	 * @return array
	 */
	public function get_status() {
		return array(
			'active'           => $this->is_active,
			'version'          => self::VERSION,
			'woocommerce'      => $this->is_woocommerce_active(),
			'wcs_active'       => $this->is_woocommerce_subscriptions_active(),
			'wp_subscription'  => class_exists( 'SpringDevs\Subscription\Illuminate' ),
		);
	}
}
