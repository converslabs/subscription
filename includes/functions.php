<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;
use SpringDevs\Subscription\Utils\Product;

/**
 * Include tailwind CSS file
 *
 * * Add this class to the parent element to apply tailwind css styles:
 * * "`wpsubs-tw-root`"
 * *
 * * Use "`yarn build:tailwind`" to build the tailwind CSS file.
 * * Use "`yarn watch:tailwind`" to continuously build the tailwind CSS file.
 *
 * ? This stylesheet is added to the all admin pages of the plugin.
 * ? You can use this function to add the stylesheet on other pages if necessary.
 */
function subscrpt_include_tailwind_css() {
	wp_enqueue_style( 'wpsubs-tailwind', SUBSCRPT_ASSETS . '/css/tailwind/output.css', [], SUBSCRPT_VERSION );
}

/**
 * Generate URL for Subscription Action.
 *
 * @param string $action Action.
 * @param string $nonce nonce.
 * @param int    $subscription_id Subscription ID.
 *
 * @return string
 */
function subscrpt_get_action_url( $action, $nonce, $subscription_id ) {
	$view_subscription_endpoint = Subscription::get_user_endpoint( 'view_subs' );
	return add_query_arg(
		array(
			'subscrpt_id' => $subscription_id,
			'action'      => $action,
			'wpnonce'     => $nonce,
		),
		wc_get_endpoint_url( $view_subscription_endpoint, $subscription_id, wc_get_page_permalink( 'myaccount' ) )
	);
}


/**
 * Get typos.
 *
 * @param int    $number Number.
 * @param string $typo   Typo.
 *
 * @return string
 */
function subscrpt_get_typos( $number, $typo ) {
	if ( $number == 1 && $typo == 'days' ) {
		return ucfirst( __( 'day', 'subscription' ) );
	} elseif ( $number == 1 && $typo == 'weeks' ) {
		return ucfirst( __( 'week', 'subscription' ) );
	} elseif ( $number == 1 && $typo == 'months' ) {
		return ucfirst( __( 'month', 'subscription' ) );
	} elseif ( $number == 1 && $typo == 'years' ) {
		return ucfirst( __( 'year', 'subscription' ) );
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
 * Count total payments made.
 *
 * @param int $subscription_id Subscription ID.
 * @return int Number of payments made.
 */
function subscrpt_count_payments_made( $subscription_id ) {
	global $wpdb;

	$table_name = $wpdb->prefix . 'subscrpt_order_relation';

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$relations = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT sr.*, p.post_status, p.post_date 
		FROM $table_name sr 
		INNER JOIN {$wpdb->posts} p ON sr.order_id = p.ID 
		WHERE sr.subscription_id = %d
		ORDER BY p.post_date ASC",
			$subscription_id
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$relations = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT sr.*, p.post_status, p.post_date 
		FROM $table_name sr 
		INNER JOIN {$wpdb->posts} p ON sr.order_id = p.ID 
		WHERE sr.subscription_id = %d
		ORDER BY p.post_date ASC",
			$subscription_id
		)
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

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
	/**
	 * Order relation type cast.
	 *
	 * @param string $key Key.
	 *
	 * @return string
	 */
	function wps_subscription_order_relation_type_cast( string $key ) {
		$relational_type_keys = apply_filters(
			'subscrpt_order_relational_types',
			array(
				'new'   => __( 'New Subscription Order', 'subscription' ),
				'renew' => __( 'Renewal Order', 'subscription' ),
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
	/**
	 * Check if HPOS enabled.
	 *
	 * @return bool
	 */
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
	/**
	 * Get timing types.
	 *
	 * @param bool $key_value Key value.
	 *
	 * @return array
	 */
	function wps_subscription_get_timing_types( $key_value = false ): array {
		return $key_value ? array(
			'days'   => 'Daily',
			'weeks'  => 'Weekly',
			'months' => 'Monthly',
			'years'  => 'Yearly',
		) : array(
			array(
				'label' => __( 'Day', 'subscription' ),
				'value' => 'days',
			),
			array(
				'label' => __( 'Week', 'subscription' ),
				'value' => 'weeks',
			),
			array(
				'label' => __( 'Month', 'subscription' ),
				'value' => 'months',
			),
			array(
				'label' => __( 'Year', 'subscription' ),
				'value' => 'years',
			),
		);
	}
}

/**
 * Get WC product in subscription wrapper.
 *
 * @deprecated 1.8.17 Use SpringDevs\Subscription\Illuminate\Subscription\Subscription::get_subs_product().
 */
function sdevs_get_subscription_product( $product ) {
	// Deprecated notice.
	_deprecated_function( 'sdevs_get_subscription_product', '1.8.17', 'SpringDevs\Subscription\Illuminate\Subscription\Subscription::get_subs_product' );

	return Subscription::get_subs_product( $product );
}

/**
 * Logger
 *
 * @param mixed $message      Message.
 * @param bool  $should_print Print the output.
 */
function subscrpt_write_log( $message, bool $should_print = false ): void {
	$logger = wc_get_logger();

	$message = is_array( $message ) || is_object( $message ) ? wp_json_encode( $message ) : $message;
	$logger->add( 'wp_subscription', $message );

	echo esc_html( $should_print ? $message : '' );
}

/**
 * Debug Logger
 *
 * @param mixed $log logs.
 */
function subscrpt_write_debug_log( $log ): void {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		} else {
			error_log( 'wp_subscription: ' . $log ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
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
		__( 'Split payment plan completed successfully! %1$d of %2$d payments received.', 'subscription' ),
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
	update_comment_meta( $comment_id, '_subscrpt_activity', __( 'Split Payment - Plan Complete', 'subscription' ) );
	update_comment_meta( $comment_id, '_subscrpt_activity_type', 'split_payment' );

	// Add payment summary note
	$payment_summary = sprintf(
		/* translators: %1$d: payments made, %2$d: total payments, %3$s: completion date */
		__( 'Payment Summary: %1$d of %2$d installments completed on %3$s. All payments received successfully.', 'subscription' ),
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
	update_comment_meta( $summary_comment_id, '_subscrpt_activity', __( 'Payment Summary - Complete', 'subscription' ) );
	update_comment_meta( $summary_comment_id, '_subscrpt_activity_type', 'split_payment_summary' );
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
function subscrpt_multiselect_field( $field ) {
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

/**
 * Render a preview for pages that require WPSubscription Pro, with a blurred background image and a call-to-action overlay.
 *
 * @param array $args Preview arguments.
 */
function subscrpt_render_page_preview( array $args = [] ) {
	$defaults = [
		'preview_image_url' => SUBSCRPT_ASSETS . '/images/previews/subscrpt-health-preview.png',
		'cta_title'         => __( 'Upgrade to WPSubscription Pro', 'subscription' ),
		'cta_description'   => __( 'This page requires WPSubscription Pro. Unlock advanced features, priority support, and more with WPSubscription Pro.', 'subscription' ),
		'cta_button_text'   => __( '⚡ Upgrade to Pro', 'subscription' ),
		'cta_button_url'    => 'https://wpsubscription.co/?utm_source=plugin&utm_medium=admin&utm_campaign=upgrade_pro',
	];

	$args = wp_parse_args( $args, $defaults );

	ob_start();
	?>
		<div style="position: relative;">
			<div style="filter:blur(4px);pointer-events:none;">
				<div style="max-width:1240px;margin:32px auto 0 auto;">
					<img
						src="<?php echo esc_url( $args['preview_image_url'] ); ?>"
						alt="<?php esc_attr_e( 'page preview', 'subscription' ); ?>"
						style="width:100%;display:block;"
					/>
				</div>
			</div>
			<div style="position:absolute;inset:0;display:flex;align-items:top;justify-content:center;padding:100px 32px 32px;">
				<div style="height:fit-content;background:#fff;border-radius:12px;padding:28px 32px;text-align:center;max-width:440px;box-shadow:0 8px 48px rgba(0,0,0,0.22);">

					<!-- Lock icon with radial glow -->
					<div style="position:relative;display:flex;align-items:center;justify-content:center;margin-bottom:20px;">
						<div style="position:absolute;width:100px;height:100px;background:radial-gradient(circle,var(--wpsubs-brand-ring) 0%,transparent 70%);border-radius:50%;"></div>
						<div style="position:relative;width:56px;height:56px;border:1.5px solid var(--wpsubs-brand);border-radius:14px;display:flex;align-items:center;justify-content:center;background:#fff;">
							<svg width="24" height="24" fill="none" viewBox="0 0 24 24" style="stroke:var(--wpsubs-brand);" stroke-width="2" aria-hidden="true">
								<rect x="5" y="11" width="14" height="10" rx="2"/>
								<path stroke-linecap="round" d="M8 11V7a4 4 0 018 0v4"/>
							</svg>
						</div>
					</div>

					<!-- Title -->
					<div style="font-size:22px;font-weight:700;color:#111;margin-bottom:10px;line-height:1.3;">
						<?php echo esc_html( $args['cta_title'] ); ?>
					</div>

					<!-- Subtitle -->
					<div style="font-size:14px;color:#6b7280;margin-bottom:20px;line-height:1.6;">
						<?php echo esc_html( $args['cta_description'] ); ?>
					</div>

					<!-- CTA button -->
					<a href="<?php echo esc_url( $args['cta_button_url'] ); ?>" target="_blank" style="display:flex;align-items:center;justify-content:center;gap:8px;background:var(--wpsubs-brand);color:#fff;font-size:15px;font-weight:600;padding:14px 28px;border-radius:8px;text-decoration:none;">
						<?php echo esc_html( $args['cta_button_text'] ); ?>
					</a>
				</div>
			</div>
		</div>
	<?php
	return ob_get_clean();
}

/**
 * Render an Advanced Select component.
 *
 * Outputs a styled trigger-button + dropdown that replaces a native <select>.
 * A hidden <input> carries the selected value for form submission.
 * JS (admin-components.js WPSubsAdvSelect) handles open/close and selection.
 *
 * @param array $args {
 *   @type string   $name          Hidden input name attribute.  Required.
 *   @type string   $placeholder   Trigger label when nothing is selected.
 *   @type string   $value         Initial hidden-input value (default: '').
 *   @type array    $options       Each item: {
 *                                   string  value      Value submitted on selection.
 *                                   string  label      Display text.
 *                                   bool    danger     Red destructive style.
 *                                   string  confirm    JS confirm() message before selecting.
 *                                   bool    divider    Render a divider BEFORE this item.
 *                                   bool    disabled   Non-selectable item.
 *                                 }
 *   @type string   $align         Menu alignment: 'left' (default) or 'right'.
 *   @type string   $id            Optional id on the root element.
 *   @type string   $class         Extra classes on the root element.
 * }
 */
function wpsubs_render_adv_select( array $args ): void {
	$args = wp_parse_args(
		$args,
		array(
			'name'        => '',
			'placeholder' => __( 'Select', 'subscription' ),
			'value'       => '',
			'options'     => array(),
			'align'       => 'left',
			'id'          => '',
			'class'       => '',
			'attrs'       => array(),
		)
	);

	$root_classes = 'wpsubs-adv-select wpsubs-adv-select--' . ( 'right' === $args['align'] ? 'right' : 'left' );
	if ( $args['class'] ) {
		$root_classes .= ' ' . $args['class'];
	}

	// Resolve trigger label: use matching option's label when a value is already set.
	$trigger_label = $args['placeholder'];
	$current_value = (string) $args['value'];
	if ( '' !== $current_value && '-1' !== $current_value ) {
		foreach ( $args['options'] as $opt ) {
			if ( (string) ( $opt['value'] ?? '' ) === $current_value ) {
				$trigger_label = $opt['label'] ?? $args['placeholder'];
				break;
			}
		}
	}

	$chevron_svg = '<svg class="wpsubs-adv-select__chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 9l6 6 6-6"/></svg>';
	?>
	<div class="<?php echo esc_attr( $root_classes ); ?>"
		<?php
		if ( $args['id'] ) :
			?>
			id="<?php echo esc_attr( $args['id'] ); ?>"<?php endif; ?>
		data-placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
		data-default-value="<?php echo esc_attr( $args['value'] ); ?>"
		<?php
		foreach ( $args['attrs'] as $attr_name => $attr_value ) :
			echo esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '" '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both parts escaped.
		endforeach;
		?>
	>
		<button type="button" class="wpsubs-adv-select__trigger" aria-haspopup="listbox" aria-expanded="false">
			<span class="wpsubs-adv-select__label"><?php echo esc_html( $trigger_label ); ?></span>
			<?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>

		<div class="wpsubs-adv-select__menu" role="listbox">
			<?php
			foreach ( $args['options'] as $option ) :
				$option = wp_parse_args(
					$option,
					array(
						'value'    => '',
						'label'    => '',
						'danger'   => false,
						'confirm'  => '',
						'divider'  => false,
						'disabled' => false,
					)
				);
				if ( $option['divider'] ) :
					?>
					<div class="wpsubs-adv-select__divider"></div>
					<?php
					continue;
				endif;
				?>
				<button
					type="button"
					class="wpsubs-adv-select__item<?php echo $option['danger'] ? ' wpsubs-adv-select__item--danger' : ''; ?>"
					data-value="<?php echo esc_attr( $option['value'] ); ?>"
					<?php
					if ( $option['confirm'] ) :
						?>
						data-confirm="<?php echo esc_attr( $option['confirm'] ); ?>"<?php endif; ?>
					<?php
					if ( $option['disabled'] ) :
						?>
						data-disabled<?php endif; ?>
					role="option"
				>
					<span class="wpsubs-adv-select__item-label"><?php echo esc_html( $option['label'] ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>

		<?php if ( $args['name'] ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( $args['value'] ); ?>">
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render a tag/pill select input with an inline filter and filterable dropdown.
 * Supports single and multiple selection. No external dependencies.
 *
 * JS: WPSubsTagSelect (admin-components.js) auto-inits elements.
 * Event fired on root: `wpsubs:select` — detail: { value, label, selected }
 *
 * @param array $args {
 *   string       $name        Form field name (base name, without [] suffix).
 *   string       $placeholder Input placeholder shown when nothing is selected.
 *   string|array $value       Current value(s). Array for multiple, string for single.
 *   array        $options     Options: array of { value, label, disabled? }.
 *   bool         $multiple    Enable multi-select mode.
 *   string       $id          Optional root element id.
 *   string       $class       Extra CSS classes for the root element.
 *   array        $attrs       Extra HTML attributes for the root element.
 * }
 */
function wpsubs_render_tag_select( array $args ): void {
	$args = wp_parse_args(
		$args,
		array(
			'name'        => '',
			'placeholder' => __( 'Select...', 'subscription' ),
			'value'       => '',
			'options'     => array(),
			'multiple'    => false,
			'id'          => '',
			'class'       => '',
			'attrs'       => array(),
		)
	);

	$multiple      = (bool) $args['multiple'];
	$current_value = $multiple ? (array) $args['value'] : (string) $args['value'];

	if ( $multiple ) {
		$selected_values = array_filter( array_map( 'strval', $current_value ), fn( $v ) => '' !== $v );
	} else {
		$selected_values = ( '' !== $current_value ) ? array( $current_value ) : array();
	}

	// Map selected values to their labels for pill rendering.
	$selected_labels = array();
	foreach ( $args['options'] as $opt ) {
		$opt_val = (string) ( $opt['value'] ?? '' );
		if ( in_array( $opt_val, $selected_values, true ) ) {
			$selected_labels[ $opt_val ] = $opt['label'] ?? $opt_val;
		}
	}

	$root_classes = 'wpsubs-tag-select';
	if ( $multiple ) {
		$root_classes .= ' wpsubs-tag-select--multi';
	}
	if ( $args['class'] ) {
		$root_classes .= ' ' . $args['class'];
	}

	$has_pills   = ! empty( $selected_values );
	$placeholder = $has_pills ? '' : esc_attr( $args['placeholder'] );

	$chevron_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 9l6 6 6-6"/></svg>';
	?>
	<div
		class="<?php echo esc_attr( $root_classes ); ?>"
		<?php
		if ( $args['id'] ) :
			?>
			id="<?php echo esc_attr( $args['id'] ); ?>"<?php endif; ?>
		data-placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
		data-name="<?php echo esc_attr( $args['name'] ); ?>"
		<?php
		if ( $multiple ) :
			?>
			data-multiple="1"<?php endif; ?>
		<?php
		foreach ( $args['attrs'] as $attr_name => $attr_value ) :
			echo esc_attr( $attr_name ) . '="' . esc_attr( $attr_value ) . '" '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both parts escaped.
		endforeach;
		?>
	>
		<div class="wpsubs-tag-select__field">
			<?php foreach ( $selected_labels as $val => $lbl ) : ?>
				<span class="wpsubs-tag-select__pill" data-value="<?php echo esc_attr( $val ); ?>">
					<span class="wpsubs-tag-select__pill-label"><?php echo esc_html( $lbl ); ?></span>
					<button type="button" class="wpsubs-tag-select__pill-remove" aria-label="<?php esc_attr_e( 'Remove', 'subscription' ); ?>">&#x2715;</button>
				</span>
			<?php endforeach; ?>
			<input
				type="text"
				class="wpsubs-tag-select__input"
				placeholder="<?php echo $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already esc_attr'd above. ?>"
				autocomplete="off"
				aria-label="<?php esc_attr_e( 'Filter options', 'subscription' ); ?>"
			/>
			<span class="wpsubs-tag-select__chevron" aria-hidden="true">
				<?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</span>
		</div>

		<div class="wpsubs-tag-select__dropdown">
			<div class="wpsubs-tag-select__list" role="listbox"
				<?php
				if ( $multiple ) :
					?>
					aria-multiselectable="true"<?php endif; ?>>
				<?php
				foreach ( $args['options'] as $option ) :
					$option      = wp_parse_args(
						$option,
						array(
							'value'    => '',
							'label'    => '',
							'disabled' => false,
						)
					);
					$opt_value   = (string) $option['value'];
					$is_selected = in_array( $opt_value, $selected_values, true );
					?>
					<button
						type="button"
						class="wpsubs-tag-select__item"
						data-value="<?php echo esc_attr( $opt_value ); ?>"
						role="option"
						aria-selected="<?php echo $is_selected ? 'true' : 'false'; ?>"
						<?php
						if ( $is_selected ) :
							?>
							data-selected<?php endif; ?>
						<?php
						if ( $option['disabled'] ) :
							?>
							data-disabled<?php endif; ?>
						style="<?php echo $is_selected ? 'display:none;' : ''; ?>"
					><?php echo esc_html( $option['label'] ); ?></button>
				<?php endforeach; ?>
			</div>
			<div class="wpsubs-tag-select__empty"><?php esc_html_e( 'No results found.', 'subscription' ); ?></div>
		</div>

		<?php if ( $args['name'] ) : ?>
			<?php if ( $multiple ) : ?>
				<?php if ( empty( $selected_values ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $args['name'] ); ?>[]" value="" data-ts-val />
				<?php else : ?>
					<?php foreach ( $selected_values as $val ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $args['name'] ); ?>[]" value="<?php echo esc_attr( $val ); ?>" data-ts-val />
					<?php endforeach; ?>
				<?php endif; ?>
			<?php else : ?>
				<input type="hidden" name="<?php echo esc_attr( $args['name'] ); ?>" value="<?php echo esc_attr( $current_value ); ?>" data-ts-val />
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
