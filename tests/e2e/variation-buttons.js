/**
 * End to end, in a browser: a variable product's variations as buttons.
 *
 *     NODE_PATH=docs/node_modules node subscription/tests/e2e/variation-buttons.js
 *
 * Needs pro active (free renders a variable product's purchase options only
 * then). Run from the bench root. It creates a variable product with three
 * sizes, the middle one out of stock, ties two plan terms to each variation,
 * and deletes it afterwards; the setting is put back as it was.
 *
 *   1. Buttons render, the select is kept and clipped, the sold-out size is
 *      disabled with its reason.
 *   2. A click sets the select, WooCommerce fires found_variation, and the plan
 *      cards swap.
 *   3. Arrow keys move through the group, a disabled button takes focus but
 *      is not chosen, and focus is visible.
 *   4. Clear resets the buttons.
 *   5. A select hidden by a theme is left alone.
 *   6. The setting off renders no buttons.
 *
 * Set PROOF_DIR to also save the two proof screenshots at 1440 and 390.
 */
const { chromium } = require("playwright");
const { execSync } = require("child_process");

const SITE = process.env.WPS_SITE || "https://wpsubscription.local";
const wp = (cmd) => execSync(`./wps cli ${cmd} 2>/dev/null`).toString().trim();
const php = (code) => wp(`eval '${code.replace(/'/g, `'\\''`)}'`);
const REPO = "SpringDevs\\Subscription\\Illuminate\\Plans\\PlanRepository";
const PROOF_DIR = process.env.PROOF_DIR || "";

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

/** A variable product with sizes Small, Medium (sold out) and Large, each tied to the plan terms. */
function createProduct(terms) {
  return JSON.parse(
    php(`
      $sizes = array( "Small", "Medium", "Large" );
      $attr = new WC_Product_Attribute();
      $attr->set_name( "Size" );
      $attr->set_options( $sizes );
      $attr->set_visible( true );
      $attr->set_variation( true );
      $product = new WC_Product_Variable();
      $product->set_name( "Variation buttons e2e" );
      $product->set_status( "publish" );
      $product->set_attributes( array( $attr ) );
      $pid = $product->save();
      foreach ( $sizes as $i => $size ) {
        $v = new WC_Product_Variation();
        $v->set_parent_id( $pid );
        $v->set_attributes( array( "size" => $size ) );
        $v->set_regular_price( (string) ( 10 + $i ) );
        $v->set_status( "publish" );
        if ( "Medium" === $size ) {
          $v->set_manage_stock( false );
          $v->set_stock_status( "outofstock" );
        }
        $vid = $v->save();
        foreach ( ${JSON.stringify(terms)} as $plan_id ) {
          ${REPO}::insert_relation( array( "plan_id" => $plan_id, "oid" => $pid, "vid" => $vid, "type" => ${REPO}::REL_PRODUCT, "status" => "active", "data" => array( "regular_price" => (string) ( 10 + $i ) ) ) );
        }
      }
      WC_Product_Variable::sync( $pid );
      wc_delete_product_transients( $pid );
      ${REPO}::flush_cache( $pid );
      echo wp_json_encode( array( "id" => $pid, "url" => get_permalink( $pid ) ) );
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

/** Save a proof screenshot at both widths. */
async function proof(page, name) {
  if (!PROOF_DIR) {
    return;
  }
  const form = page.locator("form.variations_form");
  for (const width of [1440, 390]) {
    await page.setViewportSize({ width, height: 900 });
    await page.waitForTimeout(250);
    await form.scrollIntoViewIfNeeded();
    await page.screenshot({ path: `${PROOF_DIR}/${name}-${width}.png` });
  }
  await page.setViewportSize({ width: 1440, height: 900 });
}

(async () => {
  const terms = planTerms();
  if (!Array.isArray(terms) || terms.length < 2) {
    console.log("No recurring plan group with two active terms on the bench.");
    process.exit(1);
  }
  const product = createProduct(terms);
  const original = php(`echo wp_json_encode( get_option( "subscrpt_variation_buttons", null ) );`);

  const browser = await chromium.launch();
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  const group = page.locator("[data-subscrpt-variation-buttons]");
  const select = page.locator('select[name="attribute_size"]');
  const button = (value) => group.locator(`[data-value="${value}"]`);

  try {
    await page.goto(product.url, { waitUntil: "domcontentloaded" });
    await page.waitForSelector("form.variations_form", { timeout: 15000 });

    // ── 1. Render ───────────────────────────────────────────────────────────
    console.log("1. Buttons render over a kept select");
    check((await group.count()) === 1 && (await group.isVisible()), "one visible button group");
    check((await group.getAttribute("role")) === "radiogroup", "it is a radio group");
    check((await group.locator('[role="radio"]').count()) === 3, "a button for each size");
    check((await select.count()) === 1, "WooCommerce's select is still in the page");
    check(
      await select.evaluate((el) => el.getBoundingClientRect().width <= 1 && el.getAttribute("aria-hidden") === "true"),
      "the select is visually hidden and out of the accessibility tree",
    );
    check((await group.getAttribute("aria-labelledby")) !== null, "the group is named by the attribute's label");
    check((await button("Medium").getAttribute("aria-disabled")) === "true", "the sold-out size is disabled");
    check((await button("Medium").getAttribute("title")) === "Out of stock", "with its reason as a title");
    check(
      (await button("Medium").innerText()).includes("Out of stock"),
      "and its reason is in the button, for touch and screen readers",
    );
    check((await button("Small").getAttribute("aria-disabled")) === null, "an available size is not disabled");
    check(
      (await group.locator('[role="radio"][tabindex="0"]').count()) === 1,
      "exactly one button is in the Tab order",
    );

    // ── 2. Click ────────────────────────────────────────────────────────────
    console.log("2. A click drives WooCommerce's select");
    await page.evaluate(() => {
      window.__found = 0;
      window.jQuery("form.variations_form").on("found_variation", () => window.__found++);
    });
    await button("Large").click();
    await page
      .waitForSelector('[data-subscrpt-buybox][data-subscrpt-context="variation"] [data-subscrpt-card]', {
        timeout: 5000,
      })
      .catch(() => null);
    check((await select.inputValue()) === "Large", "the select now holds the size");
    check((await button("Large").getAttribute("aria-checked")) === "true", "the button is checked");
    check((await page.evaluate(() => window.__found)) >= 1, "WooCommerce fired found_variation");
    check(
      (await page.locator('[data-subscrpt-buybox][data-subscrpt-context="variation"] [data-subscrpt-card]').count()) >
        0,
      "the plan cards swapped in",
    );
    check((await button("Small").getAttribute("aria-checked")) === "false", "the others are unchecked");

    // ── 3. Keyboard ─────────────────────────────────────────────────────────
    console.log("3. Arrow keys, and a disabled button's reason");
    await button("Large").focus();
    check(
      await button("Large").evaluate((el) => {
        const style = getComputedStyle(el);
        return style.outlineStyle !== "none" && parseFloat(style.outlineWidth) > 0;
      }),
      "focus is visible",
    );
    await page.keyboard.press("ArrowLeft");
    check(await button("Medium").evaluate((el) => el === document.activeElement), "an arrow reaches the disabled size");
    check((await select.inputValue()) === "Large", "but does not choose it");
    check((await button("Medium").getAttribute("aria-checked")) === "false", "it is not checked");
    await proof(page, "22-variation-buttons-out-of-stock");
    await page.keyboard.press("ArrowLeft");
    check((await select.inputValue()) === "Small", "the next arrow chooses the size beyond it");
    check(await button("Small").evaluate((el) => el === document.activeElement), "and moves focus there");
    await page.keyboard.press("End");
    check((await select.inputValue()) === "Large", "End goes to the last size");
    await page.keyboard.press("Home");
    check((await select.inputValue()) === "Small", "Home goes to the first");
    await page.keyboard.press("ArrowRight");
    await page.keyboard.press("ArrowRight");
    check((await select.inputValue()) === "Large", "right arrows wrap past the sold-out size");
    await proof(page, "22-variation-buttons");

    // ── 4. Clear ────────────────────────────────────────────────────────────
    console.log("4. Clear resets the buttons");
    await page.locator(".reset_variations").click();
    await page.waitForTimeout(300);
    check((await select.inputValue()) === "", "the select is back to no choice");
    check((await group.locator('[aria-checked="true"]').count()) === 0, "no button is checked");

    // ── 5. A theme that hides the select ───────────────────────────────────
    console.log("5. A select hidden by a theme is left alone");
    const themed = await context.newPage();
    await themed.addInitScript(() => {
      document.addEventListener("DOMContentLoaded", () => {
        const style = document.createElement("style");
        style.textContent = "table.variations select { display: none; }";
        document.head.appendChild(style);
      });
    });
    await themed.goto(product.url, { waitUntil: "load" });
    check((await themed.locator("[data-subscrpt-variation-buttons]").count()) === 0, "the button group is removed");
    check(
      await themed
        .locator('select[name="attribute_size"]')
        .evaluate((el) => !el.classList.contains("subscrpt-varbtns__select")),
      "and the select is not touched",
    );
    await themed.close();

    // ── 6. Setting off ──────────────────────────────────────────────────────
    console.log("6. With the setting off there are no buttons");
    php(`update_option( "subscrpt_variation_buttons", "" );`);
    await page.goto(product.url, { waitUntil: "load" });
    check((await page.locator("[data-subscrpt-variation-buttons]").count()) === 0, "no button group");
    check(await page.locator('select[name="attribute_size"]').isVisible(), "the dropdown shows as usual");
  } catch (e) {
    check(false, "run", e.message);
  } finally {
    await browser.close();
    php(
      original === "null"
        ? `delete_option( "subscrpt_variation_buttons" );`
        : `update_option( "subscrpt_variation_buttons", json_decode( '${original.replace(/'/g, "\\'")}' ) );`,
    );
    cleanup(product.id);
  }

  console.log(`\nPASS ${pass}   FAIL ${fail}`);
  process.exit(fail ? 1 : 0);
})();
