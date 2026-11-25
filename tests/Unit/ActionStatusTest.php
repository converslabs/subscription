<?php
/**
 * Tests for the statuses each My Account subscription action accepts.
 *
 * @package SpringDevs\Subscription
 */

namespace {
	// Request and redirect stubs. Each is guarded and behaves the same in every
	// test file that declares it, so load order does not matter.
	if ( ! function_exists( 'wp_unslash' ) ) {
		function wp_unslash( $value ) {
			return is_string( $value ) ? stripslashes( $value ) : $value;
		}
	}

	if ( ! function_exists( 'wp_verify_nonce' ) ) {
		function wp_verify_nonce( $nonce, $action = -1 ) {
			return $GLOBALS['wp_nonce_valid'] ?? true;
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

	if ( ! function_exists( 'get_post_type' ) ) {
		function get_post_type( $post_id ) {
			$post = $GLOBALS['wp_posts'][ (int) $post_id ] ?? null;
			return $post ? $post->post_type : false;
		}
	}

	if ( ! function_exists( 'wp_update_post' ) ) {
		function wp_update_post( $postarr ) {
			$GLOBALS['wp_updated_posts'][] = $postarr;
			$post                          = $GLOBALS['wp_posts'][ (int) $postarr['ID'] ] ?? null;
			if ( $post && isset( $postarr['post_status'] ) ) {
				$post->post_status = $postarr['post_status'];
			}
			return (int) $postarr['ID'];
		}
	}

	if ( ! function_exists( 'wp_insert_comment' ) ) {
		function wp_insert_comment( $data ) {
			$GLOBALS['wp_comments'][] = $data;
			return count( $GLOBALS['wp_comments'] );
		}
	}

	if ( ! function_exists( 'update_comment_meta' ) ) {
		function update_comment_meta( $comment_id, $key, $value ) {
			return true;
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

	if ( ! function_exists( 'WC' ) ) {
		/**
		 * Just enough of WooCommerce for status changes that build the mailer.
		 */
		function WC() {
			return new class() {
				public function mailer() {
					return null;
				}
			};
		}
	}

	if ( ! function_exists( 'wc_add_notice' ) ) {
		function wc_add_notice( $message, $type = 'success' ) {
			$GLOBALS['wc_notices'][] = [ $type, $message ];
		}
	}

	if ( ! function_exists( 'untrailingslashit' ) ) {
		function untrailingslashit( $value ) {
			return rtrim( (string) $value, '/\\' );
		}
	}

	if ( ! function_exists( 'wc_get_page_permalink' ) ) {
		function wc_get_page_permalink( $page ) {
			return 'https://example.test/' . $page . '/';
		}
	}

	if ( ! function_exists( 'wc_get_endpoint_url' ) ) {
		function wc_get_endpoint_url( $endpoint, $value = '', $permalink = '' ) {
			return $permalink . $endpoint . '/' . $value . '/';
		}
	}

	if ( ! function_exists( 'wp_safe_redirect' ) ) {
		/**
		 * Record the redirect, then stop the request the way `exit` would.
		 *
		 * @throws \RuntimeException Always.
		 */
		function wp_safe_redirect( $location, $status = 302 ) {
			$GLOBALS['wp_redirects'][] = $location;
			throw new \RuntimeException( 'wp_safe_redirect' );
		}
	}
}

namespace SpringDevs\Subscription\Tests\Unit {

	use PHPUnit\Framework\TestCase;
	use SpringDevs\Subscription\Frontend\ActionController;

	/**
	 * Each customer action runs only from the statuses its button is shown for.
	 */
	class ActionStatusTest extends TestCase {

		/**
		 * Subscription id used throughout.
		 */
		const SUB_ID = 41;

		/**
		 * Owner of the subscription.
		 */
		const OWNER = 7;

		/**
		 * A signed-in owner with a valid nonce and nothing recorded yet.
		 */
		protected function setUp(): void {
			$GLOBALS['wp_post_meta']         = [];
			$GLOBALS['wp_options']           = [];
			$GLOBALS['wp_posts']             = [];
			$GLOBALS['wp_updated_posts']     = [];
			$GLOBALS['wp_comments']          = [];
			$GLOBALS['wc_notices']           = [];
			$GLOBALS['wp_redirects']         = [];
			$GLOBALS['applied_actions']      = [];
			$GLOBALS['wp_nonce_valid']       = true;
			$GLOBALS['wp_current_user_id']   = self::OWNER;
			$GLOBALS['wp_current_user_caps'] = [];
		}

		/**
		 * Clear the request.
		 */
		protected function tearDown(): void {
			$_GET = [];
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
		 * Run the controller for an action, as the My Account page does.
		 *
		 * @param string $action Action.
		 */
		private function request( string $action ): void {
			$_GET = [
				'subscrpt_id' => (string) self::SUB_ID,
				'action'      => $action,
				'wpnonce'     => 'nonce',
			];

			try {
				( new ActionController() )->control_action_subscrpt();
			} catch ( \RuntimeException $redirect ) {
				$this->assertSame( 'wp_safe_redirect', $redirect->getMessage() );
			}
		}

		/**
		 * The status the subscription is in now.
		 *
		 * @return string
		 */
		private function current_status(): string {
			return $GLOBALS['wp_posts'][ self::SUB_ID ]->post_status;
		}

		/**
		 * Whether an error notice was shown.
		 *
		 * @return bool
		 */
		private function has_error(): bool {
			foreach ( $GLOBALS['wc_notices'] as list( $type ) ) {
				if ( 'error' === $type ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Statuses the Reactivate button is never shown for.
		 *
		 * @return array
		 */
		public static function not_pending_cancellation(): array {
			return [
				'pending'   => [ 'pending' ],
				'expired'   => [ 'expired' ],
				'cancelled' => [ 'cancelled' ],
				'on hold'   => [ 'on_hold' ],
				'completed' => [ 'completed' ],
			];
		}

		/**
		 * Reactivate only undoes a pending cancellation.
		 *
		 * @dataProvider not_pending_cancellation
		 *
		 * @param string $status Status before the request.
		 */
		public function test_reactivate_leaves_other_statuses_alone( string $status ): void {
			$this->subscription( $status );

			$this->request( 'reactivate' );

			$this->assertSame( $status, $this->current_status() );
			$this->assertSame( [], $GLOBALS['wp_updated_posts'] );
			$this->assertTrue( $this->has_error() );
		}

		/**
		 * Reactivate still works from a pending cancellation.
		 */
		public function test_reactivate_resumes_a_pending_cancellation(): void {
			$this->subscription( 'pe_cancelled' );

			$this->request( 'reactivate' );

			$this->assertSame( 'active', $this->current_status() );
			$this->assertFalse( $this->has_error() );
		}

		/**
		 * Cancel applies only to a subscription that is still running.
		 *
		 * @return array
		 */
		public static function already_ended(): array {
			return [
				'expired'   => [ 'expired' ],
				'cancelled' => [ 'cancelled' ],
				'completed' => [ 'completed' ],
			];
		}

		/**
		 * Cancel leaves an ended subscription as it is.
		 *
		 * @dataProvider already_ended
		 *
		 * @param string $status Status before the request.
		 */
		public function test_cancel_leaves_ended_subscriptions_alone( string $status ): void {
			$this->subscription( $status );

			$this->request( 'cancelled' );

			$this->assertSame( $status, $this->current_status() );
			$this->assertSame( [], $GLOBALS['wp_updated_posts'] );
		}

		/**
		 * Cancel on an active subscription still schedules the cancellation.
		 */
		public function test_cancel_on_active_schedules_cancellation(): void {
			$this->subscription( 'active' );

			$this->request( 'cancelled' );

			$this->assertSame( 'pe_cancelled', $this->current_status() );
		}

		/**
		 * The auto-renewal toggle does nothing once nothing renews.
		 *
		 * @return array
		 */
		public static function toggles_on_ended(): array {
			return [
				'on, expired'    => [ 'renew-on', 'expired' ],
				'on, cancelled'  => [ 'renew-on', 'cancelled' ],
				'off, expired'   => [ 'renew-off', 'expired' ],
				'off, cancelled' => [ 'renew-off', 'cancelled' ],
			];
		}

		/**
		 * The auto-renewal toggle leaves an ended subscription's setting alone.
		 *
		 * @dataProvider toggles_on_ended
		 *
		 * @param string $action Toggle action.
		 * @param string $status Status before the request.
		 */
		public function test_auto_renew_toggle_ignores_ended_subscriptions( string $action, string $status ): void {
			$this->subscription( $status );
			update_post_meta( self::SUB_ID, '_subscrpt_auto_renew', 'unchanged' );

			$this->request( $action );

			$this->assertSame( 'unchanged', get_post_meta( self::SUB_ID, '_subscrpt_auto_renew', true ) );
		}

		/**
		 * The auto-renewal toggle still works on an active subscription.
		 */
		public function test_auto_renew_toggle_on_active(): void {
			$this->subscription( 'active' );

			$this->request( 'renew-off' );

			$this->assertSame( 0, get_post_meta( self::SUB_ID, '_subscrpt_auto_renew', true ) );
		}

		/**
		 * The allow-list covers every action the controller handles itself.
		 */
		public function test_allowed_statuses_per_action(): void {
			$this->assertSame( [ 'pe_cancelled' ], ActionController::allowed_statuses( 'reactivate' ) );
			$this->assertSame( [ 'expired' ], ActionController::allowed_statuses( 'renew' ) );
			$this->assertContains( 'active', ActionController::allowed_statuses( 'cancelled' ) );
			$this->assertNotContains( 'expired', ActionController::allowed_statuses( 'renew-on' ) );
			$this->assertNull( ActionController::allowed_statuses( 'box-skip' ) );
		}
	}
}
