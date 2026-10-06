<?php
/**
 * Plan selector layout — an accordion.
 *
 * A card per plan group, collapsed to one line: its radio and `<label for>`, the
 * merchant's tag, the discount badge and the price. The selected card opens a
 * panel under the line with the billing note, the terms (chips or a dropdown),
 * the benefits and a body that fires `subscrpt_plan_card_body`. The radio
 * names its panel with `aria-controls` (a radio takes no `aria-expanded`; the
 * checked state says which is open); plans.js opens the selected card's panel
 * and hides the others (`data-subscrpt-panel`).
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/accordion.php
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
?>
<div class="subscrpt-buybox" data-subscrpt-buybox data-subscrpt-context="<?php echo esc_attr( $context ); ?>" data-subscrpt-layout="<?php echo esc_attr( isset( $layout ) ? $layout : 'accordion' ); ?>">
	<input type="hidden" name="subscrpt_plan_id" value="<?php echo esc_attr( PlanSelectorView::default_plan_id( $groups ) ); ?>" data-subscrpt-plan-id />
	<?php foreach ( $groups as $index => $group ) : ?>
		<?php
		$view     = PlanSelectorView::group( $group, $index );
		$panel_id = $view['gid'] . '-panel';
		?>
		<div class="subscrpt-buybox__card<?php echo $view['is_first'] ? ' is-selected' : ''; ?>" data-subscrpt-card data-subscrpt-group="<?php echo esc_attr( $group['id'] ); ?>"<?php echo 1 === $view['term_count'] ? ' data-subscrpt-single-term="' . esc_attr( $group['terms'][0]['id'] ) . '"' : ''; ?>>
			<div class="subscrpt-buybox__head">
				<input type="radio" class="subscrpt-buybox__radio" id="<?php echo esc_attr( $view['gid'] ); ?>" name="subscrpt_plan_group" value="<?php echo esc_attr( $group['id'] ); ?>" aria-controls="<?php echo esc_attr( $panel_id ); ?>"<?php echo $view['has_badge'] ? ' aria-describedby="' . esc_attr( $view['gid'] . '-badge' ) . '"' : ''; ?> <?php checked( $view['is_first'] ); ?> />
				<label class="subscrpt-buybox__label" for="<?php echo esc_attr( $view['gid'] ); ?>"><?php echo esc_html( $group['label'] ); ?></label>
				<?php if ( '' !== $view['tag'] ) : ?>
					<span class="subscrpt-buybox__ribbon"><?php echo esc_html( $view['tag'] ); ?></span>
				<?php endif; ?>
				<?php wc_get_template( 'product/plan-selector/parts/badge.php', [ 'view' => $view ], 'subscription', SUBSCRPT_TEMPLATES ); ?>
				<?php wc_get_template( 'product/plan-selector/parts/price.php', [ 'group' => $group, 'view' => $view ], 'subscription', SUBSCRPT_TEMPLATES ); ?>
			</div>
			<div class="subscrpt-buybox__panel" id="<?php echo esc_attr( $panel_id ); ?>" data-subscrpt-panel<?php echo $view['is_first'] ? '' : ' hidden'; ?>>
				<?php if ( $view['has_terms'] ) : ?>
					<span class="subscrpt-buybox__note" data-subscrpt-note><?php echo wp_kses_post( $group['terms'][0]['note'] ); ?></span>
				<?php endif; ?>
				<?php
				wc_get_template(
					'product/plan-selector/parts/terms.php',
					[
						'group'     => $group,
						'view'      => $view,
						'intervals' => $view['intervals'],
					],
					'subscription',
					SUBSCRPT_TEMPLATES
				);
				wc_get_template( 'product/plan-selector/parts/benefits.php', [ 'view' => $view ], 'subscription', SUBSCRPT_TEMPLATES );
				?>
				<div class="subscrpt-buybox__body" data-subscrpt-card-body>
					<?php
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
			</div>
		</div>
	<?php endforeach; ?>
</div>
