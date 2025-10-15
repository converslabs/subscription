<?php

use SpringDevs\Subscription\Illuminate\Helper;

if ( ! isset( $date_filter ) ) {
	$date_filter = ''; } ?>
<?php
// Determine if filters are active
$filters_active = ! empty( $status ) || ! empty( $date_filter ) || ! empty( $search );
$months         = array();
for ( $i = 0; $i < 12; $i++ ) {
	$month                           = strtotime( "-$i month" );
	$months[ date( 'Y-m', $month ) ] = date( 'F Y', $month );
}
?>
<div class="wp-subscription-admin-content list-page">
	<div class="wp-subscription-list-title"><h1 class="wp-heading-inline">Subscriptions</h1></div>
	
	<form method="post" id="subscriptions-form">
		<div class="wp-subscription-list-header">
			<div class="wp-subscription-filters">
				<input type="hidden" name="page" value="wp-subscription" />
				<select name="subscrpt_status" value="<?php echo esc_attr( $status ); ?>">
					<option value=""><?php esc_html_e( 'All Status', 'wp_subscription' ); ?></option>
					<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'wp_subscription' ); ?></option>
					<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'wp_subscription' ); ?></option>
					<option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'wp_subscription' ); ?></option>
					<option value="expired" <?php selected( $status, 'expired' ); ?>><?php esc_html_e( 'Expired', 'wp_subscription' ); ?></option>
					<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'wp_subscription' ); ?></option>
					<option value="trash" <?php selected( $status, 'trash' ); ?>><?php esc_html_e( 'Trash', 'wp_subscription' ); ?></option>
				</select>
				<select name="date_filter" value="<?php echo esc_attr( $date_filter ); ?>">
					<option value=""><?php esc_html_e( 'All Dates', 'wp_subscription' ); ?></option>
					<?php foreach ( $months as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $date_filter, $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by subscription ID...', 'wp_subscription' ); ?>" />
				<select name="per_page">
					<?php foreach ( array( 10, 20, 50, 100 ) as $n ) : ?>
						<option value="<?php echo $n; ?>" <?php selected( isset( $_GET['per_page'] ) ? intval( $_GET['per_page'] ) : 20, $n ); ?>><?php echo $n; ?> per page</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" name="filter_action" value="filter" class="button">Search</button>
				<?php if ( $filters_active ) : ?>
					<a href="<?php echo esc_url( remove_query_arg( array( 'subscrpt_status', 'date_filter', 's', 'title', 'paged' ) ) ); ?>" class="button">Reset</a>
				<?php endif; ?>
				<?php if ( $filters_active ) : ?>
					<span>Filters applied</span>
				<?php endif; ?>
			</div>
			
			<?php if ( ! empty( $subscriptions ) ) : ?>
			<div class="wp-subscription-bulk-actions">
				<select name="action">
					<option value="-1"><?php esc_html_e( 'Bulk Actions', 'wp_subscription' ); ?></option>
					<?php if ( $status === 'trash' ) : ?>
						<option value="restore"><?php esc_html_e( 'Restore', 'wp_subscription' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete Permanently', 'wp_subscription' ); ?></option>
					<?php else : ?>
						<option value="trash"><?php esc_html_e( 'Move to Trash', 'wp_subscription' ); ?></option>
					<?php endif; ?>
				</select>
				<input type="submit" name="bulk_action" value="<?php esc_attr_e( 'Apply', 'wp_subscription' ); ?>" class="button action">
				<?php if ( $status === 'trash' && ! empty( $subscriptions ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription&action=clean_trash&sub_id=all' ) ); ?>" 
						class="button button-link-delete" 
						onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to permanently delete all items in trash? This action cannot be undone.', 'wp_subscription' ); ?>')">
						<?php esc_html_e( 'Empty Trash', 'wp_subscription' ); ?>
					</a>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		</div>
	
		<h2 class="screen-reader-text">Subscriptions list</h2>
		<table class="wp-list-table widefat fixed striped wp-subscription-modern-table">
			<thead>
				<tr>
					<th style="width:20px;"><input type="checkbox" id="cb-select-all-1"></th>
					<th style="width:180px;">ID</th>
					<th style="min-width:320px;">Title</th>
					<th style="width:180px;">Customer</th>
					<th style="width:100px;">Start Date</th>
					<th style="width:100px;">Renewal Date</th>
					<th style="width:100px;">Status</th>
					<th style="width:80px;">Actions</th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! empty( $subscriptions ) ) : ?>
				<?php
				foreach ( $subscriptions as $subscription ) :
					$subscription_data = Helper::get_subscription_data( $subscription->ID );

					$order_id      = $subscription_data['order']['order_id'] ?? 0;
					$order_item_id = $subscription_data['order']['order_item_id'] ?? 0;
					$order         = $order_id ? wc_get_order( $order_id ) : null;
					$order_item    = $order ? $order->get_item( $order_item_id ) : null;
					$product_name  = $order_item ? $order_item->get_name() : '-';

					$customer       = $order ? $order->get_formatted_billing_full_name() : '-';
					$customer_id    = $order ? $order->get_customer_id() : 0;
					$customer_url   = $customer_id ? admin_url( 'user-edit.php?user_id=' . $customer_id ) : '';
					$customer_email = $order ? $order->get_billing_email() : '';

					$start_date   = $subscription_data['start_date'] ? strtotime( $subscription_data['start_date'] ) : 0;
					$renewal_date = $subscription_data['next_date'] ? strtotime( $subscription_data['next_date'] ) : 0;
					$status_obj   = get_post_status_object( get_post_status( $subscription->ID ) );

					$is_trash = $subscription->post_status === 'trash';

					$is_grace_period = isset( $subscription_data['grace_period'] ) && $subscription_data['grace_period']['remaining_days'] > 0;

					// dd( '🔽 subscription', $subscription_data, $subscription );
					?>
				<tr>
					<td><input type="checkbox" name="subscription_ids[]" value="<?php echo esc_attr( $subscription->ID ); ?>"></td>
					<td>
						<a href="<?php echo esc_url( get_edit_post_link( $subscription->ID ) ); ?>" class="subscrpt-id-link">
							#<?php echo esc_html( get_the_title( $subscription->ID ) ); ?>
						</a>
					</td>
					<td style="min-width:320px;">
						<div class="wp-subscription-title-wrap">
							<span><?php echo esc_html( $product_name ); ?></span>
							<div class="wp-subscription-row-actions">
								<a href="<?php echo esc_url( get_edit_post_link( $subscription->ID ) ); ?>">View</a>
								<?php if ( ! $is_trash ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription&action=duplicate&sub_id=' . $subscription->ID ) ); ?>">Duplicate</a>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription&action=trash&sub_id=' . $subscription->ID ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Move this subscription to trash?', 'wp_subscription' ); ?>')">Trash</a>
								<?php else : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription&action=restore&sub_id=' . $subscription->ID ) ); ?>">Restore</a>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-subscription&action=delete&sub_id=' . $subscription->ID ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this subscription permanently? This action cannot be undone.', 'wp_subscription' ); ?>')" style="color:#d93025;">Delete Permanently</a>
								<?php endif; ?>
							</div>
						</div>
					</td>
					<td>
						<?php if ( $customer_url ) : ?>
							<a href="<?php echo esc_url( $customer_url ); ?>" target="_blank"><?php echo esc_html( $customer ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $customer ); ?>
						<?php endif; ?>
						<?php if ( $customer_email ) : ?>
							<div class="wp-subscription-customer-email"><?php echo esc_html( $customer_email ); ?></div>
						<?php endif; ?>
					</td>
					<td><?php echo $start_date ? esc_html( gmdate( 'F d, Y', $start_date ) ) : '-'; ?></td>
					<td><?php echo $renewal_date ? esc_html( gmdate( 'F d, Y', $renewal_date ) ) : '-'; ?></td>
					<td>
						<span class="subscrpt-<?php echo esc_attr( $status_obj->name ); ?>">
							<?php echo esc_html( strlen( $status_obj->label ) > 9 ? substr( $status_obj->label, 0, 9 ) . '...' : $status_obj->label ); ?>
						</span>
					</td>
					<td>
						<a href="<?php echo esc_url( get_edit_post_link( $subscription->ID ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'wp_subscription' ); ?></a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="8" class="wp-subscription-list-empty">
						<?php esc_html_e( 'No subscriptions found.', 'wp_subscription' ); ?>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
		
		<?php if ( ! empty( $subscriptions ) ) : ?>
			<div class="tablenav bottom">
				<div class="alignleft actions bulkactions">
					<select name="action2">
						<option value="-1"><?php esc_html_e( 'Bulk Actions', 'wp_subscription' ); ?></option>
						<?php if ( $status === 'trash' ) : ?>
							<option value="restore"><?php esc_html_e( 'Restore', 'wp_subscription' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete Permanently', 'wp_subscription' ); ?></option>
						<?php else : ?>
							<option value="trash"><?php esc_html_e( 'Move to Trash', 'wp_subscription' ); ?></option>
						<?php endif; ?>
					</select>
					<input type="submit" name="bulk_action2" value="<?php esc_attr_e( 'Apply', 'wp_subscription' ); ?>" class="button action">
				</div>
			</div>
		<?php endif; ?>
	</form>
	
	<?php if ( $max_num_pages > 1 ) : ?>
	<div class="wp-subscription-pagination">
		<span class="total">Total <?php echo intval( $total ); ?></span>
		<?php
		$base_url   = remove_query_arg( 'paged' );
		$show_pages = $max_num_pages > 1 || $max_num_pages == 1;
		for ( $i = 1; $i <= $max_num_pages; $i++ ) :
			$url        = add_query_arg(
				array(
					'paged'    => $i,
					'per_page' => $per_page,
				),
				$base_url
			);
			$is_current = $i == $paged;
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="button
			<?php
			if ( $is_current ) {
				echo ' button-primary';}
			?>
			" 
			<?php
			if ( $is_current ) {
				echo 'disabled';}
			?>
><?php echo $i; ?></a>
		<?php endfor; ?>
		<span class="goto-label">Go to</span>
		<form method="get">
			<input type="hidden" name="page" value="wp-subscription" />
			<input type="number" name="paged" min="1" max="<?php echo $max_num_pages; ?>" value="<?php echo $paged; ?>" />
			<input type="hidden" name="per_page" value="<?php echo $per_page; ?>" />
			<button type="submit" class="button">OK</button>
		</form>
	</div>
	<?php endif; ?>
</div>
