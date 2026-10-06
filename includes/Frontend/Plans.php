<?php
/**
 * Storefront plan selector (free).
 *
 * Renders the plan selector — a radio card per plan group, with the group's
 * terms as a radio group — on a product tied to a plan, and carries the chosen
 * plan-term id onto the add-to-cart request. A variable product gets an empty
 * container; each variation's selector is rendered on the server into its
 * `woocommerce_available_variation` data and swapped in by plans.js. Guarded by `subscrpt_plan_offered()`:
 * with no tied plan this class does nothing and the classic price suffix stands.
 *
 * The single source of the purchase options, with or without Pro; Pro may add
 * to them through the `subscrpt_plan_term` and `subscrpt_plan_selector_groups`
 * filters. Two things depend on Pro: a variable product's options render only
 * with it, since free's checkout cannot sell a variation's plan, and the plan
 * price HTML is free's only without it, since Pro rewrites the same price.
 *
 * The storefront never calls REST; plan data is read directly through
 * `PlanRepository::resolve_for_product()` (object cache → DB).
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Frontend;

use SpringDevs\Subscription\Admin\PlanPresenter;
use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * Frontend plan selector for simple and variable products.
 */
class Plans {

	/**
	 * Register storefront hooks.
	 */
	public function __construct() {
		// Runs after Frontend\Product::change_price_html (priority 10) so the plan
		// price replaces the classic suffix rather than appending to it. Pro filters
		// the same price at the same priority, so with Pro it is Pro's alone.
		if ( ! subscrpt_pro_activated() ) {
			add_filter( 'woocommerce_get_price_html', array( $this, 'plan_price_html' ), 20, 2 );
		}
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_selector' ) );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'push_variation_html' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Whether the plan selector should render on this request.
	 *
	 * @return bool
	 */
	private function should_render() {
		return function_exists( 'is_product' ) && is_product();
	}

	/**
	 * Whether a simple or variable product offers a subscription: tied to a plan
	 * AND subscription-enabled (for a variable product, on any variation).
	 *
	 * A variable product counts only with Pro active: free's checkout cannot sell
	 * a variation's plan, even when relations are left over from Pro.
	 *
	 * @param mixed $product Product object.
	 *
	 * @return bool
	 */
	public static function product_has_plans( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) && ! subscrpt_pro_activated() ) {
			return false;
		}

		return $product->is_type( array( 'simple', 'variable' ) )
			&& subscrpt_plan_offered( $product->get_id() );
	}

	/**
	 * Enqueue selector assets on product pages that expose plans.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->should_render() ) {
			return;
		}

		// global $product is not set yet at wp_enqueue_scripts; resolve from the query.
		$product = wc_get_product( get_queried_object_id() );
		if ( ! self::product_has_plans( $product ) ) {
			return;
		}

		wp_enqueue_style(
			'subscrpt_plans_selector_css',
			SUBSCRPT_ASSETS . '/css/frontend/plans.css',
			array(),
			SUBSCRPT_VERSION
		);

		wp_enqueue_script(
			'subscrpt_plans_selector_js',
			SUBSCRPT_ASSETS . '/js/frontend/plans.js',
			array( 'jquery' ),
			SUBSCRPT_VERSION,
			true
		);
	}

	/**
	 * Replace the price HTML with the resolved plan price when a plan is tied;
	 * otherwise return the price unchanged (classic suffix stands).
	 *
	 * A single tied plan shows the offer price (regular struck-through when
	 * discounted); multiple plans show a "min – max" range across every term. No
	 * cadence — the selector below lists each term's cadence.
	 *
	 * @param string            $price_html Price HTML.
	 * @param \WC_Product|mixed $product    Product.
	 *
	 * @return string
	 */
	public function plan_price_html( $price_html, $product ) {
		// A variable product's price stays WooCommerce's own range.
		if ( ! self::product_has_plans( $product ) || ! $product->is_type( 'simple' ) ) {
			return $price_html;
		}

		$rows = PlanRepository::resolve_for_product( $product->get_id() );
		if ( empty( $rows ) ) {
			return $price_html;
		}

		// Single plan: offer price, with the regular struck-through when discounted.
		if ( 1 === count( $rows ) ) {
			$row     = $rows[0];
			$data    = is_array( $row['relation_data'] ) ? $row['relation_data'] : array();
			$regular = isset( $data['regular_price'] ) && '' !== $data['regular_price'] ? (float) $data['regular_price'] : null;
			$offer   = $this->term_price( $row );

			return ( null !== $regular && $offer < $regular )
				? '<del aria-hidden="true">' . wc_price( $regular ) . '</del> <ins>' . wc_price( $offer ) . '</ins>'
				: wc_price( $offer );
		}

		// Multiple plans: a min–max range across every attached term.
		$prices = array();
		foreach ( $rows as $row ) {
			$prices[] = $this->term_price( $row );
		}

		$min = min( $prices );
		$max = max( $prices );

		// All plans the same price is not a range; show nothing (selector lists each).
		return $min === $max
			? ''
			: wc_price( $min ) . ' &ndash; ' . wc_price( $max );
	}

	/**
	 * Render the plan selector inside the add-to-cart form.
	 *
	 * @return void
	 */
	public function render_selector() {
		if ( ! $this->should_render() ) {
			return;
		}

		global $product;
		if ( ! self::product_has_plans( $product ) ) {
			return;
		}

		// Filled by plans.js from the chosen variation's `subscrpt_plans_html`.
		if ( $product->is_type( 'variable' ) ) {
			echo '<div class="subscrpt-buybox" data-subscrpt-buybox data-subscrpt-variable="1" data-subscrpt-layout="' . esc_attr( self::layout_for( $product ) ) . '">';
			echo '<p class="subscrpt-buybox__placeholder">' . esc_html__( 'Select options to see available plans.', 'subscription' ) . '</p>';
			echo '</div>';
			return;
		}

		$groups = $this->build_groups( $product );
		if ( empty( $groups ) ) {
			return;
		}

		echo self::selector_html( $groups, $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the template.
	}

	/**
	 * The plan selector markup for a set of groups.
	 *
	 * @param array       $groups  Groups from `PlanGroups`.
	 * @param \WC_Product $product Product, or the variation on the variation path.
	 * @param string      $context Where the selector renders: 'page' or 'variation'.
	 *
	 * @return string
	 */
	public static function selector_html( array $groups, \WC_Product $product, string $context = 'page' ): string {
		return wc_get_template_html(
			'product/plan-selector.php',
			array(
				'groups'  => $groups,
				'product' => $product,
				'context' => $context,
			),
			'subscription',
			SUBSCRPT_TEMPLATES
		);
	}

	/**
	 * The purchase option layouts: key => partial, relative to the templates
	 * directory and overridable from a theme's `subscription/` like any template.
	 *
	 * @return array
	 */
	public static function layouts(): array {
		$defaults = [
			'stacked'  => 'product/plan-selector/stacked.php',
			'classic'  => 'product/plan-selector/classic.php',
			'dropdown' => 'product/plan-selector/dropdown.php',
		];

		/**
		 * Filters the purchase option layouts a product page can use.
		 *
		 * `stacked` is the fallback for any layout not in the list, so it is
		 * always kept.
		 *
		 * @param array $layouts Layout key => template path, relative to the plugin's
		 *                       `templates/` and overridable from the theme.
		 */
		$layouts = apply_filters( 'subscrpt_plan_selector_layouts', $defaults );
		$layouts = is_array( $layouts ) ? array_filter( $layouts, 'is_string' ) : [];

		return array_merge( [ 'stacked' => $defaults['stacked'] ], $layouts );
	}

	/**
	 * The layout a product's purchase options render in — the one place it is
	 * decided: the product's own choice, else the store's, else stacked. A value
	 * that names no known layout is skipped, so a stale override falls back to
	 * the store's layout.
	 *
	 * @param \WC_Product $product Product, or a variation (its parent decides).
	 * @param array|null  $layouts `layouts()`, when the caller already has it.
	 *
	 * @return string
	 */
	public static function layout_for( \WC_Product $product, ?array $layouts = null ): string {
		$layouts = null === $layouts ? self::layouts() : $layouts;
		$id      = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();

		foreach ( [ get_post_meta( $id, '_subscrpt_plan_selector_layout', true ), get_option( 'subscrpt_plan_selector_layout', '' ) ] as $layout ) {
			if ( is_string( $layout ) && '' !== $layout && array_key_exists( $layout, $layouts ) ) {
				return $layout;
			}
		}

		return 'stacked';
	}

	/**
	 * Add a variation's rendered plan selector to its variation data.
	 *
	 * Never gated on `is_product()`: above WooCommerce's variation threshold the
	 * data loads over `wc-ajax=get_variation`, where it is false.
	 *
	 * @param array                 $data           Variation data passed to JS.
	 * @param \WC_Product           $parent_product Variable product.
	 * @param \WC_Product_Variation $variation      Variation.
	 *
	 * @return array
	 */
	public static function push_variation_html( $data, $parent_product, $variation ) {
		if ( ! $variation instanceof \WC_Product ) {
			return $data;
		}

		// No container on the page to swap the cards into.
		if ( ! self::product_has_plans( $parent_product ) || ! $parent_product->is_type( 'variable' ) ) {
			return $data;
		}

		$groups = PlanGroups::for_product( $variation, $parent_product->get_id(), 'variation' );
		if ( empty( $groups ) ) {
			return $data;
		}

		$data['subscrpt_plans_html'] = self::selector_html( $groups, $variation, 'variation' );

		return $data;
	}

	/**
	 * Build the selector groups for a simple product.
	 *
	 * @param \WC_Product $product Simple product.
	 *
	 * @return array
	 */
	private function build_groups( $product ) {
		return PlanGroups::for_product( $product );
	}

	/**
	 * Compute the numeric offer price for a resolved plan term.
	 *
	 * @param array $row Resolved plan row.
	 *
	 * @return float
	 */
	private function term_price( $row ) {
		$data    = is_array( $row['relation_data'] ) ? $row['relation_data'] : array();
		$regular = isset( $data['regular_price'] ) ? (string) $data['regular_price'] : '';
		$selling = isset( $data['sale_price'] ) ? (string) $data['sale_price'] : '';
		$dtype   = isset( $data['discount_type'] ) ? (string) $data['discount_type'] : 'percentage';
		$dvalue  = isset( $data['discount_value'] ) ? (string) $data['discount_value'] : '0';

		return (float) PlanPresenter::offer_price( $regular, $selling, $dtype, $dvalue );
	}
}
