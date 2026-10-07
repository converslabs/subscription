<?php
/**
 * The variation buttons with pro active, run in a PHP process of its own.
 *
 * Free asks whether pro is active with `class_exists()`, which a suite cannot
 * undo; see plans-with-pro.php. Prints JSON: scenario name => what the filter
 * returned.
 *
 * @package SpringDevs\Subscription
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.GlobalVariablesOverride.Prohibited

require dirname( __DIR__ ) . '/bootstrap.php';
require __DIR__ . '/variation-buttons-fixtures.php';

use SpringDevs\Subscription\Tests\Unit\VariationButtonsFixtures as F;
use SpringDevs\Subscription\Tests\Unit\VariationButtonsVariation;

class_alias( \WC_Product_Stub::class, 'Sdevs_Wc_Subscription_Pro' );

// An older pro draws its own selector, so free puts no buttons beside it.
F::reset();
$out = [
	'pro'     => subscrpt_pro_activated(),
	'old_pro' => F::render( F::product() ),
];

// From here on, a pro that renders through free.
$GLOBALS['pro_renders_through_free'] = true;

F::reset();
$out['default'] = F::render( F::product() );

F::reset();
$out['out_of_stock'] = F::render( F::product( true, [ 42 => 'outofstock' ] ) );

F::reset();
$GLOBALS['wp_filter_returns']['woocommerce_variation_is_purchasable'] = false;
$out['not_purchasable'] = F::render( F::product() );

F::reset();
$GLOBALS['wp_filter_returns']['woocommerce_variation_is_visible'] = false;
$out['not_visible'] = F::render( F::product() );

F::reset();
$product           = F::product();
$product->children = [ 41, 43 ];
$out['missing']    = F::render( $product );

F::reset();
$product                    = F::product( true, [ 41 => 'outofstock' ] );
$GLOBALS['wc_products'][42] = new VariationButtonsVariation( 42, 'Any', [ 'type' => 'variation', 'attributes' => [ 'attribute_size' => '' ] ] );
$product->children          = [ 42 ];
$out['any_value']           = F::render( $product );

F::reset();
$buttons = new \SpringDevs\Subscription\Frontend\VariationButtons();
$args    = [ 'attribute' => 'size', 'product' => F::product() ];
$out['alone_on_filter'] = apply_filters( 'woocommerce_dropdown_variation_attribute_options_html', F::dropdown(), $args );
add_filter( 'woocommerce_dropdown_variation_attribute_options_html', static function ( $markup ) {
	return $markup . '<span class="swatches"></span>';
}, 10, 2 );
$out['other_callback'] = $buttons->render( F::dropdown(), $args );

foreach ( [ 'off_empty' => '', 'off_no' => 'no', 'on' => 'yes' ] as $name => $value ) {
	F::reset();
	$GLOBALS['wp_options']['subscrpt_variation_buttons'] = $value;
	$out[ $name ] = F::render( F::product() );
}

F::reset();
$out['on_by_default'] = F::render( F::product() );

F::reset();
$out['no_plans'] = F::render( F::product( false ) );

echo wp_json_encode( $out );
