<?php
/**
 * Onboarding Wizard Template
 * SPA-style: all pages rendered at once, JS controls visibility
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get wizard data from session
$session_data      = isset( $_SESSION['subscrpt_onboarding_wizard'] ) ? $_SESSION['subscrpt_onboarding_wizard'] : array();
$wizard_page       = isset( $session_data['page'] ) ? (int) $session_data['page'] : 1;
$product_id        = isset( $session_data['product_id'] ) ? (int) $session_data['product_id'] : 0;
$subscriptions_url = admin_url( 'admin.php?page=wp-subscription' );

// Get existing products for dropdown
$args     = array(
	'post_type'      => 'product',
	'posts_per_page' => -1,
	'post_status'    => 'any',
	'orderby'        => 'title',
	'order'          => 'ASC',
);
$products = get_posts( $args );

// Load product if exists
$product = null;
if ( $product_id > 0 ) {
	$product = wc_get_product( $product_id );
}
?>
<style>
#subscrpt-onboarding-wizard {
	max-width: 680px;
}
/* Page sections — all rendered, JS toggles visibility */
.wizard-section {
	display: none;
}
.wizard-section.active {
	display: block;
}
/* Stepper */
.wpsubs-wizard-stepper {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 0;
	margin-bottom: 32px;
}
.wpsubs-wizard-stepper__step {
	display: flex;
	align-items: center;
	gap: 10px;
}
.wpsubs-wizard-stepper__num {
	width: 28px;
	height: 28px;
	border-radius: 50%;
	background: var(--wpsubs-surface-muted);
	border: 1.5px solid var(--wpsubs-border);
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 12px;
	font-weight: 600;
	color: var(--wpsubs-text-muted);
	flex-shrink: 0;
	transition: all 0.2s;
}
.wpsubs-wizard-stepper__step.active .wpsubs-wizard-stepper__num {
	background: var(--wpsubs-brand);
	border-color: var(--wpsubs-brand);
	color: #fff;
}
.wpsubs-wizard-stepper__step.done .wpsubs-wizard-stepper__num {
	background: #dcfce7;
	border-color: #16a34a;
	color: #15803d;
}
.wpsubs-wizard-stepper__label {
	font-size: 13px;
	font-weight: 500;
	color: var(--wpsubs-text-muted);
	white-space: nowrap;
	transition: color 0.2s;
}
.wpsubs-wizard-stepper__step.active .wpsubs-wizard-stepper__label {
	color: var(--wpsubs-brand);
}
.wpsubs-wizard-stepper__line {
	flex: 1;
	min-width: 32px;
	height: 1px;
	background: var(--wpsubs-border);
	margin: 0 8px;
}
/* Card */
.wizard-card {
	padding: 28px 32px;
	margin-bottom: 16px;
}
/* Page 2 */
.section-heading {
	font-size: 13px !important;
	font-weight: 600 !important;
	color: var(--wpsubs-text-muted) !important;
	text-transform: uppercase;
	letter-spacing: 0.05em;
	margin-bottom: 16px;
	padding-bottom: 12px;
	border-bottom: 1px solid var(--wpsubs-border);
}
.product-toggle {
	display: flex;
	gap: 8px;
	margin-bottom: 20px;
}
.product-toggle-btn {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 8px;
	padding: 16px 12px;
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	background: var(--wpsubs-surface);
	cursor: pointer;
	font-size: 13px;
	font-weight: 500;
	color: var(--wpsubs-text);
	text-align: center;
	transition: all 0.15s;
}
.product-toggle-btn:hover {
	border-color: var(--wpsubs-brand);
	background: var(--wpsubs-brand-light);
}
.product-toggle-btn.active {
	background: var(--wpsubs-brand);
	border-color: var(--wpsubs-brand);
	color: #fff;
}
.product-toggle-btn__icon {
	font-size: 20px;
	line-height: 1;
}
/* Form rows */
.form-row {
	margin-bottom: 16px;
}
.form-row label {
	display: block;
	font-size: 13px;
	font-weight: 500;
	color: var(--wpsubs-text);
	margin-bottom: 6px;
}
/* Toggle subfields */
.subfields {
	margin-left: 28px;
	margin-top: 8px;
	display: none;
}
.subfields.visible {
	display: block;
}
.wizard-nav {
	display: flex;
	gap: 8px;
	padding-top: 20px;
	border-top: 1px solid var(--wpsubs-border);
	margin-top: 4px;
}
/* Page 3 */
.product-summary {
	background: var(--wpsubs-surface-muted);
}
.summary-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 10px;
}
.summary-item {
	font-size: 13px;
}
.summary-item .label {
	color: var(--wpsubs-text-muted);
	display: block;
	margin-bottom: 2px;
}
.summary-item .value {
	font-weight: 500;
	color: var(--wpsubs-text);
}
.congrats-heading {
	font-size: 22px;
	font-weight: 700;
	text-align: center;
	margin-bottom: 6px;
}
.congrats-sub {
	text-align: center;
	color: var(--wpsubs-text-muted);
	margin-bottom: 28px;
	font-size: 13.5px;
}
.action-buttons {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	justify-content: center;
	margin-top: 8px;
}
.action-btn {
	padding: 9px 18px;
	font-size: 13px;
}
/* Page 1 specific — full-width centered */
.wizard-section[data-page="1"] .wizard-card {
	display: flex;
	flex-direction: column;
	align-items: center;
	text-align: center;
	padding: 56px 48px;
}
.wizard-section[data-page="1"] .wizard-card__icon {
	font-size: 52px;
	margin-bottom: 16px;
	line-height: 1;
}
.wizard-section[data-page="1"] .wizard-card__title {
	font-size: 20px;
	font-weight: 700;
	color: var(--wpsubs-text);
	margin: 0 0 8px;
}
.wizard-section[data-page="1"] .wizard-card__desc {
	font-size: 13.5px;
	color: var(--wpsubs-text-muted);
	margin: 0 0 28px;
	max-width: 420px;
	line-height: 1.6;
}
.wizard-section[data-page="1"] .wizard-card__actions {
	display: flex;
	flex-direction: column;
	gap: 8px;
	width: 100%;
	max-width: 300px;
}
</style>

<div class="wpsubs-tw-root wpsubs-layout" id="subscrpt-onboarding-wizard">
	<!-- Page 2+ only: stepper indicator -->
	<div class="wpsubs-wizard-stepper" id="subscrpt-stepper" style="display:none;">
		<div class="wpsubs-wizard-stepper__step active" data-step="1">
			<div class="wpsubs-wizard-stepper__num">1</div>
			<div class="wpsubs-wizard-stepper__label">Welcome</div>
		</div>
		<div class="wpsubs-wizard-stepper__line"></div>
		<div class="wpsubs-wizard-stepper__step" data-step="2">
			<div class="wpsubs-wizard-stepper__num">2</div>
			<div class="wpsubs-wizard-stepper__label">Product Setup</div>
		</div>
		<div class="wpsubs-wizard-stepper__line"></div>
		<div class="wpsubs-wizard-stepper__step" data-step="3">
			<div class="wpsubs-wizard-stepper__num">3</div>
			<div class="wpsubs-wizard-stepper__label">Review</div>
		</div>
	</div>

	<!-- Hidden state -->
	<input type="hidden" name="subscrpt_wizard_page" id="subscrpt-wizard-page" value="<?php echo esc_attr( $wizard_page ); ?>">
	<input type="hidden" name="subscrpt_product_id" id="subscrpt-product-id" value="<?php echo esc_attr( $product_id ); ?>">
	<input type="hidden" id="subscrpt-ajax-url" value="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
	<input type="hidden" id="subscrpt-subscriptions-url" value="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription' ) ); ?>">
	<?php wp_nonce_field( 'subscrpt_onboarding_wizard', 'subscrpt_wizard_nonce' ); ?>

	<!-- =========================================== -->
	<!-- SECTION 1: Welcome -->
	<!-- =========================================== -->
	<div class="wizard-section <?php echo 1 === $wizard_page ? 'active' : ''; ?>" data-page="1" id="subscrpt-section-1">
		<div class="wizard-card">
			<div class="wizard-card__icon">🚀</div>
			<h1 class="wizard-card__title">Welcome to WooCommerce Subscriptions</h1>
			<p class="wizard-card__desc">Get started in minutes. We'll help you set up your first subscription product so you can start earning recurring revenue from your store.</p>
			<div class="wizard-card__actions">
				<button type="button" id="subscrpt-btn-start" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn">
					Create your first subscription
				</button>
				<button type="button" id="subscrpt-btn-skip" class="wpsubs-btn wpsubs-btn--outline">
					Skip, I'll do this later
				</button>
			</div>
		</div>
	</div>

	<!-- =========================================== -->
	<!-- SECTION 2: Product & Subscription Setup -->
	<!-- =========================================== -->
	<div class="wizard-section <?php echo 2 === $wizard_page ? 'active' : ''; ?>" id="subscrpt-section-2">
		<div class="wizard-card">
			<div class="section-heading">Product Setup</div>

			<?php if ( ! empty( $products ) ) : ?>
				<div class="product-toggle">
					<button type="button" class="product-toggle-btn <?php echo 'existing' !== ( isset( $session_data['product_mode'] ) ? $session_data['product_mode'] : 'new' ) ? 'active' : ''; ?>" data-mode="new" id="subscrpt-btn-create-new">
						<div class="product-toggle-btn__icon">📦</div>
						Create a new product
					</button>
					<button type="button" class="product-toggle-btn <?php echo 'existing' === ( isset( $session_data['product_mode'] ) ? $session_data['product_mode'] : '' ) ? 'active' : ''; ?>" data-mode="existing" id="subscrpt-btn-use-existing">
						<div class="product-toggle-btn__icon">🔗</div>
						Use an existing product
					</button>
				</div>
			<?php else : ?>
				<p style="color:var(--wpsubs-text-muted);margin-bottom:20px;">No existing products found. Create a new product below.</p>
			<?php endif; ?>

			<!-- New product fields -->
			<div id="subscrpt-new-product-fields">
				<div class="form-row">
					<label for="subscrpt_product_name">Product Name</label>
					<input type="text" id="subscrpt_product_name" name="subscrpt_product_name" class="wpsubs-input" style="max-width:380px;" placeholder="e.g. Monthly Subscription Box" value="<?php echo $product ? esc_attr( $product->get_name() ) : ( isset( $session_data['product_name'] ) ? esc_attr( $session_data['product_name'] ) : '' ); ?>">
				</div>
				<div class="form-row">
					<label for="subscrpt_product_price">Price</label>
					<input type="text" id="subscrpt_product_price" name="subscrpt_product_price" class="wpsubs-input" style="max-width:180px;" placeholder="0.00" value="<?php echo $product ? esc_attr( $product->get_price() ) : ( isset( $session_data['product_price'] ) ? esc_attr( $session_data['product_price'] ) : '' ); ?>">
				</div>
			</div>

			<!-- Existing product dropdown -->
			<?php if ( ! empty( $products ) ) : ?>
				<div id="subscrpt-existing-product-fields" style="<?php echo 'existing' !== ( isset( $session_data['product_mode'] ) ? $session_data['product_mode'] : '' ) ? 'display:none;' : ''; ?>">
					<div class="form-row">
						<label for="subscrpt_existing_product">Select Product</label>
						<select id="subscrpt_existing_product" name="subscrpt_existing_product" class="wpsubs-select" style="max-width:380px;">
							<option value="">-- Select a product --</option>
							<?php foreach ( $products as $p ) : ?>
								<option value="<?php echo esc_attr( $p->ID ); ?>" <?php echo $product_id === $p->ID ? 'selected' : ''; ?>>
									<?php echo esc_html( $p->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="wizard-card">
			<div class="section-heading">Subscription Information</div>

			<div class="form-row">
				<label for="subscrpt_timing_option">Subscription Timing</label>
				<select id="subscrpt_timing_option" name="subscrpt_timing_option" class="wpsubs-select" style="max-width:280px;">
					<option value="">-- Select timing --</option>
					<option value="1_day" <?php echo isset( $session_data['timing_option'] ) && '1_day' === $session_data['timing_option'] ? 'selected' : ''; ?>>First payment after 1 day</option>
					<option value="3_days" <?php echo isset( $session_data['timing_option'] ) && '3_days' === $session_data['timing_option'] ? 'selected' : ''; ?>>First payment after 3 days</option>
					<option value="7_days" <?php echo isset( $session_data['timing_option'] ) && '7_days' === $session_data['timing_option'] ? 'selected' : ''; ?>>First payment after 7 days</option>
					<option value="14_days" <?php echo isset( $session_data['timing_option'] ) && '14_days' === $session_data['timing_option'] ? 'selected' : ''; ?>>First payment after 14 days</option>
					<option value="30_days" <?php echo isset( $session_data['timing_option'] ) && '30_days' === $session_data['timing_option'] ? 'selected' : ''; ?>>First payment after 30 days</option>
				</select>
			</div>

			<div class="form-row">
				<label for="subscrpt_billing_period">Billing Period</label>
				<select id="subscrpt_billing_period" name="subscrpt_billing_period" class="wpsubs-select" style="max-width:200px;">
					<option value="">-- Select billing period --</option>
					<option value="week" <?php echo isset( $session_data['billing_period'] ) && 'week' === $session_data['billing_period'] ? 'selected' : ''; ?>>Weekly</option>
					<option value="month" <?php echo isset( $session_data['billing_period'] ) && 'month' === $session_data['billing_period'] ? 'selected' : ''; ?>>Monthly</option>
					<option value="year" <?php echo isset( $session_data['billing_period'] ) && 'year' === $session_data['billing_period'] ? 'selected' : ''; ?>>Yearly</option>
				</select>
			</div>

			<div class="form-row">
				<div class="wpsubs-settings-toggle-label">
					<input type="checkbox" class="wpsubs-toggle" id="subscrpt_trial_enabled" name="subscrpt_trial_enabled" value="1" <?php echo isset( $session_data['trial_enabled'] ) && $session_data['trial_enabled'] ? 'checked' : ''; ?>>
					<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
					<span class="wpsubs-settings-toggle-label__text">Enable trial period</span>
				</div>
				<div id="subscrpt-trial-fields" class="subfields <?php echo isset( $session_data['trial_enabled'] ) && $session_data['trial_enabled'] ? 'visible' : ''; ?>">
					<div class="form-row" style="margin-top:12px;">
						<label for="subscrpt_trial_timing_per">Trial Period Length</label>
						<input type="number" id="subscrpt_trial_timing_per" name="subscrpt_trial_timing_per" class="wpsubs-input" style="max-width:120px;" min="1" value="<?php echo isset( $session_data['trial_timing_per'] ) ? esc_attr( $session_data['trial_timing_per'] ) : '7'; ?>">
					</div>
					<div class="form-row">
						<label for="subscrpt_trial_timing_option">Trial Period Unit</label>
						<select id="subscrpt_trial_timing_option" name="subscrpt_trial_timing_option" class="wpsubs-select" style="max-width:160px;">
							<option value="days" <?php echo isset( $session_data['trial_timing_option'] ) && 'days' === $session_data['trial_timing_option'] ? 'selected' : ''; ?>>Days</option>
							<option value="weeks" <?php echo isset( $session_data['trial_timing_option'] ) && 'weeks' === $session_data['trial_timing_option'] ? 'selected' : ''; ?>>Weeks</option>
							<option value="months" <?php echo isset( $session_data['trial_timing_option'] ) && 'months' === $session_data['trial_timing_option'] ? 'selected' : ''; ?>>Months</option>
						</select>
					</div>
				</div>
			</div>

			<div class="form-row">
				<div class="wpsubs-settings-toggle-label">
					<input type="checkbox" class="wpsubs-toggle" id="subscrpt_length_enabled" name="subscrpt_length_enabled" value="1" <?php echo isset( $session_data['length_enabled'] ) && $session_data['length_enabled'] ? 'checked' : ''; ?>>
					<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
					<span class="wpsubs-settings-toggle-label__text">Limit subscription length</span>
				</div>
				<div id="subscrpt-length-fields" class="subfields <?php echo isset( $session_data['length_enabled'] ) && $session_data['length_enabled'] ? 'visible' : ''; ?>">
					<div class="form-row" style="margin-top:12px;">
						<label for="subscrpt_length_per">Subscription Length</label>
						<input type="number" id="subscrpt_length_per" name="subscrpt_length_per" class="wpsubs-input" style="max-width:120px;" min="1" value="<?php echo isset( $session_data['length_per'] ) ? esc_attr( $session_data['length_per'] ) : '12'; ?>">
					</div>
					<div class="form-row">
						<label for="subscrpt_length_option">Length Unit</label>
						<select id="subscrpt_length_option" name="subscrpt_length_option" class="wpsubs-select" style="max-width:160px;">
							<option value="weeks" <?php echo isset( $session_data['length_option'] ) && 'weeks' === $session_data['length_option'] ? 'selected' : ''; ?>>Weeks</option>
							<option value="months" <?php echo isset( $session_data['length_option'] ) && 'months' === $session_data['length_option'] ? 'selected' : ''; ?>>Months</option>
							<option value="years" <?php echo isset( $session_data['length_option'] ) && 'years' === $session_data['length_option'] ? 'selected' : ''; ?>>Years</option>
						</select>
					</div>
				</div>
			</div>
		</div>

		<div class="wizard-nav">
			<button type="button" id="subscrpt-btn-back" class="wpsubs-btn wpsubs-btn--outline">← Back</button>
			<button type="button" id="subscrpt-btn-save" class="wpsubs-btn wpsubs-btn--primary">Save & Continue →</button>
		</div>
	</div>

	<!-- =========================================== -->
	<!-- SECTION 3: Completion -->
	<!-- =========================================== -->
	<div class="wizard-section <?php echo 3 === $wizard_page ? 'active' : ''; ?>" id="subscrpt-section-3">
		<div class="wizard-card">
			<h1 class="congrats-heading">🎉 Congratulations!</h1>
			<p class="congrats-sub">Your subscription product is ready.</p>

			<?php if ( $product ) : ?>
				<div class="product-summary">
					<h3><?php echo esc_html( $product->get_name() ); ?></h3>
					<div class="summary-grid">
						<div class="summary-item">
							<span class="label">Price</span>
							<span class="value"><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></span>
						</div>
						<div class="summary-item">
							<span class="label">Billing</span>
							<span class="value"><?php echo esc_html( isset( $session_data['billing_period'] ) ? ucfirst( $session_data['billing_period'] ) : '—' ); ?></span>
						</div>
						<div class="summary-item">
							<span class="label">First Payment</span>
							<span class="value"><?php echo esc_html( isset( $session_data['timing_option'] ) ? str_replace( '_', ' ', ucfirst( $session_data['timing_option'] ) ) : '—' ); ?></span>
						</div>
						<div class="summary-item">
							<span class="label">Trial</span>
							<span class="value">
								<?php
								if ( isset( $session_data['trial_enabled'] ) && $session_data['trial_enabled'] ) {
									echo esc_html( $session_data['trial_timing_per'] . ' ' . $session_data['trial_timing_option'] );
								} else {
									echo 'None';
								}
								?>
							</span>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<div class="action-buttons">
				<?php if ( $product ) : ?>
					<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" target="_blank" class="action-btn action-btn-secondary">View in shop</a>
					<a href="<?php echo esc_url( get_edit_post_link( $product->get_id() ) ); ?>" class="action-btn action-btn-secondary">Edit in admin</a>
				<?php endif; ?>
				<button type="button" id="subscrpt-btn-add-another" class="action-btn action-btn-secondary">Add another product</button>
				<button type="button" id="subscrpt-btn-go-subscriptions" class="action-btn action-btn-primary">Go to Subscriptions</button>
			</div>
		</div>
	</div>
</div>