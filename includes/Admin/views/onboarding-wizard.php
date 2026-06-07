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
<style>
#subscrpt-onboarding-wizard {
	max-width: 680px;
}
#subscrpt-onboarding-wizard:has(#subscrpt-section-1.active),
#subscrpt-onboarding-wizard:has(#subscrpt-section-2.active) {
	max-width: 900px;
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
	width: fit-content;
	margin: 0 auto 32px;
	gap: 0;
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
	color: var(--wpsubs-surface);
}
.wpsubs-wizard-stepper__step.done .wpsubs-wizard-stepper__num {
	background: var(--wpsubs-brand);
	border-color: var(--wpsubs-brand);
	color: transparent;
	position: relative;
}
.wpsubs-wizard-stepper__step.done .wpsubs-wizard-stepper__num::after {
	content: "✓";
	position: absolute;
	color: var(--wpsubs-surface);
	font-size: 11px;
	font-weight: 700;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
}
.wpsubs-wizard-stepper__step.done + .wpsubs-wizard-stepper__line {
	background: var(--wpsubs-brand);
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
	width: 48px;
	flex-shrink: 0;
	height: 1px;
	background: var(--wpsubs-border);
	margin: 0 8px;
}
/* Card */
.wizard-card {
	background: var(--wpsubs-surface);
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	padding: 28px 32px;
	margin-bottom: 16px;
}
/* Form rows (shared) */
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
/* Page 2 */
.p2-page-title {
	font-size: 22px;
	font-weight: 800;
	color: var(--wpsubs-text);
	margin: 0 0 8px;
	line-height: 1.25;
}
.p2-page-subtitle {
	font-size: 13.5px;
	color: var(--wpsubs-text-muted);
	line-height: 1.6;
	margin: 0 0 28px;
}
.p2-section-block {
	margin-bottom: 28px;
}
.p2-section-label {
	font-size: 14px;
	font-weight: 700;
	color: var(--wpsubs-text);
	margin: 0 0 4px;
}
.p2-section-desc {
	font-size: 13px;
	color: var(--wpsubs-text-muted);
	margin: 0 0 14px;
}
/* Option cards */
.p2-option-cards {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
	margin-bottom: 12px;
}
.p2-option-card {
	position: relative;
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 16px;
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	background: var(--wpsubs-surface);
	cursor: pointer;
	text-align: left;
	transition: border-color 0.15s, background 0.15s;
}
.p2-option-card:hover {
	border-color: var(--wpsubs-brand);
}
.p2-option-card.active {
	border-color: var(--wpsubs-brand);
	background: var(--wpsubs-brand-light);
}
.p2-option-card__check {
	position: absolute;
	top: 10px;
	right: 10px;
	width: 20px;
	height: 20px;
	border-radius: 50%;
	background: var(--wpsubs-brand);
	display: none;
	align-items: center;
	justify-content: center;
	color: var(--wpsubs-surface);
	font-size: 11px;
	font-weight: 700;
}
.p2-option-card.active .p2-option-card__check {
	display: flex;
}
.p2-option-card__icon {
	width: 32px;
	height: 32px;
	border-radius: 6px;
	border: 1px solid var(--wpsubs-border);
	background: var(--wpsubs-surface);
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--wpsubs-text-muted);
	margin-bottom: 4px;
}
.p2-option-card.active .p2-option-card__icon {
	border-color: var(--wpsubs-brand);
	color: var(--wpsubs-brand);
}
.p2-option-card__title {
	font-size: 13px;
	font-weight: 700;
	color: var(--wpsubs-text);
	margin: 0;
}
.p2-option-card__desc {
	font-size: 12px;
	color: var(--wpsubs-text-muted);
	margin: 0;
	line-height: 1.5;
}
/* Selected product chip */
.p2-selected-product {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 14px;
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	background: var(--wpsubs-surface);
	margin-bottom: 4px;
}
.p2-selected-product__avatar {
	width: 36px;
	height: 36px;
	border-radius: 6px;
	background: var(--wpsubs-surface-muted);
	border: 1px solid var(--wpsubs-border);
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 11px;
	font-weight: 700;
	color: var(--wpsubs-text-muted);
	flex-shrink: 0;
}
.p2-selected-product__info {
	flex: 1;
	min-width: 0;
}
.p2-selected-product__name {
	font-size: 13px;
	font-weight: 600;
	color: var(--wpsubs-text);
	margin: 0 0 2px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.p2-selected-product__meta {
	font-size: 12px;
	color: var(--wpsubs-text-muted);
	margin: 0;
}
.p2-selected-product__clear {
	background: none;
	border: none;
	cursor: pointer;
	color: var(--wpsubs-text-muted);
	font-size: 18px;
	line-height: 1;
	padding: 0 4px;
	flex-shrink: 0;
	transition: color 0.15s;
}
.p2-selected-product__clear:hover {
	color: var(--wpsubs-text);
}
/* Section 2 body */
.p2-body {
	display: grid;
	grid-template-columns: 1fr 260px;
	gap: 32px;
	align-items: start;
}
.p2-form-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 16px;
	margin-bottom: 16px;
}
.p2-form-grid .form-row {
	margin-bottom: 0;
}
.p2-input-wrap {
	position: relative;
}
.p2-input-prefix {
	position: absolute;
	left: 11px;
	top: 50%;
	transform: translateY(-50%);
	font-size: 13px;
	color: var(--wpsubs-text-muted);
	pointer-events: none;
	z-index: 1;
}
.p2-input-prefix + .wpsubs-input {
	padding-left: 24px !important;
}
.p2-input-suffix {
	position: absolute;
	right: 11px;
	top: 50%;
	transform: translateY(-50%);
	font-size: 13px;
	color: var(--wpsubs-text-muted);
	pointer-events: none;
}
.p2-input-suffix-input {
	padding-right: 44px !important;
}
.p2-field-hint {
	font-size: 12px;
	color: var(--wpsubs-text-muted);
	margin-top: 5px;
	line-height: 1.4;
}
.p2-label-optional {
	font-weight: 400;
	color: var(--wpsubs-text-subtle);
	font-size: 12px;
	margin-left: 4px;
}
/* Page 2 right preview */
.p2-preview-col {
	position: sticky;
	top: 80px;
}
.p2-preview-col-label {
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.08em;
	text-transform: uppercase;
	color: var(--wpsubs-text-muted);
	margin: 0 0 10px;
}
.p2-preview-col-desc {
	font-size: 12px;
	color: var(--wpsubs-text-muted);
	line-height: 1.5;
	margin-top: 10px;
}
/* Page 2 nav (outside card) */
.p2-nav {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-top: 16px;
}
.p2-nav-back {
	background: none;
	border: none;
	cursor: pointer;
	font-size: 13px;
	color: var(--wpsubs-text-muted);
	display: flex;
	align-items: center;
	gap: 4px;
	padding: 0;
	transition: color 0.15s;
}
.p2-nav-back:hover {
	color: var(--wpsubs-text);
}
/* Product search */
.p2-product-search {
	position: relative;
}
.p2-product-search__input-wrap {
	display: flex;
	align-items: center;
	gap: 8px;
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	padding: 0 12px;
	background: var(--wpsubs-surface);
	transition: border-color 0.15s, box-shadow 0.15s;
}
.p2-product-search__input-wrap:focus-within {
	border-color: var(--wpsubs-brand);
	box-shadow: 0 0 0 3px var(--wpsubs-brand-ring);
}
.p2-product-search__icon {
	color: var(--wpsubs-text-muted);
	flex-shrink: 0;
}
.p2-product-search__input,
.p2-product-search__input:focus {
	flex: 1;
	border: none !important;
	outline: none !important;
	box-shadow: none !important;
	padding: 9px 0 !important;
	font-size: 13px;
	background: transparent !important;
}
.p2-product-search__dropdown {
	position: absolute;
	top: calc(100% + 4px);
	left: 0;
	right: 0;
	background: var(--wpsubs-surface);
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	box-shadow: var(--wpsubs-shadow-md);
	z-index: 999;
	max-height: 300px;
	overflow-y: auto;
	padding: 4px;
}
.p2-product-search__item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 9px 10px;
	border-radius: var(--wpsubs-radius-sm);
	cursor: pointer;
	transition: background 0.1s;
}
.p2-product-search__item:hover {
	background: var(--wpsubs-surface-muted);
}
.p2-product-search__avatar {
	width: 36px;
	height: 36px;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 12px;
	font-weight: 700;
	flex-shrink: 0;
}
.p2-product-search__info {
	flex: 1;
	min-width: 0;
}
.p2-product-search__name {
	font-size: 13px;
	font-weight: 600;
	color: var(--wpsubs-text);
	margin: 0 0 2px;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.p2-product-search__meta {
	font-size: 12px;
	color: var(--wpsubs-text-muted);
	margin: 0;
}
.p2-product-search__price {
	font-size: 13px;
	font-weight: 600;
	color: var(--wpsubs-text);
	white-space: nowrap;
	flex-shrink: 0;
}
.p2-product-search__empty {
	padding: 12px 10px;
	font-size: 13px;
	color: var(--wpsubs-text-muted);
	text-align: center;
}
.p2-product-search__item--locked {
	opacity: 0.55;
	cursor: not-allowed;
}
/* Billing every group — number input + standalone adv-select */
.p2-billing-group {
	width: 100%;
}
.p2-billing-group .p2-billing-per-input {
	flex-grow: 1;
}
#subscrpt-billing-period-select {
	width: 100%;
}
/* Pro badge + locked fields */
.p2-pro-badge {
	display: inline-flex;
	align-items: center;
	background: var(--wpsubs-brand);
	color: var(--wpsubs-surface);
	font-size: 10px;
	font-weight: 700;
	padding: 2px 6px;
	border-radius: 3px;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	margin-left: 6px;
	vertical-align: middle;
}
.p2-field-pro-locked {
	opacity: 0.6;
}
/* Variation picker */
.p2-variation-picker {
	margin-top: 12px;
}
.p2-variation-picker__label {
	font-size: 11px;
	font-weight: 700;
	letter-spacing: 0.06em;
	text-transform: uppercase;
	color: var(--wpsubs-text-muted);
	margin: 0 0 8px;
}
.p2-variation-picker__loading {
	font-size: 13px;
	color: var(--wpsubs-text-muted);
	padding: 10px 0;
}
.p2-variation-picker__list {
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	overflow: hidden;
}
.p2-variation-item {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 12px;
	cursor: pointer;
	transition: background 0.1s;
	border-bottom: 1px solid var(--wpsubs-border);
}
.p2-variation-item:last-child {
	border-bottom: none;
}
.p2-variation-item:hover {
	background: var(--wpsubs-surface-muted);
}
.p2-variation-item.selected {
	background: var(--wpsubs-brand-light);
}
.p2-variation-item__check {
	width: 18px;
	height: 18px;
	border-radius: 50%;
	border: 1.5px solid var(--wpsubs-border);
	flex-shrink: 0;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 10px;
	font-weight: 700;
	color: transparent;
	transition: all 0.15s;
}
.p2-variation-item.selected .p2-variation-item__check {
	background: var(--wpsubs-brand);
	border-color: var(--wpsubs-brand);
	color: var(--wpsubs-surface);
}
.p2-variation-item__info {
	flex: 1;
	min-width: 0;
}
.p2-variation-item__name {
	font-size: 13px;
	font-weight: 500;
	color: var(--wpsubs-text);
	margin: 0;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.p2-variation-item__meta {
	font-size: 12px;
	color: var(--wpsubs-text-muted);
	margin: 0;
}
.p2-variation-item__price {
	font-size: 13px;
	font-weight: 600;
	color: var(--wpsubs-text);
	white-space: nowrap;
	flex-shrink: 0;
}
/* Page 3 */
.p3-card {
	text-align: center;
	padding: 40px 40px 32px;
}
.p3-success-icon {
	width: 52px;
	height: 52px;
	border-radius: 50%;
	background: var(--wpsubs-brand);
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 auto 20px;
}
.p3-heading {
	font-size: 24px;
	font-weight: 700;
	color: var(--wpsubs-text);
	margin-bottom: 10px;
	font-family: Georgia, serif;
}
.p3-subtext {
	font-size: 13.5px;
	color: var(--wpsubs-text-muted);
	line-height: 1.6;
	max-width: 440px;
	margin: 0 auto 28px;
}
/* Product card */
.p3-product-card {
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	overflow: hidden;
	margin-bottom: 24px;
	text-align: left;
}
.p3-product-card__header {
	display: grid;
	grid-template-columns: 1fr 160px 110px;
	padding: 8px 16px;
	border-bottom: 1px solid var(--wpsubs-border);
}
.p3-product-card__header span {
	font-size: 10px;
	font-weight: 600;
	letter-spacing: 0.06em;
	color: var(--wpsubs-text-muted);
	text-transform: uppercase;
}
.p3-product-card__row {
	display: grid;
	grid-template-columns: 1fr 160px 110px;
	align-items: center;
	padding: 14px 16px;
}
.p3-product-card__product {
	display: flex;
	align-items: center;
	gap: 12px;
}
.p3-product-avatar {
	width: 36px;
	height: 36px;
	border-radius: 6px;
	background: #fde8d8;
	color: #c2440f;
	font-size: 12px;
	font-weight: 700;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}
.p3-product-name {
	font-size: 13px;
	font-weight: 600;
	color: var(--wpsubs-text);
	margin: 0 0 2px;
}
.p3-product-meta {
	font-size: 11.5px;
	color: var(--wpsubs-text-muted);
	margin: 0;
}
.p3-product-card__status {
	display: flex;
	flex-direction: column;
	gap: 4px;
}
.p3-status-badge {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	background: #ecfdf5;
	color: #065f46;
	font-size: 12px;
	font-weight: 500;
	padding: 3px 8px 3px 6px;
	border-radius: 12px;
	width: fit-content;
}
.p3-status-badge__dot {
	width: 6px;
	height: 6px;
	border-radius: 50%;
	background: #10b981;
	flex-shrink: 0;
}
.p3-status-sub {
	font-size: 11.5px;
	color: var(--wpsubs-text-muted);
	margin: 0;
}
.p3-product-card__price {
	text-align: right;
}
.p3-price-amount {
	display: block;
	font-size: 15px;
	font-weight: 700;
	color: var(--wpsubs-text);
}
.p3-price-period {
	font-size: 11.5px;
	color: var(--wpsubs-text-muted);
}
/* What now section */
.p3-what-now-label {
	font-size: 10.5px;
	font-weight: 700;
	letter-spacing: 0.08em;
	color: var(--wpsubs-text-muted);
	text-transform: uppercase;
	text-align: left;
	margin-bottom: 8px;
}
.p3-action-rows {
	display: flex;
	flex-direction: column;
	gap: 0;
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	overflow: hidden;
	margin-bottom: 20px;
}
.p3-action-row {
	display: flex;
	align-items: center;
	gap: 14px;
	padding: 14px 16px;
	background: var(--wpsubs-surface);
	text-decoration: none;
	text-align: left;
	border: none;
	cursor: pointer;
	width: 100%;
	box-sizing: border-box;
	transition: background 0.12s;
	font-family: inherit;
}
.p3-action-row + .p3-action-row {
	border-top: 1px solid var(--wpsubs-border);
}
.p3-action-row:hover {
	background: var(--wpsubs-surface-muted);
}
.p3-action-row__icon {
	width: 34px;
	height: 34px;
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius-sm);
	background: var(--wpsubs-surface-muted);
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	color: var(--wpsubs-text-muted);
}
.p3-action-row__content {
	flex: 1;
	min-width: 0;
}
.p3-action-row__title {
	font-size: 13px;
	font-weight: 600;
	color: var(--wpsubs-text);
	margin: 0 0 2px;
}
.p3-action-row__desc {
	font-size: 12px;
	color: var(--wpsubs-text-muted);
	margin: 0;
}
.p3-action-row__chevron {
	color: var(--wpsubs-text-subtle);
	flex-shrink: 0;
}
/* Help footer */
.p3-help-text {
	font-size: 12.5px;
	color: var(--wpsubs-text-muted);
	text-align: center;
}
.p3-help-link {
	color: var(--wpsubs-brand);
	text-decoration: none;
}
.p3-help-link:hover {
	text-decoration: underline;
}
/* Page 3 nav */
.p3-nav {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 10px;
	margin-top: 16px;
}
.p3-nav .wpsubs-btn {
	min-width: 140px;
}
/* Page 1 specific — two-column hero layout */
#subscrpt-section-1 {
	max-width: 860px;
}
#subscrpt-section-1 .wizard-card {
	padding: 56px 48px;
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 64px;
	align-items: center;
}
.p1-left {
	display: flex;
	flex-direction: column;
	gap: 0;
}
.p1-badge {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	background: var(--wpsubs-brand-light);
	color: var(--wpsubs-brand);
	font-size: 12px;
	font-weight: 600;
	padding: 4px 10px;
	border-radius: 20px;
	border: 1px solid var(--wpsubs-border);
	margin-bottom: 20px;
	width: fit-content;
}
.p1-badge__dot {
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background: var(--wpsubs-brand);
	flex-shrink: 0;
}
.p1-heading {
	font-size: 26px;
	font-weight: 800;
	color: var(--wpsubs-text);
	line-height: 1.25;
	margin: 0 0 20px;
}
.p1-heading__accent {
	color: var(--wpsubs-brand);
}
.p1-desc {
	font-size: 13.5px;
	color: var(--wpsubs-text-muted);
	line-height: 1.6;
	margin: 0 0 24px;
}
.p1-bullets {
	list-style: none;
	padding: 0;
	margin: 0 0 36px;
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.p1-bullets li {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 13px;
	color: var(--wpsubs-text);
}
.p1-bullet-dot {
	width: 7px;
	height: 7px;
	border-radius: 50%;
	background: var(--wpsubs-brand);
	flex-shrink: 0;
}
.p1-actions {
	display: flex;
	align-items: center;
	gap: 16px;
	flex-wrap: wrap;
}
.p1-skip {
	font-size: 13px;
	color: var(--wpsubs-text-muted);
	background: none;
	border: none;
	cursor: pointer;
	padding: 0;
	text-decoration: none;
}
.p1-skip:hover {
	color: var(--wpsubs-text);
}
/* Right — product preview mockup */
.p1-right {
	background: var(--wpsubs-surface-muted);
	border-radius: var(--wpsubs-radius);
	padding: 20px 20px 14px;
	border: 1px solid var(--wpsubs-border);
	transform: rotate(-3deg);
	transform-origin: center center;
}
.p1-preview-label {
	font-size: 13px;
	font-weight: 600;
	color: var(--wpsubs-text);
	margin: 0 0 2px;
}
.p1-preview-sublabel {
	font-size: 12px;
	color: var(--wpsubs-text-muted);
	margin: 0 0 16px;
}
.p1-preview-card {
	background: var(--wpsubs-surface);
	border-radius: var(--wpsubs-radius);
	border: 1px solid var(--wpsubs-border);
	overflow: hidden;
	box-shadow: var(--wpsubs-shadow-md);
}
.p1-preview-img {
	width: 100%;
	height: 100px;
	background: linear-gradient(135deg, var(--wpsubs-brand-light) 0%, var(--wpsubs-surface-muted) 100%);
	display: flex;
	align-items: center;
	justify-content: center;
	flex-direction: column;
	gap: 6px;
	border-bottom: 1px solid var(--wpsubs-border);
}
.p1-preview-img svg {
	width: 36px;
	height: 36px;
	color: var(--wpsubs-brand);
	opacity: 0.8;
}
.p1-preview-img__dots {
	display: flex;
	gap: 4px;
}
.p1-preview-img__dots span {
	width: 5px;
	height: 5px;
	border-radius: 50%;
	background: var(--wpsubs-brand);
	opacity: 0.3;
}
.p1-preview-body {
	padding: 14px;
}
.p1-preview-name {
	font-size: 13px;
	font-weight: 700;
	text-align: center;
	color: var(--wpsubs-text);
	margin: 0 0 4px;
}
.p1-preview-price {
	font-size: 13px;
	text-align: center;
	color: var(--wpsubs-text);
	margin: 0 0 12px;
}
.p1-preview-price span {
	color: var(--wpsubs-text-muted);
	font-size: 12px;
}
.p1-preview-btn {
	display: block;
	width: 100%;
	box-sizing: border-box;
	background: var(--wpsubs-brand);
	color: var(--wpsubs-surface);
	text-align: center;
	padding: 9px 12px;
	border-radius: var(--wpsubs-radius-sm);
	font-size: 13px;
	font-weight: 600;
	border: none;
	cursor: default;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.p1-preview-skeletons {
	margin-top: 10px;
	display: flex;
	flex-direction: column;
	gap: 6px;
}
.p1-preview-skeleton {
	height: 10px;
	border-radius: 4px;
	background: var(--wpsubs-border);
}
.p1-preview-skeleton:last-child {
	width: 65%;
}
.p1-starts-here {
	text-align: right;
	font-size: 11px;
	color: var(--wpsubs-brand);
	margin-top: 12px;
	font-style: italic;
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 4px;
}
@media (max-width: 680px) {
	#subscrpt-section-1 .wizard-card {
		grid-template-columns: 1fr;
	}
	.p1-right {
		display: none;
	}
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
	<div class="wizard-section <?php echo 1 === $wizard_page ? 'active' : ''; ?>" data-page="1" id="subscrpt-section-1">
		<div class="wizard-card">
			<!-- Left: content -->
			<div class="p1-left">
				<div class="p1-badge">
					<span class="p1-badge__dot"></span>
					<?php esc_html_e( 'Getting started', 'subscription' ); ?>
				</div>
				<h1 class="p1-heading">
					<?php esc_html_e( 'Sell anything', 'subscription' ); ?>
					<span class="p1-heading__accent"><?php esc_html_e( 'on repeat.', 'subscription' ); ?></span>
				</h1>
				<p class="p1-desc"><?php esc_html_e( "You haven't created a subscription product yet. Let's set one up together — you can start from a blank product or convert one you already sell.", 'subscription' ); ?></p>
				<ul class="p1-bullets">
					<li><span class="p1-bullet-dot"></span><?php esc_html_e( 'Daily, weekly, monthly, or annual billing', 'subscription' ); ?></li>
					<li><span class="p1-bullet-dot"></span><?php esc_html_e( 'Free trials and one-time sign-up fees', 'subscription' ); ?></li>
					<li><span class="p1-bullet-dot"></span><?php esc_html_e( 'Works with every WooCommerce product type', 'subscription' ); ?></li>
				</ul>
				<div class="p1-actions">
					<button type="button" id="subscrpt-btn-start" class="wpsubs-btn wpsubs-btn--primary">
						<?php esc_html_e( 'Create my first subscription', 'subscription' ); ?> &rsaquo;
					</button>
					<button type="button" id="subscrpt-btn-skip" class="p1-skip">
						<?php esc_html_e( 'Skip the intro', 'subscription' ); ?>
					</button>
				</div>
			</div>
			<!-- Right: product preview mockup -->
			<div class="p1-right" aria-hidden="true">
				<p class="p1-preview-label"><?php esc_html_e( 'Your shop', 'subscription' ); ?></p>
				<p class="p1-preview-sublabel"><?php esc_html_e( 'How customers see the product', 'subscription' ); ?></p>
				<div class="p1-preview-card">
						<div class="p1-preview-img">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
								<line x1="3" y1="6" x2="21" y2="6"></line>
								<path d="M16 10a4 4 0 0 1-8 0"></path>
							</svg>
							<div class="p1-preview-img__dots">
								<span></span><span></span><span></span>
							</div>
						</div>
						<div class="p1-preview-body">
							<p class="p1-preview-name"><?php esc_html_e( 'Monthly Subscription Box', 'subscription' ); ?></p>
							<p class="p1-preview-price">24.00$ <span>/ <?php esc_html_e( 'month', 'subscription' ); ?></span></p>
							<div class="p1-preview-btn"><?php esc_html_e( 'Sign up', 'subscription' ); ?></div>
							<div class="p1-preview-skeletons">
								<div class="p1-preview-skeleton"></div>
								<div class="p1-preview-skeleton"></div>
							</div>
						</div>
				</div>
				<div class="p1-starts-here">
					<svg width="18" height="12" viewBox="0 0 18 12" fill="none" style="color:var(--wpsubs-brand);"><path d="M1 1 C5 1, 14 11, 17 11" stroke="currentColor" stroke-width="1.4" fill="none" stroke-linecap="round"/><polyline points="14,8 17,11 14,14" stroke="currentColor" stroke-width="1.4" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
					<?php esc_html_e( 'Starts here', 'subscription' ); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- =========================================== -->
	<!-- SECTION 2: Product & Subscription Setup -->
	<!-- =========================================== -->
	<div class="wizard-section <?php echo 2 === $wizard_page ? 'active' : ''; ?>" id="subscrpt-section-2">
		<div class="wizard-card">
			<h1 class="p2-page-title"><?php esc_html_e( 'Create your subscription product', 'subscription' ); ?></h1>
			<p class="p2-page-subtitle"><?php esc_html_e( 'Set the basics now — once published, customers can subscribe to it from your shop. You can fine-tune everything later in the product editor.', 'subscription' ); ?></p>

			<!-- 1. Pick a starting point -->
			<div class="p2-section-block">
				<p class="p2-section-label"><?php esc_html_e( '1. Pick a starting point', 'subscription' ); ?></p>
				<p class="p2-section-desc"><?php esc_html_e( 'Build a fresh subscription product, or convert one you already sell.', 'subscription' ); ?></p>

				<div class="p2-option-cards">
					<button type="button"
						class="p2-option-card product-toggle-btn <?php echo 'existing' !== ( isset( $session_data['product_mode'] ) ? $session_data['product_mode'] : 'new' ) ? 'active' : ''; ?>"
						id="subscrpt-btn-create-new" data-mode="new">
						<div class="p2-option-card__check">✓</div>
						<div class="p2-option-card__icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
						</div>
						<p class="p2-option-card__title"><?php esc_html_e( 'Create a new product', 'subscription' ); ?></p>
						<p class="p2-option-card__desc"><?php esc_html_e( 'Start with a blank subscription product.', 'subscription' ); ?></p>
					</button>

					<?php if ( ! empty( $products ) ) : ?>
					<button type="button"
						class="p2-option-card product-toggle-btn <?php echo 'existing' === ( isset( $session_data['product_mode'] ) ? $session_data['product_mode'] : '' ) ? 'active' : ''; ?>"
						id="subscrpt-btn-use-existing" data-mode="existing">
						<div class="p2-option-card__check">✓</div>
						<div class="p2-option-card__icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
						</div>
						<p class="p2-option-card__title"><?php esc_html_e( 'Use an existing product', 'subscription' ); ?></p>
						<p class="p2-option-card__desc"><?php esc_html_e( 'Convert a WooCommerce product into a subscription.', 'subscription' ); ?></p>
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
						<div class="p2-product-search">
							<div class="p2-product-search__input-wrap">
								<svg class="p2-product-search__icon" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
								<input type="text" class="p2-product-search__input wpsubs-input" id="subscrpt-product-search-input"
									placeholder="<?php esc_attr_e( 'Search WooCommerce products by name or SKU...', 'subscription' ); ?>"
									autocomplete="off">
							</div>
							<div class="p2-product-search__dropdown" id="subscrpt-product-search-dropdown" style="display:none;">
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
								<div class="p2-product-search__item<?php echo $is_variable_locked ? ' p2-product-search__item--locked' : ''; ?>"
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
									<div class="p2-product-search__avatar" style="background:<?php echo esc_attr( $color['bg'] ); ?>;color:<?php echo esc_attr( $color['fg'] ); ?>">
										<?php echo esc_html( $initials ?: '?' ); ?>
									</div>
									<div class="p2-product-search__info">
										<p class="p2-product-search__name">
											<?php echo esc_html( $p->post_title ); ?>
											<?php if ( $is_variable_locked ) : ?>
												<span class="p2-pro-badge" title="<?php esc_attr_e( 'WPSubscription Pro required', 'subscription' ); ?>"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
											<?php endif; ?>
										</p>
										<p class="p2-product-search__meta"><?php echo esc_html( ( $sku ? 'SKU ' . $sku . ' · ' : '' ) . $type ); ?></p>
									</div>
									<?php if ( '' !== $price ) : ?>
									<span class="p2-product-search__price"><?php echo esc_html( get_woocommerce_currency_symbol() . number_format( (float) $price, 2 ) ); ?></span>
									<?php endif; ?>
								</div>
								<?php endforeach; ?>
								<div class="p2-product-search__empty" style="display:none;"><?php esc_html_e( 'No products found.', 'subscription' ); ?></div>
							</div>
						</div>
						<input type="hidden" name="subscrpt_existing_product" id="subscrpt-existing-product-hidden" value="<?php echo $product_id > 0 ? esc_attr( $product_id ) : ''; ?>">
					</div>
					<div id="subscrpt-selected-product-chip" class="p2-selected-product" style="display:none;">
						<div class="p2-selected-product__avatar" id="p2-chip-avatar"></div>
						<div class="p2-selected-product__info">
							<p class="p2-selected-product__name" id="p2-chip-name"></p>
							<p class="p2-selected-product__meta" id="p2-chip-meta"></p>
						</div>
						<button type="button" class="p2-selected-product__clear" id="subscrpt-btn-clear-product" aria-label="<?php esc_attr_e( 'Clear selection', 'subscription' ); ?>">×</button>
					</div>
					<!-- Variation picker (shown when a variable product is selected) -->
					<div id="subscrpt-variation-picker-wrap" class="p2-variation-picker" style="display:none;">
						<div id="subscrpt-variation-picker-list"></div>
					</div>
					<input type="hidden" name="subscrpt_variation_id" id="subscrpt-variation-id-hidden" value="">
				</div>
				<?php endif; ?>
			</div>

			<!-- 2. Subscription details -->
			<div class="p2-section-block" style="margin-bottom:0;">
				<p class="p2-section-label"><?php esc_html_e( '2. Subscription details', 'subscription' ); ?></p>
				<p class="p2-section-desc"><?php esc_html_e( 'How and how often customers are billed once they subscribe.', 'subscription' ); ?></p>

				<div class="p2-body">
					<!-- Left: form -->
					<div>
						<!-- Product name -->
						<div id="subscrpt-new-product-fields">
							<div class="form-row">
								<label for="subscrpt_product_name"><?php esc_html_e( 'Product name', 'subscription' ); ?></label>
								<input type="text" id="subscrpt_product_name" name="subscrpt_product_name" class="wpsubs-input" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. Monthly Subscription Box', 'subscription' ); ?>" value="<?php echo $product ? esc_attr( $product->get_name() ) : ( isset( $session_data['product_name'] ) ? esc_attr( $session_data['product_name'] ) : '' ); ?>">
								<p class="p2-field-hint"><?php esc_html_e( 'Shown on your store page and in subscription emails.', 'subscription' ); ?></p>
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
						<div class="p2-form-grid">
							<div class="form-row">
								<label for="subscrpt_product_price"><?php esc_html_e( 'Price', 'subscription' ); ?></label>
								<div class="p2-input-wrap">
									<span class="p2-input-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
									<input type="text" id="subscrpt_product_price" name="subscrpt_product_price" class="wpsubs-input" autocomplete="off" style="padding-left:24px!important;" placeholder="0.00" value="<?php echo $product ? esc_attr( $product->get_price() ) : ( isset( $session_data['product_price'] ) ? esc_attr( $session_data['product_price'] ) : '' ); ?>">
								</div>
							</div>
							<div class="form-row">
								<label><?php esc_html_e( 'Billing every', 'subscription' ); ?></label>
								<div class="wpsubs-input-group p2-billing-group">
									<?php if ( $is_pro ) : ?>
									<input type="number" id="subscrpt_billing_per_visible" class="wpsubs-input p2-billing-per-input" autocomplete="off" min="1"
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
						<div class="p2-form-grid">
							<div class="form-row">
								<label for="subscrpt_trial_timing_per"><?php esc_html_e( 'Free trial', 'subscription' ); ?> <span class="p2-label-optional"><?php esc_html_e( 'Optional', 'subscription' ); ?></span></label>
								<div class="wpsubs-input-group p2-billing-group">
									<input type="number" id="subscrpt_trial_timing_per" name="subscrpt_trial_timing_per" class="wpsubs-input p2-billing-per-input" autocomplete="off" min="0" value="<?php echo isset( $session_data['trial_timing_per'] ) ? esc_attr( $session_data['trial_timing_per'] ) : '0'; ?>">
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
								<p class="p2-field-hint"><?php esc_html_e( 'Free period before the first charge. Leave 0 for none.', 'subscription' ); ?></p>
							</div>
							<div class="form-row <?php echo $is_pro ? '' : 'p2-field-pro-locked'; ?>">
								<label for="subscrpt_signup_fee">
									<?php esc_html_e( 'Sign-up fee', 'subscription' ); ?>
									<?php if ( ! $is_pro ) : ?>
										<span class="p2-pro-badge" title="<?php esc_attr_e( 'WPSubscription Pro required', 'subscription' ); ?>"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
									<?php else : ?>
										<span class="p2-label-optional"><?php esc_html_e( 'Optional', 'subscription' ); ?></span>
									<?php endif; ?>
								</label>
								<div class="p2-input-wrap">
									<span class="p2-input-prefix"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
									<input type="text" id="subscrpt_signup_fee" name="subscrpt_signup_fee" class="wpsubs-input" autocomplete="off" style="padding-left:24px!important;" placeholder="0.00"
										value="<?php echo $is_pro && isset( $session_data['signup_fee'] ) ? esc_attr( $session_data['signup_fee'] ) : ''; ?>"
										<?php echo $is_pro ? '' : 'disabled'; ?>>
								</div>
								<?php if ( ! $is_pro ) : ?>
								<p class="p2-field-hint"><?php esc_html_e( 'One-time charge at checkout. Upgrade to Pro to enable.', 'subscription' ); ?></p>
								<?php else : ?>
								<p class="p2-field-hint"><?php esc_html_e( 'One-time charge at checkout.', 'subscription' ); ?></p>
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
					<div class="p2-preview-col">
						<p class="p2-preview-col-label"><?php esc_html_e( 'Shop Preview', 'subscription' ); ?></p>
						<div class="p1-preview-card">
							<div class="p1-preview-img">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
									<line x1="3" y1="6" x2="21" y2="6"></line>
									<path d="M16 10a4 4 0 0 1-8 0"></path>
								</svg>
								<div class="p1-preview-img__dots"><span></span><span></span><span></span></div>
							</div>
							<div class="p1-preview-body">
								<p class="p1-preview-name" id="p2-preview-name"><?php echo $product ? esc_html( $product->get_name() ) : esc_html__( 'Your product', 'subscription' ); ?></p>
								<p class="p1-preview-price"><span id="p2-preview-price"><?php echo $product ? esc_html( $product->get_price() ) : '0.00'; ?></span>$ <span>/ <span id="p2-preview-period"><?php echo isset( $session_data['billing_period'] ) ? esc_html( $session_data['billing_period'] ) : esc_html__( 'month', 'subscription' ); ?></span></span></p>
								<div class="p1-preview-btn"><?php esc_html_e( 'Sign up', 'subscription' ); ?></div>
							</div>
						</div>
						<p class="p2-preview-col-desc"><?php esc_html_e( 'This is how the product will appear on your shop page. Each purchase creates a subscription you\'ll manage from the Subscriptions list.', 'subscription' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<!-- Nav outside card -->
		<div class="p2-nav">
			<button type="button" id="subscrpt-btn-back" class="p2-nav-back">
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
	<div class="wizard-section <?php echo 3 === $wizard_page ? 'active' : ''; ?>" id="subscrpt-section-3">
		<div class="wizard-card p3-card">

			<!-- Success icon -->
			<div class="p3-success-icon">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<polyline points="20 6 9 17 4 12"></polyline>
				</svg>
			</div>

			<h1 class="p3-heading"><?php esc_html_e( 'Your product is live.', 'subscription' ); ?></h1>
			<p class="p3-subtext"><?php esc_html_e( "It's published to your shop now. When a customer buys it, a subscription is created for them automatically — you'll see those in the Subscriptions list.", 'subscription' ); ?></p>

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
			<div class="p3-product-card">
				<div class="p3-product-card__header">
					<span><?php esc_html_e( 'PRODUCT', 'subscription' ); ?></span>
					<span><?php esc_html_e( 'STATUS', 'subscription' ); ?></span>
					<span><?php esc_html_e( 'PRICE', 'subscription' ); ?></span>
				</div>
				<div class="p3-product-card__row">
					<div class="p3-product-card__product">
						<div class="p3-product-avatar"><?php echo esc_html( $p3_initials ); ?></div>
						<div>
							<p class="p3-product-name"><?php echo esc_html( $product->get_name() ); ?></p>
							<p class="p3-product-meta">
								<?php
								/* translators: %d: product ID */
								echo esc_html( sprintf( __( 'Product #%d', 'subscription' ), $product->get_id() ) );
								?>
								&middot; <?php esc_html_e( 'Subscription', 'subscription' ); ?>
							</p>
						</div>
					</div>
					<div class="p3-product-card__status">
						<span class="p3-status-badge">
							<span class="p3-status-badge__dot"></span>
							<?php esc_html_e( 'Published', 'subscription' ); ?>
						</span>
						<p class="p3-status-sub"><?php esc_html_e( 'Live in your shop', 'subscription' ); ?></p>
					</div>
					<div class="p3-product-card__price">
						<span class="p3-price-amount"><?php echo wp_kses_post( wc_price( $p3_price ) ); ?></span>
						<span class="p3-price-period">/ <?php echo esc_html( $p3_period_label ); ?></span>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<!-- What now -->
			<p class="p3-what-now-label"><?php esc_html_e( 'WHAT NOW?', 'subscription' ); ?></p>

			<div class="p3-action-rows">
				<?php if ( $product ) : ?>
				<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>" target="_blank" rel="noopener" class="p3-action-row">
					<div class="p3-action-row__icon">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
					</div>
					<div class="p3-action-row__content">
						<p class="p3-action-row__title"><?php esc_html_e( 'View product in shop', 'subscription' ); ?></p>
						<p class="p3-action-row__desc"><?php esc_html_e( 'See how customers will subscribe to it.', 'subscription' ); ?></p>
					</div>
					<svg class="p3-action-row__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
				</a>
				<?php endif; ?>

				<button type="button" id="subscrpt-btn-add-another" class="p3-action-row">
					<div class="p3-action-row__icon">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
					</div>
					<div class="p3-action-row__content">
						<p class="p3-action-row__title"><?php esc_html_e( 'Add another product', 'subscription' ); ?></p>
						<p class="p3-action-row__desc"><?php esc_html_e( 'Create another subscription product now.', 'subscription' ); ?></p>
					</div>
					<svg class="p3-action-row__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
				</button>

			</div>

			<p class="p3-help-text">
				<?php esc_html_e( 'Need help? Check the', 'subscription' ); ?>
				<a href="https://wpsubscription.co/docs/" target="_blank" rel="noopener" class="p3-help-link"><?php esc_html_e( 'setup guide', 'subscription' ); ?></a>
				<?php esc_html_e( 'or', 'subscription' ); ?>
				<a href="https://wpsubscription.co/support/" target="_blank" rel="noopener" class="p3-help-link"><?php esc_html_e( 'contact support', 'subscription' ); ?></a>.
			</p>
		</div>

		<!-- Bottom nav -->
		<div class="p3-nav">
			<button type="button" id="subscrpt-btn-start-over" class="wpsubs-btn wpsubs-btn--outline">
				<?php esc_html_e( 'Start over', 'subscription' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="wpsubs-btn wpsubs-btn--primary">
				<?php esc_html_e( 'Go to products', 'subscription' ); ?>
			</a>
		</div>
	</div>
</div>
