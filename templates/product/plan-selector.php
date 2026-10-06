<?php
/**
 * Storefront plan selector — picks the layout.
 *
 * The purchase options render in one of several layouts, each a partial in
 * `product/plan-selector/`: stacked cards (the default), a classic radio list,
 * a compact dropdown, an accordion, a grid of tiles, a grid led by the savings
 * or a segmented button row. `Plans::layout_for()` decides which; the
 * `subscrpt_plan_selector_layouts` filter lists them.
 *
 * Override a layout by copying its partial to
 * <your_theme>/subscription/product/plan-selector/<layout>.php. A theme's copy of
 * this file, from before layouts, still renders in place of all of them.
 *
 * @var array       $groups  Plan groups (id, type, label, price, old_price, badge, terms_heading, terms[]).
 * @var \WC_Product $product Product, or the variation when `$context` is 'variation'.
 * @var string      $context Where the selector renders: 'page' or 'variation'.
 *
 * @package SpringDevs\Subscription
 */

use SpringDevs\Subscription\Frontend\Plans;

defined( 'ABSPATH' ) || exit;

$subscrpt_layouts = Plans::layouts();
$subscrpt_layout  = Plans::layout_for( $product, $subscrpt_layouts );

// A layout whose partial is nowhere to be found renders stacked, not nothing.
if ( ! file_exists( wc_locate_template( $subscrpt_layouts[ $subscrpt_layout ], 'subscription', SUBSCRPT_TEMPLATES ) ) ) {
	$subscrpt_layout = 'stacked';
	$subscrpt_layouts[ $subscrpt_layout ] = 'product/plan-selector/stacked.php';
}

wc_get_template(
	$subscrpt_layouts[ $subscrpt_layout ],
	[
		'groups'  => $groups,
		'product' => $product,
		'context' => isset( $context ) ? (string) $context : 'page',
		'layout'  => $subscrpt_layout,
	],
	'subscription',
	SUBSCRPT_TEMPLATES
);
