# Mollie Payment Gateway Integration

## Issue Resolved

**Error**: `Could not find classname for order ID 228`

**Cause**: The Mollie payment gateway plugin expects WooCommerce Subscriptions to be active and calls functions like `wcs_order_contains_subscription()`, `WC_Subscription`, etc. When these functions/classes don't exist, order creation fails and leaves a placeholder order.

**Solution**: The WPSubscription Compatibility Layer was restored, providing all necessary WooCommerce Subscriptions functions and classes that Mollie (and other gateways) expect.

---

## How the Compatibility Layer Works

### 1. **Function Translation**

The compatibility layer provides all standard WooCommerce Subscriptions functions:

```php
// Mollie calls this:
if ( wcs_order_contains_subscription( $order ) ) {
    // Handle subscription
}

// Our compatibility layer intercepts and translates to:
// Uses WPSubscription's internal subscription system
```

### 2. **Class Aliasing**

Provides drop-in replacement classes:

```php
// Mollie creates:
$subscription = new WC_Subscription( $id );

// Our WC_Subscription class extends WC_Order and:
// - Maps to WPSubscription's subscrpt_order post type
// - Translates method calls to WPSubscription data structure
// - Returns data in WCS-compatible format
```

### 3. **Hook Compatibility**

Registers all WooCommerce Subscriptions hooks that gateways listen for:

```php
// Mollie listens for:
add_action( 'woocommerce_scheduled_subscription_payment_mollie_wc_gateway_creditcard', ... );

// Our compatibility layer:
// - Registers these hooks
// - Triggers them during renewal payments
// - Passes WCS-compatible data
```

---

## Mollie Gateway Status

### Detected Mollie Gateways

1. **Card (mollie_wc_gateway_creditcard)**
   - Status: Enabled ✓
   - Subscription Support: YES ✓
   - Compatible: YES ✓
   - **Ready for subscription payments**

2. **iDEAL (mollie_wc_gateway_ideal)**
   - Status: Enabled ✓
   - Subscription Support: NO (will be added automatically)
   - Compatible: YES ✓
   - **Will gain subscription support automatically**

3. **Pay with Klarna (mollie_wc_gateway_klarna)**
   - Status: Enabled ✓
   - Subscription Support: NO (will be added automatically)
   - Compatible: YES ✓
   - **Will gain subscription support automatically**

---

## What Happens During Checkout

### 1. Customer Places Order

```
Customer → Checkout → Mollie Gateway
                            ↓
                Calls: wcs_order_contains_subscription()
                            ↓
            WPSubscription Compatibility Layer
                            ↓
            Checks if order has subscription products
                            ↓
            Returns: true/false to Mollie
```

### 2. Subscription Creation

```
Order Completed → WPSubscription creates subscrpt_order
                            ↓
                Compatibility Layer wraps as WC_Subscription
                            ↓
                Mollie can access via wcs_get_subscription()
                            ↓
                Mollie saves payment method for renewals
```

### 3. Renewal Payment

```
Renewal Due → WPSubscription triggers renewal
                            ↓
        Compatibility Layer fires hook:
        woocommerce_scheduled_subscription_payment_mollie_wc_gateway_creditcard
                            ↓
        Mollie receives hook → Processes payment
                            ↓
        Payment Success → Order marked complete
                            ↓
        Subscription continues
```

---

## Testing with Mollie

### Create Test Subscription

1. **Create a subscription product**:
   - Go to Products → Add New
   - Enable WPSubscription settings
   - Set recurring payment (e.g., monthly)
   - Save product

2. **Test purchase**:
   - Add product to cart
   - Go to checkout
   - Select Mollie Card payment
   - Complete test payment
   - **Expected**: Order created successfully (no "Could not find classname" error)

3. **Verify subscription created**:

   ```bash
   wp eval '$orders = wc_get_orders(["limit" => 1, "orderby" => "date", "order" => "DESC"]); $order = reset($orders); echo "Order ID: " . $order->get_id() . "\n"; echo "Contains subscription: " . (wcs_order_contains_subscription($order) ? "YES" : "NO") . "\n";' --allow-root
   ```

4. **Check payment method saved**:
   ```bash
   wp post meta list {SUBSCRIPTION_ID} --allow-root | grep -i mollie
   ```

### Test Renewal Payment

1. **Manually trigger renewal**:

   ```bash
   # Get subscription ID
   wp post list --post_type=subscrpt_order --posts_per_page=1 --allow-root

   # Trigger renewal for that subscription
   wp eval '$sub_id = 123; \SpringDevs\Subscription\Illuminate\Helper::create_renewal_order($sub_id);' --allow-root
   ```

2. **Expected behavior**:
   - New order created
   - Mollie receives `woocommerce_scheduled_subscription_payment_mollie_wc_gateway_creditcard` hook
   - Payment processed automatically
   - Order marked as complete
   - Subscription status updated

---

## Troubleshooting

### If "Could not find classname" error returns

1. **Check compatibility layer is loaded**:

   ```bash
   wp eval 'echo function_exists("wcs_is_subscription") ? "✓ Loaded\n" : "✗ Not loaded\n";' --allow-root
   ```

2. **Check gateway is compatible**:

   ```bash
   wp eval '$detector = \SpringDevs\Subscription\Compatibility\Gateways\GatewayDetector::scan_gateways(); print_r($detector["mollie_wc_gateway_creditcard"]);' --allow-root
   ```

3. **Clear cache**:

   ```bash
   wp cache flush --allow-root
   ```

4. **Check for PHP errors**:
   ```bash
   tail -f wp-content/debug.log
   ```

### If payment method not saved

1. **Check PaymentMethodManager is initialized**:

   ```bash
   wp eval 'echo class_exists("\SpringDevs\Subscription\Compatibility\Gateways\PaymentMethodManager") ? "✓ Exists\n" : "✗ Missing\n";' --allow-root
   ```

2. **Verify Mollie customer ID in order meta**:
   ```bash
   wp post meta list {ORDER_ID} --allow-root | grep mollie
   ```

### If renewal payment fails

1. **Check scheduled payment hook is registered**:

   ```bash
   wp eval 'global $wp_filter; echo isset($wp_filter["woocommerce_scheduled_subscription_payment_mollie_wc_gateway_creditcard"]) ? "✓ Registered\n" : "✗ Not registered\n";' --allow-root
   ```

2. **Manually trigger to test**:
   ```bash
   wp eval 'do_action("woocommerce_scheduled_subscription_payment_mollie_wc_gateway_creditcard", 10.00, wc_get_order(123));' --allow-root
   ```

---

## Mollie-Specific Notes

### Payment Methods Supported

Mollie supports these payment methods for recurring payments:

- ✅ **Credit Card** (mollie_wc_gateway_creditcard) - Full support
- ⚠️ **iDEAL** (mollie_wc_gateway_ideal) - One-time only, no recurring
- ⚠️ **Klarna** (mollie_wc_gateway_klarna) - Limited recurring support

### Mollie Webhook Handling

The compatibility layer includes `WebhookHandler.php` which processes Mollie webhooks for:

- Payment status updates
- Subscription status changes
- Failed payment retries

Webhook URL: `https://your-site.com/?wc-api=mollie_return`

### Mollie Customer IDs

Mollie requires a customer ID for recurring payments. The compatibility layer:

1. Saves Mollie customer ID on initial purchase
2. Retrieves it during renewal
3. Passes it to Mollie's recurring payment API

---

## Admin Status Page

View detailed compatibility status at:

**URL**: `http://wps.test/wp-admin/admin.php?page=wpsubscription-compatibility-status`

**Menu**: `WP Subscription → Compatibility Status`

Shows:

- ✓ Loaded components
- ✓ Detected gateways (including Mollie)
- ✓ Function availability
- ✓ Class aliases
- ✓ Hook registration

---

## Summary

✅ **Compatibility layer restored**
✅ **All WooCommerce Subscriptions functions available**
✅ **Mollie gateways detected and compatible**
✅ **Order creation should now work**
✅ **Ready for testing with Mollie payments**

The "Could not find classname for order ID" error should no longer occur. You can now proceed with testing subscription purchases using Mollie payment methods.
