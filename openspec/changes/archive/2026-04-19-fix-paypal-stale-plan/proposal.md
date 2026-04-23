## Why

Once a PayPal billing plan is created for a product, its ID is stored as a single post meta value and reused for every subsequent checkout — forever. When an admin changes the product price, billing interval, trial period, or signup fee in WooCommerce, the old PayPal plan is still used at checkout. Customers are billed at the original price and schedule. The only fix today is manually deleting the `_wp_subs_paypal_*_plan_id` record from the database. Two `TODO` comments in `get_paypal_plan_id()` (lines 856 and 872) mark this as known-unimplemented.

## What Changes

- Add a `generate_plan_fingerprint()` method that encodes all critical billing fields (price, currency, interval, trial, signup fee, total cycles) as an MD5 hash.
- Extract the `$convert_interval` closure in `generate_plan_data()` into a private reusable method `convert_paypal_interval()`.
- Replace single `plan_id` meta storage with a `plans` array meta storing `{plan_id, fingerprint}` entries.
- Refactor `get_paypal_plan_id()` to: look up the current fingerprint in the stored plans array; return the matching plan if found; otherwise create a new plan and append it to the array.

## Capabilities

### New Capabilities

- `paypal-plan-fingerprint-cache`: Fingerprint-based PayPal plan cache that detects when critical product billing fields change and creates a new plan, while deduplicating plans with identical configuration.

### Modified Capabilities

<!-- No existing spec-level requirements are changing — this implements the two TODO stubs. -->

## Impact

- **File**: `includes/Illuminate/Gateways/Paypal/Paypal.php` — three method changes + two new methods.
- **Meta key change**: New meta key `_wp_subs_paypal_{mode}_plans` (array) replaces the single `_wp_subs_paypal_{mode}_plan_id` string for new lookups. Old `plan_id` key is no longer written or read; existing values are silently ignored (products get a fresh plan on next checkout).
- **No hook changes**: No `subscrpt_*` hooks added, removed, or modified.
- **PayPal API**: Same `POST /v1/billing/plans` endpoint used; new plans created only when fingerprint differs from all stored plans.
- **No breaking changes** for Pro plugin or third-party integrations.
