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

## Pending / Backlog

- [ ] **Additional Gateway Adapters** - Build gateway adapter classes for Razorpay, Payoneer, and Mollie, wiring `wps_wcs_*` actions into existing WPSubscription payment listeners.
- [ ] **Extended Hook Coverage** - Mirror additional WCS hooks (e.g., switching, retries, expiration) and ensure internal handlers respond correctly.
- [ ] **REST API Parity** - Provide REST API and WP-CLI parity with WooCommerce Subscriptions endpoints/commands.
- [ ] **Admin UI Compatibility** - Reproduce admin UI list tables/metaboxes so extensions relying on WCS screens remain compatible.
- [ ] **Migration Tooling** - Add migration tooling for converting existing WooCommerce Subscriptions data sets to WPSubscription format.
- [ ] **Developer Documentation** - Document developer integration guide and release checklist once parity milestones are met.
- [ ] **CI/CD Workflows** - Establish CI workflows executing the compatibility PHPUnit suite and (eventually) end-to-end gateway tests.
