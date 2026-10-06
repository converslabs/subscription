<?php
/**
 * Plan selector part — an option's discount badge.
 *
 * Rendered whenever any of the group's terms has a badge, hidden while the
 * selected term has none; plans.js writes the selected term's badge into it.
 * With a `$fallback`, a selected term without a badge shows the fallback
 * instead (`data-subscrpt-badge-fallback`), so the slot is never empty.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/parts/badge.php
 *
 * @var array  $view     `PlanSelectorView::group()` for the group.
 * @var string $fallback Optional. What the badge says while the selected term has none.
 *
 * @package SpringDevs\Subscription
 */

defined( 'ABSPATH' ) || exit;

$fallback = isset( $fallback ) ? (string) $fallback : '';
if ( ! $view['has_badge'] && '' === $fallback ) {
	return;
}
$subscrpt_badge = '' !== $view['badge_text'] ? $view['badge_text'] : $fallback;
?>
<span class="subscrpt-buybox__badge" id="<?php echo esc_attr( $view['gid'] . '-badge' ); ?>" data-subscrpt-badge<?php echo '' !== $fallback ? ' data-subscrpt-badge-fallback="' . esc_attr( $fallback ) . '"' : ''; ?><?php echo '' === $subscrpt_badge ? ' hidden' : ''; ?>><?php echo esc_html( $subscrpt_badge ); ?></span>
