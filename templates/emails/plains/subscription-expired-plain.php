<?php
/**
 * Mail template for Subscription status changed (Admin).
 *
 * @var string $email_heading Email Heading.
 * @var int $id Subscription id.
 * @var string $product_name Product name.
 * @var int $qty Subscription Quantity.
 * @var string $amount Subscription Amount with price format.
 * @var string $view_subscription_url Subscription view URL.
 */

echo esc_html( '= ' . $email_heading . " =\n\n" );

// translators: <b></b> tag.
$opening_paragraph = __( 'Your subscription is %1$s Expired! %2$s', 'wp_subscription' );

echo wp_kses_post( sprintf( $opening_paragraph, '<b>', '</b>' ) . "\n\n" );

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
			// translators: subscription url.
			__( 'You can view the subscription here: %s', 'wp_subscription' ),
			$view_subscription_url
		)
	)
);
echo esc_html( "\n\n" );

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
