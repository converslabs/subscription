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

echo esc_html( '= ' . $email_heading . " =\n\n" );

// translators: first is older status and last is newly updated status.
$opening_paragraph = __( 'You have only %1$s %2$s left! Please renew the subscription before expired', 'wp_subscription' );

echo wp_kses_post( sprintf( $opening_paragraph, $num_of_days_before, $num_of_days_before > 1 ? 'days' : 'day' ) . "\n\n" );

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// translators: Subscription id.
echo esc_html( sprintf( __( 'Subscription Id: %s', 'wp_subscription' ), $id ) . "\n" );

// translators: Product name.
echo esc_html( sprintf( __( 'Product: %s', 'wp_subscription' ), $product_name ) . "\n" );

// translators: Subscription quantity.
echo esc_html( sprintf( __( 'Qty: %s', 'wp_subscription' ), $qty ) . "\n" );

// translators: Subscription amount.
echo wp_kses_post( sprintf( __( 'Amount: %s', 'wp_subscription' ), $amount ) . "\n" );


echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo wp_kses_post(
	make_clickable(
		sprintf(
		// translators: subscription id.
			__( 'You can view the subscription here: %s', 'wp_subscription' ),
			wc_get_endpoint_url( 'view-subscription', $id, wc_get_page_permalink( 'myaccount' ) )
		)
	)
);
echo esc_html( "\n\n" );

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
