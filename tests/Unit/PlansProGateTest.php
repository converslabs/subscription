<?php
/**
 * Tests for what the storefront plan selector shows with and without pro.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Frontend\Plans;
use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * Free renders the purchase options without pro, and with a pro that says it
 * renders through free (`subscrpt_plan_selector_from_free`); an older pro draws
 * its own selector, so free stays out of its way. A variable product's options
 * render only with pro active, since free's checkout cannot sell a variation's
 * plan; and the plan price HTML only without pro, which rewrites the same price
 * itself.
 *
 * The pro tests read tests/Support/plans-with-pro.php, run in a process of its
 * own: once pro's class exists it cannot be taken away again.
 */
class PlansProGateTest extends TestCase {

	/**
	 * Start every test on a product page, with no meta, cache or listeners.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_post_meta']      = [];
		$GLOBALS['wp_filter_returns'] = [];
		$GLOBALS['wp_hooks_registry'] = [];
		$GLOBALS['applied_filters']   = [];
		$GLOBALS['wp_object_cache']   = [];
		$GLOBALS['wp_is_product']     = true;
	}

	/**
	 * The selector with pro active, from a separate PHP process.
	 *
	 * @param string $pro `new` for a pro that renders through free, `old` for one with its own selector.
	 *
	 * @return array pro, hooked, render, variation, bare.
	 */
	private static function with_pro( string $pro = 'new' ): array {
		static $results = [];

		if ( ! isset( $results[ $pro ] ) ) {
			$script = dirname( __DIR__ ) . '/Support/plans-with-pro.php';
			$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' ' . escapeshellarg( $pro ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
			$result = json_decode( (string) $output, true );
			self::assertIsArray( $result, 'plans-with-pro.php printed: ' . $output );
			self::assertTrue( $result['pro'] );

			$results[ $pro ] = $result;
		}

		return $results[ $pro ];
	}

	/**
	 * A resolved plan row for a variation.
	 *
	 * @param int $plan_id Plan (term) id.
	 * @param int $vid     Variation the relation belongs to.
	 */
	private function row( int $plan_id, int $vid ): array {
		return [
			'plan_group_id'     => 1,
			'group_type'        => 2,
			'group_title'       => 'Monthly',
			'group_data'        => [],
			'plan_id'           => $plan_id,
			'plan_title'        => 'Plan ' . $plan_id,
			'billing_interval'  => 3,
			'billing_frequency' => 1,
			'vid'               => $vid,
			'relation_data'     => [ 'regular_price' => '10' ],
			'plan_data'         => [],
			'free_trial'        => 0,
			'signup_fee'        => 0,
		];
	}

	/**
	 * A variable product (30) whose variation 31 is tied to two plan terms.
	 *
	 * @return \WC_Product[] Parent, then variation.
	 */
	private function variable(): array {
		$GLOBALS['wp_object_cache'][ PlanRepository::CACHE_GROUP ]['product_30'] = [
			$this->row( 11, 31 ),
			$this->row( 12, 31 ),
		];

		return [
			new \WC_Product_Stub( 30, 'Beans', [ 'type' => 'variable' ] ),
			new \WC_Product_Stub( 31, 'Beans - Large', [ 'type' => 'variation' ] ),
		];
	}

	/**
	 * What render_selector() prints for a product.
	 *
	 * @param \WC_Product $product Product on the page.
	 */
	private function render( \WC_Product $product ): string {
		$GLOBALS['product'] = $product;
		ob_start();
		( new Plans() )->render_selector();
		return (string) ob_get_clean();
	}

	/**
	 * Whether a Plans method is registered on a hook.
	 *
	 * @param string $hook   Hook name.
	 * @param string $method Plans method.
	 */
	private function hooked( string $hook, string $method ): bool {
		foreach ( $GLOBALS['wp_hooks_registry'][ $hook ] ?? [] as $callbacks ) {
			foreach ( $callbacks as list( $callback ) ) {
				if ( is_array( $callback ) && $method === $callback[1] ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Without pro a variable product shows no selector, even with plans tied to its variations. */
	public function test_without_pro_a_variable_product_shows_no_selector() {
		list( $parent ) = $this->variable();

		$this->assertSame( '', $this->render( $parent ) );
	}

	/** Without pro no variation carries cards, since its parent shows no selector to put them in. */
	public function test_without_pro_a_variation_carries_no_cards() {
		list( $parent, $variation ) = $this->variable();

		$data = Plans::push_variation_html( [ 'variation_id' => 31 ], $parent, $variation );

		$this->assertSame( [ 'variation_id' => 31 ], $data );
	}

	/** With pro a variable product shows the selector, holding the placeholder. */
	public function test_with_pro_a_variable_product_shows_the_placeholder() {
		$html = self::with_pro()['render'];

		$this->assertStringContainsString( 'data-subscrpt-variable="1"', $html );
		$this->assertStringContainsString( 'subscrpt-buybox__placeholder', $html );
	}

	/** With pro each variation carries its server-rendered cards, off the product page too (wc-ajax=get_variation). */
	public function test_with_pro_a_variation_carries_its_cards() {
		$data = self::with_pro()['variation'];

		$this->assertStringContainsString( 'data-subscrpt-context="variation"', $data['subscrpt_plans_html'] ?? '' );
		$this->assertStringContainsString( 'data-term-id="12"', $data['subscrpt_plans_html'] ?? '' );
	}

	/** With pro a variation without plans carries no cards. */
	public function test_with_pro_a_variation_without_plans_carries_no_cards() {
		$this->assertSame( [ 'variation_id' => 32 ], self::with_pro()['bare'] );
	}

	/** An older pro draws its own selector: free renders none, so the page never shows two. */
	public function test_with_an_older_pro_free_renders_no_selector() {
		$old = self::with_pro( 'old' );

		$this->assertSame( '', $old['render'] );
		$this->assertSame( [ 'variation_id' => 31 ], $old['variation'] );
	}

	/** Without pro free renders, whatever the filter says: no pro is there to answer it. */
	public function test_without_pro_the_filter_is_not_needed() {
		$GLOBALS['wp_filter_returns']['subscrpt_plan_selector_from_free'] = false;
		$GLOBALS['wp_object_cache'][ PlanRepository::CACHE_GROUP ]['product_20'] = [ array_merge( $this->row( 11, 0 ), [ 'vid' => 0 ] ) ];

		$this->assertStringContainsString( 'data-subscrpt-single-term="11"', $this->render( new \WC_Product_Stub( 20, 'Mug' ) ) );
	}

	/** Without pro free rewrites the plan price and renders the selector. */
	public function test_without_pro_free_owns_the_price_html() {
		new Plans();

		$this->assertTrue( $this->hooked( 'woocommerce_get_price_html', 'plan_price_html' ) );
		$this->assertTrue( $this->hooked( 'woocommerce_before_add_to_cart_button', 'render_selector' ) );
	}

	/** With pro free still renders the selector but leaves the price HTML to pro. */
	public function test_with_pro_free_renders_the_selector_but_not_the_price() {
		$hooked = self::with_pro()['hooked'];

		$this->assertNotContains( 'woocommerce_get_price_html:plan_price_html', $hooked );
		$this->assertContains( 'woocommerce_before_add_to_cart_button:render_selector', $hooked );
		$this->assertContains( 'woocommerce_available_variation:push_variation_html', $hooked );
		$this->assertContains( 'wp_enqueue_scripts:enqueue_assets', $hooked );
	}
}
