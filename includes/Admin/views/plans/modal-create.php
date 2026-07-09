<?php
/**
 * Create Plan modal. Free is Recurring-only; the other two types are shown
 * disabled with an upgrade hint.
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pro_types = array(
	'subscribe_save' => array(
		'label' => __( 'Subscribe & Save', 'subscription' ),
		'tag'   => __( 'For Physical Products', 'subscription' ),
		'desc'  => __( 'Charge and deliver physical products on a schedule.', 'subscription' ),
	),
	'installments'   => array(
		'label' => __( 'Installments', 'subscription' ),
		'tag'   => __( 'Any Product Type', 'subscription' ),
		'desc'  => __( 'Split a price into a fixed number of payments.', 'subscription' ),
	),
);
?>
<div class="wpsubs-modal" id="subscrpt-create-plan" hidden>
	<div class="wpsubs-modal__backdrop" data-wpsubs-modal-close></div>
	<div class="wpsubs-modal__dialog">
		<div class="wpsubs-modal__head">
			<h2 class="wpsubs-modal__title"><?php esc_html_e( 'Create Plan', 'subscription' ); ?></h2>
			<button type="button" class="wpsubs-modal__close" data-wpsubs-modal-close aria-label="<?php esc_attr_e( 'Close', 'subscription' ); ?>">&times;</button>
		</div>
		<div class="wpsubs-modal__body">
			<p class="wpsubs-settings-field"><label for="subscrpt-create-name"><strong><?php esc_html_e( 'Name', 'subscription' ); ?></strong></label></p>
			<input type="text" id="subscrpt-create-name" class="wpsubs-input" placeholder="<?php esc_attr_e( 'e.g. Premium Membership', 'subscription' ); ?>" />

			<p class="wpsubs-settings-field" style="margin-top:16px;"><strong><?php esc_html_e( 'Plan Type', 'subscription' ); ?></strong></p>

			<div style="display:flex;flex-direction:column;gap:10px;">
				<label style="display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid var(--wpsubs-border);border-radius:var(--wpsubs-radius);cursor:pointer;">
					<input type="radio" name="subscrpt_plan_type" value="recurring" checked class="wpsubs-checkbox" style="margin-top:2px;" />
					<span>
						<strong><?php esc_html_e( 'Recurring', 'subscription' ); ?></strong>
						<span class="wpsubs-badge" style="margin-left:6px;"><?php esc_html_e( 'For Virtual Products', 'subscription' ); ?></span>
						<br /><span style="color:var(--wpsubs-text-muted,#6b7280);font-size:13px;"><?php esc_html_e( 'Automatically charge recurring payments for virtual products.', 'subscription' ); ?></span>
					</span>
				</label>

				<?php foreach ( $pro_types as $type_def ) : ?>
					<label style="display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid var(--wpsubs-border);border-radius:var(--wpsubs-radius);opacity:0.6;cursor:not-allowed;">
						<input type="radio" name="subscrpt_plan_type" value="" disabled class="wpsubs-checkbox" style="margin-top:2px;" />
						<span>
							<strong><?php echo esc_html( $type_def['label'] ); ?></strong>
							<span class="wpsubs-badge wpsubs-badge--draft" style="margin-left:6px;"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
							<br /><span style="color:var(--wpsubs-text-muted,#6b7280);font-size:13px;"><?php echo esc_html( $type_def['desc'] ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>

			<p style="color:var(--wpsubs-text-muted,#6b7280);font-size:13px;margin-top:10px;">
				<?php esc_html_e( 'Subscribe & Save and Installment plans are available in Subscription Pro.', 'subscription' ); ?>
			</p>
		</div>
		<div class="wpsubs-modal__footer">
			<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-close><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-create-plan><?php esc_html_e( 'Create', 'subscription' ); ?></button>
		</div>
	</div>
</div>
