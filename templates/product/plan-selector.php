<?php
/**
 * Storefront plan selector — shared base template.
 *
 * A card per plan group: its radio and `<label for>` in the head, the price,
 * the group's terms as a radio group of their own, and a body that fires
 * `subscrpt_plan_card_body`. The card is a `<div>`, not a `<label>`, so the body
 * can hold form controls; plans.js makes a click anywhere on the card, outside
 * the body, select it. The chosen plan-term id posts via the hidden field.
 *
 * A group with terms renders them beneath its billing note; a group without
 * terms (One-Time, or a type free does not know) renders its label, price and
 * badge. The One-Time card only renders when a group of type `one_time` is
 * supplied.
 *
 * The discount badge belongs to the *selected term*, not to the card: each term
 * carries its own `data-badge`, and the selector writes it into the one badge
 * slot as the shopper moves between terms. The slot is rendered whenever any
 * term has a badge, hidden while the selected term has none, so a term that is
 * discounted can still show its saving after starting from one that is not.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector.php
 *
 * @var array       $groups  Plan groups (id, type, label, price, old_price, badge, terms[]).
 * @var \WC_Product $product Product, or the variation when `$context` is 'variation'.
 * @var string      $context Where the selector renders: 'page' or 'variation'.
 *
 * @package SpringDevs\Subscription
 */

defined( 'ABSPATH' ) || exit;

$context = isset( $context ) ? (string) $context : 'page';

// The first card is pre-selected; seed the posted plan id from its first term
// so the submitted value always matches the visible selection (One-Time = empty).
$default_plan_id = '';
if ( ! empty( $groups[0]['terms'] ) ) {
	$default_plan_id = $groups[0]['terms'][0]['id'];
}
?>
<div class="subscrpt-buybox" data-subscrpt-buybox data-subscrpt-context="<?php echo esc_attr( $context ); ?>">
	<input type="hidden" name="subscrpt_plan_id" value="<?php echo esc_attr( $default_plan_id ); ?>" data-subscrpt-plan-id />
	<?php foreach ( $groups as $index => $group ) : ?>
		<?php
		$gid        = 'subscrpt-grp-' . sanitize_html_class( $group['id'] );
		$is_first   = 0 === $index;
		$has_terms  = ! empty( $group['terms'] );
		$term_count = $has_terms ? count( $group['terms'] ) : 0;

		// The badge follows the selection, so the slot has to exist whenever any
		// term carries one — not only when the term shown first does.
		$badge_text = isset( $group['badge'] ) ? (string) $group['badge'] : '';
		$has_badge  = '' !== $badge_text;
		if ( ! $has_badge && $has_terms ) {
			foreach ( $group['terms'] as $subscrpt_term ) {
				if ( ! empty( $subscrpt_term['badge'] ) ) {
					$has_badge = true;
					break;
				}
			}
		}
		$price = isset( $group['price'] ) ? (string) $group['price'] : '';
		?>
		<div class="subscrpt-buybox__card<?php echo $is_first ? ' is-selected' : ''; ?>" data-subscrpt-card<?php echo 1 === $term_count ? ' data-subscrpt-single-term="' . esc_attr( $group['terms'][0]['id'] ) . '"' : ''; ?>>
			<div class="subscrpt-buybox__head">
				<input type="radio" class="subscrpt-buybox__radio" id="<?php echo esc_attr( $gid ); ?>" name="subscrpt_plan_group" value="<?php echo esc_attr( $group['id'] ); ?>"<?php echo $has_badge ? ' aria-describedby="' . esc_attr( $gid . '-badge' ) . '"' : ''; ?> <?php checked( $is_first ); ?> />
				<label class="subscrpt-buybox__label subscrpt-buybox__title" for="<?php echo esc_attr( $gid ); ?>"><?php echo esc_html( $group['label'] ); ?></label>
				<?php if ( $has_badge ) : ?>
					<span class="subscrpt-buybox__badge" id="<?php echo esc_attr( $gid . '-badge' ); ?>" data-subscrpt-badge<?php echo '' === $badge_text ? ' hidden' : ''; ?>><?php echo esc_html( $badge_text ); ?></span>
				<?php endif; ?>
				<?php if ( ! $has_terms && '' !== $price ) : ?>
					<span class="subscrpt-buybox__price">
						<?php if ( ! empty( $group['old_price'] ) ) : ?>
							<del><?php echo wp_kses_post( $group['old_price'] ); ?></del>
						<?php endif; ?>
						<ins data-subscrpt-card-price><?php echo wp_kses_post( $price ); ?></ins>
					</span>
				<?php endif; ?>
			</div>
			<?php if ( $has_terms ) : ?>
				<span class="subscrpt-buybox__note" data-subscrpt-note><?php echo wp_kses_post( $group['terms'][0]['note'] ); ?></span>
			<?php endif; ?>
			<?php if ( $term_count > 1 ) : ?>
				<fieldset class="subscrpt-buybox__terms" data-subscrpt-terms>
					<legend class="subscrpt-visually-hidden">
						<?php
						/* translators: %s: plan group name. */
						echo esc_html( sprintf( __( '%s: billing period', 'subscription' ), $group['label'] ) );
						?>
					</legend>
					<?php foreach ( $group['terms'] as $subscrpt_ti => $plan_term ) : ?>
						<?php
						$term_id    = $gid . '-term-' . sanitize_html_class( (string) $plan_term['id'] );
						$term_badge = isset( $plan_term['badge'] ) ? (string) $plan_term['badge'] : '';
						?>
						<input type="radio" class="subscrpt-buybox__term-radio subscrpt-visually-hidden" id="<?php echo esc_attr( $term_id ); ?>" name="subscrpt_plan_term[<?php echo esc_attr( $group['id'] ); ?>]" value="<?php echo esc_attr( $plan_term['id'] ); ?>" data-subscrpt-term data-price="<?php echo esc_attr( wp_strip_all_tags( $plan_term['price'] ) ); ?>" data-note="<?php echo esc_attr( $plan_term['note'] ); ?>" data-badge="<?php echo esc_attr( $term_badge ); ?>" <?php checked( 0 === $subscrpt_ti ); ?><?php echo $is_first ? '' : ' disabled'; ?> />
						<label class="subscrpt-buybox__term<?php echo 0 === $subscrpt_ti ? ' is-active' : ''; ?>" for="<?php echo esc_attr( $term_id ); ?>" data-subscrpt-term-btn data-term-id="<?php echo esc_attr( $plan_term['id'] ); ?>"><?php echo esc_html( $plan_term['label'] ); ?><?php if ( '' !== $term_badge ) : ?><span class="subscrpt-visually-hidden"> (<?php echo esc_html( $term_badge ); ?>)</span><?php endif; ?></label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
			<div class="subscrpt-buybox__body" data-subscrpt-card-body>
				<?php
				/**
				 * Fires inside every purchase option card, after its terms.
				 *
				 * The output lands in the card's body, where a click does not
				 * select the card. On the variation path the markup is rendered
				 * per variation and swapped in, so render heavy UI only for 'page'.
				 *
				 * @param array       $group   The plan group the card renders.
				 * @param \WC_Product $product Product, or the variation when `$context` is 'variation'.
				 * @param string      $context Where the selector renders: 'page' or 'variation'.
				 */
				do_action( 'subscrpt_plan_card_body', $group, $product, $context );
				?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
