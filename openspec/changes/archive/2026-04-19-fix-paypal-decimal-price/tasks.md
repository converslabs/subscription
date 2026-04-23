## 1. Fix Price Formatting in generate_plan_data()

- [x] 1.1 Format recurring `$price` with `number_format( (float) $price, 2, '.', '' )` at line 1251 in `Paypal.php`
- [x] 1.2 Format `$signup_fee` with `number_format( (float) $signup_fee, 2, '.', '' )` at line 1267 in `Paypal.php`

## 2. Verification

- [x] 2.1 Manually verify: create PayPal subscription with a product priced at a single-decimal value (e.g., `$10.90`) — confirm PayPal plan is created without error and correct amount is shown in PayPal dashboard
- [x] 2.2 Run side-effect-check skill
