++ wp-content/plugins/subscription/docs/compatibility-analysis.md
/\*\*

- Plugin Name - Compatibility Analysis
-
- @package WPSubscription
- @copyright Copyright (c) 2024, Your Company Name
- @license GPL-2.0+
- @since 1.0.0
  \*/

# WooCommerce Subscriptions Compatibility Analysis

## 1. Reference Stack

- WooCommerce core 8.x + Action Scheduler 3.x
- WooCommerce Subscriptions (WCS) 5.x
- WPSubscription 1.x (current)

## 2. Public PHP API Surface

### 2.1 Classes to Mirror

- `WC_Subscription`, `WCS_Cart`, `WCS_Checkout`, `WCS_Renewal_Order`, `WCS_Limiter`
- Data stores: `WC_Subscription_Data_Store`, `WC_Order_Data_Store_CPT`
- Payment handling: `WCS_Payment_Gateways`, `WC_REST_Subscriptions_Controller`
- Utilities: `WCS_Helper`, `WCS_Dynamic_Prices`

### 2.2 Functions to Provide

- `wcs_get_subscriptions()`, `wcs_get_subscription()`
- `wcs_create_subscription()`, `wcs_schedule_single_payment()`
- `wcs_get_users_subscriptions()`, `wcs_get_subscriptions_for_renewal_order()`
- `wcs_is_subscription()`, `wcs_is_subscription_product()`
- `wcs_cart_contains_renewal()`, `wcs_create_renewal_order()`

### 2.3 Key Hooks

- Creation: `woocommerce_checkout_subscription_created`, `woocommerce_subscription_status_changed`
- Payments: `woocommerce_scheduled_subscription_payment_{gateway}`, `woocommerce_subscription_renewal_payment_complete`
- Status transitions: `woocommerce_subscription_status_{status}`, `woocommerce_subscription_cancelled`
- Meta updates: `woocommerce_subscription_update_meta`, `woocommerce_subscription_trial_end`
- Filters: `wcs_renewal_order_meta_query`, `wcs_get_subscription`, `wcs_subscription_statuses`

## 3. REST API & CLI Interfaces

- REST: `/wc/v3/subscriptions`, `/wc/v1/subscriptions` legacy
- Endpoints require fields: status, billing, shipping, line items, payment schedule, dates
- WP-CLI: `wp wcs list`, `wp wcs update`, `wp wcs renew` commands
- Webhooks: `subscription.created`, `subscription.updated`, `subscription.deleted`, `subscription.renewed`, `subscription.completed`, `subscription.switched`

## 4. Data Model

### 4.1 Post Types & Statuses

- CPT `shop_subscription`
- Status taxonomy: `wc-pending`, `wc-active`, `wc-on-hold`, `wc-cancelled`, `wc-expired`, `wc-pending-cancel`, `wc-switched`, `wc-scheduled`
- Renewal orders: regular `shop_order` with meta `_subscription_renewal`

### 4.2 Meta Keys

- `_schedule_start`, `_schedule_trial_end`, `_schedule_end`, `_schedule_next_payment`
- `_billing_period`, `_billing_interval`, `_payment_method`, `_payment_method_title`
- `_customer_user`, `_order_version`, `_requires_manual_renewal`
- Related order meta: `_subscription_item_key`, `_subscription_renewal_page`

### 4.3 Action Scheduler Tables

- `wp_actionscheduler_actions` for renewal jobs
- Custom hooks `wcs_process_renewal_payment`, `wcs_retry_renewal_payment`

## 5. Email & Notification Touchpoints

- Email classes: `WCS_Email_Customer_Processing_Renewal_Order`, `WCS_Email_Customer_Completed_Renewal_Order`
- Hooks: `woocommerce_email_actions` integration, templates under `templates/emails/`

## 6. Admin UX Contracts

- Admin screens: `edit.php?post_type=shop_subscription`, post edit screen `post.php?post={id}&action=edit`
- List table filters/actions: `filter_shop_subscription`, `bulk_edit`, actions `trash`, `cancel`, `activate`
- Metabox IDs: `woocommerce-subscription-data`, `woocommerce-subscription-totals`, `woocommerce-subscription-notes`
- AJAX actions: `wcs_json_search_subscriptions`, `wcs_change_subscription_status`

## 7. Comparison with WPSubscription

| Area                | WooCommerce Subscriptions                     | WPSubscription Current         | Gap                                |
| ------------------- | --------------------------------------------- | ------------------------------ | ---------------------------------- |
| Data storage        | CPT `shop_subscription`, meta-driven schedule | Custom tables & listeners      | Requires dual-write or adapter     |
| Lifecycle hooks     | Extensive Woo hooks                           | Custom events, fewer Woo hooks | Add shim layer                     |
| Renewal scheduling  | Action Scheduler tasks                        | Internal cron/events           | Adopt Action Scheduler wrappers    |
| REST/CLI            | Official controllers/commands                 | Limited/no parity              | Implement APIs                     |
| Gateway integration | Payment gateway contracts, tokenization       | Custom gateway integrations    | Build adapters, match `supports()` |
| Admin UI            | Woo list tables/metaboxes                     | Custom UI                      | Provide compatibility screens      |

## 8. Risks & Considerations

- Dual data storage could drift; need transactional guarantees.
- Hook coverage must be exhaustive; missing a filter breaks integrations.
- Gateway retries rely on Action Scheduler + tokenization; ensure secure storage.
- Licensing/legal: mimic interfaces without shipping Woo code.
- Performance: additional abstraction must not degrade checkout times.

## 9. Next Steps

- Deep dive into WCS source to confirm hook signatures.
- Map WPSubscription internals to each required API surface.
- Prioritize compatibility by impact (data schema, hooks, gateways, UI).
