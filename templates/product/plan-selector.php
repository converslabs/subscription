<?php
/**
 * Storefront plan selector (free) — simple products.
 *
 * Renders each plan group tied to the product as a selectable radio card; a
 * group's terms render as buttons beneath its billing note. The chosen plan-term
 * id posts via the hidden field. Pro layers One-Time, discount badges and
 * variable-product support on top.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector.php
 *
 * @var array $groups Plan groups (id, type, label, price, terms[]).
 *
 * @package SpringDevs\Subscription
 */

defined( 'ABSPATH' ) || exit;

// The first card is pre-selected; seed the posted plan id from its first term
// so the submitted value always matches the visible selection.
$default_plan_id = '';
if ( ! empty( $groups[0]['terms'] ) ) {
	$default_plan_id = $groups[0]['terms'][0]['id'];
}
?>
<div class="subscrpt-buybox" data-subscrpt-buybox>
	<input type="hidden" name="subscrpt_plan_id" value="<?php echo esc_attr( $default_plan_id ); ?>" data-subscrpt-plan-id />
	<?php foreach ( $groups as $index => $group ) : ?>
		<?php
		$gid       = 'subscrpt-grp-' . sanitize_html_class( $group['id'] );
		$is_first  = 0 === $index;
		$has_terms = ! empty( $group['terms'] );
		?>
		<label class="subscrpt-buybox__card <?php echo $is_first ? 'is-selected' : ''; ?>" for="<?php echo esc_attr( $gid ); ?>" data-subscrpt-card<?php echo ( $has_terms && 1 === count( $group['terms'] ) ) ? ' data-subscrpt-single-term="' . esc_attr( $group['terms'][0]['id'] ) . '"' : ''; ?>>
			<span class="subscrpt-buybox__head">
				<input type="radio" class="subscrpt-buybox__radio" id="<?php echo esc_attr( $gid ); ?>" name="subscrpt_plan_group" value="<?php echo esc_attr( $group['id'] ); ?>" <?php checked( $is_first ); ?> />
				<?php if ( $has_terms ) : ?>
					<span class="subscrpt-buybox__label"><?php echo esc_html( $group['label'] ); ?></span>
				<?php endif; ?>
			</span>
			<?php if ( $has_terms ) : ?>
				<span class="subscrpt-buybox__note" data-subscrpt-note><?php echo wp_kses_post( $group['terms'][0]['note'] ); ?></span>
			<?php endif; ?>
			<?php if ( $has_terms && count( $group['terms'] ) > 1 ) : ?>
				<span class="subscrpt-buybox__terms" data-subscrpt-terms>
					<?php foreach ( $group['terms'] as $subscrpt_ti => $plan_term ) : ?>
						<button type="button" class="subscrpt-buybox__term<?php echo 0 === $subscrpt_ti ? ' is-active' : ''; ?>" data-subscrpt-term-btn data-term-id="<?php echo esc_attr( $plan_term['id'] ); ?>" data-price="<?php echo esc_attr( wp_strip_all_tags( $plan_term['price'] ) ); ?>" data-note="<?php echo esc_attr( $plan_term['note'] ); ?>"><?php echo esc_html( $plan_term['label'] ); ?></button>
					<?php endforeach; ?>
				</span>
			<?php endif; ?>
		</label>
	<?php endforeach; ?>
</div>
