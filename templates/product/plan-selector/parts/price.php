<?php
/**
 * Plan selector part — an option's price: the offer, and the struck regular
 * price when the selected term (or the One-Time price) saves on it.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/parts/price.php
 *
 * @var array $group Plan group.
 * @var array $view  `PlanSelectorView::group()` for the group.
 *
 * @package SpringDevs\Subscription
 */

defined( 'ABSPATH' ) || exit;

if ( '' === $view['price'] ) {
	return;
}
?>
<span class="subscrpt-buybox__price">
	<?php if ( $view['has_terms'] ) : ?>
		<del data-subscrpt-card-regular<?php echo '' === $view['first_regular'] ? ' hidden' : ''; ?>><?php echo wp_kses_post( $view['first_regular'] ); ?></del>
	<?php elseif ( ! empty( $group['old_price'] ) ) : ?>
		<del><?php echo wp_kses_post( $group['old_price'] ); ?></del>
	<?php endif; ?>
	<ins data-subscrpt-card-price><?php echo wp_kses_post( $view['price'] ); ?></ins>
</span>
