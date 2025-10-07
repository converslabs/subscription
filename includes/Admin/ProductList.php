<?php
/**
 * WPSubscription Products List Page
 *
 * Displays all WooCommerce products that have WPSubscription enabled.
 *
 * @package WPSubscription
 * @since 1.6.0
 */

namespace SpringDevs\Subscription\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product List Class
 */
class ProductList {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add menu page
	 *
	 * @return void
	 */
	public function add_menu_page() {
		// Add under WP Subscription menu
		add_submenu_page(
			'wp-subscription',
			__( 'Subscription Products', 'sdevs_wc_subs' ),
			__( 'Products', 'sdevs_wc_subs' ),
			'manage_woocommerce',
			'wp-subscription-products',
			array( $this, 'render_page' ),
			01
		);
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'subscrpt_order_page_wp-subscription-products' !== $hook ) {
			return;
		}

		// Enqueue WooCommerce admin styles
		wp_enqueue_style( 'woocommerce_admin_styles' );
	}

	/**
	 * Render the products list page
	 *
	 * @return void
	 */
	public function render_page() {
		// Get products
		$products       = $this->get_subscription_products();
		$total_products = count( $products );

		// Load view file
		include 'views/product-list.php';
	}


	/**
	 * Get subscription products
	 *
	 * @return array Array of WC_Product objects
	 */
	private function get_subscription_products() {
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			// 'post_status'    => array( 'publish', 'draft', 'private' ),
			'meta_query'     => array(
				array(
					'key'     => '_subscrpt_enabled',
					'value'   => '1',
					'compare' => '=',
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$products = array();
		$query    = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$product = wc_get_product( get_the_ID() );
				if ( $product ) {
					$products[] = $product;
				}
			}
			wp_reset_postdata();
		}

		return $products;
	}

	/**
	 * Get payment type label
	 *
	 * @param string $payment_type Payment type
	 * @return string
	 */
	public function get_payment_type_label( $payment_type ) {
		$labels = array(
			'recurring' => __( 'Recurring', 'sdevs_wc_subs' ),
			'split'     => __( 'Split Payment', 'sdevs_wc_subs' ),
		);

		return isset( $labels[ $payment_type ] ) ? $labels[ $payment_type ] : $payment_type;
	}

	/**
	 * Get payment details
	 *
	 * @param int    $product_id Product ID
	 * @param string $payment_type Payment type
	 * @return string
	 */
	public function get_payment_details( $product_id, $payment_type ) {
		$details = '';

		if ( 'recurring' === $payment_type ) {
			$time = get_post_meta( $product_id, '_subscription_time', true );
			$type = get_post_meta( $product_id, '_subscription_type', true );

			if ( $time && $type ) {
				$type_labels = array(
					'days'   => __( 'Day(s)', 'sdevs_wc_subs' ),
					'weeks'  => __( 'Week(s)', 'sdevs_wc_subs' ),
					'months' => __( 'Month(s)', 'sdevs_wc_subs' ),
					'years'  => __( 'Year(s)', 'sdevs_wc_subs' ),
				);

				$type_label = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : $type;
				$details    = '<br><small>' . sprintf( __( 'Every %1$d %2$s', 'sdevs_wc_subs' ), $time, $type_label ) . '</small>';
			}
		} elseif ( 'split' === $payment_type ) {
			$installments = get_post_meta( $product_id, '_subscrpt_installment', true );
			if ( $installments ) {
				$details = '<br><small>' . sprintf( __( '%d installments', 'sdevs_wc_subs' ), $installments ) . '</small>';
			}
		}

		return $details;
	}

	/**
	 * Get price display
	 *
	 * @param \WC_Product $product Product object
	 * @return string
	 */
	public function get_price_display( $product ) {
		$price_html = $product->get_price_html();

		if ( empty( $price_html ) ) {
			return '<span style="color: #999;">' . esc_html__( 'N/A', 'sdevs_wc_subs' ) . '</span>';
		}

		return wp_kses_post( $price_html );
	}

	/**
	 * Get status label
	 *
	 * @param string $status Status
	 * @return string
	 */
	public function get_status_label( $status ) {
		$labels = array(
			'publish' => __( 'Published', 'sdevs_wc_subs' ),
			'draft'   => __( 'Draft', 'sdevs_wc_subs' ),
			'pending' => __( 'Pending', 'sdevs_wc_subs' ),
			'private' => __( 'Private', 'sdevs_wc_subs' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
	}

	/**
	 * Get active subscriptions count for product
	 *
	 * @param int $product_id Product ID
	 * @return int
	 */
	public function get_active_subscriptions_count( $product_id ) {
		$args = array(
			'post_type'      => 'subscrpt_order',
			'post_status'    => 'active',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => 'product_id',
					'value'   => $product_id,
					'compare' => '=',
				),
			),
		);

		$query = new \WP_Query( $args );
		return $query->found_posts;
	}
}
