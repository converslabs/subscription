<?php
/**
 * Tests for the purchase option layouts.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Api\PlanController;
use SpringDevs\Subscription\Frontend\Plans;

/**
 * One set of groups, several layouts: stacked cards, a classic radio list and a
 * compact dropdown, each a partial chosen by `Plans::layout_for()`.
 */
class PlanLayoutsTest extends TestCase {

	/**
	 * Start every test with no meta, options, listeners or recorded hooks.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_post_meta']      = [];
		$GLOBALS['wp_options']        = [];
		$GLOBALS['wp_filter_returns'] = [];
		$GLOBALS['wp_hooks_registry'] = [];
		$GLOBALS['applied_filters']   = [];
		$GLOBALS['applied_actions']   = [];
	}

	/**
	 * Leave no layout behind for the next test class.
	 */
	protected function tearDown(): void {
		$GLOBALS['wp_post_meta']      = [];
		$GLOBALS['wp_options']        = [];
		$GLOBALS['wp_hooks_registry'] = [];
	}

	/**
	 * A two-term group, a one-term group, One-Time and an unknown type.
	 */
	private function fixture_groups(): array {
		return [
			[
				'id'        => 'grp_1',
				'type'      => 'subscribe_save',
				'label'     => 'Subscribe & Save',
				'price'     => '$20.00',
				'old_price' => '',
				'badge'     => '',
				'terms'     => [
					[
						'id'    => 11,
						'label' => 'Every month',
						'price' => '$20.00',
						'note'  => '$20.00 / month',
						'badge' => '',
					],
					[
						'id'             => 12,
						'label'          => 'Every 2 months',
						'price'          => '$16.00',
						'note'           => '$16.00 / 2 months',
						'badge'          => 'Save 20%',
						'regular_price'  => '$20.00',
						'interval_label' => '2 months',
						'saving_label'   => 'Save 20%',
					],
				],
			],
			[
				'id'        => 'grp_2',
				'type'      => 'recurring',
				'label'     => 'Monthly',
				'price'     => '$18.00',
				'old_price' => '',
				'badge'     => '',
				'terms'     => [
					[
						'id'    => 21,
						'label' => 'Monthly',
						'price' => '$18.00',
						'note'  => '$18.00 / month',
						'badge' => '',
					],
				],
			],
			[
				'id'        => 'one_time',
				'type'      => 'one_time',
				'label'     => 'One Time Purchase',
				'price'     => '$15.00',
				'old_price' => '$20.00',
				'terms'     => [],
				'note'      => '',
				'badge'     => 'Save 25%',
			],
			[
				'id'        => 'box_add',
				'type'      => 'box_add',
				'label'     => 'Add to a box',
				'price'     => '',
				'old_price' => '',
				'terms'     => [],
				'badge'     => '',
			],
		];
	}

	/**
	 * The product the selector renders for.
	 *
	 * @param int $id Product id.
	 */
	private function product( int $id = 10 ): \WC_Product {
		return new \WC_Product_Stub( $id, 'Beans', [ 'regular_price' => '20' ] );
	}

	/**
	 * Parse selector markup.
	 *
	 * @param string $html Markup.
	 */
	private function parse( string $html ): \DOMXPath {
		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return new \DOMXPath( $doc );
	}

	/**
	 * Render the selector in a layout, set as the store option.
	 *
	 * @param string     $layout Layout key, '' for none set.
	 * @param array|null $groups Groups, the fixtures when null.
	 */
	private function render( string $layout, ?array $groups = null ): \DOMXPath {
		if ( '' !== $layout ) {
			$GLOBALS['wp_options']['subscrpt_plan_selector_layout'] = $layout;
		}

		return $this->parse( Plans::selector_html( $groups ?? $this->fixture_groups(), $this->product(), 'page' ) );
	}

	/**
	 * The layout the rendered buybox says it is.
	 *
	 * @param \DOMXPath $xpath Parsed selector.
	 */
	private function layout_of( \DOMXPath $xpath ): string {
		$box = $xpath->query( '//*[@data-subscrpt-buybox]' )->item( 0 );
		$this->assertInstanceOf( \DOMElement::class, $box );

		return $box->getAttribute( 'data-subscrpt-layout' );
	}

	/**
	 * Markup as a comparable string: the layout attribute and the whitespace between tags dropped.
	 *
	 * @param string $html Markup.
	 */
	private function normalise( string $html ): string {
		$xpath = $this->parse( $html );
		foreach ( $xpath->query( '//*[@data-subscrpt-layout]' ) as $el ) {
			$el->removeAttribute( 'data-subscrpt-layout' );
		}
		$out = $xpath->document->saveHTML( $xpath->query( '//body' )->item( 0 ) );

		return trim( preg_replace( [ '/>\s+</', '/\s+/' ], [ '><', ' ' ], $out ) );
	}

	/**
	 * Render the frozen pre-layout template.
	 *
	 * @param array $groups Groups.
	 */
	private function render_before_layouts( array $groups ): string {
		$product = $this->product();
		$context = 'page';
		ob_start();
		include dirname( __DIR__ ) . '/Support/plan-selector-before-layouts.php';

		return (string) ob_get_clean();
	}

	public function test_layouts_filter_registers_defaults() {
		$layouts = Plans::layouts();

		$this->assertSame( [ 'stacked', 'classic', 'dropdown', 'accordion', 'grid', 'grid_savings', 'buttons' ], array_keys( $layouts ) );
		foreach ( $layouts as $key => $path ) {
			$this->assertSame( "product/plan-selector/{$key}.php", $path );
			$this->assertFileExists( SUBSCRPT_TEMPLATES . $path );
		}
		$this->assertNotEmpty( $GLOBALS['applied_filters']['subscrpt_plan_selector_layouts'] ?? [], 'the list is filterable' );
	}

	public function test_stacked_matches_previous_markup() {
		$plain     = $this->fixture_groups();
		$described = $this->fixture_groups();

		$described[0]['storefront'] = [
			'tag'              => 'Cancel anytime',
			'benefits_heading' => 'How it works',
			'benefits'         => [ 'One', 'Two' ],
			'learn_more'       => [
				'label' => '',
				'url'   => '',
				'panel' => 'Billed on the day you join.',
			],
		];

		foreach ( [ $plain, $described ] as $groups ) {
			$GLOBALS['wp_hooks_registry']['subscrpt_plan_card_body'][10] = [
				[
					static function ( $group ) {
						echo '<em>' . esc_html( $group['id'] ) . '</em>';
					},
					1,
				],
			];
			$stacked = Plans::selector_html( $groups, $this->product(), 'page' );

			$this->assertSame( 'stacked', $this->layout_of( $this->parse( $stacked ) ) );
			$this->assertSame( $this->normalise( $this->render_before_layouts( $groups ) ), $this->normalise( $stacked ) );
		}
	}

	public function test_classic_radio_list_prices_right() {
		$xpath = $this->render( 'classic' );

		$this->assertSame( 'classic', $this->layout_of( $xpath ) );
		$this->assertSame( 0, $xpath->query( "//*[contains(concat(' ', @class, ' '), ' subscrpt-buybox__card ')]" )->length, 'no cards' );

		$rows = $xpath->query( "//*[@data-subscrpt-card][contains(concat(' ', @class, ' '), ' subscrpt-buybox__row ')]" );
		$this->assertSame( 4, $rows->length );

		foreach ( $rows as $i => $row ) {
			$radio = $xpath->query( ".//input[@type='radio'][@name='subscrpt_plan_group']", $row );
			$this->assertSame( 1, $radio->length, "row {$i} has its radio" );
			$this->assertSame( 0 === $i, $radio->item( 0 )->hasAttribute( 'checked' ), 'the first row starts checked' );
			$this->assertSame( 1, $xpath->query( "//label[@for='" . $radio->item( 0 )->getAttribute( 'id' ) . "']" )->length, "row {$i} is labelled" );
			$this->assertSame( 0, $xpath->query( './/*[@data-subscrpt-card-body]', $row )->length, 'a row holds no body' );
		}

		// The price is the last thing on the row's line, after the label.
		$head = $xpath->query( "./*[contains(concat(' ', @class, ' '), ' subscrpt-buybox__head ')]", $rows->item( 0 ) )->item( 0 );
		$last = null;
		foreach ( $head->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				$last = $child;
			}
		}
		$this->assertStringContainsString( 'subscrpt-buybox__price', $last->getAttribute( 'class' ) );
		$this->assertStringContainsString( '$20.00', $last->textContent );

		$this->assertSame( 2, $xpath->query( "//input[@data-subscrpt-term][@name='subscrpt_plan_term[grp_1]']" )->length, 'the terms are still offered' );
		$this->assertSame( '11', $xpath->query( '//input[@data-subscrpt-plan-id]' )->item( 0 )->getAttribute( 'value' ) );
	}

	public function test_compact_dropdown_one_select_for_option_one_for_interval() {
		$xpath = $this->render( 'dropdown' );

		$this->assertSame( 'dropdown', $this->layout_of( $xpath ) );
		$this->assertSame( 0, $xpath->query( "//input[@type='radio']" )->length, 'no radios' );

		$option = $xpath->query( "//select[@name='subscrpt_plan_group']" );
		$this->assertSame( 1, $option->length, 'one select for the purchase option' );
		$values = [];
		foreach ( $xpath->query( './option', $option->item( 0 ) ) as $opt ) {
			$values[] = $opt->getAttribute( 'value' );
		}
		$this->assertSame( [ 'grp_1', 'grp_2', 'one_time', 'box_add' ], $values );
		$this->assertTrue( $xpath->query( './option', $option->item( 0 ) )->item( 0 )->hasAttribute( 'selected' ) );
		$id = $option->item( 0 )->getAttribute( 'id' );
		$this->assertSame( 1, $xpath->query( "//label[@for='{$id}']" )->length, 'the option select is labelled' );

		$visible = $xpath->query( '//select[@data-subscrpt-term-select][not(ancestor-or-self::*[@hidden])]' );
		$this->assertSame( 1, $visible->length, 'one select for the interval' );
		$this->assertSame( 'subscrpt_plan_term[grp_1]', $visible->item( 0 )->getAttribute( 'name' ) );
		$this->assertSame( 2, $xpath->query( './option', $visible->item( 0 ) )->length );
		$this->assertSame( '12', $xpath->query( './option', $visible->item( 0 ) )->item( 1 )->getAttribute( 'value' ) );
		$this->assertSame( '$16.00', $xpath->query( './option', $visible->item( 0 ) )->item( 1 )->getAttribute( 'data-price' ) );

		$this->assertSame( 0, $xpath->query( "//select[@name!='subscrpt_plan_group'][not(@data-subscrpt-term-select)]" )->length );
		$this->assertSame( '11', $xpath->query( '//input[@data-subscrpt-plan-id]' )->item( 0 )->getAttribute( 'value' ) );
	}

	public function test_intervals_dropdown_per_group() {
		$groups                                   = $this->fixture_groups();
		$groups[0]['storefront']['intervals']     = 'dropdown';
		$second                                   = $groups[0];
		$second['id']                             = 'grp_3';
		$second['storefront']['intervals']        = 'chips';
		array_splice( $groups, 1, 0, [ $second ] );

		$xpath = $this->render( '', $groups );

		$select = $xpath->query( "//select[@data-subscrpt-term-select][@name='subscrpt_plan_term[grp_1]']" );
		$this->assertSame( 1, $select->length, 'the terms are a select' );
		$this->assertSame( 0, $xpath->query( "//input[@name='subscrpt_plan_term[grp_1]']" )->length, 'and no chips' );
		$this->assertSame( [ '11', '12' ], [ $xpath->query( './option', $select->item( 0 ) )->item( 0 )->getAttribute( 'value' ), $xpath->query( './option', $select->item( 0 ) )->item( 1 )->getAttribute( 'value' ) ] );
		$this->assertStringContainsString( 'Save 20%', $xpath->query( './option', $select->item( 0 ) )->item( 1 )->textContent );
		$this->assertSame( 1, $xpath->query( "//label[@for='" . $select->item( 0 )->getAttribute( 'id' ) . "']" )->length, 'the select is labelled' );

		$this->assertSame( 2, $xpath->query( "//input[@data-subscrpt-term][@name='subscrpt_plan_term[grp_3]']" )->length, 'another group keeps its chips' );

		// Empty: the store option decides.
		$GLOBALS['wp_options']['subscrpt_plan_intervals_display'] = 'dropdown';
		$store = $this->render( '', $this->fixture_groups() );
		$this->assertSame( 1, $store->query( "//select[@name='subscrpt_plan_term[grp_1]']" )->length );
	}

	public function test_sanitize_storefront_keeps_intervals() {
		$this->assertSame( 'chips', PlanController::sanitize_storefront( [ 'intervals' => 'chips' ] )['intervals'] );
		$this->assertSame( 'dropdown', PlanController::sanitize_storefront( [ 'intervals' => 'dropdown' ] )['intervals'] );
		$this->assertSame( '', PlanController::sanitize_storefront( [ 'intervals' => '' ] )['intervals'] );
		$this->assertSame( '', PlanController::sanitize_storefront( [ 'intervals' => 'list' ] )['intervals'] );
		$this->assertSame( '', PlanController::sanitize_storefront( [ 'intervals' => [ 'chips' ] ] )['intervals'] );
	}

	public function test_layout_for_product_override_wins() {
		$GLOBALS['wp_options']['subscrpt_plan_selector_layout'] = 'classic';
		$GLOBALS['wp_post_meta'][10]['_subscrpt_plan_selector_layout'] = 'dropdown';

		$this->assertSame( 'dropdown', Plans::layout_for( $this->product( 10 ) ) );
		$this->assertSame( 'classic', Plans::layout_for( $this->product( 11 ) ), 'another product keeps the store layout' );
	}

	public function test_layout_for_falls_back_to_option_then_stacked() {
		$this->assertSame( 'stacked', Plans::layout_for( $this->product() ), 'nothing set' );

		$GLOBALS['wp_options']['subscrpt_plan_selector_layout'] = 'dropdown';
		$this->assertSame( 'dropdown', Plans::layout_for( $this->product() ), 'no meta, the option' );

		$GLOBALS['wp_options']['subscrpt_plan_selector_layout'] = 'carousel';
		$this->assertSame( 'stacked', Plans::layout_for( $this->product() ), 'an unknown option' );

		$GLOBALS['wp_options']['subscrpt_plan_selector_layout']        = 'classic';
		$GLOBALS['wp_post_meta'][10]['_subscrpt_plan_selector_layout'] = 'carousel';
		$this->assertSame( 'classic', Plans::layout_for( $this->product() ), 'an unknown override falls back to the store layout' );

		$GLOBALS['wp_options']['subscrpt_plan_selector_layout'] = 'carousel';
		$this->assertSame( 'stacked', Plans::layout_for( $this->product() ), 'an unknown override and an unknown option' );
	}

	public function test_unknown_layout_falls_back_to_stacked() {
		$xpath = $this->render( 'carousel' );

		$this->assertSame( 'stacked', $this->layout_of( $xpath ) );
		$this->assertSame( 4, $xpath->query( "//*[@data-subscrpt-card][contains(concat(' ', @class, ' '), ' subscrpt-buybox__card ')]" )->length );

		// A layout a filter took away is unknown too.
		$GLOBALS['wp_hooks_registry']['subscrpt_plan_selector_layouts'][10][] = [
			static function ( $layouts ) {
				unset( $layouts['classic'] );
				return $layouts;
			},
			1,
		];
		$this->assertSame( 'stacked', $this->layout_of( $this->render( 'classic' ) ) );
	}

	public function test_a_layout_whose_partial_is_missing_renders_stacked() {
		$GLOBALS['wp_hooks_registry']['subscrpt_plan_selector_layouts'][10][] = [
			static function ( $layouts ) {
				$layouts['ghost'] = 'product/plan-selector/ghost.php';
				return $layouts;
			},
			1,
		];
		$xpath = $this->render( 'ghost' );

		$this->assertSame( 'stacked', $this->layout_of( $xpath ), 'no silent empty buybox' );
		$this->assertSame( 4, $xpath->query( '//*[@data-subscrpt-card]' )->length );
	}

	public function test_dropdown_badge_describes_the_option_select() {
		$xpath = $this->render( 'dropdown' );

		$select = $xpath->query( "//select[@name='subscrpt_plan_group']" )->item( 0 );
		$this->assertSame( 'subscrpt-grp-grp_1-badge', $select->getAttribute( 'aria-describedby' ) );
		$this->assertSame( 1, $xpath->query( "//*[@id='subscrpt-grp-grp_1-badge'][@data-subscrpt-badge]" )->length );
	}

	public function test_card_body_fires_once_per_group_and_one_is_visible() {
		foreach ( [ 'dropdown', 'classic', 'buttons' ] as $layout ) {
			$GLOBALS['applied_actions']   = [];
			$GLOBALS['wp_hooks_registry'] = [];
			$GLOBALS['wp_hooks_registry']['subscrpt_plan_card_body'][10][] = [
				static function ( $group, $product, $context ) {
					echo '<span class="test-body">' . esc_html( $group['id'] . '|' . $product->get_id() . '|' . $context ) . '</span>';
				},
				3,
			];

			$xpath = $this->render( $layout );
			$calls = $GLOBALS['applied_actions']['subscrpt_plan_card_body'] ?? [];
			$this->assertCount( 4, $calls, "{$layout}: once per group" );

			$wrappers = $xpath->query( '//div[@data-subscrpt-body-for]' );
			$this->assertSame( 4, $wrappers->length, "{$layout}: one wrapper per group" );

			$shown = [];
			foreach ( $this->fixture_groups() as $i => $group ) {
				$wrapper = $wrappers->item( $i );
				$this->assertSame( $group['id'], $wrapper->getAttribute( 'data-subscrpt-body-for' ) );
				$this->assertSame( "{$group['id']}|10|page", trim( $wrapper->textContent ), "{$layout}: the body of {$group['id']}" );
				$this->assertSame( 0, $xpath->query( 'ancestor::*[@data-subscrpt-card]', $wrapper )->length, "{$layout}: below the control, not in it" );
				if ( ! $wrapper->hasAttribute( 'hidden' ) ) {
					$shown[] = $group['id'];
				}
			}
			$this->assertSame( [ 'grp_1' ], $shown, "{$layout}: only the selected group's body shows" );

			// Below the control: every wrapper follows the last option.
			$options = $xpath->query( '//*[@data-subscrpt-card] | //select[@name="subscrpt_plan_group"]' );
			$last    = $options->item( $options->length - 1 );
			$this->assertSame( 4, $xpath->query( 'following::div[@data-subscrpt-body-for]', $last )->length, "{$layout}: the bodies sit after the options" );
		}
	}

	/**
	 * Listen on `subscrpt_plan_card_body`, printing the group it fired for.
	 */
	private function listen_on_body(): void {
		$GLOBALS['wp_hooks_registry']['subscrpt_plan_card_body'][10][] = [
			static function ( $group ) {
				echo '<span class="test-body">' . esc_html( $group['id'] ) . '</span>';
			},
			1,
		];
	}

	/**
	 * Every option's group radio: one per card, labelled, the first checked.
	 *
	 * @param \DOMXPath    $xpath Parsed selector.
	 * @param \DOMNodeList $cards The options.
	 */
	private function assert_labelled_radios( \DOMXPath $xpath, \DOMNodeList $cards ): void {
		foreach ( $cards as $i => $card ) {
			$radio = $xpath->query( ".//input[@type='radio'][@name='subscrpt_plan_group']", $card );
			$this->assertSame( 1, $radio->length, "option {$i} has its radio" );
			$this->assertSame( 0 === $i, $radio->item( 0 )->hasAttribute( 'checked' ), 'the first option starts checked' );
			$this->assertSame( 1, $xpath->query( "//label[@for='" . $radio->item( 0 )->getAttribute( 'id' ) . "']" )->length, "option {$i} is labelled" );
		}
	}

	/**
	 * Each card holds its own group's body, and the action fired once per group.
	 *
	 * @param \DOMXPath    $xpath Parsed selector.
	 * @param \DOMNodeList $cards The options.
	 */
	private function assert_body_in_each_card( \DOMXPath $xpath, \DOMNodeList $cards ): void {
		$this->assertCount( 4, $GLOBALS['applied_actions']['subscrpt_plan_card_body'] ?? [], 'once per group' );
		$this->assertSame( 0, $xpath->query( '//*[@data-subscrpt-body-for]' )->length, 'no body below the options' );
		foreach ( $this->fixture_groups() as $i => $group ) {
			$body = $xpath->query( './/*[@data-subscrpt-card-body]', $cards->item( $i ) );
			$this->assertSame( 1, $body->length, "{$group['id']} has a body in its card" );
			$this->assertSame( $group['id'], trim( $body->item( 0 )->textContent ), "the body of {$group['id']}" );
		}
	}

	public function test_accordion_cards_collapse_to_one_line_and_the_selected_opens() {
		$this->listen_on_body();
		$groups                     = $this->fixture_groups();
		$groups[0]['storefront']    = [ 'benefits' => [ 'Skip any time' ] ];
		$xpath                      = $this->render( 'accordion', $groups );

		$this->assertSame( 'accordion', $this->layout_of( $xpath ) );
		$cards = $xpath->query( '//*[@data-subscrpt-card]' );
		$this->assertSame( 4, $cards->length );
		$this->assert_labelled_radios( $xpath, $cards );
		$this->assert_body_in_each_card( $xpath, $cards );

		foreach ( $cards as $i => $card ) {
			// One line: the head holds the radio, label and price; the rest folds away.
			$head  = $xpath->query( "./*[contains(concat(' ', @class, ' '), ' subscrpt-buybox__head ')]", $card )->item( 0 );
			$radio = $xpath->query( ".//input[@name='subscrpt_plan_group']", $head )->item( 0 );
			$this->assertInstanceOf( \DOMElement::class, $radio, "option {$i}: its radio is on the line" );

			$panel_id = $radio->getAttribute( 'aria-controls' );
			$panel    = $xpath->query( "./*[@id='{$panel_id}'][@data-subscrpt-panel]", $card )->item( 0 );
			$this->assertInstanceOf( \DOMElement::class, $panel, "option {$i}: the radio controls its panel" );
			$this->assertFalse( $radio->hasAttribute( 'aria-expanded' ), "option {$i}: a radio takes no aria-expanded" );
			$this->assertSame( 0 !== $i, $panel->hasAttribute( 'hidden' ), "option {$i}: only the selected one opens" );
			$this->assertSame( 1, $xpath->query( './/*[@data-subscrpt-card-body]', $panel )->length, "option {$i}: the body is in the panel" );
		}

		$first = $xpath->query( "//*[@data-subscrpt-panel][not(@hidden)]" )->item( 0 );
		$this->assertSame( 2, $xpath->query( ".//input[@data-subscrpt-term][@name='subscrpt_plan_term[grp_1]']", $first )->length, 'the open panel shows the intervals' );
		$this->assertSame( 1, $xpath->query( ".//*[contains(concat(' ', @class, ' '), ' subscrpt-buybox__benefits ')]", $first )->length, 'and the benefits' );
		$this->assertStringContainsString( '$20.00', $xpath->query( "//*[@data-subscrpt-card][1]/*[contains(concat(' ', @class, ' '), ' subscrpt-buybox__head ')]" )->item( 0 )->textContent, 'the price is on the line' );
	}

	public function test_grid_equal_width_tiles() {
		$this->listen_on_body();
		$xpath = $this->render( 'grid' );

		$this->assertSame( 'grid', $this->layout_of( $xpath ) );
		$tiles = $xpath->query( "//*[contains(concat(' ', @class, ' '), ' subscrpt-buybox__tiles ')]/*[@data-subscrpt-card]" );
		$this->assertSame( 4, $tiles->length, 'the options are tiles in one row' );
		$this->assertSame( 4, $xpath->query( '//*[@data-subscrpt-card]' )->length );
		$this->assert_labelled_radios( $xpath, $tiles );
		$this->assert_body_in_each_card( $xpath, $tiles );

		$this->assertSame( 2, $xpath->query( "//input[@data-subscrpt-term][@name='subscrpt_plan_term[grp_1]']" )->length, 'the terms are offered' );
		$this->assertSame( 0, $xpath->query( '//*[@data-subscrpt-panel]' )->length, 'nothing folds away' );
		$this->assertSame( '11', $xpath->query( '//input[@data-subscrpt-plan-id]' )->item( 0 )->getAttribute( 'value' ) );
	}

	public function test_grid_savings_tiles_led_by_the_saving() {
		$this->listen_on_body();
		$xpath = $this->render( 'grid_savings' );

		$this->assertSame( 'grid_savings', $this->layout_of( $xpath ) );
		$tiles = $xpath->query( "//*[contains(concat(' ', @class, ' '), ' subscrpt-buybox__tiles ')]/*[@data-subscrpt-card]" );
		$this->assertSame( 4, $tiles->length );
		$this->assert_labelled_radios( $xpath, $tiles );
		$this->assert_body_in_each_card( $xpath, $tiles );

		foreach ( $tiles as $i => $tile ) {
			$lead = $xpath->query( './*', $tile )->item( 0 );
			$this->assertStringContainsString( 'subscrpt-buybox__lead', $lead->getAttribute( 'class' ), "tile {$i} leads with the saving" );
		}
		$one_time = $xpath->query( './*', $tiles->item( 2 ) )->item( 0 );
		$this->assertSame( 'Save 25%', trim( $xpath->query( './/*[@data-subscrpt-badge]', $one_time )->item( 0 )->textContent ) );
		$this->assertSame( 1, $xpath->query( './/*[@data-subscrpt-badge][@hidden]', $xpath->query( './*', $tiles->item( 0 ) )->item( 0 ) )->length, 'the badge slot waits for a term that saves' );
		$this->assertSame( 0, $xpath->query( './/*[@data-subscrpt-badge]', $xpath->query( './*', $tiles->item( 1 ) )->item( 0 ) )->length, 'no saving, no badge' );
	}

	public function test_buttons_segmented_row_intervals_dropdown_below() {
		$xpath = $this->render( 'buttons' );

		$this->assertSame( 'buttons', $this->layout_of( $xpath ) );

		$row = $xpath->query( "//fieldset[contains(concat(' ', @class, ' '), ' subscrpt-buybox__segments ')]" );
		$this->assertSame( 1, $row->length, 'one segmented row' );
		$this->assertNotSame( '', trim( $xpath->query( './legend', $row->item( 0 ) )->item( 0 )->textContent ), 'the row is named' );

		$radios = $xpath->query( ".//input[@type='radio'][@name='subscrpt_plan_group']", $row->item( 0 ) );
		$this->assertSame( 4, $radios->length, 'a segment per option' );
		foreach ( $radios as $i => $radio ) {
			$this->assertSame( 0 === $i, $radio->hasAttribute( 'checked' ) );
			$this->assertSame( 1, $xpath->query( "//label[@for='" . $radio->getAttribute( 'id' ) . "']" )->length, "segment {$i} is labelled" );
			$this->assertSame( 0, $xpath->query( 'ancestor::*[@data-subscrpt-card]', $radio )->length, 'the segments are the control, not inside an option' );
		}

		$options = $xpath->query( '//*[@data-subscrpt-card][@data-subscrpt-only-selected]' );
		$this->assertSame( 4, $options->length, 'each option has details below the row' );
		$this->assertSame( 1, $xpath->query( '//*[@data-subscrpt-card][not(@hidden)]' )->length, 'only the selected one shows' );
		$this->assertSame( 4, $xpath->query( 'following::*[@data-subscrpt-card]', $row->item( 0 ) )->length, 'below the row' );

		$this->assertSame( 0, $xpath->query( '//input[@data-subscrpt-term]' )->length, 'no chips' );
		$select = $xpath->query( "//select[@data-subscrpt-term-select][@name='subscrpt_plan_term[grp_1]']" );
		$this->assertSame( 1, $select->length, 'the intervals are a dropdown' );
		$this->assertSame( 1, $xpath->query( "//label[@for='" . $select->item( 0 )->getAttribute( 'id' ) . "']" )->length, 'the dropdown is labelled' );
		$this->assertSame( '11', $xpath->query( '//input[@data-subscrpt-plan-id]' )->item( 0 )->getAttribute( 'value' ) );
	}
}
