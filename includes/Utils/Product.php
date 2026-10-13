<?php
/**
 * Base wrapper around a WC_Product with subscription accessors.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Utils;

use SpringDevs\Subscription\Illuminate\Helper;

/**
 * Abstract Product class that wraps a WC_Product object.
 *
 * @method int get_id()
 * @method string get_name()
 * @method string get_type()
 * @method string get_slug()
 * @method string get_date_created()
 * @method string get_date_modified()
 * @method string get_status()
 * @method string get_featured()
 * @method string get_catalog_visibility()
 * @method string get_description()
 * @method string get_short_description()
 * @method string get_sku()
 * @method float get_price()
 * @method float get_regular_price()
 * @method float get_sale_price()
 * @method string get_date_on_sale_from()
 * @method string get_date_on_sale_to()
 * @method float get_total_sales()
 * @method bool get_tax_status()
 * @method bool get_tax_class()
 * @method bool get_manage_stock()
 * @method int get_stock_quantity()
 * @method string get_stock_status()
 * @method string get_backorders()
 * @method bool get_sold_individually()
 * @method float get_weight()
 * @method float get_length()
 * @method float get_width()
 * @method float get_height()
 * @method array get_dimensions()
 * @method bool get_upsell_ids()
 * @method bool get_cross_sell_ids()
 * @method string get_parent_id()
 * @method string get_reviews_allowed()
 * @method string get_purchase_note()
 * @method string get_attribute()
 * @method array get_attributes()
 * @method array get_default_attributes()
 * @method string get_menu_order()
 * @method string get_category_ids()
 * @method string get_tag_ids()
 * @method string get_virtual()
 * @method string get_gallery_image_ids()
 * @method string get_shipping_class_id()
 * @method string get_downloads()
 * @method int get_download_expiry()
 * @method int get_downloadable()
 * @method int get_download_limit()
 * @method string get_image_id()
 * @method string get_rating_counts()
 * @method string get_average_rating()
 * @method string get_review_count()
 * @method bool is_virtual()
 */
abstract class Product {

	/**
	 * Wrapped WooCommerce product.
	 *
	 * @var \WC_Product
	 */
	protected \WC_Product $product;

	/**
	 * Initialize the class.
	 *
	 * @param \WC_Product $product WooCommerce product.
	 */
	public function __construct( \WC_Product $product ) {
			$this->product = $product;
	}

	/**
	 * Get the trial length as a label, e.g. "7 days".
	 *
	 * @return string|null Null when the product has no trial.
	 */
	public function get_trial(): ?string {
		if ( $this->has_trial() ) {
			$product_trial_per = $this->get_trial_timing_per();

			return $product_trial_per . ' ' . Helper::get_typos( $product_trial_per, $this->get_trial_timing_option() );
		}

		return null;
	}

	/**
	 * Whether the product has a trial the current customer can use.
	 *
	 * @return bool
	 */
	public function has_trial(): bool {
		$trial_timing_per = $this->get_trial_timing_per();
		$product_id       = $this->product->get_id();

		return ( $trial_timing_per > 0 ) && Helper::check_trial( $product_id );
	}

	/**
	 * Get the custom add to cart button label.
	 *
	 * @return mixed
	 */
	public function get_button_label() {
		return $this->product->get_meta( '_subscrpt_cart_btn_label' );
	}

	/**
	 * Magic method to call methods from the WC_Product object.
	 *
	 * @param string $name The method name.
	 * @param array  $arguments The method arguments.
	 * @return mixed The result of the called method.
	 * @uses \WC_Product::__call()
	 */
	public function __call( $name, $arguments ) {
		return $this->product->{$name}( ...$arguments );
	}

	/**
	 * Get the billing interval count.
	 *
	 * @return int
	 */
	public function get_timing_per(): int {
		return 1;
	}

	/**
	 * Get the billing period label.
	 *
	 * @return string
	 */
	public function get_timing_option(): string {
		return Helper::get_typos( $this->get_timing_per(), $this->product->get_meta( '_subscrpt_timing_option' ) );
	}

	/**
	 * Get the subscription purchase limit.
	 *
	 * @return mixed
	 */
	public function get_limit() {
		return $this->product->get_meta( '_subscrpt_limit' );
	}

	/**
	 * Get the renewal limit.
	 *
	 * @return mixed
	 */
	public function get_renewal_limit() {
		return $this->product->get_meta( '_subscrpt_max_no_payment' );
	}

	/**
	 * Get the maximum number of payments.
	 *
	 * @return mixed
	 */
	public function get_max_no_payment() {
		return $this->product->get_meta( '_subscrpt_max_no_payment' );
	}

	/**
	 * Whether subscription is enabled for the product.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		if ( empty( $this->product->get_meta( '_subscrpt_enabled' ) ) ) {
			return false;
		}

		// Once plan-connected, a product is a subscription only while it still has
		// a plan — it never falls back to the legacy terms after a detach.
		if ( 'yes' === $this->product->get_meta( '_subscrpt_plan_connected_before' ) && function_exists( 'subscrpt_product_has_plan' ) ) {
			$parent   = $this->product->get_parent_id();
			$has_plan = $parent
				? subscrpt_product_has_plan( $parent, $this->product->get_id() )
				: subscrpt_product_has_plan( $this->product->get_id() );
			if ( ! $has_plan ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the trial length.
	 *
	 * @return int
	 */
	public function get_trial_timing_per(): int {
		return (int) ( $this->product->get_meta( '_subscrpt_trial_timing_per' ) ?? 1 );
	}

	/**
	 * Get the trial period unit.
	 *
	 * @return mixed
	 */
	public function get_trial_timing_option() {
		return $this->product->get_meta( '_subscrpt_trial_timing_option' );
	}

	/**
	 * Get the signup fee.
	 *
	 * @return float
	 */
	public function get_signup_fee(): float {
		return 0;
	}

	/**
	 * Get the payment type (recurring or split_payment).
	 *
	 * @return string Payment type.
	 */
	public function get_payment_type(): string {
		$payment_type = $this->product->get_meta( '_subscrpt_payment_type' );
		return $payment_type ? $payment_type : 'recurring';
	}

	/**
	 * Check if product uses split payment type.
	 *
	 * @return bool True if split payment, false otherwise.
	 */
	public function is_split_payment(): bool {
		return 'split_payment' === $this->get_payment_type();
	}

	/**
	 * Get access ends timing for split payments.
	 *
	 * @return string Access ends timing option.
	 */
	public function get_access_ends_timing(): string {
		$access_ends_timing = $this->product->get_meta( '_subscrpt_access_ends_timing' );
		return $access_ends_timing ? $access_ends_timing : 'after_full_duration';
	}

	/**
	 * Get custom access duration time.
	 *
	 * @return int Custom access duration time.
	 */
	public function get_custom_access_duration_time(): int {
		$duration_time = $this->product->get_meta( '_subscrpt_custom_access_duration_time' );
		return (int) ( $duration_time ? $duration_time : 1 );
	}

	/**
	 * Get custom access duration type.
	 *
	 * @return string Custom access duration type (days, weeks, months, years).
	 */
	public function get_custom_access_duration_type(): string {
		$duration_type = $this->product->get_meta( '_subscrpt_custom_access_duration_type' );
		return $duration_type ? $duration_type : 'months';
	}

	/**
	 * Check if product has custom access duration.
	 *
	 * @return bool True if custom duration, false otherwise.
	 */
	public function has_custom_access_duration(): bool {
		return 'custom_duration' === $this->get_access_ends_timing();
	}
}
