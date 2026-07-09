<?php
/**
 * Add / Edit Selling Plan modal (Recurring). Optional fields are grouped in a
 * shared WPSubsAccordion. Free exposes no signup fee (no signup-fee engine).
 *
 * @var array $plan Plan (provides id + type).
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$intervals = array(
	'day'   => __( 'Day(s)', 'subscription' ),
	'week'  => __( 'Week(s)', 'subscription' ),
	'month' => __( 'Month(s)', 'subscription' ),
	'year'  => __( 'Year(s)', 'subscription' ),
);
?>
<div class="wpsubs-modal" id="subscrpt-term-modal" hidden data-subscrpt-term-modal data-group-id="<?php echo esc_attr( $plan['id'] ); ?>" data-group-type="recurring">
	<div class="wpsubs-modal__backdrop" data-wpsubs-modal-close></div>
	<div class="wpsubs-modal__dialog">
		<div class="wpsubs-modal__head">
			<h2 class="wpsubs-modal__title" data-subscrpt-term-title><?php esc_html_e( 'Add Selling Plan', 'subscription' ); ?></h2>
			<button type="button" class="wpsubs-modal__close" data-wpsubs-modal-close aria-label="<?php esc_attr_e( 'Close', 'subscription' ); ?>">&times;</button>
		</div>
		<div class="wpsubs-modal__body">

			<p class="wpsubs-settings-field"><strong><?php esc_html_e( 'Billing Every', 'subscription' ); ?></strong></p>
			<div style="display:flex;gap:10px;">
				<input type="number" class="wpsubs-input" value="1" min="1" style="max-width:120px;" data-subscrpt-field="billing_frequency" aria-label="<?php esc_attr_e( 'Frequency', 'subscription' ); ?>" />
				<select class="wpsubs-select" data-subscrpt-field="billing_interval">
					<?php foreach ( $intervals as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( 'month', $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<p class="wpsubs-settings-field" style="margin-top:16px;"><strong><?php esc_html_e( 'Plan Name', 'subscription' ); ?></strong></p>
			<input type="text" class="wpsubs-input" value="" placeholder="<?php esc_attr_e( 'e.g. Monthly', 'subscription' ); ?>" data-subscrpt-field="title" />

			<div class="wpsubs-accordion" data-multi="1" style="margin-top:16px;">
				<div class="wpsubs-accordion__item">
					<button type="button" class="wpsubs-accordion__header" aria-controls="subscrpt-term-trial" aria-expanded="false"><?php esc_html_e( 'Free Trial', 'subscription' ); ?></button>
					<div class="wpsubs-accordion__panel" id="subscrpt-term-trial" hidden>
						<div style="display:flex;gap:10px;">
							<input type="number" class="wpsubs-input" min="0" placeholder="<?php esc_attr_e( 'Length', 'subscription' ); ?>" style="max-width:120px;" data-subscrpt-field="free_trial" />
							<select class="wpsubs-select" data-subscrpt-field="free_trial_interval">
								<?php foreach ( $intervals as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<p style="color:var(--wpsubs-text-muted,#6b7280);font-size:13px;margin-bottom:0;"><?php esc_html_e( 'Charge nothing until the trial ends.', 'subscription' ); ?></p>
					</div>
				</div>

				<div class="wpsubs-accordion__item">
					<button type="button" class="wpsubs-accordion__header" aria-controls="subscrpt-term-expiry" aria-expanded="false"><?php esc_html_e( 'Expiry', 'subscription' ); ?></button>
					<div class="wpsubs-accordion__panel" id="subscrpt-term-expiry" hidden>
						<label class="wpsubs-settings-field"><?php esc_html_e( 'End after N billing cycles (0 = never ends)', 'subscription' ); ?></label>
						<input type="number" class="wpsubs-input" value="0" min="0" style="max-width:160px;" data-subscrpt-field="billing_length" />
					</div>
				</div>

				<div class="wpsubs-accordion__item">
					<button type="button" class="wpsubs-accordion__header" aria-controls="subscrpt-term-info" aria-expanded="false"><?php esc_html_e( 'Plan Information', 'subscription' ); ?></button>
					<div class="wpsubs-accordion__panel" id="subscrpt-term-info" hidden>
						<label class="wpsubs-settings-field"><?php esc_html_e( 'Pricing Breakdown', 'subscription' ); ?></label>
						<textarea class="wpsubs-input" rows="2" data-subscrpt-field="pricing_breakdown"></textarea>
					</div>
				</div>
			</div>

		</div>
		<div class="wpsubs-modal__footer">
			<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-close><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-term-submit><?php esc_html_e( 'Save', 'subscription' ); ?></button>
		</div>
	</div>
</div>
