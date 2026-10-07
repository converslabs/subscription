<?php
/**
 * Tests for cloning Stripe metadata onto a renewal without the Stripe plugin.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Free's Stripe class extends WooCommerce Stripe's gateway, so reading its
 * supported-method list loads a class whose parent may not exist. A store
 * without the Stripe plugin renews by other gateways and must not fatal here.
 */
class StripeRenewalCloneTest extends TestCase {

	/**
	 * Start with no meta or options.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_post_meta'] = [];
		$GLOBALS['wp_options']   = [];
	}

	/**
	 * An order paid by cash on delivery, recording the meta written to it.
	 *
	 * @param int $id Order id.
	 *
	 * @return object
	 */
	private function order( int $id ) {
		return new class( $id ) {
			/**
			 * Order id.
			 *
			 * @var int
			 */
			private $id;

			/**
			 * Meta written to the order.
			 *
			 * @var array
			 */
			public $written = [];

			/**
			 * Payment method set on the order.
			 *
			 * @var string
			 */
			public $method = 'cod';

			/**
			 * Remember the id.
			 *
			 * @param int $id Order id.
			 */
			public function __construct( int $id ) {
				$this->id = $id;
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
			 * Payment method.
			 *
			 * @return string
			 */
			public function get_payment_method() {
				return $this->method;
			}

			/**
			 * Payment method title.
			 *
			 * @return string
			 */
			public function get_payment_method_title() {
				return 'Cash on delivery';
			}

			/**
			 * Meta value, always empty.
			 *
			 * @param string $key Meta key.
			 *
			 * @return string
			 */
			public function get_meta( $key ) {
				return '';
			}

			/**
			 * Record a meta write.
			 *
			 * @param string $key   Meta key.
			 * @param mixed  $value Value.
			 */
			public function update_meta_data( $key, $value ) {
				$this->written[ $key ] = $value;
			}

			/**
			 * Record the payment method.
			 *
			 * @param string $method Method id.
			 */
			public function set_payment_method( $method ) {
				$this->method = $method;
			}

			/**
			 * Ignore the title.
			 *
			 * @param string $title Title.
			 */
			public function set_payment_method_title( $title ) {}
		};
	}

	/**
	 * A cash-on-delivery renewal is cloned without the Stripe plugin loaded.
	 */
	public function test_clone_does_not_need_the_stripe_plugin(): void {
		$this->assertFalse( class_exists( 'WC_Stripe_Payment_Gateway', false ), 'the Stripe plugin is not loaded in this run' );

		$old = $this->order( 11 );
		$new = $this->order( 12 );

		$error = '';

		try {
			Helper::clone_stripe_metadata_for_renewal( 10, $old, $new );
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
		}

		$this->assertSame( '', $error, 'cloning the renewal metadata threw' );
		$this->assertSame( [], $new->written, 'nothing Stripe is written to a cash-on-delivery renewal' );
		$this->assertSame( 'cod', $new->method );
	}

	/**
	 * The auto-renew flag is still settled first, as it was before the guard.
	 */
	public function test_auto_renew_flag_is_still_set(): void {
		Helper::clone_stripe_metadata_for_renewal( 20, $this->order( 21 ), $this->order( 22 ) );

		$this->assertTrue( (bool) ( $GLOBALS['wp_post_meta'][20]['_subscrpt_auto_renew'] ?? false ), 'auto renewal store-wide marks the subscription' );
	}
}
