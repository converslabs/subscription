<?php
/**
 * Products list table (Products → All Products) integration.
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

// HPOS: This file does not access WooCommerce order data directly.
// All meta access is for product data only, not WooCommerce order data.

/**
 * ProductList class
 *
 * Marks products managed by WPSubscription with a badge under the product
 * name on `edit.php?post_type=product`.
 *
 * @package SpringDevs\Subscription\Admin
 */
class ProductList {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		// After WooCommerce's own renderer (priority 10), so the badge lands
		// below the product name and above the row actions.
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_badge' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Print the badge in the name column of a subscription product.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Product id.
	 *
	 * @return void
	 */
	public function render_badge( $column, $post_id ) {
		if ( 'name' !== $column ) {
			return;
		}

		$product = wc_get_product( $post_id );
		if ( ! $product || ! self::is_subscription_product( $product ) ) {
			return;
		}

		printf(
			'<span class="subscrpt-product-list-badge" title="%1$s">%2$s</span>',
			esc_attr__( 'This product is sold as a subscription by WPSubscription.', 'subscription' ),
			esc_html__( 'Subscription', 'subscription' )
		);
	}

	/**
	 * Whether a product is sold as a subscription.
	 *
	 * Simple products check the product itself; variable products count when
	 * any variation is subscription-enabled (a plan tied to the parent applies
	 * to every variation, so it is picked up there).
	 *
	 * @param \WC_Product $product Product.
	 *
	 * @return bool
	 */
	public static function is_subscription_product( $product ) {
		$product_id = $product->get_id();

		if ( ! $product->is_type( 'variable' ) ) {
			return subscrpt_is_subscription_enabled( $product_id );
		}

		foreach ( $product->get_children() as $variation_id ) {
			if ( subscrpt_is_subscription_enabled( $product_id, $variation_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Badge styles, on the products list screen only.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}

		wp_register_style( 'subscrpt_product_list', false, array(), SUBSCRPT_VERSION );
		wp_enqueue_style( 'subscrpt_product_list' );
		wp_add_inline_style(
			'subscrpt_product_list',
			'.subscrpt-product-list-badge{display:inline-block;margin-left:6px;vertical-align:middle;padding:1px 8px;border-radius:10px;background:#e7f0fa;color:#135e96;font-size:11px;font-weight:600;line-height:18px;}'
		);
	}
}
