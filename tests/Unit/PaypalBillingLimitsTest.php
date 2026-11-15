<?php
/**
 * Tests for the PayPal billing-period limits.
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Admin\PaypalBillingLimits;

/**
 * A period is flagged only when PayPal rejects it: more than 365 days,
 * 52 weeks, 12 months or 1 year.
 */
class PaypalBillingLimitsTest extends TestCase {

	/**
	 * Periods PayPal accepts, up to and including each limit.
	 */
	public function test_periods_within_the_limit_pass(): void {
		$this->assertFalse( PaypalBillingLimits::exceeds_limit( 365, 'days' ) );
		$this->assertFalse( PaypalBillingLimits::exceeds_limit( 52, 'weeks' ) );
		$this->assertFalse( PaypalBillingLimits::exceeds_limit( 12, 'months' ) );
		$this->assertFalse( PaypalBillingLimits::exceeds_limit( 1, 'year' ) );
	}

	/**
	 * Periods one past each limit, which PayPal rejects with INVALID_PARAMETER_VALUE.
	 */
	public function test_periods_past_the_limit_are_flagged(): void {
		$this->assertTrue( PaypalBillingLimits::exceeds_limit( 366, 'days' ) );
		$this->assertTrue( PaypalBillingLimits::exceeds_limit( 53, 'weeks' ) );
		$this->assertTrue( PaypalBillingLimits::exceeds_limit( 13, 'months' ) );
		$this->assertTrue( PaypalBillingLimits::exceeds_limit( 2, 'years' ) );
	}

	/**
	 * Units come singular, plural or capitalised; an unknown unit is never flagged.
	 */
	public function test_unit_spellings(): void {
		$this->assertTrue( PaypalBillingLimits::exceeds_limit( 2, 'Year' ) );
		$this->assertTrue( PaypalBillingLimits::exceeds_limit( 2, ' YEARS ' ) );
		$this->assertFalse( PaypalBillingLimits::exceeds_limit( 1000, 'fortnight' ) );
		$this->assertFalse( PaypalBillingLimits::exceeds_limit( 1000, '' ) );
	}
}
