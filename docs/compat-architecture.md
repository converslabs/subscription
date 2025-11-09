++ wp-content/plugins/subscription/docs/compat-architecture.md
/\*\*

- Plugin Name - Compatibility Architecture
-
- @package WPSubscription
- @copyright Copyright (c) 2024, Your Company Name
- @license GPL-2.0+
- @since 1.0.0
  \*/

# WooCommerce Subscriptions Compatibility Architecture

## Directory Layout

```
includes/
└── compat/
    └── woocommerce-subscriptions/
        ├── bootstrap.php
        ├── autoload.php
        ├── contracts/
        │   ├── class-wps-wcs-subscription-interface.php
        │   ├── class-wps-wcs-payment-gateway-interface.php
        │   └── trait-wps-wcs-hookable.php
        ├── data/
        │   ├── class-wps-wcs-data-adapter.php
        │   ├── class-wps-wcs-schedule-mapper.php
        │   └── class-wps-wcs-meta-registry.php
        ├── lifecycle/
        │   ├── class-wps-wcs-lifecycle-manager.php
        │   ├── class-wps-wcs-status-transitioner.php
        │   └── class-wps-wcs-renewal-runner.php
        ├── hooks/
        │   ├── class-wps-wcs-hook-registry.php
        │   └── class-wps-wcs-hook-adapter.php
        ├── api/
        │   ├── class-wps-wcs-rest-controller.php
        │   ├── class-wps-wcs-webhook-handler.php
        │   └── class-wps-wcs-cli-commands.php
        ├── gateways/
        │   ├── class-wps-wcs-gateway-bridge.php
        │   ├── class-wps-wcs-token-store.php
        │   └── class-wps-wcs-gateway-factory.php
        ├── admin/
        │   ├── class-wps-wcs-admin-loader.php
        │   ├── class-wps-wcs-subscription-list-table.php
        │   └── views/
        │       └── *.php
        └── utils/
            ├── class-wps-wcs-logger.php
            ├── class-wps-wcs-event-dispatcher.php
            └── class-wps-wcs-compat-helpers.php
```

## Bootstrap Flow

1. `bootstrap.php` runs on plugin init, checking if WooCommerce is active and if the native WCS plugin is disabled.
2. Registers autoloader for the compatibility namespace: `WPSubscription\Compat\WooSubscriptions`.
3. Instantiates `Hook_Registry` to bind WCS hooks to WPSubscription listeners.
4. Loads data adapters to bridge WPSubscription models to WooCommerce-style subscriptions.
5. Exposes public facades (class/function shims) through `bootstrap.php` and `compat-helpers.php`.

## Namespacing & Files

- Namespace root: `WPSubscription\Compat\WooSubscriptions`.
- File naming: follow WordPress conventions, e.g., `class-wps-wcs-lifecycle-manager.php`.
- Provide `includes/compat/woocommerce-subscriptions/compat-functions.php` exporting procedural helpers matching WCS function names.

## Hook Strategy

- Maintain a registry mapping of WCS hook names to WPSubscription events.
- Use `Hook_Registry` to register action/filter callbacks during init.
- Provide hook aliasing for both dynamic and static hooks (e.g., `woocommerce_scheduled_subscription_payment_{gateway}`).

## Facade Classes

- `Subscription_Facade`: wraps WPSubscription subscription entity and implements core WCS methods.
- `Cart_Facade` & `Checkout_Facade`: intercept WooCommerce checkout phases to ensure compatibility.
- `Renewal_Manager`: orchestrates renewal orders using WPSubscription logic while presenting WCS API.

## Data Adapters

- `Data_Adapter` handles mapping between WPSubscription internal schema and WCS expectations.
- `Schedule_Mapper` converts internal schedule representation into `_schedule_*` meta values.
- `Meta_Registry` ensures meta keys remain synchronized for compatibility.

## Extensibility Considerations

- Provide filters before and after major translation steps, e.g., `wps_wcs_before_subscription_sync`.
- Use dependency injection where practical to ease testing.
- Keep adapters thin; business logic resides in existing WPSubscription services to avoid duplication.

## Initialization Hooks

- Hook `bootstrap.php` into `plugins_loaded` at priority `20` after WooCommerce.
- Provide guard to avoid collisions if official WooCommerce Subscriptions is active (log warning, disable compatibility mode).

## Testing Implications

- Each module should be testable in isolation via PHPUnit (mocks for WooCommerce classes).
- Integration tests will boot WordPress + WooCommerce + compatibility layer to validate hook registrations.
