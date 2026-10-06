/**
 * End to end, in a browser: the purchase option cards by keyboard and by mouse.
 *
 *     NODE_PATH=docs/node_modules node subscription/tests/e2e/plan-card-a11y.js
 *
 * Run from the bench root. It creates a simple product with two cards — a plan
 * group of two terms, the second saving 20%, and One-Time — and deletes it
 * afterwards.
 *
 *   1. Tab reaches the group radios; arrow keys move between the cards.
 *   2. Tab moves on to the terms; arrow keys move between them.
 *   3. Focus is visible on both.
 *   4. A click on the card's padding selects it; a click inside its body does not.
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

/** Two active terms of one recurring plan group. */
function planTerms() {
  return JSON.parse(
    php(
      `global $wpdb; echo wp_json_encode(array_map("intval", $wpdb->get_col("SELECT p.id FROM {$wpdb->prefix}subscrpt_plan p JOIN {$wpdb->prefix}subscrpt_plan_group g ON g.id = p.plan_group_id WHERE p.status = \\"active\\" AND g.status = \\"active\\" AND g.type = 2 AND p.plan_group_id = (SELECT p2.plan_group_id FROM {$wpdb->prefix}subscrpt_plan p2 JOIN {$wpdb->prefix}subscrpt_plan_group g2 ON g2.id = p2.plan_group_id WHERE p2.status = \\"active\\" AND g2.status = \\"active\\" AND g2.type = 2 GROUP BY p2.plan_group_id HAVING COUNT(*) >= 2 ORDER BY p2.plan_group_id LIMIT 1) ORDER BY p.id LIMIT 2")));`,
    ),
  );
}

/** A simple product tied to both terms, the second at 20% off, with One-Time on. */
function createProduct(terms) {
  return JSON.parse(
    php(`
      $product = new WC_Product_Simple();
      $product->set_name( "Plan card a11y e2e" );
      $product->set_status( "publish" );
      $product->set_regular_price( "20" );
      $pid = $product->save();
      update_post_meta( $pid, "_subscrpt_one_time_enabled", "yes" );
      foreach ( ${JSON.stringify(terms)} as $i => $plan_id ) {
        $data = array( "regular_price" => "20" );
        if ( 1 === $i ) { $data["discount_type"] = "percentage"; $data["discount_value"] = "20"; }
        ${REPO}::insert_relation( array( "plan_id" => $plan_id, "oid" => $pid, "vid" => 0, "type" => ${REPO}::REL_PRODUCT, "status" => "active", "data" => $data ) );
      }
      ${REPO}::flush_cache( $pid );
      echo wp_json_encode( array( "id" => $pid, "url" => get_permalink( $pid ) ) );
    `),
  );
}

/** Delete the product and its relations. */
function cleanup(pid) {
  php(`
    global $wpdb;
    $wpdb->delete( ${REPO}::relation_table(), array( "oid" => ${pid} ) );
    ${REPO}::flush_cache( ${pid} );
    $product = wc_get_product( ${pid} );
    if ( $product ) { $product->delete( true ); }
  `);
}

/** What has focus: its name, value, whether it is checked, and whether a ring shows. */
const focused = (page) =>
  page.evaluate(() => {
    const el = document.activeElement;
    // A term radio is hidden behind its pill, so the pill carries the ring.
    const ringOn = el && el.matches("[data-subscrpt-term]") ? document.querySelector(`label[for="${el.id}"]`) : el;
    const style = ringOn ? getComputedStyle(ringOn) : null;
    return {
      name: el ? el.getAttribute("name") : null,
      value: el ? el.value : null,
      checked: !!(el && el.checked),
      ring: !!style && style.outlineStyle !== "none" && parseFloat(style.outlineWidth) > 0,
    };
  });

const planId = (page) => page.locator("input[data-subscrpt-plan-id]").inputValue();
const selected = (page) =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll("[data-subscrpt-card]")).map((c) => c.classList.contains("is-selected")),
  );

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
    await page.waitForSelector("[data-subscrpt-buybox] [data-subscrpt-card]", { timeout: 15000 });
    check((await page.locator("[data-subscrpt-card]").count()) === 2, "two cards: the plan group and One-Time");

    // ── 1. The group radios ────────────────────────────────────────────────
    console.log("1. Tab reaches the group radios; arrows move between cards");
    await page.evaluate(() => {
      const start = document.createElement("button");
      start.type = "button";
      start.id = "e2e-start";
      start.textContent = "start";
      document.querySelector("[data-subscrpt-buybox]").before(start);
    });
    await page.focus("#e2e-start");
    await page.keyboard.press("Tab");
    let f = await focused(page);
    check(f.name === "subscrpt_plan_group" && f.checked, "Tab lands on the checked group radio", JSON.stringify(f));
    check(f.ring, "its focus ring shows");

    await page.keyboard.press("ArrowDown");
    f = await focused(page);
    check(f.name === "subscrpt_plan_group" && f.value === "one_time" && f.checked, "ArrowDown moves to One-Time");
    check(JSON.stringify(await selected(page)) === "[false,true]", "One-Time is the selected card");
    check((await planId(page)) === "", "One-Time posts no plan");

    await page.keyboard.press("ArrowUp");
    f = await focused(page);
    check(f.name === "subscrpt_plan_group" && f.value !== "one_time" && f.checked, "ArrowUp moves back");
    check((await planId(page)) === String(terms[0]), "the first term is posted again");

    // ── 2. The terms ───────────────────────────────────────────────────────
    console.log("2. Tab moves on to the terms; arrows move between them");
    await page.keyboard.press("Tab");
    f = await focused(page);
    check(
      /^subscrpt_plan_term\[/.test(f.name || "") && f.value === String(terms[0]) && f.checked,
      "Tab lands on the checked term",
      JSON.stringify(f),
    );
    check(f.ring, "its pill shows a focus ring");

    await page.keyboard.press("ArrowRight");
    f = await focused(page);
    check(f.value === String(terms[1]) && f.checked, "ArrowRight checks the second term");
    check((await planId(page)) === String(terms[1]), "the second term is posted");
    check(
      await page.evaluate(
        (id) =>
          document.querySelector(`[data-subscrpt-term-btn][data-term-id="${id}"]`).classList.contains("is-active"),
        terms[1],
      ),
      "its pill is marked active",
    );
    const name = await page.evaluate(
      (id) => document.querySelector(`[data-subscrpt-term-btn][data-term-id="${id}"]`).textContent,
      terms[1],
    );
    check(/Save 20%/.test(name), "its saving is in its name", name);
    check(
      (await page.locator("[data-subscrpt-badge]").first().textContent()).trim() === "Save 20%",
      "the badge shows its saving",
    );

    // ── 3. Mouse ───────────────────────────────────────────────────────────
    console.log("3. A click on the padding selects; a click in the body does not");
    const oneTime = page.locator("[data-subscrpt-card]").nth(1);
    const boxRect = await oneTime.boundingBox();
    await page.mouse.click(boxRect.x + 4, boxRect.y + boxRect.height - 4);
    check(JSON.stringify(await selected(page)) === "[false,true]", "clicking One-Time's padding selects it");
    check((await planId(page)) === "", "and posts no plan");
    check(
      await page.evaluate(() => {
        const terms = Array.from(document.querySelectorAll("[data-subscrpt-term]"));
        return terms.length === 2 && terms.every((t) => t.disabled);
      }),
      "the unselected card's terms are disabled, so never posted",
    );

    await page.evaluate(() => {
      const input = document.createElement("input");
      input.type = "number";
      input.id = "e2e-body-input";
      document.querySelector("[data-subscrpt-card] [data-subscrpt-card-body]").appendChild(input);
    });
    await page.click("#e2e-body-input");
    check(
      JSON.stringify(await selected(page)) === "[false,true]",
      "clicking inside the plan card's body leaves One-Time selected",
    );
    check(
      await page.evaluate(() => document.activeElement && document.activeElement.id === "e2e-body-input"),
      "the body's input takes the focus",
    );

    // Its terms are disabled until the card is selected, which Playwright reads
    // as "not clickable"; a shopper's click lands all the same.
    const chip = await page.locator(`[data-subscrpt-term-btn][data-term-id="${terms[0]}"]`).boundingBox();
    await page.mouse.click(chip.x + chip.width / 2, chip.y + chip.height / 2);
    check(
      JSON.stringify(await selected(page)) === "[true,false]",
      "clicking a term of the other card selects that card",
    );
    check((await planId(page)) === String(terms[0]), "and posts that term");
  } catch (e) {
    check(false, "run", e.message);
  } finally {
    await browser.close();
    cleanup(product.id);
  }

  console.log(`\nPASS ${pass}   FAIL ${fail}`);
  process.exit(fail ? 1 : 0);
})();
