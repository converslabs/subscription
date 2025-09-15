# WPSubscription WooCommerce Subscriptions Compatibility Layer - Developer Testing Guide

## 🚀 Quick Start

The compatibility layer makes WPSubscription a **drop-in replacement** for WooCommerce Subscriptions, enabling seamless integration with payment gateways and third-party plugins.

## ⚡ How It Works

```
Payment Gateway → WooCommerce Subscriptions Functions → WPSubscription
```

**Key Components:**
- **Function Aliases**: `wcs_is_subscription()` → WPSubscription functions
- **Class Aliases**: `WC_Subscription` → WPSubscription classes  
- **Hook Translation**: WooCommerce Subscriptions hooks → WPSubscription hooks
- **Auto-Creation**: Orders with subscription products → WPSubscription subscriptions

## 🧪 Testing Checklist

### 1. Basic Functionality
- [ ] `wcs_is_subscription($product)` returns `true` for subscription products
- [ ] `wcs_get_subscription($id)` returns subscription object
- [ ] `WC_Subscriptions_Cart::cart_contains_subscription()` works
- [ ] `WC_Subscriptions_Product::is_subscription($product)` works

### 2. Payment Gateway Integration
- [ ] **Stripe**: Payments create WPSubscription subscriptions
- [ ] **PayPal**: Payments create WPSubscription subscriptions
- [ ] Payment method changes work
- [ ] Subscription renewals process correctly

### 3. Order Processing
- [ ] Orders with subscription products create subscriptions
- [ ] Subscription status matches order status
- [ ] Billing/shipping data copied to subscription
- [ ] Trial periods work correctly

## 🔧 Debug Mode

Enable debug mode to see compatibility status:

```php
// Add to wp-config.php
define('WP_SUBSCRIPTION_COMPATIBILITY_DEBUG', true);
```

**Debug Features:**
- Admin notices with compatibility test results
- Payment gateway compatibility status
- Function availability checks

## 📝 Test Scenarios

### Scenario 1: Basic Subscription Purchase
1. Create subscription product (monthly, $10)
2. Add to cart and checkout
3. Pay with Stripe/PayPal
4. Verify subscription created in WPSubscription

### Scenario 2: Payment Method Change
1. Create active subscription
2. Go to My Account → Subscriptions
3. Change payment method
4. Verify new payment method saved

### Scenario 3: Third-Party Plugin Integration
1. Install any WooCommerce Subscriptions compatible plugin
2. Verify it works with WPSubscription
3. Check for any missing functions/classes

## 🐛 Common Issues

| Issue | Solution |
|-------|----------|
| Functions not found | Check if compatibility layer is active |
| Payment gateways not working | Verify gateway supports subscriptions |
| Subscriptions not created | Check order status and product settings |
| Class not found | Ensure class aliases are loaded |

## 📊 Compatibility Status

**✅ Fully Compatible:**
- Stripe Payment Gateway
- PayPal Payment Gateway
- Core WooCommerce Subscriptions functions
- Cart and product detection
- Order processing

**⚠️ Needs Testing:**
- Third-party subscription plugins
- Custom subscription workflows
- Advanced payment scenarios

## 🔍 Testing Tools

**Built-in Tests:**
- `test-compatibility.php` - Core function tests
- `test-payment-gateways.php` - Payment gateway tests

**Manual Testing:**
```php
// Test if function exists
if (function_exists('wcs_is_subscription')) {
    echo "✅ Function available";
}

// Test if class exists  
if (class_exists('WC_Subscription')) {
    echo "✅ Class available";
}
```

## 📞 Feedback

**What to Test:**
1. Your specific payment gateway
2. Your subscription product types
3. Your custom workflows
4. Any third-party plugins you use

**Report Issues:**
- Missing functions/classes
- Payment gateway problems
- Subscription creation failures
- Performance issues

**Success Stories:**
- Working integrations
- Performance improvements
- Feature requests

---

**Ready to test?** Enable debug mode and start with the basic functionality checklist!
