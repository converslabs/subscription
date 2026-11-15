<?php
/**
 * Tests for checkout on an order that already renews a subscription.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Frontend\Checkout;

/**
 * An express wallet on the pay-for-order page pays an existing order through the
 * Store API's checkout/{order_id} route, which fires the checkout hook again. A
 * renewal or early renewal order must not start a second subscription (#355).
 */
class CheckoutRenewalOrderTest extends TestCase {

	/**
	 * Start with no orders, relations or recorded actions.
	 */
	protected function setUp(): void {
		$GLOBALS['wc_orders']       = [];
		$GLOBALS['applied_actions'] = [];

		$GLOBALS['wpdb'] = new class() {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public $prefix = 'wp_';

			/**
			 * Relation rows by order id.
			 *
			 * @var array
			 */
			public $relations = [];

			/**
			 * Keep the arguments, so get_results() can read the order id.
			 *
			 * @param string $query Query.
			 * @param array  $args  Arguments.
			 *
			 * @return array
			 */
			public function prepare( $query, $args ) {
				return $args;
			}

			/**
			 * The relation rows of the order in the prepared arguments.
			 *
			 * @param array $prepared Prepared arguments: table, order id.
			 *
			 * @return array
			 */
			public function get_results( $prepared ) {
				return $this->relations[ (int) $prepared[1] ] ?? [];
			}
		};
	}

	/**
	 * An order with one item, recording whether checkout read it.
	 *
	 * @param int $id Order id.
	 *
	 * @return object
	 */
	private function order( int $id ) {
		$order = new class( $id ) {
			/**
			 * Whether checkout went on to read the order.
			 *
			 * @var bool
			 */
			public $read = false;

			/**
			 * Order id.
			 *
			 * @var int
			 */
			private $id;

			/**
			 * Remember the id.
			 *
			 * @param int $id Order id.
			 */
			public function __construct( int $id ) {
				$this->id = $id;
			}

			/**
			 * Order status.
			 *
			 * @return string
			 */
			public function get_status() {
				$this->read = true;
				return 'processing';
			}

			/**
			 * No items, so a read order creates nothing.
			 *
			 * @return array
			 */
			public function get_items() {
				return [];
			}
		};

		$GLOBALS['wc_orders'][ $id ] = $order;

		return $order;
	}

	/**
	 * Link an order to a subscription.
	 *
	 * @param int    $order_id Order id.
	 * @param string $type     Relation type.
	 */
	private function relate( int $order_id, string $type ): void {
		$GLOBALS['wpdb']->relations[ $order_id ][] = (object) [
			'subscription_id' => 10,
			'order_id'        => $order_id,
			'order_item_id'   => 1,
			'type'            => $type,
		];
	}

	/**
	 * The reported case: an early renewal paid with Google Pay.
	 */
	public function test_early_renewal_order_creates_no_subscription(): void {
		$order = $this->order( 2538 );
		$this->relate( 2538, 'early-renew' );

		( new Checkout() )->create_subscription_after_checkout( 2538 );

		$this->assertFalse( $order->read );
	}

	/**
	 * An ordinary renewal paid the same way.
	 */
	public function test_renewal_order_creates_no_subscription(): void {
		$order = $this->order( 2542 );
		$this->relate( 2542, 'renew' );

		( new Checkout() )->create_subscription_after_checkout( 2542 );

		$this->assertFalse( $order->read );
	}

	/**
	 * A new checkout still goes on to create its subscriptions.
	 */
	public function test_new_order_is_still_processed(): void {
		$order = $this->order( 2510 );

		( new Checkout() )->create_subscription_after_checkout( 2510 );

		$this->assertTrue( $order->read );
	}
}
