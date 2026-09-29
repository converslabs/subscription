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

$subscrpt_bp_currency = function_exists( 'get_woocommerce_currency_symbol' )
	? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
	: '';

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
		return '<span class="subscrpt-bp-price subscrpt-bp-price--empty">&mdash;</span>';
	}

	$min = min( $vals );
	$max = max( $vals );

	if ( $min === $max ) {
		return '<span class="subscrpt-bp-price">' . esc_html( \SpringDevs\Subscription\Admin\PlanPresenter::money( $min ) ) . '</span>';
	}

	return '<span class="subscrpt-bp-price">' . esc_html( \SpringDevs\Subscription\Admin\PlanPresenter::money( $min ) ) . ' &ndash; ' . esc_html( \SpringDevs\Subscription\Admin\PlanPresenter::money( $max ) ) . '</span>';
};

/**
 * Product / variation thumbnail cell.
 *
 * @param string $image Image URL, or '' for the placeholder icon.
 * @param string $icon  Dashicon class used when there is no image.
 *
 * @return string Escaped HTML.
 */
$subscrpt_bp_thumb = function ( $image, $icon = 'dashicons-format-image' ) {
	if ( $image ) {
		return '<span class="subscrpt-bp-thumb"><img src="' . esc_url( $image ) . '" alt="" /></span>';
	}
	return '<span class="subscrpt-bp-thumb"><span class="dashicons ' . esc_attr( $icon ) . '"></span></span>';
};
?>
<div class="wpsubs-modal" id="subscrpt-bulk-price" hidden data-subscrpt-bulk-price>
	<style>
		#subscrpt-bulk-price .subscrpt-bp-section { margin-bottom: 18px; }
		#subscrpt-bulk-price .subscrpt-bp-section:last-child { margin-bottom: 0; }
		#subscrpt-bulk-price .subscrpt-bp-legend { display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--wpsubs-text-subtle); margin-bottom: 8px; }
		#subscrpt-bulk-price .subscrpt-bp-pricecard { display: flex; gap: 12px; padding: 14px; background: var(--wpsubs-surface-muted, #f9fafb); border: 1px solid var(--wpsubs-border); border-radius: 8px; }
		#subscrpt-bulk-price .subscrpt-bp-field { flex: 1; min-width: 0; font-size: 13px; color: var(--wpsubs-text); }
		#subscrpt-bulk-price .subscrpt-bp-field__label { display: block; margin-bottom: 5px; font-weight: 600; }
		#subscrpt-bulk-price .subscrpt-bp-field__hint { color: var(--wpsubs-text-subtle); font-weight: 400; }
		#subscrpt-bulk-price .subscrpt-bp-money { position: relative; }
		#subscrpt-bulk-price .subscrpt-bp-money__sym { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 13px; color: var(--wpsubs-text-subtle); pointer-events: none; }
		#subscrpt-bulk-price .subscrpt-bp-money .wpsubs-input { width: 100%; padding-left: 24px !important; }
		#subscrpt-bulk-price .subscrpt-bp-cols { display: grid; grid-template-columns: 1fr 1.25fr; gap: 18px; }
		#subscrpt-bulk-price .subscrpt-bp-colhead { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
		#subscrpt-bulk-price .subscrpt-bp-colhead strong { font-size: 13px; color: var(--wpsubs-text); }
		#subscrpt-bulk-price .subscrpt-bp-count { font-size: 11px; color: var(--wpsubs-text-muted); }
		#subscrpt-bulk-price .subscrpt-bp-all { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--wpsubs-text-muted); cursor: pointer; }
		#subscrpt-bulk-price .subscrpt-bp-list { list-style: none; margin: 0; padding: 5px; min-height: 160px; max-height: min(340px, 44vh); overflow-y: auto; display: flex; flex-direction: column; gap: 1px; border: 1px solid var(--wpsubs-border); border-radius: 8px; background: var(--wpsubs-surface, #fff); }
		#subscrpt-bulk-price .subscrpt-bp-list ul { list-style: none; margin: 0; padding: 0; }
		#subscrpt-bulk-price .subscrpt-bp-row { display: flex; align-items: center; gap: 10px; min-width: 0; padding: 7px 8px; border-radius: 6px; cursor: pointer; font-size: 13px; }
		#subscrpt-bulk-price .subscrpt-bp-row:hover { background: var(--wpsubs-surface-muted, #f4f5f7); }
		#subscrpt-bulk-price .subscrpt-bp-row--child { padding-left: 38px; }
		#subscrpt-bulk-price .subscrpt-bp-thumb { flex: 0 0 auto; width: 30px; height: 30px; border-radius: 5px; background: var(--wpsubs-surface, #fff); border: 1px solid var(--wpsubs-border); overflow: hidden; display: flex; align-items: center; justify-content: center; }
		#subscrpt-bulk-price .subscrpt-bp-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
		#subscrpt-bulk-price .subscrpt-bp-thumb .dashicons { font-size: 16px; width: 16px; height: 16px; color: var(--wpsubs-text-subtle); }
		#subscrpt-bulk-price .subscrpt-bp-body { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.3; }
		#subscrpt-bulk-price .subscrpt-bp-name { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		#subscrpt-bulk-price .subscrpt-bp-name--strong { font-weight: 600; }
		#subscrpt-bulk-price .subscrpt-bp-sub { font-size: 11px; color: var(--wpsubs-text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		#subscrpt-bulk-price .subscrpt-bp-price { flex: 0 0 auto; color: var(--wpsubs-text-muted); font-size: 12px; white-space: nowrap; }
		#subscrpt-bulk-price .subscrpt-bp-price--empty { color: var(--wpsubs-text-subtle); }
		#subscrpt-bulk-price .subscrpt-bp-draft { flex: 0 0 auto; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: var(--wpsubs-text-subtle); background: var(--wpsubs-surface-muted, #f1f2f4); border: 1px solid var(--wpsubs-border); border-radius: 4px; padding: 1px 6px; }
		@media (max-width: 560px) {
			#subscrpt-bulk-price .subscrpt-bp-cols { grid-template-columns: 1fr; }
		}
	</style>
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

			<div class="subscrpt-bp-section">
				<span class="subscrpt-bp-legend"><?php esc_html_e( 'New price', 'subscription' ); ?></span>
				<div class="subscrpt-bp-pricecard">
					<label class="subscrpt-bp-field">
						<span class="subscrpt-bp-field__label"><?php esc_html_e( 'Regular price', 'subscription' ); ?></span>
						<span class="subscrpt-bp-money">
							<?php if ( '' !== $subscrpt_bp_currency ) : ?>
								<span class="subscrpt-bp-money__sym"><?php echo esc_html( $subscrpt_bp_currency ); ?></span>
							<?php endif; ?>
							<input type="number" min="0" step="0.01" class="wpsubs-input" data-subscrpt-bulk-regular placeholder="0.00" />
						</span>
					</label>
					<label class="subscrpt-bp-field">
						<span class="subscrpt-bp-field__label"><?php esc_html_e( 'Sale price', 'subscription' ); ?> <span class="subscrpt-bp-field__hint">(<?php esc_html_e( 'optional', 'subscription' ); ?>)</span></span>
						<span class="subscrpt-bp-money">
							<?php if ( '' !== $subscrpt_bp_currency ) : ?>
								<span class="subscrpt-bp-money__sym"><?php echo esc_html( $subscrpt_bp_currency ); ?></span>
							<?php endif; ?>
							<input type="number" min="0" step="0.01" class="wpsubs-input" data-subscrpt-bulk-sale placeholder="<?php esc_attr_e( 'No sale', 'subscription' ); ?>" />
						</span>
					</label>
				</div>
			</div>

			<div class="subscrpt-bp-section">
				<span class="subscrpt-bp-legend"><?php esc_html_e( 'Apply to', 'subscription' ); ?></span>
				<div class="subscrpt-bp-cols">

					<div>
						<div class="subscrpt-bp-colhead">
							<strong><?php esc_html_e( 'Durations', 'subscription' ); ?></strong>
							<span class="subscrpt-bp-count" data-subscrpt-bulk-count="term"></span>
							<label class="subscrpt-bp-all">
								<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-toggle-all="term" />
								<?php esc_html_e( 'Select all', 'subscription' ); ?>
							</label>
						</div>
						<ul class="subscrpt-bp-list">
							<?php foreach ( $plan['terms'] as $subscrpt_bp_term ) : ?>
								<li>
									<label class="subscrpt-bp-row">
										<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-term value="<?php echo esc_attr( $subscrpt_bp_term['id'] ); ?>" />
										<span class="subscrpt-bp-body">
											<span class="subscrpt-bp-name"><?php echo esc_html( $subscrpt_bp_term['name'] ); ?></span>
											<?php if ( ! empty( $subscrpt_bp_term['breakdown'] ) ) : ?>
												<span class="subscrpt-bp-sub"><?php echo esc_html( $subscrpt_bp_term['breakdown'] ); ?></span>
											<?php endif; ?>
										</span>
										<?php if ( ! empty( $subscrpt_bp_term['status'] ) && 'active' !== $subscrpt_bp_term['status'] ) : ?>
											<span class="subscrpt-bp-draft"><?php esc_html_e( 'Draft', 'subscription' ); ?></span>
										<?php endif; ?>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>

					<div>
						<div class="subscrpt-bp-colhead">
							<strong><?php esc_html_e( 'Products', 'subscription' ); ?></strong>
							<span class="subscrpt-bp-count" data-subscrpt-bulk-count="product"></span>
							<label class="subscrpt-bp-all">
								<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-toggle-all="product" />
								<?php esc_html_e( 'Select all', 'subscription' ); ?>
							</label>
						</div>
						<ul class="subscrpt-bp-list">
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
										<label class="subscrpt-bp-row">
											<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-parent />
											<?php echo wp_kses_post( $subscrpt_bp_thumb( $subscrpt_bp_product['image'] ?? '' ) ); ?>
											<span class="subscrpt-bp-name subscrpt-bp-name--strong" style="flex:1;"><?php echo esc_html( $subscrpt_bp_product['name'] ); ?></span>
											<?php echo wp_kses_post( $subscrpt_bp_price( $subscrpt_bp_all_rows ) ); ?>
										</label>
										<ul>
											<?php foreach ( $subscrpt_bp_product['variations'] as $subscrpt_bp_var ) : ?>
												<li>
													<label class="subscrpt-bp-row subscrpt-bp-row--child">
														<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-product data-oid="<?php echo esc_attr( $subscrpt_bp_product['id'] ); ?>" data-vid="<?php echo esc_attr( $subscrpt_bp_var['vid'] ); ?>" />
														<span class="subscrpt-bp-name" style="flex:1;"><?php echo esc_html( $subscrpt_bp_var['name'] ); ?></span>
														<?php echo wp_kses_post( $subscrpt_bp_price( $subscrpt_bp_var['rows'] ?? array() ) ); ?>
													</label>
												</li>
											<?php endforeach; ?>
										</ul>
									</li>
								<?php else : ?>
									<li>
										<label class="subscrpt-bp-row">
											<input type="checkbox" class="wpsubs-checkbox" data-subscrpt-bulk-product data-oid="<?php echo esc_attr( $subscrpt_bp_product['id'] ); ?>" data-vid="0" />
											<?php echo wp_kses_post( $subscrpt_bp_thumb( $subscrpt_bp_product['image'] ?? '' ) ); ?>
											<span class="subscrpt-bp-name" style="flex:1;"><?php echo esc_html( $subscrpt_bp_product['name'] ); ?></span>
											<?php echo wp_kses_post( $subscrpt_bp_price( $subscrpt_bp_product['rows'] ?? array() ) ); ?>
										</label>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</div>

				</div>
			</div>
		</div>
		<div class="wpsubs-modal__footer">
			<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-close><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-subscrpt-bulk-apply><?php esc_html_e( 'Apply price', 'subscription' ); ?></button>
		</div>
	</div>
</div>
