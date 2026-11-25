<?php
/**
 * Tests for trash, restore and delete on the admin subscription list.
 *
 * @package SpringDevs\Subscription
 */

namespace {
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

	if ( ! function_exists( 'get_post_type' ) ) {
		function get_post_type( $post_id ) {
			$post = $GLOBALS['wp_posts'][ (int) $post_id ] ?? null;
			return $post ? $post->post_type : false;
		}
	}

	if ( ! function_exists( 'wp_trash_post' ) ) {
		function wp_trash_post( $post_id ) {
			$GLOBALS['wp_post_changes'][] = [ 'trash', (int) $post_id ];
			return true;
		}
	}

	if ( ! function_exists( 'wp_untrash_post' ) ) {
		function wp_untrash_post( $post_id ) {
			$GLOBALS['wp_post_changes'][] = [ 'restore', (int) $post_id ];
			return true;
		}
	}

	if ( ! function_exists( 'wp_delete_post' ) ) {
		function wp_delete_post( $post_id, $force = false ) {
			$GLOBALS['wp_post_changes'][] = [ 'delete', (int) $post_id ];
			return true;
		}
	}

	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( $path = '' ) {
			return 'https://example.test/wp-admin/' . ltrim( (string) $path, '/' );
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
	use SpringDevs\Subscription\Admin\Menu;

	/**
	 * The subscription list trashes, restores and deletes subscriptions only.
	 */
	class AdminListDeleteTest extends TestCase {

		/**
		 * A subscription.
		 */
		const SUBSCRIPTION = 60;

		/**
		 * A page, which is not a subscription.
		 */
		const PAGE = 61;

		/**
		 * One subscription and one page, a valid nonce, nothing changed yet.
		 */
		protected function setUp(): void {
			$GLOBALS['wp_nonce_valid']  = true;
			$GLOBALS['wp_redirects']    = [];
			$GLOBALS['wp_post_changes'] = [];
			$GLOBALS['wp_posts']        = [
				self::SUBSCRIPTION => (object) [
					'ID'        => self::SUBSCRIPTION,
					'post_type' => 'subscrpt_order',
				],
				self::PAGE         => (object) [
					'ID'        => self::PAGE,
					'post_type' => 'page',
				],
			];
		}

		/**
		 * Clear the request.
		 */
		protected function tearDown(): void {
			$_GET    = [];
			$_POST   = [];
			$_SERVER = array_diff_key( $_SERVER, [ 'REQUEST_METHOD' => true ] );
		}

		/**
		 * Render the list page up to its redirect, without its header.
		 */
		private function render(): void {
			$menu = $this->getMockBuilder( Menu::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'render_admin_header' ] )
				->getMock();

			try {
				$menu->render_subscriptions_page();
			} catch ( \RuntimeException $redirect ) {
				$this->assertSame( 'wp_safe_redirect', $redirect->getMessage() );
			}
		}

		/**
		 * Bulk and row actions.
		 *
		 * @return array
		 */
		public static function actions(): array {
			return [
				'trash'   => [ 'trash' ],
				'restore' => [ 'restore' ],
				'delete'  => [ 'delete' ],
			];
		}

		/**
		 * A bulk action skips ids that are not subscriptions.
		 *
		 * @dataProvider actions
		 *
		 * @param string $action Action.
		 */
		public function test_bulk_action_touches_subscriptions_only( string $action ): void {
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST                     = [
				'_wpnonce'         => 'nonce',
				'bulk_action'      => 'Apply',
				'action'           => $action,
				'subscription_ids' => [ (string) self::SUBSCRIPTION, (string) self::PAGE ],
			];

			$this->render();

			$this->assertSame( [ [ $action, self::SUBSCRIPTION ] ], $GLOBALS['wp_post_changes'] );
		}

		/**
		 * A row action on something that is not a subscription does nothing.
		 *
		 * @dataProvider actions
		 *
		 * @param string $action Action.
		 */
		public function test_row_action_touches_subscriptions_only( string $action ): void {
			$_GET = [
				'action'   => $action,
				'sub_id'   => (string) self::PAGE,
				'_wpnonce' => 'nonce',
			];

			$this->render();

			$this->assertSame( [], $GLOBALS['wp_post_changes'] );
		}

		/**
		 * A row action on a subscription still works.
		 */
		public function test_row_action_on_a_subscription(): void {
			$_GET = [
				'action'   => 'trash',
				'sub_id'   => (string) self::SUBSCRIPTION,
				'_wpnonce' => 'nonce',
			];

			$this->render();

			$this->assertSame( [ [ 'trash', self::SUBSCRIPTION ] ], $GLOBALS['wp_post_changes'] );
		}
	}
}
