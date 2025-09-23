<?php

namespace SpringDevs\Subscription\Illuminate\Monitoring;

/**
 * Subscription Health Monitoring
 *
 * Monitors subscription health and performance, tracks churn rates,
 * and provides automated alerts for subscription issues.
 *
 * @package WPSubscription
 * @since 1.6.0
 */
class SubscriptionHealth {

	/**
	 * Health thresholds
	 *
	 * @var array
	 */
	private $thresholds;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize health monitoring
	 *
	 * @return void
	 */
	private function init() {
		// Set health thresholds
		$this->thresholds = array(
			'payment_success_rate' => 95.0, // Minimum 95% success rate
			'churn_rate'          => 10.0,  // Maximum 10% churn rate
			'failed_payment_rate' => 5.0,   // Maximum 5% failed payment rate
			'response_time'       => 5.0,   // Maximum 5 seconds response time
		);

		// Register hooks
		$this->register_hooks();
	}

	/**
	 * Register monitoring hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Daily health check
		add_action( 'subscrpt_daily_health_check', array( $this, 'perform_daily_health_check' ) );
		
		// Real-time monitoring
		add_action( 'subscrpt_payment_failed', array( $this, 'monitor_payment_failure' ), 10, 4 );
		add_action( 'subscrpt_subscription_cancelled', array( $this, 'monitor_subscription_cancellation' ), 10, 1 );
		
		// Schedule daily health check
		if ( ! wp_next_scheduled( 'subscrpt_daily_health_check' ) ) {
			wp_schedule_event( time(), 'daily', 'subscrpt_daily_health_check' );
		}
	}

	/**
	 * Perform daily health check
	 *
	 * @return void
	 */
	public function perform_daily_health_check() {
		wp_subscrpt_write_debug_log( 'SubscriptionHealth: Performing daily health check' );

		$health_metrics = $this->get_health_metrics();
		$health_score = $this->calculate_health_score( $health_metrics );

		// Store health metrics
		$this->store_health_metrics( $health_metrics, $health_score );

		// Check for alerts
		$this->check_health_alerts( $health_metrics, $health_score );

		wp_subscrpt_write_debug_log( "SubscriptionHealth: Daily health check completed. Health score: {$health_score}" );
	}

	/**
	 * Monitor payment failure
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param int    $order_id Order ID
	 * @param string $gateway_id Gateway ID
	 * @param string $error_message Error message
	 * @return void
	 */
	public function monitor_payment_failure( $subscription_id, $order_id, $gateway_id, $error_message ) {
		// Track failure pattern
		$this->track_failure_pattern( $subscription_id, $gateway_id, $error_message );

		// Check for immediate alerts
		$this->check_immediate_alerts( 'payment_failure', array(
			'subscription_id' => $subscription_id,
			'order_id'        => $order_id,
			'gateway_id'      => $gateway_id,
			'error_message'   => $error_message,
		) );
	}

	/**
	 * Monitor subscription cancellation
	 *
	 * @param int $subscription_id Subscription ID
	 * @return void
	 */
	public function monitor_subscription_cancellation( $subscription_id ) {
		// Track cancellation reason
		$this->track_cancellation_reason( $subscription_id );

		// Check for immediate alerts
		$this->check_immediate_alerts( 'subscription_cancelled', array(
			'subscription_id' => $subscription_id,
		) );
	}

	/**
	 * Get health metrics
	 *
	 * @return array Health metrics
	 */
	public function get_health_metrics() {
		$metrics = array();

		// Payment success rate
		$metrics['payment_success_rate'] = $this->get_payment_success_rate( 'week' );

		// Churn rate
		$metrics['churn_rate'] = $this->get_churn_rate( 30 );

		// Failed payment rate
		$metrics['failed_payment_rate'] = $this->get_failed_payment_rate( 'week' );

		// Average response time
		$metrics['response_time'] = $this->get_average_response_time();

		// Active subscriptions
		$metrics['active_subscriptions'] = $this->get_active_subscription_count();

		// Revenue metrics
		$metrics['revenue_metrics'] = $this->get_revenue_metrics();

		// Gateway performance
		$metrics['gateway_performance'] = $this->get_gateway_performance();

		return $metrics;
	}

	/**
	 * Calculate health score
	 *
	 * @param array $metrics Health metrics
	 * @return int Health score (0-100)
	 */
	public function calculate_health_score( $metrics ) {
		$score = 0;
		$max_score = 0;

		// Payment success rate (30% weight)
		$success_rate = $metrics['payment_success_rate'];
		$success_score = min( 100, ( $success_rate / $this->thresholds['payment_success_rate'] ) * 100 );
		$score += $success_score * 0.3;
		$max_score += 100 * 0.3;

		// Churn rate (25% weight)
		$churn_rate = $metrics['churn_rate'];
		$churn_score = max( 0, 100 - ( $churn_rate / $this->thresholds['churn_rate'] ) * 100 );
		$score += $churn_score * 0.25;
		$max_score += 100 * 0.25;

		// Failed payment rate (25% weight)
		$failed_rate = $metrics['failed_payment_rate'];
		$failed_score = max( 0, 100 - ( $failed_rate / $this->thresholds['failed_payment_rate'] ) * 100 );
		$score += $failed_score * 0.25;
		$max_score += 100 * 0.25;

		// Response time (20% weight)
		$response_time = $metrics['response_time'];
		$response_score = max( 0, 100 - ( $response_time / $this->thresholds['response_time'] ) * 100 );
		$score += $response_score * 0.2;
		$max_score += 100 * 0.2;

		$final_score = $max_score > 0 ? ( $score / $max_score ) * 100 : 0;
		return round( $final_score );
	}

	/**
	 * Check health alerts
	 *
	 * @param array $metrics Health metrics
	 * @param int   $health_score Health score
	 * @return void
	 */
	private function check_health_alerts( $metrics, $health_score ) {
		$alerts = array();

		// Check payment success rate
		if ( $metrics['payment_success_rate'] < $this->thresholds['payment_success_rate'] ) {
			$alerts[] = array(
				'type'    => 'payment_success_rate_low',
				'severity' => 'warning',
				'message' => sprintf(
					'Payment success rate is %.2f%%, below threshold of %.2f%%',
					$metrics['payment_success_rate'],
					$this->thresholds['payment_success_rate']
				),
				'metrics' => $metrics,
			);
		}

		// Check churn rate
		if ( $metrics['churn_rate'] > $this->thresholds['churn_rate'] ) {
			$alerts[] = array(
				'type'     => 'churn_rate_high',
				'severity' => 'critical',
				'message'  => sprintf(
					'Churn rate is %.2f%%, above threshold of %.2f%%',
					$metrics['churn_rate'],
					$this->thresholds['churn_rate']
				),
				'metrics' => $metrics,
			);
		}

		// Check failed payment rate
		if ( $metrics['failed_payment_rate'] > $this->thresholds['failed_payment_rate'] ) {
			$alerts[] = array(
				'type'     => 'failed_payment_rate_high',
				'severity' => 'warning',
				'message'  => sprintf(
					'Failed payment rate is %.2f%%, above threshold of %.2f%%',
					$metrics['failed_payment_rate'],
					$this->thresholds['failed_payment_rate']
				),
				'metrics' => $metrics,
			);
		}

		// Check overall health score
		if ( $health_score < 70 ) {
			$alerts[] = array(
				'type'     => 'health_score_low',
				'severity' => 'critical',
				'message'  => sprintf( 'Overall health score is %d%%, indicating system issues', $health_score ),
				'metrics'  => $metrics,
			);
		}

		// Process alerts
		foreach ( $alerts as $alert ) {
			$this->process_alert( $alert );
		}
	}

	/**
	 * Check immediate alerts
	 *
	 * @param string $event_type Event type
	 * @param array  $event_data Event data
	 * @return void
	 */
	private function check_immediate_alerts( $event_type, $event_data ) {
		// Check for critical events that need immediate attention
		$critical_events = array(
			'massive_payment_failure',
			'gateway_outage',
			'system_error',
		);

		if ( in_array( $event_type, $critical_events, true ) ) {
			$this->process_alert( array(
				'type'     => $event_type,
				'severity' => 'critical',
				'message'  => "Critical event detected: {$event_type}",
				'data'     => $event_data,
			) );
		}
	}

	/**
	 * Process alert
	 *
	 * @param array $alert Alert data
	 * @return void
	 */
	private function process_alert( $alert ) {
		// Store alert
		$this->store_alert( $alert );

		// Send notifications based on severity
		switch ( $alert['severity'] ) {
			case 'critical':
				$this->send_critical_alert( $alert );
				break;
			case 'warning':
				$this->send_warning_alert( $alert );
				break;
		}

		wp_subscrpt_write_debug_log( "SubscriptionHealth: Alert processed - {$alert['type']}: {$alert['message']}" );
	}

	/**
	 * Send critical alert
	 *
	 * @param array $alert Alert data
	 * @return void
	 */
	private function send_critical_alert( $alert ) {
		$admin_email = get_option( 'admin_email' );
		$subject = 'CRITICAL: Subscription System Alert';
		$message = sprintf(
			"Critical alert in subscription system:\n\nType: %s\nMessage: %s\nTime: %s\n\nPlease investigate immediately.",
			$alert['type'],
			$alert['message'],
			current_time( 'Y-m-d H:i:s' )
		);

		wp_mail( $admin_email, $subject, $message );

		// Log to error log
		error_log( "SUBSCRIPTION CRITICAL ALERT: {$alert['message']}" );
	}

	/**
	 * Send warning alert
	 *
	 * @param array $alert Alert data
	 * @return void
	 */
	private function send_warning_alert( $alert ) {
		$admin_email = get_option( 'admin_email' );
		$subject = 'WARNING: Subscription System Alert';
		$message = sprintf(
			"Warning alert in subscription system:\n\nType: %s\nMessage: %s\nTime: %s\n\nPlease review when convenient.",
			$alert['type'],
			$alert['message'],
			current_time( 'Y-m-d H:i:s' )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Get payment success rate
	 *
	 * @param string $period Period
	 * @return float Success rate
	 */
	private function get_payment_success_rate( $period ) {
		// Use PaymentAnalytics if available
		if ( class_exists( '\SpringDevs\Subscription\Illuminate\Analytics\PaymentAnalytics' ) ) {
			$analytics = new \SpringDevs\Subscription\Illuminate\Analytics\PaymentAnalytics();
			return $analytics->get_payment_success_rate( $period );
		}

		// Fallback calculation
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_history';
		$start_date = date( 'Y-m-d', strtotime( "-1 {$period}" ) );

		$total = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE processed_at >= %s",
			$start_date
		) );

		$success = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE processed_at >= %s AND status = 'success'",
			$start_date
		) );

		return $total > 0 ? ( $success / $total ) * 100 : 0;
	}

	/**
	 * Get churn rate
	 *
	 * @param int $days Number of days
	 * @return float Churn rate
	 */
	private function get_churn_rate( $days ) {
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

		$cancelled_count = count( get_posts( $args ) );

		// Get total active subscriptions at start of period
		$total_args = array(
			'post_type'      => 'subscrpt_order',
			'post_status'    => 'active',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'before' => date( 'Y-m-d', strtotime( "-{$days} days" ) ),
				),
			),
		);

		$total_count = count( get_posts( $total_args ) );

		return $total_count > 0 ? ( $cancelled_count / $total_count ) * 100 : 0;
	}

	/**
	 * Get failed payment rate
	 *
	 * @param string $period Period
	 * @return float Failed payment rate
	 */
	private function get_failed_payment_rate( $period ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'subscrpt_payment_history';
		$start_date = date( 'Y-m-d', strtotime( "-1 {$period}" ) );

		$total = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE processed_at >= %s",
			$start_date
		) );

		$failed = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE processed_at >= %s AND status = 'failed'",
			$start_date
		) );

		return $total > 0 ? ( $failed / $total ) * 100 : 0;
	}

	/**
	 * Get average response time
	 *
	 * @return float Average response time in seconds
	 */
	private function get_average_response_time() {
		// This would typically be measured from actual API calls
		// For now, return a placeholder value
		return 2.5;
	}

	/**
	 * Get active subscription count
	 *
	 * @return int Active subscription count
	 */
	private function get_active_subscription_count() {
		$status_counts = wp_count_posts( 'subscrpt_order' );
		return $status_counts->active ?? 0;
	}

	/**
	 * Get revenue metrics
	 *
	 * @return array Revenue metrics
	 */
	private function get_revenue_metrics() {
		// This would typically calculate revenue metrics
		// For now, return placeholder data
		return array(
			'daily_revenue'   => 0,
			'monthly_revenue' => 0,
			'growth_rate'     => 0,
		);
	}

	/**
	 * Get gateway performance
	 *
	 * @return array Gateway performance
	 */
	private function get_gateway_performance() {
		// This would typically calculate gateway performance
		// For now, return placeholder data
		return array();
	}

	/**
	 * Track failure pattern
	 *
	 * @param int    $subscription_id Subscription ID
	 * @param string $gateway_id Gateway ID
	 * @param string $error_message Error message
	 * @return void
	 */
	private function track_failure_pattern( $subscription_id, $gateway_id, $error_message ) {
		// Track failure patterns for analysis
		$pattern_data = array(
			'subscription_id' => $subscription_id,
			'gateway_id'      => $gateway_id,
			'error_message'   => $error_message,
			'timestamp'       => current_time( 'mysql' ),
		);

		// Store in options for analysis
		$patterns = get_option( 'subscrpt_failure_patterns', array() );
		$patterns[] = $pattern_data;
		
		// Keep only last 1000 patterns
		if ( count( $patterns ) > 1000 ) {
			$patterns = array_slice( $patterns, -1000 );
		}
		
		update_option( 'subscrpt_failure_patterns', $patterns );
	}

	/**
	 * Track cancellation reason
	 *
	 * @param int $subscription_id Subscription ID
	 * @return void
	 */
	private function track_cancellation_reason( $subscription_id ) {
		$reason = get_post_meta( $subscription_id, '_subscrpt_suspended_reason', true );
		
		$cancellation_data = array(
			'subscription_id' => $subscription_id,
			'reason'          => $reason,
			'timestamp'       => current_time( 'mysql' ),
		);

		// Store in options for analysis
		$cancellations = get_option( 'subscrpt_cancellation_reasons', array() );
		$cancellations[] = $cancellation_data;
		
		// Keep only last 1000 cancellations
		if ( count( $cancellations ) > 1000 ) {
			$cancellations = array_slice( $cancellations, -1000 );
		}
		
		update_option( 'subscrpt_cancellation_reasons', $cancellations );
	}

	/**
	 * Store health metrics
	 *
	 * @param array $metrics Health metrics
	 * @param int   $health_score Health score
	 * @return void
	 */
	private function store_health_metrics( $metrics, $health_score ) {
		$health_data = array(
			'date'         => current_time( 'Y-m-d' ),
			'metrics'      => $metrics,
			'health_score' => $health_score,
			'timestamp'    => current_time( 'mysql' ),
		);

		$daily_metrics = get_option( 'subscrpt_daily_health_metrics', array() );
		$daily_metrics[ current_time( 'Y-m-d' ) ] = $health_data;
		
		// Keep only last 90 days
		if ( count( $daily_metrics ) > 90 ) {
			$daily_metrics = array_slice( $daily_metrics, -90, null, true );
		}
		
		update_option( 'subscrpt_daily_health_metrics', $daily_metrics );
	}

	/**
	 * Store alert
	 *
	 * @param array $alert Alert data
	 * @return void
	 */
	private function store_alert( $alert ) {
		$alert_data = array_merge( $alert, array(
			'timestamp' => current_time( 'mysql' ),
		) );

		$alerts = get_option( 'subscrpt_health_alerts', array() );
		$alerts[] = $alert_data;
		
		// Keep only last 500 alerts
		if ( count( $alerts ) > 500 ) {
			$alerts = array_slice( $alerts, -500 );
		}
		
		update_option( 'subscrpt_health_alerts', $alerts );
	}
}
