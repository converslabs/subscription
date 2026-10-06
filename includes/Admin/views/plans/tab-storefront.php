<?php
/**
 * Plan detail - "On the product page" tab.
 *
 * The words a shopper sees around this plan group's options: a tag on the card,
 * a short list of benefits and a learn-more link. Saved through the group's
 * REST route; plans.js reads the `data-subscrpt-sf` fields.
 *
 * @var array $plan Plan (PlanPresenter shape).
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$subscrpt_sf       = $plan['storefront'];
$subscrpt_benefits = array_pad( array_values( $subscrpt_sf['benefits'] ), 5, '' );
$subscrpt_label    = 'display:block;font-weight:600;margin:0 0 6px;';
$subscrpt_hint     = 'margin:5px 0 0;color:var(--wpsubs-text-muted);font-size:13px;line-height:1.5;';
?>
<div class="wpsubs-table-card" style="padding:20px 24px;margin-top:8px;max-width:720px;" data-subscrpt-storefront>
	<p style="<?php echo esc_attr( $subscrpt_hint ); ?>margin:0 0 20px;">
		<?php esc_html_e( 'What shoppers read next to this plan on the product page. Every field is optional.', 'subscription' ); ?>
		<?php esc_html_e( 'Example: Cancel anytime · Free shipping · Skip a month.', 'subscription' ); ?>
	</p>

	<label for="subscrpt-sf-tag" style="<?php echo esc_attr( $subscrpt_label ); ?>"><?php esc_html_e( 'Card tag', 'subscription' ); ?></label>
	<input type="text" id="subscrpt-sf-tag" class="wpsubs-input" maxlength="30" data-subscrpt-sf="tag" value="<?php echo esc_attr( $subscrpt_sf['tag'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Cancel anytime', 'subscription' ); ?>" autocomplete="off" />
	<p style="<?php echo esc_attr( $subscrpt_hint ); ?>"><?php esc_html_e( 'A short ribbon on the card, apart from the discount badge. 30 characters at most.', 'subscription' ); ?></p>

	<label for="subscrpt-sf-heading" style="<?php echo esc_attr( $subscrpt_label ); ?>margin-top:20px;"><?php esc_html_e( 'Benefits heading', 'subscription' ); ?></label>
	<input type="text" id="subscrpt-sf-heading" class="wpsubs-input" data-subscrpt-sf="benefits_heading" value="<?php echo esc_attr( $subscrpt_sf['benefits_heading'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. How it works', 'subscription' ); ?>" autocomplete="off" />

	<p style="<?php echo esc_attr( $subscrpt_label ); ?>margin-top:20px;"><?php esc_html_e( 'Benefits', 'subscription' ); ?></p>
	<div style="display:flex;flex-direction:column;gap:8px;">
		<?php foreach ( $subscrpt_benefits as $subscrpt_i => $subscrpt_line ) : ?>
			<input type="text" class="wpsubs-input" data-subscrpt-sf-benefit value="<?php echo esc_attr( $subscrpt_line ); ?>" aria-label="<?php /* translators: %d: line number. */ echo esc_attr( sprintf( __( 'Benefit %d', 'subscription' ), $subscrpt_i + 1 ) ); ?>" autocomplete="off" />
		<?php endforeach; ?>
	</div>
	<p style="<?php echo esc_attr( $subscrpt_hint ); ?>"><?php esc_html_e( 'Up to five short lines. Empty lines are dropped.', 'subscription' ); ?></p>

	<label for="subscrpt-sf-learn-label" style="<?php echo esc_attr( $subscrpt_label ); ?>margin-top:20px;"><?php esc_html_e( 'Learn more label', 'subscription' ); ?></label>
	<input type="text" id="subscrpt-sf-learn-label" class="wpsubs-input" data-subscrpt-sf="learn_label" value="<?php echo esc_attr( $subscrpt_sf['learn_more']['label'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Subscription details', 'subscription' ); ?>" autocomplete="off" />

	<label for="subscrpt-sf-learn-url" style="<?php echo esc_attr( $subscrpt_label ); ?>margin-top:12px;"><?php esc_html_e( 'Learn more link', 'subscription' ); ?></label>
	<input type="url" id="subscrpt-sf-learn-url" class="wpsubs-input" data-subscrpt-sf="learn_url" value="<?php echo esc_attr( $subscrpt_sf['learn_more']['url'] ); ?>" placeholder="https://" autocomplete="off" />

	<label for="subscrpt-sf-learn-panel" style="<?php echo esc_attr( $subscrpt_label ); ?>margin-top:12px;"><?php esc_html_e( 'Or panel text', 'subscription' ); ?></label>
	<textarea id="subscrpt-sf-learn-panel" class="wpsubs-input" rows="3" data-subscrpt-sf="learn_panel"><?php echo esc_textarea( $subscrpt_sf['learn_more']['panel'] ); ?></textarea>
	<p style="<?php echo esc_attr( $subscrpt_hint ); ?>"><?php esc_html_e( 'Give a link, or text to show in a panel on the page.', 'subscription' ); ?></p>

	<div style="margin-top:20px;">
		<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-sf-save><?php esc_html_e( 'Save', 'subscription' ); ?></button>
	</div>
</div>
