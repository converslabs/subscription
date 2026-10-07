<?php
/**
 * The plan selector with pro active, run in a PHP process of its own.
 *
 * Free asks whether pro is active with `class_exists()`, and a class cannot be
 * taken away again once declared, so PlansProGateTest runs this script instead
 * of declaring it in the suite. PHPUnit's own process isolation cannot do it:
 * its child loads composer's autoload files before the bootstrap, and free's
 * functions.php exits there.
 *
 * Prints JSON: what render_selector() prints for a variable product with plans
 * tied to one variation, the `woocommerce_available_variation` data off the
 * product page of that variation and of one without plans, and which Plans
 * methods the constructor hooked.
 *
 * Run with `old` for a pro that draws its own selector and so does not answer
 * `subscrpt_plan_selector_from_free`; by default pro answers true, as pro does
 * from the release that renders through free.
 *
 * @package SpringDevs\Subscription
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.GlobalVariablesOverride.Prohibited

require dirname( __DIR__ ) . '/bootstrap.php';

use SpringDevs\Subscription\Frontend\Plans;
use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

class_alias( \WC_Product_Stub::class, 'Sdevs_Wc_Subscription_Pro' );

if ( 'old' !== ( $argv[1] ?? '' ) ) {
	$GLOBALS['wp_filter_returns']['subscrpt_plan_selector_from_free'] = true;
}

$row = function ( $plan_id ) {
	return [
		'plan_group_id'     => 1,
		'group_type'        => 2,
		'group_title'       => 'Monthly',
		'group_data'        => [],
		'plan_id'           => $plan_id,
		'plan_title'        => 'Plan ' . $plan_id,
		'billing_interval'  => 3,
		'billing_frequency' => 1,
		'vid'               => 31,
		'relation_data'     => [ 'regular_price' => '10' ],
		'plan_data'         => [],
		'free_trial'        => 0,
		'signup_fee'        => 0,
	];
};

$GLOBALS['wp_object_cache'][ PlanRepository::CACHE_GROUP ]['product_30'] = [ $row( 11 ), $row( 12 ) ];

$parent    = new \WC_Product_Stub( 30, 'Beans', [ 'type' => 'variable' ] );
$variation = new \WC_Product_Stub( 31, 'Beans - Large', [ 'type' => 'variation' ] );
$bare      = new \WC_Product_Stub( 32, 'Beans - Small', [ 'type' => 'variation' ] );

$plans = new Plans();

$hooked = [];
foreach ( $GLOBALS['wp_hooks_registry'] as $hook => $by_priority ) {
	foreach ( $by_priority as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback[0] ) ) {
				$hooked[] = $hook . ':' . $callback[0][1];
			}
		}
	}
}

$GLOBALS['wp_is_product'] = true;
$GLOBALS['product']       = $parent;
ob_start();
$plans->render_selector();
$render = (string) ob_get_clean();

// Off the product page, as over `wc-ajax=get_variation`.
$GLOBALS['wp_is_product'] = false;
$data                     = Plans::push_variation_html( [ 'variation_id' => 31 ], $parent, $variation );
$bare_data                = Plans::push_variation_html( [ 'variation_id' => 32 ], $parent, $bare );

echo wp_json_encode(
	[
		'pro'       => subscrpt_pro_activated(),
		'hooked'    => $hooked,
		'render'    => $render,
		'variation' => $data,
		'bare'      => $bare_data,
	]
);
