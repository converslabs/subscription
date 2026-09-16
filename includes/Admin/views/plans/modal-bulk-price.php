<?php
/**
 * Bulk price modal. Sets one regular (and optional sale) price on the chosen
 * connected products across the chosen durations in one write. Only products
 * already on the plan are listed - bulk pricing never attaches new products.
 *
 * @var array $plan Plan (PlanPresenter shape: products[], terms[]).
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$subscrpt_bp_list = 'list-style:none;margin:0;padding:6px;max-height:min(320px, 42vh);overflow-y:auto;display:flex;flex-direction:column;gap:1px;border:1px solid var(--wpsubs-border);border-radius:8px;';
$subscrpt_bp_row  = 'display:flex;align-items:center;gap:10px;min-width:0;padding:7px 8px;border-radius:6px;cursor:pointer;font-size:13px;';

/**
 * Current price of a product/variation shown next to its name: a single price
 * when every duration matches, a min-max range otherwise, a dash when unset.
 *
 * @param array $rows Price rows (each may carry regular_raw).
 *
 * @return string Escaped HTML.
 */
$subscrpt_bp_price = function ( $rows ) {
	$vals = array();
	foreach ( (array) $rows as $subscrpt_bp_r ) {
		$subscrpt_bp_raw = $subscrpt_bp_r['regular_raw'] ?? '';
		if ( '' !== $subscrpt_bp_raw ) {
			$vals[] = (float) $subscrpt_bp_raw;
		}
	}

	if ( empty( $vals ) ) {
		return '<span style="color:var(--wpsubs-text-subtle);">&mdash;</span>';
	}

	$min = min( $vals );
	$max = max( $vals );

	if ( $min === $max ) {
		return esc_html( \SpringDevs\Subscription\Admin\PlanPresenter::money( $min ) );
	}

	return esc_html( \SpringDevs\Subscription\Admin\PlanPresenter::money( $min ) ) . ' &ndash; ' . esc_html( \SpringDevs\Subscription\Admin\PlanPresenter::money( $max ) );
};
?>
<div class="wpsubs-modal" id="subscrpt-bulk-price" hidden data-subscrpt-bulk-price>
	<div class="wpsubs-modal__backdrop" data-wpsubs-modal-close></div>
	<div class="wpsubs-modal__dialog" style="width:min(760px, calc(100vw - 40px));">
		<div class="wpsubs-modal__head" style="align-items:flex-start;">
			<div>
				<h2 class="wpsubs-modal__title"><?php esc_html_e( 'Quick price update', 'subscription' ); ?></h2>
				<p style="margin:5px 0 0;color:var(--wpsubs-text-muted);font-size:13px;line-height:1.5;font-weight:400;">
					<?php esc_html_e( 'Set one price on the selected products across the selected durations. This overwrites their current prices.', 'subscription' ); ?>
				</p>
			</div>
			<button type="button" class="wpsubs-modal__close" data-wpsubs-modal-close aria-label="<?php esc_attr_e( 'Close', 'subscription' ); ?>">&times;</button>
		</div>
		<div class="wpsubs-modal__body">

			<div style="display:flex;gap:12px;padding:14px;margin-bottom:16px;background:var(--wpsubs-surface-muted,#f9fafb);border:1px solid var(--wpsubs-border);border-radius:8px;">
				<label style="flex:1;font-size:13px;color:var(--wpsubs-text);">
					<span style="display:block;margin-bottom:5px;font-weight:600;"><?php esc_html_e( 'Regular price', 'subscription' ); ?></span>
					<input type="number" min="0" step="0.01" class="wpsubs-input" data-subscrpt-bulk-regular placeholder="0.00" style="width:100%;" />
				</label>
				<label style="flex:1;font-size:13px;color:var(--wpsubs-text);">
					<span style="display:block;margin-bottom:5px;font-weight:600;"><?php esc_html_e( 'Sale price', 'subscription' ); ?> <span style="color:var(--wpsubs-text-subtle);font-weight:400;">(<?php esc_html_e( 'optional', 'subscription' ); ?>)</span></span>
					<input type="number" min="0" step="0.01" class="wpsubs-input" data-subscrpt-bulk-sale placeholder="<?php esc_attr_e( 'No sale', 'subscription' ); ?>" style="width:100%;" />
				</label>
			</div>

			<div style="display:flex;gap:18px;flex-wrap:wrap;">

				<div style="flex:1 1 250px;min-width:0;">
					<div style="display:flex;align-items:center;margin-bottom:8px;">
						<strong style="font-size:13px;color:var(--wpsubs-text);"><?php esc_html_e( 'Durations', 'subscription' ); ?></strong>
						<label style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--wpsubs-text-muted);cursor:pointer;">
							<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-toggle-all="term" />
							<?php esc_html_e( 'Select all', 'subscription' ); ?>
						</label>
					</div>
					<ul style="<?php echo esc_attr( $subscrpt_bp_list ); ?>">
						<?php foreach ( $plan['terms'] as $subscrpt_bp_term ) : ?>
							<li>
								<label style="<?php echo esc_attr( $subscrpt_bp_row ); ?>">
									<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-term value="<?php echo esc_attr( $subscrpt_bp_term['id'] ); ?>" />
									<span style="flex:1;min-width:0;display:flex;flex-direction:column;line-height:1.3;">
										<span style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $subscrpt_bp_term['name'] ); ?></span>
										<?php if ( ! empty( $subscrpt_bp_term['breakdown'] ) ) : ?>
											<span style="font-size:11px;color:var(--wpsubs-text-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $subscrpt_bp_term['breakdown'] ); ?></span>
										<?php endif; ?>
									</span>
									<?php if ( ! empty( $subscrpt_bp_term['status'] ) && 'active' !== $subscrpt_bp_term['status'] ) : ?>
										<span style="flex:0 0 auto;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:var(--wpsubs-text-subtle);background:var(--wpsubs-surface-muted,#f1f2f4);border:1px solid var(--wpsubs-border);border-radius:4px;padding:1px 6px;"><?php esc_html_e( 'Draft', 'subscription' ); ?></span>
									<?php endif; ?>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div style="flex:1 1 320px;min-width:0;">
					<div style="display:flex;align-items:center;margin-bottom:8px;">
						<strong style="font-size:13px;color:var(--wpsubs-text);"><?php esc_html_e( 'Products', 'subscription' ); ?></strong>
						<label style="margin-left:auto;display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--wpsubs-text-muted);cursor:pointer;">
							<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-toggle-all="product" />
							<?php esc_html_e( 'Select all', 'subscription' ); ?>
						</label>
					</div>
					<ul style="<?php echo esc_attr( $subscrpt_bp_list ); ?>">
						<?php foreach ( $plan['products'] as $subscrpt_bp_product ) : ?>
							<?php if ( ! empty( $subscrpt_bp_product['is_variable'] ) ) : ?>
								<?php
								// Whole-product price range, from every variation's rows.
								$subscrpt_bp_all_rows = array();
								foreach ( $subscrpt_bp_product['variations'] as $subscrpt_bp_v ) {
									foreach ( (array) ( $subscrpt_bp_v['rows'] ?? array() ) as $subscrpt_bp_vr ) {
										$subscrpt_bp_all_rows[] = $subscrpt_bp_vr;
									}
								}
								?>
								<li data-subscrpt-bulk-group>
									<label style="<?php echo esc_attr( $subscrpt_bp_row ); ?>">
										<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-parent />
										<span style="flex:0 0 auto;width:30px;height:30px;border-radius:5px;background:var(--wpsubs-surface,#fff);border:1px solid var(--wpsubs-border);overflow:hidden;display:flex;align-items:center;justify-content:center;">
											<?php if ( ! empty( $subscrpt_bp_product['image'] ) ) : ?>
												<img src="<?php echo esc_url( $subscrpt_bp_product['image'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;" />
											<?php else : ?>
												<span class="dashicons dashicons-format-image" style="font-size:16px;width:16px;height:16px;color:var(--wpsubs-text-subtle);"></span>
											<?php endif; ?>
										</span>
										<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600;"><?php echo esc_html( $subscrpt_bp_product['name'] ); ?></span>
										<span style="flex:0 0 auto;color:var(--wpsubs-text-muted);font-size:12px;white-space:nowrap;"><?php echo wp_kses_post( $subscrpt_bp_price( $subscrpt_bp_all_rows ) ); ?></span>
									</label>
									<ul style="list-style:none;margin:0;padding:0;">
										<?php foreach ( $subscrpt_bp_product['variations'] as $subscrpt_bp_var ) : ?>
											<li>
												<label style="<?php echo esc_attr( $subscrpt_bp_row ); ?>padding-left:38px;">
													<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-product data-oid="<?php echo esc_attr( $subscrpt_bp_product['id'] ); ?>" data-vid="<?php echo esc_attr( $subscrpt_bp_var['vid'] ); ?>" />
													<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $subscrpt_bp_var['name'] ); ?></span>
													<span style="flex:0 0 auto;color:var(--wpsubs-text-muted);font-size:12px;white-space:nowrap;"><?php echo wp_kses_post( $subscrpt_bp_price( $subscrpt_bp_var['rows'] ?? array() ) ); ?></span>
												</label>
											</li>
										<?php endforeach; ?>
									</ul>
								</li>
							<?php else : ?>
								<li>
									<label style="<?php echo esc_attr( $subscrpt_bp_row ); ?>">
										<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-product data-oid="<?php echo esc_attr( $subscrpt_bp_product['id'] ); ?>" data-vid="0" />
										<span style="flex:0 0 auto;width:30px;height:30px;border-radius:5px;background:var(--wpsubs-surface,#fff);border:1px solid var(--wpsubs-border);overflow:hidden;display:flex;align-items:center;justify-content:center;">
											<?php if ( ! empty( $subscrpt_bp_product['image'] ) ) : ?>
												<img src="<?php echo esc_url( $subscrpt_bp_product['image'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;" />
											<?php else : ?>
												<span class="dashicons dashicons-format-image" style="font-size:16px;width:16px;height:16px;color:var(--wpsubs-text-subtle);"></span>
											<?php endif; ?>
										</span>
										<span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500;"><?php echo esc_html( $subscrpt_bp_product['name'] ); ?></span>
										<span style="flex:0 0 auto;color:var(--wpsubs-text-muted);font-size:12px;white-space:nowrap;"><?php echo wp_kses_post( $subscrpt_bp_price( $subscrpt_bp_product['rows'] ?? array() ) ); ?></span>
									</label>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</div>

			</div>
		</div>
		<div class="wpsubs-modal__footer">
			<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-close><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-bulk-apply><?php esc_html_e( 'Apply price', 'subscription' ); ?></button>
		</div>
	</div>
</div>
