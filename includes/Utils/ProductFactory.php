<?php
/**
 * Factory that builds subscription product wrappers.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Utils;

/**
 * Product Factory Class.
 */
class ProductFactory {

	/**
	 * Build the product wrapper for a WooCommerce product.
	 *
	 * @param \WC_Product $product WooCommerce product.
	 * @return Product
	 */
	public static function load( \WC_Product $product ): Product {
		$product_data_class = apply_filters( '_subscrpt_product_data_class', SubscriptionProduct::class, $product );

		return new $product_data_class( $product );
	}
}
