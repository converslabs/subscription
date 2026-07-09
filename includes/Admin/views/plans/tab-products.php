<?php
/**
 * Plan detail — Products tab (READ-ONLY in free).
 *
 * Lists the products attached to this plan and the price each pays per selling
 * plan. Attaching a product and editing its price is done from the product
 * editor's Subscription tab — not here. (Plan-side attach / inline edit is Pro.)
 *
 * @var array $plan Plan (PlanPresenter shape).
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div style="padding-top:8px;">

	<?php if ( empty( $plan['products'] ) ) : ?>
		<div class="wpsubs-empty">
			<span class="wpsubs-empty__icon dashicons dashicons-products"></span>
			<h3 class="wpsubs-empty__title"><?php esc_html_e( 'No products connected', 'subscription' ); ?></h3>
			<p class="wpsubs-empty__desc"><?php esc_html_e( 'Open a product and use its Subscription tab to connect it to this plan and set its price.', 'subscription' ); ?></p>
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

	<?php endif; ?>

</div>
