# Drop-in Migration TODOs

## Completed Work

- [x] **Compatibility Research** - Baseline compatibility research recorded in `docs/compatibility-analysis.md`, covering WCS APIs, hooks, and data schema.
- [x] **Architecture Planning** - Compatibility architecture planned in `docs/compat-architecture.md`, including directory layout and bootstrap flow.
- [x] **Data Strategy** - Data alignment strategy drafted in `docs/data-strategy.md`, defining dual-write approach and status mapping.
- [x] **Gateway Documentation** - Gateway adapter requirements documented in `docs/gateway-compat.md` for Stripe, Razorpay, Payoneer, and Mollie.
- [x] **Testing Strategy** - TDD testing strategy defined in `docs/testing-strategy.md`, with PHPUnit scaffold and initial failing tests established.
- [x] **Test Environment** - Composer dev dependencies updated with PHPUnit + Polyfills; WordPress test installer script added under `tests/bin/install-wp-tests.sh`.
- [x] **Bootstrap Layer** - Compatibility bootstrap created in `includes/Compat/WooSubscriptions/`, with autoloader, facade, and procedural helpers loaded from `subscription.php`.
- [x] **Subscription Locator** - `SubscriptionLocator` implemented to expose WCS-style subscription lookups, meta aliases, and status normalization.
- [x] **WC_Subscription Facade** - `WC_Subscription` facade extended with accessors to mirror WooCommerce behaviour.
- [x] **Hook Registry** - Hook registry (`HookRegistry`) introduced to bridge key WooCommerce gateway and lifecycle hooks to WPSubscription actions.
- [x] **PHPUnit Tests** - PHPUnit suite expanded (`tests/phpunit/test-compat-layer.php`) to cover facade behaviour, filters, and hook bridges.
- [x] **Sync Service** - Dual-write synchronisation implemented via `SyncService`, mirroring `subscrpt_order` posts to `shop_subscription` CPTs with schedule meta and reconciliation cron.
- [x] **PSR-4 Refactoring** - Compatibility namespace refactored to PSR-4 structure under `includes/Compat/WooSubscriptions/` with Composer autoload integration.
- [x] **Action Scheduler** - Action Scheduler integration (`ActionScheduler`) maps WPSubscription schedules to WooCommerce-style renewal tasks (`woocommerce_scheduled_subscription_payment`, `woocommerce_scheduled_subscription_trial_end`, `woocommerce_scheduled_subscription_expiration`), ensuring `_schedule_*` meta stays in sync with renewal events.
- [x] **Stripe Gateway Adapter** - Stripe gateway adapter (`StripeAdapter`) bridges WooCommerce Subscriptions Stripe hooks to WPSubscription's Stripe integration, handling scheduled payments and renewal processing.
- [x] **Razorpay Gateway Adapter** - Razorpay gateway adapter (`RazorpayAdapter`) bridges WCS Razorpay hooks to WPSubscription, handling renewal order creation (requires manual processing or webhook triggers as Razorpay API doesn't auto-retry).
- [x] **Mollie Gateway Adapter** - Mollie gateway adapter (`MollieAdapter`) bridges WCS Mollie hooks to WPSubscription, handling customer IDs, mandates, and SEPA/credit card sequences for renewal processing.
- [x] **Payoneer Gateway Adapter** - Payoneer gateway adapter (`PayoneerAdapter`) bridges WCS Payoneer hooks to WPSubscription, creating renewal orders with manual processing flags (Payoneer typically requires manual renewal API calls).
- [x] **Generic Gateway Adapter** - Generic gateway adapter (`GenericGatewayAdapter`) provides fallback support for any gateway without a specific adapter, automatically detecting and handling subscription-enabled gateways.
- [x] **Extended Hook Coverage** - Extended `HookRegistry` to mirror additional WCS hooks including status transitions (active, on-hold, cancelled, expired, pending, pending-cancel), lifecycle events (trial end, expiration, end of prepaid term), switching hooks, retry hooks, and reactivation/suspension hooks.
- [x] **REST API Controller** - REST API controller (`RestController`) provides WooCommerce Subscriptions-compatible endpoints (`/wc/v3/subscriptions`), supporting GET (list, single), POST (create), PUT (update), DELETE operations, status listing, and subscription orders retrieval with proper permission checks.

## Pending / Backlog

- [ ] **WP-CLI Commands** - Provide WP-CLI parity with WooCommerce Subscriptions commands for subscription management.
- [ ] **Admin UI Compatibility** - Reproduce admin UI list tables/metaboxes so extensions relying on WCS screens remain compatible.
- [ ] **Migration Tooling** - Add migration tooling for converting existing WooCommerce Subscriptions data sets to WPSubscription format.
- [ ] **Developer Documentation** - Document developer integration guide and release checklist once parity milestones are met.
- [ ] **CI/CD Workflows** - Establish CI workflows executing the compatibility PHPUnit suite and (eventually) end-to-end gateway tests.
