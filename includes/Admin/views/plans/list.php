<?php
/**
 * Plans list view. Built on the shared wpsubs-* components.
 *
 * @var array  $plans    Plans keyed by id (PlanPresenter shape).
 * @var string $list_url Base list URL.
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SpringDevs\Subscription\Admin\Plans;

$plan_count = count( $plans );
?>
<div class="wp-subscription-admin-content list-page">

	<!-- Page header -->
	<div style="margin-bottom:20px;">
		<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0 0 6px;line-height:1.2;"><?php esc_html_e( 'Plans', 'subscription' ); ?></h1>
		<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0 0 12px;line-height:1.5;"><?php esc_html_e( 'Create a plan once and connect it to your products — manage the billing from one place.', 'subscription' ); ?></p>
		<div style="border-top:1px dashed #d0d3d7;"></div>
	</div>

	<div class="wpsubs-toolbar">
		<span style="font-size:13px;color:var(--wpsubs-text-muted);">
			<?php
			/* translators: %d: number of plans. */
			echo esc_html( sprintf( _n( '%d plan', '%d plans', $plan_count, 'subscription' ), $plan_count ) );
			?>
		</span>
		<span class="wpsubs-toolbar__spacer"></span>
		<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-wpsubs-modal-open="subscrpt-create-plan">
			<?php esc_html_e( 'Create Plan', 'subscription' ); ?>
		</button>
	</div>

	<?php if ( empty( $plans ) ) : ?>

		<div class="wpsubs-empty">
			<span class="wpsubs-empty__icon dashicons dashicons-screenoptions"></span>
			<h3 class="wpsubs-empty__title"><?php esc_html_e( 'No plans yet', 'subscription' ); ?></h3>
			<p class="wpsubs-empty__desc"><?php esc_html_e( 'Create a plan once and connect it to your products — manage the billing from one place.', 'subscription' ); ?></p>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-wpsubs-modal-open="subscrpt-create-plan">
				<?php esc_html_e( 'Create your first plan', 'subscription' ); ?>
			</button>
		</div>

	<?php else : ?>

		<div class="wpsubs-input-wrap wpsubs-input-wrap--icon-l" style="max-width:320px;margin-bottom:16px;">
			<span class="wpsubs-input-icon dashicons dashicons-search"></span>
			<input type="search" class="wpsubs-input" placeholder="<?php esc_attr_e( 'Search plans…', 'subscription' ); ?>" data-subscrpt-filter="wpsubs-plan-row" />
		</div>

		<div class="wpsubs-table-card">
			<table class="wpsubs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plan', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Type', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Selling Plans', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Last Edited', 'subscription' ); ?></th>
						<th style="width:48px;"></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $plans as $plan ) :
						$detail_url = add_query_arg(
							array(
								'view' => 'detail',
								'plan' => $plan['id'],
							),
							$list_url
						);
						?>
						<tr class="wpsubs-plan-row" data-subscrpt-name="<?php echo esc_attr( strtolower( $plan['name'] ) ); ?>" data-plan-id="<?php echo esc_attr( $plan['id'] ); ?>">
							<td>
								<a href="<?php echo esc_url( $detail_url ); ?>" style="font-weight:600;text-decoration:none;"><?php echo esc_html( $plan['name'] ); ?></a>
								<?php if ( 'draft' === $plan['status'] ) : ?>
									<span class="wpsubs-badge wpsubs-badge--draft"><?php esc_html_e( 'Draft', 'subscription' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<span class="wpsubs-badge"><?php echo esc_html( Plans::type_label( $plan['type'] ) ); ?></span>
							</td>
							<td><?php echo esc_html( count( $plan['terms'] ) ); ?></td>
							<td><?php echo esc_html( $plan['edited'] ); ?></td>
							<td>
								<div class="wpsubs-row-actions" data-subscrpt-dropdown>
									<button type="button" class="wpsubs-row-actions__trigger" aria-label="<?php esc_attr_e( 'Actions', 'subscription' ); ?>">&hellip;</button>
									<div class="wpsubs-dropdown" hidden>
										<a href="<?php echo esc_url( $detail_url ); ?>" class="wpsubs-dropdown__item"><?php esc_html_e( 'Edit', 'subscription' ); ?></a>
										<div class="wpsubs-dropdown__divider"></div>
										<a href="#" class="wpsubs-dropdown__item wpsubs-dropdown__item--danger" data-subscrpt-delete-plan="<?php echo esc_attr( $plan['id'] ); ?>"><?php esc_html_e( 'Delete', 'subscription' ); ?></a>
									</div>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

	<?php endif; ?>

</div>

<?php require __DIR__ . '/modal-create.php'; ?>
