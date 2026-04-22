## 1. Replace deprecated calls — already done

- [x] 1.1 Replace `wp_subscrpt_write_log` calls in `includes/Illuminate/AutoRenewal.php` (2 calls)

## 2. Replace deprecated calls — remaining files

- [ ] 2.1 Replace all deprecated calls in `includes/Frontend/Checkout.php` (1x `wp_subscrpt_write_log`)
- [ ] 2.2 Replace all deprecated calls in `includes/Illuminate/Order.php` (1x `wp_subscrpt_write_log`, 1x `wp_subscrpt_write_debug_log`)
- [ ] 2.3 Replace all deprecated calls in `includes/Illuminate/Helper.php` (4x `wp_subscrpt_write_log`, 3x `wp_subscrpt_write_debug_log`)
- [ ] 2.4 Replace all deprecated calls in `includes/Illuminate/Gateways/Stripe/Stripe.php` (4x `wp_subscrpt_write_log`, 7x `wp_subscrpt_write_debug_log`)
- [ ] 2.5 Replace all deprecated calls in `includes/Illuminate/Gateways/Paypal/Paypal.php` (48x `wp_subscrpt_write_log`, 30x `wp_subscrpt_write_debug_log`)

## 3. Fix missing deprecation notice

- [ ] 3.1 Add `_deprecated_function( 'wp_subscrpt_write_log', '1.9.2', 'subscrpt_write_log' )` to the `wp_subscrpt_write_log` wrapper in `includes/LegacyCompat.php`

## 4. Verify

- [ ] 4.1 Confirm zero matches for `wp_subscrpt_write_log` and `wp_subscrpt_write_debug_log` outside `LegacyCompat.php`
- [ ] 4.2 Run side-effect-check skill
