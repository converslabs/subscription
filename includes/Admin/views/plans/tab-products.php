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
// Products can only be attached once the plan group has at least one term.
$has_terms = ! empty( $plan['terms'] );
?>
<div style="padding-top:8px;">

	<?php if ( empty( $plan['products'] ) ) : ?>
		<div class="wpsubs-empty">
			<div class="wpsubs-empty__icon">📦</div>
			<h3 class="wpsubs-empty__title"><?php esc_html_e( 'No products connected', 'subscription' ); ?></h3>
			<?php if ( $pro_active && ! $has_terms ) : ?>
				<p class="wpsubs-empty__desc"><?php esc_html_e( 'Add at least one plan on the Plans tab before attaching products.', 'subscription' ); ?></p>
			<?php elseif ( $pro_active ) : ?>
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

		<?php
		/**
		 * Render one selling-plan × price table for a set of relation rows.
		 * Each editable cell has a read view + a hidden input, toggled by the
		 * card's Edit/Save buttons (JS). The One-time price column is shown only
		 * when the card's one-time purchase toggle is on; an empty price for a
		 * plan means no one-time charge for that plan.
		 *
		 * @param array $rows        Price rows (term / regular / offer / …).
		 * @param bool  $one_time_on Whether the one-time column starts visible.
		 */
		$subscrpt_render_rows = function ( $rows, $one_time_on ) use ( $pro_active ) {
			$ot_col  = $one_time_on ? '' : 'display:none;';
			$ot_div  = $ot_col . 'width:1px;padding:0 6px;text-align:center;';
			$div_bar = '<span style="display:inline-block;vertical-align:middle;width:2px;height:16px;border-radius:1px;background:var(--wpsubs-border,#e5e7eb);"></span>';
			?>
			<table class="wpsubs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Selling Plan', 'subscription' ); ?></th>
						<th>
							<?php esc_html_e( 'Regular Price', 'subscription' ); ?>
							<?php echo wp_kses_post( wpsubs_render_hint( __( 'The recurring price charged each billing cycle.', 'subscription' ) ) ); ?>
						</th>
						<th>
							<?php esc_html_e( 'Offer Price', 'subscription' ); ?>
							<?php echo wp_kses_post( wpsubs_render_hint( __( 'A discounted recurring price shown in place of the regular price. Leave empty for no discount.', 'subscription' ) ) ); ?>
						</th>
						<th class="subscrpt-onetime-col" style="<?php echo esc_attr( $ot_div ); ?>"><?php echo $div_bar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
						<th class="subscrpt-onetime-col" style="<?php echo esc_attr( $ot_col ); ?>">
							<?php esc_html_e( 'One-time price', 'subscription' ); ?>
							<?php echo wp_kses_post( wpsubs_render_hint( __( 'Price for a single, non-recurring purchase of this plan. Empty means one-time purchase is not offered for that plan.', 'subscription' ) ) ); ?>
						</th>
						<th class="subscrpt-onetime-col" style="<?php echo esc_attr( $ot_col ); ?>">
							<?php esc_html_e( 'One-time offer', 'subscription' ); ?>
							<?php echo wp_kses_post( wpsubs_render_hint( __( 'A discounted one-time price shown in place of the one-time price. Leave empty for no discount.', 'subscription' ) ) ); ?>
						</th>
						<th><?php esc_html_e( 'Status', 'subscription' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr data-subscrpt-relation="<?php echo esc_attr( $row['relation_id'] ); ?>">
							<td><?php echo esc_html( $row['term'] ); ?></td>
							<td>
								<span class="subscrpt-pe-view"><?php echo esc_html( $row['regular'] ); ?></span>
								<?php if ( $pro_active ) : ?>
									<input type="number" min="0" step="0.01" class="wpsubs-input subscrpt-pe-edit" data-field="regular_price" value="<?php echo esc_attr( $row['regular_raw'] ); ?>" placeholder="0.00" style="display:none;max-width:110px;" />
								<?php endif; ?>
							</td>
							<td>
								<span class="subscrpt-pe-view"><?php echo esc_html( $row['offer'] ); ?></span>
								<?php if ( $pro_active ) : ?>
									<input type="number" min="0" step="0.01" class="wpsubs-input subscrpt-pe-edit" data-field="sale_price" value="<?php echo esc_attr( $row['offer_raw'] ); ?>" placeholder="0.00" style="display:none;max-width:110px;" />
								<?php endif; ?>
							</td>
							<td class="subscrpt-onetime-col" style="<?php echo esc_attr( $ot_div ); ?>"><?php echo $div_bar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td class="subscrpt-onetime-col" style="<?php echo esc_attr( $ot_col ); ?>">
								<span class="subscrpt-pe-view">
									<?php if ( '' !== $row['one_time_disp'] ) : ?>
										<?php echo esc_html( $row['one_time_disp'] ); ?>
									<?php else : ?>
										<span style="color:var(--wpsubs-text-subtle);">&mdash;</span>
									<?php endif; ?>
								</span>
								<?php if ( $pro_active ) : ?>
									<input type="number" min="0" step="0.01" class="wpsubs-input subscrpt-pe-edit" data-field="one_time_price" value="<?php echo esc_attr( $row['one_time_price'] ); ?>" placeholder="<?php esc_attr_e( 'No charge', 'subscription' ); ?>" style="display:none;max-width:110px;" />
								<?php endif; ?>
							</td>
							<td class="subscrpt-onetime-col" style="<?php echo esc_attr( $ot_col ); ?>">
								<span class="subscrpt-pe-view">
									<?php if ( '' !== $row['one_time_offer_disp'] ) : ?>
										<?php echo esc_html( $row['one_time_offer_disp'] ); ?>
									<?php else : ?>
										<span style="color:var(--wpsubs-text-subtle);">&mdash;</span>
									<?php endif; ?>
								</span>
								<?php if ( $pro_active ) : ?>
									<input type="number" min="0" step="0.01" class="wpsubs-input subscrpt-pe-edit" data-field="one_time_offer" value="<?php echo esc_attr( $row['one_time_offer'] ); ?>" placeholder="<?php esc_attr_e( 'No offer', 'subscription' ); ?>" style="display:none;max-width:110px;" />
								<?php endif; ?>
							</td>
							<td>
								<span class="subscrpt-pe-view">
									<?php if ( ! empty( $row['exclude'] ) ) : ?>
										<span class="wpsubs-badge wpsubs-badge--draft"><?php esc_html_e( 'Disabled', 'subscription' ); ?></span>
									<?php else : ?>
										<span class="wpsubs-badge wpsubs-badge--active"><?php esc_html_e( 'Enabled', 'subscription' ); ?></span>
									<?php endif; ?>
								</span>
								<?php if ( $pro_active ) : ?>
									<label class="wpsubs-settings-toggle-label subscrpt-pe-edit" style="display:none;align-items:center;" title="<?php esc_attr_e( 'Enable this plan for the product', 'subscription' ); ?>">
										<input type="checkbox" class="wpsubs-toggle" data-field="enabled" <?php checked( empty( $row['exclude'] ) ); ?> />
										<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
									</label>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		};

		/**
		 * Render the price-card header controls (Pro only): the one-time purchase
		 * toggle + Edit / Cancel / Save buttons. JS toggles the card into edit
		 * mode, shows/hides the one-time column, and saves via REST.
		 *
		 * @param bool $one_time_on Whether one-time purchase is enabled for this card.
		 */
		$subscrpt_price_actions = function ( $one_time_on ) use ( $pro_active ) {
			if ( ! $pro_active ) {
				return;
			}
			?>
			<label class="wpsubs-settings-toggle-label" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--wpsubs-text-muted);cursor:pointer;">
				<input type="checkbox" class="wpsubs-toggle" data-subscrpt-onetime-toggle <?php checked( $one_time_on ); ?> disabled />
				<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
				<span><?php esc_html_e( 'One-time purchase', 'subscription' ); ?></span>
			</label>
			<span aria-hidden="true" style="align-self:center;width:2px;height:16px;background:var(--wpsubs-border,#e5e7eb);margin:0 4px;"></span>
			<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-edit-prices>
				<span class="dashicons dashicons-edit" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
				<?php esc_html_e( 'Edit prices', 'subscription' ); ?>
			</button>
			<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-cancel-prices style="display:none;"><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
			<button type="button" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" data-subscrpt-save-prices style="display:none;"><?php esc_html_e( 'Save', 'subscription' ); ?></button>
			<?php
		};
	?>

		<div data-subscrpt-browse data-per-page="10">
			<div class="wpsubs-toolbar" style="margin-bottom:14px;">
				<div class="wpsubs-search">
					<div class="wpsubs-input-wrap wpsubs-input-wrap--icon-l">
						<svg class="wpsubs-input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
						<input type="search" class="wpsubs-input" placeholder="<?php esc_attr_e( 'Search products...', 'subscription' ); ?>" data-subscrpt-browse-search />
					</div>
				</div>

				<?php
				wpsubs_render_adv_select(
					array(
						'name'    => 'subscrpt_products_per_page',
						'value'   => '10',
						'options' => array(
							array(
								'value' => '10',
								'label' => __( '10 / page', 'subscription' ),
							),
							array(
								'value' => '25',
								'label' => __( '25 / page', 'subscription' ),
							),
							array(
								'value' => '50',
								'label' => __( '50 / page', 'subscription' ),
							),
							array(
								'value' => '100',
								'label' => __( '100 / page', 'subscription' ),
							),
						),
						'attrs'   => array( 'data-subscrpt-browse-perpage' => '1' ),
					)
				);
				?>

				<div class="wpsubs-toolbar__spacer"></div>

				<?php if ( $pro_active && $has_terms ) : ?>
					<button type="button" class="wpsubs-btn wpsubs-btn--outline" data-wpsubs-modal-open="subscrpt-add-product">
						<span class="dashicons dashicons-edit" style="font-size:16px;width:16px;height:16px;line-height:1;"></span>
						<?php esc_html_e( 'Manage Products', 'subscription' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div class="wpsubs-accordion" data-multi="1" data-subscrpt-product-list>
			<?php foreach ( $plan['products'] as $product ) : ?>
				<?php $subscrpt_panel_id = 'subscrpt-prod-' . (int) $product['id']; ?>
				<div class="wpsubs-accordion__item" data-subscrpt-browse-item data-name="<?php echo esc_attr( strtolower( $product['name'] ) ); ?>" data-pid="<?php echo esc_attr( $product['id'] ); ?>">
					<div style="display:flex;align-items:stretch;background:var(--wpsubs-surface-muted,#f9fafb);">
						<button type="button" class="wpsubs-accordion__header wpsubs-accordion__header--chevron-start" style="flex:1 1 auto;min-width:0;background:transparent;" aria-controls="<?php echo esc_attr( $subscrpt_panel_id ); ?>" aria-expanded="false">
							<span style="display:flex;align-items:center;gap:10px;min-width:0;">
								<span style="flex:0 0 auto;width:44px;height:44px;border-radius:6px;background:var(--wpsubs-surface,#fff);border:1px solid var(--wpsubs-border,#e5e7eb);overflow:hidden;display:flex;align-items:center;justify-content:center;">
									<?php if ( ! empty( $product['image'] ) ) : ?>
										<img src="<?php echo esc_url( $product['image'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover;display:block;" />
									<?php else : ?>
										<span class="dashicons dashicons-format-image" style="font-size:22px;width:22px;height:22px;color:var(--wpsubs-text-subtle);"></span>
									<?php endif; ?>
								</span>
								<span style="display:flex;flex-direction:column;min-width:0;line-height:1.35;">
								<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $product['name'] ); ?></span>
								<span style="font-size:12px;font-weight:400;color:var(--wpsubs-text-muted);white-space:nowrap;">
									<?php
									/* translators: %d: product ID. */
									$subscrpt_desc = sprintf( __( 'ID: %d', 'subscription' ), (int) $product['id'] );
									if ( ! empty( $product['is_variable'] ) ) {
										$subscrpt_var_count = count( $product['variations'] );
										/* translators: %d: number of variations. */
										$subscrpt_desc .= ' &middot; ' . sprintf( _n( '%d variation', '%d variations', $subscrpt_var_count, 'subscription' ), $subscrpt_var_count );
									}
									echo wp_kses_post( $subscrpt_desc );
									?>
								</span>
								</span>
							</span>
						</button>
						<div style="display:flex;align-items:center;gap:8px;padding:0 12px 0 4px;">
							<?php if ( ! empty( $product['edit_url'] ) ) : ?>
								<a href="<?php echo esc_url( $product['edit_url'] ); ?>" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" target="_blank" rel="noopener">
									<?php esc_html_e( 'Edit product', 'subscription' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $pro_active ) : ?>
								<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm wpsubs-btn--danger" data-subscrpt-remove-product="<?php echo esc_attr( $product['id'] ); ?>">
									<?php esc_html_e( 'Remove', 'subscription' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>

					<div class="wpsubs-accordion__panel" id="<?php echo esc_attr( $subscrpt_panel_id ); ?>" style="padding:14px 16px;background:var(--wpsubs-surface-muted,#f9fafb);" hidden>
						<?php if ( ! empty( $product['is_variable'] ) ) : ?>
							<div style="display:flex;flex-direction:column;gap:14px;">
								<?php foreach ( $product['variations'] as $variation ) : ?>
									<?php $subscrpt_ot = ! empty( $variation['rows'] ) && ! empty( $variation['rows'][0]['one_time'] ); ?>
									<div data-subscrpt-price-card style="border:1px solid var(--wpsubs-border,#e5e7eb);border-radius:8px;background:var(--wpsubs-surface,#fff);">
										<div style="display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid var(--wpsubs-border,#e5e7eb);">
											<span class="dashicons dashicons-image-filter" style="flex:0 0 auto;font-size:16px;width:16px;height:16px;color:var(--wpsubs-text-subtle);"></span>
											<strong style="font-size:13px;color:var(--wpsubs-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $variation['name'] ); ?></strong>
											<span class="wpsubs-toolbar__spacer"></span>
											<?php $subscrpt_price_actions( $subscrpt_ot ); ?>
											<?php if ( $pro_active ) : ?>
												<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm wpsubs-btn--danger" data-subscrpt-remove-variation data-oid="<?php echo esc_attr( $product['id'] ); ?>" data-vid="<?php echo esc_attr( $variation['vid'] ); ?>">
													<?php esc_html_e( 'Remove', 'subscription' ); ?>
												</button>
											<?php endif; ?>
										</div>
										<?php $subscrpt_render_rows( $variation['rows'], $subscrpt_ot ); ?>
									</div>
								<?php endforeach; ?>
								<?php if ( ! empty( $product['rows'] ) ) : ?>
									<?php $subscrpt_ot = ! empty( $product['rows'][0]['one_time'] ); ?>
									<div data-subscrpt-price-card style="border:1px solid var(--wpsubs-border,#e5e7eb);border-radius:8px;background:var(--wpsubs-surface,#fff);">
										<?php if ( $pro_active ) : ?>
											<div style="display:flex;align-items:center;gap:8px;padding:11px 14px;border-bottom:1px solid var(--wpsubs-border,#e5e7eb);">
												<span class="wpsubs-toolbar__spacer"></span>
												<?php $subscrpt_price_actions( $subscrpt_ot ); ?>
											</div>
										<?php endif; ?>
										<?php $subscrpt_render_rows( $product['rows'], $subscrpt_ot ); ?>
									</div>
								<?php endif; ?>
							</div>
						<?php else : ?>
							<?php $subscrpt_ot = ! empty( $product['rows'] ) && ! empty( $product['rows'][0]['one_time'] ); ?>
							<div data-subscrpt-price-card style="border:1px solid var(--wpsubs-border,#e5e7eb);border-radius:8px;background:var(--wpsubs-surface,#fff);">
								<?php if ( $pro_active ) : ?>
									<div style="display:flex;align-items:center;gap:8px;padding:11px 14px;border-bottom:1px solid var(--wpsubs-border,#e5e7eb);">
										<strong style="font-size:13px;color:var(--wpsubs-text);"><?php esc_html_e( 'Pricing', 'subscription' ); ?></strong>
										<span class="wpsubs-toolbar__spacer"></span>
										<?php $subscrpt_price_actions( $subscrpt_ot ); ?>
									</div>
								<?php endif; ?>
								<?php $subscrpt_render_rows( $product['rows'], $subscrpt_ot ); ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
			</div>

			<p data-subscrpt-browse-empty style="display:none;padding:20px 4px;color:var(--wpsubs-text-subtle);font-size:13px;text-align:center;">
				<?php esc_html_e( 'No products match your search.', 'subscription' ); ?>
			</p>

			<div data-subscrpt-browse-pager style="margin-top:14px;"></div>
		</div>

	<?php endif; ?>

</div>

<?php
if ( $pro_active ) {
	require __DIR__ . '/modal-add-product.php';
}
