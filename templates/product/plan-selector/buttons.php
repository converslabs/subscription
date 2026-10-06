<?php
/**
 * Plan selector layout — a segmented button row.
 *
 * One segment per plan group: a radio named `subscrpt_plan_group`, hidden behind
 * the `<label for>` that draws its button, so the row is a radio group (Tab
 * reaches it, the arrow keys move along it). Under the row, the selected group's
 * price, badge, tag and note, with its intervals as a dropdown; every group's
 * panel is rendered, and plans.js shows the selected one
 * (`data-subscrpt-only-selected`). A segment has no room for a body, so each
 * group's body — benefits, learn-more and `subscrpt_plan_card_body` — renders
 * below in `<div data-subscrpt-body-for="<group id>">`, hidden unless that group
 * is the selected one.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/buttons.php
 *
 * @var array       $groups  Plan groups (id, type, label, price, old_price, badge, terms_heading, terms[]).
 * @var \WC_Product $product Product, or the variation when `$context` is 'variation'.
 * @var string      $context Where the selector renders: 'page' or 'variation'.
 * @var string      $layout  This layout's key.
 *
 * @package SpringDevs\Subscription
 */

use SpringDevs\Subscription\Frontend\PlanSelectorView;

defined( 'ABSPATH' ) || exit;

$context = isset( $context ) ? (string) $context : 'page';
$views   = [];
foreach ( $groups as $index => $group ) {
	$views[ $index ] = PlanSelectorView::group( $group, $index );
}
?>
<div class="subscrpt-buybox" data-subscrpt-buybox data-subscrpt-context="<?php echo esc_attr( $context ); ?>" data-subscrpt-layout="<?php echo esc_attr( isset( $layout ) ? $layout : 'buttons' ); ?>">
	<input type="hidden" name="subscrpt_plan_id" value="<?php echo esc_attr( PlanSelectorView::default_plan_id( $groups ) ); ?>" data-subscrpt-plan-id />
	<fieldset class="subscrpt-buybox__segments">
		<legend class="subscrpt-buybox__field-label"><?php esc_html_e( 'Purchase option', 'subscription' ); ?></legend>
		<div class="subscrpt-buybox__segment-row">
			<?php foreach ( $groups as $index => $group ) : ?>
				<?php $view = $views[ $index ]; ?>
				<input type="radio" class="subscrpt-buybox__segment-radio subscrpt-visually-hidden" id="<?php echo esc_attr( $view['gid'] ); ?>" name="subscrpt_plan_group" value="<?php echo esc_attr( $group['id'] ); ?>"<?php echo $view['has_badge'] ? ' aria-describedby="' . esc_attr( $view['gid'] . '-badge' ) . '"' : ''; ?> <?php checked( $view['is_first'] ); ?> />
				<label class="subscrpt-buybox__segment" for="<?php echo esc_attr( $view['gid'] ); ?>"><?php echo esc_html( $group['label'] ); ?></label>
			<?php endforeach; ?>
		</div>
	</fieldset>
	<?php foreach ( $groups as $index => $group ) : ?>
		<?php $view = $views[ $index ]; ?>
		<div class="subscrpt-buybox__option<?php echo $view['is_first'] ? ' is-selected' : ''; ?>" data-subscrpt-card data-subscrpt-group="<?php echo esc_attr( $group['id'] ); ?>" data-subscrpt-only-selected<?php echo 1 === $view['term_count'] ? ' data-subscrpt-single-term="' . esc_attr( $group['terms'][0]['id'] ) . '"' : ''; ?><?php echo $view['is_first'] ? '' : ' hidden'; ?>>
			<?php if ( '' !== $view['price'] || $view['has_badge'] || '' !== $view['tag'] ) : ?>
				<div class="subscrpt-buybox__head">
					<?php wc_get_template( 'product/plan-selector/parts/price.php', [ 'group' => $group, 'view' => $view ], 'subscription', SUBSCRPT_TEMPLATES ); ?>
					<?php wc_get_template( 'product/plan-selector/parts/badge.php', [ 'view' => $view ], 'subscription', SUBSCRPT_TEMPLATES ); ?>
					<?php if ( '' !== $view['tag'] ) : ?>
						<span class="subscrpt-buybox__ribbon"><?php echo esc_html( $view['tag'] ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( $view['has_terms'] ) : ?>
				<span class="subscrpt-buybox__note" data-subscrpt-note><?php echo wp_kses_post( $group['terms'][0]['note'] ); ?></span>
			<?php endif; ?>
			<?php
			wc_get_template(
				'product/plan-selector/parts/terms.php',
				[
					'group'     => $group,
					'view'      => $view,
					'intervals' => 'dropdown',
				],
				'subscription',
				SUBSCRPT_TEMPLATES
			);
			?>
		</div>
	<?php endforeach; ?>
	<?php foreach ( $groups as $index => $group ) : ?>
		<?php $view = $views[ $index ]; ?>
		<div class="subscrpt-buybox__body" data-subscrpt-card-body data-subscrpt-body-for="<?php echo esc_attr( $group['id'] ); ?>"<?php echo $view['is_first'] ? '' : ' hidden'; ?>>
			<?php
			wc_get_template( 'product/plan-selector/parts/benefits.php', [ 'view' => $view ], 'subscription', SUBSCRPT_TEMPLATES );
			wc_get_template(
				'product/plan-selector/parts/body.php',
				[
					'group'   => $group,
					'view'    => $view,
					'product' => $product,
					'context' => $context,
				],
				'subscription',
				SUBSCRPT_TEMPLATES
			);
			?>
		</div>
	<?php endforeach; ?>
</div>
