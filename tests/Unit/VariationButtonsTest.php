<?php
/**
 * Tests for variations shown as buttons.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Admin\SettingsHelper;
use SpringDevs\Subscription\Frontend\VariationButtons;
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
 * The button group beside a variable product's attribute dropdown.
 */
class VariationButtonsTest extends TestCase {

	/**
	 * A clean slate: no listeners, options, products or plans.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_post_meta']      = [];
		$GLOBALS['wp_options']        = [];
		$GLOBALS['wp_filter_returns'] = [];
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
	 *
	 * @return VariationButtonsParent
	 */
	private function product( bool $with_plans = true, array $stock = [] ): VariationButtonsParent {
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
	 *
	 * @return string
	 */
	private function dropdown(): string {
		return '<select id="size" class="" name="attribute_size" data-attribute_name="attribute_size">'
			. '<option value="">Choose an option</option>'
			. '<option value="small" selected=\'selected\'>Small</option>'
			. '<option value="medium">Medium &amp; more</option>'
			. '<option value="large">Large</option>'
			. '</select>';
	}

	/**
	 * What the filter makes of WooCommerce's dropdown.
	 *
	 * @param VariationButtonsParent $product Product the dropdown is for.
	 */
	private function render( $product ): string {
		$html = $this->dropdown();
		return ( new VariationButtons() )->render( $html, [ 'attribute' => 'size', 'product' => $product ] );
	}

	/** One button per option, the select kept, the chosen option marked. */
	public function test_buttons_render_for_each_option_of_a_product_with_plans() {
		$out = $this->render( $this->product() );

		$this->assertStringStartsWith( $this->dropdown(), $out, 'the select is kept, untouched' );
		$this->assertSame( 3, substr_count( $out, 'role="radio"' ) );
		$this->assertStringContainsString( 'role="radiogroup"', $out );
		$this->assertStringContainsString( 'data-select="size"', $out );
		$this->assertStringContainsString( 'data-value="medium"', $out );
		$this->assertStringContainsString( 'Medium &amp; more', $out, 'the label is the select\'s, escaped once' );
		$this->assertSame( 1, substr_count( $out, 'aria-checked="true"' ) );
		$this->assertMatchesRegularExpression( '/aria-checked="true"[^>]*data-value="small"|data-value="small"[^>]*aria-checked="true"/', $out );
		$this->assertStringNotContainsString( 'aria-disabled', $out );
	}

	/** A variation out of stock, blocked or hidden disables its button, with the reason as text and title. */
	public function test_disabled_when_not_purchasable_or_out_of_stock_with_reason() {
		$out = $this->render( $this->product( true, [ 42 => 'outofstock' ] ) );

		$this->assertSame( 1, substr_count( $out, 'aria-disabled="true"' ) );
		$this->assertMatchesRegularExpression( '/<button[^>]*data-value="medium"[^>]*aria-disabled="true"[^>]*title="Out of stock"|<button[^>]*aria-disabled="true"[^>]*data-value="medium"[^>]*title="Out of stock"/', $out );
		$this->assertMatchesRegularExpression( '/subscrpt-varbtn__reason[^>]*>[^<]*Out of stock/', $out, 'the reason is read out, not only a tooltip' );

		// A filter that says no to every variation: each button is unavailable.
		$GLOBALS['wp_filter_returns']['woocommerce_variation_is_purchasable'] = false;
		$out = $this->render( $this->product() );
		$this->assertSame( 3, substr_count( $out, 'aria-disabled="true"' ), 'woocommerce_variation_is_purchasable is honoured' );
		$this->assertSame( 3, substr_count( $out, 'title="Unavailable"' ) );
		unset( $GLOBALS['wp_filter_returns']['woocommerce_variation_is_purchasable'] );

		$GLOBALS['wp_filter_returns']['woocommerce_variation_is_visible'] = false;
		$out = $this->render( $this->product() );
		$this->assertSame( 3, substr_count( $out, 'aria-disabled="true"' ), 'woocommerce_variation_is_visible is honoured' );
		$this->assertSame( 3, substr_count( $out, 'title="Unavailable"' ) );
	}

	/** An option no variation is for cannot be bought either. */
	public function test_an_option_with_no_variation_is_unavailable() {
		$product           = $this->product();
		$product->children = [ 41, 43 ];

		$out = $this->render( $product );

		$this->assertSame( 1, substr_count( $out, 'aria-disabled="true"' ) );
		$this->assertMatchesRegularExpression( '/data-value="medium"[^>]*aria-disabled="true"|aria-disabled="true"[^>]*data-value="medium"/', $out );
	}

	/** A variation for any size keeps every size open. */
	public function test_a_variation_for_any_value_keeps_the_option_open() {
		$product                    = $this->product( true, [ 41 => 'outofstock' ] );
		$GLOBALS['wc_products'][42] = new VariationButtonsVariation( 42, 'Any', [ 'type' => 'variation', 'attributes' => [ 'attribute_size' => '' ] ] );
		$product->children          = [ 42 ];

		$this->assertStringNotContainsString( 'aria-disabled', $this->render( $product ) );
	}

	/** Another callback on the dropdown filter means the theme owns the control. */
	public function test_skipped_when_another_callback_filters_the_dropdown() {
		$buttons = new VariationButtons();
		$html    = $this->dropdown();
		$args    = [ 'attribute' => 'size', 'product' => $this->product() ];

		$this->assertStringContainsString( 'role="radiogroup"', $buttons->render( $html, $args ), 'alone on the filter, it renders' );

		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', static function ( $markup ) {
			return $markup . '<span class="swatches"></span>';
		}, 10, 2 );

		$this->assertSame( $html, $buttons->render( $html, $args ), 'with a swatch plugin on the filter, it leaves the dropdown alone' );
	}

	/** Its own registration is not another callback. */
	public function test_its_own_registration_does_not_count_as_another_callback() {
		new VariationButtons();
		$out = apply_filters(
			'woocommerce_dropdown_variation_attribute_options_html',
			$this->dropdown(),
			[ 'attribute' => 'size', 'product' => $this->product() ]
		);

		$this->assertStringContainsString( 'role="radiogroup"', $out );
	}

	/** The setting off, or a product with no plans, renders nothing. */
	public function test_setting_off_renders_nothing() {
		$product = $this->product();

		foreach ( [ '', 'no' ] as $off ) {
			$GLOBALS['wp_options']['subscrpt_variation_buttons'] = $off;
			$this->assertSame( $this->dropdown(), $this->render( $product ), 'off as ' . var_export( $off, true ) );
		}

		$GLOBALS['wp_options']['subscrpt_variation_buttons'] = 'yes';
		$this->assertStringContainsString( 'role="radiogroup"', $this->render( $product ) );

		unset( $GLOBALS['wp_options']['subscrpt_variation_buttons'] );
		$this->assertStringContainsString( 'role="radiogroup"', $this->render( $product ), 'on by default' );

		$GLOBALS['wp_object_cache'] = [];
		$this->assertSame( $this->dropdown(), $this->render( $this->product( false ) ), 'no plans, no buttons' );
	}

	/** Simple products and non-product args pass through. */
	public function test_other_input_passes_through() {
		$buttons = new VariationButtons();
		$html    = $this->dropdown();

		$this->assertSame( $html, $buttons->render( $html, [] ) );
		$this->assertSame( $html, $buttons->render( $html, [ 'attribute' => 'size', 'product' => new \WC_Product( 5 ) ] ) );
		$this->assertSame( 'not a select', $buttons->render( 'not a select', [ 'attribute' => 'size', 'product' => $this->product() ] ) );
	}

	/** The setting is a toggle in the Product page group, defaulting on. */
	public function test_the_setting_is_a_field_of_the_product_page_group() {
		$fields = ( new VariationButtons() )->add_settings_fields( [] );

		$heading = array_values( array_filter( $fields, static fn( $f ) => 'heading' === $f['type'] ) );
		$this->assertCount( 1, $heading );
		$this->assertSame( 'product_page', $heading[0]['group'] );
		$this->assertSame( 'Product page', $heading[0]['field_data']['title'] );

		$toggle = array_values( array_filter( $fields, static fn( $f ) => 'toggle' === $f['type'] ) );
		$this->assertCount( 1, $toggle );
		$this->assertSame( 'product_page', $toggle[0]['group'] );
		$this->assertSame( 'subscrpt_variation_buttons', $toggle[0]['field_data']['id'] );
		$this->assertSame( 'yes', $toggle[0]['field_data']['value'] );
		$this->assertTrue( $toggle[0]['field_data']['checked'], 'on until saved off' );

		$GLOBALS['wp_options']['subscrpt_variation_buttons'] = '';
		$fields = ( new VariationButtons() )->add_settings_fields( [] );
		$toggle = array_values( array_filter( $fields, static fn( $f ) => 'toggle' === $f['type'] ) );
		$this->assertFalse( $toggle[0]['field_data']['checked'], 'saved off stays off' );
	}

	/** An unmapped group would fall into Advanced; this one has a section of its own. */
	public function test_product_page_group_maps_to_a_category() {
		$category = SettingsHelper::group_category( 'product_page' );

		$this->assertNotSame( SettingsHelper::group_category( 'a_group_nobody_mapped' ), $category );
		$this->assertArrayHasKey( $category, SettingsHelper::categories() );
		$this->assertSame( 'Product page', SettingsHelper::categories()[ $category ] );
	}
}
