<?php
/**
 * Legacy Compatibility Layer
 *
 * Re-declares old WP_SUBSCRIPTION_* constants and wp_-prefixed function names
 * as thin wrappers around the canonical SUBSCRPT_* equivalents.
 *
 * This file is loaded immediately after the canonical constants and functions
 * are defined, so Pro plugin and any third-party code that depends on the old
 * names continues to work without modification.
 *
 * @package Subscription
 */

// don't call the file directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Legacy constants  (WP_SUBSCRIPTION_* → SUBSCRPT_*)
// ---------------------------------------------------------------------------
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
$subscrpt_legacy_constants = array(
	'WP_SUBSCRIPTION_VERSION'   => SUBSCRPT_VERSION,
	'WP_SUBSCRIPTION_FILE'      => SUBSCRPT_FILE,
	'WP_SUBSCRIPTION_PATH'      => SUBSCRPT_PATH,
	'WP_SUBSCRIPTION_INCLUDES'  => SUBSCRPT_INCLUDES,
	'WP_SUBSCRIPTION_TEMPLATES' => SUBSCRPT_TEMPLATES,
	'WP_SUBSCRIPTION_URL'       => SUBSCRPT_URL,
	'WP_SUBSCRIPTION_ASSETS'    => SUBSCRPT_ASSETS,
);

foreach ( $subscrpt_legacy_constants as $subscrpt_old_name => $subscrpt_new_value ) {
	if ( ! defined( $subscrpt_old_name ) ) {
		define( $subscrpt_old_name, $subscrpt_new_value );
	}
}
unset( $subscrpt_legacy_constants, $subscrpt_old_name, $subscrpt_new_value );
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

// ---------------------------------------------------------------------------
// Legacy functions  (wp_-prefixed → subscrpt_-prefixed)
// ---------------------------------------------------------------------------
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound

if ( ! function_exists( 'wp_subscrpt_write_log' ) ) {
	/**
	 * @deprecated Use subscrpt_write_log() instead.
	 */
	function wp_subscrpt_write_log( $message, bool $should_print = false ): void {
		_deprecated_function( 'wp_subscrpt_write_log', '1.9.2', 'subscrpt_write_log' );
		subscrpt_write_log( $message, $should_print );
	}
}

if ( ! function_exists( 'wp_subscrpt_write_debug_log' ) ) {
	/**
	 * @deprecated Use subscrpt_write_debug_log() instead.
	 */
	function wp_subscrpt_write_debug_log( $log ): void {
		_deprecated_function( 'wp_subscrpt_write_debug_log', '1.9.2', 'subscrpt_write_debug_log' );

		subscrpt_write_debug_log( $log );
	}
}

if ( ! function_exists( 'wp_subs_multiselect_field' ) ) {
	/**
	 * @deprecated Use subscrpt_multiselect_field() instead.
	 */
	function wp_subs_multiselect_field( $field ) {
		_deprecated_function( 'wp_subs_multiselect_field', '1.9.2', 'subscrpt_multiselect_field' );

		subscrpt_multiselect_field( $field );
	}
}

if ( ! function_exists( 'wp_subscription_register_paypal_block' ) ) {
	/**
	 * @deprecated Use subscrpt_register_paypal_block() instead.
	 */
	function wp_subscription_register_paypal_block() {
		_deprecated_function( 'wp_subscription_register_paypal_block', '1.9.2', 'subscrpt_register_paypal_block' );

		subscrpt_register_paypal_block();
	}
}

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound

// ---------------------------------------------------------------------------
// Legacy filters  (deprecated hook names bridged onto canonical ones)
// ---------------------------------------------------------------------------

if ( ! function_exists( 'subscrpt_legacy_split_payment_next_due_date' ) ) {
	/**
	 * Bridge the deprecated `subscrpt_split_payment_next_due_date` filter.
	 *
	 * The split-payment-scoped hook was replaced by the general-purpose
	 * `subscrpt_subscription_next_date`. Any code still hooking the old name keeps
	 * working (with a deprecation notice) via this bridge.
	 *
	 * @deprecated 1.11.0 Use the `subscrpt_subscription_next_date` filter instead.
	 *
	 * @param int|null $next_date       Computed next payment timestamp, or null.
	 * @param int      $subscription_id Subscription ID.
	 * @param string   $recurr_timing   Recurring timing string.
	 * @param string   $type            Subscription history type.
	 *
	 * @return int|null
	 */
	function subscrpt_legacy_split_payment_next_due_date( $next_date, $subscription_id, $recurr_timing, $type ) {
		if ( has_filter( 'subscrpt_split_payment_next_due_date' ) ) {
			$next_date = apply_filters_deprecated(
				'subscrpt_split_payment_next_due_date',
				[ $next_date, $subscription_id, $recurr_timing, $type ],
				'1.11.0',
				'subscrpt_subscription_next_date'
			);
		}
		return $next_date;
	}
}
add_filter( 'subscrpt_subscription_next_date', 'subscrpt_legacy_split_payment_next_due_date', 5, 4 );
