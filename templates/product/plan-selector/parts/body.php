<?php
/**
 * Plan selector part — what goes in a group's body: the learn-more link or
 * details panel, then whatever `subscrpt_plan_card_body` adds.
 *
 * The layout wraps it in `[data-subscrpt-card-body]`, where a click does not
 * select the option.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/parts/body.php
 *
 * @var array       $group   Plan group.
 * @var array       $view    `PlanSelectorView::group()` for the group.
 * @var \WC_Product $product Product, or the variation when `$context` is 'variation'.
 * @var string      $context Where the selector renders: 'page' or 'variation'.
 *
 * @package SpringDevs\Subscription
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( '' !== $view['learn_url'] ) : ?>
	<a class="subscrpt-buybox__learn-more" href="<?php echo esc_url( $view['learn_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $view['learn_label'] ); ?></a>
<?php elseif ( '' !== $view['learn_panel'] ) : ?>
	<button type="button" class="subscrpt-buybox__learn-more" aria-expanded="false" aria-controls="<?php echo esc_attr( $view['gid'] . '-details' ); ?>" data-subscrpt-details-toggle><?php echo esc_html( $view['learn_label'] ); ?></button>
	<div class="subscrpt-buybox__details" id="<?php echo esc_attr( $view['gid'] . '-details' ); ?>" hidden><?php echo esc_html( $view['learn_panel'] ); ?></div>
<?php endif; ?>
<?php
/**
 * Fires once per purchase option, in its body.
 *
 * In the stacked layout the body is inside the option's card, after its terms.
 * A layout with no room in its options (classic, dropdown) renders one body per
 * group below the control, in `<div data-subscrpt-body-for="<group id>">`, and
 * shows only the selected group's — so the output follows the shopper's choice.
 * Either way a click in the body does not select the option. On the variation
 * path the markup is rendered per variation and swapped in, so render heavy UI
 * only for 'page'.
 *
 * @param array       $group   The plan group the body belongs to.
 * @param \WC_Product $product Product, or the variation when `$context` is 'variation'.
 * @param string      $context Where the selector renders: 'page' or 'variation'.
 */
do_action( 'subscrpt_plan_card_body', $group, $product, $context );
