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
		add_action( 'wp_ajax_subscrpt_get_product_settings', array( $this, 'ajax_get_product_settings' ) );
		add_action( 'wp_ajax_subscrpt_save_product_settings', array( $this, 'ajax_save_product_settings' ) );
		add_action( 'admin_head', array( $this, 'hide_admin_elements_in_modal' ) );
	}

	/**
	 * Add menu page
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'wp-subscription',
			__( 'Subscription Products', 'sdevs_wc_subs' ),
			__( 'Products', 'sdevs_wc_subs' ),
			'manage_woocommerce',
			'wp-subscription-products',
			array( $this, 'render_page' ),
			1
		);
	}

	/**
	 * Enqueue scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'wp-subscription_page_wp-subscription-products' !== $hook ) {
			return;
		}

		// Enqueue WooCommerce admin styles
		wp_enqueue_style( 'woocommerce_admin_styles' );

		// Enqueue custom styles
		wp_enqueue_style(
			'subscrpt-admin-product-list',
			WP_SUBSCRIPTION_ASSETS . '/css/admin-product-list.css',
			array(),
			'1.6.0'
		);

		// Enqueue custom scripts
		wp_enqueue_script(
			'subscrpt-admin-product-list',
			WP_SUBSCRIPTION_ASSETS . '/js/admin-product-list.js',
			array( 'jquery' ),
			'1.6.0',
			true
		);

		// Localize script
		wp_localize_script(
			'subscrpt-admin-product-list',
			'subscrptProductList',
			array(
				'nonce' => wp_create_nonce( 'subscrpt_product_settings' ),
				'i18n'  => array(
					'editProduct'            => __( 'Edit Product', 'sdevs_wc_subs' ),
					'editSettings'           => __( 'Edit Subscription Settings', 'sdevs_wc_subs' ),
					'enableSubscription'     => __( 'Enable Subscription', 'sdevs_wc_subs' ),
					'enableSubscriptionDesc' => __( 'Enable subscription for this product', 'sdevs_wc_subs' ),
					'paymentType'            => __( 'Payment Type', 'sdevs_wc_subs' ),
					'recurring'              => __( 'Recurring', 'sdevs_wc_subs' ),
					'split'                  => __( 'Split Payment', 'sdevs_wc_subs' ),
					'billingInterval'        => __( 'Billing Interval', 'sdevs_wc_subs' ),
					'days'                   => __( 'Day(s)', 'sdevs_wc_subs' ),
					'weeks'                  => __( 'Week(s)', 'sdevs_wc_subs' ),
					'months'                 => __( 'Month(s)', 'sdevs_wc_subs' ),
					'years'                  => __( 'Year(s)', 'sdevs_wc_subs' ),
					'installments'           => __( 'Number of Installments', 'sdevs_wc_subs' ),
					'installmentsDesc'       => __( 'Number of payments to split the total', 'sdevs_wc_subs' ),
					'userCancel'             => __( 'User Can Cancel', 'sdevs_wc_subs' ),
					'userCancelDesc'         => __( 'Allow users to cancel their subscription', 'sdevs_wc_subs' ),
					'cancel'                 => __( 'Cancel', 'sdevs_wc_subs' ),
					'close'                  => __( 'Close', 'sdevs_wc_subs' ),
					'save'                   => __( 'Save Settings', 'sdevs_wc_subs' ),
					'saveAndClose'           => __( 'Save & Close', 'sdevs_wc_subs' ),
					'closeAndRefresh'        => __( 'Close & Refresh', 'sdevs_wc_subs' ),
					'saving'                 => __( 'Saving...', 'sdevs_wc_subs' ),
					'loading'                => __( 'Loading...', 'sdevs_wc_subs' ),
					'settingsSaved'          => __( 'Settings saved successfully!', 'sdevs_wc_subs' ),
					'saveFailed'             => __( 'Failed to save settings. Please try again.', 'sdevs_wc_subs' ),
				),
			)
		);
	}

	/**
	 * Render the products list page
	 *
	 * @return void
	 */
	public function render_page() {
		// Get products with pagination
		$result         = $this->get_subscription_products();
		$products       = $result['products'];
		$total_products = $result['total'];
		$max_pages      = $result['max_pages'];
		$paged          = $result['paged'];
		$per_page       = $result['per_page'];

		// Load view file
		include 'views/product-list.php';
	}


	/**
	 * Get subscription products with pagination
	 *
	 * @return array Array with products and pagination data
	 */
	private function get_subscription_products() {
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 10;

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_subscrpt_enabled',
				'value'   => '1',
				'compare' => '=',
			),
		);

		$base_args = array(
			'status'     => array( 'publish', 'draft', 'private' ),
			'meta_query' => $meta_query,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		// Filter by product type
		if ( isset( $_GET['product_type'] ) && ! empty( $_GET['product_type'] ) ) {
			$base_args['type'] = sanitize_text_field( $_GET['product_type'] );
		} else {
			$base_args['type'] = array( 'simple', 'variable' );
		}

		// Search
		if ( isset( $_GET['s'] ) && ! empty( $_GET['s'] ) ) {
			$base_args['s'] = sanitize_text_field( $_GET['s'] );
		}

		// Get total count
		$count_args = array_merge(
			$base_args,
			array(
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		$all_product_ids = wc_get_products( $count_args );

		// Filter by payment type manually if needed
		if ( isset( $_GET['payment_type'] ) && ! empty( $_GET['payment_type'] ) ) {
			$payment_type = sanitize_text_field( $_GET['payment_type'] );
			$filtered_ids = array();

			foreach ( $all_product_ids as $product_id ) {
				$product_payment_type = get_post_meta( $product_id, '_subscrpt_payment_type', true );

				// Default to recurring if not set
				if ( empty( $product_payment_type ) ) {
					$product_payment_type = 'recurring';
				}

				if ( $product_payment_type === $payment_type ) {
					$filtered_ids[] = $product_id;
				}
			}

			$all_product_ids = $filtered_ids;
		}

		$total = count( $all_product_ids );

		if ( empty( $all_product_ids ) ) {
			return $this->get_empty_result( $paged, $per_page );
		}

		// Get paginated products
		$offset       = ( $paged - 1 ) * $per_page;
		$paged_ids    = array_slice( $all_product_ids, $offset, $per_page );
		$product_args = array_merge(
			$base_args,
			array(
				'include' => $paged_ids,
				'limit'   => $per_page,
			)
		);

		$products = wc_get_products( $product_args );

		return array(
			'products'  => $products,
			'total'     => $total,
			'max_pages' => ceil( $total / $per_page ),
			'paged'     => $paged,
			'per_page'  => $per_page,
		);
	}

	/**
	 * Get empty result array
	 *
	 * @param int $paged Current page
	 * @param int $per_page Items per page
	 * @return array
	 */
	private function get_empty_result( $paged, $per_page ) {
		return array(
			'products'  => array(),
			'total'     => 0,
			'max_pages' => 0,
			'paged'     => $paged,
			'per_page'  => $per_page,
		);
	}

	/**
	 * Get payment type label
	 *
	 * @param string $payment_type Payment type
	 * @return string
	 */
	public function get_payment_type_label( $payment_type ) {
		$labels = array(
			'recurring'     => __( 'Recurring', 'sdevs_wc_subs' ),
			'split'         => __( 'Split Payment', 'sdevs_wc_subs' ),
			'split_payment' => __( 'Split Payment', 'sdevs_wc_subs' ),
		);

		return isset( $labels[ $payment_type ] ) ? $labels[ $payment_type ] : ucfirst( str_replace( '_', ' ', $payment_type ) );
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
	 * Get price display with subscription terms
	 *
	 * @param \WC_Product $product Product object
	 * @return string
	 */
	public function get_price_display( $product ) {
		$price_html = $product->get_price_html();

		if ( empty( $price_html ) ) {
			return '<span style="color: #999;">' . esc_html__( 'N/A', 'sdevs_wc_subs' ) . '</span>';
		}

		$product_id   = $product->get_id();
		$payment_type = get_post_meta( $product_id, '_subscrpt_payment_type', true );

		// Add subscription terms
		if ( 'recurring' === $payment_type ) {
			$time = get_post_meta( $product_id, '_subscription_time', true );
			$type = get_post_meta( $product_id, '_subscription_type', true );

			if ( $time && $type ) {
				$type_labels = array(
					'days'   => __( 'day', 'sdevs_wc_subs' ),
					'weeks'  => __( 'week', 'sdevs_wc_subs' ),
					'months' => __( 'month', 'sdevs_wc_subs' ),
					'years'  => __( 'year', 'sdevs_wc_subs' ),
				);

				$type_label = isset( $type_labels[ $type ] ) ? $type_labels[ $type ] : $type;
				$type_label = $time > 1 ? $type_label . 's' : $type_label;

				$price_html .= '<br><small style="color: #646970;">' . sprintf(
					/* translators: 1: interval number, 2: interval type */
					__( '/ %1$s %2$s', 'sdevs_wc_subs' ),
					$time,
					$type_label
				) . '</small>';
			}
		} elseif ( 'split_payment' === $payment_type ) {
			$installments = get_post_meta( $product_id, '_subscrpt_installment', true );
			if ( $installments ) {
				$price_html .= '<br><small style="color: #646970;">' . sprintf(
					/* translators: %d: number of installments */
					__( 'Split into %d payments', 'sdevs_wc_subs' ),
					$installments
				) . '</small>';
			}
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

	/**
	 * Render pagination
	 *
	 * @param int $paged Current page
	 * @param int $max_pages Maximum pages
	 * @return void
	 */
	public function render_pagination( $paged, $max_pages ) {
		if ( $max_pages <= 1 ) {
			return;
		}
		?>
		<div class="tablenav-pages">
			<span class="displaying-num">
				<?php
				printf(
					/* translators: 1: current page, 2: total pages */
					esc_html__( 'Page %1$d of %2$d', 'sdevs_wc_subs' ),
					$paged,
					$max_pages
				);
				?>
			</span>
			<span class="pagination-links">
				<?php
				$page_links = paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&lsaquo;',
						'next_text' => '&rsaquo;',
						'total'     => $max_pages,
						'current'   => $paged,
						'type'      => 'list',
					)
				);
				echo $page_links;
				?>
			</span>
		</div>
		<?php
	}

	/**
	 * AJAX handler to get product settings
	 *
	 * @return void
	 */
	public function ajax_get_product_settings() {
		check_ajax_referer( 'subscrpt_product_settings', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product ID', 'sdevs_wc_subs' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found', 'sdevs_wc_subs' ) ) );
		}

		// Get subscription settings
		$settings = array(
			'enabled'           => get_post_meta( $product_id, '_subscrpt_enabled', true ) === '1',
			'payment_type'      => get_post_meta( $product_id, '_subscrpt_payment_type', true ) ?: 'recurring',
			'subscription_time' => get_post_meta( $product_id, '_subscription_time', true ) ?: 1,
			'subscription_type' => get_post_meta( $product_id, '_subscription_type', true ) ?: 'months',
			'installments'      => get_post_meta( $product_id, '_subscrpt_installment', true ) ?: 3,
			'user_cancel'       => get_post_meta( $product_id, '_subscrpt_user_cancel', true ) === 'yes',
		);

		wp_send_json_success( $settings );
	}

	/**
	 * AJAX handler to save product settings
	 *
	 * @return void
	 */
	public function ajax_save_product_settings() {
		check_ajax_referer( 'subscrpt_product_settings', 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product ID', 'sdevs_wc_subs' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Product not found', 'sdevs_wc_subs' ) ) );
		}

		// Sanitize and save settings
		$enabled = isset( $_POST['subscrpt_enabled'] ) ? sanitize_text_field( $_POST['subscrpt_enabled'] ) : '0';
		update_post_meta( $product_id, '_subscrpt_enabled', $enabled );

		$payment_type = isset( $_POST['subscrpt_payment_type'] ) ? sanitize_text_field( $_POST['subscrpt_payment_type'] ) : 'recurring';
		update_post_meta( $product_id, '_subscrpt_payment_type', $payment_type );

		if ( 'recurring' === $payment_type ) {
			$time = isset( $_POST['subscription_time'] ) ? absint( $_POST['subscription_time'] ) : 1;
			update_post_meta( $product_id, '_subscription_time', $time );

			$type = isset( $_POST['subscription_type'] ) ? sanitize_text_field( $_POST['subscription_type'] ) : 'months';
			update_post_meta( $product_id, '_subscription_type', $type );

			$user_cancel = isset( $_POST['subscrpt_user_cancel'] ) ? sanitize_text_field( $_POST['subscrpt_user_cancel'] ) : 'no';
			update_post_meta( $product_id, '_subscrpt_user_cancel', $user_cancel );
		} elseif ( 'split' === $payment_type ) {
			$installments = isset( $_POST['subscrpt_installment'] ) ? absint( $_POST['subscrpt_installment'] ) : 3;
			update_post_meta( $product_id, '_subscrpt_installment', $installments );
		}

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully', 'sdevs_wc_subs' ) ) );
	}

	/**
	 * Hide admin elements when loaded in modal
	 *
	 * @return void
	 */
	public function hide_admin_elements_in_modal() {
		if ( ! isset( $_GET['subscrpt_modal'] ) || '1' !== $_GET['subscrpt_modal'] ) {
			return;
		}

		?>
		<style>
			/* Hide WordPress admin menu and header */
			#wpcontent {
				margin-left: 0 !important;
				padding-left: 20px !important;
			}
			#adminmenumain,
			#wpadminbar,
			.update-nag,
			.notice,
			.error {
				display: none !important;
			}
			#wpbody-content {
				padding-bottom: 0;
			}
			#wpfooter {
				display: none !important;
			}
			/* Adjust WooCommerce product page */
			.wrap {
				margin-top: 10px !important;
			}
			/* Hide some WooCommerce elements that aren't needed */
			.page-title-action,
			#screen-meta,
			#screen-meta-links {
				display: none !important;
			}
			/* Make the content full width */
			#poststuff {
				margin-top: 10px;
			}
			/* Adjust notices for modal */
			.notice,
			.error,
			.updated {
				display: block !important;
				margin: 10px 0 !important;
			}
			/* Ensure publish box is visible */
			#submitdiv {
				display: block !important;
			}
			/* Optimize spacing for modal */
			body {
				background: #fff;
			}
			#wpbody {
				padding-top: 0 !important;
			}
		</style>
		<?php
	}
}

