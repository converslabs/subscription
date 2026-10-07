<?php
/**
 * Tests for the purchase option card markup.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Frontend\Plans;

/**
 * The card is a `<div>` with a labelled radio, terms are a radio group, and
 * every card carries a body pro can fill through `subscrpt_plan_card_body`.
 */
class PlanSelectorMarkupTest extends TestCase {

	/**
	 * Start every test with no meta, listeners or recorded hooks.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_post_meta']      = [];
		$GLOBALS['wp_filter_returns'] = [];
		$GLOBALS['wp_hooks_registry'] = [];
		$GLOBALS['applied_filters']   = [];
		$GLOBALS['applied_actions']   = [];
	}

	/**
	 * One group of each kind the template meets: two terms, one term,
	 * one-time, and a type free does not know.
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
						'id'    => 12,
						'label' => 'Every 2 months',
						'price' => '$16.00',
						'note'  => '$16.00 / 2 months',
						'badge' => 'Save 20%',
						'regular_price' => '$20.00',
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
	 */
	private function product(): \WC_Product {
		return new \WC_Product_Stub( 10, 'Beans', [ 'regular_price' => '20' ] );
	}

	/**
	 * Render the fixture groups and parse the markup.
	 *
	 * @param array|null $groups Groups to render, the fixtures when null.
	 */
	private function render( ?array $groups = null ): \DOMXPath {
		$html = Plans::selector_html( $groups ?? $this->fixture_groups(), $this->product(), 'page' );

		$doc      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return new \DOMXPath( $doc );
	}

	/**
	 * XPath for elements carrying a class.
	 *
	 * @param string $class_name Class name.
	 */
	private function with_class( string $class_name ): string {
		return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class_name} ')]";
	}

	/**
	 * The card element whose group radio has this value.
	 *
	 * @param \DOMXPath $xpath Parsed selector.
	 * @param string    $group Group id.
	 */
	private function card( \DOMXPath $xpath, string $group ): \DOMElement {
		$card = $xpath->query( "//*[@data-subscrpt-card][.//input[@name='subscrpt_plan_group'][@value='{$group}']]" )->item( 0 );
		$this->assertInstanceOf( \DOMElement::class, $card, "a card for {$group}" );

		return $card;
	}

	public function test_card_is_a_div_with_a_labelled_radio() {
		$xpath = $this->render();

		$cards = $xpath->query( '//*[@data-subscrpt-card]' );
		$this->assertSame( 4, $cards->length );
		foreach ( $cards as $card ) {
			$this->assertSame( 'div', $card->nodeName );
		}

		$this->assertSame( 0, $xpath->query( '//label[.//input or .//select or .//textarea or .//button]' )->length, 'no label holds a form control' );

		$radios = $xpath->query( "//input[@type='radio']" );
		$this->assertGreaterThan( 4, $radios->length );
		foreach ( $radios as $radio ) {
			$id = $radio->getAttribute( 'id' );
			$this->assertNotSame( '', $id );
			$this->assertSame( 1, $xpath->query( "//label[@for='{$id}']" )->length, "radio {$id} has one label" );
		}
	}

	public function test_terms_are_a_radio_group_with_a_legend() {
		$xpath = $this->render();
		$card  = $this->card( $xpath, 'grp_1' );

		$fieldset = $xpath->query( ".//fieldset[contains(concat(' ', @class, ' '), ' subscrpt-buybox__terms ')]", $card );
		$this->assertSame( 1, $fieldset->length );
		$this->assertNotSame( '', trim( $xpath->query( './legend', $fieldset->item( 0 ) )->item( 0 )->textContent ?? '' ) );

		$terms = $xpath->query( ".//input[@type='radio']", $fieldset->item( 0 ) );
		$this->assertSame( 2, $terms->length );
		foreach ( $terms as $term ) {
			$this->assertSame( 'subscrpt_plan_term[grp_1]', $term->getAttribute( 'name' ) );
		}
		$this->assertSame( [ '11', '12' ], [ $terms->item( 0 )->getAttribute( 'value' ), $terms->item( 1 )->getAttribute( 'value' ) ] );
		$this->assertTrue( $terms->item( 0 )->hasAttribute( 'checked' ), 'the first term starts checked' );

		$this->assertSame( 0, $xpath->query( './/fieldset', $this->card( $xpath, 'grp_2' ) )->length, 'a single term needs no group' );
		$this->assertSame( 0, $xpath->query( '//button' )->length, 'terms are no longer buttons' );
	}

	public function test_saving_is_in_the_accessible_name() {
		$xpath = $this->render();

		$radio = $xpath->query( "//input[@name='subscrpt_plan_term[grp_1]'][@value='12']" )->item( 0 );
		$this->assertInstanceOf( \DOMElement::class, $radio );
		$label = $xpath->query( "//label[@for='" . $radio->getAttribute( 'id' ) . "']" )->item( 0 );

		$this->assertStringContainsString( '2 months', $label->textContent );
		$this->assertStringContainsString( 'Save 20%', $label->textContent );
	}

	public function test_old_classes_survive() {
		$xpath = $this->render();

		foreach ( [ 'card', 'head', 'radio', 'label', 'price', 'note', 'terms', 'term', 'badge' ] as $suffix ) {
			$this->assertGreaterThan( 0, $xpath->query( $this->with_class( 'subscrpt-buybox__' . $suffix ) )->length, "subscrpt-buybox__{$suffix}" );
		}
		$this->assertSame( 'label', $xpath->query( $this->with_class( 'subscrpt-buybox__label' ) )->item( 0 )->nodeName );
		$this->assertSame( 1, $xpath->query( $this->with_class( 'is-selected' ) )->length );
		$this->assertSame( 2, $xpath->query( '//*[@data-subscrpt-term-btn][@data-term-id]' )->length );
	}

	public function test_card_body_action_fires_in_every_card() {
		$groups  = $this->fixture_groups();
		$GLOBALS['wp_hooks_registry']['subscrpt_plan_card_body'][10][] = [
			static function ( $group ) {
				echo '<span class="test-body">' . esc_html( $group['id'] ) . '</span>';
			},
			1,
		];

		$xpath = $this->render( $groups );

		$calls = $GLOBALS['applied_actions']['subscrpt_plan_card_body'] ?? [];
		$this->assertCount( 4, $calls );
		foreach ( $groups as $i => $group ) {
			$this->assertSame( $group, $calls[ $i ][0] );
			$this->assertInstanceOf( \WC_Product::class, $calls[ $i ][1] );
			$this->assertSame( 10, $calls[ $i ][1]->get_id() );
			$this->assertSame( 'page', $calls[ $i ][2] );

			$card = $this->card( $xpath, $group['id'] );
			$body = $xpath->query( ".//div[@data-subscrpt-card-body][contains(concat(' ', @class, ' '), ' subscrpt-buybox__body ')]", $card );
			$this->assertSame( 1, $body->length, "{$group['id']} has a body" );
			$this->assertSame( $group['id'], trim( $body->item( 0 )->textContent ) );
		}
	}

	public function test_unknown_type_renders_generic_card() {
		$groups             = $this->fixture_groups();
		$groups[3]['price'] = '$9.00';
		$groups[3]['badge'] = 'New';
		$xpath              = $this->render( $groups );

		$card  = $this->card( $xpath, 'box_add' );
		$radio = $xpath->query( ".//input[@name='subscrpt_plan_group']", $card )->item( 0 );
		$label = $xpath->query( ".//label[@for='" . $radio->getAttribute( 'id' ) . "']", $card )->item( 0 );

		$this->assertInstanceOf( \DOMElement::class, $label );
		$this->assertSame( 'Add to a box', trim( $label->textContent ) );
		$this->assertStringContainsString( '$9.00', $xpath->query( ".//*[contains(concat(' ', @class, ' '), ' subscrpt-buybox__price ')]", $card )->item( 0 )->textContent ?? '' );
		$this->assertSame( 'New', trim( $xpath->query( ".//*[contains(concat(' ', @class, ' '), ' subscrpt-buybox__badge ')]", $card )->item( 0 )->textContent ?? '' ) );
		$this->assertSame( 1, $xpath->query( './/*[@data-subscrpt-card-body]', $card )->length );
		$this->assertSame( 0, $xpath->query( './/fieldset', $card )->length );

		$bare = $this->card( $this->render(), 'box_add' );
		$this->assertSame( 0, $bare->getElementsByTagName( 'del' )->length + $bare->getElementsByTagName( 'ins' )->length, 'no price, no price block' );
	}

	public function test_card_shows_struck_regular_price() {
		$groups                         = $this->fixture_groups();
		$groups[0]['terms']             = array_reverse( $groups[0]['terms'] );
		$groups[0]['price']             = '$16.00';
		$groups[0]['terms'][0]['price'] = '$16.00';
		$xpath                          = $this->render( $groups );
		$card                           = $this->card( $xpath, 'grp_1' );

		$del = $xpath->query( './/del[@data-subscrpt-card-regular]', $card );
		$ins = $xpath->query( './/ins[@data-subscrpt-card-price]', $card );
		$this->assertSame( 1, $del->length );
		$this->assertSame( '$20.00', trim( $del->item( 0 )->textContent ) );
		$this->assertSame( '$16.00', trim( $ins->item( 0 )->textContent ) );

		$offer = $xpath->query( ".//input[@data-subscrpt-term][@value='12']", $card )->item( 0 );
		$this->assertSame( '$20.00', $offer->getAttribute( 'data-regular' ) );
		$plain = $xpath->query( ".//input[@data-subscrpt-term][@value='11']", $card )->item( 0 );
		$this->assertSame( '', $plain->getAttribute( 'data-regular' ), 'no saving, nothing to strike' );
	}

	public function test_chip_shows_interval_and_saving_and_legend_the_heading() {
		$groups                         = $this->fixture_groups();
		$groups[0]['terms_heading']     = 'Deliver every';
		$xpath                          = $this->render( $groups );
		$card                           = $this->card( $xpath, 'grp_1' );

		$this->assertSame( 'Deliver every', trim( $xpath->query( './/legend', $card )->item( 0 )->textContent ) );
		$chip = $xpath->query( ".//label[@data-term-id='12']", $card )->item( 0 );
		$this->assertSame( '2 months · Save 20%', trim( preg_replace( '/\s+/', ' ', $chip->textContent ) ) );
		$plain = $xpath->query( ".//label[@data-term-id='11']", $card )->item( 0 );
		$this->assertSame( 'Every month', trim( $plain->textContent ), 'no interval_label falls back to the label' );
	}

	/**
	 * A fixture group carrying storefront fields.
	 *
	 * @param array $storefront Storefront fields.
	 */
	private function with_storefront( array $storefront ): array {
		$groups                  = $this->fixture_groups();
		$groups[0]['storefront'] = $storefront;
		$groups[0]['badge']      = 'Save 20%';

		return $groups;
	}

	public function test_benefits_render_with_heading() {
		$xpath = $this->render(
			$this->with_storefront(
				[
					'benefits_heading' => 'How it works',
					'benefits'         => [ 'One', 'Two <b>x</b>', 'Three', 'Four', 'Five', 'Six' ],
				]
			)
		);
		$card  = $this->card( $xpath, 'grp_1' );

		$list = $xpath->query( ".//ul[contains(concat(' ', @class, ' '), ' subscrpt-buybox__benefits ')]", $card );
		$this->assertSame( 1, $list->length );
		$this->assertSame( 5, $xpath->query( './li', $list->item( 0 ) )->length, 'at most five lines' );
		$this->assertSame( 'Two <b>x</b>', $xpath->query( './li', $list->item( 0 ) )->item( 1 )->textContent, 'a line is text, never markup' );

		$heading = $xpath->query( ".//*[contains(concat(' ', @class, ' '), ' subscrpt-buybox__benefits-heading ')]", $card );
		$this->assertSame( 1, $heading->length );
		$this->assertSame( 'How it works', trim( $heading->item( 0 )->textContent ) );
		$this->assertSame( 0, $xpath->query( "//*[@data-subscrpt-card][.//input[@value='grp_2']]//ul[contains(@class,'subscrpt-buybox__benefits')]" )->length, 'another group shows none' );

		$no_heading = $this->render( $this->with_storefront( [ 'benefits' => [ 'Only' ] ] ) );
		$this->assertSame( 1, $no_heading->query( '//ul[contains(@class,"subscrpt-buybox__benefits")]' )->length );
		$this->assertSame( 0, $no_heading->query( '//*[contains(@class,"subscrpt-buybox__benefits-heading")]' )->length );
	}

	public function test_tag_renders_as_ribbon_separate_from_badge() {
		$xpath = $this->render( $this->with_storefront( [ 'tag' => 'Cancel anytime' ] ) );
		$card  = $this->card( $xpath, 'grp_1' );

		$ribbon = $xpath->query( ".//*[contains(concat(' ', @class, ' '), ' subscrpt-buybox__ribbon ')]", $card );
		$this->assertSame( 1, $ribbon->length );
		$this->assertSame( 'Cancel anytime', trim( $ribbon->item( 0 )->textContent ) );

		$badge = $xpath->query( ".//*[@data-subscrpt-badge]", $card );
		$this->assertSame( 1, $badge->length, 'the discount badge is still there' );
		$this->assertSame( 'Save 20%', trim( $badge->item( 0 )->textContent ) );
		$this->assertNotSame( $ribbon->item( 0 ), $badge->item( 0 ) );
		$this->assertSame( 1, $xpath->query( $this->with_class( 'subscrpt-buybox__ribbon' ) )->length, 'the other cards show no ribbon' );
	}

	public function test_learn_more_link_or_panel() {
		$link  = $this->render(
			$this->with_storefront(
				[
					'learn_more' => [
						'label' => 'Read the terms',
						'url'   => 'https://example.com/terms',
						'panel' => '',
					],
				]
			)
		);
		$anchor = $link->query( '//a[contains(@class,"subscrpt-buybox__learn-more")]' );
		$this->assertSame( 1, $anchor->length );
		$this->assertSame( 'https://example.com/terms', $anchor->item( 0 )->getAttribute( 'href' ) );
		$this->assertSame( 'Read the terms', trim( $anchor->item( 0 )->textContent ) );
		$this->assertSame( 0, $link->query( '//button[@aria-expanded]' )->length );
		$this->assertSame( 1, $link->query( '//*[contains(@class,"subscrpt-buybox__learn-more")]' )->length, 'the other cards show no link' );

		$panel  = $this->render( $this->with_storefront( [ 'learn_more' => [ 'label' => '', 'url' => '', 'panel' => 'Billed on the day you join.' ] ] ) );
		$button = $panel->query( '//button[@aria-expanded][@aria-controls]' );
		$this->assertSame( 1, $button->length );
		$this->assertSame( 'false', $button->item( 0 )->getAttribute( 'aria-expanded' ) );
		$this->assertNotSame( '', trim( $button->item( 0 )->textContent ), 'an empty label falls back to a default' );

		$region = $panel->query( '//*[@id="' . $button->item( 0 )->getAttribute( 'aria-controls' ) . '"]' );
		$this->assertSame( 1, $region->length );
		$this->assertTrue( $region->item( 0 )->hasAttribute( 'hidden' ), 'the panel starts closed' );
		$this->assertStringContainsString( 'Billed on the day you join.', $region->item( 0 )->textContent );
		$this->assertSame( 0, $panel->query( '//a[contains(@class,"subscrpt-buybox__learn-more")]' )->length );
	}

	public function test_storefront_values_are_escaped_on_output() {
		$xpath = $this->render(
			$this->with_storefront(
				[
					'tag'        => '"><script>alert(1)</script>',
					'benefits'   => [ '<img src=x onerror=alert(1)>' ],
					'learn_more' => [ 'label' => '<i>x</i>', 'url' => '"><script>alert(2)</script>', 'panel' => '' ],
				]
			)
		);

		$this->assertSame( 0, $xpath->query( '//script | //img | //i' )->length );
	}

	public function test_nothing_renders_when_empty() {
		foreach ( [ null, [], [ 'benefits' => [], 'tag' => '', 'benefits_heading' => 'Orphan heading', 'learn_more' => [ 'label' => 'Orphan', 'url' => '', 'panel' => '' ] ] ] as $storefront ) {
			$groups = $this->fixture_groups();
			if ( null !== $storefront ) {
				$groups[0]['storefront'] = $storefront;
			}
			$xpath = $this->render( $groups );

			foreach ( [ 'benefits', 'benefits-heading', 'ribbon', 'learn-more', 'details' ] as $part ) {
				$this->assertSame( 0, $xpath->query( $this->with_class( 'subscrpt-buybox__' . $part ) )->length, "no {$part} for an empty storefront" );
			}
		}
	}
}
