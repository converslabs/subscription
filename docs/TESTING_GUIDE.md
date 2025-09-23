# WPSubscription Auto-Renewal Testing Guide

This guide provides comprehensive testing steps for the auto-renewal payment system.

## 🚀 Quick Start

### 1. Enable Debug Mode

Add this to your `wp-config.php` file:

```php
define('WP_SUBSCRIPTION_DEBUG', true);
```

### 2. Access Debug Tools

1. Go to **WP Admin → Subscriptions → Debug Tools**
2. Review system status and database tables
3. Use test tools to verify functionality

## 📋 Testing Checklist

### Phase 1: System Setup

- [ ] **Debug Mode Enabled**
  - Check `wp-config.php` has `WP_SUBSCRIPTION_DEBUG` constant
  - Verify debug tools are accessible in admin

- [ ] **Database Tables Created**
  - `wp_subscrpt_payment_methods` exists
  - `wp_subscrpt_payment_history` exists
  - `wp_subscrpt_webhook_events` exists

- [ ] **Payment Gateways Active**
  - Stripe gateway enabled and configured
  - PayPal gateway enabled and configured
  - Other gateways as needed

### Phase 2: Payment Method Storage

- [ ] **Create Test Subscription**
  1. Create a subscription product
  2. Place an order with subscription product
  3. Complete payment with Stripe/PayPal
  4. Check payment method is saved

- [ ] **Verify Payment Method Storage**
  1. Go to Debug Tools → Payment Methods
  2. Verify payment method appears in table
  3. Check all required fields are populated

### Phase 3: Scheduled Payment Processing

- [ ] **Test Manual Renewal**
  1. Create a subscription with short interval (1 day)
  2. Wait for renewal or manually trigger
  3. Check renewal order is created
  4. Verify payment is processed

- [ ] **Test Payment Processing**
  1. Check debug logs for payment processing
  2. Verify payment method token is used
  3. Confirm order status updates correctly

### Phase 4: Webhook Processing

- [ ] **Test Stripe Webhook**
  1. Send test webhook from Stripe dashboard
  2. Check webhook is received and processed
  3. Verify order status updates

- [ ] **Test PayPal Webhook**
  1. Send test webhook from PayPal dashboard
  2. Check webhook processing
  3. Verify payment confirmation

### Phase 5: Error Handling

- [ ] **Test Payment Failures**
  1. Use test card that will decline
  2. Check retry mechanism activates
  3. Verify failure logging

- [ ] **Test Retry System**
  1. Simulate payment failure
  2. Check retry is scheduled
  3. Verify retry attempts work

## 🔧 Debug Tools Usage

### System Status

Check all components are working:
- WordPress and WooCommerce versions
- PHP configuration
- Database table status
- Payment gateway status

### Test Tools

**Test Payment Processing:**
- Tests payment method saving/retrieval
- Verifies database operations
- Checks gateway integration

**Test Webhook Processing:**
- Simulates webhook events
- Tests webhook parsing
- Verifies event processing

**Test Scheduled Payments:**
- Triggers scheduled payment processing
- Tests renewal order creation
- Verifies payment processing

### Debug Logs

**View Logs:**
- Real-time log viewing
- Filter by log level
- Export logs for analysis

**Log Levels:**
- `info`: General information
- `warning`: Non-critical issues
- `error`: Critical errors with stack trace

## 🧪 Test Scenarios

### Scenario 1: Successful Renewal

1. **Setup:**
   - Create subscription with 1-day interval
   - Use valid test card
   - Complete initial payment

2. **Test:**
   - Wait for renewal or trigger manually
   - Check renewal order created
   - Verify payment processed successfully
   - Check subscription status updated

3. **Expected Results:**
   - Renewal order status: `completed`
   - Payment method token used correctly
   - Subscription next payment date updated
   - Debug logs show success

### Scenario 2: Payment Failure

1. **Setup:**
   - Create subscription with test card
   - Use card that will decline

2. **Test:**
   - Trigger renewal
   - Check payment failure handling
   - Verify retry mechanism

3. **Expected Results:**
   - Renewal order status: `failed`
   - Retry scheduled for later
   - Failure reason logged
   - Admin notification sent

### Scenario 3: Webhook Processing

1. **Setup:**
   - Configure webhook endpoints
   - Set up test webhook data

2. **Test:**
   - Send test webhook
   - Check webhook processing
   - Verify order updates

3. **Expected Results:**
   - Webhook received and parsed
   - Order status updated correctly
   - Payment confirmed
   - Debug logs show processing

## 📊 Monitoring & Analytics

### Payment Analytics

Check payment success rates:
- Overall success rate
- Gateway-specific performance
- Revenue tracking
- Failure analysis

### Health Monitoring

Monitor system health:
- Payment success rates
- Churn rates
- Failed payment rates
- System performance

### Alerts

Configure alerts for:
- Low payment success rates
- High churn rates
- System errors
- Critical failures

## 🐛 Troubleshooting

### Common Issues

**Payment Methods Not Saving:**
- Check database table exists
- Verify payment gateway configuration
- Check debug logs for errors

**Renewals Not Processing:**
- Check cron jobs are running
- Verify subscription status
- Check payment method tokens

**Webhooks Not Working:**
- Verify webhook endpoints
- Check webhook signatures
- Review webhook processing logs

**Debug Logs Empty:**
- Ensure debug mode is enabled
- Check file permissions
- Verify log file path

### Debug Commands

**Check System Status:**
```php
// In wp-config.php or functions.php
var_dump(defined('WP_SUBSCRIPTION_DEBUG'));
var_dump(WP_SUBSCRIPTION_DEBUG);
```

**Check Database Tables:**
```sql
SHOW TABLES LIKE 'wp_subscrpt_%';
```

**Check Payment Methods:**
```sql
SELECT * FROM wp_subscrpt_payment_methods LIMIT 10;
```

**Check Debug Logs:**
```bash
tail -f /path/to/wordpress/wp-content/uploads/subscrpt-debug.log
```

## 📈 Performance Testing

### Load Testing

1. **Create Multiple Subscriptions:**
   - Create 100+ test subscriptions
   - Use different payment methods
   - Test concurrent processing

2. **Monitor Performance:**
   - Check processing times
   - Monitor memory usage
   - Verify database performance

### Stress Testing

1. **High Volume Processing:**
   - Process many renewals simultaneously
   - Test webhook processing under load
   - Verify system stability

2. **Error Recovery:**
   - Simulate system failures
   - Test retry mechanisms
   - Verify data integrity

## 🔒 Security Testing

### Data Protection

1. **Payment Method Tokens:**
   - Verify tokens are encrypted
   - Check database security
   - Test token validation

2. **Webhook Security:**
   - Verify signature validation
   - Test unauthorized access
   - Check data sanitization

### Access Control

1. **Admin Access:**
   - Verify debug tools access
   - Check user permissions
   - Test data exposure

2. **API Security:**
   - Test webhook endpoints
   - Verify authentication
   - Check rate limiting

## 📝 Test Results Template

### Test Run: [Date]

**Environment:**
- WordPress Version: [Version]
- WooCommerce Version: [Version]
- PHP Version: [Version]
- Debug Mode: [Enabled/Disabled]

**Tests Performed:**
- [ ] System Setup
- [ ] Payment Method Storage
- [ ] Scheduled Payments
- [ ] Webhook Processing
- [ ] Error Handling

**Results:**
- Passed: [Number]
- Failed: [Number]
- Issues Found: [List]

**Notes:**
[Additional observations and recommendations]

## 🎯 Success Criteria

### Functional Requirements

- [ ] Payment methods save correctly
- [ ] Renewals process automatically
- [ ] Webhooks work properly
- [ ] Error handling functions
- [ ] Retry system works

### Performance Requirements

- [ ] Processing time < 5 seconds
- [ ] Memory usage reasonable
- [ ] Database queries optimized
- [ ] No memory leaks

### Security Requirements

- [ ] Payment data encrypted
- [ ] Webhooks secured
- [ ] Access controlled
- [ ] Data sanitized

## 📞 Support

If you encounter issues during testing:

1. **Check Debug Logs:** Review debug logs for error messages
2. **Use Debug Tools:** Run diagnostic tests in admin
3. **Review Documentation:** Check implementation guides
4. **Contact Support:** Provide debug logs and test results

---

**Happy Testing! 🚀**

Remember to test in a staging environment before deploying to production.
