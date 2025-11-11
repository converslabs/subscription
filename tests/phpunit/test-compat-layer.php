<?php
/**
 * Plugin Name - Compatibility Layer Tests
 *
 * @package   WPSubscription\Tests
 * @copyright Copyright (c)
 * @license   GPL-2.0+
 * @since     1.0.0
 */

/**
 * Compatibility layer expectations derived from WooCommerce Subscriptions.
 *
 * @group compat
 */
class WPSubscription_Compat_Layer_Tests extends WP_UnitTestCase {

	/**
	 * Ensure WooCommerce Subscriptions facade class is available.
	 */
	public function test_wc_subscription_facade_class_exists() {
		$this->assertTrue(
			class_exists( 'WC_Subscription' ),
			'Expected WC_Subscription facade class to exist for compatibility.'
		);
	}

	/**
	 * Ensure core helper function resolves subscriptions for a user.
	 */
	public function test_wcs_get_users_subscriptions_function_exists() {
		$this->assertTrue(
			function_exists( 'wcs_get_users_subscriptions' ),
			'Expected wcs_get_users_subscriptions() helper to exist.'
		);
	}

	/**
	 * Ensure subscriptions are returned as WC_Subscription facades.
	 */
	public function test_wcs_get_users_subscriptions_returns_facade_instances() {
		$user_id = self::factory()->user->create();

		$subscription_id = wp_insert_post(
			array(
				'post_author' => $user_id,
				'post_type'   => 'subscrpt_order',
				'post_status' => 'active',
				'post_title'  => 'Test Subscription',
			)
		);

		update_post_meta( $subscription_id, '_subscrpt_billing_period', 'month' );
		update_post_meta( $subscription_id, '_subscrpt_billing_interval', 1 );
		update_post_meta( $subscription_id, '_subscrpt_start_date', 1700000000 );
		update_post_meta( $subscription_id, '_subscrpt_next_date', 1700600000 );
		update_post_meta( $subscription_id, '_subscrpt_end_date', 1701200000 );
		update_post_meta( $subscription_id, '_subscrpt_trial_ended', 1700300000 );

		$subscriptions = wcs_get_users_subscriptions( $user_id );

		$this->assertArrayHasKey( $subscription_id, $subscriptions, 'Expected subscription ID key to exist.' );

		$subscription = $subscriptions[ $subscription_id ];

		$this->assertInstanceOf( 'WC_Subscription', $subscription, 'Expected WC_Subscription facade instance.' );
		$this->assertSame( 'wc-active', $subscription->get_status(), 'Expected subscription status to map to Woo status.' );
		$this->assertSame( 'month', $subscription->get_meta( '_billing_period' ), 'Expected Woo billing period meta alias.' );
		$this->assertSame( 1700600000, $subscription->get_meta( '_schedule_next_payment' ), 'Expected schedule next payment meta alias.' );
	}

	/**
	 * Ensure subscription filtering by status works with Woo status slugs.
	 */
	public function test_wcs_get_users_subscriptions_filters_by_status() {
		$user_id = self::factory()->user->create();

		$active_id = wp_insert_post(
			array(
				'post_author' => $user_id,
				'post_type'   => 'subscrpt_order',
				'post_status' => 'active',
				'post_title'  => 'Active Subscription',
			)
		);

		$cancelled_id = wp_insert_post(
			array(
				'post_author' => $user_id,
				'post_type'   => 'subscrpt_order',
				'post_status' => 'cancelled',
				'post_title'  => 'Cancelled Subscription',
			)
		);

		$subscriptions = wcs_get_users_subscriptions(
			$user_id,
			array(
				'status' => array( 'wc-active' ),
			)
		);

		$this->assertArrayHasKey( $active_id, $subscriptions, 'Expected active subscription present when filtering.' );
		$this->assertArrayNotHasKey( $cancelled_id, $subscriptions, 'Cancelled subscription should be filtered out.' );
	}

	/**
	 * Ensure subscription filtering by product matches Woo helper expectations.
	 */
	public function test_wcs_get_users_subscriptions_filters_by_product_id() {
		$user_id = self::factory()->user->create();

		$product_one = self::factory()->post->create(
			array(
				'post_type' => 'product',
			)
		);

		$product_two = self::factory()->post->create(
			array(
				'post_type' => 'product',
			)
		);

		$subscription_one = wp_insert_post(
			array(
				'post_author' => $user_id,
				'post_type'   => 'subscrpt_order',
				'post_status' => 'active',
				'post_title'  => 'Product One Subscription',
			)
		);
		update_post_meta( $subscription_one, '_subscrpt_product_id', $product_one );

		$subscription_two = wp_insert_post(
			array(
				'post_author' => $user_id,
				'post_type'   => 'subscrpt_order',
				'post_status' => 'active',
				'post_title'  => 'Product Two Subscription',
			)
		);
		update_post_meta( $subscription_two, '_subscrpt_product_id', $product_two );

		$subscriptions = wcs_get_users_subscriptions(
			$user_id,
			array(
				'product_id' => $product_one,
			)
		);

		$this->assertArrayHasKey( $subscription_one, $subscriptions, 'Expected subscription for product one to be returned.' );
		$this->assertArrayNotHasKey( $subscription_two, $subscriptions, 'Subscription for other product should not be returned.' );
	}

	/**
	 * Ensure compatibility hook is registered for scheduled payments.
	 */
	public function test_gateway_scheduled_payment_hook_registered() {
		$hook_name = 'woocommerce_scheduled_subscription_payment_stripe';

		$this->assertNotFalse(
			has_action( $hook_name ),
			"Expected action {$hook_name} to be registered for compatibility."
		);
	}

	/**
	 * Ensure status change hooks are registered.
	 */
	public function test_subscription_status_changed_hook_registered() {
		$this->assertNotFalse(
			has_action( 'woocommerce_subscription_status_changed' ),
			'Expected subscription status changed hook to be registered.'
		);
	}

	/**
	 * Ensure status changes bridge to internal actions.
	 */
	public function test_subscription_status_changed_hook_bridges() {
		$triggered = false;
		$payload   = array();

		add_action(
			'wps_wcs_subscription_status_changed',
			function ( $subscription, $new_status, $old_status ) use ( &$triggered, &$payload ) {
				$triggered = true;
				$payload   = array( $subscription, $new_status, $old_status );
			},
			10,
			3
		);

		$subscription = new WC_Subscription(
			array(
				'id'     => 321,
				'status' => 'wc-on-hold',
			)
		);

		do_action( 'woocommerce_subscription_status_changed', $subscription, 'wc-active', 'wc-on-hold' );

		remove_all_actions( 'wps_wcs_subscription_status_changed' );

		$this->assertTrue( $triggered, 'Expected internal action to fire on status change.' );
		$this->assertSame( $subscription, $payload[0], 'Expected subscription instance to be forwarded.' );
		$this->assertSame( 'wc-active', $payload[1], 'Expected new status forwarded.' );
		$this->assertSame( 'wc-on-hold', $payload[2], 'Expected old status forwarded.' );
	}

	/**
	 * Ensure cancellation hook bridges to internal actions.
	 */
	public function test_subscription_cancelled_hook_bridges() {
		$triggered = false;
		$received  = null;

		add_action(
			'wps_wcs_subscription_cancelled',
			function ( $subscription ) use ( &$triggered, &$received ) {
				$triggered = true;
				$received  = $subscription;
			},
			10,
			1
		);

		$subscription = new WC_Subscription(
			array(
				'id'     => 654,
				'status' => 'wc-active',
			)
		);

		do_action( 'woocommerce_subscription_cancelled', $subscription );

		remove_all_actions( 'wps_wcs_subscription_cancelled' );

		$this->assertTrue( $triggered, 'Expected internal cancellation action to fire.' );
		$this->assertSame( $subscription, $received, 'Expected subscription instance to be forwarded on cancellation.' );
	}

	/**
	 * Ensure renewal payment complete hook is registered and bridged.
	 */
	public function test_subscription_renewal_payment_complete_hook_bridges() {
		$this->assertNotFalse(
			has_action( 'woocommerce_subscription_renewal_payment_complete' ),
			'Expected renewal payment complete hook to be registered.'
		);

		$triggered    = false;
		$subscription = new WC_Subscription( array( 'id' => 777 ) );
		$order        = new stdClass();

		add_action(
			'wps_wcs_subscription_renewal_payment_complete',
			function ( $sub, $renewal_order ) use ( &$triggered, $subscription, $order ) {
				$triggered = ( $sub === $subscription && $renewal_order === $order );
			},
			10,
			2
		);

		do_action( 'woocommerce_subscription_renewal_payment_complete', $subscription, $order );

		remove_all_actions( 'wps_wcs_subscription_renewal_payment_complete' );

		$this->assertTrue( $triggered, 'Expected renewal payment complete bridge to fire internal action.' );
	}

	/**
	 * Ensure renewal payment failed hook is bridged.
	 */
	public function test_subscription_renewal_payment_failed_hook_bridges() {
		$this->assertNotFalse(
			has_action( 'woocommerce_subscription_renewal_payment_failed' ),
			'Expected renewal payment failed hook to be registered.'
		);

		$triggered    = false;
		$subscription = new WC_Subscription( array( 'id' => 888 ) );
		$order        = new stdClass();

		add_action(
			'wps_wcs_subscription_renewal_payment_failed',
			function ( $sub, $renewal_order ) use ( &$triggered, $subscription, $order ) {
				$triggered = ( $sub === $subscription && $renewal_order === $order );
			},
			10,
			2
		);

		do_action( 'woocommerce_subscription_renewal_payment_failed', $subscription, $order );

		remove_all_actions( 'wps_wcs_subscription_renewal_payment_failed' );

		$this->assertTrue( $triggered, 'Expected renewal payment failed bridge to fire internal action.' );
	}

	/**
	 * Ensure generic payment failed hook is bridged.
	 */
	public function test_subscription_payment_failed_hook_bridges() {
		$this->assertNotFalse(
			has_action( 'woocommerce_subscription_payment_failed' ),
			'Expected subscription payment failed hook to be registered.'
		);

		$triggered    = false;
		$subscription = new WC_Subscription( array( 'id' => 999 ) );

		add_action(
			'wps_wcs_subscription_payment_failed',
			function ( $sub ) use ( &$triggered, $subscription ) {
				$triggered = ( $sub === $subscription );
			},
			10,
			1
		);

		do_action( 'woocommerce_subscription_payment_failed', $subscription );

		remove_all_actions( 'wps_wcs_subscription_payment_failed' );

		$this->assertTrue( $triggered, 'Expected subscription payment failed bridge to fire internal action.' );
	}

	/**
	 * Ensure dual-write creates mirrored shop_subscription records.
	 */
	public function test_subscrpt_order_creates_shop_subscription_mirror() {
		$service = \SpringDevs\Subscription\Compat\WooSubscriptions\Data\Sync_Service::instance();

		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$subscription_id = wp_insert_post(
			array(
				'post_author' => $user_id,
				'post_type'   => 'subscrpt_order',
				'post_status' => 'active',
				'post_title'  => 'Sync Subscription',
			)
		);

		update_post_meta( $subscription_id, '_subscrpt_billing_period', 'month' );
		update_post_meta( $subscription_id, '_subscrpt_billing_interval', 1 );
		update_post_meta( $subscription_id, '_subscrpt_start_date', 1700000000 );
		update_post_meta( $subscription_id, '_subscrpt_next_date', 1700600000 );

		$service->sync_subscription( $subscription_id );

		$wcs_id = (int) get_post_meta( $subscription_id, \SpringDevs\Subscription\Compat\WooSubscriptions\Data\Sync_Service::MAP_META_KEY, true );

		$this->assertGreaterThan( 0, $wcs_id, 'Expected mirrored shop_subscription to be created.' );

		$wcs_post = get_post( $wcs_id );

		$this->assertSame( 'shop_subscription', $wcs_post->post_type );
		$this->assertSame( 'wc-active', get_post_status( $wcs_post ) );
		$this->assertSame( 'month', get_post_meta( $wcs_id, '_billing_period', true ) );
	}

	/**
	 * Ensure status changes propagate to mirrored shop_subscription posts.
	 */
	public function test_mirror_updates_status_on_original_change() {
		$service = \SpringDevs\Subscription\Compat\WooSubscriptions\Data\Sync_Service::instance();

		$user_id         = self::factory()->user->create();
		$subscription_id = wp_insert_post(
			array(
				'post_author' => $user_id,
				'post_type'   => 'subscrpt_order',
				'post_status' => 'active',
				'post_title'  => 'Status Sync',
			)
		);

		$service->sync_subscription( $subscription_id );

		wp_update_post(
			array(
				'ID'          => $subscription_id,
				'post_status' => 'cancelled',
			)
		);

		$service->sync_subscription( $subscription_id );

		$wcs_id = (int) get_post_meta( $subscription_id, \SpringDevs\Subscription\Compat\WooSubscriptions\Data\Sync_Service::MAP_META_KEY, true );
		$this->assertGreaterThan( 0, $wcs_id );

		$this->assertSame( 'wc-cancelled', get_post_status( $wcs_id ) );
	}

	/**
	 * Ensure reconciliation recreates missing mirrors.
	 */
	public function test_reconciliation_backfills_missing_mirror() {
		$service = \SpringDevs\Subscription\Compat\WooSubscriptions\Data\Sync_Service::instance();

		$user_id         = self::factory()->user->create();
		$subscription_id = wp_insert_post(
			array(
				'post_author' => $user_id,
				'post_type'   => 'subscrpt_order',
				'post_status' => 'active',
				'post_title'  => 'Reconcile Sync',
			)
		);

		$service->sync_subscription( $subscription_id );

		$wcs_id = (int) get_post_meta( $subscription_id, \SpringDevs\Subscription\Compat\WooSubscriptions\Data\Sync_Service::MAP_META_KEY, true );
		$this->assertGreaterThan( 0, $wcs_id );

		// Remove mirror to simulate drift.
		wp_delete_post( $wcs_id, true );
		delete_post_meta( $subscription_id, \SpringDevs\Subscription\Compat\WooSubscriptions\Data\Sync_Service::MAP_META_KEY );

		do_action( \SpringDevs\Subscription\Compat\WooSubscriptions\Data\Sync_Service::CRON_HOOK );

		$new_wcs_id = (int) get_post_meta( $subscription_id, \SpringDevs\Subscription\Compat\WooSubscriptions\Data\Sync_Service::MAP_META_KEY, true );

		$this->assertGreaterThan( 0, $new_wcs_id, 'Expected reconciliation to recreate mirror.' );
		$this->assertNotSame( $wcs_id, $new_wcs_id, 'Recreated mirror should differ from deleted one.' );
	}
}
