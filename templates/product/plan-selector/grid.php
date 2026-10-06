<?php
/**
 * Plan selector layout — a grid of equal-width tiles.
 *
 * A tile per plan group, side by side: its radio and `<label for>`, the price,
 * the billing note, the terms (chips or a dropdown), the benefits and a body that
 * fires `subscrpt_plan_card_body`. The tiles stack on a narrow screen.
 *
 * The grid with savings layout renders this partial with `$lead` set to
 * 'saving': each tile then leads with its discount badge, in a band of its own
 * that every tile keeps, so the tiles line up whether they save or not. While
 * the selected term saves nothing, the band names the group's best saving,
 * "Save up to 20%".
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/grid.php
 *
 * @var array       $groups  Plan groups (id, type, label, price, old_price, badge, terms_heading, terms[]).
 * @var \WC_Product $product Product, or the variation when `$context` is 'variation'.
 * @var string      $context Where the selector renders: 'page' or 'variation'.
 * @var string      $layout  This layout's key.
 * @var string      $lead    'saving' to lead each tile with its badge; '' otherwise.
 *
 * @package SpringDevs\Subscription
 */

use SpringDevs\Subscription\Frontend\PlanSelectorView;

defined( 'ABSPATH' ) || exit;

$context = isset( $context ) ? (string) $context : 'page';
$lead    = isset( $lead ) && 'saving' === $lead;
?>
<div class="subscrpt-buybox" data-subscrpt-buybox data-subscrpt-context="<?php echo esc_attr( $context ); ?>" data-subscrpt-layout="<?php echo esc_attr( isset( $layout ) ? $layout : 'grid' ); ?>">
	<input type="hidden" name="subscrpt_plan_id" value="<?php echo esc_attr( PlanSelectorView::default_plan_id( $groups ) ); ?>" data-subscrpt-plan-id />
	<div class="subscrpt-buybox__tiles">
		<?php foreach ( $groups as $index => $group ) : ?>
			<?php $view = PlanSelectorView::group( $group, $index ); ?>
			<div class="subscrpt-buybox__card subscrpt-buybox__tile<?php echo $view['is_first'] ? ' is-selected' : ''; ?>" data-subscrpt-card data-subscrpt-group="<?php echo esc_attr( $group['id'] ); ?>"<?php echo 1 === $view['term_count'] ? ' data-subscrpt-single-term="' . esc_attr( $group['terms'][0]['id'] ) . '"' : ''; ?>>
				<?php if ( $lead ) : ?>
					<div class="subscrpt-buybox__lead">
						<?php
						wc_get_template(
							'product/plan-selector/parts/badge.php',
							[
								'view'     => $view,
								'fallback' => $view['best_saving'],
							],
							'subscription',
							SUBSCRPT_TEMPLATES
						);
						?>
					</div>
				<?php endif; ?>
				<?php if ( '' !== $view['tag'] ) : ?>
					<span class="subscrpt-buybox__ribbon"><?php echo esc_html( $view['tag'] ); ?></span>
				<?php endif; ?>
				<div class="subscrpt-buybox__head">
					<input type="radio" class="subscrpt-buybox__radio" id="<?php echo esc_attr( $view['gid'] ); ?>" name="subscrpt_plan_group" value="<?php echo esc_attr( $group['id'] ); ?>"<?php echo $view['has_badge'] ? ' aria-describedby="' . esc_attr( $view['gid'] . '-badge' ) . '"' : ''; ?> <?php checked( $view['is_first'] ); ?> />
					<label class="subscrpt-buybox__label" for="<?php echo esc_attr( $view['gid'] ); ?>"><?php echo esc_html( $group['label'] ); ?></label>
					<?php
					if ( ! $lead ) {
						wc_get_template( 'product/plan-selector/parts/badge.php', [ 'view' => $view ], 'subscription', SUBSCRPT_TEMPLATES );
					}
					?>
				</div>
				<?php wc_get_template( 'product/plan-selector/parts/price.php', [ 'group' => $group, 'view' => $view ], 'subscription', SUBSCRPT_TEMPLATES ); ?>
				<?php wc_get_template( 'product/plan-selector/parts/note.php', [ 'group' => $group, 'view' => $view ], 'subscription', SUBSCRPT_TEMPLATES ); ?>
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
		<?php endforeach; ?>
	</div>
</div>
