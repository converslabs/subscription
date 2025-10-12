<?php
/**
 * WooCommerce Subscriptions Compatibility Layer Bootstrap
 *
 * @package   SpringDevs\Subscription
 * @copyright Copyright (c) 2024, ConversWP
 * @license   GPL-2.0+
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compatibility;

/**
 * Bootstrap class for WooCommerce Subscriptions compatibility layer.
 *
 * @package SpringDevs\Subscription\Compatibility
 * @since   1.0.0
 */
class Bootstrap {

	/**
	 * Single instance of the class.
	 *
	 * @since 1.0.0
	 * @var   Bootstrap|null
	 */
	private static $instance = null;

	/**
	 * Loaded components tracking.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	private static $loaded_components = array();

	/**
	 * Error tracking.
	 *
	 * @since 1.0.0
	 * @var   array
	 */
	private static $errors = array();

	/**
	 * Compatibility base path.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private $compat_path;

	/**
	 * Initialize the compatibility layer.
	 *
	 * @since  1.0.0
	 * @return Bootstrap
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->compat_path = WP_SUBSCRIPTION_INCLUDES . '/Compatibility/';

		// Check for WooCommerce Subscriptions conflict.
		add_action( 'admin_init', array( $this, 'check_wcs_conflict' ) );

		// Load compatibility layer.
		// If plugins_loaded has already fired, load immediately.
		if ( did_action( 'plugins_loaded' ) ) {
			$this->load_compatibility_layer();
			$this->verify_compatibility_layer();
		} else {
			// Otherwise, wait for plugins_loaded hook.
			add_action( 'plugins_loaded', array( $this, 'load_compatibility_layer' ), 5 );
			add_action( 'plugins_loaded', array( $this, 'verify_compatibility_layer' ), 999 );
		}
	}

	/**
	 * Check for WooCommerce Subscriptions conflict.
	 *
	 * @since 1.0.0
	 */
	public function check_wcs_conflict() {
		// Load conflict detector.
		if ( file_exists( $this->compat_path . 'ConflictDetector.php' ) ) {
			require_once $this->compat_path . 'ConflictDetector.php';
			ConflictDetector::init();
		}
	}

	/**
	 * Load the compatibility layer components.
	 *
	 * @since 1.0.0
	 */
	public function load_compatibility_layer() {
		// Define compatibility constants.
		if ( ! defined( 'WPSUBSCRIPTION_COMPAT_VERSION' ) ) {
			define( 'WPSUBSCRIPTION_COMPAT_VERSION', '1.0.0' );
		}

		// Mark that we're loading our compatibility layer.
		if ( ! defined( 'WPSUBSCRIPTION_COMPAT_WC_SUBSCRIPTION' ) ) {
			define( 'WPSUBSCRIPTION_COMPAT_WC_SUBSCRIPTION', true );
		}

		// Load components in order with verification.
		$this->load_functions();
		$this->verify_functions();

		$this->load_classes();
		$this->verify_classes();

		$this->setup_class_aliases();
		$this->verify_aliases();

		$this->load_hooks();
		$this->verify_hooks();

		$this->load_gateways();
		$this->verify_gateways();

		$this->load_utils();

		// Load admin components if in admin.
		if ( is_admin() ) {
			$this->load_admin();
		}

		// Load CLI commands if WP-CLI is available.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->load_cli();
		}
	}

	/**
	 * Load core functions.
	 *
	 * @since 1.0.0
	 */
	private function load_functions() {
		try {
			$functions_path = $this->compat_path . 'Functions/';

			if ( file_exists( $functions_path . 'CoreFunctions.php' ) ) {
				require_once $functions_path . 'CoreFunctions.php';
			}

			if ( file_exists( $functions_path . 'HelperFunctions.php' ) ) {
				require_once $functions_path . 'HelperFunctions.php';
			}

			if ( file_exists( $functions_path . 'DeprecatedFunctions.php' ) ) {
				require_once $functions_path . 'DeprecatedFunctions.php';
			}

			self::$loaded_components['functions'] = true;
		} catch ( \Exception $e ) {
			self::$errors[]                       = 'Functions load error: ' . $e->getMessage();
			self::$loaded_components['functions'] = false;
		}
	}

	/**
	 * Verify functions are loaded.
	 *
	 * @since 1.0.0
	 */
	private function verify_functions() {
		if ( ! isset( self::$loaded_components['functions'] ) || ! self::$loaded_components['functions'] ) {
			return;
		}

		// Check if function registry exists.
		if ( ! function_exists( 'wpsubscription_compat_get_functions' ) ) {
			self::$errors[] = 'Function registry not available';
			return;
		}

		$functions                                  = wpsubscription_compat_get_functions();
		self::$loaded_components['functions_count'] = count( $functions );
	}

	/**
	 * Load wrapper classes.
	 *
	 * @since 1.0.0
	 */
	private function load_classes() {
		try {
			$classes_path = $this->compat_path . 'Classes/';

			$classes = array(
				'WC_Subscription.php',
				'WC_Subscriptions_Manager.php',
				'WC_Subscriptions_Product.php',
				'WC_Subscriptions_Order.php',
				'WC_Subscriptions_Cart.php',
				'WC_Subscriptions_Change_Payment_Gateway.php',
			);

			foreach ( $classes as $class_file ) {
				if ( file_exists( $classes_path . $class_file ) ) {
					require_once $classes_path . $class_file;
				}
			}

			self::$loaded_components['classes'] = true;
		} catch ( \Exception $e ) {
			self::$errors[]                     = 'Classes load error: ' . $e->getMessage();
			self::$loaded_components['classes'] = false;
		}
	}

	/**
	 * Verify classes are loaded.
	 *
	 * @since 1.0.0
	 */
	private function verify_classes() {
		if ( ! isset( self::$loaded_components['classes'] ) || ! self::$loaded_components['classes'] ) {
			return;
		}

		$classes = array(
			'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscription',
			'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscriptions_Manager',
			'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscriptions_Product',
			'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscriptions_Order',
			'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscriptions_Cart',
			'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscriptions_Change_Payment_Gateway',
		);

		$loaded_count = 0;
		foreach ( $classes as $class ) {
			if ( class_exists( $class ) ) {
				++$loaded_count;
			}
		}

		self::$loaded_components['classes_count'] = $loaded_count;
	}

	/**
	 * Setup class aliases for global namespace.
	 *
	 * @since 1.0.0
	 */
	private function setup_class_aliases() {
		try {
			// Only create aliases if WooCommerce Subscriptions is not active.
			if ( class_exists( 'WC_Subscriptions' ) && ! defined( 'WPSUBSCRIPTION_COMPAT_WC_SUBSCRIPTION' ) ) {
				self::$loaded_components['aliases'] = false;
				self::$errors[]                     = 'WooCommerce Subscriptions is active, aliases not created to avoid conflicts';
				return;
			}

			$aliases = array(
				'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscription'                     => 'WC_Subscription',
				'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscriptions_Manager'            => 'WC_Subscriptions_Manager',
				'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscriptions_Product'            => 'WC_Subscriptions_Product',
				'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscriptions_Order'              => 'WC_Subscriptions_Order',
				'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscriptions_Cart'               => 'WC_Subscriptions_Cart',
				'SpringDevs\\Subscription\\Compatibility\\Classes\\WC_Subscriptions_Change_Payment_Gateway' => 'WC_Subscriptions_Change_Payment_Gateway',
			);

			foreach ( $aliases as $original => $alias ) {
				if ( class_exists( $original ) && ! class_exists( $alias ) ) {
					class_alias( $original, $alias );
				}
			}

			self::$loaded_components['aliases'] = true;
		} catch ( \Exception $e ) {
			self::$errors[]                     = 'Aliases setup error: ' . $e->getMessage();
			self::$loaded_components['aliases'] = false;
		}
	}

	/**
	 * Verify aliases are working.
	 *
	 * @since 1.0.0
	 */
	private function verify_aliases() {
		if ( ! isset( self::$loaded_components['aliases'] ) || ! self::$loaded_components['aliases'] ) {
			return;
		}

		$aliases = array(
			'WC_Subscription',
			'WC_Subscriptions_Manager',
			'WC_Subscriptions_Product',
			'WC_Subscriptions_Order',
			'WC_Subscriptions_Cart',
			'WC_Subscriptions_Change_Payment_Gateway',
		);

		$loaded_count = 0;
		foreach ( $aliases as $alias ) {
			if ( class_exists( $alias ) ) {
				++$loaded_count;
			}
		}

		self::$loaded_components['aliases_count'] = $loaded_count;
	}

	/**
	 * Load hook system.
	 *
	 * @since 1.0.0
	 */
	private function load_hooks() {
		try {
			$hooks_path = $this->compat_path . 'Hooks/';

			if ( file_exists( $hooks_path . 'HookRegistry.php' ) ) {
				require_once $hooks_path . 'HookRegistry.php';
			}

			if ( file_exists( $hooks_path . 'ActionHooks.php' ) ) {
				require_once $hooks_path . 'ActionHooks.php';
				Hooks\ActionHooks::init();
			}

			if ( file_exists( $hooks_path . 'FilterHooks.php' ) ) {
				require_once $hooks_path . 'FilterHooks.php';
				Hooks\FilterHooks::init();
			}

			if ( file_exists( $hooks_path . 'HookManager.php' ) ) {
				require_once $hooks_path . 'HookManager.php';
				Hooks\HookManager::init();
			}

			self::$loaded_components['hooks'] = true;
		} catch ( \Exception $e ) {
			self::$errors[]                   = 'Hooks load error: ' . $e->getMessage();
			self::$loaded_components['hooks'] = false;
		}
	}

	/**
	 * Verify hooks are registered.
	 *
	 * @since 1.0.0
	 */
	private function verify_hooks() {
		if ( ! isset( self::$loaded_components['hooks'] ) || ! self::$loaded_components['hooks'] ) {
			return;
		}

		if ( class_exists( 'SpringDevs\\Subscription\\Compatibility\\Hooks\\HookRegistry' ) ) {
			$hooks = Hooks\HookRegistry::get_registered_hooks();
			self::$loaded_components['action_hooks_count'] = count( $hooks['actions'] ?? array() );
			self::$loaded_components['filter_hooks_count'] = count( $hooks['filters'] ?? array() );
		}
	}

	/**
	 * Load gateway integration.
	 *
	 * @since 1.0.0
	 */
	private function load_gateways() {
		try {
			$gateways_path = $this->compat_path . 'Gateways/';

			if ( file_exists( $gateways_path . 'GatewayDetector.php' ) ) {
				require_once $gateways_path . 'GatewayDetector.php';
			}

			if ( file_exists( $gateways_path . 'GatewayCompatibility.php' ) ) {
				require_once $gateways_path . 'GatewayCompatibility.php';
				Gateways\GatewayCompatibility::init();
			}

			if ( file_exists( $gateways_path . 'PaymentMethodManager.php' ) ) {
				require_once $gateways_path . 'PaymentMethodManager.php';
			}

			if ( file_exists( $gateways_path . 'ScheduledPaymentProcessor.php' ) ) {
				require_once $gateways_path . 'ScheduledPaymentProcessor.php';
			}

			if ( file_exists( $gateways_path . 'WebhookHandler.php' ) ) {
				require_once $gateways_path . 'WebhookHandler.php';
			}

			self::$loaded_components['gateways'] = true;
		} catch ( \Exception $e ) {
			self::$errors[]                      = 'Gateways load error: ' . $e->getMessage();
			self::$loaded_components['gateways'] = false;
		}
	}

	/**
	 * Verify gateways integration.
	 *
	 * @since 1.0.0
	 */
	private function verify_gateways() {
		if ( ! isset( self::$loaded_components['gateways'] ) || ! self::$loaded_components['gateways'] ) {
			return;
		}

		// Count will be populated after WooCommerce is fully loaded.
		self::$loaded_components['gateways_count'] = 0;
	}

	/**
	 * Load utility classes.
	 *
	 * @since 1.0.0
	 */
	private function load_utils() {
		try {
			$utils_path = $this->compat_path . 'Utils/';

			if ( file_exists( $utils_path . 'Logger.php' ) ) {
				require_once $utils_path . 'Logger.php';
			}

			if ( file_exists( $utils_path . 'CompatibilityChecker.php' ) ) {
				require_once $utils_path . 'CompatibilityChecker.php';
			}

			self::$loaded_components['utils'] = true;
		} catch ( \Exception $e ) {
			self::$errors[]                   = 'Utils load error: ' . $e->getMessage();
			self::$loaded_components['utils'] = false;
		}
	}

	/**
	 * Load admin components.
	 *
	 * @since 1.0.0
	 */
	private function load_admin() {
		$admin_path = $this->compat_path . 'Admin/';

		if ( file_exists( $admin_path . 'StatusPage.php' ) ) {
			require_once $admin_path . 'StatusPage.php';
			Admin\StatusPage::init();
		}

		if ( file_exists( $admin_path . 'FunctionTester.php' ) ) {
			require_once $admin_path . 'FunctionTester.php';
		}

		if ( file_exists( $admin_path . 'GatewayTester.php' ) ) {
			require_once $admin_path . 'GatewayTester.php';
		}
	}

	/**
	 * Load CLI commands.
	 *
	 * @since 1.0.0
	 */
	private function load_cli() {
		$cli_path = $this->compat_path . 'CLI/';

		if ( file_exists( $cli_path . 'Commands.php' ) ) {
			require_once $cli_path . 'Commands.php';
		}
	}

	/**
	 * Verify compatibility layer after all plugins loaded.
	 *
	 * @since 1.0.0
	 */
	public function verify_compatibility_layer() {
		// Dispatch action for extensions.
		do_action( 'wpsubscription_compat_loaded', self::$loaded_components );

		// Log any errors.
		if ( ! empty( self::$errors ) && function_exists( 'error_log' ) ) {
			foreach ( self::$errors as $error ) {
				error_log( 'WPSubscription Compatibility: ' . $error );
			}
		}
	}

	/**
	 * Get compatibility layer status.
	 *
	 * @since  1.0.0
	 * @return array Status information
	 */
	public static function get_status() {
		return array(
			'loaded_components' => self::$loaded_components,
			'errors'            => self::$errors,
			'is_healthy'        => empty( self::$errors ),
			'version'           => defined( 'WPSUBSCRIPTION_COMPAT_VERSION' ) ? WPSUBSCRIPTION_COMPAT_VERSION : 'unknown',
		);
	}

	/**
	 * Check if a component is loaded.
	 *
	 * @since  1.0.0
	 * @param  string $component Component name.
	 * @return bool
	 */
	public static function is_component_loaded( $component ) {
		return isset( self::$loaded_components[ $component ] ) && self::$loaded_components[ $component ];
	}

	/**
	 * Get loaded component count.
	 *
	 * @since  1.0.0
	 * @param  string $component Component name with '_count' suffix.
	 * @return int
	 */
	public static function get_component_count( $component ) {
		return isset( self::$loaded_components[ $component ] ) ? (int) self::$loaded_components[ $component ] : 0;
	}
}
