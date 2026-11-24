<?php
/**
 * Tests for shipping on a renewal order.
 *
 * @package SpringDevs\Subscription
 */

namespace {
	if ( ! class_exists( 'WC_Order' ) ) {
		/**
		 * Stand-in parent for the fake orders, so `instanceof WC_Order` holds.
		 */
		class WC_Order {}
	}

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		/**
		 * The parent gateway, as much as the PayPal gateway reads without its constructor.
		 */
		class WC_Payment_Gateway {

			/**
			 * Gateway id.
			 *
			 * @var string
			 */
			public $id = '';
		}
	}

	if ( ! class_exists( 'WC_Order_Item_Shipping' ) ) {
		/**
		 * A shipping line, keeping the props set on it.
		 */
		class WC_Order_Item_Shipping {

			/**
			 * Props set on the line.
			 *
			 * @var array
			 */
			public $props = [];

			/**
			 * Remember the props.
			 *
			 * @param array $props Props.
			 */
			public function set_props( $props ) {
				$this->props = array_merge( $this->props, $props );
			}
		}
	}
}

namespace SpringDevs\Subscription\Tests\Unit {

	use PHPUnit\Framework\TestCase;
	use SpringDevs\Subscription\Illuminate\Gateways\Paypal\Paypal;
	use SpringDevs\Subscription\Illuminate\Helper;

	/**
	 * Renewals carry the checkout's shipping only when the store turns it on,
	 * and a PayPal renewal only when PayPal bills it.
	 */
	class RenewalShippingTest extends TestCase {

		/**
		 * Subscription id.
		 */
		const SUBSCRIPTION_ID = 77;

		/**
		 * Start with no meta, options or hooks.
		 */
		protected function setUp(): void {
			$GLOBALS['wp_post_meta']      = [];
			$GLOBALS['wp_options']        = [];
			$GLOBALS['wp_hooks_registry'] = [];
			$GLOBALS['wc_orders']         = [];
			$GLOBALS['wpdb']              = $this->wpdb( [] );
		}

		/**
		 * Drop the stubbed database.
		 */
		protected function tearDown(): void {
			unset( $GLOBALS['wpdb'] );
		}

		/**
		 * A database whose relation table holds these rows.
		 *
		 * @param array $rows Rows with order_id, order_item_id, type.
		 *
		 * @return object
		 */
		private function wpdb( array $rows ) {
			return new class( $rows ) {
				/**
				 * Table prefix.
				 *
				 * @var string
				 */
				public $prefix = 'wp_';

				/**
				 * Rows returned.
				 *
				 * @var array
				 */
				private $rows;

				/**
				 * Keep the rows.
				 *
				 * @param array $rows Rows.
				 */
				public function __construct( array $rows ) {
					$this->rows = $rows;
				}

				/**
				 * The query, unchanged.
				 *
				 * @param string $query Query.
				 * @param array  $args  Args.
				 *
				 * @return string
				 */
				public function prepare( $query, $args ) {
					return $query;
				}

				/**
				 * The rows.
				 *
				 * @return array
				 */
				public function get_results() {
					return $this->rows;
				}
			};
		}

		/**
		 * A shipping line on an order.
		 *
		 * @param string $total Cost.
		 *
		 * @return object
		 */
		private function shipping_line( string $total ) {
			return new class( $total ) {
				/**
				 * Cost.
				 *
				 * @var string
				 */
				private $total;

				/**
				 * Keep the cost.
				 *
				 * @param string $total Cost.
				 */
				public function __construct( string $total ) {
					$this->total = $total;
				}

				/**
				 * Title.
				 *
				 * @return string
				 */
				public function get_method_title() {
					return 'Flat rate';
				}

				/**
				 * Method id.
				 *
				 * @return string
				 */
				public function get_method_id() {
					return 'flat_rate';
				}

				/**
				 * Zone instance.
				 *
				 * @return int
				 */
				public function get_instance_id() {
					return 3;
				}

				/**
				 * Cost.
				 *
				 * @return string
				 */
				public function get_total() {
					return $this->total;
				}

				/**
				 * Taxes.
				 *
				 * @return array
				 */
				public function get_taxes() {
					return [ 'total' => [ 1 => '0.90' ] ];
				}
			};
		}

		/**
		 * An order holding these shipping lines.
		 *
		 * @param array  $shipping Shipping lines.
		 * @param string $method   Payment method.
		 *
		 * @return \WC_Order
		 */
		private function order( array $shipping = [], string $method = 'stripe' ) {
			return new class( $shipping, $method ) extends \WC_Order {
				/**
				 * Shipping lines.
				 *
				 * @var array
				 */
				public $shipping;

				/**
				 * Payment method.
				 *
				 * @var string
				 */
				private $method;

				/**
				 * Items added.
				 *
				 * @var array
				 */
				public $added = [];

				/**
				 * Keep the lines.
				 *
				 * @param array  $shipping Shipping lines.
				 * @param string $method   Payment method.
				 */
				public function __construct( array $shipping, string $method ) {
					$this->shipping = $shipping;
					$this->method   = $method;
				}

				/**
				 * Shipping lines, and any added since.
				 *
				 * @return array
				 */
				public function get_shipping_methods() {
					return array_merge( $this->shipping, $this->added );
				}

				/**
				 * Add a line.
				 *
				 * @param object $item Line.
				 */
				public function add_item( $item ) {
					$this->added[] = $item;
				}

				/**
				 * Payment method.
				 *
				 * @return string
				 */
				public function get_payment_method() {
					return $this->method;
				}

				/**
				 * Shipping cost.
				 *
				 * @return string
				 */
				public function get_shipping_total() {
					return '4.50';
				}

				/**
				 * Shipping tax.
				 *
				 * @return string
				 */
				public function get_shipping_tax() {
					return '0.90';
				}
			};
		}

		/**
		 * The subscription line, for a product that does or does not ship.
		 *
		 * @param bool $ships Whether the product needs shipping.
		 *
		 * @return object
		 */
		private function item( bool $ships = true ) {
			$product = $this->product( $ships );

			return new class( $product ) {
				/**
				 * Product.
				 *
				 * @var object
				 */
				private $product;

				/**
				 * Keep the product.
				 *
				 * @param object $product Product.
				 */
				public function __construct( $product ) {
					$this->product = $product;
				}

				/**
				 * Product.
				 *
				 * @return object
				 */
				public function get_product() {
					return $this->product;
				}
			};
		}

		/**
		 * A product that does or does not ship.
		 *
		 * @param bool $ships Whether it needs shipping.
		 *
		 * @return \WC_Product
		 */
		private function product( bool $ships ) {
			return new class( $ships ) extends \WC_Product {
				/**
				 * Whether it ships.
				 *
				 * @var bool
				 */
				private $ships;

				/**
				 * Keep the flag.
				 *
				 * @param bool $ships Whether it ships.
				 */
				public function __construct( bool $ships ) {
					parent::__construct( 9 );
					$this->ships = $ships;
				}

				/**
				 * Whether it ships.
				 *
				 * @return bool
				 */
				public function needs_shipping() {
					return $this->ships;
				}
			};
		}

		/**
		 * Turn Renewal Shipping on.
		 */
		private function turn_on(): void {
			$GLOBALS['wp_options']['subscrpt_renewal_shipping'] = 'copy';
		}

		/**
		 * Off by default: existing subscribers are not charged something new.
		 */
		public function test_off_by_default_adds_nothing(): void {
			$new = $this->order();
			Helper::add_renewal_shipping( $new, $this->order( [ $this->shipping_line( '4.50' ) ] ), self::SUBSCRIPTION_ID, $this->item() );

			$this->assertSame( [], $new->added );
		}

		/**
		 * On: the previous order's shipping is copied with its cost and tax.
		 */
		public function test_copies_previous_orders_shipping(): void {
			$this->turn_on();
			$new = $this->order();
			Helper::add_renewal_shipping( $new, $this->order( [ $this->shipping_line( '4.50' ) ] ), self::SUBSCRIPTION_ID, $this->item() );

			$this->assertCount( 1, $new->added );
			$this->assertSame(
				[
					'method_title' => 'Flat rate',
					'method_id'    => 'flat_rate',
					'instance_id'  => 3,
					'total'        => '4.50',
					'taxes'        => [ 'total' => [ 1 => '0.90' ] ],
				],
				$new->added[0]->props
			);
		}

		/**
		 * A renewal made before the setting was on has no shipping: use the first order's.
		 */
		public function test_falls_back_to_first_order(): void {
			$this->turn_on();
			$GLOBALS['wc_orders'][10] = $this->order( [ $this->shipping_line( '6.00' ) ] );
			$GLOBALS['wpdb']          = $this->wpdb(
				[
					(object) [
						'order_id' => 12,
						'order_item_id' => 2,
						'type' => 'renew',
					],
					(object) [
						'order_id' => 10,
						'order_item_id' => 1,
						'type' => 'new',
					],
				]
			);

			$new = $this->order();
			Helper::add_renewal_shipping( $new, $this->order(), self::SUBSCRIPTION_ID, $this->item() );

			$this->assertCount( 1, $new->added );
			$this->assertSame( '6.00', $new->added[0]->props['total'] );
		}

		/**
		 * A virtual subscription bought with a physical product ships nothing on renewal.
		 */
		public function test_virtual_subscription_gets_no_shipping(): void {
			$this->turn_on();
			$new = $this->order();
			Helper::add_renewal_shipping( $new, $this->order( [ $this->shipping_line( '4.50' ) ] ), self::SUBSCRIPTION_ID, $this->item( false ) );

			$this->assertSame( [], $new->added );
		}

		/**
		 * A renewal that already has shipping (a box, or a retry) gets no second line.
		 */
		public function test_no_second_shipping_line(): void {
			$this->turn_on();
			$new = $this->order( [ $this->shipping_line( '4.50' ) ] );
			Helper::add_renewal_shipping( $new, $this->order( [ $this->shipping_line( '4.50' ) ] ), self::SUBSCRIPTION_ID, $this->item() );

			$this->assertSame( [], $new->added );
		}

		/**
		 * The gateway may refuse the copy.
		 */
		public function test_filter_can_refuse(): void {
			$this->turn_on();
			add_filter(
				'subscrpt_renewal_copy_shipping',
				static function () {
					return false;
				}
			);
			$new = $this->order();
			Helper::add_renewal_shipping( $new, $this->order( [ $this->shipping_line( '4.50' ) ] ), self::SUBSCRIPTION_ID, $this->item() );

			$this->assertSame( [], $new->added );
		}

		/**
		 * The PayPal gateway, without its constructor.
		 *
		 * @return Paypal
		 */
		private function gateway(): Paypal {
			$gateway     = ( new \ReflectionClass( Paypal::class ) )->newInstanceWithoutConstructor();
			$gateway->id = 'wp_subscription_paypal';

			return $gateway;
		}

		/**
		 * A PayPal subscription bought before the setting was on bills no shipping.
		 */
		public function test_paypal_renewal_without_billed_shipping_is_refused(): void {
			$old = $this->order( [], 'wp_subscription_paypal' );

			$this->assertFalse( $this->gateway()->maybe_skip_renewal_shipping( true, self::SUBSCRIPTION_ID, $this->order(), $old ) );
		}

		/**
		 * A PayPal subscription whose plan includes shipping keeps it.
		 */
		public function test_paypal_renewal_with_billed_shipping_is_allowed(): void {
			$GLOBALS['wp_post_meta'][ self::SUBSCRIPTION_ID ]['_wpsubs_paypal_bills_shipping'] = '1';
			$old = $this->order( [], 'wp_subscription_paypal' );

			$this->assertTrue( $this->gateway()->maybe_skip_renewal_shipping( true, self::SUBSCRIPTION_ID, $this->order(), $old ) );
		}

		/**
		 * Other gateways are left alone.
		 */
		public function test_other_gateway_untouched(): void {
			$this->assertTrue( $this->gateway()->maybe_skip_renewal_shipping( true, self::SUBSCRIPTION_ID, $this->order(), $this->order() ) );
		}

		/**
		 * PayPal bills shipping with its tax on every cycle, only when on and the product ships.
		 */
		public function test_paypal_renewal_shipping_amount(): void {
			$order = $this->order( [ $this->shipping_line( '4.50' ) ], 'wp_subscription_paypal' );

			$this->assertNull( $this->gateway()->get_renewal_shipping_amount( $order, $this->product( true ) ) );

			$this->turn_on();
			$this->assertSame( 5.4, $this->gateway()->get_renewal_shipping_amount( $order, $this->product( true ) ) );
			$this->assertNull( $this->gateway()->get_renewal_shipping_amount( $order, $this->product( false ) ) );
			$this->assertNull( $this->gateway()->get_renewal_shipping_amount( $this->order(), $this->product( true ) ) );
		}
		/**
		 * A cart with this shipping chosen.
		 *
		 * @return object
		 */
		private function cart() {
			return new class() {
				/**
				 * Shipping cost.
				 *
				 * @return string
				 */
				public function get_shipping_total() {
					return '50.00';
				}

				/**
				 * Shipping tax.
				 *
				 * @return string
				 */
				public function get_shipping_tax() {
					return '10.00';
				}
			};
		}

		/**
		 * The recurring total shown in the cart includes shipping only when renewals charge it.
		 */
		public function test_cart_renewal_shipping(): void {
			$ships = [ 'data' => $this->product( true ) ];

			$this->assertSame( 0.0, Helper::get_cart_renewal_shipping( $ships, $this->cart() ) );

			$this->turn_on();
			$this->assertSame( 60.0, Helper::get_cart_renewal_shipping( $ships, $this->cart() ) );
			$this->assertSame( 0.0, Helper::get_cart_renewal_shipping( [ 'data' => $this->product( false ) ], $this->cart() ) );
		}

		/**
		 * Pro may change the amount, e.g. for a box.
		 */
		public function test_cart_renewal_shipping_filter(): void {
			add_filter(
				'subscrpt_cart_renewal_shipping',
				static function () {
					return 7.5;
				}
			);

			$this->assertSame( 7.5, Helper::get_cart_renewal_shipping( [ 'data' => $this->product( true ) ], $this->cart() ) );
		}
	}
}
