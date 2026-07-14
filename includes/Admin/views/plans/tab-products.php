<?php
/**
 * Plan detail - Products tab.
 *
 * Lists the products attached to this plan group. In free this is read-only:
 * products are connected from their own editor. With Pro active, an "Add
 * Products" bulk picker is available here.
 *
 * @var array $plan Plan (PlanPresenter shape).
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pro_active = function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated();
?>
<div style="padding-top:8px;">

	<?php if ( empty( $plan['products'] ) ) : ?>
		<div class="wpsubs-empty">
			<div class="wpsubs-empty__icon">📦</div>
			<h3 class="wpsubs-empty__title"><?php esc_html_e( 'No products connected', 'subscription' ); ?></h3>
			<?php if ( $pro_active ) : ?>
				<p class="wpsubs-empty__desc"><?php esc_html_e( 'Add products to this plan group and set their prices here, or connect a product from its own Subscription tab.', 'subscription' ); ?></p>
				<button type="button" class="wpsubs-btn wpsubs-btn--primary" style="margin-top:20px;" data-wpsubs-modal-open="subscrpt-add-product">
					<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:1;"></span>
					<?php esc_html_e( 'Add Products', 'subscription' ); ?>
				</button>
			<?php else : ?>
				<p class="wpsubs-empty__desc"><?php esc_html_e( 'Products are connected from their own editor: open a product, go to its Subscription tab, pick this plan group, and set the price. Bulk-add from here is available with WPSubscription Pro.', 'subscription' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="wpsubs-btn wpsubs-btn--primary" style="margin-top:20px;">
					<?php esc_html_e( 'Go to Products', 'subscription' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php else : ?>

		<?php foreach ( $plan['products'] as $product ) : ?>
			<div class="wpsubs-table-card" style="margin-bottom:16px;">
				<div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--wpsubs-border,#e5e7eb);">
					<strong><?php echo esc_html( $product['name'] ); ?></strong>
					<span class="wpsubs-badge">
						<?php
						/* translators: %s: product base price. */
						echo esc_html( sprintf( __( 'Base: %s', 'subscription' ), $product['base_price'] ) );
						?>
					</span>
					<span class="wpsubs-toolbar__spacer"></span>
					<?php if ( ! empty( $product['edit_url'] ) ) : ?>
						<a href="<?php echo esc_url( $product['edit_url'] ); ?>" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" target="_blank" rel="noopener">
							<?php esc_html_e( 'Edit product', 'subscription' ); ?>
						</a>
					<?php endif; ?>
				</div>
				<table class="wpsubs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Selling Plan', 'subscription' ); ?></th>
							<th><?php esc_html_e( 'Regular Price', 'subscription' ); ?></th>
							<th><?php esc_html_e( 'Offer Price', 'subscription' ); ?></th>
							<th><?php esc_html_e( 'Status', 'subscription' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $product['rows'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['term'] ); ?></td>
								<td><?php echo esc_html( $row['regular'] ); ?></td>
								<td><?php echo esc_html( $row['offer'] ); ?></td>
								<td>
									<?php if ( ! empty( $row['exclude'] ) ) : ?>
										<span class="wpsubs-badge wpsubs-badge--draft"><?php esc_html_e( 'Excluded', 'subscription' ); ?></span>
									<?php else : ?>
										<span class="wpsubs-badge wpsubs-badge--active"><?php esc_html_e( 'Active', 'subscription' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>

		<?php if ( $pro_active ) : ?>
			<button type="button" class="wpsubs-btn wpsubs-btn--outline" style="margin-top:16px;" data-wpsubs-modal-open="subscrpt-add-product">
				<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:1;"></span>
				<?php esc_html_e( 'Add Products', 'subscription' ); ?>
			</button>
		<?php endif; ?>

	<?php endif; ?>

</div>

<?php
if ( $pro_active ) {
	require __DIR__ . '/modal-add-product.php';
}
