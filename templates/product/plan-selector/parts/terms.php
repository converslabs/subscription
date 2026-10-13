<?php
/**
 * Plan selector part — a group's terms, as chips or as a dropdown.
 *
 * Renders only for a group of more than one term. Chips are a radio group, each
 * radio hidden behind the `<label>` that draws its pill; a dropdown is one
 * `<select>` whose options carry the same data. Either way the controls are
 * named `subscrpt_plan_term[<group id>]` and start disabled unless their group
 * is the selected one, and plans.js posts the chosen term as `subscrpt_plan_id`.
 *
 * Override by copying to <your_theme>/subscription/product/plan-selector/parts/terms.php
 *
 * @var array  $group     Plan group.
 * @var array  $view      `PlanSelectorView::group()` for the group.
 * @var string $intervals 'chips' or 'dropdown'.
 *
 * @package SpringDevs\Subscription
 */

use SpringDevs\Subscription\Frontend\PlanSelectorView;

defined( 'ABSPATH' ) || exit;

if ( $view['term_count'] < 2 ) {
	return;
}

$subscrpt_heading = ! empty( $group['terms_heading'] )
	? (string) $group['terms_heading']
	/* translators: %s: plan group name. */
	: sprintf( __( '%s: billing period', 'subscription' ), $group['label'] );
?>
<?php if ( 'dropdown' === $intervals ) : ?>
	<?php $subscrpt_select_id = $view['gid'] . '-terms'; ?>
	<div class="subscrpt-buybox__terms subscrpt-buybox__terms--select" data-subscrpt-terms>
		<label class="subscrpt-buybox__terms-heading" for="<?php echo esc_attr( $subscrpt_select_id ); ?>"><?php echo esc_html( $subscrpt_heading ); ?></label>
		<select class="subscrpt-buybox__term-select" id="<?php echo esc_attr( $subscrpt_select_id ); ?>" name="subscrpt_plan_term[<?php echo esc_attr( $group['id'] ); ?>]" data-subscrpt-term-select<?php echo $view['is_first'] ? '' : ' disabled'; ?>>
			<?php foreach ( $group['terms'] as $subscrpt_ti => $plan_term ) : ?>
				<?php
				$subscrpt_text = ! empty( $plan_term['interval_label'] ) ? (string) $plan_term['interval_label'] : (string) $plan_term['label'];
				$subscrpt_save = ! empty( $plan_term['saving_label'] ) ? (string) $plan_term['saving_label'] : ( isset( $plan_term['badge'] ) ? (string) $plan_term['badge'] : '' );
				?>
				<option value="<?php echo esc_attr( $plan_term['id'] ); ?>" data-subscrpt-term-option data-price="<?php echo esc_attr( wp_strip_all_tags( $plan_term['price'] ) ); ?>" data-note="<?php echo esc_attr( $plan_term['note'] ); ?>" data-regular="<?php echo esc_attr( wp_strip_all_tags( PlanSelectorView::term_regular( $plan_term ) ) ); ?>" data-badge="<?php echo esc_attr( isset( $plan_term['badge'] ) ? $plan_term['badge'] : '' ); ?>"<?php echo 0 === $subscrpt_ti ? ' selected' : ''; ?>><?php echo esc_html( '' !== $subscrpt_save ? $subscrpt_text . ' · ' . $subscrpt_save : $subscrpt_text ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
<?php else : ?>
	<fieldset class="subscrpt-buybox__terms" data-subscrpt-terms>
		<legend class="subscrpt-buybox__terms-heading">
			<?php echo esc_html( $subscrpt_heading ); ?>
		</legend>
		<?php foreach ( $group['terms'] as $subscrpt_ti => $plan_term ) : ?>
			<?php
			$term_id    = $view['gid'] . '-term-' . sanitize_html_class( (string) $plan_term['id'] );
			$term_badge = isset( $plan_term['badge'] ) ? (string) $plan_term['badge'] : '';
			$chip_text  = ! empty( $plan_term['interval_label'] ) ? (string) $plan_term['interval_label'] : (string) $plan_term['label'];
			$chip_save  = isset( $plan_term['saving_label'] ) ? (string) $plan_term['saving_label'] : '';
			?>
			<input type="radio" class="subscrpt-buybox__term-radio subscrpt-visually-hidden" id="<?php echo esc_attr( $term_id ); ?>" name="subscrpt_plan_term[<?php echo esc_attr( $group['id'] ); ?>]" value="<?php echo esc_attr( $plan_term['id'] ); ?>" data-subscrpt-term data-price="<?php echo esc_attr( wp_strip_all_tags( $plan_term['price'] ) ); ?>" data-note="<?php echo esc_attr( $plan_term['note'] ); ?>" data-regular="<?php echo esc_attr( wp_strip_all_tags( PlanSelectorView::term_regular( $plan_term ) ) ); ?>" data-badge="<?php echo esc_attr( $term_badge ); ?>" <?php checked( 0 === $subscrpt_ti ); ?><?php echo $view['is_first'] ? '' : ' disabled'; ?> />
			<label class="subscrpt-buybox__term<?php echo 0 === $subscrpt_ti ? ' is-active' : ''; ?>" for="<?php echo esc_attr( $term_id ); ?>" data-subscrpt-term-btn data-term-id="<?php echo esc_attr( $plan_term['id'] ); ?>"><?php echo esc_html( $chip_text ); ?><?php if ( '' !== $chip_save ) : ?><span class="subscrpt-buybox__term-saving"> · <?php echo esc_html( $chip_save ); ?></span><?php elseif ( '' !== $term_badge ) : ?><span class="subscrpt-visually-hidden"> (<?php echo esc_html( $term_badge ); ?>)</span><?php endif; ?></label>
		<?php endforeach; ?>
	</fieldset>
<?php endif; ?>
