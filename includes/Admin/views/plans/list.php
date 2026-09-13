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

	<?php
	wpsubs_render_page_header(
		array(
			'title'       => __( 'Plans', 'subscription' ),
			'description' => __( 'Set up a plan and connect it to your products. Manage billing from one place.', 'subscription' ),
			'actions'     => sprintf(
				'<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-wpsubs-modal-open="subscrpt-create-plan"><span class="dashicons dashicons-plus-alt2" style="font-size:14px;width:14px;height:14px;line-height:1;"></span>%s</button>',
				esc_html__( 'Create Plan', 'subscription' )
			),
		)
	);
	?>

	<?php if ( empty( $plans ) ) : ?>

		<div class="wpsubs-empty">
			<div class="wpsubs-empty__icon">🗂️</div>
			<h3 class="wpsubs-empty__title"><?php esc_html_e( 'No plans yet', 'subscription' ); ?></h3>
			<p class="wpsubs-empty__desc"><?php esc_html_e( 'Set up a plan and connect it to your products. Manage billing from one place.', 'subscription' ); ?></p>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" style="margin-top:20px;" data-wpsubs-modal-open="subscrpt-create-plan">
				<?php esc_html_e( 'Create your first plan', 'subscription' ); ?>
			</button>
		</div>

	<?php else : ?>

		<div data-subscrpt-browse data-per-page="20">

			<!-- Search + per-page + bulk actions -->
			<div class="wpsubs-toolbar" style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
				<div class="wpsubs-search">
					<div class="wpsubs-input-wrap wpsubs-input-wrap--icon-l">
						<svg class="wpsubs-input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
						<input type="search" class="wpsubs-input" placeholder="<?php esc_attr_e( 'Search plans...', 'subscription' ); ?>" data-subscrpt-browse-search />
					</div>
				</div>
				<span style="flex:1 1 auto;"></span>
				<?php
				wpsubs_render_per_page_select(
					array(
						'name'  => 'subscrpt_groups_per_page',
						'value' => '20',
						'attrs' => array( 'data-subscrpt-browse-perpage' => '1' ),
					)
				);
				wpsubs_render_adv_select(
					array(
						'name'        => 'subscrpt_bulk_action',
						'placeholder' => __( 'Bulk actions', 'subscription' ),
						'options'     => array(
							array(
								'value'  => 'delete',
								'label'  => __( 'Delete', 'subscription' ),
								'danger' => true,
							),
						),
						'attrs'       => array( 'data-subscrpt-bulk-select' => '1' ),
					)
				);
				?>
			</div>

			<div class="wpsubs-table-card">
				<table class="wpsubs-table">
					<thead>
						<tr>
							<th class="wpsubs-col--check"><input type="checkbox" class="wpsubs-checkbox" data-subscrpt-select-all aria-label="<?php esc_attr_e( 'Select all plans', 'subscription' ); ?>" /></th>
							<th><?php esc_html_e( 'Plan', 'subscription' ); ?></th>
							<th><?php esc_html_e( 'Type', 'subscription' ); ?></th>
							<th><?php esc_html_e( 'Durations', 'subscription' ); ?></th>
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
							<tr class="wpsubs-plan-row" data-subscrpt-browse-item data-name="<?php echo esc_attr( strtolower( $plan['name'] ) ); ?>" data-plan-id="<?php echo esc_attr( $plan['id'] ); ?>" data-href="<?php echo esc_url( $detail_url ); ?>" style="cursor:pointer;">
							<td class="wpsubs-col--check">
								<input type="checkbox" class="wpsubs-checkbox wpsubs-row-check" value="<?php echo esc_attr( $plan['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: plan name. */ __( 'Select %s', 'subscription' ), $plan['name'] ) ); ?>" />
							</td>
							<td>
								<?php
								$subscrpt_full  = $plan['name'];
								$subscrpt_short = subscrpt_truncate_text( $subscrpt_full );
								?>
								<a href="<?php echo esc_url( $detail_url ); ?>" style="display:inline-flex;align-items:center;vertical-align:middle;gap:8px;font-weight:600;text-decoration:none;color:var(--wpsubs-text);"<?php echo $subscrpt_short !== $subscrpt_full ? ' title="' . esc_attr( $subscrpt_full ) . '"' : ''; ?>>
									<span class="dashicons <?php echo esc_attr( Plans::type_icon( $plan['type'] ) ); ?>" style="flex:0 0 auto;color:var(--wpsubs-text-subtle);"></span>
									<?php echo esc_html( $subscrpt_short ); ?>
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
									/* translators: %d: number of durations. */
									? esc_html( sprintf( _n( '%d duration', '%d durations', $term_count, 'subscription' ), $term_count ) )
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
								<?php // The title already opens the plan; the row keeps only the action that is not on screen. plans.js confirms and deletes on data-subscrpt-delete-plan. ?>
								<button type="button" class="wpsubs-icon-action wpsubs-icon-action--danger" data-subscrpt-delete-plan="<?php echo esc_attr( $plan['id'] ); ?>" title="<?php esc_attr_e( 'Delete plan', 'subscription' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: plan name. */ __( 'Delete %s', 'subscription' ), $plan['name'] ) ); ?>">
									<span class="dashicons dashicons-trash" aria-hidden="true"></span>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

			<p data-subscrpt-browse-empty style="display:none;padding:20px 4px;color:var(--wpsubs-text-subtle);font-size:13px;text-align:center;"><?php esc_html_e( 'No plans match your search.', 'subscription' ); ?></p>
			<div data-subscrpt-browse-pager style="margin-top:14px;"></div>

		</div>

	<?php endif; ?>

</div>

<?php require __DIR__ . '/modal-create.php'; ?>
