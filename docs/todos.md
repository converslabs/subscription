# Drop-in Migration TODOs

## Completed Work

- Baseline compatibility research recorded in `docs/compatibility-analysis.md`, covering WCS APIs, hooks, and data schema.
- Compatibility architecture planned in `docs/compat-architecture.md`, including directory layout and bootstrap flow.
- Data alignment strategy drafted in `docs/data-strategy.md`, defining dual-write approach and status mapping.
- Gateway adapter requirements documented in `docs/gateway-compat.md` for Stripe, Razorpay, Payoneer, and Mollie.
- TDD testing strategy defined in `docs/testing-strategy.md`, with PHPUnit scaffold and initial failing tests established.
- Composer dev dependencies updated with PHPUnit + Polyfills; WordPress test installer script added under `tests/bin/install-wp-tests.sh`.
- Compatibility bootstrap created in `includes/compat/woocommerce-subscriptions/`, with autoloader, facade, and procedural helpers loaded from `subscription.php`.
- `SubscriptionLocator` implemented to expose WCS-style subscription lookups, meta aliases, and status normalization.
- `WC_Subscription` facade extended with accessors to mirror WooCommerce behaviour.
- Hook registry (`HookRegistry`) introduced to bridge key WooCommerce gateway and lifecycle hooks to WPSubscription actions.
- PHPUnit suite expanded (`tests/phpunit/test-compat-layer.php`) to cover facade behaviour, filters, and hook bridges.
- Dual-write synchronisation implemented via `SyncService`, mirroring `subscrpt_order` posts to `shop_subscription` CPTs with schedule meta and reconciliation cron.
- Compatibility namespace refactored to PSR-4 structure under `includes/Compat/WooSubscriptions/` with Composer autoload integration.

## Pending / Backlog

- Map schedule data into Action Scheduler tasks, ensuring `_schedule_*` meta stays in sync with renewal events.
- Build gateway adapter classes (Stripe first), wiring `wps_wcs_*` actions into existing WPSubscription payment listeners.
- Mirror additional WCS hooks (e.g., switching, retries, expiration) and ensure internal handlers respond correctly.
- Provide REST API and WP-CLI parity with WooCommerce Subscriptions endpoints/commands.
- Reproduce admin UI list tables/metaboxes so extensions relying on WCS screens remain compatible.
- Add migration tooling for converting existing WooCommerce Subscriptions data sets to WPSubscription format.
- Document developer integration guide and release checklist once parity milestones are met.
- Establish CI workflows executing the compatibility PHPUnit suite and (eventually) end-to-end gateway tests.
