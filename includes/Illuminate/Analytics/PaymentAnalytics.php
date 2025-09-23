<?php

namespace SpringDevs\Subscription\Illuminate\Analytics;

/**
 * Payment Analytics
 *
 * Tracks payment success/failure metrics, gateway performance,
 * and revenue analytics for subscription payments.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class PaymentAnalytics {

	/**
	 * Analytics data cache
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize analytics
	 *
	 * @return void
	 */
	private function init() {
		// Register hooks
		$this->register_hooks();
	}

	/**
	 * Register analytics hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Track payment events
		add_action( 'subscrpt_payment_success', array( $this, 'track_payment_success' ), 10, 3 );
		add_action( 'subscrpt_payment_failed', array( $this, 'track_payment_failure' ), 10, 4 );
		
		// Track subscription events
		add_action( 'subscrpt_subscription_activated', array( $this, 'track_subscription_activated' ), 10, 1 );
		add_action( 'subscrpt_subscription_cancelled', array( $this, 'track_subscription_cancelled' ), 10, 1 );
		add_action( 'subscrpt_subscription_expired', array( $this, 'track_subscription_expired' ), 10, 1 );
	}

	/**
	 * Track payment success
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param int    $order_id Order ID
	 * @param string $gateway_id Gateway ID
	 * @return void
	 */
	public function track_payment_success( $subscription_id, $order_id, $gateway_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$data = array(
			'subscription_id' => $subscription_id,
			'order_id'        => $order_id,
			'gateway_id'      => $gateway_id,
			'amount'          => $order->get_total(),
			'currency'        => $order->get_currency(),
			'status'          => 'success',
			'processed_at'    => current_time( 'mysql' ),
		);

		$this->store_payment_event( $data );
		$this->update_gateway_stats( $gateway_id, 'success' );
		$this->update_revenue_stats( $order->get_total(), $order->get_currency() );

		wp_subscrpt_write_debug_log( "PaymentAnalytics: Tracked successful payment for order #{$order_id}" );
	}

	/**
	 * Track payment failure
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param int    $order_id Order ID
	 * @param string $gateway_id Gateway ID
	 * @param string $error_message Error message
	 * @return void
	 */
	public function track_payment_failure( $subscription_id, $order_id, $gateway_id, $error_message ) {
		$order = wc_get_order( $order_id );
		$amount = $order ? $order->get_total() : 0;
		$currency = $order ? $order->get_currency() : get_woocommerce_currency();

		$data = array(
			'subscription_id' => $subscription_id,
			'order_id'        => $order_id,
			'gateway_id'      => $gateway_id,
			'amount'          => $amount,
			'currency'        => $currency,
			'status'          => 'failed',
			'error_message'   => $error_message,
			'processed_at'    => current_time( 'mysql' ),
		);

		$this->store_payment_event( $data );
		$this->update_gateway_stats( $gateway_id, 'failed' );

		wp_subscrpt_write_debug_log( "PaymentAnalytics: Tracked failed payment for order #{$order_id}" );
	}

	/**
	 * Track subscription activated
	 *
	 * @param int $subscription_id Subscription ID
	 * @return void
	 */
	public function track_subscription_activated( $subscription_id ) {
		$this->update_subscription_stats( $subscription_id, 'activated' );
		wp_subscrpt_write_debug_log( "PaymentAnalytics: Tracked subscription activation #{$subscription_id}" );
	}

	/**
	 * Track subscription cancelled
	 *
	 * @param int $subscription_id Subscription ID
	 * @return void
	 */
	public function track_subscription_cancelled( $subscription_id ) {
		$this->update_subscription_stats( $subscription_id, 'cancelled' );
		wp_subscrpt_write_debug_log( "PaymentAnalytics: Tracked subscription cancellation #{$subscription_id}" );
	}

	/**
	 * Track subscription expired
	 *
	 * @param int $subscription_id Subscription ID
	 * @return void
	 */
	public function track_subscription_expired( $subscription_id ) {
		$this->update_subscription_stats( $subscription_id, 'expired' );
		wp_subscrpt_write_debug_log( "PaymentAnalytics: Tracked subscription expiration #{$subscription_id}" );
	}

	/**
	 * Get payment success rate
	 *
	 * @param string $period Period (day, week, month, year)
	 * @param string $gateway_id Optional gateway ID filter
	 * @return float Success rate percentage
	 */
	public function get_payment_success_rate( $period = 'month', $gateway_id = null ) {
		$cache_key = "success_rate_{$period}_{$gateway_id}";
		
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_history';

		$where_clause = "WHERE processed_at >= %s";
		$where_values = array( $this->get_period_start( $period ) );

		if ( $gateway_id ) {
			$where_clause .= " AND gateway_id = %s";
			$where_values[] = $gateway_id;
		}

		$total_query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} {$where_clause}",
			$where_values
		);

		$success_query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} {$where_clause} AND status = 'success'",
			$where_values
		);

		$total = (int) $wpdb->get_var( $total_query );
		$success = (int) $wpdb->get_var( $success_query );

		$success_rate = $total > 0 ? ( $success / $total ) * 100 : 0;
		$this->cache[ $cache_key ] = $success_rate;

		return $success_rate;
	}

	/**
	 * Get gateway performance
	 *
	 * @param string $period Period (day, week, month, year)
	 * @return array Gateway performance data
	 */
	public function get_gateway_performance( $period = 'month' ) {
		$cache_key = "gateway_performance_{$period}";
		
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_history';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				gateway_id,
				COUNT(*) as total_payments,
				SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_payments,
				SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
				AVG(CASE WHEN status = 'success' THEN amount ELSE NULL END) as avg_successful_amount,
				SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as total_revenue
			FROM {$table_name} 
			WHERE processed_at >= %s 
			GROUP BY gateway_id 
			ORDER BY total_payments DESC",
			$this->get_period_start( $period )
		), ARRAY_A );

		$performance = array();
		foreach ( $results as $result ) {
			$success_rate = $result['total_payments'] > 0 ? 
				( $result['successful_payments'] / $result['total_payments'] ) * 100 : 0;

			$performance[ $result['gateway_id'] ] = array(
				'total_payments'        => (int) $result['total_payments'],
				'successful_payments'   => (int) $result['successful_payments'],
				'failed_payments'       => (int) $result['failed_payments'],
				'success_rate'          => round( $success_rate, 2 ),
				'avg_successful_amount' => round( (float) $result['avg_successful_amount'], 2 ),
				'total_revenue'         => round( (float) $result['total_revenue'], 2 ),
			);
		}

		$this->cache[ $cache_key ] = $performance;
		return $performance;
	}

	/**
	 * Get revenue analytics
	 *
	 * @param string $period Period (day, week, month, year)
	 * @param string $currency Currency code
	 * @return array Revenue analytics data
	 */
	public function get_revenue_analytics( $period = 'month', $currency = null ) {
		$cache_key = "revenue_analytics_{$period}_{$currency}";
		
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_history';

		$where_clause = "WHERE processed_at >= %s AND status = 'success'";
		$where_values = array( $this->get_period_start( $period ) );

		if ( $currency ) {
			$where_clause .= " AND currency = %s";
			$where_values[] = $currency;
		}

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				DATE(processed_at) as date,
				COUNT(*) as payment_count,
				SUM(amount) as total_revenue,
				AVG(amount) as avg_payment_amount
			FROM {$table_name} 
			{$where_clause}
			GROUP BY DATE(processed_at) 
			ORDER BY date ASC",
			$where_values
		), ARRAY_A );

		$analytics = array(
			'total_revenue'      => 0,
			'total_payments'     => 0,
			'avg_payment_amount' => 0,
			'daily_breakdown'    => array(),
		);

		foreach ( $results as $result ) {
			$analytics['total_revenue'] += (float) $result['total_revenue'];
			$analytics['total_payments'] += (int) $result['payment_count'];
			$analytics['daily_breakdown'][ $result['date'] ] = array(
				'payment_count'      => (int) $result['payment_count'],
				'total_revenue'      => (float) $result['total_revenue'],
				'avg_payment_amount' => (float) $result['avg_payment_amount'],
			);
		}

		if ( $analytics['total_payments'] > 0 ) {
			$analytics['avg_payment_amount'] = $analytics['total_revenue'] / $analytics['total_payments'];
		}

		$this->cache[ $cache_key ] = $analytics;
		return $analytics;
	}

	/**
	 * Get failure reasons
	 *
	 * @param string $period Period (day, week, month, year)
	 * @param string $gateway_id Optional gateway ID filter
	 * @return array Failure reasons data
	 */
	public function get_failure_reasons( $period = 'month', $gateway_id = null ) {
		$cache_key = "failure_reasons_{$period}_{$gateway_id}";
		
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_history';

		$where_clause = "WHERE processed_at >= %s AND status = 'failed'";
		$where_values = array( $this->get_period_start( $period ) );

		if ( $gateway_id ) {
			$where_clause .= " AND gateway_id = %s";
			$where_values[] = $gateway_id;
		}

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT 
				failure_reason,
				COUNT(*) as count
			FROM {$table_name} 
			{$where_clause}
			GROUP BY failure_reason 
			ORDER BY count DESC",
			$where_values
		), ARRAY_A );

		$reasons = array();
		foreach ( $results as $result ) {
			$reasons[ $result['failure_reason'] ] = (int) $result['count'];
		}

		$this->cache[ $cache_key ] = $reasons;
		return $reasons;
	}

	/**
	 * Get subscription health metrics
	 *
	 * @return array Subscription health data
	 */
	public function get_subscription_health_metrics() {
		$cache_key = 'subscription_health_metrics';
		
		if ( isset( $this->cache[ $cache_key ] ) ) {
			return $this->cache[ $cache_key ];
		}

		// Get subscription counts by status
		$status_counts = wp_count_posts( 'subscrpt_order' );
		
		// Get active subscriptions
		$active_count = $status_counts->active ?? 0;
		$total_count = array_sum( (array) $status_counts );

		// Get recent payment success rate
		$success_rate = $this->get_payment_success_rate( 'week' );

		// Get churn rate (cancelled + expired in last 30 days)
		$churn_count = $this->get_churn_count( 30 );
		$churn_rate = $total_count > 0 ? ( $churn_count / $total_count ) * 100 : 0;

		$metrics = array(
			'total_subscriptions'    => $total_count,
			'active_subscriptions'   => $active_count,
			'inactive_subscriptions' => $total_count - $active_count,
			'payment_success_rate'   => $success_rate,
			'churn_rate'            => round( $churn_rate, 2 ),
			'health_score'          => $this->calculate_health_score( $success_rate, $churn_rate ),
		);

		$this->cache[ $cache_key ] = $metrics;
		return $metrics;
	}

	/**
	 * Store payment event
	 *
	 * @param array $data Payment event data
	 * @return void
	 */
	private function store_payment_event( $data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_history';

		$wpdb->insert( $table_name, $data );
	}

	/**
	 * Update gateway stats
	 *
	 * @param string $gateway_id Gateway ID
	 * @param string $status Payment status
	 * @return void
	 */
	private function update_gateway_stats( $gateway_id, $status ) {
		$option_name = "subscrpt_gateway_stats_{$gateway_id}";
		$stats = get_option( $option_name, array() );

		$today = current_time( 'Y-m-d' );
		if ( ! isset( $stats[ $today ] ) ) {
			$stats[ $today ] = array( 'success' => 0, 'failed' => 0 );
		}

		$stats[ $today ][ $status ]++;
		update_option( $option_name, $stats );
	}

	/**
	 * Update revenue stats
	 *
	 * @param float  $amount Revenue amount
	 * @param string $currency Currency code
	 * @return void
	 */
	private function update_revenue_stats( $amount, $currency ) {
		$option_name = "subscrpt_revenue_stats_{$currency}";
		$stats = get_option( $option_name, array() );

		$today = current_time( 'Y-m-d' );
		if ( ! isset( $stats[ $today ] ) ) {
			$stats[ $today ] = 0;
		}

		$stats[ $today ] += $amount;
		update_option( $option_name, $stats );
	}

	/**
	 * Update subscription stats
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param string $event Event type
	 * @return void
	 */
	private function update_subscription_stats( $subscription_id, $event ) {
		$option_name = 'subscrpt_subscription_stats';
		$stats = get_option( $option_name, array() );

		$today = current_time( 'Y-m-d' );
		if ( ! isset( $stats[ $today ] ) ) {
			$stats[ $today ] = array();
		}

		if ( ! isset( $stats[ $today ][ $event ] ) ) {
			$stats[ $today ][ $event ] = 0;
		}

		$stats[ $today ][ $event ]++;
		update_option( $option_name, $stats );
	}

	/**
	 * Get period start date
	 *
	 * @param string $period Period (day, week, month, year)
	 * @return string Start date
	 */
	private function get_period_start( $period ) {
		switch ( $period ) {
			case 'day':
				return date( 'Y-m-d 00:00:00', strtotime( '-1 day' ) );
			case 'week':
				return date( 'Y-m-d 00:00:00', strtotime( '-1 week' ) );
			case 'month':
				return date( 'Y-m-d 00:00:00', strtotime( '-1 month' ) );
			case 'year':
				return date( 'Y-m-d 00:00:00', strtotime( '-1 year' ) );
			default:
				return date( 'Y-m-d 00:00:00', strtotime( '-1 month' ) );
		}
	}

	/**
	 * Get churn count
	 *
	 * @param int $days Number of days
	 * @return int Churn count
	 */
	private function get_churn_count( $days ) {
		$args = array(
			'post_type'      => 'subscrpt_order',
			'post_status'    => array( 'cancelled', 'expired' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'after' => date( 'Y-m-d', strtotime( "-{$days} days" ) ),
				),
			),
		);

		$posts = get_posts( $args );
		return count( $posts );
	}

	/**
	 * Calculate health score
	 *
	 * @param float $success_rate Success rate percentage
	 * @param float $churn_rate Churn rate percentage
	 * @return int Health score (0-100)
	 */
	private function calculate_health_score( $success_rate, $churn_rate ) {
		// Weight success rate more heavily
		$success_weight = 0.7;
		$churn_weight = 0.3;

		// Normalize churn rate (lower is better)
		$churn_score = max( 0, 100 - $churn_rate );

		$health_score = ( $success_rate * $success_weight ) + ( $churn_score * $churn_weight );
		return round( $health_score );
	}

	/**
	 * Clear cache
	 *
	 * @return void
	 */
	public function clear_cache() {
		$this->cache = array();
	}
}
