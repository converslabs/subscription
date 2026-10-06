<?php
/**
 * Plan selector layout — a grid of tiles led by the saving.
 *
 * The grid layout (`grid.php`) with each tile led by its discount badge,
 * _Save 20%_, in a band every tile keeps. The badge follows the selected term,
 * as in every layout.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/grid_savings.php
 *
 * @var array       $groups  Plan groups (id, type, label, price, old_price, badge, terms_heading, terms[]).
 * @var \WC_Product $product Product, or the variation when `$context` is 'variation'.
 * @var string      $context Where the selector renders: 'page' or 'variation'.
 * @var string      $layout  This layout's key.
 *
 * @package SpringDevs\Subscription
 */

defined( 'ABSPATH' ) || exit;

wc_get_template(
	'product/plan-selector/grid.php',
	[
		'groups'  => $groups,
		'product' => $product,
		'context' => isset( $context ) ? (string) $context : 'page',
		'layout'  => isset( $layout ) ? $layout : 'grid_savings',
		'lead'    => 'saving',
	],
	'subscription',
	SUBSCRPT_TEMPLATES
);
