<?php
/**
 * Standalone subscription details page view.
 *
 * Rendered by Menu::render_subscription_details_page(). Matches the
 * WPSubscription admin design system (wpsubs-* components + :root tokens).
 *
 * @package SpringDevs\Subscription\Admin
 *
 * @var int       $subscription_id   Subscription post ID.
 * @var array     $subscription_data Structured data from Helper::get_subscription_data().
 * @var array     $rows              Filtered info rows from Subscriptions::get_info_rows().
 * @var \WC_Order $order             Related WooCommerce order.
 * @var mixed     $order_item        Related order line item.
 * @var array     $actions           Allowed action slugs for the current status.
 * @var array     $actions_data      Map of action slug => label/value.
 * @var array     $order_histories   Related order relation rows.
 * @var string    $list_url          Subscriptions list URL.
 * @var string    $form_action       URL the status form posts to.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SpringDevs\Subscription\Illuminate\Helper;

$product_name = $order_item ? $order_item->get_name() : '-';
$product_link = $order_item ? get_the_permalink( $order_item->get_product_id() ) : '';

$subscrpt_status = $subscription_data['status'] ?? get_post_status( $subscription_id );
$verbose_status  = Helper::get_verbose_status( $subscrpt_status );

$badge_mod_map = array(
	'active'       => 'active',
	'pending'      => 'pending',
	'pe_cancelled' => 'pending-cancel',
	'cancelled'    => 'cancelled',
	'expired'      => 'expired',
	'draft'        => 'draft',
	'trash'        => 'trash',
);
$badge_mod     = $badge_mod_map[ $subscrpt_status ] ?? 'expired';

$is_grace_period = isset( $subscription_data['grace_period'] );
$grace_remaining = $subscription_data['grace_period']['remaining_days'] ?? 0;
$grace_end_date  = $subscription_data['grace_period']['end_date'] ?? '';
$grace_end_date  = ! empty( $grace_end_date ) ? wp_date( 'F j, Y - g:i A', strtotime( $grace_end_date ) ) : '';

if ( $is_grace_period && $grace_remaining > 0 ) {
	$badge_mod      = 'active';
	$verbose_status = __( 'Active', 'subscription' );
}

$customer_id    = $order ? $order->get_customer_id() : 0;
$customer_name  = $order ? $order->get_formatted_billing_full_name() : '-';
$customer_email = $order ? $order->get_billing_email() : '';
$customer_phone = $order ? $order->get_billing_phone() : '';
$customer_url   = $customer_id ? admin_url( 'user-edit.php?user_id=' . $customer_id ) : '';

$view_subs_endpoint = \SpringDevs\Subscription\Illuminate\Subscription\Subscription::get_user_endpoint( 'view_subs' );
$subs_frontend_url  = $order ? wc_get_endpoint_url( $view_subs_endpoint, $subscription_id, wc_get_page_permalink( 'myaccount' ) ) : '';
?>
<div class="wp-subscription-admin-content list-page subscrpt-subs-details">

	<!-- Page header -->
	<div class="subscrpt-detail-head">
		<div>
			<div class="subscrpt-detail-head__title">
				<?php echo esc_html( $product_name ); ?>
				<span class="wpsubs-cell-id">#<?php echo (int) $subscription_id; ?></span>
			</div>
			<div class="subscrpt-detail-head__badge">
				<span class="wpsubs-badge wpsubs-badge--<?php echo esc_attr( $badge_mod ); ?>">
					<?php echo esc_html( $verbose_status ); ?>
					<?php if ( $is_grace_period && $grace_remaining > 0 ) : ?>
						<span class="dashicons dashicons-warning" style="font-size:11px;width:11px;height:11px;color:#d97706;" title="<?php echo esc_attr( sprintf( /* translators: %d: days remaining */ __( '%d days remaining in grace period', 'subscription' ), $grace_remaining ) ); ?>"></span>
					<?php endif; ?>
				</span>
			</div>
		</div>
		<a href="<?php echo esc_url( $list_url ); ?>" class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm">
			<span class="dashicons dashicons-arrow-left-alt2" style="font-size:15px;width:15px;height:15px;"></span>
			<?php esc_html_e( 'Back to subscriptions', 'subscription' ); ?>
		</a>
	</div>

	<div class="subscrpt-detail-grid">

		<!-- Main column -->
		<div class="subscrpt-detail-main">

			<!-- Details card -->
			<div class="subscrpt-card">
				<div class="subscrpt-card__head"><?php esc_html_e( 'Subscription Details', 'subscription' ); ?></div>
				<div class="subscrpt-card__body">
					<?php if ( ! empty( $rows ) ) : ?>
						<table class="subscrpt-kv">
							<tbody>
								<?php foreach ( $rows as $row ) : ?>
									<tr>
										<th><?php echo esc_html( $row['label'] ); ?></th>
										<td><?php echo wp_kses_post( $row['value'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="subscrpt-muted"><?php esc_html_e( 'No subscription details available.', 'subscription' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Related orders card -->
			<div class="subscrpt-card">
				<div class="subscrpt-card__head"><?php esc_html_e( 'Related Orders', 'subscription' ); ?></div>
				<div class="wpsubs-table-card" style="box-shadow:none;border:0;border-radius:0;">
					<?php if ( ! empty( $order_histories ) ) : ?>
						<table class="wpsubs-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Order', 'subscription' ); ?></th>
									<th><?php esc_html_e( 'Type', 'subscription' ); ?></th>
									<th><?php esc_html_e( 'Date', 'subscription' ); ?></th>
									<th><?php esc_html_e( 'Status', 'subscription' ); ?></th>
									<th><?php esc_html_e( 'Total', 'subscription' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $order_histories as $history ) : ?>
									<?php $related_order = wc_get_order( $history->order_id ); ?>
									<?php if ( $related_order ) : ?>
										<tr>
											<td>
												<a href="<?php echo esc_url( $related_order->get_edit_order_url() ); ?>" target="_blank" class="wpsubs-cell-title">
													#<?php echo esc_html( $related_order->get_order_number() ); ?>
												</a>
											</td>
											<td><?php echo esc_html( ucfirst( str_replace( '-', ' ', $history->type ) ) ); ?></td>
											<td><?php echo esc_html( $related_order->get_date_created()->date( 'M j, Y - g:i A' ) ); ?></td>
											<td><?php echo esc_html( wc_get_order_status_name( $related_order->get_status() ) ); ?></td>
											<td><?php echo wp_kses_post( $related_order->get_formatted_order_total() ); ?></td>
										</tr>
									<?php endif; ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="subscrpt-muted" style="padding:14px 16px;"><?php esc_html_e( 'No related orders found.', 'subscription' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Activities card -->
			<?php $subscrpt_pro_on = function_exists( 'subscrpt_pro_activated' ) && subscrpt_pro_activated(); ?>
			<div class="subscrpt-card">
				<div class="subscrpt-card__head"><?php esc_html_e( 'Subscription Activities', 'subscription' ); ?></div>
				<div class="subscrpt-card__body">
					<?php if ( $subscrpt_pro_on ) : ?>
						<?php
						/**
						 * Fires inside the subscription details activities card.
						 *
						 * The Pro plugin renders the activity timeline here.
						 *
						 * @param int $subscription_id Subscription post ID.
						 */
						do_action( 'subscrpt_order_activities', $subscription_id );
						?>
					<?php else : ?>
						<div class="subscrpt-upgrade-banner">
							<div>
								<strong><?php esc_html_e( 'Upgrade to WPSubscription Pro', 'subscription' ); ?></strong>
								<p><?php esc_html_e( 'Track subscription activity history, automation, and more.', 'subscription' ); ?></p>
							</div>
							<a href="https://wpsubscription.co/" target="_blank" class="wpsubs-btn wpsubs-btn--primary wpsubs-btn--sm" rel="noreferrer noopener">
								<?php esc_html_e( 'Upgrade to Pro', 'subscription' ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- /.subscrpt-detail-main -->

		<!-- Sidebar column -->
		<div class="subscrpt-detail-side">

			<!-- Status action card -->
			<div class="subscrpt-card">
				<div class="subscrpt-card__head"><?php esc_html_e( 'Subscription Action', 'subscription' ); ?></div>
				<div class="subscrpt-card__body">
					<?php if ( ! empty( $actions ) ) : ?>
						<form method="post" action="<?php echo esc_url( $form_action ); ?>" class="subscrpt-action-form">
							<?php wp_nonce_field( 'subscrpt_order_action_nonce', 'subscrpt_order_action_nonce_field' ); ?>
							<?php
							$action_options = array();
							foreach ( $actions as $action_slug ) {
								if ( isset( $actions_data[ $action_slug ] ) ) {
									$action_options[] = array(
										'value' => $actions_data[ $action_slug ]['value'],
										'label' => $actions_data[ $action_slug ]['label'],
									);
								}
							}
							wpsubs_render_adv_select(
								array(
									'name'        => 'subscrpt_order_action',
									'placeholder' => __( 'Choose Action', 'subscription' ),
									'value'       => '',
									'options'     => $action_options,
									'class'       => 'subscrpt-action-select',
								)
							);
							?>
							<button type="submit" class="wpsubs-btn wpsubs-btn--primary" style="width:100%;justify-content:center;margin-top:10px;">
								<?php esc_html_e( 'Process', 'subscription' ); ?>
							</button>
						</form>
					<?php else : ?>
						<p class="subscrpt-muted"><?php esc_html_e( 'No actions available for this status.', 'subscription' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Customer card -->
			<div class="subscrpt-card">
				<div class="subscrpt-card__head"><?php esc_html_e( 'Customer Details', 'subscription' ); ?></div>
				<div class="subscrpt-card__body">
					<?php if ( $order ) : ?>
						<div class="subscrpt-kv-group">
							<span class="subscrpt-kv-group__label"><?php esc_html_e( 'Customer', 'subscription' ); ?></span>
							<div class="subscrpt-kv-group__value">
								<?php if ( $customer_url ) : ?>
									<a href="<?php echo esc_url( $customer_url ); ?>" target="_blank"><?php echo esc_html( $customer_name ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $customer_name ); ?>
								<?php endif; ?>
								<?php if ( $customer_email ) : ?>
									<br><a href="mailto:<?php echo esc_attr( $customer_email ); ?>"><?php echo esc_html( $customer_email ); ?></a>
								<?php endif; ?>
								<?php if ( $customer_phone ) : ?>
									<br><a href="tel:<?php echo esc_attr( $customer_phone ); ?>"><?php echo esc_html( $customer_phone ); ?></a>
								<?php endif; ?>
							</div>
						</div>

						<div class="subscrpt-kv-group">
							<span class="subscrpt-kv-group__label"><?php esc_html_e( 'Billing', 'subscription' ); ?></span>
							<div class="subscrpt-kv-group__value subscrpt-address">
								<?php echo wp_kses_post( $order->get_formatted_billing_address() ? $order->get_formatted_billing_address() : __( 'No billing address set.', 'subscription' ) ); ?>
							</div>
						</div>

						<div class="subscrpt-kv-group">
							<span class="subscrpt-kv-group__label"><?php esc_html_e( 'Shipping', 'subscription' ); ?></span>
							<div class="subscrpt-kv-group__value subscrpt-address">
								<?php echo wp_kses_post( $order->get_formatted_shipping_address() ? $order->get_formatted_shipping_address() : __( 'No shipping address set.', 'subscription' ) ); ?>
							</div>
						</div>

						<div class="subscrpt-card__actions">
							<a class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" target="_blank" href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
								<?php esc_html_e( 'View Order', 'subscription' ); ?>
							</a>
							<?php if ( $subs_frontend_url ) : ?>
								<a class="wpsubs-btn wpsubs-btn--outline wpsubs-btn--sm" target="_blank" href="<?php echo esc_url( $subs_frontend_url ); ?>">
									<?php esc_html_e( 'View Frontend', 'subscription' ); ?>
								</a>
							<?php endif; ?>
						</div>
					<?php else : ?>
						<p class="subscrpt-muted"><?php esc_html_e( 'Order not found.', 'subscription' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $is_grace_period ) : ?>
				<!-- Grace period card -->
				<div class="subscrpt-card">
					<div class="subscrpt-card__head"><?php esc_html_e( 'Grace Period', 'subscription' ); ?></div>
					<div class="subscrpt-card__body">
						<table class="subscrpt-kv">
							<tbody>
								<tr>
									<th><?php esc_html_e( 'Remaining', 'subscription' ); ?></th>
									<td><?php echo esc_html( sprintf( /* translators: %d: days */ _n( '%d day', '%d days', (int) $grace_remaining, 'subscription' ), (int) $grace_remaining ) ); ?></td>
								</tr>
								<?php if ( $grace_end_date ) : ?>
									<tr>
										<th><?php esc_html_e( 'End Date', 'subscription' ); ?></th>
										<td><?php echo esc_html( $grace_end_date ); ?></td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>

		</div><!-- /.subscrpt-detail-side -->

	</div><!-- /.subscrpt-detail-grid -->

</div>

<style>
.subscrpt-subs-details .subscrpt-detail-head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 16px;
	margin-bottom: 20px;
	padding-bottom: 16px;
	border-bottom: 1px dashed var(--wpsubs-border-strong);
}
.subscrpt-detail-head__title {
	font-size: 1.375rem;
	font-weight: 700;
	color: var(--wpsubs-text);
	line-height: 1.2;
	display: flex;
	align-items: center;
	gap: 8px;
}
.subscrpt-detail-head__badge { margin-top: 8px; }

.subscrpt-detail-grid {
	display: grid;
	grid-template-columns: minmax(0, 1fr) 340px;
	gap: 20px;
	align-items: start;
}
@media (max-width: 960px) {
	.subscrpt-detail-grid { grid-template-columns: 1fr; }
}
.subscrpt-detail-main, .subscrpt-detail-side {
	display: flex;
	flex-direction: column;
	gap: 20px;
	min-width: 0;
}

.subscrpt-card {
	background: var(--wpsubs-surface);
	border: 1px solid var(--wpsubs-border);
	border-radius: var(--wpsubs-radius);
	box-shadow: var(--wpsubs-shadow);
	overflow: hidden;
}
.subscrpt-card__head {
	padding: 12px 16px;
	font-size: 13px;
	font-weight: 600;
	color: var(--wpsubs-text);
	border-bottom: 1px solid var(--wpsubs-border);
	background: var(--wpsubs-surface-muted);
}
.subscrpt-card__body { padding: 16px; }
.subscrpt-card__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 14px;
}

.subscrpt-kv { width: 100%; border-collapse: collapse; }
.subscrpt-kv th, .subscrpt-kv td {
	text-align: left;
	padding: 9px 0;
	font-size: 13px;
	vertical-align: top;
	border-bottom: 1px solid var(--wpsubs-border);
}
.subscrpt-kv tr:last-child th, .subscrpt-kv tr:last-child td { border-bottom: 0; }
.subscrpt-kv th {
	width: 40%;
	font-weight: 500;
	color: var(--wpsubs-text-muted);
	padding-right: 12px;
}
.subscrpt-kv td { color: var(--wpsubs-text); }
.subscrpt-kv a { color: var(--wpsubs-brand); text-decoration: none; }
.subscrpt-kv a:hover { text-decoration: underline; }

.subscrpt-kv-group {
	padding: 0 0 12px;
	margin-bottom: 12px;
	border-bottom: 1px solid var(--wpsubs-border);
	font-size: 13px;
}
.subscrpt-kv-group:last-child { border-bottom: 0; margin-bottom: 0; padding-bottom: 0; }
.subscrpt-kv-group__label {
	display: block;
	font-weight: 600;
	color: var(--wpsubs-text-muted);
	margin-bottom: 4px;
}
.subscrpt-kv-group__value { color: var(--wpsubs-text); line-height: 1.5; }
.subscrpt-kv-group__value a { color: var(--wpsubs-brand); text-decoration: none; }
.subscrpt-kv-group__value a:hover { text-decoration: underline; }
.subscrpt-address { font-size: 12px; color: var(--wpsubs-text-muted); line-height: 1.5; }

.subscrpt-subs-details .subscrpt-muted { color: var(--wpsubs-text-muted); font-size: 13px; margin: 0; }
.subscrpt-action-form .wpsubs-adv-select { width: 100%; }
.subscrpt-action-form .wpsubs-adv-select__trigger { width: 100%; }

.subscrpt-upgrade-banner {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	flex-wrap: wrap;
	padding: 16px;
	border: 1px solid var(--wpsubs-brand);
	border-radius: var(--wpsubs-radius-sm);
	background: var(--wpsubs-brand-light);
}
.subscrpt-upgrade-banner strong { color: var(--wpsubs-text); font-size: 14px; }
.subscrpt-upgrade-banner p { margin: 4px 0 0; font-size: 13px; color: var(--wpsubs-text-muted); }
</style>
