## Context

The `Paypal` gateway class (`includes/Illuminate/Gateways/Paypal/Paypal.php`) extends `\WC_Payment_Gateway` and declares `'refunds'` in `$this->supports`. WooCommerce checks this flag before calling `process_refund()` — because the flag is set, WooCommerce proceeds, hits the base-class stub returning `false`, and throws the generic refund error.

PayPal's v2 Payments API supports refunding a captured payment via `POST /v2/payments/captures/{capture_id}/refund`. The capture ID is already stored on the WooCommerce order via `$order->set_transaction_id()` in the `PAYMENT.SALE.COMPLETED` webhook handler.

## Goals / Non-Goals

**Goals:**

- Implement `process_refund()` to call PayPal v2 Captures API for partial and full refunds.
- Use existing infrastructure: `$this->api_endpoint`, `$this->get_paypal_access_token()`.
- Return `true` on success, `WP_Error` on failure (WooCommerce contract).

**Non-Goals:**

- Handling orders that predate transaction ID storage (no migration for old orders).
- Refund via v1 NVP/SOAP API.
- Automatic refund on subscription cancellation.

## Decisions

**Use PayPal v2 Captures API (`/v2/payments/captures/{id}/refund`)**
Rationale: The stored transaction ID (`set_transaction_id`) is a PayPal sale/capture ID from the `PAYMENT.SALE.COMPLETED` webhook. The v2 API is current and REST-based, consistent with all other API calls in the gateway.

**Return `WP_Error` (not `false`) on failure**
Rationale: WooCommerce displays `WP_Error::get_error_message()` to the admin when a `WP_Error` is returned, giving actionable feedback. Returning `false` shows only the generic error.

**`note_to_payer` capped at 255 chars**
Rationale: PayPal API limit for this field.

## Risks / Trade-offs

- **Missing capture ID on older orders** → `WP_Error` returned with clear message; admin sees "PayPal capture ID not found on this order." No silent failure.
- **Full refund amount** → When `$amount === null` (WooCommerce full refund), omit `amount` from request body; PayPal defaults to full capture amount.
- **Duplicate refund** → PayPal rejects duplicate `PayPal-Request-Id`; using `uniqid()` prevents accidental replay but does not deduplicate admin double-clicks. Risk: low.
