## Why

PayPal's REST API requires monetary amounts as strings with exactly 2 decimal places (e.g., `"10.90"`), but the plugin sends raw PHP floats (e.g., `10.9`) for the recurring price and an un-normalized string for the signup fee. This causes PayPal to reject plans or create subscriptions with wrong prices for products priced with non-round decimals. Regression introduced in commit `4d1a00b` which removed `wpsubs_format_price()` without a replacement.

## What Changes

- Format `$price` (recurring billing amount) with `number_format( (float) $price, 2, '.', '' )` before inserting into PayPal plan payload.
- Format `$signup_fee` with the same pattern instead of bare `(string)` cast.

## Capabilities

### New Capabilities

- `paypal-price-formatting`: Correct decimal formatting of PayPal billing plan amounts (recurring price and setup fee) to comply with PayPal REST API requirements.

### Modified Capabilities

<!-- No existing spec-level requirements are changing — this is a bug fix restoring correct API compliance. -->

## Impact

- **File**: `includes/Illuminate/Gateways/Paypal/Paypal.php` — `generate_plan_data()` method, lines 1251 and 1267.
- **API**: PayPal `/v1/billing/plans` — plan creation payload `billing_cycles[].pricing_scheme.fixed_price.value` and `payment_preferences.setup_fee.value`.
- **No hook changes**: No `subscrpt_*` hooks added, removed, or modified.
- **No breaking changes** for end users or Pro plugin.
