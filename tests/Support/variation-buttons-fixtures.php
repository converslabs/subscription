<?php
/**
 * Fixtures shared by VariationButtonsTest and variation-buttons-with-pro.php.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * A variation as WooCommerce's own class answers about it: through the two
 * filters a store or plugin uses to hide or block one.
 */
class VariationButtonsVariation extends \WC_Product {

	/**
	 * Stock status: instock, outofstock or onbackorder.
	 *
	 * @var string
	 */
	public $stock = 'instock';

	/**
	 * Variation attributes, keyed `attribute_<name>`; '' means any value.
	 *
	 * @var array
	 */
	public $attributes = [];

	/** Whether the variation is published, priced and shown. */
	public function variation_is_visible() {
		return apply_filters( 'woocommerce_variation_is_visible', true, $this->id, 0, $this );
	}

	/** Whether it can be bought. */
	public function is_purchasable() {
		return apply_filters( 'woocommerce_variation_is_purchasable', $this->variation_is_visible(), $this );
	}

	/** Whether any stock is left, or it can be backordered. */
	public function is_in_stock() {
		return 'outofstock' !== $this->stock;
	}

	/** The attributes this variation is for. */
	public function get_variation_attributes() {
		return $this->attributes;
	}
}

/**
 * A variable product that lists its variations.
 */
class VariationButtonsParent extends \WC_Product {

	/**
	 * Variation ids.
	 *
	 * @var int[]
	 */
	public $children = [];

	/** The variation ids. */
	public function get_children() {
		return $this->children;
	}
}

/**
 * Builders for the product and its dropdown.
 */
class VariationButtonsFixtures {

	/**
	 * Clear every fixture the tests write.
	 */
	public static function reset(): void {
		$GLOBALS['wp_post_meta']      = [];
		$GLOBALS['wp_options']        = [];
		// A pro that renders through free answers this, from every reset.
		$GLOBALS['wp_filter_returns'] = empty( $GLOBALS['pro_renders_through_free'] ) ? [] : [ 'subscrpt_plan_selector_from_free' => true ];
		$GLOBALS['wp_hooks_registry'] = [];
		$GLOBALS['wp_filter']         = [];
		$GLOBALS['applied_filters']   = [];
		$GLOBALS['wp_object_cache']   = [];
		$GLOBALS['wc_products']       = [];
	}

	/**
	 * A variable product (40) with three sizes, one variation each.
	 *
	 * @param bool  $with_plans Whether plans are tied to it.
	 * @param array $stock      Stock status by variation id.
	 */
	public static function product( bool $with_plans = true, array $stock = [] ): VariationButtonsParent {
		$parent           = new VariationButtonsParent( 40, 'Beans', [ 'type' => 'variable' ] );
		$parent->children = [ 41, 42, 43 ];

		foreach ( [
			41 => 'small',
			42 => 'medium',
			43 => 'large',
		] as $id => $size ) {
			$GLOBALS['wc_products'][ $id ] = new VariationButtonsVariation(
				$id,
				'Beans - ' . $size,
				[
					'type'       => 'variation',
					'attributes' => [ 'attribute_size' => $size ],
					'stock'      => $stock[ $id ] ?? 'instock',
				]
			);
		}

		$GLOBALS['wp_object_cache'][ PlanRepository::CACHE_GROUP ]['product_40'] = $with_plans
			? [
				[
					'plan_group_id' => 1,
					'plan_id'       => 11,
					'vid'           => 41,
					'relation_data' => [],
				],
			]
			: [];

		return $parent;
	}

	/**
	 * WooCommerce's dropdown for the Size attribute.
	 */
	public static function dropdown(): string {
		return '<select id="size" class="" name="attribute_size" data-attribute_name="attribute_size">'
			. '<option value="">Choose an option</option>'
			. '<option value="small" selected=\'selected\'>Small</option>'
			. '<option value="medium">Medium &amp; more</option>'
			. '<option value="large">Large</option>'
			. '</select>';
	}

	/**
	 * What the filter makes of the dropdown for a product.
	 *
	 * @param \WC_Product $product Product the dropdown is for.
	 */
	public static function render( $product ): string {
		return ( new \SpringDevs\Subscription\Frontend\VariationButtons() )->render(
			self::dropdown(),
			[
				'attribute' => 'size',
				'product'   => $product,
			]
		);
	}
}
