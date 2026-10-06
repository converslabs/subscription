<?php
/**
 * Plan selector layout — stacked cards (the default).
 *
 * A card per plan group: its radio and `<label for>` in the head, the price,
 * the group's terms (chips or a dropdown), and a body that fires
 * `subscrpt_plan_card_body`. The card is a `<div>`, not a `<label>`, so the body
 * can hold form controls; plans.js makes a click anywhere on the card, outside
 * the body, select it. The chosen plan-term id posts via the hidden field.
 *
 * A group with terms renders them beneath its billing note; a group without
 * terms (One-Time, or a type free does not know) renders its label, price and
 * badge.
 *
 * The discount badge belongs to the *selected term*, not to the card: each term
 * carries its own `data-badge`, and the selector writes it into the one badge
 * slot as the shopper moves between terms. The slot is rendered whenever any
 * term has a badge, hidden while the selected term has none.
 *
 * A group's `storefront` fields add a tag ribbon, a benefits list and a learn-more
 * link or details panel. The link or button sits in the body, so using it does
 * not select the card.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/stacked.php
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
<div class="subscrpt-buybox" data-subscrpt-buybox data-subscrpt-context="<?php echo esc_attr( $context ); ?>" data-subscrpt-layout="<?php echo esc_attr( isset( $layout ) ? $layout : 'stacked' ); ?>">
	<input type="hidden" name="subscrpt_plan_id" value="<?php echo esc_attr( PlanSelectorView::default_plan_id( $groups ) ); ?>" data-subscrpt-plan-id />
	<?php foreach ( $groups as $index => $group ) : ?>
		<?php $view = PlanSelectorView::group( $group, $index ); ?>
		<div class="subscrpt-buybox__card<?php echo $view['is_first'] ? ' is-selected' : ''; ?>" data-subscrpt-card<?php echo 1 === $view['term_count'] ? ' data-subscrpt-single-term="' . esc_attr( $group['terms'][0]['id'] ) . '"' : ''; ?>>
			<?php if ( '' !== $view['tag'] ) : ?>
				<span class="subscrpt-buybox__ribbon"><?php echo esc_html( $view['tag'] ); ?></span>
			<?php endif; ?>
			<div class="subscrpt-buybox__head">
				<input type="radio" class="subscrpt-buybox__radio" id="<?php echo esc_attr( $view['gid'] ); ?>" name="subscrpt_plan_group" value="<?php echo esc_attr( $group['id'] ); ?>"<?php echo $view['has_badge'] ? ' aria-describedby="' . esc_attr( $view['gid'] . '-badge' ) . '"' : ''; ?> <?php checked( $view['is_first'] ); ?> />
				<label class="subscrpt-buybox__label subscrpt-buybox__title" for="<?php echo esc_attr( $view['gid'] ); ?>"><?php echo esc_html( $group['label'] ); ?></label>
				<?php if ( $view['has_badge'] ) : ?>
					<span class="subscrpt-buybox__badge" id="<?php echo esc_attr( $view['gid'] . '-badge' ); ?>" data-subscrpt-badge<?php echo '' === $view['badge_text'] ? ' hidden' : ''; ?>><?php echo esc_html( $view['badge_text'] ); ?></span>
				<?php endif; ?>
				<?php if ( '' !== $view['price'] ) : ?>
					<span class="subscrpt-buybox__price">
						<?php if ( $view['has_terms'] ) : ?>
							<del data-subscrpt-card-regular<?php echo '' === $view['first_regular'] ? ' hidden' : ''; ?>><?php echo wp_kses_post( $view['first_regular'] ); ?></del>
						<?php elseif ( ! empty( $group['old_price'] ) ) : ?>
							<del><?php echo wp_kses_post( $group['old_price'] ); ?></del>
						<?php endif; ?>
						<ins data-subscrpt-card-price><?php echo wp_kses_post( $view['price'] ); ?></ins>
					</span>
				<?php endif; ?>
			</div>
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
	<?php endforeach; ?>
</div>
