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
$is_pro            = subscrpt_pro_activated();

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
<div class="wpsubs-layout" id="subscrpt-onboarding-wizard">
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
			<div class="wpsubs-wizard-stepper__label"><?php esc_html_e( 'Done', 'subscription' ); ?></div>
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
	<div class="wpsubs-wizard-section <?php echo 1 === $wizard_page ? 'active' : ''; ?>" data-page="1" id="subscrpt-section-1">
		<div class="wpsubs-wizard-card">
			<!-- Left: content -->
			<div class="wpsubs-p1-left">
				<div class="wpsubs-p1-badge">
					<span class="wpsubs-p1-badge__dot"></span>
					<?php esc_html_e( 'Getting started', 'subscription' ); ?>
				</div>
				<h1 class="wpsubs-p1-heading">
					<?php esc_html_e( 'Your first subscription', 'subscription' ); ?>
					<span class="wpsubs-p1-heading__accent"><?php esc_html_e( 'starts here', 'subscription' ); ?></span>
				</h1>
				<p class="wpsubs-p1-desc"><?php esc_html_e( 'Set up your first subscription product in minutes. Start from scratch or convert a product you already sell.', 'subscription' ); ?></p>
				<ul class="wpsubs-p1-bullets">
					<li><span class="wpsubs-p1-bullet-dot"></span><?php esc_html_e( 'Daily, weekly, monthly, or annual billing', 'subscription' ); ?></li>
					<li><span class="wpsubs-p1-bullet-dot"></span><?php esc_html_e( 'Free trials and one-time sign-up fees', 'subscription' ); ?></li>
					</ul>
				<div class="wpsubs-p1-actions">
					<button type="button" id="subscrpt-btn-start" class="wpsubs-btn wpsubs-btn--primary">
						<?php esc_html_e( 'Create my first subscription', 'subscription' ); ?> &rsaquo;
					</button>
					<button type="button" id="subscrpt-btn-skip" class="wpsubs-p1-skip">
						<?php esc_html_e( 'Skip the intro', 'subscription' ); ?>
					</button>
				</div>
			</div>
			<!-- Right: product preview mockup -->
			<div class="wpsubs-p1-right" aria-hidden="true">
				<p class="wpsubs-p1-preview-label"><?php esc_html_e( 'Your shop', 'subscription' ); ?></p>
				<p class="wpsubs-p1-preview-sublabel"><?php esc_html_e( 'How customers see the product', 'subscription' ); ?></p>
				<div class="wpsubs-p1-preview-card">
						<div class="wpsubs-p1-preview-img">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
								<line x1="3" y1="6" x2="21" y2="6"></line>
								<path d="M16 10a4 4 0 0 1-8 0"></path>
							</svg>
							<div class="wpsubs-p1-preview-img__dots">
								<span></span><span></span><span></span>
							</div>
						</div>
						<div class="wpsubs-p1-preview-body">
							<p class="wpsubs-p1-preview-name"><?php esc_html_e( 'Monthly Subscription Box', 'subscription' ); ?></p>
							<p class="wpsubs-p1-preview-price">24.00$ <span>/ <?php esc_html_e( 'month', 'subscription' ); ?></span></p>
							<div class="wpsubs-p1-preview-btn"><?php esc_html_e( 'Sign up', 'subscription' ); ?></div>
							<div class="wpsubs-p1-preview-skeletons">
								<div class="wpsubs-p1-preview-skeleton"></div>
								<div class="wpsubs-p1-preview-skeleton"></div>
							</div>
						</div>
				</div>
				</div>
		</div>
	</div>

	<!-- =========================================== -->
	<!-- SECTION 2: Product & Subscription Setup -->
	<!-- =========================================== -->
	<div class="wpsubs-wizard-section <?php echo 2 === $wizard_page ? 'active' : ''; ?>" id="subscrpt-section-2">
		<div class="wpsubs-wizard-card">
			<h1 class="wpsubs-p2-page-title"><?php esc_html_e( 'Create your subscription product', 'subscription' ); ?></h1>
			<p class="wpsubs-p2-page-subtitle"><?php esc_html_e( 'Set the basics now. Once published, customers can subscribe from your shop and you can fine-tune everything later in the product editor.', 'subscription' ); ?></p>

			<!-- 1. Pick a starting point -->
			<div class="wpsubs-p2-section-block">
				<p class="wpsubs-p2-section-label"><?php esc_html_e( '1. Pick a starting point', 'subscription' ); ?></p>
				<p class="wpsubs-p2-section-desc"><?php esc_html_e( 'Build a fresh subscription product, or convert one you already sell.', 'subscription' ); ?></p>

				<div class="wpsubs-p2-option-cards">
					<button type="button"
						class="wpsubs-p2-option-card product-toggle-btn <?php echo 'existing' !== ( isset( $session_data['product_mode'] ) ? $session_data['product_mode'] : 'new' ) ? 'active' : ''; ?>"
						id="subscrpt-btn-create-new" data-mode="new">
						<div class="wpsubs-p2-option-card__check">✓</div>
						<div class="wpsubs-p2-option-card__icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
						</div>
						<p class="wpsubs-p2-option-card__title"><?php esc_html_e( 'Create a new product', 'subscription' ); ?></p>
						<p class="wpsubs-p2-option-card__desc"><?php esc_html_e( 'Start with a blank subscription product.', 'subscription' ); ?></p>
					</button>

					<?php if ( ! empty( $products ) ) : ?>
					<button type="button"
						class="wpsubs-p2-option-card product-toggle-btn <?php echo 'existing' === ( isset( $session_data['product_mode'] ) ? $session_data['product_mode'] : '' ) ? 'active' : ''; ?>"
						id="subscrpt-btn-use-existing" data-mode="existing">
						<div class="wpsubs-p2-option-card__check">✓</div>
						<div class="wpsubs-p2-option-card__icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
						</div>
						<p class="wpsubs-p2-option-card__title"><?php esc_html_e( 'Use an existing product', 'subscription' ); ?></p>
						<p class="wpsubs-p2-option-card__desc"><?php esc_html_e( 'Convert a WooCommerce product into a subscription.', 'subscription' ); ?></p>
					</button>
					<?php endif; ?>
				</div>

				<!-- Existing product selector -->
				<?php if ( ! empty( $products ) ) : ?>
				<div id="subscrpt-existing-product-fields" style="<?php echo 'existing' !== ( isset( $session_data['product_mode'] ) ? $session_data['product_mode'] : '' ) ? 'display:none;' : ''; ?>">
					<div id="subscrpt-product-select-wrap">
						<?php
						$avatar_palette = array(
							array(
								'bg' => '#fde8d8',
								'fg' => '#b85c20',
							),
							array(
								'bg' => '#dbeafe',
								'fg' => '#1d4ed8',
							),
							array(
								'bg' => '#ede9fe',
								'fg' => '#6d28d9',
							),
							array(
								'bg' => '#d1fae5',
								'fg' => '#065f46',
							),
							array(
								'bg' => '#fce7f3',
								'fg' => '#9d174d',
							),
							array(
								'bg' => '#fef9c3',
								'fg' => '#854d0e',
							),
						);
						?>
						<div class="wpsubs-p2-product-search">
							<div class="wpsubs-p2-product-search__input-wrap">
								<svg class="wpsubs-p2-product-search__icon" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
								<input type="text" class="wpsubs-p2-product-search__input wpsubs-input" id="subscrpt-product-search-input"
									placeholder="<?php esc_attr_e( 'Search WooCommerce products by name or SKU...', 'subscription' ); ?>"
									autocomplete="off">
							</div>
							<div class="wpsubs-p2-product-search__dropdown" id="subscrpt-product-search-dropdown" style="display:none;">
								<?php
								foreach ( $products as $i => $p ) :
									$wc_p                = wc_get_product( $p->ID );
									$price               = $wc_p ? $wc_p->get_price() : '';
									$type                = $wc_p ? ucfirst( $wc_p->get_type() ) . ' ' . __( 'product', 'subscription' ) : '';
									$sku                 = $wc_p ? $wc_p->get_sku() : '';
									$billing_period_meta = $wc_p ? $wc_p->get_meta( '_subscrpt_timing_option' ) : '';
									$billing_per_meta    = $wc_p ? $wc_p->get_meta( '_subscrpt_timing_per' ) : '';
									$trial_per_meta      = $wc_p ? $wc_p->get_meta( '_subscrpt_trial_timing_per' ) : '';
									$signup_fee_meta     = $wc_p ? $wc_p->get_meta( '_subscrpt_signup_fee' ) : '';
									$words               = array_filter( explode( ' ', $p->post_title ) );
									$initials            = implode(
										'',
										array_slice(
											array_map(
												function ( $w ) {
													return mb_strtoupper( mb_substr( $w, 0, 1 ) );
												},
												$words
											),
											0,
											2
										)
									);
									$color               = $avatar_palette[ $i % count( $avatar_palette ) ];
									?>
									<?php $is_variable_locked = ( $wc_p && 'variable' === $wc_p->get_type() && ! $is_pro ); ?>
								<div class="wpsubs-p2-product-search__item<?php echo $is_variable_locked ? ' wpsubs-p2-product-search__item--locked' : ''; ?>"
									data-id="<?php echo esc_attr( $p->ID ); ?>"
									data-price="<?php echo esc_attr( $price ); ?>"
									data-type="<?php echo esc_attr( $type ); ?>"
									data-product-type="<?php echo esc_attr( $wc_p ? $wc_p->get_type() : '' ); ?>"
									data-sku="<?php echo esc_attr( $sku ); ?>"
									data-name="<?php echo esc_attr( $p->post_title ); ?>"
									data-billing-period="<?php echo esc_attr( $billing_period_meta ); ?>"
									data-billing-per="<?php echo esc_attr( $billing_per_meta ?: '1' ); ?>"
									data-trial-per="<?php echo esc_attr( $trial_per_meta ); ?>"
									data-signup-fee="<?php echo esc_attr( $signup_fee_meta ); ?>">
									<div class="wpsubs-p2-product-search__avatar" style="background:<?php echo esc_attr( $color['bg'] ); ?>;color:<?php echo esc_attr( $color['fg'] ); ?>">
										<?php echo esc_html( $initials ?: '?' ); ?>
									</div>
									<div class="wpsubs-p2-product-search__info">
										<p class="wpsubs-p2-product-search__name">
											<?php echo esc_html( $p->post_title ); ?>
											<?php if ( $is_variable_locked ) : ?>
												<span class="wpsubs-p2-pro-badge" title="<?php esc_attr_e( 'WPSubscription Pro required', 'subscription' ); ?>"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
											<?php endif; ?>
										</p>
										<p class="wpsubs-p2-product-search__meta"><?php echo esc_html( ( $sku ? 'SKU ' . $sku . ' · ' : '' ) . $type ); ?></p>
									</div>
									<?php if ( '' !== $price ) : ?>
									<span class="wpsubs-p2-product-search__price"><?php echo esc_html( get_woocommerce_currency_symbol() . number_format( (float) $price, 2 ) ); ?></span>
									<?php endif; ?>
								</div>
								<?php endforeach; ?>
								<div class="wpsubs-p2-product-search__empty" style="display:none;"><?php esc_html_e( 'No products found.', 'subscription' ); ?></div>
							</div>
						</div>
						<input type="hidden" name="subscrpt_existing_product" id="subscrpt-existing-product-hidden" value="<?php echo $product_id > 0 ? esc_attr( $product_id ) : ''; ?>">
					</div>
					<div id="subscrpt-selected-product-chip" class="wpsubs-p2-selected-product" style="display:none;">
						<div class="wpsubs-p2-selected-product__avatar" id="p2-chip-avatar"></div>
						<div class="wpsubs-p2-selected-product__info">
							<p class="wpsubs-p2-selected-product__name" id="p2-chip-name"></p>
							<p class="wpsubs-p2-selected-product__meta" id="p2-chip-meta"></p>
						</div>
						<button type="button" class="wpsubs-p2-selected-product__clear" id="subscrpt-btn-clear-product" aria-label="<?php esc_attr_e( 'Clear selection', 'subscription' ); ?>">×</button>
					</div>
					<!-- Variation picker (shown when a variable product is selected) -->
					<div id="subscrpt-variation-picker-wrap" class="wpsubs-p2-variation-picker" style="display:none;">
						<div id="subscrpt-variation-picker-list"></div>
					</div>
					<input type="hidden" name="subscrpt_variation_id" id="subscrpt-variation-id-hidden" value="">
				</div>
				<?php endif; ?>
			</div>

			<!-- 2. Subscription details -->
			<div class="wpsubs-p2-section-block" style="margin-bottom:0;">
				<p class="wpsubs-p2-section-label"><?php esc_html_e( '2. Subscription details', 'subscription' ); ?></p>
				<p class="wpsubs-p2-section-desc"><?php esc_html_e( 'How and how often customers are billed once they subscribe.', 'subscription' ); ?></p>

				<div class="wpsubs-p2-body">
					<!-- Left: form -->
					<div>
						<!-- Product name -->
						<div id="subscrpt-new-product-fields">
							<div class="wpsubs-form-row">
								<label for="subscrpt_product_name"><?php esc_html_e( 'Product name', 'subscription' ); ?></label>
								<input type="text" id="subscrpt_product_name" name="subscrpt_product_name" class="wpsubs-input" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. Monthly Subscription Box', 'subscription' ); ?>" value="<?php echo $product ? esc_attr( $product->get_name() ) : ( isset( $session_data['product_name'] ) ? esc_attr( $session_data['product_name'] ) : '' ); ?>">
								<p class="wpsubs-p2-field-hint"><?php esc_html_e( 'Shown on your store page and in subscription emails.', 'subscription' ); ?></p>
							</div>
						</div>

						<?php
						$billing_period       = isset( $session_data['billing_period'] ) ? $session_data['billing_period'] : 'months';
						$trial_period         = isset( $session_data['trial_timing_option'] ) ? $session_data['trial_timing_option'] : 'days';
						$trial_period_options = array(
							array(
								'value' => 'days',
								'label' => __( 'Day', 'subscription' ),
							),
							array(
								'value' => 'weeks',
								'label' => __( 'Week', 'subscription' ),
							),
							array(
								'value' => 'months',
								'label' => __( 'Month', 'subscription' ),
							),
							array(
								'value' => 'years',
								'label' => __( 'Year', 'subscription' ),
							),
						);
						$period_options       = array(
							array(
								'value' => 'days',
								'label' => __( 'Day', 'subscription' ),
							),
							array(
								'value' => 'weeks',
								'label' => __( 'Week', 'subscription' ),
							),
							array(
								'value' => 'months',
								'label' => __( 'Month', 'subscription' ),
							),
							array(
								'value' => 'years',
								'label' => __( 'Year', 'subscription' ),
							),
						);
						?>
						<input type="hidden" id="subscrpt_timing_option" name="subscrpt_timing_option" value="never">
						<input type="hidden" id="subscrpt_billing_per" name="subscrpt_billing_per" value="<?php echo $is_pro && isset( $session_data['billing_per'] ) ? esc_attr( $session_data['billing_per'] ) : '1'; ?>">

						<!-- Row: Price | Billing every (group component) -->
						<div class="wpsubs-p2-form-grid">
							<div class="wpsubs-form-row">
								<label for="subscrpt_product_price"><?php esc_html_e( 'Price', 'subscription' ); ?></label>
								<div class="wpsubs-p2-input-wrap">
									<span class="wpsubs-p2-input-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
									<input type="text" id="subscrpt_product_price" name="subscrpt_product_price" class="wpsubs-input" autocomplete="off" style="padding-left:24px!important;" placeholder="0.00" value="<?php echo $product ? esc_attr( $product->get_price() ) : ( isset( $session_data['product_price'] ) ? esc_attr( $session_data['product_price'] ) : '' ); ?>">
								</div>
							</div>
							<div class="wpsubs-form-row">
								<label><?php esc_html_e( 'Billing every', 'subscription' ); ?></label>
								<div class="wpsubs-input-group wpsubs-p2-billing-group">
									<?php if ( $is_pro ) : ?>
									<input type="number" id="subscrpt_billing_per_visible" class="wpsubs-input wpsubs-p2-billing-per-input" autocomplete="off" min="1"
										value="<?php echo isset( $session_data['billing_per'] ) ? esc_attr( $session_data['billing_per'] ) : '1'; ?>"
										oninput="document.getElementById('subscrpt_billing_per').value=this.value">
									<?php endif; ?>
									<?php
									wpsubs_render_adv_select(
										array(
											'name'    => 'subscrpt_billing_period',
											'id'      => 'subscrpt-billing-period-select',
											'value'   => $billing_period,
											'options' => $period_options,
										)
									);
									?>
								</div>
							</div>
						</div>

						<!-- Free trial (free) + Sign-up fee (Pro only) -->
						<div class="wpsubs-p2-form-grid">
							<div class="wpsubs-form-row">
								<label for="subscrpt_trial_timing_per"><?php esc_html_e( 'Free trial', 'subscription' ); ?> <span class="wpsubs-p2-label-optional"><?php esc_html_e( 'Optional', 'subscription' ); ?></span></label>
								<div class="wpsubs-input-group wpsubs-p2-billing-group">
									<input type="number" id="subscrpt_trial_timing_per" name="subscrpt_trial_timing_per" class="wpsubs-input wpsubs-p2-billing-per-input" autocomplete="off" min="0" value="<?php echo isset( $session_data['trial_timing_per'] ) ? esc_attr( $session_data['trial_timing_per'] ) : '0'; ?>">
									<?php
									wpsubs_render_adv_select(
										array(
											'name'    => 'subscrpt_trial_timing_option',
											'id'      => 'subscrpt-trial-period-select',
											'value'   => $trial_period,
											'options' => $trial_period_options,
										)
									);
									?>
								</div>
								<p class="wpsubs-p2-field-hint"><?php esc_html_e( 'Free period before the first charge. Leave 0 for none.', 'subscription' ); ?></p>
							</div>
							<div class="wpsubs-form-row <?php echo $is_pro ? '' : 'wpsubs-p2-field-pro-locked'; ?>">
								<label for="subscrpt_signup_fee">
									<?php esc_html_e( 'Sign-up fee', 'subscription' ); ?>
									<?php if ( ! $is_pro ) : ?>
										<span class="wpsubs-p2-pro-badge" title="<?php esc_attr_e( 'WPSubscription Pro required', 'subscription' ); ?>"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
									<?php else : ?>
										<span class="wpsubs-p2-label-optional"><?php esc_html_e( 'Optional', 'subscription' ); ?></span>
									<?php endif; ?>
								</label>
								<div class="wpsubs-p2-input-wrap">
									<span class="wpsubs-p2-input-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
									<input type="text" id="subscrpt_signup_fee" name="subscrpt_signup_fee" class="wpsubs-input" autocomplete="off" style="padding-left:24px!important;" placeholder="0.00"
										value="<?php echo $is_pro && isset( $session_data['signup_fee'] ) ? esc_attr( $session_data['signup_fee'] ) : ''; ?>"
										<?php echo $is_pro ? '' : 'disabled'; ?>>
								</div>
								<?php if ( ! $is_pro ) : ?>
								<p class="wpsubs-p2-field-hint"><?php esc_html_e( 'One-time charge at checkout. Upgrade to Pro to enable.', 'subscription' ); ?></p>
								<?php else : ?>
								<p class="wpsubs-p2-field-hint"><?php esc_html_e( 'One-time charge at checkout.', 'subscription' ); ?></p>
								<?php endif; ?>
							</div>
						</div>

						<!-- Hidden compat fields -->
						<input type="hidden" id="subscrpt_trial_enabled" name="subscrpt_trial_enabled" value="0">
						<input type="hidden" id="subscrpt_length_enabled" name="subscrpt_length_enabled" value="0">
						<input type="hidden" id="subscrpt_length_per" name="subscrpt_length_per" value="">
						<input type="hidden" id="subscrpt_length_option" name="subscrpt_length_option" value="months">
					</div>

					<!-- Right: shop preview -->
					<div class="wpsubs-p2-preview-col">
						<p class="wpsubs-p2-preview-col-label"><?php esc_html_e( 'Shop Preview', 'subscription' ); ?></p>
						<div class="wpsubs-p1-preview-card">
							<div class="wpsubs-p1-preview-img">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
									<line x1="3" y1="6" x2="21" y2="6"></line>
									<path d="M16 10a4 4 0 0 1-8 0"></path>
								</svg>
								<div class="wpsubs-p1-preview-img__dots"><span></span><span></span><span></span></div>
							</div>
							<div class="wpsubs-p1-preview-body">
								<p class="wpsubs-p1-preview-name" id="p2-preview-name"><?php echo $product ? esc_html( $product->get_name() ) : esc_html__( 'Your product', 'subscription' ); ?></p>
								<p class="wpsubs-p1-preview-price"><span id="p2-preview-price"><?php echo $product ? esc_html( $product->get_price() ) : '0.00'; ?></span>$ <span>/ <span id="p2-preview-period"><?php echo isset( $session_data['billing_period'] ) ? esc_html( $session_data['billing_period'] ) : esc_html__( 'month', 'subscription' ); ?></span></span></p>
								<div class="wpsubs-p1-preview-btn"><?php esc_html_e( 'Sign up', 'subscription' ); ?></div>
							</div>
						</div>
						<p class="wpsubs-p2-preview-col-desc"><?php esc_html_e( 'This is how the product will appear on your shop page. Each purchase creates a subscription you\'ll manage from the Subscriptions list.', 'subscription' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Nav outside card -->
		<div class="wpsubs-p2-nav">
			<button type="button" id="subscrpt-btn-back" class="wpsubs-p2-nav-back">
				&#8249; <?php esc_html_e( 'Back', 'subscription' ); ?>
			</button>
			<button type="button" id="subscrpt-btn-save" class="wpsubs-btn wpsubs-btn--primary">
				<?php esc_html_e( 'Create product', 'subscription' ); ?> &rsaquo;
			</button>
		</div>
	</div>

	<!-- =========================================== -->
	<!-- SECTION 3: Completion -->
	<!-- =========================================== -->
	<div class="wpsubs-wizard-section <?php echo 3 === $wizard_page ? 'active' : ''; ?>" id="subscrpt-section-3">
		<div class="wpsubs-wizard-card wpsubs-p3-card">

			<!-- Success icon -->
			<div class="wpsubs-p3-success-icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<polyline points="20 6 9 17 4 12"></polyline>
				</svg>
			</div>

			<h1 class="wpsubs-p3-heading"><?php esc_html_e( 'Your product is live.', 'subscription' ); ?></h1>
			<p class="wpsubs-p3-subtext"><?php esc_html_e( "It's published to your shop now. When a customer buys it, a subscription is created automatically. You'll see those in the Subscriptions list.", 'subscription' ); ?></p>

			<?php
			if ( $product ) :
				$p3_price        = $product->get_price();
				$p3_period_raw   = isset( $session_data['billing_period'] ) ? $session_data['billing_period'] : 'months';
				$p3_billing_per  = isset( $session_data['billing_per'] ) ? (int) $session_data['billing_per'] : 1;
				$p3_period_label = $p3_billing_per > 1
					? $p3_billing_per . ' ' . strtolower( $p3_period_raw )
					: strtolower( subscrpt_get_typos( 1, $p3_period_raw ) );
				$p3_words        = array_filter( explode( ' ', $product->get_name() ) );
				$p3_initials     = implode(
					'',
					array_map(
						function ( $w ) {
							return strtoupper( mb_substr( $w, 0, 1 ) );
						},
						array_slice( $p3_words, 0, 2 )
					)
				);
				?>
			<!-- Product card -->
			<div class="wpsubs-p3-product-card">
				<div class="wpsubs-p3-product-card__header">
					<span><?php esc_html_e( 'PRODUCT', 'subscription' ); ?></span>
					<span><?php esc_html_e( 'STATUS', 'subscription' ); ?></span>
					<span><?php esc_html_e( 'PRICE', 'subscription' ); ?></span>
				</div>
				<div class="wpsubs-p3-product-card__row">
					<div class="wpsubs-p3-product-card__product">
						<div class="wpsubs-p3-product-avatar"><?php echo esc_html( $p3_initials ); ?></div>
						<div>
							<p class="wpsubs-p3-product-name"><?php echo esc_html( $product->get_name() ); ?></p>
							<p class="wpsubs-p3-product-meta">
								<?php
								/* translators: %d: product ID */
								echo esc_html( sprintf( __( 'Product #%d', 'subscription' ), $product->get_id() ) );
								?>
								&middot; <?php esc_html_e( 'Subscription', 'subscription' ); ?>
							</p>
						</div>
					</div>
					<div class="wpsubs-p3-product-card__status">
						<span class="wpsubs-p3-status-badge">
							<span class="wpsubs-p3-status-badge__dot"></span>
							<?php esc_html_e( 'Published', 'subscription' ); ?>
						</span>
						<p class="wpsubs-p3-status-sub"><?php esc_html_e( 'Live in your shop', 'subscription' ); ?></p>
					</div>
					<div class="wpsubs-p3-product-card__price">
						<span class="wpsubs-p3-price-amount"><?php echo wp_kses_post( wc_price( $p3_price ) ); ?></span>
						<span class="wpsubs-p3-price-period">/ <?php echo esc_html( $p3_period_label ); ?></span>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<!-- What now -->
			<p class="wpsubs-p3-what-now-label"><?php esc_html_e( 'WHAT NOW?', 'subscription' ); ?></p>

			<div class="wpsubs-p3-action-rows">
				<?php if ( $product ) : ?>
				<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" target="_blank" rel="noopener" class="wpsubs-p3-action-row">
					<div class="wpsubs-p3-action-row__icon">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
					</div>
					<div class="wpsubs-p3-action-row__content">
						<p class="wpsubs-p3-action-row__title"><?php esc_html_e( 'View product in shop', 'subscription' ); ?></p>
						<p class="wpsubs-p3-action-row__desc"><?php esc_html_e( 'See how customers will subscribe to it.', 'subscription' ); ?></p>
					</div>
					<svg class="wpsubs-p3-action-row__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
				</a>
				<?php endif; ?>

				<button type="button" id="subscrpt-btn-add-another" class="wpsubs-p3-action-row">
					<div class="wpsubs-p3-action-row__icon">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
					</div>
					<div class="wpsubs-p3-action-row__content">
						<p class="wpsubs-p3-action-row__title"><?php esc_html_e( 'Add another product', 'subscription' ); ?></p>
						<p class="wpsubs-p3-action-row__desc"><?php esc_html_e( 'Create another subscription product now.', 'subscription' ); ?></p>
					</div>
					<svg class="wpsubs-p3-action-row__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
				</button>

			</div>

			<p class="wpsubs-p3-help-text">
				<?php esc_html_e( 'Need help? Check the', 'subscription' ); ?>
				<a href="https://docs.wpsubscription.co/en" target="_blank" rel="noopener" class="wpsubs-p3-help-link"><?php esc_html_e( 'setup guide', 'subscription' ); ?></a>
				<?php esc_html_e( 'or', 'subscription' ); ?>
				<a href="https://wordpress.org/support/plugin/subscription/" target="_blank" rel="noopener" class="wpsubs-p3-help-link"><?php esc_html_e( 'contact support', 'subscription' ); ?></a>.
			</p>
		</div>

		<!-- Bottom nav -->
		<div class="wpsubs-p3-nav">
			<button type="button" id="subscrpt-btn-start-over" class="wpsubs-btn wpsubs-btn--outline">
				<?php esc_html_e( 'Start over', 'subscription' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="wpsubs-btn wpsubs-btn--primary">
				<?php esc_html_e( 'Go to products', 'subscription' ); ?>
			</a>
		</div>
	</div>
</div>
