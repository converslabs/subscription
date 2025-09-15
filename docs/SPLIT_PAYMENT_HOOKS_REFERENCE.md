# Split Payment Hooks Reference

## Overview

This document provides a comprehensive reference for the Split Payment feature implementation in WP Subscription Pro. The split payment system allows customers to pay for subscriptions in installments rather than recurring payments.

## Current Implementation Status

### ✅ **COMPLETED FEATURES**

#### 1. Product Page Settings

- **Payment Type Selection**: Choose between 'recurring' and 'split_payment'
- **Number of Payments**: Set total installments (minimum 2)
- **Access Ends Timing**: Three options:
  - `lifetime`: Lifetime access after completing all payments
  - `after_full_duration`: Access ends after full subscription duration (payment interval × number of payments)
  - `custom_duration`: Custom access period after first payment (e.g., 6 months after first payment)
- **Custom Access Duration**: Time and type (days/weeks/months/years) for custom duration
- **Payment Failure Handling**: Managed globally through WPS Pro Settings (applies to all products)

**Files**:

- `includes/Admin/Product/Simple.php` (Simple products)
- `includes/Admin/Product/Variable.php` (Variable products)

#### 2. Order Page Integration

- **Split Payment Notes**: Orders automatically show split payment information
- **Payment Progress Tracking**: Shows current payment vs total payments
- **Order-Subscription Linking**: Proper relationship tracking between orders and subscriptions

**Files**: `includes/Illuminate/Helper.php`

#### 3. Subscription Admin Page

- **Split Payment Activities**: Complete payment history and status tracking
- **Payment Progress**: Shows payments made vs total required
- **Access Control Information**: Displays when access will end based on settings
- **Payment Failure Logging**: Tracks failed payments and retry attempts
- **Renewal Notes**: Comprehensive activity logging

**Files**: `includes/Illuminate/SplitPaymentHandler.php`

#### 4. User Subscription Page

- **Payment Progress Display**: Shows "X/Y payments completed"
- **Payment Type Indicator**: Clearly shows "Split Payment" vs "Recurring"
- **Remaining Payments**: Displays count of remaining installments
- **Access Duration Info**: Shows when access will end

**Files**: `templates/myaccount/single.php`

#### 5. WPS Settings Page

- **Split Payment Retry Settings**: Global configuration for payment retries
- **Grace Period Management**: Default grace period settings
- **Payment Failure Handling**: Comprehensive failure management system

**Files**: `includes/Admin/Settings.php`

#### 6. Reminder Emails

- **Payment Due Reminders**: Automated emails before next payment
- **Failure Notifications**: Alerts for failed payments
- **Completion Notifications**: Emails when split payment plan is completed

**Files**: `includes/Illuminate/Emails/RenewReminder.php`

#### 6. Email System

- **Payment Due Reminders**: Automated emails before next payment
- **Payment Failure Notifications**: Professional email templates for failed payments
- **Delayed Failure Notifications**: Configurable delayed emails to prevent spam
- **Grace Period Warnings**: Proactive customer communication
- **Completion Notifications**: Emails when split payment plan is completed

**Files**:

- `includes/Illuminate/Emails/RenewReminder.php` (Free)
- `includes/Illuminate/Emails/PaymentFailure.php` (Pro)
- `templates/emails/payment-failure-html.php` (Pro)
- `templates/emails/plains/payment-failure-plain.php` (Pro)

#### 7. Core Split Payment Hooks

- **Payment Completion**: `subscrpt_split_payment_completed`
- **Payment Failure**: `subscrpt_split_payment_failed`
- **Arguments Filter**: `subscrpt_split_payment_args`
- **Total Override**: `subscrpt_split_payment_total_override`
- **Expire Status**: `subscrpt_split_payment_expire_status`
- **Next Due Date**: `subscrpt_split_payment_next_due_date`

**Files**: `includes/functions.php`, `includes/Illuminate/SplitPaymentHandler.php`

### 🔧 **CORE CLASSES & FUNCTIONS**

#### SplitPaymentHandler Class

```php
namespace SpringDevs\SubscriptionPro\Illuminate;

class SplitPaymentHandler {
    // Calculate access end dates
    public static function calculate_access_end_date($subscription_id)

    // Handle payment completion
    public static function handle_split_payment_completion($subscription_id, $payments_made, $max_payments)

    // Get payment dates
    public static function get_final_payment_date($subscription_id)
    public static function get_first_payment_date($subscription_id)
}
```

#### PaymentFailureHandler Class

```php
namespace SpringDevs\SubscriptionPro\Illuminate;

class PaymentFailureHandler {
    // Handle payment failures
    public static function handle_payment_failure($subscription_id)

    // Manage retries and grace periods
    public static function schedule_payment_retry($subscription_id, $failure_count)
    public static function start_grace_period($subscription_id, $grace_period_days)

    // Send notifications
    public static function send_failure_notification($subscription_id, $failure_count, $max_retries)
    public static function send_delayed_failure_notification($subscription_id, $failure_count, $max_retries)
    public static function send_grace_period_warnings()
}
```

#### Helper Functions

```php
// Check if max payments reached
function subscrpt_is_max_payments_reached($subscription_id)

// Get remaining payments
function subscrpt_get_remaining_payments($subscription_id)

// Get payment type
function subscrpt_get_payment_type($subscription_id)

// Count payments made
function subscrpt_count_payments_made($subscription_id)
```

### 📋 **IMPLEMENTATION DETAILS**

#### Product Meta Fields

- `_subscrpt_payment_type`: 'recurring' or 'split_payment'
- `_subscrpt_max_no_payment`: Number of total payments
- `_subscrpt_access_ends_timing`: When access ends
- `_subscrpt_custom_access_duration_time`: Custom duration value
- `_subscrpt_custom_access_duration_type`: Custom duration unit
- `_subscrpt_max_payment_retries`: Maximum retry attempts
- `_subscrpt_payment_grace_period`: Grace period in days

#### Subscription Meta Fields

- `_subscrpt_split_payment_completed_fired`: Prevents duplicate completion actions
- `_subscrpt_access_end_date`: Calculated access end timestamp
- `_subscrpt_payment_failure_count`: Current failure count
- `_subscrpt_grace_period_start`: Grace period start timestamp
- `_subscrpt_grace_period_warning_sent`: Prevents duplicate grace period warnings

#### Database Tables

- `wp_subscrpt_order_relation`: Links orders to subscriptions
- `wp_posts`: Subscription posts with custom post type 'subscrpt_order'
- `wp_comments`: Activity notes and payment history

### 🚀 **AVAILABLE HOOKS FOR DEVELOPERS**

#### Actions (do_action)

```php
// Split payment completed
do_action('subscrpt_split_payment_completed', $subscription_id, $payments_made, $max_payments);

// Payment failure logged
do_action('subscrpt_payment_failure_logged', $subscription_id, $failure_count);

// Payment failure notification sent
do_action('subscrpt_payment_failure_notification_sent', $subscription_id, $customer_id, $failure_count);

// Delayed payment failure notification sent
do_action('subscrpt_delayed_payment_failure_notification_sent', $subscription_id, $customer_id, $failure_count);

// Access suspended
do_action('subscrpt_access_suspended', $subscription_id);

// Access restored
do_action('subscrpt_access_restored', $subscription_id);

// Grace period warning sent
do_action('subscrpt_grace_period_warning_sent', $subscription_id, $customer_id, $days_remaining);

// Grace period expired
do_action('subscrpt_grace_period_expired', $subscription_id);
```

#### Filters (apply_filters)

```php
// Modify split payment arguments
$args = apply_filters('subscrpt_split_payment_args', $args, $order_item, $product);

// Override total payments
$max_payments = apply_filters('subscrpt_split_payment_total_override', $max_payments, $subscription_id, $product_id);

// Customize expire status
$expire_status = apply_filters('subscrpt_split_payment_expire_status', 'expired', $subscription_id, $payments_made, $max_payments);

// Modify next due date
$next_date = apply_filters('subscrpt_split_payment_next_due_date', $next_date, $subscription_id, $recurr_timing, $type);

// Override default max payment retries
$default_retries = apply_filters('subscrpt_default_max_payment_retries', $default_retries);

// Override default grace period days
$default_grace_period = apply_filters('subscrpt_default_grace_period_days', $default_grace_period);
```

### 📧 **EMAIL TEMPLATES**

#### Available Email Types

1. **Payment Due Reminders**: Sent before next payment date
2. **Payment Failure Notifications**: Professional email templates for failed payments
3. **Delayed Payment Failure Notifications**: Sent after configurable delay to avoid spam
4. **Grace Period Warnings**: Notify customers before access suspension
5. **Completion Notifications**: Inform when split payment plan is finished

#### Email Customization

- **Free Templates**: Located in `templates/emails/` (subscription plugin)
- **Pro Templates**: Located in `templates/emails/` (subscription-pro plugin)
- **Customizable**: Subjects, content, and styling
- **Placeholder Support**: Dynamic data insertion
- **Automatic Triggers**: Cron job integration
- **Configurable Delays**: Prevent spam during temporary issues
- **HTML & Plain Text**: Both formats supported
- **Theme Override**: Templates can be customized in active theme

#### Payment Failure Email Features

- **Professional Design**: WooCommerce-compatible email styling
- **Dynamic Content**: Shows failure count, remaining attempts, and subscription details
- **Smart Messaging**: Different content based on failure status
- **Delayed Delivery**: Configurable delays to prevent spam
- **Customer Support**: Clear next steps and contact information

### ⚙️ **CONFIGURATION OPTIONS**

#### Global Settings (WPS Pro Settings Page)

- **Default Payment Retries**: 3 attempts (0-10)
- **Default Grace Period**: 7 days (0-30)
- **Enable Payment Failure Emails**: Toggle email notifications on/off
- **Payment Failure Email Delay**: 24 hours delay to avoid spam (1-168 hours)
- **Enable Grace Period Notifications**: Toggle grace period warnings on/off
- **Grace Period Warning Days**: 2 days before expiry (1-7 days)

#### Per-Product Settings

- **Payment Type**: Recurring or Split Payment
- **Installment Count**: 2-∞ payments
- **Access Timing**: Flexible access control
- **Failure Handling**: Custom retry and grace settings (overrides global defaults)

#### Email Settings

- **Failure Notification Delay**: Prevents spam during temporary payment issues
- **Grace Period Warnings**: Proactive customer communication
- **Configurable Content**: Customizable email templates and messages

### 🔍 **DEBUGGING & MONITORING**

#### Debug Logging

- Comprehensive error logging for payment failures
- Payment progress tracking
- Access timing calculations
- Retry attempt monitoring
- Grace period management

#### Admin Notifications

- Payment failure alerts
- Completion status updates
- Access suspension notifications
- Grace period warnings
- Retry attempt logging

#### Customer Communication

- Immediate failure notifications
- Delayed failure notifications (configurable)
- Grace period warnings
- Access suspension alerts

## 🆕 **NEW PAYMENT FAILURE HANDLING FEATURES**

### Enhanced Settings Integration

The Payment Failure Handling settings are now fully integrated into the WPS Pro Settings page, following the same pattern as the existing API settings. This provides administrators with centralized control over:

1. **Global Defaults**: Set default retry and grace period values for all products
2. **Email Management**: Control when and how failure notifications are sent
3. **Customer Experience**: Balance between immediate alerts and spam prevention

## 🆕 **ACCESS TIME SETTINGS EXPLAINED**

### What Do Access Time Settings Do?

The Access Time Settings control **when customer access ends** for split payment subscriptions. This is crucial for managing customer experience and business revenue:

#### **Three Access Timing Options:**

1. **`lifetime`** - Lifetime Access After Completion
   - **Behavior**: Access continues indefinitely after completing all payments
   - **Use Case**: For products where you want to reward customers with permanent access after completing their payment plan
   - **Example**: A premium course with 4 payments - customers get lifetime access after completing all installments

2. **`after_full_duration`** - Full Subscription Duration (Default)
   - **Behavior**: Access continues for the full subscription period (payment interval × number of payments)
   - **Use Case**: Standard subscription behavior where customers get full value for their payments
   - **Example**: A 12-month course with 4 payments - access continues for 12 months from start date

3. **`custom_duration`** - Flexible Access Extension
   - **Behavior**: Access continues for a custom period after the first payment is completed
   - **Use Case**: When you want to give customers immediate access but control how long it lasts
   - **Example**: A 12-month course with 4 payments, but access continues for 6 months after first payment

### **Why This Matters:**

- **Customer Experience**: Controls how long customers can access your content
- **Revenue Protection**: Ensures customers pay for the access they receive
- **Business Flexibility**: Allows different strategies for different products
- **Access Control**: Prevents customers from getting unlimited access with partial payments

### **Global vs Per-Product Settings:**

- **Payment Failure Handling**: Now managed globally through WPS Pro Settings (applies to all products)
- **Access Time Settings**: Still configurable per product for maximum flexibility
- **Rationale**: Payment failures are business-wide concerns, while access timing is product-specific

### **Technical Implementation:**

The Access Time Settings are implemented through the `SplitPaymentHandler` class which:

1. **Calculates Access End Dates**: Uses different logic based on the selected timing option
2. **Handles Payment Completion**: Applies the appropriate access control when payments are completed
3. **Logs Access Decisions**: Records detailed activity notes explaining why access continues or expires
4. **Integrates with Cron**: Automatically expires access when the calculated end date is reached (except for lifetime access)

#### **Key Methods:**

```php
// Calculate when access should end
SplitPaymentHandler::calculate_access_end_date($subscription_id)

// Handle access control when payments complete
SplitPaymentHandler::handle_split_payment_completion($subscription_id, $payments_made, $max_payments)

// Check if access should be expired
SplitPaymentHandler::should_expire_access($subscription_id)
```

### Smart Email Delivery

- **Configurable Delays**: Prevent spam during temporary payment issues
- **Grace Period Warnings**: Proactive customer communication
- **Failure Tracking**: Comprehensive logging of all payment attempts
- **Customer Support**: Clear communication about next steps

### Automated Workflow

- **Retry Scheduling**: Exponential backoff for payment retries
- **Grace Period Management**: Automatic access control during payment issues
- **Status Tracking**: Real-time subscription status updates
- **Cleanup**: Automatic cleanup when payments succeed

---

_This documentation covers the current implementation as of the latest plugin version. For updates and new features, refer to the plugin changelog and development notes._
