# WP Subscription 1.9.2 — Developer Notes

**Release date:** 2026-03-29

This release is a compliance and security update driven by a WordPress.org plugin directory review. No features were added or removed. If you only use WP Subscription as a store owner you do not need to do anything — all changes are backwards-compatible. If you extend the plugin via code (custom code, a child plugin, or integrations), read the sections below.

---

## Why these changes were made

WordPress.org requires that every plugin use a unique prefix for all global PHP symbols (constants, functions, AJAX actions). Using reserved prefixes like `wp_` or generic names like `install_woocommerce_plugin` risks conflicts with WordPress core and other plugins. The reviewer flagged 30+ declarations in WP Subscription using the `WP_SUBSCRIPTION_`, `wp_subscrpt_`, and `wp_subs_` prefixes. This release resolves all of them.

---

## What changed

### 1. Plugin constants renamed

All plugin-level PHP constants have been renamed to use the `SUBSCRPT_` prefix.

| Old name (deprecated)       | New name             |
| --------------------------- | -------------------- |
| `WP_SUBSCRIPTION_VERSION`   | `SUBSCRPT_VERSION`   |
| `WP_SUBSCRIPTION_FILE`      | `SUBSCRPT_FILE`      |
| `WP_SUBSCRIPTION_PATH`      | `SUBSCRPT_PATH`      |
| `WP_SUBSCRIPTION_INCLUDES`  | `SUBSCRPT_INCLUDES`  |
| `WP_SUBSCRIPTION_TEMPLATES` | `SUBSCRPT_TEMPLATES` |
| `WP_SUBSCRIPTION_URL`       | `SUBSCRPT_URL`       |
| `WP_SUBSCRIPTION_ASSETS`    | `SUBSCRPT_ASSETS`    |

**The old names still work.** They are defined as aliases in `includes/LegacyCompat.php` immediately after the new constants are set, so existing code referencing `WP_SUBSCRIPTION_PATH` etc. will continue to work without modification. The aliases will be removed in a future major release.

### 2. Global functions renamed

Three global functions had a reserved `wp_` prefix and have been renamed:

| Old name (deprecated)           | New name                       |
| ------------------------------- | ------------------------------ |
| `wp_subscrpt_write_log()`       | `subscrpt_write_log()`         |
| `wp_subscrpt_write_debug_log()` | `subscrpt_write_debug_log()`   |
| `wp_subs_multiselect_field()`   | `subscrpt_multiselect_field()` |

One top-level function previously declared at global scope was renamed:

| Old name (deprecated)                     | New name                           |
| ----------------------------------------- | ---------------------------------- |
| `wp_subscription_register_paypal_block()` | `subscrpt_register_paypal_block()` |

**The old names still work** via wrapper functions in `includes/LegacyCompat.php`. They will be removed in a future major release.

### 3. AJAX actions renamed

Two AJAX actions had no plugin prefix, making them prone to conflicts:

| Old action (deprecated)               | New action                                     |
| ------------------------------------- | ---------------------------------------------- |
| `wp_ajax_install_woocommerce_plugin`  | `wp_ajax_subscrpt_install_woocommerce_plugin`  |
| `wp_ajax_activate_woocommerce_plugin` | `wp_ajax_subscrpt_activate_woocommerce_plugin` |

If you have custom JavaScript that calls either of these actions directly via `$.ajax({ action: '...' })`, update the action string. The old action names are **not** aliased — they were non-prefixed and therefore could not be safely kept active.

### 4. Stripe class guarded with `class_exists`

The `Stripe` gateway class (`includes/Illuminate/Gateways/Stripe/Stripe.php`) extends `\WC_Stripe_Payment_Gateway`, which is provided by the separate WooCommerce Stripe plugin. Previously the class declaration was unconditional, causing a PHP fatal error if WooCommerce Stripe was not installed. The class is now declared inside a `class_exists( '\WC_Stripe_Payment_Gateway' )` guard.

No API change — the class name and namespace are unchanged.

### 5. Guest checkout auto-login scoped to new accounts only

**Class:** `SpringDevs\Subscription\Frontend\Checkout`
**Method:** `handle_guest_checkout()` (internal)

Previously, the auto-login at checkout fired for any guest completing a subscription order — including existing users who were not logged in. This could silently log an existing customer back in, overriding their session choice. The auto-login now fires **only when a brand-new WordPress account is created** during checkout.

If you hooked into `wp_login` or `set_auth_cookie` to detect subscription checkouts, be aware it will no longer fire for returning customers going through guest checkout.

### 6. Redirects changed from JavaScript to `wp_safe_redirect()`

**Class:** `SpringDevs\Subscription\Frontend\ActionController`
**Methods:** `handle_action()`, `redirect()`

All subscription action redirects (renew, pause, cancel, etc.) previously used inline `<script>location.href='...'</script>` output. These have been replaced with `wp_safe_redirect()` + `exit`. The behaviour is identical from the user's perspective.

If you call `ActionController::redirect()` directly and expected HTML output, it now exits immediately after the redirect header. Make sure you are not buffering or processing its return value.

### 7. Security hardening (no API changes)

- **PayPal webhook handler:** `event_type`, `sale_id`, `billing_agreement_id`, and related fields from the webhook payload are now passed through `sanitize_text_field()` before use.
- **Settings field rendering:** `SettingsHelper` output methods now pass HTML through `wp_kses_post()` instead of printing raw.
- **Plugin name:** Renamed from "Subscription & Recurring Payment **Plugin** for WooCommerce" to "Subscription & Recurring Payment for WooCommerce" to comply with WordPress.org trademark guidelines (the word "Plugin" in the name is disallowed).

---

## Migration guide

### If you use the constants

No action needed now. To future-proof your code, do a find-and-replace:

```
WP_SUBSCRIPTION_VERSION   → SUBSCRPT_VERSION
WP_SUBSCRIPTION_FILE      → SUBSCRPT_FILE
WP_SUBSCRIPTION_PATH      → SUBSCRPT_PATH
WP_SUBSCRIPTION_INCLUDES  → SUBSCRPT_INCLUDES
WP_SUBSCRIPTION_TEMPLATES → SUBSCRPT_TEMPLATES
WP_SUBSCRIPTION_URL       → SUBSCRPT_URL
WP_SUBSCRIPTION_ASSETS    → SUBSCRPT_ASSETS
```

### If you call the renamed functions

No action needed now. To future-proof:

```
wp_subscrpt_write_log()       → subscrpt_write_log()
wp_subscrpt_write_debug_log() → subscrpt_write_debug_log()
wp_subs_multiselect_field()   → subscrpt_multiselect_field()
```

### If you call the AJAX actions from JavaScript

**Action required.** Update your AJAX calls:

```js
// Before
{
  action: "install_woocommerce_plugin";
}
{
  action: "wps_subscription_activate_woocommerce_plugin";
}

// After
{
  action: "subscrpt_install_woocommerce_plugin";
}
{
  action: "subscrpt_activate_woocommerce_plugin";
}
```

### If you extend `ActionController`

If you override the `redirect()` method, update your override to use `wp_safe_redirect()` + `exit` instead of printing a `<script>` tag.

---

## Compatibility

- WP Subscription Pro: fully compatible. No changes needed in the Pro plugin.
- PHP: 7.4+
- WordPress: 6.0+
- WooCommerce: 6.0+
