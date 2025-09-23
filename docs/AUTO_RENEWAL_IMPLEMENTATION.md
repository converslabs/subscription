# Auto-Renewal Payment Implementation Guide

## Overview
This document outlines the complete implementation process for automatic renewal payments in WPSubscription. The implementation is divided into 4 phases, each building upon the previous phase to create a robust, gateway-agnostic auto-renewal system.

## Table of Contents
1. [Current State Analysis](#current-state-analysis)
2. [Implementation Phases](#implementation-phases)
3. [Technical Architecture](#technical-architecture)
4. [Database Schema](#database-schema)
5. [API Reference](#api-reference)
6. [Testing Guide](#testing-guide)
7. [Troubleshooting](#troubleshooting)

## Current State Analysis

### ✅ What's Already Implemented
- **Core Subscription System**: Subscription post type (`subscrpt_order`)
- **Auto-Renewal Mechanism**: `AutoRenewal` class with `subscrpt_subscription_expired` hook
- **Renewal Order Creation**: `Helper::create_renewal_order()` method
- **Payment Method Cloning**: Basic cloning from original orders
- **Universal Payment Processor**: Gateway-agnostic processing framework
- **Stripe Integration**: Basic Stripe payment processing
- **Pro Features**: Payment failure handling, retry logic, grace periods

### 🔄 What Needs Implementation
- **Payment Gateway Hook Integration**: Proper WooCommerce gateway hooks
- **Scheduled Payment Processing**: Automated payment processing system
- **Payment Method Token Management**: Secure token storage and retrieval
- **Webhook Integration**: Real-time payment status updates
- **Multi-Gateway Support**: PayPal, Square, Razorpay, etc.
- **Payment Monitoring**: Success/failure tracking and analytics

## Implementation Phases

### Phase 1: Core Payment Processing
**Goal**: Establish the foundation for automatic payment processing

#### 1.1 Payment Method Token Management
- **File**: `includes/Illuminate/PaymentMethodManager.php`
- **Purpose**: Secure storage and retrieval of payment method tokens
- **Features**:
  - Save payment methods during checkout
  - Retrieve tokens for renewals
  - Handle payment method updates
  - Support multiple gateways

#### 1.2 Scheduled Payment Processor
- **File**: `includes/Illuminate/ScheduledPaymentProcessor.php`
- **Purpose**: Process scheduled subscription payments
- **Features**:
  - Check for due renewals
  - Create renewal orders
  - Process payments using saved methods
  - Handle success/failure scenarios

#### 1.3 Gateway Hook Integration
- **File**: `includes/Compatibility/Gateways/GatewayCompatibility.php`
- **Purpose**: Integrate with WooCommerce payment gateway hooks
- **Features**:
  - Add subscription support to gateways
  - Hook into `woocommerce_scheduled_subscription_payment_*`
  - Handle payment completion/failure events

### Phase 2: Gateway Integration
**Goal**: Implement support for multiple payment gateways

#### 2.1 Stripe Integration Enhancement
- **File**: `includes/Illuminate/Gateways/Stripe.php`
- **Purpose**: Enhanced Stripe payment processing
- **Features**:
  - PaymentIntent API integration
  - SetupIntent for future payments
  - Webhook handling
  - Error handling and retries

#### 2.2 PayPal Integration
- **File**: `includes/Illuminate/Gateways/PayPal.php`
- **Purpose**: PayPal subscription payment processing
- **Features**:
  - PayPal Subscriptions API
  - Billing agreement management
  - Webhook integration
  - Payment method updates

#### 2.3 Other Gateway Support
- **Files**: `includes/Illuminate/Gateways/`
- **Purpose**: Support for additional payment gateways
- **Gateways**:
  - Square
  - Razorpay
  - Mollie
  - Authorize.Net

### Phase 3: Advanced Features
**Goal**: Implement advanced payment features and real-time updates

#### 3.1 Webhook Handler
- **File**: `includes/Illuminate/WebhookHandler.php`
- **Purpose**: Handle real-time payment status updates
- **Features**:
  - Gateway-specific webhook processing
  - Payment success/failure handling
  - Subscription status updates
  - Security validation

#### 3.2 Payment Retry System
- **File**: `includes/Illuminate/PaymentRetryManager.php`
- **Purpose**: Intelligent payment retry system
- **Features**:
  - Configurable retry attempts
  - Exponential backoff
  - Failure reason tracking
  - Admin notifications

#### 3.3 Subscription Status Management
- **File**: `includes/Illuminate/SubscriptionStatusManager.php`
- **Purpose**: Centralized subscription status management
- **Features**:
  - Status transition validation
  - Event logging
  - Email notifications
  - Admin dashboard updates

### Phase 4: Monitoring & Analytics
**Goal**: Provide comprehensive monitoring and reporting

#### 4.1 Payment Analytics
- **File**: `includes/Illuminate/Analytics/PaymentAnalytics.php`
- **Purpose**: Track payment success/failure metrics
- **Features**:
  - Payment success rates
  - Failure reason analysis
  - Gateway performance comparison
  - Revenue tracking

#### 4.2 Subscription Health Monitoring
- **File**: `includes/Illuminate/Monitoring/SubscriptionHealth.php`
- **Purpose**: Monitor subscription health and performance
- **Features**:
  - Subscription lifecycle tracking
  - Churn rate analysis
  - Payment method health
  - Automated alerts

#### 4.3 Reporting Dashboard
- **File**: `includes/Admin/Reports/PaymentReports.php`
- **Purpose**: Admin reporting interface
- **Features**:
  - Payment success/failure reports
  - Subscription analytics
  - Gateway performance metrics
  - Export capabilities

## Technical Architecture

### Core Components

```
WPSubscription Auto-Renewal System
├── PaymentMethodManager
│   ├── save_payment_method()
│   ├── get_payment_method()
│   └── update_payment_method()
├── ScheduledPaymentProcessor
│   ├── process_scheduled_payments()
│   ├── create_renewal_order()
│   └── process_payment()
├── GatewayCompatibility
│   ├── add_subscription_support()
│   ├── handle_scheduled_payment()
│   └── handle_payment_complete()
└── WebhookHandler
    ├── handle_stripe_webhook()
    ├── handle_paypal_webhook()
    └── process_payment_event()
```

### Data Flow

1. **Initial Checkout**:
   - Customer completes checkout
   - Payment method token saved via `PaymentMethodManager`
   - Subscription created with payment method reference

2. **Scheduled Renewal**:
   - Cron job triggers `ScheduledPaymentProcessor`
   - System checks for due renewals
   - Renewal order created with cloned payment data
   - Payment processed using saved payment method

3. **Payment Processing**:
   - Gateway-specific payment processing
   - Success/failure handling
   - Subscription status updates
   - Email notifications sent

4. **Webhook Processing**:
   - Real-time payment status updates
   - Subscription status synchronization
   - Error handling and retries

## Database Schema

### Payment Methods Table
```sql
CREATE TABLE wp_subscrpt_payment_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscription_id INT NOT NULL,
    gateway_id VARCHAR(50) NOT NULL,
    payment_method_token TEXT NOT NULL,
    customer_id VARCHAR(100),
    gateway_customer_id VARCHAR(100),
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_gateway_id (gateway_id),
    INDEX idx_customer_id (customer_id)
);
```

### Payment History Table
```sql
CREATE TABLE wp_subscrpt_payment_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subscription_id INT NOT NULL,
    order_id INT,
    gateway_id VARCHAR(50) NOT NULL,
    payment_method_token VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL,
    status ENUM('pending', 'success', 'failed', 'cancelled') NOT NULL,
    gateway_transaction_id VARCHAR(255),
    failure_reason TEXT,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_order_id (order_id),
    INDEX idx_status (status),
    INDEX idx_processed_at (processed_at)
);
```

### Webhook Events Table
```sql
CREATE TABLE wp_subscrpt_webhook_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    gateway_id VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_id VARCHAR(255) UNIQUE,
    subscription_id INT,
    order_id INT,
    payload TEXT NOT NULL,
    processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gateway_id (gateway_id),
    INDEX idx_event_type (event_type),
    INDEX idx_subscription_id (subscription_id),
    INDEX idx_processed (processed)
);
```

## API Reference

### Hooks

#### Actions
```php
// Payment processing
do_action('subscrpt_scheduled_payment_processed', $subscription_id, $order_id);
do_action('subscrpt_payment_success', $subscription_id, $order_id, $gateway_id);
do_action('subscrpt_payment_failed', $subscription_id, $order_id, $gateway_id, $error);

// Payment method management
do_action('subscrpt_payment_method_saved', $subscription_id, $payment_method_data);
do_action('subscrpt_payment_method_updated', $subscription_id, $old_data, $new_data);
do_action('subscrpt_payment_method_deleted', $subscription_id, $payment_method_id);

// Webhook processing
do_action('subscrpt_webhook_received', $gateway_id, $event_type, $payload);
do_action('subscrpt_webhook_processed', $webhook_id, $success);
```

#### Filters
```php
// Payment processing
apply_filters('subscrpt_payment_amount', $amount, $subscription_id);
apply_filters('subscrpt_payment_currency', $currency, $subscription_id);
apply_filters('subscrpt_payment_retry_attempts', $attempts, $subscription_id);

// Gateway compatibility
apply_filters('subscrpt_supported_gateways', $gateways);
apply_filters('subscrpt_gateway_capabilities', $capabilities, $gateway_id);

// Webhook processing
apply_filters('subscrpt_webhook_validation', $is_valid, $gateway_id, $payload);
apply_filters('subscrpt_webhook_event_handlers', $handlers, $event_type);
```

### Classes

#### PaymentMethodManager
```php
class PaymentMethodManager {
    public static function save_payment_method($subscription_id, $gateway_id, $token, $customer_id);
    public static function get_payment_method($subscription_id, $gateway_id = null);
    public static function update_payment_method($subscription_id, $gateway_id, $new_token);
    public static function delete_payment_method($subscription_id, $gateway_id);
    public static function get_customer_payment_methods($customer_id, $gateway_id = null);
}
```

#### ScheduledPaymentProcessor
```php
class ScheduledPaymentProcessor {
    public static function process_scheduled_payments();
    public static function process_subscription_renewal($subscription_id);
    public static function create_renewal_order($subscription_id);
    public static function process_payment($order_id, $gateway_id);
}
```

#### WebhookHandler
```php
class WebhookHandler {
    public static function handle_webhook($gateway_id, $payload);
    public static function validate_webhook($gateway_id, $payload, $signature);
    public static function process_payment_event($event_data);
    public static function process_subscription_event($event_data);
}
```

## Testing Guide

### Unit Tests
```php
// Test payment method management
test_save_payment_method();
test_get_payment_method();
test_update_payment_method();

// Test scheduled payment processing
test_process_scheduled_payments();
test_create_renewal_order();
test_payment_processing();

// Test webhook handling
test_webhook_validation();
test_payment_event_processing();
test_subscription_event_processing();
```

### Integration Tests
```php
// Test gateway integration
test_stripe_integration();
test_paypal_integration();
test_gateway_compatibility();

// Test end-to-end flow
test_complete_renewal_flow();
test_payment_failure_handling();
test_webhook_processing();
```

### Manual Testing
1. **Create Test Subscription**: Set up a test subscription with Stripe
2. **Trigger Renewal**: Manually trigger renewal process
3. **Verify Payment**: Check payment processing and order creation
4. **Test Webhooks**: Send test webhook events
5. **Monitor Logs**: Check debug logs for errors

## Troubleshooting

### Common Issues

#### Payment Method Not Found
- **Cause**: Payment method token not saved during checkout
- **Solution**: Check `PaymentMethodManager::save_payment_method()` implementation
- **Debug**: Enable debug logging and check payment method storage

#### Gateway Not Supporting Subscriptions
- **Cause**: Gateway compatibility not properly configured
- **Solution**: Verify `GatewayCompatibility::add_subscription_support()` implementation
- **Debug**: Check gateway capabilities and supported features

#### Webhook Validation Failed
- **Cause**: Webhook signature validation failing
- **Solution**: Verify webhook secret configuration
- **Debug**: Check webhook payload and signature validation

#### Payment Processing Failed
- **Cause**: Gateway API errors or invalid payment data
- **Solution**: Check gateway API credentials and payment method validity
- **Debug**: Enable gateway-specific debug logging

### Debug Tools

#### Debug Logging
```php
// Enable debug logging
define('WP_SUBSCRIPTION_DEBUG', true);

// Check logs
wp_subscrpt_write_debug_log('Payment processing started', $subscription_id);
```

#### Admin Debug Panel
- **Location**: WP Admin > Subscriptions > Debug
- **Features**: Payment method status, webhook events, error logs
- **Usage**: Monitor system health and troubleshoot issues

#### Gateway Test Mode
- **Stripe**: Use test API keys and webhook endpoints
- **PayPal**: Use sandbox environment
- **Other Gateways**: Enable test mode in gateway settings

## Security Considerations

### Payment Method Security
- Encrypt payment method tokens at rest
- Use secure token storage mechanisms
- Implement token rotation policies
- Regular security audits

### Webhook Security
- Validate webhook signatures
- Use HTTPS for webhook endpoints
- Implement rate limiting
- Monitor for suspicious activity

### Data Protection
- Comply with PCI DSS requirements
- Implement data retention policies
- Regular security updates
- Access control and logging

## Performance Optimization

### Database Optimization
- Index frequently queried fields
- Implement query caching
- Regular database maintenance
- Monitor query performance

### Caching Strategy
- Cache payment method data
- Implement subscription status caching
- Use object caching for frequently accessed data
- Cache gateway responses

### Background Processing
- Use WordPress cron for scheduled tasks
- Implement queue system for webhook processing
- Batch process multiple renewals
- Optimize payment processing workflows

## Maintenance

### Regular Tasks
- Monitor payment success rates
- Review failed payment logs
- Update gateway integrations
- Security updates and patches

### Monitoring
- Set up alerts for payment failures
- Monitor system performance
- Track subscription health metrics
- Regular backup verification

### Updates
- Test updates in staging environment
- Gradual rollout of new features
- Monitor for issues after updates
- Rollback plan for critical issues

---

## Implementation Checklist

### Phase 1: Core Payment Processing
- [ ] Create `PaymentMethodManager` class
- [ ] Create `ScheduledPaymentProcessor` class
- [ ] Enhance `GatewayCompatibility` class
- [ ] Implement payment method token storage
- [ ] Add scheduled payment processing
- [ ] Test core functionality

### Phase 2: Gateway Integration
- [ ] Enhance Stripe integration
- [ ] Implement PayPal integration
- [ ] Add Square support
- [ ] Add Razorpay support
- [ ] Add Mollie support
- [ ] Test all gateway integrations

### Phase 3: Advanced Features
- [ ] Create `WebhookHandler` class
- [ ] Implement `PaymentRetryManager`
- [ ] Create `SubscriptionStatusManager`
- [ ] Add webhook processing
- [ ] Implement retry logic
- [ ] Test advanced features

### Phase 4: Monitoring & Analytics
- [ ] Create `PaymentAnalytics` class
- [ ] Implement `SubscriptionHealth` monitoring
- [ ] Create reporting dashboard
- [ ] Add performance metrics
- [ ] Implement alerting system
- [ ] Test monitoring features

---

*This document will be updated as implementation progresses. Last updated: [Current Date]*
