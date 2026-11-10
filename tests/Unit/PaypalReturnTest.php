<?php
/**
 * Tests for the PayPal return to the order-received page.
 *
 * @package SpringDevs\Subscription
 */

namespace {
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

	if ( ! function_exists( 'is_order_received_page' ) ) {
		function is_order_received_page() {
			return true;
		}
	}

	if ( ! function_exists( 'wp_unslash' ) ) {
		function wp_unslash( $value ) {
			return is_string( $value ) ? stripslashes( $value ) : $value;
		}
	}
}

namespace SpringDevs\Subscription\Tests\Unit {

	use PHPUnit\Framework\TestCase;
	use SpringDevs\Subscription\Illuminate\Gateways\Paypal\Paypal;

	/**
	 * The order-received page completes an order only on the PayPal subscription
	 * that belongs to it.
	 */
	class PaypalReturnTest extends TestCase {

		/**
		 * Order id.
		 */
		const ORDER_ID = 501;

		/**
		 * Order key.
		 */
		const ORDER_KEY = 'wc_order_abc123';

		/**
		 * The PayPal subscription created for this order at checkout.
		 */
		const STORED_ID = 'I-STORED0001';

		/**
		 * PayPal subscriptions and orders the stubbed API knows: [id] => object.
		 *
		 * @var array
		 */
		private $paypal = [];

		/**
		 * Ids the gateway asked PayPal about.
		 *
		 * @var string[]
		 */
		private $looked_up = [];

		/**
		 * No orders, no request.
		 */
		protected function setUp(): void {
			$GLOBALS['wc_orders'] = [];
			$this->paypal         = [];
			$this->looked_up      = [];

			$GLOBALS['wpdb'] = new class() {
				/**
				 * Table prefix.
				 *
				 * @var string
				 */
				public $prefix = 'wp_';

				/**
				 * Pass the arguments through.
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
				 * No subscription relations.
				 *
				 * @return array
				 */
				public function get_results() {
					return [];
				}
			};
		}

		/**
		 * Clear the request.
		 */
		protected function tearDown(): void {
			$_GET = [];
		}

		/**
		 * A pending PayPal order holding the PayPal subscription created at checkout.
		 *
		 * @return object
		 */
		private function order() {
			$order = new class( self::ORDER_ID, self::ORDER_KEY ) {
				/**
				 * Order id.
				 *
				 * @var int
				 */
				private $id;

				/**
				 * Order key.
				 *
				 * @var string
				 */
				private $key;

				/**
				 * Status.
				 *
				 * @var string
				 */
				public $status = 'pending';

				/**
				 * Meta.
				 *
				 * @var array
				 */
				public $meta = [];

				/**
				 * Remember the id and key.
				 *
				 * @param int    $id  Order id.
				 * @param string $key Order key.
				 */
				public function __construct( int $id, string $key ) {
					$this->id  = $id;
					$this->key = $key;
				}

				/**
				 * Order id.
				 *
				 * @return int
				 */
				public function get_id() {
					return $this->id;
				}

				/**
				 * Order key.
				 *
				 * @return string
				 */
				public function get_order_key() {
					return $this->key;
				}

				/**
				 * Payment method.
				 *
				 * @return string
				 */
				public function get_payment_method() {
					return 'wp_subscription_paypal';
				}

				/**
				 * Status.
				 *
				 * @return string
				 */
				public function get_status() {
					return $this->status;
				}

				/**
				 * Meta value.
				 *
				 * @param string $key Meta key.
				 *
				 * @return mixed
				 */
				public function get_meta( $key ) {
					return $this->meta[ $key ] ?? '';
				}

				/**
				 * Set a meta value.
				 *
				 * @param string $key   Meta key.
				 * @param mixed  $value Value.
				 */
				public function update_meta_data( $key, $value ) {
					$this->meta[ $key ] = $value;
				}

				/**
				 * Change the status.
				 *
				 * @param string $status New status.
				 */
				public function update_status( $status ) {
					$this->status = $status;
				}

				/**
				 * Nothing to persist.
				 */
				public function save() {}

				/**
				 * No line items.
				 *
				 * @return array
				 */
				public function get_items() {
					return [];
				}
			};

			$order->meta['_wp_subs_paypal_live_subscription_id'] = self::STORED_ID;
			$GLOBALS['wc_orders'][ self::ORDER_ID ]              = $order;

			return $order;
		}

		/**
		 * The gateway, with PayPal's API answered from $this->paypal.
		 *
		 * @return Paypal
		 */
		private function gateway(): Paypal {
			$gateway = $this->getMockBuilder( Paypal::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'get_paypal_subscription', 'get_paypal_order' ] )
				->getMock();

			$lookup = function ( string $id ) {
				$this->looked_up[] = $id;
				return $this->paypal[ $id ] ?? null;
			};

			$gateway->method( 'get_paypal_subscription' )->willReturnCallback( $lookup );
			$gateway->method( 'get_paypal_order' )->willReturnCallback( $lookup );
			$gateway->id = 'wp_subscription_paypal';

			return $gateway;
		}

		/**
		 * The order's own subscription, approved, completes the order.
		 */
		public function test_completes_on_the_orders_own_subscription(): void {
			$order                          = $this->order();
			$this->paypal[ self::STORED_ID ] = (object) [ 'status' => 'ACTIVE' ];
			$_GET['subscription_id']        = self::STORED_ID;

			$this->gateway()->order_received_page( self::ORDER_ID );

			$this->assertSame( 'completed', $order->status );
		}

		/**
		 * A subscription id in the URL that is not the order's is ignored.
		 */
		public function test_ignores_another_subscription_in_the_url(): void {
			$order                           = $this->order();
			$this->paypal[ self::STORED_ID ] = (object) [ 'status' => 'APPROVAL_PENDING' ];
			$this->paypal['I-OTHER0002']     = (object) [
				'status'    => 'ACTIVE',
				'custom_id' => '999',
			];
			$_GET['subscription_id']         = 'I-OTHER0002';

			$this->gateway()->order_received_page( self::ORDER_ID );

			$this->assertSame( 'pending', $order->status );
			$this->assertSame( self::STORED_ID, $order->meta['_wp_subs_paypal_live_subscription_id'] );
		}

		/**
		 * A subscription PayPal says was made for this order is accepted.
		 *
		 * @return array
		 */
		public static function own_custom_ids(): array {
			return [
				'order id'  => [ (string) self::ORDER_ID ],
				'order key' => [ self::ORDER_KEY ],
			];
		}

		/**
		 * A different id is accepted when PayPal ties it to this order.
		 *
		 * @dataProvider own_custom_ids
		 *
		 * @param string $custom_id custom_id PayPal returns.
		 */
		public function test_accepts_a_subscription_paypal_ties_to_the_order( string $custom_id ): void {
			$order                       = $this->order();
			$this->paypal['I-OTHER0002'] = (object) [
				'status'    => 'ACTIVE',
				'custom_id' => $custom_id,
			];
			$_GET['subscription_id']     = 'I-OTHER0002';

			$this->gateway()->order_received_page( self::ORDER_ID );

			$this->assertSame( 'completed', $order->status );
		}

		/**
		 * A checkout token for a PayPal order made for something else is ignored.
		 */
		public function test_ignores_a_token_for_another_paypal_order(): void {
			$order                       = $this->order();
			$this->paypal['5O190127TN3'] = (object) [
				'status'         => 'APPROVED',
				'purchase_units' => [ (object) [ 'custom_id' => '999' ] ],
			];
			$_GET['token']               = '5O190127TN3';

			$this->gateway()->order_received_page( self::ORDER_ID );

			$this->assertSame( 'pending', $order->status );
		}

		/**
		 * An id that is not a PayPal id never reaches the API URL.
		 */
		public function test_malformed_ids_never_reach_paypal(): void {
			$this->order();
			$_GET['subscription_id'] = 'I-X/../../v1/other';
			$_GET['token']           = '../v2/x';

			$this->gateway()->order_received_page( self::ORDER_ID );

			$this->assertNotContains( 'I-X/../../v1/other', $this->looked_up );
			$this->assertNotContains( '../v2/x', $this->looked_up );
		}
	}
}
