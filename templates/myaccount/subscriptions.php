<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Subscriptions Table
 *
 * @var int $current_page
 * @var WP_Query $postslist
 *
 * This template can be overridden by copying it to <your_theme>/subscription/myaccount/subscriptions.php
 */

use SpringDevs\Subscription\Illuminate\Helper;
use SpringDevs\Subscription\Illuminate\Subscription\Subscription;
?>

<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table my_account_subscrpt">
	<thead>
		<tr>
			<th scope="col" class="subscrpt-id"><?php esc_html_e( 'Subscription', 'wp_subscription' ); ?></th>
			<th scope="col" class="order-status"><?php esc_html_e( 'Status', 'wp_subscription' ); ?></th>
			<th scope="col" class="order-product"><?php esc_html_e( 'Product', 'wp_subscription' ); ?></th>
			<th scope="col" class="subscrpt-next-date"><?php esc_html_e( 'Next Payment', 'wp_subscription' ); ?></th>
			<th scope="col" class="subscrpt-total"><?php esc_html_e( 'Total', 'wp_subscription' ); ?></th>
			<th scope="col" class="subscrpt-action">Actions</th>
		</tr>
	</thead>
	<tbody>
		<?php
		if ( $postslist->have_posts() ) :
			while ( $postslist->have_posts() ) :
				$postslist->the_post();

				$subscription_id   = get_the_ID();
				$subscription_data = Helper::get_subscription_data( $subscription_id );

				$subscrpt_status = $subscription_data['status'] ?? '';
				$verbose_status  = Helper::get_verbose_status( $subscrpt_status );

				$order_id      = $subscription_data['order']['order_id'] ?? 0;
				$order_item_id = $subscription_data['order']['order_item_id'] ?? 0;

				$order      = wc_get_order( $order_id );
				$order_item = $order->get_item( $order_item_id );

				$product_id   = $subscription_data['product']['product_id'] ?? 0;
				$product_name = $order_item->get_name();

				$start_date = $subscription_data['start_date'] ?? '';
				$start_date = ! empty( $start_date ) ? wp_date( 'F j, Y', strtotime( $start_date ) ) : '-';

				$next_date = $subscription_data['next_date'] ?? '';
				$next_date = ! empty( $next_date ) ? wp_date( 'F j, Y', strtotime( $next_date ) ) : '-';

				$trial      = get_post_meta( get_the_ID(), '_subscrpt_trial', true );
				$trial_mode = get_post_meta( get_the_ID(), '_subscrpt_trial_mode', true );
				$trial_mode = empty( $trial_mode ) ? 'off' : $trial_mode;

				$price          = $subscription_data['price'] ?? 0;
				$price_excl_tax = (float) $order_item->get_total();
				$tax_amount     = (float) $order_item->get_total_tax();

				if ( $tax_amount > 0 ) {
					$price = $price_excl_tax + $tax_amount;
					$price = number_format( (float) $price, 2, '.', '' );
				}

				$product_price_html = Helper::format_price_with_order_item( $price, $order_item->get_id() );

				$is_grace_period = isset( $subscription_data['grace_period'] );
				$grace_remaining = $subscription_data['grace_period']['remaining_days'] ?? 0;

				$my_account_page_id = get_option( 'woocommerce_myaccount_page_id' );
				$my_account_url     = get_permalink( $my_account_page_id );
				$view_sub_endpoint  = Subscription::get_user_endpoint( 'view_subs' );
				$view_sub_url       = wc_get_endpoint_url( $view_sub_endpoint, get_the_ID(), $my_account_url );
				?>

				<tr>
					<td data-title="Subscription"><?php the_ID(); ?></td>

					<td data-title="Status">
						<?php if ( $is_grace_period && $grace_remaining > 0 ) : ?>
							<span class="subscrpt-active grace-active">
								Active

								<?php
									$grace_remaining_text = sprintf(
										// translators: Number of days remaining in grace period.
										__( '%d days remaining!', 'wp_subscription' ),
										$grace_remaining
									);
								?>
								<span class="grace-icon" data-tooltip="<?php echo esc_attr( $grace_remaining_text ); ?>">
									<span class="dashicons dashicons-warning"></span>
								</span>
							</span>
						<?php else : ?>
							<span class="subscrpt-<?php echo esc_attr( strtolower( $subscrpt_status ) ); ?>">
								<?php echo esc_html( strlen( $verbose_status ) > 9 ? substr( $verbose_status, 0, 9 ) . '...' : $verbose_status ); ?>
							</span>
						<?php endif; ?>
					</td>
					
					<td data-title="Product"><?php echo esc_html( $product_name ); ?></td>
					
					<?php if ( 'on' !== $trial_mode ) : ?>
						<td data-title="Next Payment"><?php echo esc_html( $next_date ); ?></td>
					<?php else : ?>
						<td data-title="Next Payment"><small>First Billing : </small><?php echo esc_html( $start_date ); ?></td>
					<?php endif; ?>

					<td data-title="Total"><?php echo wp_kses_post( $product_price_html ); ?></td>

					<td data-title="Actions">						
						<a href="<?php echo esc_url( $view_sub_url ); ?>" class="woocommerce-button <?php echo esc_attr( $wp_button_class ); ?> button view">
							<?php echo esc_html_e( 'View', 'wp_subscription' ); ?>
						</a>
					</td>
				</tr>
				<?php
			endwhile;
			wp_reset_postdata();
		else :
			?>
			<tr>
				<td colspan="6">
					<p style="text-align: center;">
						<?php echo esc_html_e( 'No subscriptions available yet.', 'wp_subscription' ); ?>
					</p>
				</td>
			</tr>
			<?php
		endif;
		?>
	</tbody>
</table>

<?php if ( 1 < $postslist->max_num_pages ) : ?>
	<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
		<?php if ( 1 !== $current_page ) : ?>
			<a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button<?php echo esc_attr( $wp_button_class ); ?>" href="<?php echo esc_url( wc_get_endpoint_url( 'subscriptions', $current_page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'wp_subscription' ); ?></a>
		<?php endif; ?>

		<?php if ( intval( $postslist->max_num_pages ) !== $current_page ) : ?>
			<a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button<?php echo esc_attr( $wp_button_class ); ?>" href="<?php echo esc_url( wc_get_endpoint_url( 'subscriptions', $current_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'wp_subscription' ); ?></a>
		<?php endif; ?>
	</div>
<?php endif; ?>
