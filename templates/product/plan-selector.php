<?php
/**
 * Single-line plan display (free storefront).
 *
 * Shown on a simple product that is tied to a Recurring plan, in place of the
 * classic price suffix. Pro overrides this with a multi-plan selector.
 *
 * @package SpringDevs\Subscription
 *
 * @var string $price_html   Formatted recurring price (wc_price()).
 * @var string $period_label Billing period, e.g. "month" or "3 months".
 * @var string $trial_html   Optional trial suffix HTML ('' when none).
 * @var int    $plan_id      Resolved plan term id (carried to the cart).
 */

defined( 'ABSPATH' ) || exit;
?>
<span class="wpsubs-plan-line" data-subscrpt-plan-id="<?php echo esc_attr( $plan_id ); ?>">
	<?php
	printf(
		/* translators: 1: formatted price, 2: billing period (e.g. "month" or "3 months"). */
		wp_kses_post( __( 'Subscribe &mdash; renew every %2$s: %1$s', 'subscription' ) ),
		wp_kses_post( $price_html ),
		esc_html( $period_label )
	);
	echo wp_kses_post( $trial_html );
	?>
</span>
