<?php
/**
 * Tests for subscrpt_one_time_group().
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The storefront One-Time Purchase card.
 */
class OneTimeGroupTest extends TestCase {

	/**
	 * Start every test with no meta and no filter overrides.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_post_meta']      = [];
		$GLOBALS['wp_filter_returns'] = [];
	}

	/**
	 * Build a product with one-time purchase switched on or off.
	 *
	 * @param array  $props   Product properties.
	 * @param string $enabled The `_subscrpt_one_time_enabled` value.
	 */
	private function product( array $props, string $enabled = 'yes' ): \WC_Product {
		$GLOBALS['wp_post_meta'][10]['_subscrpt_one_time_enabled'] = $enabled;
		return new \WC_Product_Stub( 10, 'Beans', $props );
	}

	public function test_no_card_when_one_time_is_off() {
		$product = $this->product( [ 'regular_price' => '20' ], 'no' );

		$this->assertNull( subscrpt_one_time_group( $product ) );
	}

	public function test_card_carries_the_native_price() {
		$group = subscrpt_one_time_group( $this->product( [ 'regular_price' => '20' ] ) );

		$this->assertSame( 'one_time', $group['type'] );
		$this->assertSame( '$20.00', $group['price'] );
		$this->assertSame( '', $group['old_price'] );
		$this->assertSame( '', $group['badge'] );
		$this->assertSame( 0, $group['discount_percent'] );
	}

	public function test_sale_price_strikes_the_regular_and_badges() {
		$group = subscrpt_one_time_group(
			$this->product(
				[
					'regular_price' => '20',
					'sale_price'    => '16',
				]
			)
		);

		$this->assertSame( '$16.00', $group['price'] );
		$this->assertSame( '$20.00', $group['old_price'] );
		$this->assertSame( 20, $group['discount_percent'] );
		$this->assertSame( 'Save 20%', $group['badge'] );
	}
}
