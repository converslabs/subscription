<?php

namespace SpringDevs\Subscription;

use SpringDevs\Subscription\Illuminate\Gateways\Paypal\PaypalDB;

/**
 * Class Installer
 *
 * @package SpringDevs\Subscription
 */
class Installer {

	/**
	 * Database schema version. Bump whenever a custom table changes.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.2.0';

	/**
	 * Run the installer
	 *
	 * @return void
	 */
	public function run() {
		$this->add_version();
		$this->register_schedules();
		$this->create_tables();
	}

	/**
	 * Create/upgrade custom tables when the stored schema version is outdated.
	 *
	 * Lets existing installs pick up new tables on update without a manual
	 * deactivate/reactivate. A single option read short-circuits once current.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'subscrpt_db_version' ) === self::DB_VERSION ) {
			return;
		}

		( new self() )->create_tables();
	}

	/**
	 * Add time and version on DB
	 */
	public function add_version() {
		$installed = get_option( 'subscrpt_installed' );

		if ( ! $installed ) {
			update_option( 'subscrpt_installed', time() );
		}

		update_option( 'subscrpt_version', SUBSCRPT_VERSION );

		update_option( 'subscrpt_manual_renew_cart_notice', 'Subscriptional product added to cart. Please complete the checkout to renew subscription.' );
	}

	/**
	 * Register cron events.
	 *
	 * @return void
	 */
	public function register_schedules() {
		if ( ! wp_next_scheduled( 'subscrpt_hourly_cron' ) ) {
			wp_schedule_event( strtotime( 'tomorrow midnight' ), 'hourly', 'subscrpt_hourly_cron' );
		}
	}

	/**
	 * Create necessary database tables
	 *
	 * @return void
	 */
	public function create_tables() {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$this->create_histories_table();
		$this->create_stats_snapshot_table();
		$this->create_cancellation_feedback_table();
		PaypalDB::maybe_create_tables();

		update_option( 'subscrpt_db_version', self::DB_VERSION );
	}

	/**
	 * Create the daily stats snapshot table.
	 *
	 * One row per calendar day holding the subscription status counts and the
	 * total monthly recurring revenue (MRR) at snapshot time. Powers MRR/
	 * subscription "over time" charts in reports and the recovery report.
	 *
	 * @return void
	 */
	public function create_stats_snapshot_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'subscrpt_stats_snapshot';

		$schema = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
                      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `snapshot_date` DATE NOT NULL,
                      `active_count` INT(11) NOT NULL DEFAULT 0,
                      `pending_count` INT(11) NOT NULL DEFAULT 0,
                      `on_hold_count` INT(11) NOT NULL DEFAULT 0,
                      `cancelled_count` INT(11) NOT NULL DEFAULT 0,
                      `expired_count` INT(11) NOT NULL DEFAULT 0,
                      `pe_cancelled_count` INT(11) NOT NULL DEFAULT 0,
                      `active_mrr` DECIMAL(14,2) NOT NULL DEFAULT 0,
                      `created_at` DATETIME NOT NULL,
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `snapshot_date` (`snapshot_date`)
                    ) $charset_collate";

		dbDelta( $schema );
	}

	/**
	 * Create the cancellation feedback table.
	 *
	 * One row per subscription — the latest cancellation feedback (a re-cancel after
	 * reactivation overwrites the previous row rather than accumulating a log, so
	 * churn-by-reason reports never double-count a subscription). Stores the
	 * customer's stated reason (key + a label snapshot that survives later reason
	 * edits/deletes) and optional comment. Consumed for churn tracking — the recovery
	 * plugin joins its recovery log to this table on subscription_id.
	 *
	 * @return void
	 */
	public function create_cancellation_feedback_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'subscrpt_cancellation_feedback';

		$schema = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
                      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                      `subscription_id` BIGINT(20) UNSIGNED NOT NULL,
                      `customer_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                      `reason_key` VARCHAR(60) NOT NULL DEFAULT '',
                      `reason_label` VARCHAR(191) NOT NULL DEFAULT '',
                      `comment` TEXT NULL,
                      `created_at` DATETIME NOT NULL,
                      PRIMARY KEY (`id`),
                      KEY `subscription_id` (`subscription_id`),
                      KEY `created_at` (`created_at`)
                    ) $charset_collate";

		dbDelta( $schema );
	}

	/**
	 * Create histories table
	 *
	 * @return void
	 */
	public function create_histories_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'subscrpt_order_relation';

		$schema = "CREATE TABLE IF NOT EXISTS `{$table_name}` (
                      `id` INT(255) NOT NULL AUTO_INCREMENT,
                      `subscription_id` INT(100) NOT NULL,
                      `order_id` INT(100) NOT NULL,
                      `order_item_id` INT(100) NOT NULL,
                      `type` VARCHAR(50) NOT NULL,
                      PRIMARY KEY (`id`)
                    ) $charset_collate";

		dbDelta( $schema );
	}
}
