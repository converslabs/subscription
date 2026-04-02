---
name: ui
description: >
  Use this skill whenever making any UI change in this plugin: admin views, My Account templates,
  email templates, or JavaScript components. Covers CSS class conventions, template structure,
  WooCommerce email hooks, JS build setup, and when to use Tailwind vs legacy classes vs WP core components.
  Trigger for any work touching includes/Admin/views/, templates/, or src/.
---

# UI — subscription plugin

This plugin is backend-first but has four distinct UI surfaces, each with its own conventions.

---

## Admin views (`includes/Admin/views/`)

### CSS class conventions

| Use case           | Class                           | Notes                                     |
| ------------------ | ------------------------------- | ----------------------------------------- |
| Main page wrapper  | `wp-subscription-admin-content` | Outer container for all admin pages       |
| White card / box   | `wp-subscription-admin-box`     | White bg, box-shadow, 6–8px border-radius |
| Sticky page header | `wp-subscription-admin-header`  | `position: sticky; top: 32px`             |
| New components     | `subscrpt-` prefix              | e.g. `subscrpt-status-badge`              |

**Typography:** Georgia, serif for headings/titles. System sans-serif for body text.

**Never rename existing `wp-subscription-*` classes** — they may be targeted by themes or the Pro plugin.

### Styling approach — mix based on context

- **Existing views:** extend with `wp-subscription-admin-*` and `subscrpt-` classes
- **New self-contained components:** Tailwind utilities are available inside any element with `class="wpsubs-tw-root"` — use them for new UI work rather than writing custom CSS
- **Interactive/React components:** use `@wordpress/components` (the planned direction for the next admin UI pass — buttons, cards, notices from WP core rather than custom-styled equivalents)

### Structure template

```php
<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wp-subscription-admin-content">
    <div class="wp-subscription-admin-header">
        <h1><?php esc_html_e( 'Page Title', 'subscription' ); ?></h1>
    </div>
    <div class="wp-subscription-admin-box">
        <!-- content -->
    </div>
</div>
```

---

## My Account templates (`templates/myaccount/`)

### Files

| File                | Purpose                         |
| ------------------- | ------------------------------- |
| `subscriptions.php` | Subscription list table         |
| `single.php`        | Single subscription detail page |

### CSS classes

My Account templates sit inside WooCommerce's frontend, so use WooCommerce table classes alongside plugin-specific ones:

- WooCommerce table base: `woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive`
- Plugin table identifier: `my_account_subscrpt`
- Column classes: `subscrpt-id`, `subscrpt-next-date`, `subscrpt-total`, `subscrpt-action`

New frontend component classes use the `subscrpt-` prefix.

### Template override notice

Every template must include the override path in its docblock so theme developers know they can customise it:

```php
/**
 * Template name here.
 *
 * This template can be overridden by copying it to
 * <your_theme>/subscription/myaccount/<filename>.php
 *
 * @var WC_Order $order  Always declare variables passed from PHP.
 * @var string   $status
 */
```

### Variables

All variables are passed from PHP via `wc_get_template()`. Declare every variable in the docblock with its type. Never assume a variable exists — check with `isset()` where necessary.

---

## Email templates (`templates/emails/`)

### Files

| File                              | Purpose                          |
| --------------------------------- | -------------------------------- |
| `renew-reminder-html.php`         | Renewal reminder (HTML)          |
| `status-changed-admin-html.php`   | Status change admin notification |
| `subscription-cancelled-html.php` | Cancellation email               |
| `subscription-expired-html.php`   | Expiry email                     |
| `plains/`                         | Plain-text versions of the above |

### Structure

Email templates wrap content between WooCommerce's email header and footer hooks — never output a full `<html>` document:

```php
<?php do_action( 'woocommerce_email_header', $email_heading ); ?>

<!-- email body content here -->

<?php do_action( 'woocommerce_email_footer' ); ?>
```

For plain-text versions in `plains/`, output plain text only — no HTML tags.

### Variables

All variables are injected by the email class. Declare them at the top of the file:

```php
/**
 * Mail template for renewal reminder.
 *
 * @var string $email_heading  Email heading text.
 * @var int    $id             Subscription post ID.
 * @var string $product_name  Product name.
 * @var string $amount        Formatted price string.
 * @var string $next_date     Next payment date.
 */
```

---

## JavaScript (`src/`)

### Build setup

- Entry point: `src/index.js`
- CSS: `src/css/`
- Built to `build/` via `@wordpress/scripts` (Webpack)
- WooCommerce dependency extraction is active — `@woocommerce/*` packages are treated as externals

```bash
yarn build:wpscripts   # build once
yarn watch:wpscripts   # watch mode
```

### When to use JS

JavaScript is appropriate for:

- Block editor integrations (Cart/Checkout blocks)
- Interactive admin UI elements that need React (use `@wordpress/components`)
- Dynamic frontend behaviour that can't be handled server-side

For static admin pages, PHP views are preferred over JS-rendered components.

### `@wordpress/components`

For new interactive admin UI, prefer WP core components over custom markup:

```js
import { Button, Notice, Card, CardBody } from "@wordpress/components";
```

This aligns with the planned admin UI migration direction and ensures visual consistency with WordPress core.
