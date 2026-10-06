<?php
/**
 * Variations as buttons (free).
 *
 * On a variable product that sells a plan, shows each attribute's options as a
 * radio-like button group instead of a dropdown. The dropdown stays, visually
 * hidden: the buttons only set its value, so WooCommerce's own variation
 * script, stock messages, images and price keep driving the page.
 *
 * Skips a product when the theme or another plugin already takes over the
 * dropdown, which is to say when any other callback sits on
 * `woocommerce_dropdown_variation_attribute_options_html`. variation-buttons.js
 * makes the same call in the browser, for a select that is hidden on load.
 *
 * Registered always, not only on the storefront: the setting lives on the
 * admin settings screen.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Frontend;

/**
 * Attribute dropdowns rendered as buttons, behind a setting.
 */
class VariationButtons {

	/**
	 * WooCommerce's filter on the attribute dropdown's markup.
	 */
	const HOOK = 'woocommerce_dropdown_variation_attribute_options_html';

	/**
	 * Option name of the setting; 'yes' is on, and it is on until saved otherwise.
	 */
	const OPTION = 'subscrpt_variation_buttons';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		// Late, so every callback that means to replace the dropdown has run.
		add_filter( self::HOOK, array( $this, 'render' ), 99, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'subscrpt_settings_fields', array( $this, 'add_settings_fields' ) );
		add_action( 'subscrpt_register_settings', array( $this, 'register_settings' ) );
	}

	/**
	 * Whether the setting is on.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return 'yes' === get_option( self::OPTION, 'yes' );
	}

	/**
	 * Whether a product gets buttons: a variable product whose plan cards show
	 * (`Plans::product_has_plans()`, which for a variable product needs pro),
	 * with the setting on.
	 *
	 * @param mixed $product Product object.
	 *
	 * @return bool
	 */
	private static function applies( $product ): bool {
		return $product instanceof \WC_Product
			&& $product->is_type( 'variable' )
			&& self::is_enabled()
			&& Plans::product_has_plans( $product );
	}

	/**
	 * Whether any callback besides this class's own is on the dropdown filter.
	 *
	 * @return bool
	 */
	private static function another_callback_filters_dropdown(): bool {
		global $wp_filter;

		$hook = $wp_filter[ self::HOOK ] ?? null;
		if ( empty( $hook->callbacks ) ) {
			return false;
		}

		foreach ( $hook->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'] ?? null;
				if ( is_array( $function ) && $function[0] instanceof self ) {
					continue;
				}
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueue the script and style on a product page that gets buttons.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		// global $product is not set yet at wp_enqueue_scripts; resolve from the query.
		if ( ! self::applies( wc_get_product( get_queried_object_id() ) ) || self::another_callback_filters_dropdown() ) {
			return;
		}

		wp_enqueue_style(
			'subscrpt_variation_buttons_css',
			SUBSCRPT_ASSETS . '/css/frontend/variation-buttons.css',
			array(),
			SUBSCRPT_VERSION
		);

		wp_enqueue_script(
			'subscrpt_variation_buttons_js',
			SUBSCRPT_ASSETS . '/js/frontend/variation-buttons.js',
			array( 'jquery' ),
			SUBSCRPT_VERSION,
			true
		);
	}

	/**
	 * Append the button group to an attribute's dropdown.
	 *
	 * @param string $html Markup of WooCommerce's `<select>`.
	 * @param array  $args The dropdown's arguments: `attribute`, `product`, ….
	 *
	 * @return string The dropdown, followed by its buttons; unchanged when this
	 *                product, or this dropdown, is not ours to change.
	 */
	public function render( $html, $args = array() ) {
		$product = $args['product'] ?? null;
		if ( ! is_string( $html ) || ! self::applies( $product ) || self::another_callback_filters_dropdown() ) {
			return $html;
		}

		if ( ! preg_match( '/<select\b[^>]*\sid="([^"]*)"/', $html, $select ) ) {
			return $html;
		}

		preg_match_all( '/<option\s+value=(["\'])(.*?)\1([^>]*)>(.*?)<\/option>/s', $html, $found, PREG_SET_ORDER );

		$attribute = (string) ( $args['attribute'] ?? '' );
		$variants  = $this->variants( $product, $attribute );
		$buttons   = '';

		foreach ( $found as $option ) {
			$value = html_entity_decode( $option[2], ENT_QUOTES );
			if ( '' === $value ) {
				continue;
			}

			$label    = html_entity_decode( wp_strip_all_tags( $option[4] ), ENT_QUOTES );
			$selected = (bool) preg_match( '/\bselected\b/', $option[3] );
			$reason   = $this->unavailable_reason( $variants, $value );

			$buttons .= $this->button( $value, $label, $selected, $reason );
		}

		if ( '' === $buttons ) {
			return $html;
		}

		return $html . sprintf(
			'<div class="subscrpt-varbtns" role="radiogroup" aria-label="%1$s" data-subscrpt-variation-buttons data-select="%2$s" data-unavailable-label="%3$s" hidden>%4$s</div>',
			esc_attr( wc_attribute_label( $attribute, $product ) ),
			esc_attr( $select[1] ),
			esc_attr( $this->reason_text( 'unavailable' ) ),
			$buttons
		);
	}

	/**
	 * One button.
	 *
	 * @param string $value    Option value.
	 * @param string $label    Option label, as text.
	 * @param bool   $selected Whether it is the chosen option.
	 * @param string $reason   Why it cannot be chosen: 'out_of_stock', 'unavailable', or ''.
	 *                         Every button starts out of the Tab order; variation-buttons.js
	 *                         gives the group its one stop.
	 *
	 * @return string
	 */
	private function button( string $value, string $label, bool $selected, string $reason ): string {
		$disabled = '' !== $reason;
		$text     = $disabled ? $this->reason_text( $reason ) : '';

		return sprintf(
			'<button type="button" role="radio" class="subscrpt-varbtn%1$s" data-value="%2$s" aria-checked="%3$s" tabindex="-1"%4$s><span class="subscrpt-varbtn__label">%5$s</span>%6$s</button>',
			$selected ? ' is-selected' : '',
			esc_attr( $value ),
			$selected ? 'true' : 'false',
			$disabled ? ' aria-disabled="true" title="' . esc_attr( $text ) . '"' : '',
			esc_html( $label ),
			$disabled ? '<span class="subscrpt-varbtn__reason">' . esc_html( $text ) . '</span>' : ''
		);
	}

	/**
	 * The words for a reason.
	 *
	 * @param string $reason 'out_of_stock' or 'unavailable'.
	 *
	 * @return string
	 */
	private function reason_text( string $reason ): string {
		return 'out_of_stock' === $reason
			? __( 'Out of stock', 'subscription' )
			: __( 'Unavailable', 'subscription' );
	}

	/**
	 * What each variation says about an attribute and whether it can be bought.
	 *
	 * Asks WooCommerce's own methods, which apply `woocommerce_variation_is_visible`
	 * and `woocommerce_variation_is_purchasable`, so a store's rules for hiding or
	 * blocking a variation reach the buttons without this class repeating them.
	 *
	 * @param \WC_Product $product   Variable product.
	 * @param string      $attribute Attribute name.
	 *
	 * @return array[] Per variation: `value` (slug, '' for any), `buyable`, `in_stock`.
	 */
	private function variants( \WC_Product $product, string $attribute ): array {
		$key      = 'attribute_' . sanitize_title( $attribute );
		$variants = array();

		foreach ( $product->get_children() as $id ) {
			$variation = wc_get_product( $id );
			if ( ! $variation instanceof \WC_Product ) {
				continue;
			}

			$attributes = $variation->get_variation_attributes();
			if ( ! array_key_exists( $key, $attributes ) ) {
				continue;
			}

			$variants[] = array(
				'value'    => sanitize_title( (string) $attributes[ $key ] ),
				'buyable'  => $variation->variation_is_visible() && $variation->is_purchasable(),
				'in_stock' => $variation->is_in_stock(),
			);
		}

		return $variants;
	}

	/**
	 * Why an option cannot be chosen.
	 *
	 * @param array[] $variants From `variants()`.
	 * @param string  $value    Option value.
	 *
	 * @return string 'out_of_stock' when every variation that could be bought is
	 *                sold out, 'unavailable' when none can be, '' when one can.
	 */
	private function unavailable_reason( array $variants, string $value ): string {
		$slug     = sanitize_title( $value );
		$buyable  = 0;
		$in_stock = 0;

		foreach ( $variants as $variant ) {
			// A variation "for any value" covers every option.
			if ( '' !== $variant['value'] && $slug !== $variant['value'] ) {
				continue;
			}
			if ( $variant['buyable'] ) {
				++$buyable;
				$in_stock += $variant['in_stock'] ? 1 : 0;
			}
		}

		if ( $in_stock > 0 ) {
			return '';
		}

		return $buyable > 0 ? 'out_of_stock' : 'unavailable';
	}

	/**
	 * Add the setting to the Product page group of the settings screen. The
	 * group's heading is added by `Admin\ProductPageSettings`.
	 *
	 * @param array $settings_fields Settings fields.
	 *
	 * @return array
	 */
	public function add_settings_fields( $settings_fields ) {
		$settings_fields[] = array(
			'type'       => 'toggle',
			'group'      => 'product_page',
			'priority'   => 20,
			'field_data' => array(
				'id'          => self::OPTION,
				'title'       => __( 'Show variations as buttons', 'subscription' ),
				'description' => __( 'On a variable product that sells a plan, show each attribute as buttons instead of a dropdown. Options that are out of stock or unavailable are disabled, with the reason. A theme that already replaces the dropdown is left alone.', 'subscription' ),
				'value'       => 'yes',
				'checked'     => self::is_enabled(),
			),
		);

		return $settings_fields;
	}

	/**
	 * Register the setting.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'wp_subscription_settings',
			self::OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);
	}
}
