# Compatibility Layer Testing Guide

## Quick Start

### 1. Enable Compatibility Layer

The compatibility layer is **automatically enabled** when WPSubscription is installed. No configuration needed!

### 2. Verify Installation

Navigate to: **WP Admin → WP Subscription → Compatibility**

You should see:

- ✓ Status: Active
- ✓ Core Functions: 15/15 loaded
- ✓ Wrapper Classes: 6/6 loaded
- ✓ Detected gateways with subscription support

### 3. Run Quick Test

```bash
wp wpsubscription compat test
```

Expected output:

```
✓ [BOOTSTRAP] Bootstrap loaded successfully
✓ [FUNCTIONS] 15 functions loaded
✓ [CLASSES] 6/6 classes loaded
✓ [HOOKS] X hooks registered
✓ [GATEWAYS] X compatible gateways found

Success: All compatibility tests passed!
```

## Detailed Testing Scenarios

### Test 1: Function Availability

**Objective**: Verify all WCS functions are available

**Steps**:

1. Create a test PHP file or use WP-CLI shell:

```php
// Check core functions exist
assert( function_exists( 'wcs_is_subscription' ) );
assert( function_exists( 'wcs_get_subscription' ) );
assert( function_exists( 'wcs_order_contains_subscription' ) );
assert( function_exists( 'wcs_is_subscription_product' ) );

echo "All functions available!\n";
```

2. Run via WP-CLI:

```bash
wp eval-file test-functions.php
```

**Expected Result**: No errors, message "All functions available!"

### Test 2: Class Availability

**Objective**: Verify all WCS classes are aliased correctly

**Steps**:

```php
// Check classes exist in global namespace
assert( class_exists( 'WC_Subscription' ) );
assert( class_exists( 'WC_Subscriptions_Manager' ) );
assert( class_exists( 'WC_Subscriptions_Product' ) );
assert( class_exists( 'WC_Subscriptions_Order' ) );
assert( class_exists( 'WC_Subscriptions_Cart' ) );
assert( class_exists( 'WC_Subscriptions_Change_Payment_Gateway' ) );

echo "All classes available!\n";
```

**Expected Result**: All assertions pass

### Test 3: Gateway Detection

**Objective**: Verify gateways detect subscription support

**Steps**:

1. Install Stripe gateway plugin (woo-stripe-payment)
2. Navigate to: **WooCommerce → Settings → Payments**
3. Enable Stripe gateway
4. Navigate to: **WP Subscription → Compatibility**
5. Check "Detected Payment Gateways" section

**Expected Result**:

```
✓ Stripe (woo-stripe-payment)
  - Enabled: ✓
  - Subscriptions Support: ✓
```

### Test 4: Hook Registration

**Objective**: Verify WCS hooks are registered and triggerable

**Steps**:

```php
// Test hook is registered
global $wp_filter;
assert( isset( $wp_filter['woocommerce_scheduled_subscription_payment_stripe_cc'] ) );

// Test hook can be triggered
$triggered = false;
add_action( 'woocommerce_subscription_status_updated', function() use ( &$triggered ) {
    $triggered = true;
} );

// Simulate status change
do_action( 'woocommerce_subscription_status_updated', 123, 'active', 'on-hold' );

assert( $triggered === true );
echo "Hooks working correctly!\n";
```

**Expected Result**: Hook triggers successfully

### Test 5: End-to-End Gateway Integration

**Objective**: Test complete subscription flow with real gateway

**Prerequisites**:

- Stripe gateway installed and configured
- Test mode enabled
- Test API keys configured

**Steps**:

1. Create a subscription product:
   - Navigate to **Products → Add New**
   - Enable "WPSubscription" checkbox
   - Set recurring price: $10/month
   - Publish product

2. Make test purchase:
   - Add product to cart
   - Proceed to checkout
   - Use Stripe test card: 4242 4242 4242 4242
   - Complete purchase

3. Verify subscription created:
   - Navigate to **WP Subscription**
   - Verify subscription shows as "Active"
   - Check parent order has Stripe metadata

4. Test renewal (manual trigger):

   ```bash
   wp eval 'SpringDevs\Subscription\Illuminate\Helper::create_renewal_order(SUBSCRIPTION_ID);'
   ```

   Replace `SUBSCRIPTION_ID` with actual ID

5. Verify renewal order:
   - Check new order created
   - Verify order has Stripe payment method
   - Verify order status updates automatically

**Expected Results**:

- ✓ Subscription created successfully
- ✓ Payment method saved
- ✓ Renewal order created with payment method
- ✓ Gateway processes renewal payment
- ✓ Subscription status remains active

### Test 6: WCS Plugin Conflict Detection

**Objective**: Verify conflict detection works

**Steps**:

1. Install WooCommerce Subscriptions plugin (if available)
2. Activate WooCommerce Subscriptions
3. Visit any admin page

**Expected Result**:

- Admin notice appears: "WooCommerce Subscriptions plugin is active. For best compatibility, please deactivate..."
- Button to deactivate WCS available
- WPSubscription compatibility layer still functions

### Test 7: Payment Meta Cloning

**Objective**: Verify payment tokens are copied to renewal orders

**Steps**:

1. Complete Test 5 (make subscription purchase)
2. Get original order ID
3. Check original order meta:

```bash
wp post meta list ORDER_ID
```

4. Note Stripe metadata: `_stripe_customer_id`, `_stripe_payment_method_id`
5. Trigger renewal (Test 5, step 4)
6. Check renewal order meta:

```bash
wp post meta list RENEWAL_ORDER_ID
```

**Expected Result**:

- Original order has Stripe meta
- Renewal order has same Stripe meta
- Payment method matches

### Test 8: Subscription Status Translation

**Objective**: Verify WPS statuses map correctly to WCS statuses

**Steps**:

```php
// Create subscription object
$subscription = new WC_Subscription( SUBSCRIPTION_ID );

// Test status mapping
$wps_status = get_post_status( SUBSCRIPTION_ID ); // e.g., 'active'
$wcs_status = $subscription->get_status(); // Should return 'active'

assert( $wcs_status === 'active' );

// Test status update
$subscription->update_status( 'on-hold' );
$new_wps_status = get_post_status( SUBSCRIPTION_ID );

assert( $new_wps_status === 'on-hold' );
echo "Status translation working!\n";
```

**Expected Result**: Status translations work bidirectionally

## Automated Test Suite

### Run All Tests

```bash
wp wpsubscription compat test
```

### Run Specific Component Tests

```bash
wp wpsubscription compat test --component=functions
wp wpsubscription compat test --component=classes
wp wpsubscription compat test --component=hooks
wp wpsubscription compat test --component=gateways
```

## Common Issues & Solutions

### Issue: Functions not found

**Symptom**: `Fatal error: Call to undefined function wcs_is_subscription()`

**Solutions**:

1. Check compatibility layer loaded:
   ```bash
   wp wpsubscription compat status
   ```
2. Verify Bootstrap loaded:
   ```php
   assert( class_exists( 'SpringDevs\\Subscription\\Compatibility\\Bootstrap' ) );
   ```
3. Check for PHP errors in error log

### Issue: Classes not found

**Symptom**: `Class 'WC_Subscription' not found`

**Solutions**:

1. Check if WooCommerce Subscriptions is active (it shouldn't be)
2. Verify aliases created:
   ```bash
   wp wpsubscription compat test --component=classes
   ```
3. Check Bootstrap loaded successfully

### Issue: Gateway not detecting subscription support

**Symptom**: Gateway doesn't show "Subscriptions Support: ✓"

**Solutions**:

1. Check if gateway is in compatible list:
   ```bash
   wp wpsubscription compat status
   ```
2. Verify gateway fully loaded before checking
3. Check gateway plugin version (may need update)

### Issue: Renewal payments not processing

**Symptom**: Renewal orders created but payment not charged

**Checklist**:

1. ☐ WPSubscription auto-renewal enabled
2. ☐ Gateway active and configured
3. ☐ Payment method saved on original order
4. ☐ Gateway supports `scheduled_subscription_payment` method
5. ☐ WP Cron functioning
6. ☐ No errors in error log

**Debug Steps**:

```bash
# Check if payment method saved
wp post meta get ORIGINAL_ORDER_ID _stripe_customer_id

# Check if renewal order has payment method
wp post meta get RENEWAL_ORDER_ID _stripe_customer_id

# Check if hook fires
wp eval 'add_action("woocommerce_scheduled_subscription_payment_stripe_cc", function($amount, $order) { error_log("Hook fired! Amount: $amount, Order: " . $order->get_id()); }, 10, 2);'
```

## Performance Testing

### Measure Overhead

```php
// Test without compatibility layer
$start = microtime(true);
// ... perform operations ...
$without = microtime(true) - $start;

// Test with compatibility layer
$start = microtime(true);
// ... same operations ...
$with = microtime(true) - $start;

$overhead = ($with - $without) / $without * 100;
echo "Overhead: {$overhead}%\n";
```

**Expected**: < 5% overhead

## Test Coverage Checklist

- [ ] All 15+ functions callable
- [ ] All 6 classes instantiable
- [ ] Class methods return correct data types
- [ ] Status translation bidirectional
- [ ] Hooks registered in `$wp_filter`
- [ ] Hooks triggerable by external code
- [ ] Gateways detected correctly
- [ ] Subscription support added to gateways
- [ ] Payment meta clones to renewal orders
- [ ] Scheduled payment hook fires
- [ ] Gateway processes renewal payment
- [ ] Admin dashboard displays correctly
- [ ] WP-CLI commands functional
- [ ] WCS conflict detected
- [ ] No PHP errors/warnings
- [ ] Performance impact minimal

## Continuous Testing

### Daily Checks

```bash
# Quick health check
wp wpsubscription compat status

# Run tests
wp wpsubscription compat test
```

### After Plugin Updates

1. Run full test suite
2. Check compatibility status dashboard
3. Test with real gateway
4. Verify no regressions

### Before Production Deployment

1. Complete all tests in this guide
2. Test with all active gateways
3. Verify no errors in logs
4. Test actual renewal flow
5. Confirm WP Cron working

## Reporting Issues

When reporting compatibility issues, include:

1. WP-CLI test output: `wp wpsubscription compat test`
2. Status output: `wp wpsubscription compat status`
3. Gateway name and version
4. WPSubscription version
5. WordPress and WooCommerce versions
6. Relevant error logs
7. Steps to reproduce

## Success Criteria

✓ All automated tests pass  
✓ All 15+ functions available  
✓ All 6 classes available  
✓ Gateways detect subscription support  
✓ Real gateway processes test payment  
✓ Renewal order created and paid  
✓ No PHP errors or warnings  
✓ Performance impact < 5%  
✓ Admin dashboard shows healthy status

## Next Steps

After successful testing:

1. Deploy to staging environment
2. Test with production-like data
3. Monitor for 1 week
4. Deploy to production
5. Monitor renewal cycles

## Support

For testing assistance:

- Check: [COMPATIBILITY_LAYER.md](COMPATIBILITY_LAYER.md)
- Email: support@wpsubscription.co
- Forum: WordPress.org support forum
