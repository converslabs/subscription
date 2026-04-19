## 1. Refactor Interval Conversion

- [x] 1.1 Extract the `$convert_interval` closure from `generate_plan_data()` into a new private method `convert_paypal_interval( string $interval ): string` in `Paypal.php`
- [x] 1.2 Replace the closure usage in `generate_plan_data()` with calls to `$this->convert_paypal_interval()`

## 2. Add Plan Fingerprint Method

- [x] 2.1 Add private method `generate_plan_fingerprint( WC_Product $wc_product ): string` to `Paypal.php` that collects critical fields (price, currency, interval unit, interval count, trial unit, trial count, signup fee, total_cycles) and returns `md5( wp_json_encode( $data ) )`

## 3. Refactor get_paypal_plan_id()

- [x] 3.1 Replace the single `plan_id` meta read with a `plans` array meta read using `get_meta_key( 'plans' )`
- [x] 3.2 Add fingerprint generation and loop-based lookup: iterate `$stored_plans`, return matching `plan_id` if `fingerprint` matches
- [x] 3.3 Replace the single `plan_id` meta write with appending `{plan_id, fingerprint}` to the `plans` array and saving with `update_post_meta`
- [x] 3.4 Remove the two TODO comment blocks (lines 856–857 and 872–873) now that the logic is implemented

## 4. Verification

- [ ] 4.1 Manually verify: create product, checkout via PayPal, change price, checkout again — confirm new plan created with correct price
- [ ] 4.2 Manually verify: revert price to original, checkout — confirm original plan reused (no new plan in PayPal)
- [x] 4.3 Run side-effect-check skill
