<?php
/**
 * Mail template for Subscription cancelled (Customer).
 *
 * @var string $email_heading Email Heading.
 * @var int $id Subscription id.
 * @var string $product_name Product name.
 * @var int $qty Subscription Quantity.
 * @var string $amount Subscription Amount with price format.
 * @var string $view_subscription_url Subscription view URL.
 *
 * @package SpringDevs\Subscription
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<?php do_action( 'woocommerce_email_header', $email_heading ); ?>

<p>
	<?php
	// translators: <b></b> tag.
	echo wp_kses_post( sprintf( __( 'Your subscription is %1$s Cancelled! %2$s', 'subscription' ), '<b>', '</b>' ) );
	?>
</p>

<table cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:separate;border-spacing:0;border:1px solid #e3e3e8;border-radius:6px;overflow:hidden;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:14px;">
	<tbody>
		<tr>
			<th scope="row" style="width:40%;padding:10px 14px;text-align:left;vertical-align:top;font-weight:600;color:#50575e;background-color:#f6f7f7;"><?php esc_html_e( 'Subscription Id', 'subscription' ); ?></th>
			<td style="padding:10px 14px;text-align:left;vertical-align:top;color:#1d2327;"><?php echo esc_html( $id ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="width:40%;padding:10px 14px;text-align:left;vertical-align:top;font-weight:600;color:#50575e;background-color:#f6f7f7;border-top:1px solid #e3e3e8;"><?php esc_html_e( 'Product', 'subscription' ); ?></th>
			<td style="padding:10px 14px;text-align:left;vertical-align:top;color:#1d2327;border-top:1px solid #e3e3e8;"><?php echo esc_html( $product_name ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="width:40%;padding:10px 14px;text-align:left;vertical-align:top;font-weight:600;color:#50575e;background-color:#f6f7f7;border-top:1px solid #e3e3e8;"><?php esc_html_e( 'Qty', 'subscription' ); ?></th>
			<td style="padding:10px 14px;text-align:left;vertical-align:top;color:#1d2327;border-top:1px solid #e3e3e8;"><?php echo esc_html( $qty ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="width:40%;padding:10px 14px;text-align:left;vertical-align:top;font-weight:600;color:#50575e;background-color:#f6f7f7;border-top:1px solid #e3e3e8;"><?php esc_html_e( 'Amount', 'subscription' ); ?></th>
			<td style="padding:10px 14px;text-align:left;vertical-align:top;color:#1d2327;border-top:1px solid #e3e3e8;"><?php echo wp_kses_post( $amount ); ?></td>
		</tr>
	</tbody>
</table>

<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:24px 0;">
	<tr>
		<td align="center">
			<a href="<?php echo esc_url( $view_subscription_url ); ?>"
				style="display:inline-block;padding:12px 24px;background-color:#7f54b3;color:#ffffff;text-decoration:none;font-size:14px;font-weight:600;border-radius:4px;">
				<?php esc_html_e( 'View subscription', 'subscription' ); ?>
			</a>
		</td>
	</tr>
</table>

<?php do_action( 'woocommerce_email_footer' ); ?>
