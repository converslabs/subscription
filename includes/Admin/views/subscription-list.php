<?php
/**
 * Subscription admin list view.
 *
 * @package SpringDevs\Subscription\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $date_filter ) ) {
	$date_filter = '';
}

$filters_active = ! empty( $status ) || ! empty( $date_filter ) || ! empty( $search );
$months         = array();
for ( $i = 0; $i < 12; $i++ ) {
	$month                           = strtotime( "-$i month" );
	$months[ date( 'Y-m', $month ) ] = date( 'F Y', $month );
}
?>
<div class="wp-subscription-admin-content list-page">

	<form method="post" id="subscriptions-form">
		<?php wp_nonce_field( 'subscrpt_list_action' ); ?>
		<input type="hidden" name="page" value="wp-subscription" />

		<!-- Toolbar -->
		<div class="wpsubs-toolbar">

			<div class="wpsubs-search">
				<svg class="wpsubs-search__icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/></svg>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search subscriptions...', 'subscription' ); ?>" class="wpsubs-search__input" />
			</div>

			<select name="subscrpt_status" class="wpsubs-select">
				<option value=""><?php esc_html_e( 'All Status', 'subscription' ); ?></option>
				<option value="active"    <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'subscription' ); ?></option>
				<option value="pending"   <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'subscription' ); ?></option>
				<option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'subscription' ); ?></option>
				<option value="expired"   <?php selected( $status, 'expired' ); ?>><?php esc_html_e( 'Expired', 'subscription' ); ?></option>
				<option value="draft"     <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'subscription' ); ?></option>
				<option value="trash"     <?php selected( $status, 'trash' ); ?>><?php esc_html_e( 'Trash', 'subscription' ); ?></option>
			</select>

			<select name="date_filter" class="wpsubs-select">
				<option value=""><?php esc_html_e( 'All Dates', 'subscription' ); ?></option>
				<?php foreach ( $months as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $date_filter, $val ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<button type="submit" name="filter_action" value="filter" class="wpsubs-btn wpsubs-btn--outline">
				<?php esc_html_e( 'Filter', 'subscription' ); ?>
			</button>

			<?php if ( $filters_active ) : ?>
				<a href="<?php echo esc_url( remove_query_arg( array( 'subscrpt_status', 'date_filter', 's', 'title', 'paged' ) ) ); ?>" class="wpsubs-btn wpsubs-btn--outline">
					<?php esc_html_e( 'Clear', 'subscription' ); ?>
				</a>
			<?php endif; ?>

			<div class="wpsubs-toolbar__spacer"></div>

			<select name="per_page" class="wpsubs-select">
				<?php foreach ( array( 10, 20, 50, 100 ) as $n ) : ?>
					<option value="<?php echo (int) $n; ?>" <?php selected( isset( $_GET['per_page'] ) ? intval( wp_unslash( $_GET['per_page'] ) ) : 20, $n ); ?>><?php echo (int) $n; ?> / page</option>
				<?php endforeach; ?>
			</select>

			<?php if ( ! empty( $subscriptions ) ) : ?>
				<select name="action" class="wpsubs-select">
					<option value="-1"><?php esc_html_e( 'Select Action', 'subscription' ); ?></option>
					<?php if ( 'trash' === $status ) : ?>
						<option value="restore"><?php esc_html_e( 'Restore', 'subscription' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete Permanently', 'subscription' ); ?></option>
					<?php else : ?>
						<option value="trash"><?php esc_html_e( 'Move to Trash', 'subscription' ); ?></option>
					<?php endif; ?>
				</select>
				<button type="submit" name="bulk_action" class="wpsubs-btn wpsubs-btn--outline"><?php esc_html_e( 'Apply', 'subscription' ); ?></button>
			<?php endif; ?>

			<?php
			if ( 'trash' === $status && ! empty( $subscriptions ) ) :
				$empty_trash_url = wp_nonce_url( admin_url( 'admin.php?page=wp-subscription&action=clean_trash&sub_id=all' ), 'wpsubs_action_clean_trash' );
				?>
				<a href="<?php echo esc_url( $empty_trash_url ); ?>" class="wpsubs-btn wpsubs-btn--danger" onclick="return confirm('<?php esc_attr_e( 'Permanently delete all trash items? This cannot be undone.', 'subscription' ); ?>')">
					<?php esc_html_e( 'Empty Trash', 'subscription' ); ?>
				</a>
			<?php endif; ?>

		</div><!-- /.wpsubs-toolbar -->

		<!-- Table card -->
		<h2 class="screen-reader-text"><?php esc_html_e( 'Subscriptions list', 'subscription' ); ?></h2>
		<div class="wpsubs-table-card">
			<table class="wpsubs-table">
				<thead>
					<tr>
						<th class="wpsubs-col--check">
							<input type="checkbox" id="cb-select-all" class="wpsubs-checkbox">
						</th>
						<th><?php esc_html_e( 'Subscription', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Product', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Status', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Started', 'subscription' ); ?></th>
						<th><?php esc_html_e( 'Next Renewal', 'subscription' ); ?></th>
						<th class="wpsubs-col--actions"></th>
					</tr>
				</thead>
				<tbody>

				<?php if ( ! empty( $subscriptions ) ) : ?>
					<?php
					foreach ( $subscriptions as $subscription ) :
						$subscription_id   = $subscription->ID;
						$subscription_data = SpringDevs\Subscription\Illuminate\Helper::get_subscription_data( $subscription_id );

						$subscrpt_status = $subscription_data['status'] ?? '';
						$subscrpt_status = empty( $subscrpt_status ) ? get_post_status( $subscription_id ) : $subscrpt_status;

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

						$is_trash        = 'trash' === $subscription->post_status;
						$is_grace_period = isset( $subscription_data['grace_period'] );
						$grace_remaining = $subscription_data['grace_period']['remaining_days'] ?? 0;

						// Avatar: derive initials + deterministic color slot (0-7)
						$name_parts = array_values( array_filter( explode( ' ', trim( $customer ) ) ) );
						$initials   = '?';
						if ( $name_parts ) {
							$initials = '';
							foreach ( array_slice( $name_parts, 0, 2 ) as $part ) {
								$initials .= strtoupper( $part[0] );
							}
						}
						$color_slot = ord( strtolower( $initials[0] ?? 'a' ) ) % 8;

						// Action URLs
						$nonce_action = 'wpsubs_action_' . $subscription->ID;
						$view_url     = get_edit_post_link( $subscription->ID );
						$trash_url    = wp_nonce_url( admin_url( 'admin.php?page=wp-subscription&action=trash&sub_id=' . $subscription->ID ), $nonce_action );
						$delete_url   = wp_nonce_url( admin_url( 'admin.php?page=wp-subscription&action=delete&sub_id=' . $subscription->ID ), $nonce_action );
						$restore_url  = wp_nonce_url( admin_url( 'admin.php?page=wp-subscription&action=restore&sub_id=' . $subscription->ID ), $nonce_action );

						// Status badge
						$badge_mod_map  = array(
							'active'    => 'active',
							'pending'   => 'pending',
							'cancelled' => 'cancelled',
							'expired'   => 'expired',
							'draft'     => 'draft',
							'trash'     => 'trash',
						);
						$badge_mod      = $badge_mod_map[ $subscrpt_status ] ?? 'expired';
						$verbose_status = SpringDevs\Subscription\Illuminate\Helper::get_verbose_status( $subscrpt_status );

						if ( $is_grace_period && $grace_remaining > 0 ) {
							$badge_mod      = 'active';
							$verbose_status = __( 'Active', 'subscription' );
						}
						?>
					<tr>
						<td class="wpsubs-col--check">
							<input type="checkbox" name="subscription_ids[]" value="<?php echo esc_attr( $subscription->ID ); ?>" class="wpsubs-checkbox wpsubs-row-check">
						</td>

						<td>
							<div class="wpsubs-customer">
								<div class="wpsubs-avatar" data-color="<?php echo (int) $color_slot; ?>">
									<?php echo esc_html( $initials ); ?>
								</div>
								<div class="wpsubs-customer__info">
									<?php if ( $customer_url ) : ?>
										<a href="<?php echo esc_url( $customer_url ); ?>" class="wpsubs-customer__name"><?php echo esc_html( $customer ); ?></a>
									<?php else : ?>
										<span class="wpsubs-customer__name"><?php echo esc_html( $customer ); ?></span>
									<?php endif; ?>
									<?php if ( $customer_email ) : ?>
										<span class="wpsubs-customer__sub"><?php echo esc_html( $customer_email ); ?></span>
									<?php endif; ?>
								</div>
							</div>
						</td>

						<td>
							<a href="<?php echo esc_url( $view_url ); ?>" class="wpsubs-cell-title"><?php echo esc_html( $product_name ); ?></a>
							<span class="wpsubs-cell-id">#<?php echo esc_html( get_the_title( $subscription->ID ) ); ?></span>
						</td>

						<td>
							<span class="wpsubs-badge wpsubs-badge--<?php echo esc_attr( $badge_mod ); ?>">
								<span class="wpsubs-badge__dot"></span>
								<?php echo esc_html( $verbose_status ); ?>
								<?php if ( $is_grace_period && $grace_remaining > 0 ) : ?>
									<span class="dashicons dashicons-warning" style="font-size:11px;width:11px;height:11px;color:#d97706;" title="<?php echo esc_attr( sprintf( __( '%d days remaining in grace period', 'subscription' ), $grace_remaining ) ); ?>"></span>
								<?php endif; ?>
							</span>
						</td>

						<td class="wpsubs-cell--muted wpsubs-col--nowrap">
							<?php echo $start_date ? esc_html( wp_date( 'n/j/Y', $start_date ) ) : '&#8212;'; ?>
						</td>

						<td class="wpsubs-cell--muted wpsubs-col--nowrap">
							<?php echo $renewal_date ? esc_html( wp_date( 'n/j/Y', $renewal_date ) ) : '&#8212;'; ?>
						</td>

						<td class="wpsubs-cell--actions">
							<div class="wpsubs-row-actions">
								<button type="button" class="wpsubs-row-actions__trigger" aria-label="<?php esc_attr_e( 'Row actions', 'subscription' ); ?>">···</button>
								<div class="wpsubs-dropdown">
									<a href="<?php echo esc_url( $view_url ); ?>" class="wpsubs-dropdown__item"><?php esc_html_e( 'View / Edit', 'subscription' ); ?></a>
									<div class="wpsubs-dropdown__divider"></div>
									<?php if ( ! $is_trash ) : ?>
										<a href="<?php echo esc_url( $trash_url ); ?>" class="wpsubs-dropdown__item wpsubs-dropdown__item--danger" onclick="return confirm('<?php esc_attr_e( 'Move this subscription to trash?', 'subscription' ); ?>')"><?php esc_html_e( 'Trash', 'subscription' ); ?></a>
									<?php else : ?>
										<a href="<?php echo esc_url( $restore_url ); ?>" class="wpsubs-dropdown__item"><?php esc_html_e( 'Restore', 'subscription' ); ?></a>
										<a href="<?php echo esc_url( $delete_url ); ?>" class="wpsubs-dropdown__item wpsubs-dropdown__item--danger" onclick="return confirm('<?php esc_attr_e( 'Delete permanently? This cannot be undone.', 'subscription' ); ?>')"><?php esc_html_e( 'Delete Permanently', 'subscription' ); ?></a>
									<?php endif; ?>
								</div>
							</div>
						</td>
					</tr>
					<?php endforeach; ?>

				<?php else : ?>
					<tr>
						<td colspan="7">
							<div class="wpsubs-empty">
								<div class="wpsubs-empty__icon">📋</div>
								<div class="wpsubs-empty__title"><?php esc_html_e( 'No subscriptions found', 'subscription' ); ?></div>
								<div class="wpsubs-empty__desc"><?php esc_html_e( 'Subscriptions will appear here once customers complete checkout.', 'subscription' ); ?></div>
							</div>
						</td>
					</tr>
				<?php endif; ?>

				</tbody>
			</table>

			<!-- Pagination inside table card -->
			<?php
			$start_item = $total > 0 ? ( ( $paged - 1 ) * $per_page ) + 1 : 0;
			$end_item   = min( $paged * $per_page, $total );
			$base_url   = remove_query_arg( 'paged' );
			?>
			<div class="wpsubs-pagination">
				<span class="wpsubs-pagination__info">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: first item number, 2: last item number, 3: total count */
							__( 'Showing %1$s\u{2013}%2$s of %3$s subscriptions', 'subscription' ),
							number_format_i18n( $start_item ),
							number_format_i18n( $end_item ),
							number_format_i18n( $total )
						)
					);
					?>
				</span>
				<div class="wpsubs-pagination__nav">
					<?php if ( $paged > 1 ) : ?>
						<a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'paged'    => $paged - 1,
									'per_page' => $per_page,
								),
								$base_url
							)
						);
						?>
									" class="wpsubs-pagination__btn" aria-label="<?php esc_attr_e( 'Previous', 'subscription' ); ?>">&#8249;</a>
					<?php else : ?>
						<span class="wpsubs-pagination__btn wpsubs-pagination__btn--disabled">&#8249;</span>
					<?php endif; ?>

					<span class="wpsubs-pagination__label"><?php echo (int) $paged; ?> / <?php echo (int) max( 1, $max_num_pages ); ?></span>

					<?php if ( $paged < $max_num_pages ) : ?>
						<a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'paged'    => $paged + 1,
									'per_page' => $per_page,
								),
								$base_url
							)
						);
						?>
									" class="wpsubs-pagination__btn" aria-label="<?php esc_attr_e( 'Next', 'subscription' ); ?>">&#8250;</a>
					<?php else : ?>
						<span class="wpsubs-pagination__btn wpsubs-pagination__btn--disabled">&#8250;</span>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- /.wpsubs-table-card -->

	</form>

</div>

<script>
( function () {
	var selectAll = document.getElementById( 'cb-select-all' );
	var form      = document.getElementById( 'subscriptions-form' );

	function rowChecks() {
		return form ? Array.from( form.querySelectorAll( '.wpsubs-row-check' ) ) : [];
	}

	function syncSelectAll() {
		if ( ! selectAll ) return;
		var checks  = rowChecks();
		var checked = checks.filter( function ( cb ) { return cb.checked; } );
		checks.forEach( function ( cb ) {
			var row = cb.closest( 'tr' );
			if ( row ) row.classList.toggle( 'wpsubs-row--selected', cb.checked );
		} );
		selectAll.indeterminate = checked.length > 0 && checked.length < checks.length;
		selectAll.checked       = checks.length > 0 && checked.length === checks.length;
	}

	if ( selectAll ) {
		selectAll.addEventListener( 'change', function () {
			rowChecks().forEach( function ( cb ) { cb.checked = selectAll.checked; } );
			syncSelectAll();
		} );
	}

	if ( form ) {
		form.addEventListener( 'change', function ( e ) {
			if ( e.target.classList.contains( 'wpsubs-row-check' ) ) syncSelectAll();
		} );
	}

	// Row action dropdowns
	function closeAll() {
		document.querySelectorAll( '.wpsubs-row-actions--open' ).forEach( function ( el ) {
			el.classList.remove( 'wpsubs-row-actions--open' );
			var d = el.querySelector( '.wpsubs-dropdown' );
			if ( d ) d.classList.remove( 'wpsubs-dropdown--open' );
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var trigger = e.target.closest( '.wpsubs-row-actions__trigger' );

		// Close all other open menus
		document.querySelectorAll( '.wpsubs-row-actions--open' ).forEach( function ( el ) {
			if ( el !== trigger?.closest( '.wpsubs-row-actions' ) ) {
				el.classList.remove( 'wpsubs-row-actions--open' );
				var d = el.querySelector( '.wpsubs-dropdown' );
				if ( d ) d.classList.remove( 'wpsubs-dropdown--open' );
			}
		} );

		if ( ! trigger ) return;

		e.stopPropagation();
		var wrapper  = trigger.closest( '.wpsubs-row-actions' );
		var dropdown = wrapper && wrapper.querySelector( '.wpsubs-dropdown' );
		if ( ! wrapper || ! dropdown ) return;

		var isOpen = wrapper.classList.contains( 'wpsubs-row-actions--open' );
		wrapper.classList.toggle( 'wpsubs-row-actions--open', ! isOpen );
		dropdown.classList.toggle( 'wpsubs-dropdown--open', ! isOpen );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) closeAll();
	} );

	syncSelectAll();
}() );
</script>
