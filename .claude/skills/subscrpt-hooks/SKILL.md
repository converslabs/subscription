---
name: subscrpt-hooks
description: >
  Use this skill whenever you are adding, modifying, or removing any action or filter hook
  in this plugin, or whenever code touches subscription lifecycle events, checkout, renewals,
  cron jobs, admin UI extension points, or frontend action buttons.
  The Pro plugin (subscription-pro) depends entirely on these hooks — removing or renaming
  one silently breaks Pro integrations. Always check this reference before touching hooks.
---

# subscrpt\_\* Hook Reference

The Pro plugin extends the free plugin **exclusively through hooks** — it never modifies free plugin files. This means every `subscrpt_*` hook is a public contract. Removing or renaming a hook without a deprecation plan will silently break Pro and any third-party integrations.

**Rule:** If you need to change a hook's name or signature, keep the old hook firing for at least one major version and add a `_deprecated_hook()` notice.

---

## Subscription lifecycle

Fired by `includes/Illuminate/Action.php` when subscription status changes.

| Hook                                         | Parameters                      | Fired when                                                       |
| -------------------------------------------- | ------------------------------- | ---------------------------------------------------------------- |
| `subscrpt_subscription_status_changed`       | `$id, $old_status, $new_status` | Any status transition — fires alongside all specific hooks below |
| `subscrpt_subscription_activated`            | `$id`                           | Status → active                                                  |
| `subscrpt_subscription_pending`              | `$id`                           | Status → pending                                                 |
| `subscrpt_subscription_cancelled`            | `$id`                           | Status → cancelled                                               |
| `subscrpt_subscription_expired`              | `$id`                           | Status → expired                                                 |
| `subscrpt_subscription_resumed`              | `$id, $old_status`              | Resumed from pause                                               |
| `subscrpt_subscription_pending_cancellation` | `$id`                           | Cancellation scheduled                                           |

---

## Checkout & orders

| Hook                                   | Type   | Parameters                                           | Fired when                           |
| -------------------------------------- | ------ | ---------------------------------------------------- | ------------------------------------ |
| `subscrpt_product_checkout`            | action | `$order_item, $product, $status`                     | New subscription created at checkout |
| `subscrpt_order_checkout`              | action | `$subscription_id, $order_item`                      | Order linked to subscription         |
| `subscrpt_after_create_renew_order`    | action | `$new_order, $old_order, $subscription_id, false`    | Renewal order created                |
| `subscrpt_before_saving_renewal_order` | filter | `$new_order, $old_order, $subscription_id`           | Modify renewal order before save     |
| `subscrpt_order_status_changed`        | action | `$order, $history`                                   | Order status changes                 |
| `subscrpt_switch_order_created`        | action | `$switch_type, $order, $order_item, $switch_context` | Subscription switch order created    |

---

## Cron / auto-renewal / grace period

| Hook                                          | Type   | Parameters                       | Fired when                            |
| --------------------------------------------- | ------ | -------------------------------- | ------------------------------------- |
| `subscrpt_grace_period_started`               | action | `$id`                            | Grace period begins                   |
| `subscrpt_grace_period_ended`                 | action | `$id`                            | Grace period ends without payment     |
| `subscrpt_subscription_payment_completed`     | action | `$id, $payment_id`               | Payment received                      |
| `subscrpt_payment_failure_email_notification` | action | `$id`                            | Payment retry failed                  |
| `subscrpt_when_product_expired`               | action | `$id, true`                      | Product/subscription expired via cron |
| `subscrpt_split_payment_created`              | action | `$id, $args, $order_item`        | Split payment plan created            |
| `subscrpt_split_payment_renewed`              | action | `$id, $order_id, $order_item_id` | Split payment instalment paid         |
| `subscrpt_split_payment_cancelled`            | action | `$id`                            | Split payment plan cancelled          |

**Filters for renewal data:**

| Filter                                 | Parameters                     | Purpose                               |
| -------------------------------------- | ------------------------------ | ------------------------------------- |
| `subscrpt_before_saving_renewal_order` | `$new_order, $old_order, $id`  | Modify renewal order                  |
| `subscrpt_renewal_product_args`        | `$args, $product, $order_item` | Modify product added to renewal order |
| `subscrpt_renewal_item_meta`           | `$meta, $product, $order_item` | Modify item meta on renewal           |
| `subscrpt_split_payment_args`          | `$args, $order_item, $product` | Modify split payment configuration    |
| `subscrpt_split_payment_next_due_date` | `$date, $id, $timing, $type`   | Override next due date calculation    |

---

## Admin UI extension points

| Hook                               | Type   | Parameters                | Purpose                                       |
| ---------------------------------- | ------ | ------------------------- | --------------------------------------------- |
| `subscrpt_simple_pro_fields`       | action | `$post_id`                | Inject fields into product settings panel     |
| `subscrpt_settings_fields`         | filter | `$fields`                 | Add/modify plugin settings fields             |
| `subscrpt_register_settings`       | action | `'subscrpt_settings'`     | Register additional settings sections         |
| `subscrpt_admin_info_rows`         | filter | `$rows, $post_id, $order` | Add rows to subscription detail view          |
| `subscrpt_order_activities`        | action | `$post_id`                | Append entries to activity log                |
| `subscrpt_render_stats_page`       | action | _(none)_                  | Render Reports page (Pro replaces the upsell) |
| `subscrpt_admin_header_menu_items` | filter | `$items, $current`        | Add items to admin header nav                 |

---

## Frontend extension points

| Hook                                    | Type   | Parameters                         | Purpose                                   |
| --------------------------------------- | ------ | ---------------------------------- | ----------------------------------------- |
| `subscrpt_single_action_buttons`        | filter | `$buttons, $id, $nonce, $status`   | Add/remove action buttons on My Account   |
| `subscrpt_execute_actions`              | action | `$id, $action`                     | Handle custom subscription actions        |
| `subscrpt_after_subscription_totals`    | action | _(none)_                           | Append content after subscription totals  |
| `subscrpt_simple_price_html`            | filter | `$html, $product, $price, $trial`  | Override price HTML on product page       |
| `subscrpt_block_simple_cart_item_data`  | filter | `$data, $product, $cart_item_data` | Modify cart item data for block cart      |
| `subscrpt_renewal_actions`              | filter | `$actions`                         | Add/remove available renewal action names |
| `subscrpt_split_payment_button_text`    | filter | `$label, $action, $id, $status`    | Override split payment button labels      |
| `subscrpt_split_payment_disable_cancel` | filter | `false, $id, $status`              | Conditionally disable cancel button       |

---

## Utility filters

| Filter                                    | Parameters                | Purpose                                          |
| ----------------------------------------- | ------------------------- | ------------------------------------------------ |
| `subscrpt_format_price_with_subscription` | `$formatted, $price, $id` | Override formatted subscription price string     |
| `subscrpt_order_post_args`                | `$args`                   | Modify WP_Query args for subscription posts      |
| `subscrpt_order_item_post_args`           | `$args`                   | Modify WP_Query args for order item posts        |
| `subscrpt_simple_enable_checkbox_classes` | `'show_if_simple'`        | CSS classes for the subscription enable checkbox |

---

## Email notification actions

These fire alongside lifecycle hooks and trigger transactional emails:

- `subscrpt_subscription_cancelled_email_notification` `($id)`
- `subscrpt_subscription_expired_email_notification` `($id)`
- `subscrpt_payment_failure_email_notification` `($id)`
- `subscrpt_status_changed_admin_email_notification` `($id, $old_label, $new_label)`

---

## Adding new hooks

When adding a new hook:

1. Use the `subscrpt_` prefix
2. Add a PHPDoc block above the `do_action()` / `apply_filters()` call — document all parameters with `@param` and `@since`
3. Add it to this skill's reference so it stays discoverable
4. Never fire a hook inside a loop without a good reason — hooks are expensive when called hundreds of times

**Template:**

```php
/**
 * Fires after a subscription is paused.
 *
 * @since 2.0.0
 * @param int $subscription_id The subscription post ID.
 */
do_action( 'subscrpt_subscription_paused', $subscription_id );
```
