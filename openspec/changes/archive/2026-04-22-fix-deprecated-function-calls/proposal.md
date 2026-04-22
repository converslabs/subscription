## Why

Internal plugin code still calls `wp_`-prefixed deprecated function aliases (`wp_subscrpt_write_log`, `wp_subscrpt_write_debug_log`) that were superseded in v1.9.2. These wrappers exist in `LegacyCompat.php` solely for third-party/Pro compatibility — the free plugin itself should use the canonical `subscrpt_`-prefixed names directly.

## What Changes

- Replace all internal calls to `wp_subscrpt_write_log(` with `subscrpt_write_log(` across 6 files
- Replace all internal calls to `wp_subscrpt_write_debug_log(` with `subscrpt_write_debug_log(` across 5 files
- Add missing `_deprecated_function()` notice to the `wp_subscrpt_write_log` wrapper in `LegacyCompat.php` (the only one of the four wrappers that omits it)

## Capabilities

### New Capabilities

None.

### Modified Capabilities

None. This is a pure internal call-site cleanup — no behavior changes, no hook contract changes, no API surface changes.

## Impact

- **Files modified**: `AutoRenewal.php`, `Helper.php`, `Checkout.php`, `Order.php`, `Stripe/Stripe.php`, `Paypal/Paypal.php`, `LegacyCompat.php`
- **No hook changes**: no `subscrpt_*` hooks are added, removed, or reordered
- **No API changes**: canonical functions are identical in signature and behavior to the wrappers
- **Pro compatibility preserved**: `LegacyCompat.php` wrappers remain; only their internal `_deprecated_function()` notice is added
