# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Install Dependencies

```bash
yarn install-dev   # installs both npm and composer deps
# or separately:
yarn install --ignore-engines
composer install --ignore-platform-req=php
```

### JavaScript Build

```bash
yarn build          # build JS (wp-scripts) + Tailwind CSS
yarn watch          # watch mode for both
yarn build:wpscripts
yarn watch:wpscripts
yarn build:tailwind
yarn watch:tailwind
```

### PHP Linting / Formatting

```bash
yarn lint:php       # runs phpcs
yarn format:php     # runs phpcbf (auto-fix)
```

### JS Linting / Formatting

```bash
yarn format         # wp-scripts format
yarn format:prettier
```

## Architecture

### Plugin Bootstrap

`subscription.php` defines the `Sdevs_Subscription` singleton (kept as-is for backwards compatibility with Pro). On `plugins_loaded` it calls `init_plugin()`, which loads three loader classes based on request context: `Admin`, `Frontend`, and `Illuminate`. The `init` hook then loads `Ajax`, `API`, and `Assets`.

`define_constants()` runs first and also requires `includes/LegacyCompat.php`, which provides backwards-compatible aliases for old constant and function names.

### Three-Layer Structure

- **`includes/Admin/`** — Settings pages, product configuration UI, subscription list/management, admin order details.
- **`includes/Frontend/`** — Product display, cart, checkout (including guest auto-login for new accounts only), My Account pages, action controller (pause/resume/cancel).
- **`includes/Illuminate/`** — Core business logic: subscription post type, cron jobs, email dispatch, auto-renewal, guest checkout, WooCommerce Blocks integration, payment gateways (`Gateways/Stripe/`, `Gateways/Paypal/`).

### Namespace & Autoloading

Namespace root: `SpringDevs\Subscription\` → PSR-4 mapped to `includes/`.
The main plugin file and its singleton (`Sdevs_Subscription`) are not namespaced — do not change these names; Pro depends on `class_exists('Sdevs_Subscription')` as a load guard.

### Constants

All plugin constants use the `SUBSCRPT_` prefix since v1.9.2:

| Constant             | Value                               |
| -------------------- | ----------------------------------- |
| `SUBSCRPT_VERSION`   | Plugin version string               |
| `SUBSCRPT_FILE`      | Absolute path to `subscription.php` |
| `SUBSCRPT_PATH`      | Plugin root directory               |
| `SUBSCRPT_INCLUDES`  | `SUBSCRPT_PATH/includes`            |
| `SUBSCRPT_TEMPLATES` | `SUBSCRPT_PATH/templates/`          |
| `SUBSCRPT_URL`       | Plugin root URL                     |
| `SUBSCRPT_ASSETS`    | `SUBSCRPT_URL/assets`               |

The old `WP_SUBSCRIPTION_*` names are still defined via `includes/LegacyCompat.php` for backwards compatibility but are deprecated. Always use `SUBSCRPT_*` in new code.

### Templates

- **`templates/myaccount/`** — Customer-facing subscription list and detail views.
- **`templates/emails/`** — Transactional email templates.
- **`includes/Admin/views/`** — Admin UI views (settings, product form, subscription list).

### Assets / JavaScript

- **`src/`** → built to **`build/`** via `@wordpress/scripts` (Webpack). `webpack.config.js` uses WooCommerce dependency extraction alongside the WordPress default.
- **Tailwind CSS** is scoped under the class `wpsubs-tw-root` to avoid global style leaks. Input: `assets/css/tailwind/input.css`, output: `assets/css/tailwind/output.css`. Tailwind scans `src/**`, `templates/**`, and `includes/**`.

### REST API

`includes/API.php` registers on `rest_api_init` and is the intended home for all future REST endpoints. It is currently a stub. New REST routes should be added here.

### WooCommerce Integration Points

- Custom `subscrpt_order` post type registered in `Illuminate/Post.php`.
- Payment gateways inside `Illuminate/Gateways/` extend WooCommerce gateway base classes. The Stripe class is wrapped in `class_exists('\WC_Stripe_Payment_Gateway')` — always guard gateway classes this way.
- Blocks (Cart/Checkout) integrated via `Illuminate/Block.php` and `Illuminate/Gateways/Paypal/Paypal_Blocks_Integration.php`.
- HPOS compatibility declared in `subscription.php`.

### Cron & Auto-Renewal

`Illuminate/Cron.php` schedules WordPress cron events for renewal reminders and expiration checks. `Illuminate/AutoRenewal.php` handles payment retry logic and fires grace-period hooks.

---

## Pro Plugin Integration

The Pro plugin (`subscription-pro/`) is a separate plugin that extends this one entirely through WordPress hooks. It **never** modifies free plugin files.

### Load guard

Pro checks `class_exists('Sdevs_Subscription')` before initialising. Do not rename that class.

### How Pro extends Free

Pro registers listeners on `subscrpt_*` hooks fired by the free plugin. Never remove or rename a `subscrpt_` hook without a deprecation plan — Pro and third-party integrations depend on them.

### Key hooks Pro listens to

**Subscription lifecycle (from `Illuminate/Action.php`)**
| Hook | Fired when |
|---|---|
| `subscrpt_subscription_activated` | Subscription becomes active |
| `subscrpt_subscription_cancelled` | Subscription cancelled |
| `subscrpt_subscription_expired` | Subscription expired |
| `subscrpt_subscription_pending` | Subscription set to pending |
| `subscrpt_subscription_resumed` | Subscription resumed from pause |
| `subscrpt_subscription_pending_cancellation` | Cancellation scheduled |
| `subscrpt_subscription_status_changed` | Any status transition `($id, $old, $new)` |

**Checkout & orders**
| Hook | Fired when |
|---|---|
| `subscrpt_product_checkout` | New subscription created at checkout `($order_item, $product, $status)` |
| `subscrpt_order_checkout` | Order linked to subscription `($subscription_id, $order_item)` |
| `subscrpt_after_create_renew_order` | Renewal order created `($new_order, $old_order, $subscription_id)` |
| `subscrpt_before_saving_renewal_order` | Filter — modify renewal order before save |

**Cron / grace period**
| Hook | Fired when |
|---|---|
| `subscrpt_grace_period_started` | Grace period begins |
| `subscrpt_grace_period_ended` | Grace period ends |
| `subscrpt_split_payment_completed` | All split-payment instalments received |

**Admin UI extension points**
| Hook | Purpose |
|---|---|
| `subscrpt_simple_pro_fields` | Inject extra fields into the product settings panel |
| `subscrpt_settings_fields` | Filter to add settings fields |
| `subscrpt_admin_info_rows` | Filter subscription detail rows in admin |
| `subscrpt_order_activities` | Action to append activity log entries |
| `subscrpt_render_stats_page` | Action to render the Reports page content (Pro replaces the upsell) |
| `wp_subscription_render_stats_page` | Legacy alias of the above — kept alive for Pro compatibility |

**Frontend extension points**
| Hook | Purpose |
|---|---|
| `subscrpt_single_action_buttons` | Filter action buttons on My Account subscription page |
| `subscrpt_execute_actions` | Action to handle custom subscription actions |
| `subscrpt_after_subscription_totals` | Action to append content after subscription totals |
| `subscrpt_simple_price_html` | Filter subscription price HTML on product page |
| `subscrpt_block_simple_cart_item_data` | Filter cart item data for block cart |
| `subscrpt_admin_header_menu_items` | Filter admin header nav items |

---

## Code Standards

### Naming — enforced prefix: `subscrpt_` / `SUBSCRPT_`

All new global PHP symbols must use `subscrpt_` (functions, hooks, post types, options) or `SUBSCRPT_` (constants). The `.cursorrules` file lists `WPS_`/`wps_` — that is outdated; ignore it. Class names inside the `SpringDevs\Subscription\` namespace do not need the prefix.

### CSS class naming

- **Legacy (existing):** `wp-subscription-` prefix — do not rename existing classes; templates depend on them.
- **New components:** use `subscrpt-` prefix going forward.
- **Next UI pass:** admin UI will migrate to WordPress core UI components (buttons, cards, notices from `@wordpress/components`). Do not add new custom-styled admin UI components that duplicate WP core patterns.

### Admin UI conventions (current, until next UI pass)

From the in-file style guide in `includes/Admin/views/required-notice.php`:

- Main content wrapper: `wp-subscription-admin-content`
- White card/box: `wp-subscription-admin-box` (white, box-shadow, 6–8 px border-radius)
- Header: `wp-subscription-admin-header` (sticky, `top: 32px`)
- Title font: Georgia, serif. Body: system sans-serif.
- Tailwind utilities are available inside any element with class `wpsubs-tw-root`.

### PHP Rules (enforced by PHPCS / `phpcs.xml`)

- WordPress Coding Standards, PHP 7.4+ target, minimum WP 5.0.
- Excluded rules: file naming, `PrefixAllGlobals`, Yoda conditions, short array syntax.
- All output must be escaped (`esc_html`, `esc_attr`, `wp_kses_post`). All input sanitized. AJAX handlers must verify nonces and `current_user_can()`.
- Use `wp_safe_redirect()` + `exit` for redirects — never `echo "<script>location.href=..."`.
- Gateway classes that extend external base classes (Stripe, etc.) must be wrapped in `class_exists()`.

### Documentation

PHPDoc on all classes, methods, global functions, `do_action`, and `apply_filters` calls. JSDoc on JS functions.

## Compatibility Requirements

- PHP >= 7.4
- WordPress >= 6.0
- WooCommerce >= 6.0
