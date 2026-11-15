<?php
/**
 * Tests for the subscription checks on the My Account action and view handlers.
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
	use SpringDevs\Subscription\Frontend\MyAccount;

	/**
	 * The My Account handlers act only on subscriptions, and pass on only actions
	 * someone registered.
	 */
	class AccountRequestChecksTest extends TestCase {

		/**
		 * Post id used throughout.
		 */
		const POST_ID = 52;

		/**
		 * Owner of the post.
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

			unset( $GLOBALS['wp_hooks_registry']['subscrpt_customer_actions'] );

			$GLOBALS['wpdb'] = new class() {
				/**
				 * Table prefix.
				 *
				 * @var string
				 */
				public $prefix = 'wp_';

				/**
				 * Queries run.
				 *
				 * @var array
				 */
				public $queries = [];

				/**
				 * Keep the arguments.
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
				 * Record the query and find nothing.
				 *
				 * @param mixed $prepared Prepared query.
				 *
				 * @return array
				 */
				public function get_results( $prepared ) {
					$this->queries[] = $prepared;
					return [];
				}
			};
		}

		/**
		 * Clear the request and listeners.
		 */
		protected function tearDown(): void {
			$_GET = [];
			unset( $GLOBALS['wp_hooks_registry']['subscrpt_customer_actions'] );
		}

		/**
		 * Give the owner a post.
		 *
		 * @param string $type   Post type.
		 * @param string $status Post status.
		 */
		private function post( string $type, string $status = 'pe_cancelled' ): void {
			$GLOBALS['wp_posts'][ self::POST_ID ] = (object) [
				'ID'          => self::POST_ID,
				'post_author' => self::OWNER,
				'post_status' => $status,
				'post_type'   => $type,
			];
		}

		/**
		 * Run the action controller, as the My Account page does.
		 *
		 * @param string $action Action.
		 */
		private function request( string $action ): void {
			$_GET = [
				'subscrpt_id' => (string) self::POST_ID,
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
		 * The actions passed on to listeners, with their arguments.
		 *
		 * @return array
		 */
		private function forwarded(): array {
			return $GLOBALS['applied_actions']['subscrpt_execute_actions'] ?? [];
		}

		/**
		 * An action on a post that is not a subscription changes nothing.
		 */
		public function test_action_on_another_post_type_is_refused(): void {
			$this->post( 'shop_order' );

			$this->request( 'reactivate' );

			$this->assertSame( 'pe_cancelled', $GLOBALS['wp_posts'][ self::POST_ID ]->post_status );
			$this->assertSame( [], $GLOBALS['wp_updated_posts'] );
		}

		/**
		 * An action nobody registered is not passed on.
		 */
		public function test_unknown_action_is_not_forwarded(): void {
			$this->post( 'subscrpt_order', 'active' );

			$this->request( 'anything-else' );

			$this->assertSame( [], $this->forwarded() );
		}

		/**
		 * The actions add-ons handle today are still passed on.
		 *
		 * @return array
		 */
		public static function registered_actions(): array {
			return [
				'early renew'      => [ 'early-renew' ],
				'cancel downgrade' => [ 'cancel-downgrade' ],
				'box skip'         => [ 'box-skip' ],
			];
		}

		/**
		 * A registered action reaches its listeners.
		 *
		 * @dataProvider registered_actions
		 *
		 * @param string $action Action.
		 */
		public function test_registered_action_is_forwarded( string $action ): void {
			$this->post( 'subscrpt_order', 'active' );

			$this->request( $action );

			$this->assertSame( [ [ self::POST_ID, $action ] ], array_map( static fn( $args ) => [ (int) $args[0], $args[1] ], $this->forwarded() ) );
		}

		/**
		 * An add-on can register its own action.
		 */
		public function test_filter_registers_an_action(): void {
			$this->post( 'subscrpt_order', 'active' );
			add_filter(
				'subscrpt_customer_actions',
				static function ( $actions ) {
					$actions[] = 'custom-thing';
					return $actions;
				}
			);

			$this->request( 'custom-thing' );

			$this->assertCount( 1, $this->forwarded() );
		}

		/**
		 * The view page does not load a post that is not a subscription.
		 */
		public function test_view_of_another_post_type_redirects_before_loading(): void {
			$this->post( 'shop_order', 'active' );

			try {
				( new MyAccount() )->view_subscrpt_content( self::POST_ID );
			} catch ( \RuntimeException $redirect ) {
				$this->assertSame( 'wp_safe_redirect', $redirect->getMessage() );
			}

			$this->assertSame( [ '/404' ], $GLOBALS['wp_redirects'] );
			$this->assertSame( [], $GLOBALS['wpdb']->queries );
		}
	}
}
