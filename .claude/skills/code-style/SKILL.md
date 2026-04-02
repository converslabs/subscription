---
name: code-style
description: >
  Use this skill whenever you are writing, reviewing, or formatting code in this WordPress subscription plugin.
  Covers PHP coding standards (WordPress CS + PHPCS rules), JavaScript/CSS formatting (Prettier + wp-scripts),
  naming conventions (subscrpt_ prefix, SUBSCRPT_ constants), security requirements, and PHPDoc rules.
  Trigger whenever the user asks about formatting, linting, code style, naming, or you are about to write new code.
---

# Code Style & Formatting — subscription plugin

## PHP

### Ruleset

Governed by `phpcs.xml`, which applies:

- **WordPress Coding Standards** (`WordPress` ruleset)
- **PHPCompatibilityWP** — target PHP 7.4+, minimum WP 5.0

### What's different from vanilla WordPress CS

These rules are **disabled** — do not apply them:

| Disabled rule                                      | What it means in practice                                                           |
| -------------------------------------------------- | ----------------------------------------------------------------------------------- |
| File naming (hyphenated-lowercase, class-filename) | Class files can keep PascalCase names                                               |
| `PrefixAllGlobals`                                 | PHPCS won't enforce the prefix, but **you still must follow it** (see Naming below) |
| Short array syntax                                 | Use `[]` — long `array()` syntax is not required                                    |
| Yoda conditions                                    | Write `$a === $b`, not `'value' === $a`                                             |
| `Squiz.PHP.CommentedOutCode`                       | Commented-out code is allowed                                                       |
| `Squiz.Commenting.FileComment.*`                   | File-level docblock is not required                                                 |

Everything else in the WordPress ruleset is still enforced.

### Naming conventions (enforced by convention, not PHPCS)

| Symbol type             | Prefix      | Example                                             |
| ----------------------- | ----------- | --------------------------------------------------- |
| Functions (global)      | `subscrpt_` | `subscrpt_get_status()`                             |
| Hooks (actions/filters) | `subscrpt_` | `do_action('subscrpt_subscription_activated', $id)` |
| Post types / options    | `subscrpt_` | `subscrpt_order`                                    |
| Constants               | `SUBSCRPT_` | `SUBSCRPT_VERSION`                                  |
| Namespaced classes      | none needed | `SpringDevs\Subscription\Admin\Settings`            |

The old `WP_SUBSCRIPTION_*` / `wps_` / `WPS_` names are deprecated legacy — never use them in new code.

### i18n

Text domain is `subscription`. Every user-facing string must use it:

```php
esc_html__( 'My string', 'subscription' )
```

### Security (non-negotiable)

- **Escape all output**: `esc_html()`, `esc_attr()`, `wp_kses_post()`, etc.
- **Sanitize all input**: `sanitize_text_field()`, `absint()`, etc.
- **AJAX handlers**: verify nonce AND `current_user_can()` before touching data.
- **Redirects**: use `wp_safe_redirect()` + `exit`. Never `echo "<script>location.href=..."`.
- **Gateway classes**: wrap in `class_exists()`:
  ```php
  if ( class_exists( '\WC_Stripe_Payment_Gateway' ) ) { ... }
  ```

### PHPDoc

Required on:

- All classes and interfaces
- All public and protected methods
- All global (non-namespaced) functions
- Every `do_action()` and `apply_filters()` call

Not required on file headers (rule is disabled).

### Linting / formatting commands

```bash
yarn lint:php      # check — runs phpcs
yarn format:php    # auto-fix — runs phpcbf
```

PHPCS scans all `.php` files and skips `assets/`, `build/`, `vendor/`, `node_modules/`.

---

## JavaScript / TypeScript / CSS

### Formatter

**Prettier**, configured in `.prettierrc`:

```json
{ "printWidth": 120 }
```

All other settings come from `@wordpress/prettier-config` (the wp-scripts default). The only project-level override is the wider print width of 120.

### What Prettier covers

`**/*.{js,ts,jsx,tsx,css,scss,json,md,html}`

### What Prettier ignores (`.prettierignore`)

`node_modules/`, `vendor/`, `dist/`, `build/`, `*.min.js`, `*.min.css`, `*.bundle.js`, `*.bundle.css`, `assets/css/tailwind/output.css`

### Formatting commands

```bash
yarn format              # wp-scripts format (preferred — respects WP defaults)
yarn format:prettier     # run prettier directly on all supported files
```

Husky + lint-staged runs prettier automatically on staged files at commit time — you don't need to run it manually before committing.

### JSDoc

Add JSDoc comments to all JS/TS functions.

---

## CSS / Tailwind

- Legacy admin styles use the `wp-subscription-` CSS class prefix — do not rename existing classes.
- New admin components use `subscrpt-` prefix.
- Tailwind utilities are available inside any element with `class="wpsubs-tw-root"`. Input: `assets/css/tailwind/input.css`, output (generated, do not edit): `assets/css/tailwind/output.css`.

```bash
yarn build:tailwind    # build once
yarn watch:tailwind    # watch mode
```

---

## Quick reference

| Task               | Command                |
| ------------------ | ---------------------- |
| Lint PHP           | `yarn lint:php`        |
| Fix PHP            | `yarn format:php`      |
| Format JS/CSS/JSON | `yarn format`          |
| Build JS           | `yarn build:wpscripts` |
| Build Tailwind     | `yarn build:tailwind`  |
| Build everything   | `yarn build`           |
