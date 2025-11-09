Feasibility Overview
A perfect drop-in replacement for woocommerce-subscriptions is theoretically possible but extremely ambitious. WooCommerce Subscriptions exposes a mature, extensively documented API surface, relies on WooCommerce core internals, and integrates deeply with dozens of gateway extensions. Matching that behavior would require reproducing every public hook, data structure, lifecycle event, and REST/CLI interface they rely on—plus backward-compatibility quirks accumulated over years. Expect a multi-phase project.
How It Could Work
Compatibility wrappers: Create a compatibility layer inside wp-content/plugins/subscription that mimics the classes, functions, and filters of WooCommerce Subscriptions (e.g., WC*Subscription, wcs_get_subscriptions_for_customer, key action/filter names). Under the hood, route into existing WPSubscription logic.
Data model alignment: Map or mirror WooCommerce’s subscription post types (shop_subscription), custom order meta, and schedule tables. Either maintain dual storage (WooCommerce format for compatibility + native format for your logic) or migrate fully to WooCommerce-style tables while keeping adapters.
Gateway integration: Implement the WooCommerce Subscriptions gateway API surface (renewal/automatic payments, token handling, retries). For each gateway plugin, supply adapter classes so their supports( 'subscriptions' ) checks pass and hooks like woocommerce_scheduled_subscription_payment*{gateway} map correctly to WPSubscription events.
Webhooks & APIs: Provide REST endpoints and webhook listeners that match WooCommerce Subscriptions’ behavior so external services expecting those routes keep working.
Admin UI parity: Mirror key admin screens (subscription list, edit UI, schedule changes) to satisfy integrations that rely on screen IDs, metaboxes, or AJAX actions.
Task Inventory
Gap Analysis
Catalogue WooCommerce Subscriptions public APIs/hooks (PHP, REST, JS, WP-CLI).
Audit WPSubscription’s current architecture against each touchpoint.
Compatibility Layer
Build shims for classes/functions.
Add hook aliases (actions/filters) that translate into WPSubscription equivalents.
Data Compatibility
Ensure usage of shop_subscription post type with expected meta keys.
Align order notes, statuses, schedule metadata, and renewal order creation flow.
Gateway Support
Implement supports flags, token storage, retries, webhook receivers.
Create adapters per gateway (Razorpay, Stripe, Payoneer, Mollie) to confirm compatibility.
API & Webhooks
Implement REST routes matching WooCommerce Subscriptions naming/signatures.
Provide webhook endpoints for payment state changes.
Admin UX
Reproduce screen IDs, metabox names, AJAX actions.
Ensure bulk actions, filters, and list-table columns match expectations.
Testing Infrastructure
Set up automated integration tests that run WooCommerce Subscriptions’ plugin tests (if available) or custom suites replicating their behavior.
Add backward compatibility unit/integration tests for crucial hooks and class methods.
Documentation & Migration
Provide a migration guide for existing WooCommerce Subscription data.
Document limitations or unsupported edge cases.
Drop-In Replacement Expectations
Installing your plugin while disabling WooCommerce Subscriptions should leave other extensions unaware of the swap. They should:
Resolve the same PHP classes/functions/constants.
Receive identical hooks/filters.
Access equivalent post types/meta fields.
Trigger the same payment scheduling and renewal flows.
Communicate with the same REST/webhook endpoints.
Ideally, the plugin slug/file structure mimics WooCommerce Subscriptions to satisfy hard-coded checks, though this can conflict with licensing; a compatibility wrapper is safer.
Requirements Summary
Exhaustive API coverage (PHP, REST, JS, CLI).
Sync with WooCommerce order/subscription lifecycle.
Full gateway token/renewal handling.
Matching database schema and statuses.
Extensive automated tests to guard regressions.
Comprehensive documentation and migration tools.
High-Level Plan
Research & Blueprint: document API diffs, dependency matrix, data model differences.
Core Compatibility Layer: build class/function shims, hook bridges, and data adapters.
Gateway Integration: implement payment gateway compatibility API; validate with target gateways.
Web/API Parity: REST routes, webhook handlers, CLI commands.
Admin UX & Misc: align screens, statuses, email templates.
Testing & QA: create failing tests, make them pass; run against sample gateways.
Migration & Release Prep: data migration scripts, docs, staged rollout.
Detailed Plan Highlights
Phase 0 (Research): Reverse-engineer WooCommerce Subscriptions; catalogue hooks, classes, REST routes; identify must-have vs optional features.
Phase 1 (Core compatibility):
Implement class facades (class-wc-subscription-compat.php, etc.).
Hook translation engine mapping WooCommerce hooks to WPSubscription internals.
Align data storage (post type, meta keys).
Phase 2 (Lifecycle parity):
Ensure creation/renewal/cancellation flows mirror WooCommerce (including manual renewals and failed payment retries).
Replicate status transitions and scheduled actions via Action Scheduler.
Phase 3 (Gateways):
Provide compatibility layer for WC_Payment_Gateway extension points.
Implement token management, webhook listeners for Stripe/Razorpay/Mollie/Payoneer.
Phase 4 (Interfaces):
REST routes & CLI commands matching WooCommerce Subscriptions signatures.
Admin screens/list tables/metaboxes duplication.
Phase 5 (Testing):
Build PHPUnit integration suite emulating WooCommerce Subscriptions tests.
Write acceptance tests covering gateway interactions.
Gradually convert failing tests to passing as features completed.
Phase 6 (Docs & Migration):
Scripts for converting existing WooCommerce subscriptions to WPSubscription.
Developer guide for SDK/compatibility.
Release plan with beta testing using selected gateways.
Testing Strategy
Yes, you can scaffold PHPUnit/Codeception tests that intentionally fail. Create a compatibility test suite referencing WooCommerce Subscriptions behaviors (e.g., using fixtures for subscription lifecycle). Each failing test represents a required compatibility feature. As you implement layers, update tests to pass, following WordPress testing conventions (use WP’s scaffolded test suites, WP_UnitTestCase, Action Scheduler factories).
Include gateway-specific integration tests by mocking their webhook payloads and renewal callbacks to ensure compatibility wrappers behave identically.
Next Steps
Begin with Phase 0 gap analysis: inventory WooCommerce Subscriptions APIs/hooks, compare to WPSubscription, prioritize blockers.
Set up a dedicated test environment with WooCommerce core + target gateways + your plugin.
Design the test harness (PHPUnit + optional Cypress/Playwright for admin UI).
