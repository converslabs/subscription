# WooCommerce Subscriptions Compatibility Layer

## Overview

The WPSubscription Compatibility Layer provides full WooCommerce Subscriptions API compatibility, enabling payment gateway plugins designed for WooCommerce Subscriptions to work seamlessly with WPSubscription without any modifications.

## Architecture

```
Payment Gateways (Stripe, PayPal, etc.)
         ↓
WCS API Layer (Classes, Functions, Hooks)
         ↓
Compatibility Library ← WPSubscription Compatibility
         ↓
WPSubscription Core (unchanged)
```

## Features

### ✓ Complete Class Compatibility

- `WC_Subscription` - Full subscription object wrapper
- `WC_Subscriptions_Manager` - Subscription management helpers
- `WC_Subscriptions_Product` - Product subscription helpers
- `WC_Subscriptions_Order` - Order subscription helpers
- `WC_Subscriptions_Cart` - Cart subscription helpers
- `WC_Subscriptions_Change_Payment_Gateway` - Payment method change support

### ✓ Core Functions (15+)

All essential WooCommerce Subscriptions functions:

- `wcs_is_subscription()`
- `wcs_get_subscription()`
- `wcs_order_contains_subscription()`
- `wcs_get_users_subscriptions()`
- And more...

### ✓ Hook Translation System

- Automatic translation between WCS and WPS action/filter hooks
- Full hook registry for debugging
- Bidirectional hook support

### ✓ Payment Gateway Integration

Automatic subscription support for:

- Stripe (woo-stripe-payment)
- PayPal Standard & Express
- Mollie Payments
- Razorpay
- WooCommerce Payments
- Square
- And more...

### ✓ Admin Dashboard

- Real-time compatibility status monitoring
- Component health checks
- Gateway detection and reporting
- WooCommerce Subscriptions conflict detection

### ✓ WP-CLI Commands

```bash
wp wpsubscription compat test
wp wpsubscription compat status
```

## Installation

The compatibility layer is **automatically loaded** when WPSubscription is activated. No configuration needed.

## Verification

### Admin Dashboard

Navigate to: **WP Admin → WP Subscription → Compatibility**

View:

- Loaded components status
- Detected payment gateways
- System health
- Quick tests

### WP-CLI

```bash
# Run all compatibility tests
wp wpsubscription compat test

# Check status
wp wpsubscription compat status

# Test specific component
wp wpsubscription compat test --component=functions
```

## Gateway Support

The compatibility layer automatically detects and adds subscription support to compatible gateways:

### Fully Supported

- **Stripe** (woo-stripe-payment)
- **PayPal Standard**
- **PayPal Express Checkout**
- **Mollie** (all payment methods)
- **Razorpay**
- **Square**
- **WooCommerce Payments**

### How It Works

1. **Detection**: Scans active payment gateways
2. **Enhancement**: Adds subscription support features
3. **Hook Registration**: Registers scheduled payment hooks
4. **Metadata Cloning**: Copies payment tokens for renewals
5. **Automatic Processing**: Triggers gateway renewal payments

## Data Mapping

### Status Translation

| WPSubscription | WooCommerce Subscriptions |
| -------------- | ------------------------- |
| active         | active                    |
| on-hold        | on-hold                   |
| cancelled      | cancelled                 |
| pe_cancelled   | pending-cancel            |
| expired        | expired                   |

### Meta Key Translation

| WPSubscription           | WCS Equivalent       |
| ------------------------ | -------------------- |
| \_subscrpt_timing_option | \_billing_period     |
| \_subscrpt_timing_per    | \_billing_interval   |
| \_subscrpt_price         | \_subscription_price |
| \_subscrpt_trial         | \_schedule_trial     |
| \_subscrpt_order_id      | parent order ID      |

## Troubleshooting

### Issue: Gateway doesn't detect subscription support

**Solution**: Check if gateway is in compatible list. View: Admin → Compatibility Status

### Issue: Renewals not processing automatically

**Check**:

1. WPSubscription auto-renewal enabled
2. Payment method saved on original order
3. Gateway supports `scheduled_subscription_payment` method
4. Check WP Cron is working

### Issue: WooCommerce Subscriptions conflict

**Solution**: Deactivate WooCommerce Subscriptions plugin (WPSubscription replaces it)

## API Usage

### Check if Product is Subscription

```php
if ( wcs_is_subscription_product( $product ) ) {
    // Handle subscription product
}
```

### Get User Subscriptions

```php
$subscriptions = wcs_get_users_subscriptions( $user_id );
foreach ( $subscriptions as $subscription ) {
    echo $subscription->get_status();
}
```

### Check Order Contains Subscription

```php
if ( wcs_order_contains_subscription( $order ) ) {
    // Order has subscription products
}
```

## Extending the Compatibility Layer

### Add Custom Gateway Support

```php
add_filter( 'wpsubscription_compat_gateways', function( $gateways ) {
    $gateways[] = 'my_custom_gateway';
    return $gateways;
} );
```

### Hook into Compatibility Events

```php
// When compatibility layer loads
add_action( 'wpsubscription_compat_loaded', function( $components ) {
    // Your code here
} );

// When subscription status changes (WCS-style)
add_action( 'woocommerce_subscription_status_updated', function( $subscription_id, $new_status, $old_status ) {
    // Your code here
}, 10, 3 );
```

## Performance

- **Minimal Overhead**: Compatibility layer only loads what's needed
- **Lazy Loading**: Components load on-demand
- **No Database Changes**: Uses existing WPSubscription data structure
- **Cached Results**: Status and gateway detection results cached

## Security

- **Capability Checks**: All admin functions check `manage_woocommerce` capability
- **Nonce Verification**: All actions use WordPress nonces
- **Input Sanitization**: All inputs sanitized using WordPress functions
- **Output Escaping**: All outputs escaped using WordPress functions

## Support

For issues or questions:

1. Check Admin Dashboard → Compatibility Status
2. Run `wp wpsubscription compat test`
3. Review error logs
4. Contact WPSubscription support

## Version History

- **1.0.0** - Initial release with full WCS compatibility

## License

GPL-2.0+
