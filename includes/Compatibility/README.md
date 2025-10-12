# WooCommerce Subscriptions Compatibility Layer

## Quick Start

This compatibility layer makes WPSubscription fully compatible with WooCommerce Subscriptions APIs, enabling all WCS-compatible payment gateways to work without modifications.

## Status Check

### Admin Dashboard

Navigate to: **WP Admin → WP Subscription → Compatibility**

### Command Line

```bash
wp wpsubscription compat status
wp wpsubscription compat test
```

## What's Included

- ✓ 6 WCS Classes (WC_Subscription, etc.)
- ✓ 15+ WCS Functions (wcs_is_subscription, etc.)
- ✓ Complete Hook Translation System
- ✓ Automatic Gateway Integration
- ✓ Admin Status Dashboard
- ✓ WP-CLI Test Commands

## Supported Gateways

- Stripe (woo-stripe-payment)
- PayPal Standard & Express
- Mollie Payments
- Razorpay
- WooCommerce Payments
- Square

## Documentation

- [COMPATIBILITY_LAYER.md](../../docs/COMPATIBILITY_LAYER.md) - Full API documentation
- [COMPATIBILITY_TESTING.md](../../docs/COMPATIBILITY_TESTING.md) - Testing guide
- [IMPLEMENTATION_COMPLETE.md](../../docs/IMPLEMENTATION_COMPLETE.md) - Implementation summary

## Architecture

```
Bootstrap.php
├── Classes/ (6 WCS wrapper classes)
├── Functions/ (15+ core functions)
├── Hooks/ (Hook translation system)
├── Gateways/ (Gateway integration)
├── Admin/ (Status dashboard)
├── CLI/ (WP-CLI commands)
└── Utils/ (Logger, checker)
```

## Key Files

- **Bootstrap.php** - Main orchestrator
- **ConflictDetector.php** - WCS conflict detection
- **Classes/WC_Subscription.php** - Main subscription class
- **Functions/CoreFunctions.php** - Core WCS functions
- **Gateways/GatewayCompatibility.php** - Gateway integration
- **Admin/StatusPage.php** - Admin dashboard

## Usage Examples

### Check if Product is Subscription

```php
if ( wcs_is_subscription_product( $product ) ) {
    // Handle subscription
}
```

### Get User Subscriptions

```php
$subscriptions = wcs_get_users_subscriptions( $user_id );
```

### Check Order Contains Subscription

```php
if ( wcs_order_contains_subscription( $order ) ) {
    // Process subscription order
}
```

## Testing

### Quick Test

```bash
wp wpsubscription compat test
```

### Component Tests

```bash
wp wpsubscription compat test --component=functions
wp wpsubscription compat test --component=classes
wp wpsubscription compat test --component=gateways
```

## Troubleshooting

1. **Check Status**: `wp wpsubscription compat status`
2. **Run Tests**: `wp wpsubscription compat test`
3. **View Dashboard**: WP Admin → WP Subscription → Compatibility
4. **Check Logs**: Look for "WPSubscription Compat" entries

## Requirements

- WordPress 6.0+
- WooCommerce 6.0+
- WPSubscription 1.0+
- PHP 7.4+

## License

GPL-2.0+
