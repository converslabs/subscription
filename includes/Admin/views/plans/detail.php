<?php
/**
 * Plan detail view. Uses the shared WPSubsTabs component (client-side tabs —
 * no page reload): Selling Plans | Products (read-only).
 *
 * @var array  $plan     Plan (PlanPresenter shape).
 * @var string $list_url Base list URL.
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SpringDevs\Subscription\Admin\Plans;
?>
<div class="wp-subscription-admin-content list-page">
	<div data-plan-type="<?php echo esc_attr( $plan['type'] ); ?>" data-plan-id="<?php echo esc_attr( $plan['id'] ); ?>">

		<!-- Page header -->
		<div style="margin-bottom:20px;">
			<a href="<?php echo esc_url( $list_url ); ?>" style="display:inline-flex;align-items:center;gap:4px;font-size:13px;color:var(--wpsubs-text-muted);text-decoration:none;">
				<span class="dashicons dashicons-arrow-left-alt2" style="font-size:16px;width:16px;height:16px;"></span><?php esc_html_e( 'Plans', 'subscription' ); ?>
			</a>
			<div style="display:flex;align-items:center;gap:10px;margin:8px 0 6px;">
				<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0;line-height:1.2;"><?php echo esc_html( $plan['name'] ); ?></h1>
				<span class="wpsubs-badge"><?php echo esc_html( Plans::type_label( $plan['type'] ) ); ?></span>
				<span class="wpsubs-toolbar__spacer"></span>
				<div class="wpsubs-row-actions" data-subscrpt-dropdown>
					<button type="button" class="wpsubs-row-actions__trigger" aria-label="<?php esc_attr_e( 'Actions', 'subscription' ); ?>">&hellip;</button>
					<div class="wpsubs-dropdown" hidden>
						<a href="#" class="wpsubs-dropdown__item wpsubs-dropdown__item--danger" data-subscrpt-delete-plan="<?php echo esc_attr( $plan['id'] ); ?>"><?php esc_html_e( 'Delete', 'subscription' ); ?></a>
					</div>
				</div>
			</div>
			<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0 0 12px;line-height:1.5;"><?php esc_html_e( 'Add selling plans (billing terms) and see the products connected to this plan.', 'subscription' ); ?></p>
			<div style="border-top:1px dashed #d0d3d7;"></div>
		</div>

		<div class="wpsubs-tabs">
			<div class="wpsubs-tabs__list" role="tablist">
				<button class="wpsubs-tabs__tab" role="tab" id="subscrpt-tab-selling" aria-controls="subscrpt-panel-selling" aria-selected="true">
					<?php esc_html_e( 'Selling Plans', 'subscription' ); ?>
				</button>
				<button class="wpsubs-tabs__tab" role="tab" id="subscrpt-tab-products" aria-controls="subscrpt-panel-products" aria-selected="false">
					<?php esc_html_e( 'Products', 'subscription' ); ?>
				</button>
			</div>

			<div class="wpsubs-tab-panel" role="tabpanel" id="subscrpt-panel-selling" aria-labelledby="subscrpt-tab-selling">
				<?php require __DIR__ . '/tab-selling-plans.php'; ?>
			</div>

			<div class="wpsubs-tab-panel" role="tabpanel" id="subscrpt-panel-products" aria-labelledby="subscrpt-tab-products" hidden>
				<?php require __DIR__ . '/tab-products.php'; ?>
			</div>
		</div>

	</div>
</div>

<?php require __DIR__ . '/modal-term.php'; ?>
