<?php
/**
 * WPSubscription Products List View
 *
 * @package WPSubscription
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'Subscription Products', 'sdevs_wc_subs' ); ?>
	</h1>
	
	<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add Product', 'sdevs_wc_subs' ); ?>
	</a>

	<hr class="wp-header-end">

	<form id="posts-filter" method="get">
		<input type="hidden" name="page" value="wp-subscription-products" />

		<div class="tablenav top">
			<div class="alignleft actions">
				<label for="filter-product-type" class="screen-reader-text"><?php esc_html_e( 'Filter by product type', 'sdevs_wc_subs' ); ?></label>
				<select name="product_type" id="filter-product-type">
					<option value=""><?php esc_html_e( 'All product types', 'sdevs_wc_subs' ); ?></option>
					<option value="simple" <?php selected( isset( $_GET['product_type'] ) ? $_GET['product_type'] : '', 'simple' ); ?>><?php esc_html_e( 'Simple', 'sdevs_wc_subs' ); ?></option>
					<option value="variable" <?php selected( isset( $_GET['product_type'] ) ? $_GET['product_type'] : '', 'variable' ); ?>><?php esc_html_e( 'Variable', 'sdevs_wc_subs' ); ?></option>
				</select>

				<label for="filter-payment-type" class="screen-reader-text"><?php esc_html_e( 'Filter by payment type', 'sdevs_wc_subs' ); ?></label>
				<select name="payment_type" id="filter-payment-type">
					<option value=""><?php esc_html_e( 'All payment types', 'sdevs_wc_subs' ); ?></option>
					<option value="recurring" <?php selected( isset( $_GET['payment_type'] ) ? $_GET['payment_type'] : '', 'recurring' ); ?>><?php esc_html_e( 'Recurring', 'sdevs_wc_subs' ); ?></option>
					<option value="split_payment" <?php selected( isset( $_GET['payment_type'] ) ? $_GET['payment_type'] : '', 'split_payment' ); ?>><?php esc_html_e( 'Split Payment', 'sdevs_wc_subs' ); ?></option>
				</select>

				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'sdevs_wc_subs' ); ?>">
			</div>

			<div class="alignleft actions">
				<label for="post-search-input" class="screen-reader-text"><?php esc_html_e( 'Search Products', 'sdevs_wc_subs' ); ?></label>
				<input type="search" id="post-search-input" name="s" value="<?php echo esc_attr( isset( $_GET['s'] ) ? $_GET['s'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Search products...', 'sdevs_wc_subs' ); ?>">
				<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'sdevs_wc_subs' ); ?>">
			</div>

			<?php if ( $total_products > 0 ) : ?>
				<div class="alignleft actions">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: %d: number of products */
							esc_html( _n( '%d item', '%d items', $total_products, 'sdevs_wc_subs' ) ),
							$total_products
						);
						?>
					</span>
				</div>
			<?php endif; ?>
			
			<?php $this->render_pagination( $paged, $max_pages ); ?>
		</div>

		<?php if ( $total_products > 0 ) : ?>

			<table class="wp-list-table widefat fixed striped table-view-list posts">
				<thead>
					<tr>
						<th scope="col" class="manage-column column-thumb">
							<?php esc_html_e( 'Image', 'sdevs_wc_subs' ); ?>
						</th>
						<th scope="col" class="manage-column column-name column-primary">
							<?php esc_html_e( 'Product Name', 'sdevs_wc_subs' ); ?>
						</th>
						<th scope="col" class="manage-column column-type">
							<?php esc_html_e( 'Type', 'sdevs_wc_subs' ); ?>
						</th>
						<th scope="col" class="manage-column column-payment-type">
							<?php esc_html_e( 'Payment Type', 'sdevs_wc_subs' ); ?>
						</th>
						<th scope="col" class="manage-column column-price">
							<?php esc_html_e( 'Price', 'sdevs_wc_subs' ); ?>
						</th>
						<th scope="col" class="manage-column column-status">
							<?php esc_html_e( 'Status', 'sdevs_wc_subs' ); ?>
						</th>
					</tr>
				</thead>

				<tbody id="the-list">
					<?php foreach ( $products as $product ) : ?>
						<?php
						$product_id   = $product->get_id();
						$product_name = $product->get_name();
						$product_type = $product->get_type();
						$edit_link    = get_edit_post_link( $product_id );
						$view_link    = get_permalink( $product_id );

						// Get subscription meta
						$payment_type = get_post_meta( $product_id, '_subscrpt_payment_type', true );
						$payment_type = $payment_type ? $payment_type : 'recurring';

						// Get active subscriptions count
						$active_subscriptions = $this->get_active_subscriptions_count( $product_id );

						// Get product status
						$status       = $product->get_status();
						$status_label = $this->get_status_label( $status );
						?>
						<tr id="post-<?php echo esc_attr( $product_id ); ?>" class="iedit author-self level-0 post-<?php echo esc_attr( $product_id ); ?> type-product status-<?php echo esc_attr( $status ); ?>">
							<!-- Thumbnail -->
							<td class="column-thumb" data-colname="<?php esc_attr_e( 'Image', 'sdevs_wc_subs' ); ?>">
								<span class="product-thumb">
									<?php echo $product->get_image( 'thumbnail' ); ?>
								</span>
							</td>

			<!-- Product Name -->
			<td class="name column-name has-row-actions column-primary" data-colname="<?php esc_attr_e( 'Product Name', 'sdevs_wc_subs' ); ?>">
				<strong>
					<a href="<?php echo esc_url( $edit_link ); ?>" class="row-title" aria-label="<?php echo esc_attr( sprintf( __( 'Edit "%s"', 'sdevs_wc_subs' ), $product_name ) ); ?>">
						<?php echo esc_html( $product_name ); ?>
					</a>
				</strong>

				<div class="row-actions">
					<span class="edit">
						<a href="<?php echo esc_url( $edit_link ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Edit "%s"', 'sdevs_wc_subs' ), $product_name ) ); ?>">
							<?php esc_html_e( 'Edit', 'sdevs_wc_subs' ); ?>
						</a> | 
					</span>
					<span class="view">
						<a href="<?php echo esc_url( $view_link ); ?>" rel="bookmark" aria-label="<?php echo esc_attr( sprintf( __( 'View "%s"', 'sdevs_wc_subs' ), $product_name ) ); ?>" target="_blank">
							<?php esc_html_e( 'View', 'sdevs_wc_subs' ); ?>
						</a>
					</span>
				</div>

								<button type="button" class="toggle-row">
									<span class="screen-reader-text"><?php esc_html_e( 'Show more details', 'sdevs_wc_subs' ); ?></span>
								</button>
							</td>

							<!-- Type -->
							<td class="column-type" data-colname="<?php esc_attr_e( 'Type', 'sdevs_wc_subs' ); ?>">
								<span class="product-type-badge product-type-<?php echo esc_attr( $product_type ); ?>">
									<?php echo esc_html( ucfirst( $product_type ) ); ?>
								</span>
							</td>

							<!-- Payment Type -->
							<td class="column-payment-type" data-colname="<?php esc_attr_e( 'Payment Type', 'sdevs_wc_subs' ); ?>">
								<span class="payment-type-badge payment-type-<?php echo esc_attr( $payment_type ); ?>">
									<?php echo esc_html( $this->get_payment_type_label( $payment_type ) ); ?>
								</span>
								<?php echo $this->get_payment_details( $product_id, $payment_type ); ?>
							</td>

							<!-- Price -->
							<td class="column-price" data-colname="<?php esc_attr_e( 'Price', 'sdevs_wc_subs' ); ?>">
								<?php echo $this->get_price_display( $product ); ?>
							</td>

			<!-- Status -->
			<td class="column-status" data-colname="<?php esc_attr_e( 'Status', 'sdevs_wc_subs' ); ?>">
				<mark class="order-status status-<?php echo esc_attr( $status ); ?>">
					<span><?php echo esc_html( $status_label ); ?></span>
				</mark>
			</td>
		</tr>
					<?php endforeach; ?>
				</tbody>

				<tfoot>
					<tr>
						<th scope="col" class="manage-column column-thumb">
							<?php esc_html_e( 'Image', 'sdevs_wc_subs' ); ?>
						</th>
						<th scope="col" class="manage-column column-name column-primary">
							<?php esc_html_e( 'Product Name', 'sdevs_wc_subs' ); ?>
						</th>
						<th scope="col" class="manage-column column-type">
							<?php esc_html_e( 'Type', 'sdevs_wc_subs' ); ?>
						</th>
						<th scope="col" class="manage-column column-payment-type">
							<?php esc_html_e( 'Payment Type', 'sdevs_wc_subs' ); ?>
						</th>
						<th scope="col" class="manage-column column-price">
							<?php esc_html_e( 'Price', 'sdevs_wc_subs' ); ?>
						</th>
						<th scope="col" class="manage-column column-status">
							<?php esc_html_e( 'Status', 'sdevs_wc_subs' ); ?>
						</th>
					</tr>
				</tfoot>
			</table>

			<div class="tablenav bottom">
				<div class="alignleft actions"></div>
				<?php $this->render_pagination( $paged, $max_pages ); ?>
			</div>

		<?php else : ?>
			<p><?php esc_html_e( 'No products found matching the selected criteria.', 'sdevs_wc_subs' ); ?></p>
		<?php endif; ?>

	</form>

	<?php if ( $total_products === 0 && ! isset( $_GET['s'] ) && ! isset( $_GET['product_type'] ) && ! isset( $_GET['payment_type'] ) ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php esc_html_e( 'No subscription products found.', 'sdevs_wc_subs' ); ?>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">
					<?php esc_html_e( 'Create your first subscription product', 'sdevs_wc_subs' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>
</div>

<style>
	.column-thumb {
		width: 52px;
		text-align: center;
		white-space: nowrap;
	}
	.column-type {
		width: 12%;
	}
	.column-payment-type {
		width: 18%;
	}
	.column-price {
		width: 18%;
	}
	.column-status {
		width: 12%;
	}
	.product-thumb {
		width: 32px;
		height: 32px;
		display: inline-block;
	}
	.product-thumb img {
		width: 100%;
		height: 100%;
		object-fit: cover;
		border-radius: 3px;
	}
	.payment-type-badge,
	.product-type-badge {
		display: inline-block;
		padding: 2px 8px;
		border-radius: 3px;
		font-size: 12px;
		line-height: 1.5;
	}
	.payment-type-recurring {
		background-color: #2271b1;
		color: #fff;
	}
	.payment-type-split,
	.payment-type-split_payment {
		background-color: #d63638;
		color: #fff;
	}
	.product-type-simple {
		background-color: #50575e;
		color: #fff;
	}
	.product-type-variable {
		background-color: #2c3338;
		color: #fff;
	}
	.row-actions {
		color: #dcdcde;
	}
	.tablenav {
		height: 100%;
		clear: both;
	}
	.tablenav .displaying-num {
		margin-right: 10px;
		padding-top: 8px;
		color: #646970;
		font-size: 13px;
		font-style: normal;
	}
	.tablenav-pages {
		float: right;
		margin: 0;
	}
	.pagination-links {
		padding-top: 4px;
	}
	.pagination-links .page-numbers {
		display: flex;
		min-width: 24px;
		padding: 5px 10px;
		font-size: 14px;
		line-height: 1;
		text-align: center;
		text-decoration: none;
		justify-content: center;
	}
	.pagination-links .page-numbers.current {
		background-color: #2271b1;
		color: #fff;
		border-radius: 3px;
	}
	@media screen and (max-width: 782px) {
		.column-thumb,
		.column-type,
		.column-payment-type,
		.column-price,
		.column-status {
			display: none;
		}
		.tablenav-pages {
			float: none;
			margin: 20px 0;
		}
	}
</style>

