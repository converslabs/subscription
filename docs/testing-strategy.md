++ wp-content/plugins/subscription/docs/testing-strategy.md
/\*\*

- Plugin Name - Testing Strategy
-
- @package WPSubscription
- @copyright Copyright (c) 2024, Your Company Name
- @license GPL-2.0+
- @since 1.0.0
  \*/

# TDD & Testing Strategy

## Objectives

- Drive development via failing tests that codify WooCommerce Subscriptions compatibility expectations.
- Cover API surface, data synchronization, gateway flows, and admin UX.
- Use WordPress automated testing standards for maintainability.

## Tooling

- PHPUnit 9.x with WordPress test suite (`WP_UnitTestCase`).
- Action Scheduler helpers for scheduling assertions.
- Optional: Codeception or Playwright for end-to-end admin coverage (future phase).

## Test Suites

| Suite     | Scope                                                       | Status                    |
| --------- | ----------------------------------------------------------- | ------------------------- |
| `compat`  | Core compatibility expectations (facades, functions, hooks) | Seeded with failing tests |
| `data`    | Synchronization invariants (dual-write, status mapping)     | TODO                      |
| `gateway` | Tokenization, scheduled payments, webhooks                  | TODO                      |
| `api`     | REST + CLI endpoints                                        | TODO                      |
| `ui`      | Admin list tables, metaboxes                                | TODO                      |

## Initial Failing Tests

- `tests/phpunit/test-compat-layer.php` asserts:
  - `WC_Subscription` class facade exists.
  - `wcs_get_users_subscriptions()` helper function exists.
  - `woocommerce_scheduled_subscription_payment_stripe` hook registered.
- Tests intentionally fail to highlight missing compatibility layer until implemented.

## Development Workflow

1. Run `composer install` (fills `vendor/` with PHPUnit and tools).
2. Bootstrap the WordPress test library (one-time) via `bash tests/bin/install-wp-tests.sh latest wordpress_test root '' localhost`.
3. Export `WP_TESTS_DIR` to the installed path (default `/tmp/wordpress-tests-lib`) or pass it inline when invoking PHPUnit.
4. Execute `phpunit` — observe failing tests.
5. Implement compatibility features to satisfy each failure.
6. Add new failing tests for uncovered API surface before writing code.
7. Maintain regression tests for bug fixes.

## Data Synchronization Tests (Planned)

- Unit tests for status mapping matrix.
- Integration tests verifying `shop_subscription` post creation upon WPSubscription lifecycle events.
- Drift detection tests using mocked data.

## Gateway Tests (Planned)

- Mock gateway adapters to ensure `supports()` flags propagate.
- Simulate webhook payloads and assert WPSubscription listeners triggered.
- Retry mechanism coverage with Action Scheduler.

## CI Considerations

- Provide GitHub Actions workflow leveraging `shivammathur/setup-php`, MySQL service, and WP test suite download.
- Cache `wordpress-tests-lib` between runs.
- Collect logs via `phpunit --log-junit`.

## Documentation

- Update README with instructions for configuring WP test suite.
- Provide sample `.env.testing` for gateway sandbox credentials (never commit secrets).
