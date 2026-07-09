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
?>
<div class="wp-subscription-admin-content list-page">

	<!-- Page header -->
	<div style="margin-bottom:20px;">
		<div style="display:flex;align-items:center;gap:10px;margin:0 0 6px;">
			<h1 style="font-size:1.375rem;font-weight:700;color:var(--wpsubs-text);margin:0;line-height:1.2;"><?php esc_html_e( 'Plan Groups', 'subscription' ); ?></h1>
			<span class="wpsubs-toolbar__spacer"></span>
			<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-open="subscrpt-create-plan">
				<span class="dashicons dashicons-plus-alt2" style="font-size:14px;width:14px;height:14px;line-height:1;"></span>
				<?php esc_html_e( 'Create Plan Group', 'subscription' ); ?>
			</button>
		</div>
		<p style="font-size:13px;color:var(--wpsubs-text-muted);margin:0 0 12px;line-height:1.5;"><?php esc_html_e( 'Set up a plan group and connect it to your products. Manage billing from one place.', 'subscription' ); ?></p>
		<div style="border-top:1px dashed #d0d3d7;"></div>
	</div>

	<?php if ( empty( $plans ) ) : ?>

		<div class="wpsubs-empty">
			<div class="wpsubs-empty__icon">🗂️</div>
			<h3 class="wpsubs-empty__title"><?php esc_html_e( 'No plan groups yet', 'subscription' ); ?></h3>
			<p class="wpsubs-empty__desc"><?php esc_html_e( 'Set up a plan group and connect it to your products. Manage billing from one place.', 'subscription' ); ?></p>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" style="margin-top:20px;" data-wpsubs-modal-open="subscrpt-create-plan">
				<?php esc_html_e( 'Create your first plan group', 'subscription' ); ?>
			</button>
		</div>

	<?php else : ?>

		<div class="wpsubs-table-card">
			<table class="wpsubs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plan Group', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Type', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Selling Plans', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Products', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Last Edited', 'subscription' ); ?></th>
						<th style="width:48px;"></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $plans as $plan ) :
						$detail_url    = add_query_arg(
							array(
								'view' => 'detail',
								'plan' => $plan['id'],
							),
							$list_url
						);
						$term_count    = count( $plan['terms'] );
						$product_count = count( $plan['products'] );
						?>
						<tr class="wpsubs-plan-row" data-subscrpt-name="<?php echo esc_attr( strtolower( $plan['name'] ) ); ?>" data-plan-id="<?php echo esc_attr( $plan['id'] ); ?>">
							<td>
								<a href="<?php echo esc_url( $detail_url ); ?>" style="display:inline-flex;align-items:center;gap:8px;font-weight:600;text-decoration:none;color:var(--wpsubs-text);">
									<span class="dashicons dashicons-screenoptions" style="color:var(--wpsubs-text-subtle);"></span>
									<?php echo esc_html( $plan['name'] ); ?>
								</a>
								<?php if ( 'draft' === $plan['status'] ) : ?>
									<span class="wpsubs-badge wpsubs-badge--draft" style="margin-left:4px;"><?php esc_html_e( 'Draft', 'subscription' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<span class="wpsubs-badge wpsubs-badge--neutral"><?php echo esc_html( Plans::type_label( $plan['type'] ) ); ?></span>
							</td>
							<td>
								<?php
								echo $term_count > 0
									/* translators: %d: number of selling plans. */
									? esc_html( sprintf( _n( '%d plan', '%d plans', $term_count, 'subscription' ), $term_count ) )
									: '<span style="color:var(--wpsubs-text-subtle);">' . esc_html__( 'None', 'subscription' ) . '</span>';
								?>
							</td>
							<td>
								<?php
								echo $product_count > 0
									/* translators: %d: number of connected products. */
									? esc_html( sprintf( _n( '%d product', '%d products', $product_count, 'subscription' ), $product_count ) )
									: '<span style="color:var(--wpsubs-text-subtle);">' . esc_html__( 'None', 'subscription' ) . '</span>';
								?>
							</td>
							<td><?php echo esc_html( $plan['edited'] ); ?></td>
							<td>
								<div class="wpsubs-row-actions" data-subscrpt-dropdown>
									<button type="button" class="wpsubs-row-actions__trigger" aria-label="<?php esc_attr_e( 'Actions', 'subscription' ); ?>">···</button>
									<div class="wpsubs-dropdown" hidden>
										<a href="<?php echo esc_url( $detail_url ); ?>" class="wpsubs-dropdown__item"><?php esc_html_e( 'View / Edit', 'subscription' ); ?></a>
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
