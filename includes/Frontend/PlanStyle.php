<?php
/**
 * The colours and shape of the purchase options.
 *
 * The store's settings — Settings > Product page — become CSS custom properties
 * on `.subscrpt-buybox`, printed after plans.css so they win over its defaults.
 * Every value is validated on the way in (`register_setting`) and again on the
 * way out, so what reaches the stylesheet is a hex colour or a number, never CSS.
 *
 * Which layout a product uses is not decided here: `Plans::layout_for()` is the
 * one place for that.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Inline CSS for the purchase options, from the store's settings.
 */
class PlanStyle {

	/**
	 * Colour settings: option name => CSS custom property, in print order.
	 */
	const COLOURS = [
		'subscrpt_plan_color_accent'     => '--subscrpt-accent',
		'subscrpt_plan_color_accent_ink' => '--subscrpt-accent-ink',
		'subscrpt_plan_color_ink'        => '--subscrpt-ink',
		'subscrpt_plan_color_border'     => '--subscrpt-border',
	];

	/**
	 * More colours, printed after the radius.
	 */
	const BADGE_COLOURS = [
		'subscrpt_plan_color_badge'  => '--subscrpt-badge',
		'subscrpt_plan_color_ribbon' => '--subscrpt-ribbon',
	];

	/**
	 * Option name of the corner radius, in pixels.
	 */
	const RADIUS = 'subscrpt_plan_radius';

	/**
	 * Largest corner radius the setting accepts, in pixels.
	 */
	const MAX_RADIUS = 40;

	/**
	 * Register hooks.
	 */
	public function __construct() {
		// After Plans::enqueue_assets (10), which registers the stylesheet this adds to.
		add_action( 'wp_enqueue_scripts', [ $this, 'add_inline_style' ], 20 );
	}

	/**
	 * Print the store's colours after the selector stylesheet, when it is on the page.
	 *
	 * @return void
	 */
	public function add_inline_style() {
		if ( ! wp_style_is( 'subscrpt_plans_selector_css', 'enqueued' ) ) {
			return;
		}

		$css = self::inline_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'subscrpt_plans_selector_css', $css );
		}
	}

	/**
	 * The CSS the store's settings produce.
	 *
	 * @return string `.subscrpt-buybox{--subscrpt-accent:#…;…}`, or an empty string
	 *                when no setting is set (and nothing else adds to it).
	 */
	public static function inline_css(): string {
		$declarations = '';

		foreach ( self::COLOURS as $option => $property ) {
			$declarations .= self::colour_declaration( $option, $property );
		}

		$radius = self::radius( get_option( self::RADIUS, '' ) );
		if ( null !== $radius ) {
			$declarations .= '--subscrpt-radius:' . $radius . 'px;';
		}

		foreach ( self::BADGE_COLOURS as $option => $property ) {
			$declarations .= self::colour_declaration( $option, $property );
		}

		$css = '' === $declarations ? '' : '.subscrpt-buybox{' . rtrim( $declarations, ';' ) . '}';

		/**
		 * Filters the CSS printed after the purchase options' stylesheet.
		 *
		 * Pro appends the merchant's custom CSS here. A callback must return
		 * plain CSS: it is printed inside a `<style>` element, so anything that
		 * could close the element must have been removed.
		 *
		 * @param string $css The store's colour and radius settings as CSS, or ''.
		 */
		$css = apply_filters( 'subscrpt_plan_selector_inline_css', $css );

		return is_string( $css ) ? $css : '';
	}

	/**
	 * One custom property declaration, '' when the option is empty or not a colour.
	 *
	 * @param string $option   Option name.
	 * @param string $property CSS custom property.
	 *
	 * @return string
	 */
	private static function colour_declaration( string $option, string $property ): string {
		$colour = self::sanitize_colour( get_option( $option, '' ) );

		return '' === $colour ? '' : $property . ':' . $colour . ';';
	}

	/**
	 * A hex colour, or an empty string for anything else.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	public static function sanitize_colour( $value ): string {
		$colour = is_string( $value ) ? sanitize_hex_color( trim( $value ) ) : '';

		return is_string( $colour ) ? $colour : '';
	}

	/**
	 * The corner radius, in pixels.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return int|null Zero to `MAX_RADIUS`; null for empty or not a whole number.
	 */
	private static function radius( $value ) {
		$value = is_int( $value ) ? (string) $value : $value;
		if ( ! is_string( $value ) || ! ctype_digit( $value ) ) {
			return null;
		}

		return min( self::MAX_RADIUS, absint( $value ) );
	}

	/**
	 * Sanitise the radius setting: a whole number of pixels, or empty.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	public static function sanitize_radius( $value ): string {
		$radius = self::radius( is_string( $value ) ? trim( $value ) : $value );

		return null === $radius ? '' : (string) $radius;
	}

	/**
	 * Sanitise the layout setting: a layout that exists, or empty for stacked.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	public static function sanitize_layout( $value ): string {
		return is_string( $value ) && array_key_exists( $value, Plans::layouts() ) ? $value : '';
	}

	/**
	 * Sanitise the interval display setting.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string 'chips' or 'dropdown'.
	 */
	public static function sanitize_intervals( $value ): string {
		return 'dropdown' === $value ? 'dropdown' : 'chips';
	}
}
