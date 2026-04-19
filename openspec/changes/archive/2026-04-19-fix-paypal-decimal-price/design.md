## Context

PayPal's REST API (`/v1/billing/plans`) requires monetary `value` fields as **strings** with exactly 2 decimal places and a period as decimal separator, regardless of locale. Example: `"10.90"`, `"0.00"`, `"1099.99"`.

`generate_plan_data()` in `Paypal.php` assembles the plan payload:

- `$price` from `wc_get_price_including_tax()` — returns PHP `float` (e.g., `10.9`)
- `$signup_fee` from `get_signup_fee()` — returns PHP `float` (e.g., `0.0` or `5.5`)

Both are placed into the payload without formatting. `wp_json_encode()` serializes PHP floats as JSON numbers (`10.9`), not strings (`"10.90"`). PayPal rejects or misinterprets these.

Regression source: commit `4d1a00b` removed the old `wpsubs_format_price()` helper (which was locale-unsafe) and left no replacement.

## Goals / Non-Goals

**Goals:**

- Send `value` fields as strings with exactly 2 decimal places and period decimal separator.
- Fix both the recurring price and the setup fee fields.
- Use the same pattern already established in `MyAccount.php`, `Order.php`, and `Admin/Subscriptions.php`.

**Non-Goals:**

- Currency conversion or WooCommerce tax recalculation.
- Fixing other PayPal API fields beyond `value`.
- Changing behavior for non-decimal (whole number) prices.

## Decisions

**Use `number_format( (float) $value, 2, '.', '' )`**

- Forces 2 decimal places, period separator, no thousands separator.
- Matches pattern used elsewhere in the codebase.
- Locale-safe: `number_format` with explicit separators ignores `LC_NUMERIC`.
- Alternative `wc_format_decimal()` was considered but returns a string that may still use locale decimal separator in some WooCommerce configurations — not safe for API payloads.
- Alternative `round()` alone is insufficient — still produces a float, not a properly formatted string.

**Apply at payload assembly, not at data retrieval**

- `wc_get_price_including_tax()` and `get_signup_fee()` are reused elsewhere and should not be altered.
- Format only at the point where PayPal API values are set.

## Risks / Trade-offs

- **Risk**: Floating-point rounding edge cases (e.g., `10.005` rounds to `"10.00"` or `"10.01"` depending on PHP float precision).
  → Mitigation: Same risk exists in WooCommerce core price display; `number_format` with 2 decimal places matches WooCommerce's own display rounding, so the PayPal amount will match what the customer sees.

- **Risk**: Pro plugin's `get_signup_fee()` override returns a value that formats unexpectedly.
  → Mitigation: `(float)` cast before `number_format` handles any string/numeric type safely.

## Migration Plan

No data migration needed. Change takes effect immediately on next PayPal plan creation. Existing PayPal plans stored in post meta are not affected — those were already created in PayPal's system.

Rollback: revert the two `number_format` calls in `generate_plan_data()`.
