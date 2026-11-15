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
				<strong><?php esc_html_e( 'PayPal cannot bill this product.', 'subscription' ); ?></strong>
				<?php esc_html_e( 'PayPal accepts a billing period or free trial of at most 365 days, 52 weeks, 12 months or 1 year, so a customer paying with PayPal will not be able to check out:', 'subscription' ); ?>
			</p>
			<ul class="ul-disc">
				<?php foreach ( $problems as $problem ) : ?>
					<li><?php echo esc_html( $problem ); ?></li>
				<?php endforeach; ?>
			</ul>
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
				/* translators: 1: plan name, 2: interval count, 3: interval unit. */
				$problems[] = sprintf( __( '%1$s: billed every %2$d %3$s', 'subscription' ), $label, $count, $unit );
			}

			$trial      = (int) $row['free_trial'];
			$trial_unit = isset( $row['plan_data']['free_trial_interval'] ) ? (string) $row['plan_data']['free_trial_interval'] : 'days';

			if ( $trial > 0 && self::exceeds_limit( $trial, $trial_unit ) ) {
				/* translators: 1: plan name, 2: trial length, 3: trial unit. */
				$problems[] = sprintf( __( '%1$s: %2$d %3$s free trial', 'subscription' ), $label, $trial, $trial_unit );
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
				/* translators: 1: product or variation name, 2: interval count, 3: interval unit. */
				$problems[] = sprintf( __( '%1$s: billed every %2$d %3$s', 'subscription' ), $item->get_name(), $count, $unit );
			}

			$trial      = $subs_product->get_trial_timing_per();
			$trial_unit = (string) $subs_product->get_trial_timing_option();

			if ( $trial > 0 && self::exceeds_limit( $trial, $trial_unit ) ) {
				/* translators: 1: product or variation name, 2: trial length, 3: trial unit. */
				$problems[] = sprintf( __( '%1$s: %2$d %3$s free trial', 'subscription' ), $item->get_name(), $trial, $trial_unit );
			}
		}

		return array_values( array_unique( $problems ) );
	}
}
