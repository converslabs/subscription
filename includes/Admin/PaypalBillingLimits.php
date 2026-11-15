<?php
/**
 * PayPal billing-period limits on the product edit screen.
 *
 * @package SpringDevs\Subscription\Admin
 */

namespace SpringDevs\Subscription\Admin;

use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;

/**
 * Warns when a product bills on a period PayPal cannot create a plan for.
 *
 * PayPal accepts a billing or trial frequency of at most 365 days, 52 weeks,
 * 12 months or 1 year. Anything longer is rejected when the plan is created,
 * so the customer's PayPal checkout fails. The terms are read when the screen
 * renders, so the notice follows the plans attached to the product as well as
 * its own timing.
 */
class PaypalBillingLimits {

	/**
	 * Largest interval count PayPal accepts for each unit.
	 */
	const MAX_COUNT = array(
		'day'   => 365,
		'week'  => 52,
		'month' => 12,
		'year'  => 1,
	);

	/**
	 * Hook the notice.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Whether PayPal cannot bill a period of this length.
	 *
	 * @param int    $count Interval count, e.g. 2 in "every 2 years".
	 * @param string $unit  Interval unit, singular or plural ("year", "years").
	 *
	 * @return bool
	 */
	public static function exceeds_limit( int $count, string $unit ): bool {
		$unit = rtrim( strtolower( trim( $unit ) ), 's' );

		return isset( self::MAX_COUNT[ $unit ] ) && $count > self::MAX_COUNT[ $unit ];
	}

	/**
	 * Print the warning on the edit screen of a product PayPal cannot bill.
	 *
	 * @return void
	 */
	public function render_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'product' !== $screen->id || 'post' !== $screen->base || ! self::paypal_enabled() ) {
			return;
		}

		$product = wc_get_product( get_the_ID() );

		if ( ! $product ) {
			return;
		}

		$problems = self::find_problems( $product );

		if ( empty( $problems ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Some billing periods on this product are too long for PayPal.', 'subscription' ); ?></strong>
				<?php esc_html_e( 'The longest period PayPal can bill, or give as a free trial, is 1 year (365 days, 52 weeks or 12 months). Anything shorter works. Customers who pick one of these and pay with PayPal will not be able to check out:', 'subscription' ); ?>
			</p>
			<ul class="ul-disc">
				<?php foreach ( $problems as $problem ) : ?>
					<li><?php echo esc_html( $problem ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><?php esc_html_e( 'Shorten these periods, or let customers pay for them with another payment method.', 'subscription' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Whether the PayPal for WPSubscription gateway is enabled.
	 *
	 * @return bool
	 */
	private static function paypal_enabled(): bool {
		$settings = get_option( 'woocommerce_wp_subscription_paypal_settings', array() );

		return is_array( $settings ) && 'yes' === ( $settings['enabled'] ?? 'no' );
	}

	/**
	 * One line of the notice: what is too long, and PayPal's limit for that unit.
	 *
	 * @param string $label Plan, product or variation name.
	 * @param int    $count Interval count.
	 * @param string $unit  Interval unit, singular or plural.
	 * @param bool   $trial Whether the period is a free trial.
	 *
	 * @return string
	 */
	private static function describe( string $label, int $count, string $unit, bool $trial ): string {
		$unit = rtrim( strtolower( trim( $unit ) ), 's' );

		$period = array(
			/* translators: %d: number of days. */
			'day'   => sprintf( _n( '%d day', '%d days', $count, 'subscription' ), $count ),
			/* translators: %d: number of weeks. */
			'week'  => sprintf( _n( '%d week', '%d weeks', $count, 'subscription' ), $count ),
			/* translators: %d: number of months. */
			'month' => sprintf( _n( '%d month', '%d months', $count, 'subscription' ), $count ),
			/* translators: %d: number of years. */
			'year'  => sprintf( _n( '%d year', '%d years', $count, 'subscription' ), $count ),
		);

		$limit = array(
			'day'   => __( '365 days', 'subscription' ),
			'week'  => __( '52 weeks', 'subscription' ),
			'month' => __( '12 months', 'subscription' ),
			'year'  => __( '1 year', 'subscription' ),
		);

		if ( $trial ) {
			/* translators: 1: plan or product name, 2: trial length such as "400 days", 3: PayPal's limit such as "365 days". */
			return sprintf( __( '%1$s: %2$s free trial (PayPal allows up to %3$s)', 'subscription' ), $label, $period[ $unit ], $limit[ $unit ] );
		}

		/* translators: 1: plan or product name, 2: billing period such as "2 years", 3: PayPal's limit such as "1 year". */
		return sprintf( __( '%1$s: billed every %2$s (PayPal allows up to %3$s)', 'subscription' ), $label, $period[ $unit ], $limit[ $unit ] );
	}

	/**
	 * Describe every billing period or trial on the product that PayPal rejects.
	 *
	 * @param \WC_Product $product Product being edited.
	 *
	 * @return string[]
	 */
	private static function find_problems( $product ): array {
		$problems = array();

		// Plans attached to the product or to one of its variations.
		foreach ( PlanRepository::resolve_for_product( $product->get_id() ) as $row ) {
			$label = $row['group_title'] . ' · ' . $row['plan_title'];
			$count = max( 1, (int) $row['billing_frequency'] );
			$unit  = PlanRepository::interval_to_option( (int) $row['billing_interval'] );

			if ( self::exceeds_limit( $count, $unit ) ) {
				$problems[] = self::describe( $label, $count, $unit, false );
			}

			$trial      = (int) $row['free_trial'];
			$trial_unit = isset( $row['plan_data']['free_trial_interval'] ) ? (string) $row['plan_data']['free_trial_interval'] : 'days';

			if ( $trial > 0 && self::exceeds_limit( $trial, $trial_unit ) ) {
				$problems[] = self::describe( $label, $trial, $trial_unit, true );
			}
		}

		// The product's own timing, and each variation's.
		$products = $product->is_type( 'variable' ) ? array_filter( array_map( 'wc_get_product', $product->get_children() ) ) : array( $product );

		foreach ( $products as $item ) {
			$subs_product = Subscription::get_subs_product( $item );

			if ( ! $subs_product || ! $subs_product->is_enabled() ) {
				continue;
			}

			$count = $subs_product->get_timing_per();
			$unit  = (string) $item->get_meta( '_subscrpt_timing_option' );

			if ( self::exceeds_limit( $count, $unit ) ) {
				$problems[] = self::describe( $item->get_name(), $count, $unit, false );
			}

			$trial      = $subs_product->get_trial_timing_per();
			$trial_unit = (string) $subs_product->get_trial_timing_option();

			if ( $trial > 0 && self::exceeds_limit( $trial, $trial_unit ) ) {
				$problems[] = self::describe( $item->get_name(), $trial, $trial_unit, true );
			}
		}

		return array_values( array_unique( $problems ) );
	}
}
