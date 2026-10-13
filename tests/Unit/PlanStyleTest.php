<?php
/**
 * Tests for the purchase options' colours and shape.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Frontend\PlanStyle;

/**
 * The store's colour and radius settings become CSS custom properties on
 * `.subscrpt-buybox`, and a value that is not a colour never reaches the page.
 */
class PlanStyleTest extends TestCase {

	/**
	 * Start every test with no options, listeners or recorded hooks.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_options']        = [];
		$GLOBALS['wp_filter_returns'] = [];
		$GLOBALS['wp_hooks_registry'] = [];
		$GLOBALS['applied_filters']   = [];
	}

	/**
	 * Leave nothing behind for the next test class.
	 */
	protected function tearDown(): void {
		$GLOBALS['wp_options']        = [];
		$GLOBALS['wp_hooks_registry'] = [];
	}

	/**
	 * Every setting prints as its own variable, in a fixed order.
	 */
	public function test_css_variables_from_settings() {
		$GLOBALS['wp_options'] = [
			'subscrpt_plan_color_accent'     => '#112233',
			'subscrpt_plan_color_accent_ink' => '#ffffff',
			'subscrpt_plan_color_ink'        => '#111111',
			'subscrpt_plan_color_border'     => '#cccccc',
			'subscrpt_plan_radius'           => '12',
			'subscrpt_plan_color_badge'      => '#ff0000',
			'subscrpt_plan_color_ribbon'     => '#00ff00',
		];

		$this->assertSame(
			'.subscrpt-buybox{--subscrpt-accent:#112233;--subscrpt-accent-ink:#ffffff;--subscrpt-ink:#111111;--subscrpt-border:#cccccc;--subscrpt-radius:12px;--subscrpt-badge:#ff0000;--subscrpt-ribbon:#00ff00}',
			PlanStyle::inline_css()
		);
	}

	/**
	 * A setting left empty prints nothing, so the stylesheet's default stands.
	 */
	public function test_unset_settings_print_nothing() {
		$this->assertSame( '', PlanStyle::inline_css() );

		$GLOBALS['wp_options'] = [ 'subscrpt_plan_color_accent' => '#abc' ];
		$this->assertSame( '.subscrpt-buybox{--subscrpt-accent:#abc}', PlanStyle::inline_css() );
	}

	/**
	 * Anything that is not a hex colour, or a radius outside 0-40, is dropped:
	 * the value is printed into a stylesheet, so it must not carry CSS of its own.
	 */
	public function test_invalid_colour_ignored() {
		$GLOBALS['wp_options'] = [
			'subscrpt_plan_color_accent'     => 'red',
			'subscrpt_plan_color_accent_ink' => '#fff;}body{display:none',
			'subscrpt_plan_color_ink'        => 'url(javascript:alert(1))',
			'subscrpt_plan_color_border'     => '#12',
			'subscrpt_plan_color_badge'      => '</style><script>',
			'subscrpt_plan_color_ribbon'     => '#00ff00',
			'subscrpt_plan_radius'           => '12px;color:red',
		];

		$this->assertSame( '.subscrpt-buybox{--subscrpt-ribbon:#00ff00}', PlanStyle::inline_css() );

		$GLOBALS['wp_options'] = [ 'subscrpt_plan_radius' => '900' ];
		$this->assertSame( '.subscrpt-buybox{--subscrpt-radius:40px}', PlanStyle::inline_css() );
	}

	/**
	 * A radius of zero is a real choice: square corners.
	 */
	public function test_zero_radius_is_printed() {
		$GLOBALS['wp_options'] = [ 'subscrpt_plan_radius' => '0' ];

		$this->assertSame( '.subscrpt-buybox{--subscrpt-radius:0px}', PlanStyle::inline_css() );
	}

	/**
	 * Another plugin can add to the CSS, through the filter.
	 */
	public function test_css_goes_through_the_filter() {
		add_filter(
			'subscrpt_plan_selector_inline_css',
			static function ( $css ) {
				return $css . '.x{color:red}';
			}
		);

		$this->assertSame( '.x{color:red}', PlanStyle::inline_css() );
		$this->assertArrayHasKey( 'subscrpt_plan_selector_inline_css', $GLOBALS['applied_filters'] );
	}

	/**
	 * The layout setting keeps a known layout and nothing else.
	 */
	public function test_layout_setting_keeps_known_layouts_only() {
		$this->assertSame( 'grid', PlanStyle::sanitize_layout( 'grid' ) );
		$this->assertSame( '', PlanStyle::sanitize_layout( 'carousel' ) );
		$this->assertSame( '', PlanStyle::sanitize_layout( [ 'grid' ] ) );
	}

	/**
	 * The interval setting is chips or a dropdown.
	 */
	public function test_intervals_setting_is_chips_or_dropdown() {
		$this->assertSame( 'dropdown', PlanStyle::sanitize_intervals( 'dropdown' ) );
		$this->assertSame( 'chips', PlanStyle::sanitize_intervals( 'tabs' ) );
	}
}
