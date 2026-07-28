<?php
/**
 * Product-editor plan view (free, simple products only).
 *
 * Renders, on the product editor Subscription tab, the plan(s) this product is
 * connected to plus a connect control to attach it to a Recurring plan group at
 * a per-product price. The classic `_subscrpt_*` meta inputs stay in the DOM
 * (hidden) behind a "Switch to classic settings" toggle. Writes go through the
 * wpsubscription/v1 REST API (see assets/js/admin/product-plans.js).
 *
 * @package SpringDevs\Subscription\Admin\Product
 */

namespace SpringDevs\Subscription\Admin\Product;

use SpringDevs\Subscription\Illuminate\Plans\PlanRepository;

/**
 * Product-editor plan view.
 */
class Plans {

	/**
	 * Register the mount point used when Pro renders the Subscription panel.
	 *
	 * With Pro active, Pro's `Admin/Product/Simple` renders the panel and fires
	 * `subscrpt_simple_plan_panel` at the top; free mounts its plan view there,
	 * toggling against Pro's classic fields (`.subscrpt-classic-fields`). With
	 * Pro inactive, free renders its own panel (see Admin\Product::subscription_forms).
	 */
	public function __construct() {
		add_action( 'subscrpt_simple_plan_panel', array( $this, 'render_mount' ) );
	}

	/**
	 * Mount the plan view inside Pro's Subscription panel.
	 *
	 * @param int $product_id Product being edited.
	 *
	 * @return void
	 */
	public function render_mount( $product_id ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product || ! $product->is_type( 'simple' ) ) {
			return;
		}
		?>
		<div data-subscrpt-product-plans data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" style="margin-bottom:16px;">
			<?php self::render_toolbar(); ?>
			<div data-subscrpt-plan-view>
				<?php self::render_plan_view( $product ); ?>
			</div>
		</div>
		<?php
		self::render_modals();
	}

	/**
	 * Render the Create-Plan-Group + Add-Selling-Plan modals once per page, so
	 * merchants can build a new plan group + plan without leaving the product
	 * editor. Pro-only: free's product editor is attach-only. The modals sit
	 * outside the [data-subscrpt-plan-view] region so an in-place refresh never
	 * duplicates them. Driven by the shared plan-forms.js module.
	 *
	 * @return void
	 */
	public static function render_modals() {
		if ( ! function_exists( 'subscrpt_pro_activated' ) || ! subscrpt_pro_activated() ) {
			return;
		}
		// modal-term.php expects a $plan (id + type); product-plans.js rewrites
		// the modal's group id/type before opening it for a freshly made group.
		$plan = array(
			'id'   => 0,
			'type' => 'recurring',
		);
		require __DIR__ . '/../views/plans/modal-create.php';
		require __DIR__ . '/../views/plans/modal-term.php';
	}

	/**
	 * Render the view toggle bar (plan view ⇄ classic settings).
	 *
	 * @return void
	 */
	public static function render_toolbar() {
		?>
		<div style="display:flex;align-items:center;gap:10px;margin:0 0 14px;">
			<strong style="margin-left:3px;font-size:13px;color:var(--wpsubs-text);"><?php esc_html_e( 'Subscription', 'subscription' ); ?></strong>
			<span style="flex:1 1 auto;"></span>
			<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-show-classic>
				<?php esc_html_e( 'Switch to classic settings', 'subscription' ); ?>
			</button>
			<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-show-plan style="display:none;">
				<span class="dashicons dashicons-arrow-left-alt2" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
				<?php esc_html_e( 'Back to plan view', 'subscription' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render the plan view: connected plan(s) + connect control.
	 *
	 * @param \WC_Product $product Simple product being edited.
	 *
	 * @return void
	 */
	public static function render_plan_view( $product ) {
		$connections = PlanRepository::get_product_connections( $product->get_id() );
		$connected   = self::group_connections( $connections );
		$available   = self::available_groups( array_keys( $connected ) );
		$currency    = function_exists( 'get_woocommerce_currency_symbol' )
			? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' )
			: '$';
		// Pro adds "create plan group / plan" shortcuts here; free is attach-only.
		$pro_active = function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated();
		?>
		<style>
			/* Neutralise WooCommerce's floated-label layout inside our plan view. */
			#sdevs_subscription_options [data-subscrpt-plan-view] label {
				float: none;
				width: auto;
				margin: 0;
			}
			/* Compact adv-select triggers on this tab only (not the modals, which
				are relocated to <body>). */
			#sdevs_subscription_options [data-subscrpt-plan-view] .wpsubs-adv-select__trigger {
				height: 30px;
				min-height: 30px;
				padding-top: 0;
				padding-bottom: 0;
			}
			/* Keep the group picker a fixed width and ellipsis a long plan name. */
			#sdevs_subscription_options [data-subscrpt-connect-group] {
				width: 240px;
				max-width: 100%;
			}
			#sdevs_subscription_options [data-subscrpt-connect-group] .wpsubs-adv-select__trigger {
				min-width: 0;
			}
			#sdevs_subscription_options [data-subscrpt-connect-group] .wpsubs-adv-select__label {
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}
		</style>

		<?php if ( ! empty( $connected ) ) : ?>
			<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:18px;">
				<?php
				foreach ( $connected as $subscrpt_gid => $group ) :
					$all_terms = PlanRepository::get_plans( $subscrpt_gid );
					?>
					<div class="wpsubs-table-card" data-subscrpt-plan-card data-group-id="<?php echo esc_attr( $subscrpt_gid ); ?>" style="padding:12px 14px;">
						<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
							<span style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:var(--wpsubs-radius,8px);background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand,#ff4d00);">
								<span class="dashicons dashicons-update"></span>
							</span>
							<div style="flex:1 1 auto;min-width:0;display:flex;align-items:center;gap:8px;">
								<strong style="font-size:13.5px;color:var(--wpsubs-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $group['title'] ); ?></strong>
								<span class="wpsubs-badge wpsubs-badge--active" style="font-weight:500;"><?php esc_html_e( 'Connected', 'subscription' ); ?></span>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription-plans&view=detail&plan=' . (int) $subscrpt_gid ) ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Manage this plan group', 'subscription' ); ?>" aria-label="<?php esc_attr_e( 'Manage this plan group', 'subscription' ); ?>" style="flex:0 0 auto;display:inline-flex;align-items:center;color:var(--wpsubs-text-subtle);text-decoration:none;">
									<span class="dashicons dashicons-external" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
								</a>
							</div>
							<div style="flex:0 0 auto;display:flex;align-items:center;gap:8px;">
								<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-edit-prices>
									<span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;line-height:1;"></span>
									<?php esc_html_e( 'Edit plans', 'subscription' ); ?>
								</button>
								<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-subscrpt-cancel-prices style="display:none;"><?php esc_html_e( 'Cancel', 'subscription' ); ?></button>
								<button type="button" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" data-subscrpt-save-prices style="display:none;"><?php esc_html_e( 'Save', 'subscription' ); ?></button>
								<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm wpsubs-btn--danger" data-subscrpt-detach data-relation-ids="<?php echo esc_attr( implode( ',', $group['relation_ids'] ) ); ?>">
									<?php esc_html_e( 'Detach', 'subscription' ); ?>
								</button>
							</div>
						</div>

						<!-- Read view: offered plans as chips. -->
						<div class="subscrpt-pe-view" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:10px;">
							<?php
							foreach ( $group['terms'] as $term ) :
								$subscrpt_off = ! empty( $term['excluded'] );
								?>
								<span style="display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:20px;background:var(--wpsubs-surface-muted,#f9fafb);border:1px solid var(--wpsubs-border,#e5e7eb);font-size:11.5px;color:var(--wpsubs-text-muted);<?php echo $subscrpt_off ? 'opacity:0.55;' : ''; ?>">
									<?php echo esc_html( $term['title'] ); ?>
									<?php if ( '' !== $term['price'] ) : ?>
										<span style="color:var(--wpsubs-text);font-weight:600;"><?php echo esc_html( $currency . number_format_i18n( (float) $term['price'], 2 ) ); ?></span>
									<?php endif; ?>
									<?php if ( $subscrpt_off ) : ?>
										<span style="font-style:italic;">(<?php esc_html_e( 'off', 'subscription' ); ?>)</span>
									<?php endif; ?>
								</span>
							<?php endforeach; ?>
						</div>

						<!-- Edit view: full price table per term (mirrors the Plans page). -->
						<div class="subscrpt-pe-edit" style="display:none;margin-top:12px;">
							<?php self::render_price_table( $all_terms, $group['term_map'], false ); ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div style="display:flex;align-items:flex-start;gap:10px;padding:14px;margin-bottom:18px;border-radius:8px;background:var(--wpsubs-surface-muted,#f9fafb);border:1px solid var(--wpsubs-border,#e5e7eb);">
				<span class="dashicons dashicons-info-outline" style="flex:0 0 auto;color:var(--wpsubs-text-subtle);"></span>
				<span style="font-size:13px;color:var(--wpsubs-text-muted);line-height:1.5;">
					<?php esc_html_e( 'Not connected to any plan yet. Connect this product to a plan to sell it as a subscription.', 'subscription' ); ?>
				</span>
			</div>
		<?php endif; ?>

		<?php if ( empty( $available ) && empty( $connected ) ) : ?>
			<?php if ( $pro_active ) : ?>
				<div class="wpsubs-table-card" style="padding:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
					<span style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:var(--wpsubs-radius,8px);background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand,#ff4d00);">
						<span class="dashicons dashicons-admin-links"></span>
					</span>
					<div style="flex:1 1 auto;min-width:140px;">
						<div style="font-size:13.5px;font-weight:600;color:var(--wpsubs-text);line-height:1.3;"><?php esc_html_e( 'No plan groups yet', 'subscription' ); ?></div>
						<div style="font-size:12px;color:var(--wpsubs-text-muted);line-height:1.4;"><?php esc_html_e( 'Create a plan group and its first plan, then connect this product to it.', 'subscription' ); ?></div>
					</div>
					<button type="button" class="wpsubs-btn wpsubs-btn--primary" data-wpsubs-modal-open="subscrpt-create-plan">
						<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;line-height:1;"></span>
						<?php esc_html_e( 'New plan group', 'subscription' ); ?>
					</button>
				</div>
			<?php else : ?>
				<p style="margin:0;font-size:13px;color:var(--wpsubs-text-muted);">
					<?php esc_html_e( 'No plans available.', 'subscription' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription-plans' ) ); ?>"><?php esc_html_e( 'Create a plan', 'subscription' ); ?></a>
				</p>
			<?php endif; ?>
		<?php elseif ( ! empty( $available ) && empty( $connected ) ) : ?>
			<div class="wpsubs-table-card" data-subscrpt-connect-card style="padding:14px;">
				<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
					<span style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:var(--wpsubs-radius,8px);background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand,#ff4d00);">
						<span class="dashicons dashicons-admin-links"></span>
					</span>
					<div style="flex:1 1 auto;min-width:140px;">
						<div style="font-size:13.5px;font-weight:600;color:var(--wpsubs-text);line-height:1.3;"><?php esc_html_e( 'Connect to a plan', 'subscription' ); ?></div>
						<div style="font-size:12px;color:var(--wpsubs-text-muted);line-height:1.4;"><?php esc_html_e( 'Pick a plan group, then set the prices for each of its plans.', 'subscription' ); ?></div>
					</div>
					<div style="flex:0 0 auto;display:flex;align-items:center;gap:8px;">
						<?php
						$options = array();
						foreach ( $available as $group ) {
							$options[] = array(
								'value' => (string) $group['id'],
								'label' => $group['title'],
							);
						}
						wpsubs_render_adv_select(
							array(
								'name'        => 'subscrpt_connect_group',
								'placeholder' => __( 'Select a plan group…', 'subscription' ),
								'options'     => $options,
								'attrs'       => array( 'data-subscrpt-connect-group' => '1' ),
								'align'       => 'right',
							)
						);
						?>
						<?php if ( $pro_active ) : ?>
							<button type="button" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" data-wpsubs-modal-open="subscrpt-create-plan" title="<?php esc_attr_e( 'Create a new plan group', 'subscription' ); ?>">
								<span class="dashicons dashicons-plus-alt2" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
								<?php esc_html_e( 'New', 'subscription' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>

				<div data-subscrpt-connect-divider style="display:none;border-top:1px dashed var(--wpsubs-border-strong,#d1d5db);margin-top:14px;"></div>

				<!-- One price table per available group; shown when its group is picked. -->
				<?php foreach ( $available as $group ) : ?>
					<?php $subscrpt_group_terms = PlanRepository::get_plans( (int) $group['id'] ); ?>
					<div data-subscrpt-plan-card data-connect-block data-group-id="<?php echo esc_attr( $group['id'] ); ?>" style="display:none;margin-top:14px;">
						<?php if ( empty( $subscrpt_group_terms ) ) : ?>
							<div style="display:flex;flex-direction:column;align-items:center;gap:12px;padding:20px 16px;text-align:center;">
								<p style="margin:0;color:var(--wpsubs-text-subtle);font-size:13px;">
									<?php esc_html_e( 'This plan group has no plans yet. Add a plan before connecting this product.', 'subscription' ); ?>
								</p>
								<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:center;">
									<?php if ( $pro_active ) : ?>
										<button type="button" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" data-subscrpt-create-plan-for="<?php echo esc_attr( $group['id'] ); ?>">
											<span class="dashicons dashicons-plus-alt2" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
											<?php esc_html_e( 'Create plan', 'subscription' ); ?>
										</button>
									<?php endif; ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription-plans&view=detail&plan=' . (int) $group['id'] ) ); ?>" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" target="_blank" rel="noopener">
										<span class="dashicons dashicons-external" style="font-size:14px;width:14px;height:14px;line-height:1;"></span>
										<?php esc_html_e( 'Manage plans', 'subscription' ); ?>
									</a>
								</div>
							</div>
						<?php else : ?>
							<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
								<span style="flex:1 1 auto;"></span>
								<button type="button" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" data-subscrpt-save-prices>
									<span class="dashicons dashicons-yes" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
									<?php esc_html_e( 'Connect', 'subscription' ); ?>
								</button>
							</div>
							<div>
								<?php self::render_price_table( $subscrpt_group_terms, array(), true ); ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $connected ) || ! empty( $available ) ) : ?>
			<?php
			$subscrpt_ot_on  = $pro_active && 'yes' === $product->get_meta( '_subscrpt_one_time_enabled' );
			$subscrpt_ot_reg = (string) $product->get_regular_price();
			$subscrpt_ot_off = (string) $product->get_sale_price();
			// One-time is edit-gated like the plan prices: while connected it is
			// locked until "Edit plans" unlocks it and the card Save persists it.
			// During the connect flow (not yet connected) it is editable straight
			// away. Non-Pro: always locked (upsell).
			$subscrpt_ot_editgate = $pro_active && ! empty( $connected );
			$subscrpt_ot_disabled = ! ( $pro_active && empty( $connected ) );
			?>
			<div data-subscrpt-onetime-wrap style="<?php echo empty( $connected ) ? 'display:none;' : ''; ?>">
			<div style="width:90%;border-top:1px dashed var(--wpsubs-border-strong,#d1d5db);margin:0 auto 18px;"></div>
			<div class="wpsubs-table-card" data-subscrpt-onetime-card data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" <?php echo $subscrpt_ot_editgate ? 'data-subscrpt-ot-editgated="1"' : ''; ?> style="padding:12px 14px;margin-bottom:18px;">
				<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
					<span style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:var(--wpsubs-radius,8px);background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand,#ff4d00);">
						<span class="dashicons dashicons-cart"></span>
					</span>
					<strong style="flex:0 0 auto;font-size:13.5px;color:var(--wpsubs-text);"><?php esc_html_e( 'One-time purchase', 'subscription' ); ?></strong>
					<?php echo wp_kses_post( wpsubs_render_hint( __( 'This is the product’s regular WooCommerce price, charged when a customer buys it once instead of subscribing.', 'subscription' ) ) ); ?>
					<?php if ( ! $pro_active ) : ?>
						<span class="wpsubs-badge wpsubs-badge--pro" title="<?php esc_attr_e( 'WPSubscription Pro required', 'subscription' ); ?>"><?php esc_html_e( 'Pro', 'subscription' ); ?></span>
					<?php endif; ?>
					<span style="flex:1 1 auto;"></span>
					<label class="wpsubs-settings-toggle-label" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--wpsubs-text-muted);<?php echo $pro_active ? '' : 'opacity:0.6;'; ?>">
						<input type="checkbox" class="wpsubs-toggle" data-subscrpt-onetime-enable <?php checked( $subscrpt_ot_on ); ?> <?php disabled( $subscrpt_ot_disabled ); ?> />
						<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
						<span><?php esc_html_e( 'Allow one-time purchase', 'subscription' ); ?></span>
					</label>
				</div>
				<div data-subscrpt-onetime-body style="margin-top:12px;<?php echo $subscrpt_ot_on ? '' : 'display:none;'; ?>">
					<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;max-width:460px;">
						<label style="display:flex;flex-direction:column;gap:5px;font-size:12px;color:var(--wpsubs-text-muted);">
							<?php esc_html_e( 'Regular Price', 'subscription' ); ?>
							<input type="number" min="0" step="0.01" class="wpsubs-input" data-ot-field="price" value="<?php echo esc_attr( $subscrpt_ot_reg ); ?>" placeholder="0.00" style="width:100%;box-sizing:border-box;" <?php disabled( $subscrpt_ot_disabled ); ?> />
						</label>
						<label style="display:flex;flex-direction:column;gap:5px;font-size:12px;color:var(--wpsubs-text-muted);">
							<?php esc_html_e( 'Offer Price', 'subscription' ); ?>
							<input type="number" min="0" step="0.01" class="wpsubs-input" data-ot-field="offer" value="<?php echo esc_attr( $subscrpt_ot_off ); ?>" placeholder="<?php esc_attr_e( 'No offer', 'subscription' ); ?>" style="width:100%;box-sizing:border-box;" <?php disabled( $subscrpt_ot_disabled ); ?> />
						</label>
					</div>
				</div>
			</div>
			</div>
		<?php endif; ?>

		<?php
		/**
		 * Fires at the end of the product-editor plan view. Pro hooks this to add
		 * more plan-related inputs to the Subscription tab.
		 *
		 * @param \WC_Product $product The product being edited.
		 */
		do_action( 'subscrpt_product_plan_view', $product );
		?>
		<?php
	}

	/**
	 * Render the per-term price table (Regular / Offer + an enable toggle)
	 * shared by the edit and connect views. One-time purchase is product-level
	 * (its own section), not part of this per-plan table.
	 *
	 * @param array $all_terms All terms of the group (from PlanRepository::get_plans()).
	 * @param array $term_map  Map plan_id => relation values (empty for the connect view).
	 * @param bool  $connect   Connect view: no relation ids, every term enabled by default.
	 *
	 * @return void
	 */
	protected static function render_price_table( $all_terms, $term_map, $connect ) {
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
					<th><?php esc_html_e( 'Status', 'subscription' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $all_terms as $subscrpt_term ) :
					$subscrpt_tid     = (int) $subscrpt_term['id'];
					$subscrpt_map     = $connect ? null : ( isset( $term_map[ $subscrpt_tid ] ) ? $term_map[ $subscrpt_tid ] : null );
					$subscrpt_relid   = $subscrpt_map ? $subscrpt_map['relation_id'] : '';
					$subscrpt_enabled = $connect ? true : ( $subscrpt_map && empty( $subscrpt_map['excluded'] ) );
					$subscrpt_vals    = $subscrpt_map ? $subscrpt_map : array(
						'regular' => '',
						'offer'   => '',
					);
					?>
					<tr data-subscrpt-term-row data-plan-id="<?php echo esc_attr( $subscrpt_tid ); ?>" data-relation-id="<?php echo esc_attr( $subscrpt_relid ); ?>">
						<td>
							<div style="font-size:12.5px;color:var(--wpsubs-text);"><?php echo esc_html( $subscrpt_term['title'] ); ?></div>
							<?php $subscrpt_meta = \SpringDevs\Subscription\Admin\PlanPresenter::term_meta( $subscrpt_term ); ?>
							<?php if ( ! empty( $subscrpt_meta ) ) : ?>
								<div style="margin-top:3px;font-size:11px;color:var(--wpsubs-text-muted);line-height:1.5;">
									<?php echo esc_html( implode( ' · ', $subscrpt_meta ) ); ?>
								</div>
							<?php endif; ?>
						</td>
						<td><input type="number" min="0" step="0.01" class="wpsubs-input" data-field="regular_price" value="<?php echo esc_attr( $subscrpt_vals['regular'] ); ?>" placeholder="0.00" style="max-width:110px;" /></td>
						<td><input type="number" min="0" step="0.01" class="wpsubs-input" data-field="sale_price" value="<?php echo esc_attr( $subscrpt_vals['offer'] ); ?>" placeholder="0.00" style="max-width:110px;" /></td>
						<td>
							<label class="wpsubs-settings-toggle-label" style="display:inline-flex;align-items:center;">
								<input type="checkbox" class="wpsubs-toggle" data-subscrpt-term-toggle <?php checked( $subscrpt_enabled ); ?> />
								<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Group flat relation rows by plan group.
	 *
	 * @param array $connections Rows from PlanRepository::get_product_connections().
	 *
	 * @return array<int,array> Keyed by group id: title, terms[], relation_ids[].
	 */
	protected static function group_connections( $connections ) {
		$by_group = array();

		foreach ( $connections as $row ) {
			$gid = (int) $row['plan_group_id'];

			if ( ! isset( $by_group[ $gid ] ) ) {
				$by_group[ $gid ] = array(
					'title'        => $row['group_title'],
					'terms'        => array(),
					'relation_ids' => array(),
					'term_map'     => array(),
				);
			}

			$data                               = is_array( $row['relation_data'] ) ? $row['relation_data'] : array();
			$price                              = isset( $data['regular_price'] ) ? (string) $data['regular_price'] : '';
			$excluded                           = ! empty( $row['exclude'] );
			$by_group[ $gid ]['terms'][]        = array(
				'title'       => $row['plan_title'],
				'price'       => $price,
				'relation_id' => (int) $row['relation_id'],
				'excluded'    => $excluded,
			);
			$by_group[ $gid ]['relation_ids'][] = (int) $row['relation_id'];
			$by_group[ $gid ]['term_map'][ (int) $row['plan_id'] ] = array(
				'relation_id' => (int) $row['relation_id'],
				'excluded'    => $excluded,
				'regular'     => $price,
				'offer'       => isset( $data['sale_price'] ) ? (string) $data['sale_price'] : '',
			);
		}

		return $by_group;
	}

	/**
	 * Recurring plan groups not already connected to this product.
	 *
	 * @param array $connected_ids Group ids already connected.
	 *
	 * @return array<int,array> Each: id, title.
	 */
	protected static function available_groups( $connected_ids ) {
		$recurring = PlanRepository::type_to_int( 'recurring' );
		$available = array();

		foreach ( PlanRepository::get_groups() as $group ) {
			if ( (int) $group['type'] !== $recurring ) {
				continue;
			}
			if ( 'trash' === ( $group['status'] ?? '' ) ) {
				continue;
			}
			if ( in_array( (int) $group['id'], array_map( 'intval', $connected_ids ), true ) ) {
				continue;
			}
			$available[] = array(
				'id'    => (int) $group['id'],
				'title' => $group['title'],
			);
		}

		return $available;
	}
}
