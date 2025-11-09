++ wp-content/plugins/subscription/docs/data-strategy.md
/\*\*

- Plugin Name - Data Strategy
-
- @package WPSubscription
- @copyright Copyright (c) 2024, Your Company Name
- @license GPL-2.0+
- @since 1.0.0
  \*/

# Data Alignment & Migration Strategy

## Goals

- Preserve existing WPSubscription functionality while exposing WooCommerce Subscriptions-compatible data.
- Minimize downtime and avoid destructive migrations.
- Ensure data integrity and consistency between schemas.

## Current WPSubscription Model (Baseline)

- Primary storage: custom tables (`wps_subscriptions`, `wps_subscription_items`, `wps_subscription_events`).
- Schedules managed via custom cron/listener system.
- Subscription identifiers use UUID strings.
- Status values: `active`, `paused`, `cancelled`, `expired`, `trial`.

## Target WooCommerce Subscriptions Model

- CPT `shop_subscription` with meta-driven schedule data.
- WooCommerce order linkage via `_subscription_renewal` order meta.
- Uses Action Scheduler for renewal events.
- Status taxonomy prefixed with `wc-`.

## Proposed Alignment Approach

### 1. Dual-Writing Layer

- Continue writing to WPSubscription tables for native flow.
- Introduce adapter to simultaneously persist mirrored `shop_subscription` posts and meta.
- Implement transactional wrapper: if Woo persistence fails, roll back WPSubscription insert and log error.

### 2. Synchronization Service

- Background job to reconcile discrepancies (batch compare IDs, status, schedule).
- Command: `wp wps compat sync` to force resync.

### 3. Identifier Mapping

- Store cross-reference meta `_wps_subscription_id` on `shop_subscription` posts.
- Maintain map table `wps_subscription_map` (`wps_id`, `wcs_post_id`, `created_at`, `synced_at`).

### 4. Status Translation Matrix

| WPSubscription | WooCommerce Subscriptions   |
| -------------- | --------------------------- |
| `active`       | `wc-active`                 |
| `paused`       | `wc-on-hold`                |
| `cancelled`    | `wc-cancelled`              |
| `expired`      | `wc-expired`                |
| `trial`        | `wc-pending` (until active) |

- Provide filters `wps_wcs_status_map` for customization.

### 5. Schedule Mapping

- Convert internal schedule objects into meta values `_schedule_*`.
- When Action Scheduler triggers renewal, delegate to WPSubscription renewal services.
- Record last run timestamps in both systems to avoid duplicate billing.

### 6. Data Validation

- Before saving mirrored data, validate with WooCommerce schema (required fields, date formats).
- Add automated unit tests covering status/schedule translation.

### 7. Migration Path

1. **Phase A (Read-Only Mirror)**: Generate `shop_subscription` posts from existing WPSubscription data without switching flows.
2. **Phase B (Dual-Write)**: On new subscription events, persist to both stores; keep WPSubscription as source of truth.
3. **Phase C (Optional Switch)**: Once parity confirmed, optionally shift source of truth to Woo schema while still feeding WPSubscription as fallback.

### 8. Rollback & Fallback

- Feature flag `wps_wcs_mirror_enabled` (filter + option) to disable compatibility layer quickly.
- Maintain backup of new tables before migration.
- Provide CLI command to purge mirrored data if revert required.

### 9. Risks

- Race conditions between cron (Action Scheduler) and WPSubscription listeners.
- Performance overhead due to dual writes; mitigate via batched operations and async processing.
- Third-party plugins may update `shop_subscription` posts directly; ensure sync back to WPSubscription when needed.

### 10. Monitoring

- Add logging around synchronization failures.
- Dashboard widget summarizing drift (counts mismatched statuses, missing posts).
- Hook into WooCommerce status change to trigger immediate resync.
