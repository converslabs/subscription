<?php
/**
 * Tests for the Product page settings.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Admin\ProductPageSettings;
use SpringDevs\Subscription\Frontend\Plans;

/**
 * Settings > Product page: the layout, how intervals list, the colours, and a
 * preview that shows them.
 */
class ProductPageSettingsTest extends TestCase {

	/**
	 * Start every test with no options or registered settings.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_options']          = [];
		$GLOBALS['registered_settings'] = [];
		$GLOBALS['wp_hooks_registry']   = [];
	}

	/**
	 * Leave nothing behind for the next test class.
	 */
	protected function tearDown(): void {
		$GLOBALS['wp_options']          = [];
		$GLOBALS['registered_settings'] = [];
	}

	/**
	 * The fields of a type, by option id (a heading has none).
	 *
	 * @param array  $fields Settings fields.
	 * @param string $type   Field type.
	 */
	private function of_type( array $fields, string $type ): array {
		$found = [];
		foreach ( $fields as $field ) {
			if ( $type === $field['type'] ) {
				$found[ $field['field_data']['id'] ?? '' ] = $field;
			}
		}
		return $found;
	}

	/**
	 * The group has one heading, and every field is in the group.
	 */
	public function test_fields_belong_to_the_product_page_group_with_one_heading() {
		$fields = ( new ProductPageSettings() )->add_settings_fields( [] );

		foreach ( $fields as $field ) {
			$this->assertSame( 'product_page', $field['group'] );
		}
		$this->assertCount( 1, $this->of_type( $fields, 'heading' ) );
	}

	/**
	 * Every layout can be chosen, and the select shows the one in force.
	 */
	public function test_layout_select_offers_every_layout() {
		$GLOBALS['wp_options']['subscrpt_plan_selector_layout'] = 'grid';
		$select = $this->of_type( ( new ProductPageSettings() )->add_settings_fields( [] ), 'select' );

		$layout = $select['subscrpt_plan_selector_layout']['field_data'];
		$this->assertSame( array_keys( Plans::layouts() ), array_keys( $layout['options'] ) );
		$this->assertCount( 7, $layout['options'] );
		$this->assertSame( 'grid', $layout['selected'] );

		$this->assertSame( [ 'chips', 'dropdown' ], array_keys( $select['subscrpt_plan_intervals_display']['field_data']['options'] ) );
		$this->assertSame( 'chips', $select['subscrpt_plan_intervals_display']['field_data']['selected'], 'chips until chosen' );
	}

	/**
	 * Six colours and a radius, each an input the preview can read.
	 */
	public function test_colour_and_radius_inputs() {
		$inputs = $this->of_type( ( new ProductPageSettings() )->add_settings_fields( [] ), 'input' );

		foreach ( [ 'accent', 'accent_ink', 'ink', 'border', 'badge', 'ribbon' ] as $name ) {
			$this->assertArrayHasKey( 'subscrpt_plan_color_' . $name, $inputs );
			$this->assertSame( '#', substr( $inputs[ 'subscrpt_plan_color_' . $name ]['field_data']['placeholder'], 0, 1 ) );
		}
		$this->assertArrayHasKey( 'subscrpt_plan_radius', $inputs );
		$this->assertSame( 'number', $inputs['subscrpt_plan_radius']['field_data']['type'] );
	}

	/**
	 * Every setting is registered with a callback that keeps bad values out.
	 */
	public function test_settings_are_registered_with_sanitisers() {
		( new ProductPageSettings() )->register_settings();
		$registered = $GLOBALS['registered_settings'];

		foreach ( [ 'subscrpt_plan_selector_layout', 'subscrpt_plan_intervals_display', 'subscrpt_plan_radius', 'subscrpt_plan_color_accent', 'subscrpt_plan_color_ribbon' ] as $option ) {
			$this->assertArrayHasKey( $option, $registered );
			$this->assertSame( 'wp_subscription_settings', $registered[ $option ][0] );
		}

		$accent = $registered['subscrpt_plan_color_accent'][1]['sanitize_callback'];
		$this->assertSame( '#abcdef', call_user_func( $accent, '#abcdef' ) );
		$this->assertSame( '', call_user_func( $accent, 'javascript:alert(1)' ) );

		$layout = $registered['subscrpt_plan_selector_layout'][1]['sanitize_callback'];
		$this->assertSame( 'accordion', call_user_func( $layout, 'accordion' ) );
		$this->assertSame( '', call_user_func( $layout, 'nonsense' ) );
	}

	/**
	 * The preview holds every layout in both interval styles, shows the chosen
	 * one, and posts nothing.
	 */
	public function test_preview_renders_every_layout_and_posts_nothing() {
		$GLOBALS['wp_options']['subscrpt_plan_selector_layout'] = 'buttons';
		$html = ( new ProductPageSettings() )->preview_html();

		$this->assertSame( 14, substr_count( $html, 'data-subscrpt-preview-layout=' ) );
		$this->assertStringContainsString( 'inert', $html );
		$this->assertStringNotContainsString( ' name="', $html, 'the preview must not post into the settings form' );
		$this->assertStringNotContainsString( 'data-subscrpt-buybox ', $html, 'plans.js must not bind to it' );
		$this->assertMatchesRegularExpression( '/data-subscrpt-preview-layout="buttons" data-subscrpt-preview-intervals="chips"(?![^>]*hidden)/', $html );
		$this->assertMatchesRegularExpression( '/data-subscrpt-preview-layout="stacked" data-subscrpt-preview-intervals="chips" hidden/', $html );
	}
}
