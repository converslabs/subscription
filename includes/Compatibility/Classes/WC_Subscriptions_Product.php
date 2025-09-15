<?php
/**
 * WooCommerce Subscriptions Product Compatibility Class
 *
 * This class provides compatibility with WooCommerce Subscriptions WC_Subscriptions_Product class
 * by mapping it to WPSubscription's product functionality.
 *
 * @package WPSubscription
 * @subpackage Compatibility
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Compatibility\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Subscriptions_Product compatibility class
 */
class WC_Subscriptions_Product {

	/**
	 * Instance of this class
	 *
	 * @var WC_Subscriptions_Product
	 */
	private static $instance = null;

	/**
	 * Get instance of this class
	 *
	 * @return WC_Subscriptions_Product
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
	 * Initialize product functionality
	 *
	 * @return void
	 */
	private function init() {
		// Initialize product hooks
		add_action( 'init', array( $this, 'register_hooks' ) );
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Product hooks
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_subscription_product_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_subscription_product_fields' ) );
		add_action( 'woocommerce_product_data_tabs', array( $this, 'add_subscription_product_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'add_subscription_product_panel' ) );
		
		// Variation hooks
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'add_subscription_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_subscription_variation_fields' ), 10, 2 );
	}

	/**
	 * Add subscription product fields
	 *
	 * @return void
	 */
	public function add_subscription_product_fields() {
		global $post;
		
		echo '<div class="options_group subscription_pricing show_if_simple show_if_variable">';
		
		// Subscription checkbox
		woocommerce_wp_checkbox( array(
			'id'          => '_subscription',
			'label'       => __( 'Subscription', 'woocommerce-subscriptions' ),
			'description' => __( 'Enable subscription for this product', 'woocommerce-subscriptions' ),
		) );
		
		// Billing interval
		woocommerce_wp_text_input( array(
			'id'                => '_subscription_interval',
			'label'             => __( 'Billing Interval', 'woocommerce-subscriptions' ),
			'description'       => __( 'Billing interval for subscription', 'woocommerce-subscriptions' ),
			'type'              => 'number',
			'custom_attributes' => array(
				'min'  => 1,
				'step' => 1,
			),
		) );
		
		// Billing period
		woocommerce_wp_select( array(
			'id'          => '_subscription_period',
			'label'       => __( 'Billing Period', 'woocommerce-subscriptions' ),
			'description' => __( 'Billing period for subscription', 'woocommerce-subscriptions' ),
			'options'     => array(
				'day'   => __( 'Day', 'woocommerce-subscriptions' ),
				'week'  => __( 'Week', 'woocommerce-subscriptions' ),
				'month' => __( 'Month', 'woocommerce-subscriptions' ),
				'year'  => __( 'Year', 'woocommerce-subscriptions' ),
			),
		) );
		
		// Subscription length
		woocommerce_wp_text_input( array(
			'id'                => '_subscription_length',
			'label'             => __( 'Subscription Length', 'woocommerce-subscriptions' ),
			'description'       => __( 'Length of subscription (0 for never expires)', 'woocommerce-subscriptions' ),
			'type'              => 'number',
			'custom_attributes' => array(
				'min'  => 0,
				'step' => 1,
			),
		) );
		
		// Trial length
		woocommerce_wp_text_input( array(
			'id'                => '_subscription_trial_length',
			'label'             => __( 'Trial Length', 'woocommerce-subscriptions' ),
			'description'       => __( 'Length of trial period', 'woocommerce-subscriptions' ),
			'type'              => 'number',
			'custom_attributes' => array(
				'min'  => 0,
				'step' => 1,
			),
		) );
		
		// Trial period
		woocommerce_wp_select( array(
			'id'          => '_subscription_trial_period',
			'label'       => __( 'Trial Period', 'woocommerce-subscriptions' ),
			'description' => __( 'Trial period for subscription', 'woocommerce-subscriptions' ),
			'options'     => array(
				'day'   => __( 'Day', 'woocommerce-subscriptions' ),
				'week'  => __( 'Week', 'woocommerce-subscriptions' ),
				'month' => __( 'Month', 'woocommerce-subscriptions' ),
				'year'  => __( 'Year', 'woocommerce-subscriptions' ),
			),
		) );
		
		echo '</div>';
	}

	/**
	 * Save subscription product fields
	 *
	 * @param int $post_id Post ID
	 * @return void
	 */
	public function save_subscription_product_fields( $post_id ) {
		// Save subscription checkbox
		$subscription = isset( $_POST['_subscription'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_subscription', $subscription );
		
		// Save subscription fields
		if ( 'yes' === $subscription ) {
			$fields = array(
				'_subscription_interval',
				'_subscription_period',
				'_subscription_length',
				'_subscription_trial_length',
				'_subscription_trial_period',
			);
			
			foreach ( $fields as $field ) {
				if ( isset( $_POST[ $field ] ) ) {
					update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
				}
			}
		}
	}

	/**
	 * Add subscription product tab
	 *
	 * @param array $tabs Product tabs
	 * @return array
	 */
	public function add_subscription_product_tab( $tabs ) {
		$tabs['subscription'] = array(
			'label'  => __( 'Subscription', 'woocommerce-subscriptions' ),
			'target' => 'subscription_product_data',
			'class'  => array( 'show_if_simple', 'show_if_variable' ),
		);
		return $tabs;
	}

	/**
	 * Add subscription product panel
	 *
	 * @return void
	 */
	public function add_subscription_product_panel() {
		global $post;
		
		echo '<div id="subscription_product_data" class="panel woocommerce_options_panel">';
		$this->add_subscription_product_fields();
		echo '</div>';
	}

	/**
	 * Add subscription variation fields
	 *
	 * @param int $loop Loop index
	 * @param array $variation_data Variation data
	 * @param \WP_Post $variation Variation post
	 * @return void
	 */
	public function add_subscription_variation_fields( $loop, $variation_data, $variation ) {
		echo '<div class="subscription_pricing">';
		
		// Subscription checkbox
		woocommerce_wp_checkbox( array(
			'id'            => '_subscription[' . $loop . ']',
			'label'         => __( 'Subscription', 'woocommerce-subscriptions' ),
			'description'   => __( 'Enable subscription for this variation', 'woocommerce-subscriptions' ),
			'value'         => get_post_meta( $variation->ID, '_subscription', true ),
		) );
		
		// Billing interval
		woocommerce_wp_text_input( array(
			'id'                => '_subscription_interval[' . $loop . ']',
			'label'             => __( 'Billing Interval', 'woocommerce-subscriptions' ),
			'description'       => __( 'Billing interval for subscription', 'woocommerce-subscriptions' ),
			'type'              => 'number',
			'custom_attributes' => array(
				'min'  => 1,
				'step' => 1,
			),
			'value'             => get_post_meta( $variation->ID, '_subscription_interval', true ),
		) );
		
		// Billing period
		woocommerce_wp_select( array(
			'id'          => '_subscription_period[' . $loop . ']',
			'label'       => __( 'Billing Period', 'woocommerce-subscriptions' ),
			'description' => __( 'Billing period for subscription', 'woocommerce-subscriptions' ),
			'options'     => array(
				'day'   => __( 'Day', 'woocommerce-subscriptions' ),
				'week'  => __( 'Week', 'woocommerce-subscriptions' ),
				'month' => __( 'Month', 'woocommerce-subscriptions' ),
				'year'  => __( 'Year', 'woocommerce-subscriptions' ),
			),
			'value'       => get_post_meta( $variation->ID, '_subscription_period', true ),
		) );
		
		echo '</div>';
	}

	/**
	 * Save subscription variation fields
	 *
	 * @param int $variation_id Variation ID
	 * @param int $loop Loop index
	 * @return void
	 */
	public function save_subscription_variation_fields( $variation_id, $loop ) {
		// Save subscription checkbox
		$subscription = isset( $_POST['_subscription'][ $loop ] ) ? 'yes' : 'no';
		update_post_meta( $variation_id, '_subscription', $subscription );
		
		// Save subscription fields
		if ( 'yes' === $subscription ) {
			$fields = array(
				'_subscription_interval',
				'_subscription_period',
				'_subscription_length',
				'_subscription_trial_length',
				'_subscription_trial_period',
			);
			
			foreach ( $fields as $field ) {
				if ( isset( $_POST[ $field ][ $loop ] ) ) {
					update_post_meta( $variation_id, $field, sanitize_text_field( $_POST[ $field ][ $loop ] ) );
				}
			}
		}
	}

	/**
	 * Check if product is subscription product
	 *
	 * @param mixed $product Product object or ID
	 * @return bool
	 */
	public function is_subscription_product( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return false;
		}

		return $product->get_meta( '_subscription', true ) === 'yes';
	}

	/**
	 * Get subscription interval
	 *
	 * @param mixed $product Product object or ID
	 * @return int
	 */
	public function get_interval( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return 1;
		}

		return (int) $product->get_meta( '_subscription_interval', true ) ?: 1;
	}

	/**
	 * Get subscription period
	 *
	 * @param mixed $product Product object or ID
	 * @return string
	 */
	public function get_period( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return 'month';
		}

		return $product->get_meta( '_subscription_period', true ) ?: 'month';
	}

	/**
	 * Get subscription length
	 *
	 * @param mixed $product Product object or ID
	 * @return int
	 */
	public function get_length( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return 0;
		}

		return (int) $product->get_meta( '_subscription_length', true ) ?: 0;
	}

	/**
	 * Get trial length
	 *
	 * @param mixed $product Product object or ID
	 * @return int
	 */
	public function get_trial_length( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return 0;
		}

		return (int) $product->get_meta( '_subscription_trial_length', true ) ?: 0;
	}

	/**
	 * Get trial period
	 *
	 * @param mixed $product Product object or ID
	 * @return string
	 */
	public function get_trial_period( $product ) {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( $product );
		}

		if ( ! $product ) {
			return 'day';
		}

		return $product->get_meta( '_subscription_trial_period', true ) ?: 'day';
	}

	/**
	 * Static method to check if product is subscription
	 *
	 * @param mixed $product Product object or ID
	 * @return bool
	 */
	public static function is_subscription( $product ) {
		$instance = self::get_instance();
		return $instance->is_subscription_product( $product );
	}

}
