<?php
/**
 * Storefront plan display (free).
 *
 * Renders the single-line plan display on a simple product that is tied to a
 * Recurring plan, and carries the resolved plan id onto the add-to-cart
 * request. Everything is guarded by `subscrpt_product_has_plan()`: when a
 * product has no tied plan this class does nothing and the classic
 * `subscrpt_simple_price_html` suffix (Frontend\Product) renders instead — the
 * two never render together for one product.
 *
 * The storefront never calls REST; plan data is read directly through
 * `PlanRepository::resolve_for_product()` (object cache → DB). Pro layers its
 * multi-plan selector / per-variation swap on top via its own hooks.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Admin\PlanPresenter;
use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * Frontend plan display for simple products.
 */
class Plans {

	/**
	 * Register storefront hooks.
	 */
	public function __construct() {
		// Runs after Frontend\Product::change_price_html (priority 10) so the
		// plan line replaces the classic suffix rather than appending to it.
		add_filter( 'woocommerce_get_price_html', array( $this, 'plan_price_html' ), 20, 2 );
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_to_cart_plan_field' ) );
	}

	/**
	 * Resolve the single (first/only) plan term tied to a simple product.
	 *
	 * Free is single-plan: `resolve_for_product()` may return several terms, but
	 * the free storefront shows only the first. Pro renders the full selector.
	 *
	 * @param \WC_Product|mixed $product Product.
	 *
	 * @return array|null Resolved plan row, or null when no plan is tied.
	 */
	protected function plan_row( $product ) {
		if ( ! $product instanceof \WC_Product || ! $product->is_type( 'simple' ) ) {
			return null;
		}

		$product_id = $product->get_id();
		if ( ! subscrpt_product_has_plan( $product_id ) ) {
			return null;
		}

		$rows = PlanRepository::resolve_for_product( $product_id );

		return empty( $rows ) ? null : $rows[0];
	}

	/**
	 * Replace the price HTML with the single-line plan display when a plan is
	 * tied; otherwise return the price unchanged (classic suffix stands).
	 *
	 * @param string            $price_html Price HTML.
	 * @param \WC_Product|mixed $product    Product.
	 *
	 * @return string
	 */
	public function plan_price_html( $price_html, $product ) {
		$row = $this->plan_row( $product );
		if ( null === $row ) {
			return $price_html;
		}

		$data    = is_array( $row['relation_data'] ) ? $row['relation_data'] : array();
		$regular = isset( $data['regular_price'] ) ? (string) $data['regular_price'] : '';
		$sale    = isset( $data['sale_price'] ) ? (string) $data['sale_price'] : '';
		$dtype   = isset( $data['discount_type'] ) ? (string) $data['discount_type'] : 'percentage';
		$dvalue  = isset( $data['discount_value'] ) ? (string) $data['discount_value'] : '0';
		$price   = PlanPresenter::offer_price( $regular, $sale, $dtype, $dvalue );

		$frequency = max( 1, (int) $row['billing_frequency'] );
		$interval  = PlanRepository::interval_to_option( (int) $row['billing_interval'] );
		$word      = subscrpt_get_typos( $frequency, $interval );
		$period    = $frequency > 1 ? $frequency . ' ' . strtolower( $word ) : strtolower( $word );

		$trial_html = '';
		$trial_num  = (int) ( $row['free_trial'] ?? 0 );
		if ( $trial_num > 0 ) {
			$trial_unit = isset( $row['plan_data']['free_trial_interval'] ) ? (string) $row['plan_data']['free_trial_interval'] : 'days';
			$trial_word = strtolower( subscrpt_get_typos( $trial_num, $trial_unit ) );
			$trial_html = '<small class="wpsubs-plan-trial"> + ' . sprintf(
				/* translators: 1: number, 2: unit (day/week/month/year). */
				esc_html__( '%1$d %2$s free trial', 'subscription' ),
				$trial_num,
				esc_html( $trial_word )
			) . '</small>';
		}

		return wc_get_template_html(
			'product/plan-selector.php',
			array(
				'price_html'   => wc_price( $price ),
				'period_label' => $period,
				'trial_html'   => $trial_html,
				'plan_id'      => (int) $row['plan_id'],
			),
			'subscription',
			SUBSCRPT_TEMPLATES
		);
	}

	/**
	 * Output the hidden plan-id field inside the add-to-cart form so the chosen
	 * plan travels on the add-to-cart request. Checkout consumption reads it.
	 *
	 * @return void
	 */
	public function add_to_cart_plan_field() {
		global $product;

		$row = $this->plan_row( $product );
		if ( null === $row ) {
			return;
		}

		printf(
			'<input type="hidden" name="subscrpt_plan_id" value="%d" />',
			(int) $row['plan_id']
		);
	}
}
