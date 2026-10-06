/**
 * End to end, in a browser: a variable product's purchase options.
 *
 *     NODE_PATH=docs/node_modules node subscription/tests/e2e/variation-plans.js
 *
 * Needs pro active: free renders a variable product's purchase options only
 * then, since free's checkout cannot sell a variation's plan.
 *
 * Run from the bench root. It creates a variable product with 31 variations —
 * one past WooCommerce's threshold, so variations load over
 * `wc-ajax=get_variation`, off the product page — ties two plan terms to every
 * variation, and deletes all of it afterwards.
 *
 *   1. The page shows one selector, holding only the placeholder.
 *   2. Picking a variation swaps in its server-rendered cards.
 *   3. Resetting the choice clears them back to the placeholder.
 */
const { chromium } = require("playwright");
const { execSync } = require("child_process");

const SITE = process.env.WPS_SITE || "https://wpsubscription.local";
const wp = (cmd) => execSync(`./wps cli ${cmd} 2>/dev/null`).toString().trim();
const php = (code) => wp(`eval '${code.replace(/'/g, `'\\''`)}'`);
const REPO = "SpringDevs\\Subscription\\Illuminate\\Plans\\PlanRepository";

let pass = 0;
let fail = 0;
const check = (ok, label, detail = "") => {
  if (ok) {
    pass++;
    console.log(`  ✓ ${label}`);
  } else {
    fail++;
    console.log(`  ✗ ${label}${detail ? ` — ${detail}` : ""}`);
  }
};

/** Two active terms of one recurring plan group, to tie to every variation. */
function planTerms() {
  return JSON.parse(
    php(
      `global $wpdb; echo wp_json_encode(array_map("intval", $wpdb->get_col("SELECT p.id FROM {$wpdb->prefix}subscrpt_plan p JOIN {$wpdb->prefix}subscrpt_plan_group g ON g.id = p.plan_group_id WHERE p.status = \\"active\\" AND g.status = \\"active\\" AND g.type = 2 AND p.plan_group_id = (SELECT p2.plan_group_id FROM {$wpdb->prefix}subscrpt_plan p2 JOIN {$wpdb->prefix}subscrpt_plan_group g2 ON g2.id = p2.plan_group_id WHERE p2.status = \\"active\\" AND g2.status = \\"active\\" AND g2.type = 2 GROUP BY p2.plan_group_id HAVING COUNT(*) >= 2 ORDER BY p2.plan_group_id LIMIT 1) ORDER BY p.id LIMIT 2")));`,
    ),
  );
}

/** A variable product with 31 sizes, each tied to the given plan terms. */
function createProduct(terms) {
  return JSON.parse(
    php(`
      $sizes = array();
      for ( $i = 1; $i <= 31; $i++ ) { $sizes[] = "Size " . $i; }
      $attr = new WC_Product_Attribute();
      $attr->set_name( "Size" );
      $attr->set_options( $sizes );
      $attr->set_visible( true );
      $attr->set_variation( true );
      $product = new WC_Product_Variable();
      $product->set_name( "Variation plans e2e" );
      $product->set_status( "publish" );
      $product->set_attributes( array( $attr ) );
      $pid = $product->save();
      $vids = array();
      foreach ( $sizes as $i => $size ) {
        $v = new WC_Product_Variation();
        $v->set_parent_id( $pid );
        $v->set_attributes( array( "size" => $size ) );
        $v->set_regular_price( (string) ( 10 + $i ) );
        $v->set_status( "publish" );
        $vid = $v->save();
        $vids[] = $vid;
        foreach ( ${JSON.stringify(terms)} as $plan_id ) {
          ${REPO}::insert_relation( array( "plan_id" => $plan_id, "oid" => $pid, "vid" => $vid, "type" => ${REPO}::REL_PRODUCT, "status" => "active", "data" => array( "regular_price" => (string) ( 10 + $i ) ) ) );
        }
      }
      WC_Product_Variable::sync( $pid );
      wc_delete_product_transients( $pid );
      ${REPO}::flush_cache( $pid );
      echo wp_json_encode( array( "id" => $pid, "url" => get_permalink( $pid ), "vids" => $vids ) );
    `),
  );
}

/** Delete the product, its variations and every relation tied to them. */
function cleanup(pid) {
  php(`
    global $wpdb;
    $wpdb->delete( ${REPO}::relation_table(), array( "oid" => ${pid} ) );
    ${REPO}::flush_cache( ${pid} );
    $product = wc_get_product( ${pid} );
    if ( $product ) {
      foreach ( $product->get_children() as $vid ) { wp_delete_post( $vid, true ); }
      $product->delete( true );
    }
  `);
}

(async () => {
  const terms = planTerms();
  if (!Array.isArray(terms) || terms.length < 2) {
    console.log("No recurring plan group with two active terms on the bench.");
    process.exit(1);
  }
  const product = createProduct(terms);

  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });

  try {
    await page.goto(product.url, { waitUntil: "domcontentloaded" });
    await page.waitForSelector("form.variations_form", { timeout: 15000 });

    // ── 1. Placeholder ──────────────────────────────────────────────────────
    console.log("1. Before a choice: one selector, placeholder only");
    check((await page.locator("[data-subscrpt-buybox]").count()) === 1, "exactly one selector on the page");
    check(
      (await page.locator("[data-subscrpt-buybox] .subscrpt-buybox__placeholder").count()) === 1,
      "the placeholder shows",
    );
    check((await page.locator("[data-subscrpt-buybox] [data-subscrpt-card]").count()) === 0, "no cards yet");

    // ── 2. Pick a variation ─────────────────────────────────────────────────
    console.log("2. Picking a variation shows its cards");
    const fetched = page.waitForResponse((r) => r.url().includes("wc-ajax=get_variation"), { timeout: 15000 });
    await page.selectOption('select[name="attribute_size"]', "Size 3");
    const response = await fetched.catch(() => null);
    check(!!response, "the variation loads over wc-ajax=get_variation");
    const body = response ? await response.json().catch(() => ({})) : {};
    check(
      typeof body.subscrpt_plans_html === "string" &&
        body.subscrpt_plans_html.includes('data-subscrpt-context="variation"'),
      "its data carries the server-rendered cards",
    );

    await page
      .waitForSelector('[data-subscrpt-buybox][data-subscrpt-context="variation"] [data-subscrpt-card]', {
        timeout: 5000,
      })
      .catch(() => null);
    const box = page.locator("[data-subscrpt-buybox]");
    check((await box.count()) === 1, "still exactly one selector");
    check((await box.getAttribute("data-subscrpt-context")) === "variation", "the selector is the variation's");
    check(
      (await box.locator(`[data-subscrpt-term-btn][data-term-id="${terms[1]}"]`).count()) === 1,
      "both terms render as choices",
    );
    check(
      (await box
        .locator("input[data-subscrpt-plan-id]")
        .inputValue()
        .catch(() => "")) === String(terms[0]),
      "the first term is posted",
    );
    const paired = await box.evaluate((el) => {
      const radios = Array.from(el.querySelectorAll('[data-subscrpt-card] input[name="subscrpt_plan_group"]'));
      return (
        radios.length > 0 &&
        radios.every((radio) => radio.id && el.querySelectorAll(`label[for="${radio.id}"]`).length === 1)
      );
    });
    check(paired, "every card's label is paired with its radio");

    await box
      .locator(`[data-subscrpt-term-btn][data-term-id="${terms[1]}"]`)
      .click()
      .catch(() => null);
    check(
      (await box
        .locator("input[data-subscrpt-plan-id]")
        .inputValue()
        .catch(() => "")) === String(terms[1]),
      "choosing the second term posts it",
    );

    // ── 3. Reset ────────────────────────────────────────────────────────────
    console.log("3. Resetting clears the cards");
    await page.locator(".reset_variations").click();
    await page.waitForTimeout(500);
    check((await page.locator("[data-subscrpt-buybox] [data-subscrpt-card]").count()) === 0, "the cards are gone");
    check(
      (await page.locator("[data-subscrpt-buybox] .subscrpt-buybox__placeholder").count()) === 1,
      "the placeholder is back",
    );
  } catch (e) {
    check(false, "run", e.message);
  } finally {
    await browser.close();
    cleanup(product.id);
  }

  console.log(`\nPASS ${pass}   FAIL ${fail}`);
  process.exit(fail ? 1 : 0);
})();
