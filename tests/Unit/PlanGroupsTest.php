<?php
/**
 * Tests for the storefront plan groups builder.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Frontend\PlanGroups;

/**
 * PlanGroups::from_rows().
 */
class PlanGroupsTest extends TestCase {

	/**
	 * Start every test with no meta, no listeners and no forced filter returns.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_post_meta']      = [];
		$GLOBALS['wp_filter_returns'] = [];
		$GLOBALS['wp_hooks_registry'] = [];
		$GLOBALS['applied_filters']   = [];
	}

	/**
	 * A resolved plan row in the shape PlanRepository::resolve_for_product() returns.
	 *
	 * @param int   $group_id  Plan group id.
	 * @param int   $type      Stored group type (1 subscribe & save, 2 recurring, 3 installments).
	 * @param int   $plan_id   Plan (term) id.
	 * @param array $relation  Relation data (regular_price, sale_price, discount_type, discount_value).
	 * @param array $plan_data Plan data blob.
	 */
	private function row( int $group_id, int $type, int $plan_id, array $relation, array $plan_data = [] ): array {
		return [
			'plan_group_id'     => $group_id,
			'group_type'        => $type,
			'group_title'       => 'Group ' . $group_id,
			'group_data'        => [],
			'plan_id'           => $plan_id,
			'plan_title'        => 'Plan ' . $plan_id,
			'billing_interval'  => 3,
			'billing_frequency' => 1,
			'relation_data'     => $relation,
			'plan_data'         => $plan_data,
			'free_trial'        => 0,
			'signup_fee'        => 0,
		];
	}

	/**
	 * A simple product with one-time purchase off.
	 */
	private function product(): \WC_Product {
		return new \WC_Product_Stub( 10, 'Beans', [ 'regular_price' => '20' ] );
	}

	public function test_one_group_per_plan_group_with_its_terms() {
		$rows = [
			$this->row( 1, 2, 11, [ 'regular_price' => '10' ] ),
			$this->row( 1, 2, 12, [ 'regular_price' => '25' ] ),
			$this->row( 2, 1, 21, [ 'regular_price' => '15' ] ),
		];

		$groups = PlanGroups::from_rows( $rows, $this->product() );

		$this->assertCount( 2, $groups );
		$this->assertSame( [ 'grp_1', 'grp_2' ], array_column( $groups, 'id' ) );
		$this->assertSame( [ 'recurring', 'subscribe_save' ], array_column( $groups, 'type' ) );
		$this->assertSame( 'Group 1', $groups[0]['label'] );
		$this->assertSame( [ 11, 12 ], array_column( $groups[0]['terms'], 'id' ) );
		$this->assertSame( [ 21 ], array_column( $groups[1]['terms'], 'id' ) );
		$this->assertSame( '$10.00', $groups[0]['price'] );
		$this->assertSame( '', $groups[0]['old_price'] );
		$this->assertSame( 'Billed $25.00 / month', $groups[0]['terms'][1]['note'] );
		foreach ( [ 'id', 'type', 'label', 'price', 'old_price', 'terms', 'badge', 'discount_percent' ] as $key ) {
			$this->assertArrayHasKey( $key, $groups[0] );
		}
	}

	public function test_term_discount_and_badge_follow_each_term() {
		$rows = [
			$this->row(
				1,
				1,
				11,
				[
					'regular_price'  => '100',
					'discount_type'  => 'percentage',
					'discount_value' => '20',
				]
			),
			$this->row(
				1,
				1,
				12,
				[
					'regular_price'  => '100',
					'discount_type'  => 'percentage',
					'discount_value' => '10',
				]
			),
		];

		$groups = PlanGroups::from_rows( $rows, $this->product() );
		$this->assertCount( 1, $groups );
		$group = $groups[0];

		$this->assertSame( [ 20, 10 ], array_column( $group['terms'], 'discount_percent' ) );
		$this->assertSame( [ 'Save 20%', 'Save 10%' ], array_column( $group['terms'], 'badge' ) );
		$this->assertSame( 'Save 20%', $group['badge'] );
		$this->assertSame( 20, $group['discount_percent'] );
	}

	public function test_installments_term_is_priced_per_payment_with_its_note() {
		$rows = [ $this->row( 1, 3, 11, [ 'regular_price' => '90' ], [ 'installment_count' => 3 ] ) ];

		$groups = PlanGroups::from_rows( $rows, $this->product() );
		$this->assertCount( 1, $groups );
		$group = $groups[0];

		$this->assertSame( 'installments', $group['type'] );
		$this->assertSame( wc_price( 30 ), $group['terms'][0]['price'] );
		$this->assertSame( wc_price( 30 ), $group['price'] );
		$this->assertSame( 'Pay $90.00 in 3 installments of $30.00, billed every month.', $group['terms'][0]['note'] );
		$this->assertSame( 0, $group['terms'][0]['discount_percent'] );
	}

	public function test_one_time_card_is_appended_last() {
		$GLOBALS['wp_post_meta'][10]['_subscrpt_one_time_enabled'] = 'yes';
		$rows = [
			$this->row( 1, 2, 11, [ 'regular_price' => '10' ] ),
			$this->row( 2, 1, 21, [ 'regular_price' => '15' ] ),
		];

		$groups = PlanGroups::from_rows( $rows, $this->product() );

		$this->assertCount( 3, $groups );
		$this->assertSame( 'one_time', $groups[2]['type'] );
		$this->assertSame( '$20.00', $groups[2]['price'] );
	}

	public function test_term_filter_can_add_to_a_term() {
		$product  = $this->product();
		$received = [];
		add_filter(
			'subscrpt_plan_term',
			static function ( $term, $row, $type_key, $product ) use ( &$received ) {
				$received[] = [ $term, $row, $type_key, $product ];
				if ( 12 === $term['id'] ) {
					$term['note'] .= ' +trial';
				}
				return $term;
			},
			10,
			4
		);
		$rows = [
			$this->row( 1, 2, 11, [ 'regular_price' => '10' ] ),
			$this->row( 1, 2, 12, [ 'regular_price' => '25' ] ),
		];

		$groups = PlanGroups::from_rows( $rows, $product );
		$this->assertCount( 1, $groups );
		$group = $groups[0];

		$this->assertSame( 'Billed $10.00 / month', $group['terms'][0]['note'] );
		$this->assertSame( 'Billed $25.00 / month +trial', $group['terms'][1]['note'] );
		$this->assertCount( 2, $received );
		$this->assertSame( 12, $received[1][0]['id'] );
		$this->assertSame( $rows[1], $received[1][1] );
		$this->assertSame( 'recurring', $received[1][2] );
		$this->assertSame( $product, $received[1][3] );
	}

	public function test_groups_filter_receives_context() {
		$GLOBALS['wp_post_meta'][10]['_subscrpt_one_time_enabled'] = 'yes';
		$product = $this->product();
		$GLOBALS['wp_filter_returns']['subscrpt_plan_selector_groups'] = [ 'filtered' ];

		$groups = PlanGroups::from_rows( [ $this->row( 1, 2, 11, [ 'regular_price' => '10' ] ) ], $product );

		$this->assertSame( [ 'filtered' ], $groups );
		$this->assertCount( 1, $GLOBALS['applied_filters']['subscrpt_plan_selector_groups'] ?? [] );
		list( $passed, $passed_product, $context ) = $GLOBALS['applied_filters']['subscrpt_plan_selector_groups'][0];
		$this->assertSame( [ 'grp_1', 'one_time' ], array_column( $passed, 'id' ) );
		$this->assertSame( $product, $passed_product );
		$this->assertSame( 'page', $context );
	}

	public function test_variation_uses_the_variation_for_one_time() {
		$rows      = [ $this->row( 1, 2, 11, [ 'regular_price' => '10' ] ) ];
		$variation = new \WC_Product_Stub(
			31,
			'Beans - Large',
			[
				'type'          => 'variation',
				'regular_price' => '40',
			]
		);

		// Enabled on the parent only: the variation does not offer one-time.
		$GLOBALS['wp_post_meta'][30]['_subscrpt_one_time_enabled'] = 'yes';
		$this->assertSame( [ 'grp_1' ], array_column( PlanGroups::from_rows( $rows, $variation ), 'id' ) );

		$GLOBALS['wp_post_meta'][31]['_subscrpt_one_time_enabled'] = 'yes';
		$groups = PlanGroups::from_rows( $rows, $variation );
		$this->assertSame( [ 'grp_1', 'one_time' ], array_column( $groups, 'id' ) );
		$this->assertSame( '$40.00', $groups[1]['price'] );
	}
}
