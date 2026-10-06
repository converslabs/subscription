<?php
/**
 * Plan selector part — an option's discount badge.
 *
 * Rendered whenever any of the group's terms has a badge, hidden while the
 * selected term has none; plans.js writes the selected term's badge into it.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/parts/badge.php
 *
 * @var array $view `PlanSelectorView::group()` for the group.
 *
 * @package SpringDevs\Subscription
 */

defined( 'ABSPATH' ) || exit;

if ( ! $view['has_badge'] ) {
	return;
}
?>
<span class="subscrpt-buybox__badge" id="<?php echo esc_attr( $view['gid'] . '-badge' ); ?>" data-subscrpt-badge<?php echo '' === $view['badge_text'] ? ' hidden' : ''; ?>><?php echo esc_html( $view['badge_text'] ); ?></span>
