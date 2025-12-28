<?php

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Helper;

// HPOS: This file is compatible with WooCommerce High-Performance Order Storage (HPOS).
// All WooCommerce order data is accessed via WooCommerce CRUD methods (wc_get_order, etc.).
// All direct post meta access is for subscription data only, not WooCommerce order data.
// If you add new order data access, use WooCommerce CRUD for HPOS compatibility.

/**
 * Order class
 *
 * @package SpringDevs\Subscription\Admin
 */
class Order {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'woocommerce_admin_order_data_after_payment_info', array( $this, 'add_subscription_label' ) );
	}

	/**
	 * Add subscriotion label after order title.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return void
	 */
	public function add_subscription_label( $order ) {
		$histories = Helper::get_subscriptions_from_order( $order->get_id() ); // HPOS: Safe, uses custom table for subscription relations.
		if ( count( $histories ) > 0 ) :
			?>
			<div class="subscrpt-order-label"><?php echo esc_html_e( 'Subscription order', 'subscription' ); ?></div>
			<?php
		endif;
	}

	/**
	 * Related Subscriptions meta box on Orders.
	 */
	public function add_meta_boxes() {
		$screen    = wps_subscription_is_wc_order_hpos_enabled()
				? wc_get_page_screen_id( 'shop-order' )
				: 'shop_order';
		$order_id  = wps_subscription_is_wc_order_hpos_enabled() && isset( $_GET['id'] ) ? ( sanitize_text_field( wp_unslash( $_GET['id'] ) ) ) : get_the_ID();
		$histories = Helper::get_subscriptions_from_order( $order_id );
		if ( is_array( $histories ) ) {
			$order = wc_get_order( $order_id ); // HPOS: Safe, uses WooCommerce CRUD.
			add_meta_box(
				'subscrpt_order_related',
				__( 'Related Subscriptions', 'subscription' ),
				array( $this, 'subscrpt_order_related' ),
				$screen,
				'normal',
				'default',
				array(
					'histories' => $histories,
					'order'     => $order,
				)
			);
		}
	}

	/**
	 * Display content related subscriptions.
	 *
	 * @param mixed $order_post Current Order.
	 * @param array $info Meta box Info.
	 */
	public function subscrpt_order_related( $order_post, $info ) {
		$histories = $info['args']['histories'];
		$order     = $info['args']['order'];

		include 'views/related-subscriptions.php';
	}
}
