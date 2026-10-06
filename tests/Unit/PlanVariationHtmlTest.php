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

/**
 * Plans::selector_html() renders the same cards for both contexts.
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
}
