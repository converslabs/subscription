<?php
/**
 * Tests for the server-rendered plan selector on variations.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Frontend\PlanGroups;
use SpringDevs\Subscription\Frontend\Plans;
use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * Plans::selector_html() and Plans::push_variation_html().
 */
class PlanVariationHtmlTest extends TestCase {

	/**
	 * Start every test off a product page, with no meta, cache or listeners.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_post_meta']      = [];
		$GLOBALS['wp_filter_returns'] = [];
		$GLOBALS['wp_hooks_registry'] = [];
		$GLOBALS['applied_filters']   = [];
		$GLOBALS['wp_object_cache']   = [];
		$GLOBALS['wp_is_product']     = false;
	}

	/**
	 * A resolved plan row in the shape PlanRepository::resolve_for_product() returns.
	 *
	 * @param int $plan_id Plan (term) id.
	 * @param int $vid     Variation the relation belongs to, 0 for all.
	 * @param int $price   Regular price.
	 */
	private function row( int $plan_id, int $vid, int $price ): array {
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
			'relation_data'     => [ 'regular_price' => (string) $price ],
			'plan_data'         => [],
			'free_trial'        => 0,
			'signup_fee'        => 0,
		];
	}

	/**
	 * Groups with two terms and a One-Time card, as the template receives them.
	 */
	private function sample_groups(): array {
		$GLOBALS['wp_post_meta'][10]['_subscrpt_one_time_enabled'] = 'yes';

		return PlanGroups::from_rows(
			[ $this->row( 11, 0, 10 ), $this->row( 12, 0, 25 ) ],
			new \WC_Product_Stub( 10, 'Beans', [ 'regular_price' => '20' ] )
		);
	}

	/**
	 * A variable parent (30) and two of its variations: 31 has a plan, 32 none.
	 */
	private function variable(): array {
		$GLOBALS['wp_object_cache'][ PlanRepository::CACHE_GROUP ]['product_30'] = [
			$this->row( 11, 31, 10 ),
			$this->row( 12, 31, 25 ),
		];

		return [
			new \WC_Product_Stub( 30, 'Beans', [ 'type' => 'variable' ] ),
			new \WC_Product_Stub(
				31,
				'Beans - Large',
				[
					'type'          => 'variation',
					'regular_price' => '40',
				]
			),
			new \WC_Product_Stub(
				32,
				'Beans - Small',
				[
					'type'          => 'variation',
					'regular_price' => '15',
				]
			),
		];
	}

	public function test_variation_card_html_equals_simple_card_html_for_the_same_groups() {
		$groups  = $this->sample_groups();
		$product = new \WC_Product_Stub( 10, 'Beans', [ 'regular_price' => '20' ] );

		$page      = Plans::selector_html( $groups, $product, 'page' );
		$variation = Plans::selector_html( $groups, $product, 'variation' );

		$this->assertStringContainsString( 'data-subscrpt-context="page"', $page );
		$this->assertStringContainsString( 'data-subscrpt-context="variation"', $variation );
		$this->assertStringContainsString( 'data-subscrpt-term-btn data-term-id="12"', $page );
		$this->assertStringContainsString( 'for="subscrpt-grp-one_time"', $page );
		$this->assertSame(
			str_replace( 'data-subscrpt-context="page"', '', $page ),
			str_replace( 'data-subscrpt-context="variation"', '', $variation )
		);
	}

	public function test_available_variation_carries_html_without_is_product() {
		list( $parent, $variation ) = $this->variable();
		$this->assertFalse( is_product() );

		$data = Plans::push_variation_html( [ 'variation_id' => 31 ], $parent, $variation );

		$this->assertNotEmpty( $data['subscrpt_plans_html'] ?? '' );
		$this->assertStringContainsString( 'data-subscrpt-context="variation"', $data['subscrpt_plans_html'] );
		$this->assertStringContainsString( 'data-term-id="12"', $data['subscrpt_plans_html'] );
		$this->assertSame( 31, $data['variation_id'] );
	}

	public function test_variation_without_plans_gets_no_html() {
		list( $parent, , $variation ) = $this->variable();
		$GLOBALS['wp_is_product']     = true;

		$data = Plans::push_variation_html( [ 'variation_id' => 32 ], $parent, $variation );

		$this->assertSame( [ 'variation_id' => 32 ], $data );
	}
}
