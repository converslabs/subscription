## Context

`get_paypal_plan_id()` (`Paypal.php:839`) stores and retrieves a single PayPal plan ID in post meta (`_wp_subs_paypal_{mode}_plan_id`). The check is binary: if the meta value is non-empty, it is returned immediately. `generate_plan_data()` is called on every invocation (line 854) but its result is discarded when a plan ID already exists. There is no comparison between the stored plan and the current product state.

**Critical billing fields** (those that determine a distinct PayPal plan):

- Regular price (tax-inclusive, 2 decimal string)
- Currency code
- Billing interval unit (DAY / WEEK / MONTH / YEAR)
- Billing interval count
- Trial interval unit
- Trial interval count
- Signup fee (2 decimal string)
- Max payment cycles (`_subscrpt_max_no_payment`)

## Goals / Non-Goals

**Goals:**

- Detect when any critical billing field has changed since the last stored plan.
- Create a new PayPal plan when a change is detected.
- Reuse an existing stored plan when all critical fields are identical (no API call).
- Support accumulation of multiple plans per product (one per distinct configuration history).

**Non-Goals:**

- Deleting or deactivating old PayPal plans in the API (PayPal does not support plan deletion; old plans become unused but remain in PayPal).
- Migrating existing `plan_id` meta to the new format (existing products will generate a new plan on next checkout — acceptable).
- Locking / concurrency protection for simultaneous checkouts (pre-existing limitation).

## Decisions

**MD5 fingerprint over raw field storage**

Storing a compact MD5 hash in the plans array keeps the meta value small. The pre-image is deterministic (same inputs → same hash), locale-safe (all values normalized before hashing), and collision risk is negligible for this domain. Alternative: store a JSON description string per Paddle's approach (`price:10.90|currency:USD|…`) — readable but larger and harder to compare reliably. MD5 chosen for compactness.

**Array of `{plan_id, fingerprint}` in a single meta key**

Storing all historical plans in one serialized array keeps meta reads to a single `get_post_meta()` call. Alternative: one meta row per plan (key includes fingerprint) — avoids serialization but causes unbounded meta row growth and more complex lookup. Array chosen.

**New meta key `_wp_subs_paypal_{mode}_plans` (not migrating old `plan_id`)**

Reading the old `plan_id` without its fingerprint would require an API call to verify the plan's price — complex and rate-limited. Starting fresh means one new plan per product on first checkout after upgrade, which is correct behavior (old plan may have been stale). Old key left in place but ignored.

**Extract `$convert_interval` closure to `convert_paypal_interval()` private method**

Both `generate_plan_data()` and the new `generate_plan_fingerprint()` need interval normalization. Duplicating the closure would create a silent divergence risk. Extracted to a private method — not a speculative abstraction, required for correctness.

## Risks / Trade-offs

- **Risk**: Product with many price changes accumulates many entries in the `plans` meta array.
  → Mitigation: Each entry is ~60 bytes (`plan_id` ≈ 30 chars + 32-char MD5 hash). 100 changes = ~6 KB — negligible. No pruning needed.

- **Risk**: Two simultaneous checkouts for a product with no matching plan could each create a new plan before either writes to meta.
  → Mitigation: Both plans would be identical; the second would be a duplicate in PayPal but both are valid. On next checkout a third won't be created (one of the two will match the fingerprint). Pre-existing race — acceptable for now.

- **Risk**: `wc_get_price_including_tax()` returns a different value depending on WooCommerce tax settings or customer tax class at checkout vs. at admin save time.
  → Mitigation: Fingerprint is generated from `wc_get_price_including_tax( $wc_product )` at the time of checkout (same call as plan creation) — consistent within a single checkout flow.

## Migration Plan

No database migration. On first checkout after upgrade, existing products will have an empty `plans` array, find no fingerprint match, and create a new PayPal plan. The new plan ID is stored in the `plans` array. Subsequent checkouts with the same price will reuse it. Old `plan_id` meta rows remain in the database but are never read again.

Rollback: revert changes to `get_paypal_plan_id()`. Old `plan_id` meta is still present for products that haven't had a post-upgrade checkout. Products that have already gotten a new plan under the new code will create yet another plan on rollback — acceptable for a rollback scenario.
