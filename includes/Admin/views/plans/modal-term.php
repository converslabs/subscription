<?php
/**
 * Add / Edit Selling Plan modal (Recurring). Fields are laid out flat. Free
 * exposes no signup fee (no signup-fee engine).
 *
 * @var array $plan Plan (provides id + type).
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$interval_options = array(
	array(
		'value' => 'day',
		'label' => __( 'Day(s)', 'subscription' ),
	),
	array(
		'value' => 'week',
		'label' => __( 'Week(s)', 'subscription' ),
	),
	array(
		'value' => 'month',
		'label' => __( 'Month(s)', 'subscription' ),
	),
	array(
		'value' => 'year',
		'label' => __( 'Year(s)', 'subscription' ),
	),
);

$label_style = 'display:block;font-weight:600;margin:0 0 6px;color:var(--wpsubs-text);';
$hint_style  = 'margin:6px 0 0;font-size:12px;color:var(--wpsubs-text-muted);line-height:1.5;';
$group_style = 'margin-top:18px;';
$grid_style  = 'display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:center;';
?>
<div class="wpsubs-modal" id="subscrpt-term-modal" hidden data-subscrpt-term-modal data-group-id="<?php echo esc_attr( $plan['id'] ); ?>" data-group-type="recurring">
	<div class="wpsubs-modal__backdrop" data-wpsubs-modal-close></div>
	<div class="wpsubs-modal__dialog" style="width:min(460px, calc(100vw - 40px));">
		<div class="wpsubs-modal__head">
			<h2 class="wpsubs-modal__title" data-subscrpt-term-title><?php esc_html_e( 'Add Selling Plan', 'subscription' ); ?></h2>
			<button type="button" class="wpsubs-modal__close" data-wpsubs-modal-close aria-label="<?php esc_attr_e( 'Close', 'subscription' ); ?>">&times;</button>
		</div>
		<div class="wpsubs-modal__body">

			<div>
				<label style="<?php echo esc_attr( $label_style ); ?>" for="subscrpt-term-name"><?php esc_html_e( 'Plan Name', 'subscription' ); ?></label>
				<input type="text" id="subscrpt-term-name" class="wpsubs-input" value="" placeholder="<?php esc_attr_e( 'e.g. Monthly', 'subscription' ); ?>" data-subscrpt-field="title" />
				<p style="<?php echo esc_attr( $hint_style ); ?>"><?php esc_html_e( 'Shown to customers when they pick this plan.', 'subscription' ); ?></p>
			</div>

			<div style="<?php echo esc_attr( $group_style ); ?>">
				<label style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Billing Every', 'subscription' ); ?></label>
				<div style="<?php echo esc_attr( $grid_style ); ?>">
					<input type="number" class="wpsubs-input" value="1" min="1" data-subscrpt-field="billing_frequency" aria-label="<?php esc_attr_e( 'Frequency', 'subscription' ); ?>" />
					<?php
					wpsubs_render_adv_select(
						array(
							'name'    => 'subscrpt_billing_interval',
							'value'   => 'month',
							'options' => $interval_options,
							'attrs'   => array( 'data-subscrpt-field' => 'billing_interval' ),
						)
					);
					?>
				</div>
				<p style="<?php echo esc_attr( $hint_style ); ?>"><?php esc_html_e( 'How often the customer is charged, for example every 1 month.', 'subscription' ); ?></p>
			</div>

			<div style="<?php echo esc_attr( $group_style ); ?>">
				<label style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Free Trial (optional)', 'subscription' ); ?></label>
				<div style="<?php echo esc_attr( $grid_style ); ?>">
					<input type="number" class="wpsubs-input" value="" min="0" placeholder="<?php esc_attr_e( 'Length', 'subscription' ); ?>" data-subscrpt-field="free_trial" aria-label="<?php esc_attr_e( 'Trial length', 'subscription' ); ?>" />
					<?php
					wpsubs_render_adv_select(
						array(
							'name'    => 'subscrpt_free_trial_interval',
							'value'   => 'day',
							'options' => $interval_options,
							'attrs'   => array( 'data-subscrpt-field' => 'free_trial_interval' ),
						)
					);
					?>
				</div>
				<p style="<?php echo esc_attr( $hint_style ); ?>"><?php esc_html_e( 'Charge nothing until the trial ends. Leave blank for no trial.', 'subscription' ); ?></p>
			</div>

			<div style="<?php echo esc_attr( $group_style ); ?>">
				<label style="<?php echo esc_attr( $label_style ); ?>" for="subscrpt-term-expiry"><?php esc_html_e( 'Expiry', 'subscription' ); ?></label>
				<input type="number" id="subscrpt-term-expiry" class="wpsubs-input" value="0" min="0" data-subscrpt-field="billing_length" />
				<p style="<?php echo esc_attr( $hint_style ); ?>"><?php esc_html_e( 'End after this many billing cycles. Use 0 for never ends.', 'subscription' ); ?></p>
			</div>

		</div>
		<div class="wpsubs-modal__footer">
			<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-close><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-term-submit><?php esc_html_e( 'Save Selling Plan', 'subscription' ); ?></button>
		</div>
	</div>
</div>
