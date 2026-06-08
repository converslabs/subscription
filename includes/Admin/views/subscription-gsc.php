<?php
/**
 * Getting started card for the subscriptions list page.
 * Shown only when no product has '_subscrpt_enabled' meta.
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$transient_key        = 'subscrpt_has_enabled_product';
$has_subscrpt_product = get_transient( $transient_key );

if ( false === $has_subscrpt_product ) {
	$has_subscrpt_product = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- transient caches the result.
				array(
					'key'     => '_subscrpt_enabled',
					'compare' => 'EXISTS',
				),
			),
		)
	);
	// Store as a non-empty array or an empty string ([] can't be distinguished from false).
	set_transient( $transient_key, $has_subscrpt_product ? $has_subscrpt_product : 'none', DAY_IN_SECONDS );
}

if ( 'none' !== $has_subscrpt_product && ! empty( $has_subscrpt_product ) ) {
	return;
}
?>
<style>
/* Card shell */
.subscrpt-gsc {
	background: #fff;
	border: 1px solid var(--wpsubs-border);
	border-radius: 12px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
	padding: 24px 28px 22px;
	margin-bottom: 24px;
}

/* Dismiss × */
.subscrpt-gsc__dismiss {
	background: none;
	border: none;
	cursor: pointer;
	color: var(--wpsubs-text-subtle);
	font-size: 18px;
	line-height: 1;
	padding: 4px 6px;
	transition: color 0.15s;
	flex-shrink: 0;
	font-family: inherit;
	float: right;
	margin: -8px -8px 0 0;
}
.subscrpt-gsc__dismiss:hover {
	color: var(--wpsubs-text);
}

/* Heading — override WP admin h2 margin/font */
.subscrpt-gsc h2.subscrpt-gsc__heading {
	font-size: 20px !important;
	font-weight: 700 !important;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
	color: #1f2937 !important;
	margin: 0 0 8px !important;
	padding: 0 !important;
	line-height: 1.25 !important;
	border: none !important;
}

/* Subtitle — override WP admin p margin */
.subscrpt-gsc p.subscrpt-gsc__desc {
	font-size: 13.5px !important;
	color: var(--wpsubs-text-muted) !important;
	line-height: 1.6 !important;
	margin: 0 0 20px !important;
	max-width: 600px;
}

/* Three-column step grid */
.subscrpt-gsc__steps {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	gap: 12px;
	margin-bottom: 22px;
}
.subscrpt-gsc__step {
	background: var(--wpsubs-surface-muted);
	border: 1px solid var(--wpsubs-border);
	border-radius: 8px;
	padding: 14px 16px 16px;
}
.subscrpt-gsc__step-top {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	margin-bottom: 12px;
}
.subscrpt-gsc__step-icon {
	width: 32px;
	height: 32px;
	border-radius: 6px;
	background: #fff;
	border: 1px solid var(--wpsubs-border);
	display: flex;
	align-items: center;
	justify-content: center;
	color: var(--wpsubs-brand);
	flex-shrink: 0;
}
/* Reset global span[class*="subscrpt-"] (status.css) background/padding. */
.subscrpt-gsc span.subscrpt-gsc__step-num {
	background: none !important;
	color: var(--wpsubs-text-subtle) !important;
	font-size: 12px !important;
	font-weight: 500 !important;
	padding: 0 !important;
	border-radius: 0 !important;
	text-transform: none !important;
	line-height: 1 !important;
}

/* Step title + desc — override WP admin p margin */
.subscrpt-gsc__step p.subscrpt-gsc__step-title {
	font-size: 13.5px !important;
	font-weight: 700 !important;
	color: var(--wpsubs-text) !important;
	margin: 0 0 4px !important;
	line-height: 1.3 !important;
}
.subscrpt-gsc__step p.subscrpt-gsc__step-desc {
	font-size: 12.5px !important;
	color: var(--wpsubs-text-muted) !important;
	line-height: 1.5 !important;
	margin: 0 !important;
}

/* Footer */
.subscrpt-gsc__footer {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
}
.subscrpt-gsc__footer-left {
	display: flex;
	align-items: center;
	gap: 14px;
}
.subscrpt-gsc__skip {
	font-size: 13px !important;
	color: var(--wpsubs-text-muted) !important;
	background: none !important;
	border: none !important;
	cursor: pointer;
	padding: 0 !important;
	text-decoration: none !important;
	box-shadow: none !important;
	font-family: inherit !important;
	line-height: 1 !important;
	transition: color 0.15s;
}
.subscrpt-gsc__skip:hover {
	color: var(--wpsubs-text) !important;
}
/* Reset global span[class*="subscrpt-"] (status.css) background/padding. */
.subscrpt-gsc span.subscrpt-gsc__progress {
	background: none !important;
	color: var(--wpsubs-text-subtle) !important;
	font-size: 12.5px !important;
	font-weight: 400 !important;
	padding: 0 !important;
	border-radius: 0 !important;
	text-transform: none !important;
	line-height: 1 !important;
}
</style>

<div class="subscrpt-gsc" id="subscrpt-gsc-card">
	<button type="button" class="subscrpt-gsc__dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'subscription' ); ?>" onclick="document.getElementById('subscrpt-gsc-card').style.display='none';">&times;</button>

	<h2 class="subscrpt-gsc__heading"><?php esc_html_e( 'Welcome to WPSubscription', 'subscription' ); ?></h2>
	<p class="subscrpt-gsc__desc"><?php esc_html_e( "You're three short steps from your first recurring product. We'll walk you through creating it, setting the billing cadence, and going live.", 'subscription' ); ?></p>

	<div class="subscrpt-gsc__steps">
		<div class="subscrpt-gsc__step">
			<div class="subscrpt-gsc__step-top">
				<div class="subscrpt-gsc__step-icon">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
				</div>
				<span class="subscrpt-gsc__step-num">01</span>
			</div>
			<p class="subscrpt-gsc__step-title"><?php esc_html_e( 'Create a product', 'subscription' ); ?></p>
			<p class="subscrpt-gsc__step-desc"><?php esc_html_e( 'Start fresh or convert an existing product into a subscription.', 'subscription' ); ?></p>
		</div>
		<div class="subscrpt-gsc__step">
			<div class="subscrpt-gsc__step-top">
				<div class="subscrpt-gsc__step-icon">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
				</div>
				<span class="subscrpt-gsc__step-num">02</span>
			</div>
			<p class="subscrpt-gsc__step-title"><?php esc_html_e( 'Set the cadence', 'subscription' ); ?></p>
			<p class="subscrpt-gsc__step-desc"><?php esc_html_e( 'Choose billing period, length, free trial and any signup fee.', 'subscription' ); ?></p>
		</div>
		<div class="subscrpt-gsc__step">
			<div class="subscrpt-gsc__step-top">
				<div class="subscrpt-gsc__step-icon">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
				</div>
				<span class="subscrpt-gsc__step-num">03</span>
			</div>
			<p class="subscrpt-gsc__step-title"><?php esc_html_e( 'Go live', 'subscription' ); ?></p>
			<p class="subscrpt-gsc__step-desc"><?php esc_html_e( 'Publish and your subscription is ready for customers to buy.', 'subscription' ); ?></p>
		</div>
	</div>

	<div class="subscrpt-gsc__footer">
		<div class="subscrpt-gsc__footer-left">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription-onboarding' ) ); ?>" class="wpsubs-btn wpsubs-btn--primary">
				<?php esc_html_e( 'Create my first subscription', 'subscription' ); ?> &rsaquo;
			</a>
			<button type="button" class="subscrpt-gsc__skip" onclick="document.getElementById('subscrpt-gsc-card').style.display='none';">
				<?php esc_html_e( 'Skip for now', 'subscription' ); ?>
			</button>
		</div>
		<span class="subscrpt-gsc__progress"><?php esc_html_e( 'about 2 min', 'subscription' ); ?></span>
	</div>
</div>
