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
	}

	/**
	 * Render the view toggle bar (plan view ⇄ classic settings).
	 *
	 * @return void
	 */
	public static function render_toolbar() {
		?>
		<div style="display:flex;align-items:center;gap:10px;margin:0 0 14px;">
			<strong style="font-size:13px;color:var(--wpsubs-text);"><?php esc_html_e( 'Subscription', 'subscription' ); ?></strong>
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
		?>
		<style>
			/* Neutralise WooCommerce's floated-label layout inside our plan view. */
			#sdevs_subscription_options [data-subscrpt-plan-view] label {
				float: none;
				width: auto;
				margin: 0;
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
					<?php $subscrpt_ot = ! empty( $group['one_time_on'] ); ?>
					<div class="wpsubs-table-card" data-subscrpt-plan-card data-group-id="<?php echo esc_attr( $subscrpt_gid ); ?>" style="padding:12px 14px;">
						<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
							<span style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:var(--wpsubs-radius,8px);background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand,#ff4d00);">
								<span class="dashicons dashicons-update"></span>
							</span>
							<div style="flex:1 1 auto;min-width:0;display:flex;align-items:center;gap:8px;">
								<strong style="font-size:13.5px;color:var(--wpsubs-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $group['title'] ); ?></strong>
								<span class="wpsubs-badge wpsubs-badge--active" style="font-weight:500;"><?php esc_html_e( 'Connected', 'subscription' ); ?></span>
							</div>
							<div style="flex:0 0 auto;display:flex;align-items:center;gap:8px;">
								<label class="wpsubs-settings-toggle-label subscrpt-edit-only" style="display:none;align-items:center;gap:6px;font-size:12px;color:var(--wpsubs-text-muted);">
									<input type="checkbox" class="wpsubs-toggle" data-subscrpt-onetime-toggle <?php checked( $subscrpt_ot ); ?> />
									<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
									<span><?php esc_html_e( 'One-time purchase', 'subscription' ); ?></span>
								</label>
								<span aria-hidden="true" class="subscrpt-edit-only" style="display:none;width:2px;height:16px;background:var(--wpsubs-border,#e5e7eb);"></span>
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
						<div class="subscrpt-pe-edit" style="display:none;margin-top:12px;overflow-x:auto;">
							<?php self::render_price_table( $all_terms, $group['term_map'], $subscrpt_ot, false ); ?>
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
			<p style="margin:0;font-size:13px;color:var(--wpsubs-text-muted);">
				<?php esc_html_e( 'No plans available.', 'subscription' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription-plans' ) ); ?>"><?php esc_html_e( 'Create a plan', 'subscription' ); ?></a>
			</p>
		<?php elseif ( ! empty( $available ) ) : ?>
			<div class="wpsubs-table-card" data-subscrpt-connect-card style="padding:14px;">
				<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
					<span style="flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:var(--wpsubs-radius,8px);background:var(--wpsubs-brand-light,#fff1eb);color:var(--wpsubs-brand,#ff4d00);">
						<span class="dashicons dashicons-admin-links"></span>
					</span>
					<div style="flex:1 1 auto;min-width:140px;">
						<div style="font-size:13.5px;font-weight:600;color:var(--wpsubs-text);line-height:1.3;"><?php esc_html_e( 'Connect to a plan', 'subscription' ); ?></div>
						<div style="font-size:12px;color:var(--wpsubs-text-muted);line-height:1.4;"><?php esc_html_e( 'Pick a plan group, then set the prices for each of its plans.', 'subscription' ); ?></div>
					</div>
					<div style="flex:0 0 auto;">
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
					</div>
				</div>

				<div data-subscrpt-connect-divider style="display:none;border-top:1px dashed var(--wpsubs-border-strong,#d1d5db);margin-top:14px;"></div>

				<!-- One price table per available group; shown when its group is picked. -->
				<?php foreach ( $available as $group ) : ?>
					<?php $subscrpt_group_terms = PlanRepository::get_plans( (int) $group['id'] ); ?>
					<div data-subscrpt-plan-card data-connect-block data-group-id="<?php echo esc_attr( $group['id'] ); ?>" style="display:none;margin-top:14px;">
						<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
							<span style="flex:1 1 auto;"></span>
							<label class="wpsubs-settings-toggle-label" style="display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--wpsubs-text-muted);">
								<input type="checkbox" class="wpsubs-toggle" data-subscrpt-onetime-toggle />
								<span class="wpsubs-toggle-ui" aria-hidden="true"></span>
								<span><?php esc_html_e( 'One-time purchase', 'subscription' ); ?></span>
							</label>
							<span aria-hidden="true" style="width:2px;height:16px;background:var(--wpsubs-border,#e5e7eb);"></span>
							<button type="button" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" data-subscrpt-save-prices>
								<span class="dashicons dashicons-yes" style="font-size:15px;width:15px;height:15px;line-height:1;"></span>
								<?php esc_html_e( 'Connect', 'subscription' ); ?>
							</button>
						</div>
						<div>
							<?php self::render_price_table( $subscrpt_group_terms, array(), false, true ); ?>
						</div>
					</div>
				<?php endforeach; ?>
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
	 * Render the per-term price table (Regular / Offer / One-time price /
	 * One-time offer + an enable toggle) shared by the edit and connect views.
	 * The one-time columns start hidden unless $one_time_on.
	 *
	 * @param array $all_terms   All terms of the group (from PlanRepository::get_plans()).
	 * @param array $term_map    Map plan_id => relation values (empty for the connect view).
	 * @param bool  $one_time_on Whether the one-time columns start visible.
	 * @param bool  $connect     Connect view: no relation ids, every term enabled by default.
	 *
	 * @return void
	 */
	protected static function render_price_table( $all_terms, $term_map, $one_time_on, $connect ) {
		$ot_col = $one_time_on ? '' : 'display:none;';
		$bar    = '<span style="display:inline-block;vertical-align:middle;width:2px;height:16px;border-radius:1px;background:var(--wpsubs-border,#e5e7eb);"></span>';
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
					<th class="subscrpt-onetime-col" style="<?php echo esc_attr( $ot_col ); ?>width:1px;padding:0 6px;text-align:center;"><?php echo $bar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></th>
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
				<?php
				foreach ( $all_terms as $subscrpt_term ) :
					$subscrpt_tid     = (int) $subscrpt_term['id'];
					$subscrpt_map     = $connect ? null : ( isset( $term_map[ $subscrpt_tid ] ) ? $term_map[ $subscrpt_tid ] : null );
					$subscrpt_relid   = $subscrpt_map ? $subscrpt_map['relation_id'] : '';
					$subscrpt_enabled = $connect ? true : ( $subscrpt_map && empty( $subscrpt_map['excluded'] ) );
					$subscrpt_vals    = $subscrpt_map ? $subscrpt_map : array(
						'regular'        => '',
						'offer'          => '',
						'one_time_price' => '',
						'one_time_offer' => '',
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
						<td class="subscrpt-onetime-col" style="<?php echo esc_attr( $ot_col ); ?>width:1px;padding:0 6px;text-align:center;"><?php echo $bar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td class="subscrpt-onetime-col" style="<?php echo esc_attr( $ot_col ); ?>"><input type="number" min="0" step="0.01" class="wpsubs-input" data-field="one_time_price" value="<?php echo esc_attr( $subscrpt_vals['one_time_price'] ); ?>" placeholder="<?php esc_attr_e( 'No charge', 'subscription' ); ?>" style="max-width:110px;" /></td>
						<td class="subscrpt-onetime-col" style="<?php echo esc_attr( $ot_col ); ?>"><input type="number" min="0" step="0.01" class="wpsubs-input" data-field="one_time_offer" value="<?php echo esc_attr( $subscrpt_vals['one_time_offer'] ); ?>" placeholder="<?php esc_attr_e( 'No offer', 'subscription' ); ?>" style="max-width:110px;" /></td>
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
				'relation_id'    => (int) $row['relation_id'],
				'excluded'       => $excluded,
				'regular'        => $price,
				'offer'          => isset( $data['sale_price'] ) ? (string) $data['sale_price'] : '',
				'one_time'       => ! empty( $data['one_time'] ),
				'one_time_price' => isset( $data['one_time_price'] ) ? (string) $data['one_time_price'] : '',
				'one_time_offer' => isset( $data['one_time_offer'] ) ? (string) $data['one_time_offer'] : '',
			);
			if ( ! empty( $data['one_time'] ) ) {
				$by_group[ $gid ]['one_time_on'] = true;
			}
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
