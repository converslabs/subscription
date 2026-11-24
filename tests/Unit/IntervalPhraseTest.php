<?php
/**
 * Tests for billing-interval phrases in the site's language.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Admin\PlanPresenter;
use SpringDevs\Subscription\Frontend\PlanGroups;

/**
 * PlanPresenter::interval_phrase(), its storefront callers and subscrpt_get_typos().
 */
class IntervalPhraseTest extends TestCase {

	/**
	 * German, as a translation file would supply it.
	 */
	private const GERMAN = [
		'day'           => 'Tag',
		'week'          => 'Woche',
		'month'         => 'Monat',
		'year'          => 'Jahr',
		'Month'         => 'Monat',
		'%d day'        => [ '%d Tag', '%d Tage' ],
		'%d week'       => [ '%d Woche', '%d Wochen' ],
		'%d month'      => [ '%d Monat', '%d Monate' ],
		'%d year'       => [ '%d Jahr', '%d Jahre' ],
		'Billed %1$s / %2$s' => '%1$s pro %2$s',
	];

	/**
	 * Start every test in English, with no meta or filters.
	 */
	protected function setUp(): void {
		$GLOBALS['wp_post_meta']      = [];
		$GLOBALS['wp_filter_returns'] = [];
		$GLOBALS['wp_hooks_registry'] = [];
		$GLOBALS['applied_filters']   = [];
		$GLOBALS['test_translations'] = [];
	}

	/**
	 * Back to English for the tests that follow.
	 */
	protected function tearDown(): void {
		$GLOBALS['test_translations'] = [];
	}

	/**
	 * Every unit, one and many, as English reads it.
	 *
	 * @return array<string, array{int, int, bool, string}>
	 */
	public static function english(): array {
		return [
			'1 day'           => [ 1, 1, false, 'day' ],
			'3 days'          => [ 3, 1, false, '3 days' ],
			'1 week'          => [ 1, 2, false, 'week' ],
			'2 weeks'         => [ 2, 2, false, '2 weeks' ],
			'1 month'         => [ 1, 3, false, 'month' ],
			'2 months'        => [ 2, 3, false, '2 months' ],
			'1 year'          => [ 1, 4, false, 'year' ],
			'5 years'         => [ 5, 4, false, '5 years' ],
			'1 month counted' => [ 1, 3, true, '1 month' ],
			'unknown unit'    => [ 2, 9, false, '2 months' ],
			'zero frequency'  => [ 0, 2, false, 'week' ],
		];
	}

	/**
	 * English output is what it was before the phrases became translatable.
	 *
	 * @dataProvider english
	 *
	 * @param int    $freq      Frequency.
	 * @param int    $interval  Interval.
	 * @param bool   $count_one Whether one reads "1 month".
	 * @param string $expected  Phrase.
	 */
	public function test_english_phrase( int $freq, int $interval, bool $count_one, string $expected ) {
		$this->assertSame( $expected, PlanPresenter::interval_phrase( $freq, $interval, $count_one ) );
	}

	/**
	 * A German site gets German plurals and German case, never "2 monats".
	 */
	public function test_german_phrase_uses_translated_plural() {
		$GLOBALS['test_translations'] = self::GERMAN;

		$this->assertSame( 'Monat', PlanPresenter::interval_phrase( 1, 3 ) );
		$this->assertSame( '2 Monate', PlanPresenter::interval_phrase( 2, 3 ) );
		$this->assertSame( '1 Woche', PlanPresenter::interval_phrase( 1, 2, true ) );
		$this->assertSame( '3 Tage', PlanPresenter::interval_phrase( 3, 1 ) );
		$this->assertSame( '2 Jahre', PlanPresenter::interval_phrase( 2, 4 ) );
	}

	/**
	 * The storefront chip and note read German on a German site.
	 */
	public function test_storefront_term_reads_german() {
		$GLOBALS['test_translations'] = self::GERMAN;

		$row  = $this->row( 2, 'Plan 11' );
		$term = PlanGroups::from_rows( [ $row ], $this->product() )[0]['terms'][0];

		$this->assertSame( 'Plan 11', $term['interval_label'] );
		$this->assertSame( '$10.00 pro 2 Monate', $term['note'] );
	}

	/**
	 * A title that only restates the interval in the site's language is replaced
	 * by the computed phrase, as an English "Monthly" already is.
	 */
	public function test_title_restating_translated_interval_shows_phrase() {
		$GLOBALS['test_translations'] = self::GERMAN;

		$terms = PlanGroups::from_rows(
			[ $this->row( 1, 'monat', 11 ), $this->row( 2, '2 Monate', 12 ), $this->row( 1, 'Monthly', 13 ) ],
			$this->product()
		)[0]['terms'];

		$this->assertSame( [ '1 Monat', '2 Monate', '1 Monat' ], array_column( $terms, 'interval_label' ) );
	}

	/**
	 * Plural interval words are translated, not returned as the raw key.
	 */
	public function test_typos_translate_plural() {
		$this->assertSame( 'Month', subscrpt_get_typos( 1, 'months' ) );
		$this->assertSame( 'Months', subscrpt_get_typos( 2, 'months' ) );
		$this->assertSame( 'Days', subscrpt_get_typos( '3', 'days' ) );

		$GLOBALS['test_translations'] = [ 'month' => [ 'Monat', 'Monate' ] ];

		$this->assertSame( 'Monat', subscrpt_get_typos( 1, 'months' ) );
		$this->assertSame( 'Monate', subscrpt_get_typos( 2, 'months' ) );
	}

	/**
	 * The admin trial chip comes from a phrase, never the stored English key.
	 */
	public function test_trial_chip_is_translatable() {
		$plan = [
			'billing_frequency' => 1,
			'billing_interval'  => 3,
			'free_trial'        => 14,
			'data'              => [ 'free_trial_interval' => 'day' ],
		];

		$this->assertContains( '14-day free trial', PlanPresenter::term_meta( $plan ) );

		$GLOBALS['test_translations'] = [ '%d-day free trial' => [ '%d Tag kostenlos testen', '%d Tage kostenlos testen' ] ];
		$plan['data']['free_trial_interval'] = 'days';

		$this->assertContains( '14 Tage kostenlos testen', PlanPresenter::term_meta( $plan ) );
	}

	/**
	 * A recurring monthly plan row.
	 *
	 * @param int    $freq    Billing frequency.
	 * @param string $title   Plan title.
	 * @param int    $plan_id Plan id.
	 */
	private function row( int $freq, string $title, int $plan_id = 11 ): array {
		return [
			'plan_group_id'     => 1,
			'group_type'        => 2,
			'group_title'       => 'Group 1',
			'group_data'        => [],
			'plan_id'           => $plan_id,
			'plan_title'        => $title,
			'billing_interval'  => 3,
			'billing_frequency' => $freq,
			'relation_data'     => [ 'regular_price' => '10' ],
			'plan_data'         => [],
			'free_trial'        => 0,
			'signup_fee'        => 0,
		];
	}

	/**
	 * The product the rows belong to.
	 */
	private function product(): \WC_Product {
		return new \WC_Product_Stub( 10, 'Beans', [ 'regular_price' => '20' ] );
	}
}
