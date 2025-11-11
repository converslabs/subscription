<?php
/**
 * Plugin Name - WooCommerce Subscriptions Sync Service
 *
 * @package   WPSubscription
 * @since     1.0.0
 */

namespace SpringDevs\Subscription\Compat\WooSubscriptions\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synchronise WPSubscription data with WooCommerce Subscriptions structures.
 *
 * @since 1.0.0
 */
class Sync_Service {

	const MAP_META_KEY      = '_wps_wcs_subscription_id';
	const WCS_MAP_META_KEY  = '_wps_subscription_id';
	const CRON_HOOK         = 'wps_wcs_sync_subscriptions';
	const SYNC_META_TRIGGER = 'wps_wcs_sync_trigger';

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var Sync_Service|null
	 */
	private static $instance = null;

	/**
	 * Prevent recursive sync loops.
	 *
	 * @since 1.0.0
	 *
	 * @var bool
	 */
	private $is_syncing = false;

	/**
	 * Retrieve singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Sync_Service
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		add_action( 'save_post_subscrpt_order', array( $this, 'handle_save' ), 20, 3 );
		add_action( 'before_delete_post', array( $this, 'handle_delete' ) );
		add_action( 'added_post_meta', array( $this, 'handle_meta_change' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'handle_meta_change' ), 10, 4 );
		add_action( self::CRON_HOOK, array( $this, 'run_reconciliation' ) );
		add_action( 'init', array( $this, 'maybe_schedule_reconciliation' ) );
	}

	/**
	 * Respond to subscription saves.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 *
	 * @return void
	 */
	public function handle_save( $post_id, $post, $update ) {
		unset( $update );

		if ( $this->should_skip_save( $post_id, $post ) ) {
			return;
		}

		$this->sync_subscription( $post_id );
	}

	/**
	 * Respond to relevant meta changes.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $meta_id    Meta identifier.
	 * @param int    $object_id  Post identifier.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 *
	 * @return void
	 */
	public function handle_meta_change( $meta_id, $object_id, $meta_key, $meta_value ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		unset( $meta_value );

		if ( $this->is_syncing || 'subscrpt_order' !== get_post_type( $object_id ) ) {
			return;
		}

		if ( self::MAP_META_KEY === $meta_key ) {
			return;
		}

		$this->sync_subscription( $object_id );
	}

	/**
	 * Handle subscription deletion by trashing mirrored WCS record.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID being deleted.
	 *
	 * @return void
	 */
	public function handle_delete( $post_id ) {
		if ( 'subscrpt_order' !== get_post_type( $post_id ) ) {
			return;
		}

		$wcs_id = (int) get_post_meta( $post_id, self::MAP_META_KEY, true );

		if ( $wcs_id > 0 ) {
			wp_trash_post( $wcs_id );
		}
	}

	/**
	 * Ensure reconciliation cron exists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_schedule_reconciliation() {
		if ( defined( 'WP_RUNNING_TESTS' ) && WP_RUNNING_TESTS ) {
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK );
		}
	}

	/**
	 * Run reconciliation to backfill missing mirrors.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function run_reconciliation() {
		$query = new \WP_Query(
			array(
				'post_type'      => 'subscrpt_order',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
			)
		);

		foreach ( $query->posts as $subscription_id ) {
			$this->sync_subscription( (int) $subscription_id );
		}
	}

	/**
	 * Synchronise a single subscription to WooCommerce storage.
	 *
	 * @since 1.0.0
	 *
	 * @param int $subscription_id Subscription post ID.
	 *
	 * @return void
	 */
	public function sync_subscription( $subscription_id ) {
		$post = get_post( $subscription_id );

		if ( ! $post || 'subscrpt_order' !== $post->post_type ) {
			return;
		}

		if ( $this->is_syncing ) {
			return;
		}

		$this->is_syncing = true;

		$wcs_id = $this->ensure_shop_subscription( $post );

		if ( $wcs_id ) {
			$this->synchronise_meta( $post, $wcs_id );
		}

		$this->is_syncing = false;
	}

	/**
	 * Determine whether to skip save handling.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $post_id Post identifier.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return bool
	 */
	private function should_skip_save( $post_id, $post ) {
		if ( 'subscrpt_order' !== get_post_type( $post_id ) ) {
			return true;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return true;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return true;
		}

		return empty( $post ) || 'auto-draft' === $post->post_status;
	}

	/**
	 * Ensure a WooCommerce shop_subscription exists for given post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post Subscription post.
	 *
	 * @return int|null
	 */
	private function ensure_shop_subscription( $post ) {
		$existing = (int) get_post_meta( $post->ID, self::MAP_META_KEY, true );

		if ( $existing > 0 && get_post( $existing ) ) {
			return $this->update_shop_subscription_post( $existing, $post );
		}

		$located = get_posts(
			array(
				'post_type'      => 'shop_subscription',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				' meta_key'      => self::WCS_MAP_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				' meta_value'    => $post->ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if ( ! empty( $located ) ) {
			$wcs_id = (int) $located[0];
			update_post_meta( $post->ID, self::MAP_META_KEY, $wcs_id );

			return $this->update_shop_subscription_post( $wcs_id, $post );
		}

		$wcs_post = array(
			'post_type'     => 'shop_subscription',
			'post_status'   => Status_Mapper::to_wcs( $post->post_status ),
			'post_author'   => $post->post_author,
			/* translators: %d: subscription ID. */
			'post_title'    => sprintf( __( 'Subscription #%d', 'wp_subscription' ), $post->ID ),
			'post_content'  => '',
			'post_excerpt'  => '',
			'post_date'     => $post->post_date,
			'post_date_gmt' => $post->post_date_gmt,
		);

		$wcs_id = wp_insert_post( $wcs_post, true );

		if ( is_wp_error( $wcs_id ) ) {
			return null;
		}

		update_post_meta( $post->ID, self::MAP_META_KEY, $wcs_id );
		update_post_meta( $wcs_id, self::WCS_MAP_META_KEY, $post->ID );

		return (int) $wcs_id;
	}

	/**
	 * Update mirrored post with latest status/author.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $wcs_id WooCommerce subscription ID.
	 * @param \WP_Post $post   Source subscription.
	 *
	 * @return int|null
	 */
	private function update_shop_subscription_post( $wcs_id, $post ) {
		wp_update_post(
			array(
				'ID'          => $wcs_id,
				'post_status' => Status_Mapper::to_wcs( $post->post_status ),
				'post_author' => $post->post_author,
				/* translators: %d: subscription ID. */
				'post_title'  => sprintf( __( 'Subscription #%d', 'wp_subscription' ), $post->ID ),
			)
		);

		update_post_meta( $wcs_id, self::WCS_MAP_META_KEY, $post->ID );
		update_post_meta( $post->ID, self::MAP_META_KEY, $wcs_id );

		return (int) $wcs_id;
	}

	/**
	 * Synchronise mirrored meta values.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post   Source subscription.
	 * @param int      $wcs_id Mirrored WooCommerce subscription.
	 *
	 * @return void
	 */
	private function synchronise_meta( $post, $wcs_id ) {
		$meta_keys = array(
			'_subscrpt_billing_period',
			'_subscrpt_billing_interval',
			'_subscrpt_start_date',
			'_subscrpt_next_date',
			'_subscrpt_end_date',
			'_subscrpt_trial_ended',
			'_subscrpt_product_id',
		);

		foreach ( $meta_keys as $key ) {
			$value = get_post_meta( $post->ID, $key, true );

			if ( '' !== $value && null !== $value ) {
				update_post_meta( $wcs_id, $key, $value );
			}
		}

		update_post_meta( $wcs_id, '_billing_period', get_post_meta( $post->ID, '_subscrpt_billing_period', true ) );
		update_post_meta( $wcs_id, '_billing_interval', (int) get_post_meta( $post->ID, '_subscrpt_billing_interval', true ) );
		update_post_meta( $wcs_id, '_schedule_start', (int) get_post_meta( $post->ID, '_subscrpt_start_date', true ) );
		update_post_meta( $wcs_id, '_schedule_next_payment', (int) get_post_meta( $post->ID, '_subscrpt_next_date', true ) );
		update_post_meta( $wcs_id, '_schedule_end', (int) get_post_meta( $post->ID, '_subscrpt_end_date', true ) );
		update_post_meta( $wcs_id, '_schedule_trial_end', (int) get_post_meta( $post->ID, '_subscrpt_trial_ended', true ) );
		update_post_meta( $wcs_id, '_customer_user', (int) $post->post_author );

		update_post_meta( $post->ID, self::MAP_META_KEY, $wcs_id );
		update_post_meta( $wcs_id, self::WCS_MAP_META_KEY, $post->ID );
	}
}
