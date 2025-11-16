# Gateway Integration Guide

## How New Gateways Work with WPSubscription Compatibility Layer

### Overview

The compatibility layer automatically supports new payment gateways that integrate with WooCommerce Subscriptions (WCS) without requiring custom code for each gateway. Here's how it works:

## Automatic Gateway Support Flow

### 1. **Hook Registration Pattern**

WooCommerce Subscriptions triggers gateway-specific hooks using this pattern:

```php
do_action( 'woocommerce_scheduled_subscription_payment_{gateway_id}', $amount, $renewal_order );
```

For example:

- Stripe: `woocommerce_scheduled_subscription_payment_stripe`
- Razorpay: `woocommerce_scheduled_subscription_payment_razorpay`
- PayPal: `woocommerce_scheduled_subscription_payment_paypal`

### 2. **Compatibility Layer Processing**

The compatibility layer works in this order:

1. **Action Scheduler** triggers `woocommerce_scheduled_subscription_payment` with subscription ID
2. **Hook Registry** bridges this to `wps_wcs_process_renewal_payment`
3. **Generic Gateway Adapter** handles any gateway without a specific adapter
4. **Specific Gateway Adapters** (if they exist) handle gateway-specific logic

### 3. **Dynamic Gateway Detection**

When a renewal payment is due:

```
Action Scheduler (scheduled task)
    ↓
woocommerce_scheduled_subscription_payment (subscription_id)
    ↓
ActionScheduler::handle_renewal_payment()
    ↓
wps_wcs_process_renewal_payment (subscription_id)
    ↓
[Gateway Adapters listen]
    ├─ StripeAdapter (if Stripe payment method)
    ├─ RazorpayAdapter (if Razorpay payment method)
    ├─ MollieAdapter (if Mollie payment method)
    ├─ PayoneerAdapter (if Payoneer payment method)
    └─ GenericGatewayAdapter (for any other gateway)
```

## How Gateways Get Registered

### Automatic Registration

Gateways that integrate with WooCommerce Subscriptions are automatically detected because they:

1. Register their gateway ID with WooCommerce
2. Listen to `woocommerce_scheduled_subscription_payment_{gateway_id}`
3. Have `supports( 'subscriptions' )` capability

### Gateway-Specific Adapters (Optional)

For gateways that need special handling, create an adapter:

```php
class MyGatewayAdapter {
    public static function instance() {
        // Singleton pattern
    }

    private function __construct() {
        // Listen to WCS compatibility actions
        add_action( 'wps_wcs_process_renewal_payment', array( $this, 'handle_renewal' ), 20, 1 );
        add_action( 'woocommerce_scheduled_subscription_payment_my_gateway', array( $this, 'handle_scheduled_payment' ), 10, 2 );
    }

    public function handle_renewal( $subscription_id ) {
        if ( ! $this->is_my_gateway_subscription( $subscription_id ) ) {
            return;
        }
        // Gateway-specific logic
    }
}
```

Register in `Bootstrap.php`:

```php
\SpringDevs\Subscription\Compat\WooSubscriptions\Gateways\MyGatewayAdapter::instance();
```

## Generic Gateway Adapter (Fallback)

For gateways without specific adapters, the `GenericGatewayAdapter`:

1. Creates renewal orders via WPSubscription Helper
2. Preserves payment method metadata
3. Sets order status to pending for manual processing
4. Triggers compatibility hooks for gateway plugins to handle

## What Gateway Developers Need to Know

### For Gateway Plugin Developers

If you're developing a gateway plugin that works with WooCommerce Subscriptions:

#### Standard Integration (Works Automatically)

Your gateway plugin should:

1. **Extend `WC_Payment_Gateway`** and declare subscription support:

   ```php
   public function __construct() {
       $this->supports = array( 'subscriptions', 'subscription_cancellation', 'subscription_suspension', 'subscription_reactivation' );
   }
   ```

2. **Listen to scheduled payment hooks**:

   ```php
   add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
   ```

3. **Process renewals** when the hook fires:
   ```php
   public function scheduled_subscription_payment( $amount, $renewal_order ) {
       // Your gateway logic here
       // The renewal_order is a WC_Order object
   }
   ```

#### This Will Work Automatically Because:

- The compatibility layer creates `shop_subscription` posts that mirror WPSubscription subscriptions
- The Action Scheduler triggers `woocommerce_scheduled_subscription_payment_{gateway_id}` hooks
- Your gateway plugin listens to these hooks as usual
- The renewal orders are created and linked properly

### Optional: WPSubscription-Specific Integration

For tighter integration with WPSubscription, listen to WPSubscription's internal hooks:

```php
// Listen to WPSubscription renewal order creation
add_action( 'subscrpt_after_create_renew_order', array( $this, 'handle_renewal_order' ), 10, 4 );

// Listen to WPSubscription compatibility actions
add_action( 'wps_wcs_process_renewal_payment', array( $this, 'handle_renewal_payment' ), 10, 1 );
add_action( 'wps_wcs_subscription_renewal_payment_complete', array( $this, 'handle_payment_complete' ), 10, 2 );
```

## Testing New Gateways

### Test Checklist

1. ✅ Create a subscription using the gateway
2. ✅ Verify `shop_subscription` post is created (via SyncService)
3. ✅ Verify Action Scheduler task is created for next payment
4. ✅ Trigger scheduled payment (or wait for schedule)
5. ✅ Verify renewal order is created
6. ✅ Verify gateway hook `woocommerce_scheduled_subscription_payment_{gateway_id}` fires
7. ✅ Verify payment is processed correctly

### Manual Testing

You can manually trigger a scheduled payment:

```php
// In WordPress admin or via WP-CLI
do_action( 'woocommerce_scheduled_subscription_payment_{gateway_id}', $amount, $renewal_order_id );
```

## Limitations & Considerations

### Gateways That May Need Custom Adapters

1. **Gateways with complex tokenization** (e.g., SEPA mandates)
2. **Gateways with API-driven renewals** (not webhook-based)
3. **Gateways requiring special metadata handling**
4. **Gateways with retry logic that differs from standard WCS**

### What Happens Without a Specific Adapter

- Renewal orders are created automatically
- Orders are set to "pending" status
- Gateway hooks still fire normally
- Gateway plugin handles payment processing as usual
- Manual intervention may be needed for complex scenarios

## Summary

**For Most Gateways**: No code changes needed! If your gateway works with WooCommerce Subscriptions, it will work with WPSubscription through the compatibility layer.

**For Special Cases**: Create a gateway adapter in `includes/Compat/WooSubscriptions/Gateways/` to handle gateway-specific requirements.
