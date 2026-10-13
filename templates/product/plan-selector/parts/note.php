<?php
/**
 * Plan selector part — a group's billing note, for its first term.
 *
 * Renders only for a group with terms; plans.js rewrites it with the selected
 * term's note.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/parts/note.php
 *
 * @var array $group Plan group.
 * @var array $view  `PlanSelectorView::group()` for the group.
 *
 * @package SpringDevs\Subscription
 */

defined( 'ABSPATH' ) || exit;

if ( ! $view['has_terms'] ) {
	return;
}
?>
<span class="subscrpt-buybox__note" data-subscrpt-note><?php echo wp_kses_post( $group['terms'][0]['note'] ); ?></span>
