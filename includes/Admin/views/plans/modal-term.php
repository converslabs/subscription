<?php
/**
 * Add / Edit Selling Plan modal (Recurring). Field rows mirror the onboarding
 * wizard's "Subscription details" section. Signup fee is a Pro-only field.
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

// Match the onboarding "Subscription details" form-row styling.
$label_style = 'display:block;font-size:13px;font-weight:500;color:var(--wpsubs-text);margin:0 0 6px;';
$hint_style  = 'font-size:12px;color:var(--wpsubs-text-muted);margin:5px 0 0;line-height:1.4;';
$row_style   = 'margin-bottom:16px;';
$pair_style  = 'display:flex;gap:8px;align-items:center;';

$pro_active = function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated();
$currency   = function_exists( 'get_woocommerce_currency_symbol' )
	? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
	: '$';
?>
<div class="wpsubs-modal" id="subscrpt-term-modal" hidden data-subscrpt-term-modal data-group-id="<?php echo esc_attr( $plan['id'] ); ?>" data-group-type="recurring">
	<div class="wpsubs-modal__backdrop" data-wpsubs-modal-close></div>
	<div class="wpsubs-modal__dialog" style="width:min(460px, calc(100vw - 40px));">
		<div class="wpsubs-modal__head">
			<h2 class="wpsubs-modal__title" data-subscrpt-term-title><?php esc_html_e( 'Add Selling Plan', 'subscription' ); ?></h2>
			<button type="button" class="wpsubs-modal__close" data-wpsubs-modal-close aria-label="<?php esc_attr_e( 'Close', 'subscription' ); ?>">&times;</button>
		</div>
		<div class="wpsubs-modal__body" style="overflow:visible;">

			<!-- Row 1: Plan name -->
			<div style="<?php echo esc_attr( $row_style ); ?>">
				<label style="<?php echo esc_attr( $label_style ); ?>" for="subscrpt-term-name"><?php esc_html_e( 'Plan Name', 'subscription' ); ?></label>
				<input type="text" id="subscrpt-term-name" class="wpsubs-input" value="" placeholder="<?php esc_attr_e( 'e.g. Monthly', 'subscription' ); ?>" data-subscrpt-field="title" />
				<p style="<?php echo esc_attr( $hint_style ); ?>"><?php esc_html_e( 'Shown to customers when they pick this plan.', 'subscription' ); ?></p>
			</div>

			<!-- Row 2: Billing every (full width) -->
			<div style="<?php echo esc_attr( $row_style ); ?>">
				<label style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Billing every', 'subscription' ); ?></label>
				<div style="<?php echo esc_attr( $pair_style ); ?>">
					<input type="number" class="wpsubs-input" value="1" min="1" style="flex:1 1 auto;min-width:0;" data-subscrpt-field="billing_frequency" aria-label="<?php esc_attr_e( 'Frequency', 'subscription' ); ?>" />
					<?php
					wpsubs_render_adv_select(
						array(
							'name'    => 'subscrpt_billing_interval',
							'value'   => 'month',
							'options' => $interval_options,
							'attrs'   => array(
								'data-subscrpt-field' => 'billing_interval',
								'style'               => 'flex:0 0 auto;',
							),
						)
					);
					?>
				</div>
				<p style="<?php echo esc_attr( $hint_style ); ?>"><?php esc_html_e( 'How often the customer is charged, for example every 1 month.', 'subscription' ); ?></p>
			</div>

			<!-- Row 3: Free trial | Signup fee -->
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:0;">
				<div>
					<label style="<?php echo esc_attr( $label_style ); ?>"><?php esc_html_e( 'Free trial', 'subscription' ); ?> <span style="color:var(--wpsubs-text-subtle);font-weight:400;">(<?php esc_html_e( 'optional', 'subscription' ); ?>)</span></label>
					<div style="<?php echo esc_attr( $pair_style ); ?>">
						<input type="number" class="wpsubs-input" value="" min="0" placeholder="0" style="flex:1 1 auto;min-width:0;" data-subscrpt-field="free_trial" aria-label="<?php esc_attr_e( 'Trial length', 'subscription' ); ?>" />
						<?php
						wpsubs_render_adv_select(
							array(
								'name'    => 'subscrpt_free_trial_interval',
								'value'   => 'day',
								'options' => $interval_options,
								'attrs'   => array(
									'data-subscrpt-field' => 'free_trial_interval',
									'style'               => 'flex:0 0 auto;',
								),
							)
						);
						?>
					</div>
					<p style="<?php echo esc_attr( $hint_style ); ?>"><?php esc_html_e( 'Charge nothing until the trial ends.', 'subscription' ); ?></p>
				</div>

				<div>
					<label style="<?php echo esc_attr( $label_style ); ?>" for="subscrpt-term-signup-fee">
						<?php esc_html_e( 'Signup fee', 'subscription' ); ?> <span style="color:var(--wpsubs-text-subtle);font-weight:400;">(<?php esc_html_e( 'optional', 'subscription' ); ?>)</span>
						<?php if ( ! $pro_active ) : ?>
							<span class="wpsubs-badge wpsubs-badge--pro" style="margin-left:6px;" title="<?php esc_attr_e( 'WPSubscription Pro required', 'subscription' ); ?>"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
						<?php endif; ?>
					</label>
					<div style="position:relative;">
						<span style="position:absolute;left:11px;top:50%;transform:translateY(-50%);font-size:13px;color:var(--wpsubs-text-muted);pointer-events:none;z-index:1;"><?php echo esc_html( $currency ); ?></span>
						<input type="number" id="subscrpt-term-signup-fee" class="wpsubs-input" value="" min="0" step="0.01" placeholder="0.00" style="padding-left:26px!important;" data-subscrpt-field="signup_fee_amount" <?php disabled( ! $pro_active ); ?> />
					</div>
					<p style="<?php echo esc_attr( $hint_style ); ?>"><?php esc_html_e( 'One-time fee on the first payment.', 'subscription' ); ?></p>
				</div>
			</div>

		</div>
		<div class="wpsubs-modal__footer">
			<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-close><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-term-submit><?php esc_html_e( 'Save Selling Plan', 'subscription' ); ?></button>
		</div>
	</div>
</div>
