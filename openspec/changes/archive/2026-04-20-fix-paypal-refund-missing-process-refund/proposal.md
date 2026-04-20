## Why

The `wp_subscription_paypal` gateway declares `'refunds'` in `$this->supports` but never implements `process_refund()`. WooCommerce calls the base-class stub which returns `false`, causing every refund attempt to fail with "An error occurred while attempting to create the refund using the payment gateway API." Partial and full refunds are completely broken for all PayPal subscription orders.

## What Changes

- Add `process_refund( $order_id, $amount, $reason )` to `Paypal.php` — calls PayPal v2 Captures API to issue partial or full refunds against the stored capture ID.

## Capabilities

### New Capabilities

- `paypal-refund`: Process partial or full refunds for orders paid via `wp_subscription_paypal` gateway using PayPal's v2 Captures refund API.

### Modified Capabilities

## Impact

- `includes/Illuminate/Gateways/Paypal/Paypal.php` — new public method added
- PayPal v2 API: `POST /v2/payments/captures/{capture_id}/refund`
- No hook changes; no Pro integration affected
- Requires valid PayPal capture ID stored on the order via `set_transaction_id()`
