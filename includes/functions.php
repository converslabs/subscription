<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use SpringDevs\Subscription\Utils\Product;
use SpringDevs\Subscription\Utils\ProductFactory;
use SpringDevs\Subscription\Utils\SubscriptionProduct;

/**
 * Include tailwind CSS file
 *
 * * Add this class to the parent element to apply tailwind css styles:
 * * "wpsubs-tw-root"
 * *
 * * Use "yarn build:tailwind" to build the tailwind CSS file.
 * * Use "yarn watch:tailwind" to continuously build the tailwind CSS file.
 */
function subscrpt_include_tailwind_css() {
	wp_enqueue_style( 'wpsubs-tailwind', WP_SUBSCRIPTION_ASSETS . '/css/tailwind/output.css', [], WP_SUBSCRIPTION_VERSION );
}

/**
 * Generate Url for Subscription Action.
 *
 * @param string $action Action.
 * @param string $nonce nonce.
 * @param int    $subscription_id Subscription ID.
 *
 * @return string
 */
function subscrpt_get_action_url( $action, $nonce, $subscription_id ) {
	return add_query_arg(
		array(
			'subscrpt_id' => $subscription_id,
			'action'      => $action,
			'wpnonce'     => $nonce,
		),
		wc_get_endpoint_url( 'view-subscription', $subscription_id, wc_get_page_permalink( 'myaccount' ) )
	);
}


function subscrpt_get_typos( $number, $typo ) {
	if ( $number == 1 && $typo == 'days' ) {
		return ucfirst( __( 'day', 'wp_subscription' ) );
	} elseif ( $number == 1 && $typo == 'weeks' ) {
		return ucfirst( __( 'week', 'wp_subscription' ) );
	} elseif ( $number == 1 && $typo == 'months' ) {
		return ucfirst( __( 'month', 'wp_subscription' ) );
	} elseif ( $number == 1 && $typo == 'years' ) {
		return ucfirst( __( 'year', 'wp_subscription' ) );
	} else {
		return ucfirst( $typo );
	}
}

/**
 * Format time with trial.
 *
 * @param mixed       $time Time.
 * @param null|string $trial Trial.
 *
 * @return string
 */
function subscrpt_next_date( $time, $trial = null ) {
	if ( null === $trial ) {
		$start_date = time();
	} else {
		$start_date = strtotime( $trial );
	}

	return gmdate( 'F d, Y', strtotime( $time, $start_date ) );
}

/**
 * Check if subscription-pro activated.
 *
 * @return bool
 */
function subscrpt_pro_activated(): bool {
	return class_exists( 'Sdevs_Wc_Subscription_Pro' );
}

/**
 * Get renewal process settings.
 *
 * @return bool
 */
function subscrpt_is_auto_renew_enabled() {
	return 'auto' === get_option( 'subscrpt_renewal_process', 'auto' );
}

/**
 * Get maximum payments for a subscription, checking variation, product, and subscription meta.
 *
 * @param int $subscription_id Subscription ID.
 * @return string|int Maximum payments or empty string if not set.
 */
function subscrpt_get_max_payments( $subscription_id ) {
	$product_id = get_post_meta( $subscription_id, '_subscrpt_product_id', true );
	if ( ! $product_id ) {
		return '';
	}

	$max_payments = null;

	// Check for variation first
	$variation_id = get_post_meta( $subscription_id, '_subscrpt_variation_id', true );
	if ( $variation_id ) {
		$max_payments = get_post_meta( $variation_id, '_subscrpt_max_no_payment', true );
	}

	// Fallback to product if variation doesn't have max payments or no variation
	if ( ! $max_payments ) {
		$max_payments = get_post_meta( $product_id, '_subscrpt_max_no_payment', true );
	}

	// Also check subscription's own meta data as final fallback
	if ( ! $max_payments ) {
		$max_payments = get_post_meta( $subscription_id, '_subscrpt_max_no_payment', true );
	}

	return $max_payments ?: '';
}

/**
 * Count total payments made for a subscription (including original + renewals).
 *
 * @param int $subscription_id Subscription ID.
 * @return int Number of payments made.
 */
function subscrpt_count_payments_made( $subscription_id ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'subscrpt_order_relation';

	// Get all relations for this subscription
	$relations = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT sr.*, p.post_status, p.post_date 
		FROM {$table_name} sr 
		INNER JOIN {$wpdb->posts} p ON sr.order_id = p.ID 
		WHERE sr.subscription_id = %d
		ORDER BY p.post_date ASC",
			$subscription_id
		)
	);

	// Define all payment-related order types (allow filtering for extensibility)
	$payment_types = apply_filters( 'subscrpt_payment_order_types', array( 'new', 'renew', 'early-renew' ) );

	// Count successful payments
	$successful_count = 0;
	foreach ( $relations as $relation ) {
		// Count all payment-related types
		if ( in_array( $relation->type, $payment_types ) ) {
			// Get the actual WooCommerce order
			$order = wc_get_order( $relation->order_id );
			if ( $order ) {
				// Check if order was paid/successful
				if ( $order->is_paid() || in_array( $order->get_status(), array( 'completed', 'processing', 'on-hold' ) ) ) {
					++$successful_count;
				}
			}
		}
	}

	return $successful_count;
}

/**
 * Check if subscription has reached its maximum payment limit.
 *
 * @param int $subscription_id Subscription ID.
 * @return bool True if limit reached, false otherwise.
 */
function subscrpt_is_max_payments_reached( $subscription_id ) {
	// Get the product ID from subscription
	$product_id = get_post_meta( $subscription_id, '_subscrpt_product_id', true );
	if ( ! $product_id ) {
		return false;
	}

	// Get maximum payments using helper function
	$max_payments = subscrpt_get_max_payments( $subscription_id );

	// Allow override of total installments
	$max_payments = apply_filters( 'subscrpt_split_payment_total_override', $max_payments, $subscription_id, $product_id );

	// If no limit set or unlimited, more payments are allowed
	if ( ! $max_payments || intval( $max_payments ) <= 0 ) {
		return false;
	}

	// Count payments made
	$payments_made = subscrpt_count_payments_made( $subscription_id );

	// Enhanced completion logic considering failed payments
	$is_reached = subscrpt_check_enhanced_completion( $subscription_id, $payments_made, $max_payments );

	// Fire action when split payment plan is completed (first time only)
	if ( $is_reached && ! get_post_meta( $subscription_id, '_subscrpt_split_payment_completed_fired', true ) ) {
		// Add completion milestone note
		subscrpt_add_payment_completion_note( $subscription_id, $payments_made, $max_payments );

		// Allow customization of subscription status after completion
		$expire_status = apply_filters( 'subscrpt_split_payment_expire_status', 'expired', $subscription_id, $payments_made, $max_payments );

		// Update subscription status if different from current
		$current_status = get_post_status( $subscription_id );
		if ( $current_status !== $expire_status ) {
			wp_update_post(
				array(
					'ID'          => $subscription_id,
					'post_status' => $expire_status,
				)
			);
		}

		do_action( 'subscrpt_split_payment_completed', $subscription_id, $payments_made, $max_payments );
		update_post_meta( $subscription_id, '_subscrpt_split_payment_completed_fired', true );

		// Handle split payment access timing if Pro version is active
		if ( function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated() ) {
			if ( class_exists( '\SpringDevs\SubscriptionPro\Illuminate\SplitPaymentHandler' ) ) {
				\SpringDevs\SubscriptionPro\Illuminate\SplitPaymentHandler::handle_split_payment_completion( $subscription_id, $payments_made, $max_payments );
			}
		}
	}

	return $is_reached;
}

/**
 * Get remaining payments for a subscription.
 *
 * @param int $subscription_id Subscription ID.
 * @return int|string Number of remaining payments or 'unlimited'.
 */
function subscrpt_get_remaining_payments( $subscription_id ) {
	// Get maximum payments using helper function
	$max_payments = subscrpt_get_max_payments( $subscription_id );

	// If no limit set or unlimited
	if ( ! $max_payments || intval( $max_payments ) <= 0 ) {
		return 'unlimited';
	}

	// Count payments made
	$payments_made = subscrpt_count_payments_made( $subscription_id );

	// Calculate remaining
	$remaining = intval( $max_payments ) - intval( $payments_made );

	return max( 0, $remaining );
}

/**
 * Get payment type for a subscription (handles variations properly).
 *
 * @param int $subscription_id Subscription ID.
 * @return string Payment type ('split_payment' or 'recurring').
 */
function subscrpt_get_payment_type( $subscription_id ) {
	$product_id   = get_post_meta( $subscription_id, '_subscrpt_product_id', true );
	$variation_id = get_post_meta( $subscription_id, '_subscrpt_variation_id', true );

	$payment_type = 'recurring'; // Default

	// Check variation first if it exists
	if ( $variation_id ) {
		$variation_payment_type = get_post_meta( $variation_id, '_subscrpt_payment_type', true );
		if ( $variation_payment_type ) {
			$payment_type = $variation_payment_type;
		}
	}

	// Fallback to product if no variation payment type
	if ( $payment_type === 'recurring' && $product_id ) {
		$product_payment_type = get_post_meta( $product_id, '_subscrpt_payment_type', true );
		if ( $product_payment_type ) {
			$payment_type = $product_payment_type;
		}
	}

	// Final fallback: check subscription's own meta data
	if ( $payment_type === 'recurring' ) {
		$subscription_payment_type = get_post_meta( $subscription_id, '_subscrpt_payment_type', true );
		if ( $subscription_payment_type ) {
			$payment_type = $subscription_payment_type;
		}
	}

	return $payment_type;
}

/**
 * Enhanced completion check considering failed payments and access suspension.
 *
 * @param int $subscription_id Subscription ID.
 * @param int $payments_made Number of successful payments made.
 * @param int $max_payments Maximum payments required.
 * @return bool True if subscription should be considered complete.
 */
function subscrpt_check_enhanced_completion( $subscription_id, $payments_made, $max_payments ) {
	// Standard completion check
	if ( $payments_made >= $max_payments ) {
		return true;
	}

	// Check for access suspension due to payment failures
	if ( function_exists( '\SpringDevs\SubscriptionPro\Illuminate\PaymentFailureHandler::is_access_suspended' ) ) {
		$is_suspended = \SpringDevs\SubscriptionPro\Illuminate\PaymentFailureHandler::is_access_suspended( $subscription_id );
		if ( $is_suspended ) {
			// If access is suspended, check if we should force completion
			$force_completion_on_suspension = apply_filters( 'subscrpt_force_completion_on_suspension', false, $subscription_id );
			if ( $force_completion_on_suspension ) {
				return true;
			}
		}
	}

	// Check for maximum failure threshold
	$failure_count                  = get_post_meta( $subscription_id, '_subscrpt_payment_failure_count', true ) ?: 0;
	$max_failures_before_completion = apply_filters( 'subscrpt_max_failures_before_completion', 0, $subscription_id );

	if ( $max_failures_before_completion > 0 && $failure_count >= $max_failures_before_completion ) {
		// Force completion after too many failures
		return true;
	}

	// Check for time-based completion (e.g., if too much time has passed)
	$completion_timeout_days = apply_filters( 'subscrpt_completion_timeout_days', 0, $subscription_id );
	if ( $completion_timeout_days > 0 ) {
		$start_date = get_post_meta( $subscription_id, '_subscrpt_start_date', true );
		if ( $start_date ) {
			$timeout_timestamp = $start_date + ( $completion_timeout_days * DAY_IN_SECONDS );
			if ( current_time( 'timestamp' ) >= $timeout_timestamp ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Count total payment attempts (including failed ones) for a subscription.
 *
 * @param int $subscription_id Subscription ID.
 * @return array Array with 'successful', 'failed', and 'total' counts.
 */
function subscrpt_count_all_payment_attempts( $subscription_id ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'subscrpt_order_relation';

	// Get all relations for this subscription
	$relations = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT sr.*, p.post_status, p.post_date 
		FROM {$table_name} sr 
		INNER JOIN {$wpdb->posts} p ON sr.order_id = p.ID 
		WHERE sr.subscription_id = %d
		ORDER BY p.post_date ASC",
			$subscription_id
		)
	);

	// Define all payment-related order types
	$payment_types = apply_filters( 'subscrpt_payment_order_types', array( 'new', 'renew', 'early-renew' ) );

	$successful_count = 0;
	$failed_count     = 0;

	foreach ( $relations as $relation ) {
		if ( in_array( $relation->type, $payment_types ) ) {
			$order = wc_get_order( $relation->order_id );
			if ( $order ) {
				if ( $order->is_paid() || in_array( $order->get_status(), array( 'completed', 'processing', 'on-hold' ) ) ) {
					++$successful_count;
				} elseif ( in_array( $order->get_status(), array( 'failed', 'cancelled' ) ) ) {
					++$failed_count;
				}
			}
		}
	}

	return array(
		'successful' => $successful_count,
		'failed'     => $failed_count,
		'total'      => $successful_count + $failed_count,
	);
}

if ( ! function_exists( 'wps_subscription_order_relation_type_cast' ) ) {
	/**
	 * Return Label against key.
	 *
	 * @param string $key Key to return cast Value.
	 *
	 * @return string
	 */
	function order_relation_type_cast( string $key ) {
		// add Deprecated notice
		_deprecated_function( 'order_relation_type_cast', '1.5.3', 'wps_subscription_order_relation_type_cast' );
		return wps_subscription_order_relation_type_cast( $key );
	}
	function wps_subscription_order_relation_type_cast( string $key ) {
		$relational_type_keys = apply_filters(
			'subscrpt_order_relational_types',
			array(
				'new'   => __( 'New Subscription Order', 'wp_subscription' ),
				'renew' => __( 'Renewal Order', 'wp_subscription' ),
			)
		);

		return isset( $relational_type_keys[ $key ] ) ? $relational_type_keys[ $key ] : '-';
	}
}

if ( ! function_exists( 'wps_subscription_is_wc_order_hpos_enabled' ) ) {
	/**
	 * Check if HPOS enabled.
	 */
	function is_wc_order_hpos_enabled() {
		// add Deprecated notice
		_deprecated_function( 'is_wc_order_hpos_enabled', '1.5.3', 'wps_subscription_is_wc_order_hpos_enabled' );
		return wps_subscription_is_wc_order_hpos_enabled();
	}
	function wps_subscription_is_wc_order_hpos_enabled() {
		return function_exists( 'wc_get_container' ) ?
			wc_get_container()
				->get( CustomOrdersTableController::class )
				->custom_orders_table_usage_is_enabled()
			: false;
	}
}

if ( ! function_exists( 'sdevs_wp_strtotime' ) ) {
	/**
	 * Get strtotime with WordPress timezone config.
	 *
	 * @param string   $str string.
	 * @param int|null $base_timestamp base timestamp.
	 *
	 * @return int
	 */
	function sdevs_wp_strtotime( $str, $base_timestamp = null ) {
		return strtotime( wp_date( 'Y-m-d H:i:s', strtotime( $str, $base_timestamp ) ) );
	}
}

if ( ! function_exists( 'sdevs_order_status_label' ) ) {
	/**
	 * Get order status label from slug.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	function sdevs_order_status_label( $status ) {
		$order_statuses = wc_get_order_statuses();

		return ( isset( $order_statuses[ "wc-{$status}" ] ) ? $order_statuses[ "wc-{$status}" ] : $status );
	}
}

if ( ! function_exists( 'wps_subscription_get_timing_types' ) ) {
	/**
	 * Get labels.
	 *
	 * @param bool $key_value key_value.
	 *
	 * @return array
	 */
	function get_timing_types( $key_value = false ): array {
		// add Deprecated notice
		_deprecated_function( 'get_timing_types', '1.5.3', 'wps_subscription_get_timing_types' );
		return wps_subscription_get_timing_types( $key_value );
	}
	function wps_subscription_get_timing_types( $key_value = false ): array {
		return $key_value ? array(
			'days'   => 'Daily',
			'weeks'  => 'Weekly',
			'months' => 'Monthly',
			'years'  => 'Yearly',
		) : array(
			array(
				'label' => __( 'Day', 'wp_subscription' ),
				'value' => 'days',
			),
			array(
				'label' => __( 'Week', 'wp_subscription' ),
				'value' => 'weeks',
			),
			array(
				'label' => __( 'Month', 'wp_subscription' ),
				'value' => 'months',
			),
			array(
				'label' => __( 'Year', 'wp_subscription' ),
				'value' => 'years',
			),
		);
	}
}

function sdevs_get_subscription_product( $product ): Product {
	if ( is_int( $product ) ) {
		$product = wc_get_product( $product );
	}

	return ProductFactory::load( $product );
}

/**
 * Logger
 *
 * @param mixed $message      Message.
 * @param bool  $should_print Print the output.
 */
function wp_subscrpt_write_log( $message, bool $should_print = false ): void {
	$logger = wc_get_logger();

	$message = is_array( $message ) || is_object( $message ) ? wp_json_encode( $message ) : $message;
	$logger->add( 'wp_subcription', $message );

	echo esc_html( $should_print ? $message : '' );
}

/**
 * Debug Logger
 *
 * @param mixed $log logs.
 */
function wp_subscrpt_write_debug_log( $log ): void {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		} else {
			error_log( 'wp_subcription: ' . $log ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
}

/**
 * Add payment completion note for split payment subscriptions.
 *
 * @param int $subscription_id Subscription ID.
 * @param int $payments_made  Number of payments made.
 * @param int $max_payments   Maximum number of payments.
 */
function subscrpt_add_payment_completion_note( $subscription_id, $payments_made, $max_payments ) {
	// Check if this is a split payment subscription
	if ( ! function_exists( 'subscrpt_get_payment_type' ) ) {
		return;
	}

	$payment_type = subscrpt_get_payment_type( $subscription_id );
	if ( 'split_payment' !== $payment_type ) {
		return;
	}

	// Create completion note
	$completion_note = sprintf(
		/* translators: %1$d: payments made, %2$d: total payments */
		__( '🎉 Split payment plan completed successfully! %1$d of %2$d payments received.', 'wp_subscription' ),
		$payments_made,
		$max_payments
	);

	// Add the completion note
	$comment_id = wp_insert_comment(
		array(
			'comment_author'  => 'Subscription for WooCommerce',
			'comment_content' => $completion_note,
			'comment_post_ID' => $subscription_id,
			'comment_type'    => 'order_note',
		)
	);
	update_comment_meta( $comment_id, '_subscrpt_activity', __( 'Split Payment - Plan Complete', 'wp_subscription' ) );

	// Add payment summary note
	$payment_summary = sprintf(
		/* translators: %1$d: payments made, %2$d: total payments, %3$s: completion date */
		__( 'Payment Summary: %1$d of %2$d installments completed on %3$s. All payments received successfully.', 'wp_subscription' ),
		$payments_made,
		$max_payments,
		date_i18n( wc_date_format(), current_time( 'timestamp' ) )
	);

	$summary_comment_id = wp_insert_comment(
		array(
			'comment_author'  => 'Subscription for WooCommerce',
			'comment_content' => $payment_summary,
			'comment_post_ID' => $subscription_id,
			'comment_type'    => 'order_note',
		)
	);
	update_comment_meta( $summary_comment_id, '_subscrpt_activity', __( 'Payment Summary - Complete', 'wp_subscription' ) );
}


/**
 * Render a WooCommerce-style multiselect field.
 *
 * @param array $field {
 *     Field arguments.
 *
 *     @type string       $id                Required. Meta key / input ID.
 *     @type string       $label             Field label.
 *     @type array        $options           Key => Label pairs for options.
 *     @type array|string $selected          Optional. Selected value(s). Array, JSON, or CSV.
 *     @type string       $desc_tip          Optional. Description tooltip text.
 *     @type string       $description       Optional. Field description text.
 *     @type string       $wrapper_class     Optional. Extra wrapper classes.
 *     @type string       $class             Optional. Extra <select> classes.
 *     @type string       $name              Optional. Input name. Defaults to $id.'[]'.
 * }
 */
function wp_subs_multiselect_field( $field ) {
	$defaults = [
		'id'            => '',
		'label'         => '',
		'options'       => [],
		'selected'      => [],
		'desc_tip'      => false,
		'description'   => '',
		'wrapper_class' => '',
		'wrapper_style' => '',
		'class'         => 'wc-enhanced-select',
		'style'         => '',
		'name'          => '',
	];

	$field = wp_parse_args( $field, $defaults );

	if ( empty( $field['id'] ) ) {
		return;
	}

	$id          = esc_attr( $field['id'] );
	$name        = $field['name'] ? $field['name'] : $id . '[]';
	$label       = esc_html( $field['label'] );
	$description = $field['description'];
	$desc_tip    = $field['desc_tip'];

	// Normalize selected values into array.
	$selected = [];
	if ( is_array( $field['selected'] ) ) {
		$selected = $field['selected'];
	} elseif ( is_string( $field['selected'] ) && $field['selected'] !== '' ) {
		if ( false !== strpos( $field['selected'], '[' ) ) {
			$tmp      = json_decode( $field['selected'], true );
			$selected = is_array( $tmp ) ? $tmp : [];
		} else {
			$selected = array_filter( array_map( 'trim', explode( ',', $field['selected'] ) ) );
		}
	}

	// Build <option> list.
	$options_html = '';
	foreach ( $field['options'] as $key => $text ) {
		$is_selected   = in_array( (string) $key, (array) $selected, true ) ? ' selected="selected"' : '';
		$options_html .= sprintf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $key ),
			$is_selected,
			esc_html( $text )
		);
	}

	$tooltip_html = '';
	if ( $desc_tip && $description ) {
		$tooltip_html = wc_help_tip( $description );
	}

	$description_html = '';
	if ( $description && ! $desc_tip ) {
		$description_html = '<span class="description">' . wp_kses_post( $description ) . '</span>';
	}

	// ? Escaped intentionally.
	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<p 
		class="form-field <?php echo esc_attr( $id . '_field ' . ( $field['wrapper_class'] ) ); ?>" 
		style="<?php echo esc_attr( $field['wrapper_style'] ); ?>"
	>
		<label for="<?php echo esc_attr( $id ); ?>">
			<?php echo esc_html( $label ); ?>
		</label>

		<?php echo $tooltip_html; ?>

		<select 
			multiple="multiple" 
			id="<?php echo esc_attr( $id ); ?>" 
			name="<?php echo esc_attr( $name ); ?>" 
			class="<?php echo esc_attr( $field['class'] ); ?>" 
			style="<?php echo esc_attr( $field['style'] ); ?>" 
		>
			<?php echo $options_html; ?>
		</select>
		
		<?php echo $description_html; ?>
	</p>
	<?php
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
}
