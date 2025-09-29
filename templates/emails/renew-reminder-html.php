<?php
/**
 * Mail template for Subscription status changed (Admin).
 *
 * @var string $email_heading Email Heading.
 * @var int $id Subscription id.
 * @var string $product_name Product name.
 * @var int $qty Subscription Quantity.
 * @var string $amount Subscription Amount with price format.
 * @var int $num_of_days_before Number of days before.
 */

?>

<?php do_action( 'woocommerce_email_header', $email_heading ); ?>

<p>
<?php
echo esc_html(
	sprintf(
	// translators: Number of days before & day|days.
		__( 'You have only %1$s %2$s left! Please renew the subscription before expired', 'wp_subscription' ),
		$num_of_days_before,
		$num_of_days_before > 1 ? 'days' : 'day'
	)
);
?>
</p>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
	<tbody>
	<tr>
		<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php esc_html_e( 'Subscription Id', 'wp_subscription' ); ?></th>
		<td style="text-align:left; border: 1px solid #eee;"><?php echo esc_html( $id ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php esc_html_e( 'Product', 'wp_subscription' ); ?></th>
		<td style="text-align:left; border: 1px solid #eee;"><?php echo esc_html( $product_name ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php esc_html_e( 'Qty', 'wp_subscription' ); ?></th>
		<td style="text-align:left; border: 1px solid #eee;"><?php echo esc_html( $qty ); ?></td>
	</tr>
	<tr>
		<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php esc_html_e( 'Amount', 'wp_subscription' ); ?></th>
		<td style="text-align:left; border: 1px solid #eee;"><?php echo wp_kses_post( $amount ); ?></td>
	</tr>
	</tbody>
</table>
<br>
<p>
	<?php
	echo wp_kses_post(
		make_clickable(
			sprintf(
			// translators: subscription id.
				__( 'You can view the subscription here: %s', 'wp_subscription' ),
				wc_get_endpoint_url( 'view-subscription', $id, wc_get_page_permalink( 'myaccount' ) )
			)
		)
	);
	?>
</p>

<?php do_action( 'woocommerce_email_footer' ); ?>
