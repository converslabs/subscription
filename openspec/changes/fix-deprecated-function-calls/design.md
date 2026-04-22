## Context

Since v1.9.2 the plugin renamed all global symbols from `wp_`/`WP_SUBSCRIPTION_` prefixes to `subscrpt_`/`SUBSCRPT_`. The old names were kept alive in `includes/LegacyCompat.php` as thin wrapper functions for backwards compatibility with the Pro plugin and any third-party integrations.

However, the free plugin's own source files were never updated — they continue to call the deprecated wrappers rather than the canonical functions directly. This creates unnecessary indirection on every log call and suppresses the deprecation notices that would alert external callers.

Current state (from code-search):

- `wp_subscrpt_write_log`: **58 call sites** across 6 files (2 already fixed in `AutoRenewal.php`)
- `wp_subscrpt_write_debug_log`: **41 call sites** across 4 files
- `wp_subscrpt_write_log` wrapper in `LegacyCompat.php` is the only one of the four wrappers missing `_deprecated_function()`

## Goals / Non-Goals

**Goals:**

- Eliminate all internal uses of `wp_subscrpt_write_log` and `wp_subscrpt_write_debug_log` in free plugin source files
- Add `_deprecated_function()` to the `wp_subscrpt_write_log` wrapper so it matches the other three wrappers
- Leave `LegacyCompat.php` wrappers in place (Pro/third-party may still call them)

**Non-Goals:**

- Removing `LegacyCompat.php` or any wrapper functions
- Touching `wp_subs_multiselect_field` or `wp_subscription_register_paypal_block` (zero internal call sites)
- Changing function signatures, log format, or behavior

## Decisions

**Use `replace_all` string replacement per file, not a global sed.**
Each file gets its own edit pass so changes are reviewable in isolation and rollback is scoped to a single file. Global sed across the repo risks touching strings in comments or test fixtures.

**Fix `LegacyCompat.php` in the same change.**
The missing `_deprecated_function()` in `wp_subscrpt_write_log` is a companion defect — it was clearly forgotten when the other three wrappers were written. Fixing it here is zero risk and completes the consistency of the compat layer.

**File order: smallest → largest.**
`AutoRenewal.php` (done) → `Checkout.php` → `Order.php` → `Helper.php` → `Stripe.php` → `Paypal.php` → `LegacyCompat.php`. Smaller files first keeps early diffs reviewable; `Paypal.php` (78 combined calls) is last as the highest-volume change.

## Risks / Trade-offs

- **Near-zero risk**: canonical functions are identical in signature and body to the wrappers; no behavior changes.
- [Risk: Pro calls the internal function via hook] → Not applicable — Pro hooks into `subscrpt_*` action hooks, not these log utility functions.
- [Risk: `_deprecated_function()` on `wp_subscrpt_write_log` triggers notices in Pro] → Acceptable. The notice is the intended signal; Pro should update its own calls if it uses them. The wrapper still delegates correctly.

## Migration Plan

1. Replace call sites file by file (tasks.md order).
2. Add `_deprecated_function()` to `LegacyCompat.php` wrapper last.
3. No database changes, no option migrations, no cache flushes needed.

**Rollback**: `git revert` the commit. No state is persisted.
