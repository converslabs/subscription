<?php
/**
 * Tests for claiming the retention offer from the cancellation modal.
 *
 * @package SpringDevs\Subscription
 */

namespace {
	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}

	if ( ! function_exists( 'wp_unslash' ) ) {
		function wp_unslash( $value ) {
			return is_string( $value ) ? stripslashes( $value ) : $value;
		}
	}

	if ( ! function_exists( 'check_ajax_referer' ) ) {
		function check_ajax_referer( $action = -1, $query_arg = false, $stop = true ) {
			return 1;
		}
	}

	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $post_id ) {
			return $GLOBALS['wp_posts'][ (int) $post_id ] ?? null;
		}
	}

	if ( ! function_exists( 'get_post_status' ) ) {
		function get_post_status( $post_id ) {
			$post = $GLOBALS['wp_posts'][ (int) $post_id ] ?? null;
			return $post ? $post->post_status : false;
		}
	}

	if ( ! function_exists( 'get_current_user_id' ) ) {
		function get_current_user_id() {
			return (int) ( $GLOBALS['wp_current_user_id'] ?? 0 );
		}
	}

	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can( $capability, ...$args ) {
			return in_array( $capability, $GLOBALS['wp_current_user_caps'] ?? [], true );
		}
	}

	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ) {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
		}
	}

	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( $key, $value, $expiration = 0 ) {
			$GLOBALS['wp_transients'][ $key ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'delete_transient' ) ) {
		function delete_transient( $key ) {
			unset( $GLOBALS['wp_transients'][ $key ] );
			return true;
		}
	}

	if ( ! function_exists( 'wp_send_json_success' ) ) {
		/**
		 * Record the response, then stop the request the way `wp_die` would.
		 *
		 * @throws \RuntimeException Always.
		 */
		function wp_send_json_success( $data = null, $status_code = null ) {
			$GLOBALS['wp_json_responses'][] = [ true, $data ];
			throw new \RuntimeException( 'wp_send_json' );
		}
	}

	if ( ! function_exists( 'wp_send_json_error' ) ) {
		/**
		 * Record the response, then stop the request the way `wp_die` would.
		 *
		 * @throws \RuntimeException Always.
		 */
		function wp_send_json_error( $data = null, $status_code = null ) {
			$GLOBALS['wp_json_responses'][] = [ false, $data ];
			throw new \RuntimeException( 'wp_send_json' );
		}
	}
}

namespace SpringDevs\Subscription\Tests\Unit {

	use PHPUnit\Framework\TestCase;
	use SpringDevs\Subscription\Illuminate\Cancellation;

	/**
	 * A retention offer is claimed once per subscription, and only while the
	 * subscription is one the customer could still keep.
	 */
	class CancellationOfferClaimTest extends TestCase {

		/**
		 * Subscription id.
		 */
		const SUB_ID = 73;

		/**
		 * Owner.
		 */
		const OWNER = 9;

		/**
		 * How many offers the listener issued.
		 *
		 * @var int
		 */
		private $issued = 0;

		/**
		 * A signed-in owner and a listener that issues a fresh code every call.
		 */
		protected function setUp(): void {
			$GLOBALS['wp_post_meta']         = [];
			$GLOBALS['wp_options']           = [];
			$GLOBALS['wp_posts']             = [];
			$GLOBALS['wp_transients']        = [];
			$GLOBALS['wp_json_responses']    = [];
			$GLOBALS['applied_actions']      = [];
			$GLOBALS['wp_current_user_id']   = self::OWNER;
			$GLOBALS['wp_current_user_caps'] = [];
			$this->issued                    = 0;

			unset( $GLOBALS['wp_hooks_registry']['subscrpt_cancellation_offer'] );

			add_filter(
				'subscrpt_cancellation_offer',
				function () {
					++$this->issued;
					return [ 'code' => 'SAVE' . $this->issued ];
				}
			);
		}

		/**
		 * Clear the request and listeners.
		 */
		protected function tearDown(): void {
			$_POST = [];
			unset( $GLOBALS['wp_hooks_registry']['subscrpt_cancellation_offer'] );
		}

		/**
		 * Give the owner a subscription in a status.
		 *
		 * @param string $status Post status.
		 */
		private function subscription( string $status ): void {
			$GLOBALS['wp_posts'][ self::SUB_ID ] = (object) [
				'ID'          => self::SUB_ID,
				'post_author' => self::OWNER,
				'post_status' => $status,
				'post_type'   => 'subscrpt_order',
			];
		}

		/**
		 * Claim the offer once and return the JSON response.
		 *
		 * @return array [ success, data ].
		 */
		private function claim(): array {
			$_POST = [
				'nonce'           => 'nonce',
				'subscription_id' => (string) self::SUB_ID,
			];

			try {
				( new Cancellation() )->claim_offer();
			} catch ( \RuntimeException $sent ) {
				$this->assertSame( 'wp_send_json', $sent->getMessage() );
			}

			return end( $GLOBALS['wp_json_responses'] );
		}

		/**
		 * The first claim on an active subscription returns the offer.
		 */
		public function test_first_claim_returns_the_offer(): void {
			$this->subscription( 'active' );

			list( $success, $data ) = $this->claim();

			$this->assertTrue( $success );
			$this->assertSame( 'SAVE1', $data['code'] );
		}

		/**
		 * A second claim on the same subscription issues nothing new.
		 */
		public function test_second_claim_is_refused(): void {
			$this->subscription( 'active' );

			$this->claim();
			list( $success ) = $this->claim();

			$this->assertFalse( $success );
			$this->assertSame( 1, $this->issued );
		}

		/**
		 * A subscription already on its way out may still be saved.
		 */
		public function test_pending_cancellation_may_claim(): void {
			$this->subscription( 'pe_cancelled' );

			list( $success ) = $this->claim();

			$this->assertTrue( $success );
		}

		/**
		 * Statuses with nothing left to keep.
		 *
		 * @return array
		 */
		public static function not_keepable(): array {
			return [
				'cancelled' => [ 'cancelled' ],
				'expired'   => [ 'expired' ],
				'completed' => [ 'completed' ],
				'pending'   => [ 'pending' ],
			];
		}

		/**
		 * No offer is issued for a subscription that is not active.
		 *
		 * @dataProvider not_keepable
		 *
		 * @param string $status Status.
		 */
		public function test_no_offer_unless_active( string $status ): void {
			$this->subscription( $status );

			list( $success ) = $this->claim();

			$this->assertFalse( $success );
			$this->assertSame( 0, $this->issued );
		}

		/**
		 * Someone else's subscription is still refused.
		 */
		public function test_other_customers_subscription_is_refused(): void {
			$this->subscription( 'active' );
			$GLOBALS['wp_current_user_id'] = self::OWNER + 1;

			list( $success ) = $this->claim();

			$this->assertFalse( $success );
			$this->assertSame( 0, $this->issued );
		}
	}
}
