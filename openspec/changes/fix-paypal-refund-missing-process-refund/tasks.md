## 1. Implement process_refund

- [x] 1.1 Add `process_refund( $order_id, $amount = null, $reason = '' )` method to `includes/Illuminate/Gateways/Paypal/Paypal.php` — get capture ID from order, call PayPal v2 captures refund API, return `true` on 201 or `WP_Error` on failure

## 2. Verify

- [ ] 2.1 Manually test full refund on a completed PayPal subscription order
- [ ] 2.2 Manually test partial refund on a completed PayPal subscription order
- [ ] 2.3 Run side-effect-check skill
