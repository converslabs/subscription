<?php
/**
 * Tests for the plan group storefront fields (benefits, learn-more link, tag).
 *
 * @package SpringDevs\Subscription
 */

namespace SpringDevs\Subscription\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SpringDevs\Subscription\Api\PlanController;

/**
 * PlanController::sanitize_storefront() and merge_storefront().
 */
class PlanStorefrontDataTest extends TestCase {

	public function test_benefits_trimmed_to_five_and_stripped() {
		$out = PlanController::sanitize_storefront(
			[
				'benefits' => [ ' Cancel anytime ', '', '<b>Free shipping</b>', 'Skip a month', 'Four', 'Five', 'Six' ],
			]
		);

		$this->assertSame( [ 'Cancel anytime', 'Free shipping', 'Skip a month', 'Four', 'Five' ], $out['benefits'] );
	}

	public function test_heading_learn_more_and_tag_sanitised() {
		$out = PlanController::sanitize_storefront(
			[
				'benefits_heading' => '  <i>How it works</i> ',
				'learn_more'       => [
					'label' => ' <b>Subscription details</b> ',
					'url'   => 'https://example.com/details',
					'panel' => ' Plain <em>text</em> ',
				],
				'tag'              => str_repeat( 'x', 45 ),
			]
		);

		$this->assertSame( 'How it works', $out['benefits_heading'] );
		$this->assertSame( 'Subscription details', $out['learn_more']['label'] );
		$this->assertSame( 'https://example.com/details', $out['learn_more']['url'] );
		$this->assertSame( 'Plain text', $out['learn_more']['panel'] );
		$this->assertSame( 30, strlen( $out['tag'] ) );
	}

	public function test_unsafe_url_is_dropped_and_missing_fields_default_empty() {
		$out = PlanController::sanitize_storefront( [ 'learn_more' => [ 'url' => 'javascript:alert(1)' ] ] );

		$this->assertSame( '', $out['learn_more']['url'] );
		$this->assertSame( '', $out['tag'] );
		$this->assertSame( [], $out['benefits'] );
	}

	public function test_intervals_key_is_kept_only_when_valid() {
		$this->assertSame( 'chips', PlanController::sanitize_storefront( [ 'intervals' => 'chips' ] )['intervals'] );
		$this->assertSame( 'dropdown', PlanController::sanitize_storefront( [ 'intervals' => 'dropdown' ] )['intervals'] );
		$this->assertSame( '', PlanController::sanitize_storefront( [ 'intervals' => 'grid' ] )['intervals'] );
		$this->assertArrayNotHasKey( 'intervals', PlanController::sanitize_storefront( [ 'tag' => 'x' ] ) );
	}

	public function test_update_merges_into_existing_data() {
		$merged = PlanController::merge_storefront(
			[
				'x'          => 'keep me',
				'storefront' => [ 'intervals' => 'chips' ],
			],
			[ 'tag' => 'Cancel anytime' ]
		);

		$this->assertSame( 'keep me', $merged['x'] );
		$this->assertSame( 'Cancel anytime', $merged['storefront']['tag'] );
		$this->assertSame( 'chips', $merged['storefront']['intervals'], 'a field the form did not send is not erased' );
	}
}
