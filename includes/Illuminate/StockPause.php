<?php

namespace SpringDevs\Subscription\Illuminate;

use WC_Product;

/**
 * Class StockPause
 *
 * Pauses (`on_hold`) active subscriptions whose product has run low on stock and
 * resumes them once stock recovers, so a renewal never charges a customer for
 * something the store cannot ship.
 *
 * Opt-in via the `wp_subscription_stock_pause` setting. Only subscriptions this
 * class paused (marked with `_subscrpt_stock_paused`) are ever resumed here, so a
 * subscription an admin put on hold by hand stays on hold.
 *
 * @package SpringDevs\Subscription\Illuminate
 */
class StockPause {

	/**
	 * Meta set on a subscription paused because of stock.
	 */
	const META_KEY = '_subscrpt_stock_paused';

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'woocommerce_product_set_stock', [ $this, 'on_stock_changed' ] );
		add_action( 'woocommerce_variation_set_stock', [ $this, 'on_stock_changed' ] );
		add_action( 'woocommerce_product_set_stock_status', [ $this, 'on_stock_status_changed' ], 10, 3 );
		add_action( 'woocommerce_variation_set_stock_status', [ $this, 'on_stock_status_changed' ], 10, 3 );
	}

	/**
	 * Whether the feature is switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return in_array( get_option( 'wp_subscription_stock_pause', '' ), [ 1, '1', 'true', 'yes' ], true );
	}

	/**
	 * Stock level at or below which subscriptions pause.
	 *
	 * @return int
	 */
	public static function get_threshold(): int {
		return max( 0, (int) get_option( 'wp_subscription_stock_pause_threshold', 0 ) );
	}

	/**
	 * Stock quantity changed.
	 *
	 * @param WC_Product $product Product (or variation).
	 */
	public function on_stock_changed( $product ) {
		$this->sync( $product );
	}

	/**
	 * Stock status changed.
	 *
	 * @param int        $product_id   Product id.
	 * @param string     $stock_status New stock status.
	 * @param WC_Product $product      Product (or variation).
	 */
	public function on_stock_status_changed( $product_id, $stock_status, $product = null ) {
		$this->sync( $product ? $product : wc_get_product( $product_id ) );
	}

	/**
	 * Whether a product is at or below the pause threshold.
	 *
	 * Products that do not manage a quantity only pause when out of stock.
	 *
	 * @param WC_Product $product Product (or variation).
	 * @return bool
	 */
	public static function is_low_stock( $product ): bool {
		if ( 'outofstock' === $product->get_stock_status() ) {
			return true;
		}

		if ( $product->managing_stock() && null !== $product->get_stock_quantity() ) {
			return (int) $product->get_stock_quantity() <= self::get_threshold();
		}

		return false;
	}

	/**
	 * Pause or resume the subscriptions of a product to match its stock.
	 *
	 * @param WC_Product|false|null $product Product (or variation).
	 */
	public function sync( $product ) {
		if ( ! $product instanceof WC_Product || ! self::is_enabled() ) {
			return;
		}

		if ( self::is_low_stock( $product ) ) {
			$this->pause( $product );
		} elseif ( '1' === get_option( 'wp_subscription_stock_auto_resume', '1' ) ) {
			$this->resume( $product );
		}
	}

	/**
	 * Subscription ids of a product (or variation) in the given statuses.
	 *
	 * @param WC_Product $product  Product (or variation).
	 * @param string[]   $statuses Subscription statuses.
	 * @param array      $extra    Extra meta_query clauses.
	 * @return int[]
	 */
	private function get_subscriptions( WC_Product $product, array $statuses, array $extra = [] ): array {
		$is_variation = $product->is_type( 'variation' );

		$meta_query = [
			[
				'key'   => '_subscrpt_product_id',
				'value' => $is_variation ? $product->get_parent_id() : $product->get_id(),
			],
		];
		if ( $is_variation ) {
			$meta_query[] = [
				'key'   => '_subscrpt_variation_id',
				'value' => $product->get_id(),
			];
		}

		return array_map(
			'intval',
			get_posts(
				[
					'post_type'      => 'subscrpt_order',
					'post_status'    => $statuses,
					'fields'         => 'ids',
					'posts_per_page' => -1,
					'meta_query'     => array_merge( $meta_query, $extra ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				]
			)
		);
	}

	/**
	 * Pause the active subscriptions of a product and tell their customers.
	 *
	 * @param WC_Product $product Product (or variation).
	 */
	private function pause( WC_Product $product ) {
		foreach ( $this->get_subscriptions( $product, [ 'active' ] ) as $subscription_id ) {
			Action::status( 'on_hold', $subscription_id, false );
			update_post_meta( $subscription_id, self::META_KEY, time() );

			$this->add_note(
				$subscription_id,
				__( 'Subscription paused because the product is low on stock.', 'subscription' ),
				'Subscription Paused (Low Stock)',
				'subs_stock_paused'
			);

			if ( function_exists( 'WC' ) && WC()->mailer() ) {
				do_action( 'subscrpt_subscription_stock_paused_email_notification', $subscription_id );
			}

			/**
			 * Fires after a subscription is paused because of low stock.
			 *
			 * @param int        $subscription_id Subscription id.
			 * @param WC_Product $product         Product (or variation) that is low on stock.
			 */
			do_action( 'subscrpt_subscription_stock_paused', $subscription_id, $product );
		}
	}

	/**
	 * Resume the subscriptions this class paused for a product.
	 *
	 * @param WC_Product $product Product (or variation).
	 */
	private function resume( WC_Product $product ) {
		$paused = $this->get_subscriptions(
			$product,
			[ 'on_hold', 'active', 'pending', 'cancelled', 'pe_cancelled', 'expired', 'completed' ],
			[
				[
					'key'     => self::META_KEY,
					'compare' => 'EXISTS',
				],
			]
		);

		foreach ( $paused as $subscription_id ) {
			delete_post_meta( $subscription_id, self::META_KEY );

			// Changed by hand since: nothing to resume.
			if ( 'on_hold' !== get_post_status( $subscription_id ) ) {
				continue;
			}

			Action::status( 'active', $subscription_id, false );

			$this->add_note(
				$subscription_id,
				__( 'Subscription resumed because the product is back in stock.', 'subscription' ),
				'Subscription Resumed (Back In Stock)',
				'subs_stock_resumed'
			);

			/**
			 * Fires after a subscription is resumed because stock recovered.
			 *
			 * @param int        $subscription_id Subscription id.
			 * @param WC_Product $product         Product (or variation) back in stock.
			 */
			do_action( 'subscrpt_subscription_stock_resumed', $subscription_id, $product );
		}
	}

	/**
	 * Write an activity note on a subscription.
	 *
	 * @param int    $subscription_id Subscription id.
	 * @param string $content         Note text.
	 * @param string $activity        Activity label.
	 * @param string $type            Activity type.
	 */
	private function add_note( int $subscription_id, string $content, string $activity, string $type ) {
		$comment_id = wp_insert_comment(
			[
				'comment_author'  => 'Subscription for WooCommerce',
				'comment_content' => $content,
				'comment_post_ID' => $subscription_id,
				'comment_type'    => 'order_note',
			]
		);
		update_comment_meta( $comment_id, '_subscrpt_activity', $activity );
		update_comment_meta( $comment_id, '_subscrpt_activity_type', $type );
	}
}
