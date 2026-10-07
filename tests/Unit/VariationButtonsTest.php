<?php
/**
 * Tests for variations shown as buttons.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

require_once dirname( __DIR__ ) . '/Support/variation-buttons-fixtures.php';

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Admin\SettingsHelper;
use SpringDevs\Subscription\Frontend\VariationButtons;
use SpringDevs\Subscription\Tests\Unit\VariationButtonsFixtures as F;

/**
 * The button group beside a variable product's attribute dropdown.
 *
 * Buttons appear exactly when the plan cards do, and a variable product's cards
 * need pro, so every test that expects buttons reads
 * tests/Support/variation-buttons-with-pro.php, run in a process of its own.
 */
class VariationButtonsTest extends TestCase {

	/**
	 * A clean slate: no listeners, options, products or plans.
	 */
	protected function setUp(): void {
		F::reset();
	}

	/**
	 * Every scenario with pro active, from a separate PHP process.
	 *
	 * @return array Scenario => the filter's output.
	 */
	private static function with_pro(): array {
		static $result = null;

		if ( null === $result ) {
			$script = dirname( __DIR__ ) . '/Support/variation-buttons-with-pro.php';
			$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
			$result = json_decode( (string) $output, true );
			self::assertIsArray( $result, 'variation-buttons-with-pro.php printed: ' . $output );
			self::assertTrue( $result['pro'] );
		}

		return $result;
	}

	/** Without pro a variable product shows no cards, so it gets no buttons either. */
	public function test_without_pro_a_variable_product_gets_no_buttons() {
		$this->assertFalse( subscrpt_pro_activated() );
		$this->assertSame( F::dropdown(), F::render( F::product() ) );
	}

	/** An older pro, which draws its own selector, gets no buttons from free. */
	public function test_with_an_older_pro_the_product_gets_no_buttons() {
		$this->assertStringNotContainsString( 'role="radiogroup"', self::with_pro()['old_pro'] );
	}

	/** With pro active the same product gets them. */
	public function test_with_pro_the_same_product_gets_buttons() {
		$this->assertStringContainsString( 'role="radiogroup"', self::with_pro()['default'] );
	}

	/** One button per option, the select kept, the chosen option marked. */
	public function test_buttons_render_for_each_option_of_a_product_with_plans() {
		$out = self::with_pro()['default'];

		$this->assertStringStartsWith( F::dropdown(), $out, 'the select is kept, untouched' );
		$this->assertSame( 3, substr_count( $out, 'role="radio"' ) );
		$this->assertStringContainsString( 'role="radiogroup"', $out );
		$this->assertStringContainsString( 'data-select="size"', $out );
		$this->assertStringContainsString( 'data-value="medium"', $out );
		$this->assertStringContainsString( 'Medium &amp; more', $out, 'the label is the select\'s, escaped once' );
		$this->assertSame( 1, substr_count( $out, 'aria-checked="true"' ) );
		$this->assertMatchesRegularExpression( '/aria-checked="true"[^>]*data-value="small"|data-value="small"[^>]*aria-checked="true"/', $out );
		$this->assertStringNotContainsString( 'aria-disabled', $out );
	}

	/** A variation out of stock, blocked or hidden disables its button, with the reason as text and title. */
	public function test_disabled_when_not_purchasable_or_out_of_stock_with_reason() {
		$all = self::with_pro();
		$out = $all['out_of_stock'];

		$this->assertSame( 1, substr_count( $out, 'aria-disabled="true"' ) );
		$this->assertMatchesRegularExpression( '/<button[^>]*data-value="medium"[^>]*aria-disabled="true"[^>]*title="Out of stock"|<button[^>]*aria-disabled="true"[^>]*data-value="medium"[^>]*title="Out of stock"/', $out );
		$this->assertMatchesRegularExpression( '/subscrpt-varbtn__reason[^>]*>[^<]*Out of stock/', $out, 'the reason is read out, not only a tooltip' );

		foreach ( [ 'not_purchasable', 'not_visible' ] as $filtered ) {
			$this->assertSame( 3, substr_count( $all[ $filtered ], 'aria-disabled="true"' ), $filtered . ' is honoured' );
			$this->assertSame( 3, substr_count( $all[ $filtered ], 'title="Unavailable"' ) );
		}
	}

	/** An option no variation is for cannot be bought either. */
	public function test_an_option_with_no_variation_is_unavailable() {
		$out = self::with_pro()['missing'];

		$this->assertSame( 1, substr_count( $out, 'aria-disabled="true"' ) );
		$this->assertMatchesRegularExpression( '/data-value="medium"[^>]*aria-disabled="true"|aria-disabled="true"[^>]*data-value="medium"/', $out );
	}

	/** A variation for any size keeps every size open. */
	public function test_a_variation_for_any_value_keeps_the_option_open() {
		$this->assertStringNotContainsString( 'aria-disabled', self::with_pro()['any_value'] );
	}

	/** Another callback on the dropdown filter means the theme owns the control; its own registration does not count. */
	public function test_skipped_when_another_callback_filters_the_dropdown() {
		$all = self::with_pro();

		$this->assertStringContainsString( 'role="radiogroup"', $all['alone_on_filter'], 'alone on the filter, it renders' );
		$this->assertSame( F::dropdown(), $all['other_callback'], 'with a swatch plugin on the filter, it leaves the dropdown alone' );
	}

	/** The setting off, or a product with no plans, renders nothing. */
	public function test_setting_off_renders_nothing() {
		$all = self::with_pro();

		$this->assertSame( F::dropdown(), $all['off_empty'] );
		$this->assertSame( F::dropdown(), $all['off_no'] );
		$this->assertStringContainsString( 'role="radiogroup"', $all['on'] );
		$this->assertStringContainsString( 'role="radiogroup"', $all['on_by_default'], 'on by default' );
		$this->assertSame( F::dropdown(), $all['no_plans'], 'no plans, no buttons' );
	}

	/** Simple products and non-product args pass through. */
	public function test_other_input_passes_through() {
		$buttons = new VariationButtons();
		$html    = F::dropdown();

		$this->assertSame( $html, $buttons->render( $html, [] ) );
		$this->assertSame( $html, $buttons->render( $html, [ 'attribute' => 'size', 'product' => new \WC_Product( 5 ) ] ) );
		$this->assertSame( 'not a select', $buttons->render( 'not a select', [ 'attribute' => 'size', 'product' => F::product() ] ) );
	}

	/** The setting is a toggle in the Product page group, defaulting on. */
	public function test_the_setting_is_a_field_of_the_product_page_group() {
		$fields = ( new VariationButtons() )->add_settings_fields( [] );

		// The group's heading is ProductPageSettings', so the group has exactly one.
		$heading = array_values( array_filter( $fields, static fn( $f ) => 'heading' === $f['type'] ) );
		$this->assertCount( 0, $heading );

		$toggle = array_values( array_filter( $fields, static fn( $f ) => 'toggle' === $f['type'] ) );
		$this->assertCount( 1, $toggle );
		$this->assertSame( 'product_page', $toggle[0]['group'] );
		$this->assertSame( 'subscrpt_variation_buttons', $toggle[0]['field_data']['id'] );
		$this->assertSame( 'yes', $toggle[0]['field_data']['value'] );
		$this->assertTrue( $toggle[0]['field_data']['checked'], 'on until saved off' );

		$GLOBALS['wp_options']['subscrpt_variation_buttons'] = '';
		$fields = ( new VariationButtons() )->add_settings_fields( [] );
		$toggle = array_values( array_filter( $fields, static fn( $f ) => 'toggle' === $f['type'] ) );
		$this->assertFalse( $toggle[0]['field_data']['checked'], 'saved off stays off' );
	}

	/** An unmapped group would fall into Advanced; this one has a section of its own. */
	public function test_product_page_group_maps_to_a_category() {
		$category = SettingsHelper::group_category( 'product_page' );

		$this->assertNotSame( SettingsHelper::group_category( 'a_group_nobody_mapped' ), $category );
		$this->assertArrayHasKey( $category, SettingsHelper::categories() );
		$this->assertSame( 'Product page', SettingsHelper::categories()[ $category ] );
	}
}
