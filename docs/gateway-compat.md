++ wp-content/plugins/subscription/docs/gateway-compat.md
/\*\*

- Plugin Name - Gateway Compatibility
-
- @package WPSubscription
- @copyright Copyright (c) 2024, Your Company Name
- @license GPL-2.0+
- @since 1.0.0
  \*/

# Payment Gateway Compatibility Plan

## Shared Requirements

- Implement `WC_Payment_Gateway` contracts with `supports( 'subscriptions' )`, `supports( 'subscription_cancellation' )`, etc.
- Provide tokenization via `WC_Payment_Token` interface and secure storage that maps to WPSubscription tokens.
- Hook into `woocommerce_scheduled_subscription_payment_{gateway}` and forward to WPSubscription renewal processors.
- Handle webhook/event ingestion and translate into WPSubscription listener events.
- Maintain compatibility with gateway-specific settings tabs and admin notices.

## Core Components (Compat Layer)

- `Gateway_Bridge`: wraps existing WPSubscription gateway logic.
- `Token_Store`: manages CRUD for Woo tokens, ensuring sync with WPSubscription credentials.
- `Retry_Manager`: integrates with WCS retry rules (`wcs_get_retry_rules`).
- `Webhook_Router`: normalizes incoming gateway webhooks to WPSubscription events.
- `Capability_Resolver`: maps WCS gateway supports to WPSubscription features.

## Gateway-Specific Notes

### Stripe

- Use `WC_Stripe_Subscription_Service` contracts: ensure `process_payment`, `process_refund`, and `save_payment_method` align.
- Support setup intents for initial authorizations.
- Map webhook events (`invoice.payment_succeeded`, `invoice.payment_failed`, `customer.subscription.deleted`) to WPSubscription events.
- Token storage with multi-use cards and SEPA mandates.

### Razorpay

- Support order/payment capture flow; ensure subscription entity maps to Razorpay plan/razorpay_subscription_id.
- Handle webhook signatures and events (`subscription.charged`, `subscription.completed`, `subscription.cancelled`).
- Manage manual retry (Razorpay API does not auto retry; use compatibility retry manager).

### Payoneer

- Focus on recurring billing agreements; ensure API credentials stored securely.
- Webhooks for billing agreement activation/cancellation.
- Provide manual renewal command fallback if API lacks automated charges.

### Mollie

- Integrate with `mollie_customer_id` and mandate tokens.
- Handle SEPA/Credit Card sequences via Mollie mandates.
- Webhooks: `payment.paid`, `payment.failed`, `payment.charged_back`, `subscription.canceled`.
- Ensure compatibility with `mollie_wc_gateway_subscription_support()` functions.

## Testing Strategy

- Mock WooCommerce gateway interfaces in unit tests.
- End-to-end tests with sandbox credentials verifying checkout, renewal, cancellation, and webhook flows.
- CLI command to trigger dummy renewals per gateway.

## Risks

- Token synchronization race conditions between WooCommerce and WPSubscription.
- Webhook retries leading to duplicate renewals; implement idempotency checks.
- Differences in gateway capabilities (e.g., Payoneer manual renewals) require documented limitations.
