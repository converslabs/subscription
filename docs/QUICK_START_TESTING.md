# WPSubscription Auto-Renewal - Quick Start Testing

This guide provides step-by-step instructions to quickly test the auto-renewal payment system.

## 🚀 Quick Setup (5 minutes)

### 1. Enable Debug Mode

Add this to your `wp-config.php` file:

```php
define('WP_SUBSCRIPTION_DEBUG', true);
```

### 2. Access Debug Tools

1. Go to **WP Admin → Subscriptions → Debug Tools**
2. You should see a comprehensive debug dashboard

## 🧪 Quick Tests

### Test 1: System Status Check

**Via Admin Interface:**
1. Go to Debug Tools
2. Check "System Status" section
3. Verify all components show "OK" status

**Via WP-CLI:**
```bash
wp subscrpt status
```

### Test 2: Run All Tests

**Via Admin Interface:**
1. Go to Debug Tools
2. Click "Run All Tests"
3. Check results in debug logs

**Via WP-CLI:**
```bash
wp subscrpt test all
```

### Test 3: Test Payment Method Storage

**Via Admin Interface:**
1. Go to Debug Tools
2. Click "Test Payment Processing"
3. Check "Payment Methods" section for test data

**Via WP-CLI:**
```bash
wp subscrpt test payment-methods
```

## 📋 Complete Testing Workflow

### Step 1: Create Test Subscription

1. **Create Subscription Product:**
   - Go to Products → Add New
   - Set product type to "Subscription"
   - Set billing interval to 1 day (for quick testing)
   - Set price to $10.00
   - Publish product

2. **Place Test Order:**
   - Go to shop and add subscription product to cart
   - Proceed to checkout
   - Use Stripe test card: `4242 4242 4242 4242`
   - Complete payment

3. **Verify Subscription Created:**
   - Go to Subscriptions → All Subscriptions
   - Check new subscription is created
   - Note the subscription ID

### Step 2: Test Payment Method Storage

1. **Check Debug Tools:**
   - Go to Debug Tools → Payment Methods
   - Verify payment method is saved
   - Check all required fields are populated

2. **Check via WP-CLI:**
   ```bash
   wp subscrpt subscription [SUBSCRIPTION_ID]
   ```

### Step 3: Test Renewal Processing

1. **Manual Renewal Test:**
   - Go to Debug Tools
   - Click "Test Scheduled Payments"
   - Check debug logs for processing

2. **WP-CLI Test:**
   ```bash
   wp subscrpt test scheduled
   wp subscrpt process-renewals
   ```

### Step 4: Test Webhook Processing

1. **Stripe Webhook Test:**
   - Go to Stripe Dashboard → Webhooks
   - Send test event: `payment_intent.succeeded`
   - Check debug logs for processing

2. **WP-CLI Test:**
   ```bash
   wp subscrpt test webhooks
   ```

## 🔍 Debugging Common Issues

### Issue: Debug Tools Not Showing

**Solution:**
1. Check `wp-config.php` has `WP_SUBSCRIPTION_DEBUG` constant
2. Clear any caching plugins
3. Check file permissions

### Issue: Payment Methods Not Saving

**Solution:**
1. Check database tables exist
2. Check payment gateway configuration
3. Review debug logs for errors

### Issue: Renewals Not Processing

**Solution:**
1. Check WordPress cron is working
2. Verify subscription status
3. Check payment method tokens

### Issue: Webhooks Not Working

**Solution:**
1. Verify webhook endpoints
2. Check webhook signatures
3. Review webhook processing logs

## 📊 Monitoring & Analytics

### View Payment Analytics

1. **Via Admin Interface:**
   - Go to Debug Tools
   - Check system status and metrics

2. **Via WP-CLI:**
   ```bash
   wp subscrpt status
   ```

### View Debug Logs

1. **Via Admin Interface:**
   - Go to Debug Tools
   - Scroll to "Debug Logs" section

2. **Via WP-CLI:**
   ```bash
   wp subscrpt logs
   wp subscrpt logs --lines=100
   wp subscrpt logs --level=error
   ```

### Export Debug Logs

```bash
wp subscrpt logs export --output=/tmp/debug-logs.txt
```

## 🎯 Success Criteria

### ✅ All Tests Pass

- System requirements met
- Database tables created
- Payment methods saving correctly
- Renewals processing
- Webhooks working
- Error handling functional

### ✅ Debug Tools Working

- Debug dashboard accessible
- Test buttons functional
- Logs displaying correctly
- WP-CLI commands working

### ✅ Real-world Testing

- Test subscription created
- Payment method saved
- Renewal order created
- Payment processed successfully

## 🚨 Troubleshooting

### Check System Status

```bash
wp subscrpt status
```

### Run Diagnostic Tests

```bash
wp subscrpt test all
```

### View Recent Logs

```bash
wp subscrpt logs --lines=50
```

### Clear Debug Logs

```bash
wp subscrpt logs clear
```

### Reset Debug Settings

```bash
wp subscrpt reset-settings
```

## 📞 Getting Help

### Debug Information to Collect

1. **System Status:**
   ```bash
   wp subscrpt status
   ```

2. **Test Results:**
   ```bash
   wp subscrpt test all --format=json
   ```

3. **Debug Logs:**
   ```bash
   wp subscrpt logs export
   ```

4. **Subscription Info:**
   ```bash
   wp subscrpt subscription [ID]
   ```

### Common Commands Reference

```bash
# Run all tests
wp subscrpt test all

# Test specific components
wp subscrpt test payment-methods
wp subscrpt test webhooks
wp subscrpt test scheduled

# View system status
wp subscrpt status

# View logs
wp subscrpt logs
wp subscrpt logs --level=error

# Export logs
wp subscrpt logs export

# Process renewals manually
wp subscrpt process-renewals

# Clean up old logs
wp subscrpt cleanup

# Reset settings
wp subscrpt reset-settings
```

---

**Happy Testing! 🚀**

The debug tools provide comprehensive testing and monitoring capabilities. Use them to verify everything is working correctly before deploying to production.
