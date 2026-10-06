/**
 * End to end, in a browser: the purchase options in each layout.
 *
 *     NODE_PATH=docs/node_modules node subscription/tests/e2e/plan-layouts.js
 *
 * Run from the bench root. It creates a recurring plan group of two terms (the
 * second 20% off) and a simple product that sells it with One-Time on, and
 * deletes both afterwards. For each layout, set as the product's override:
 *
 *   1. The buybox renders in that layout.
 *   2. Picking the second term posts its id; picking One-Time posts none.
 *   3. Only the selected option's body shows (layouts with no card).
 *   4. Add to cart sends the chosen `subscrpt_plan_id`.
 *
 * Then, in the stacked layout, the group's intervals set to a dropdown.
 * Screenshots go to research/box-storefront/proof/23-*.
 */
const { chromium } = require("playwright");
const { execSync } = require("child_process");
const fs = require("fs");

const SHOT = process.env.WPS_SHOT_DIR || "research/box-storefront/proof";
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

/** A recurring group of two monthly terms, and a product tied to both, the second 20% off. */
function createFixture() {
  return JSON.parse(
    php(`
      $gid = ${REPO}::insert_group( array( "type" => 2, "product_type" => 1, "title" => "Layouts e2e", "status" => "active", "data" => array() ) );
      $t1  = ${REPO}::insert_plan( array( "plan_group_id" => $gid, "title" => "Monthly", "type" => 2, "billing_frequency" => 1, "billing_interval" => 3, "status" => "active", "price_mode" => "snapshot" ) );
      $t2  = ${REPO}::insert_plan( array( "plan_group_id" => $gid, "title" => "Every 2 months", "type" => 2, "billing_frequency" => 2, "billing_interval" => 3, "status" => "active", "price_mode" => "snapshot" ) );
      $product = new WC_Product_Simple();
      $product->set_name( "Plan layouts e2e" );
      $product->set_status( "publish" );
      $product->set_regular_price( "20" );
      $pid = $product->save();
      update_post_meta( $pid, "_subscrpt_one_time_enabled", "yes" );
      foreach ( array( $t1, $t2 ) as $i => $plan_id ) {
        $data = array( "regular_price" => "20" );
        if ( 1 === $i ) { $data["discount_type"] = "percentage"; $data["discount_value"] = "20"; }
        ${REPO}::insert_relation( array( "plan_id" => $plan_id, "oid" => $pid, "vid" => 0, "type" => ${REPO}::REL_PRODUCT, "status" => "active", "data" => $data ) );
      }
      ${REPO}::flush_cache( $pid );
      echo wp_json_encode( array( "group" => $gid, "terms" => array( $t1, $t2 ), "id" => $pid, "url" => get_permalink( $pid ) ) );
    `),
  );
}

// A body listener for this product only: one text input per option's body.
const MU = "docker/mu-plugins/e2e-plan-layouts-body.php";
const MU_CODE = `<?php
add_action( "subscrpt_plan_card_body", function ( $group, $product ) {
	if ( $product && "Plan layouts e2e" === $product->get_name() ) {
		echo '<input type="text" name="e2e_body_' . esc_attr( $group["id"] ) . '" value="x" />';
	}
}, 10, 2 );
`;

/** Delete the product, its relations and the group. */
function cleanup(fx) {
  php(`
    global $wpdb;
    $wpdb->delete( ${REPO}::relation_table(), array( "oid" => ${fx.id} ) );
    ${REPO}::delete_group( ${fx.group} );
    ${REPO}::flush_cache( ${fx.id} );
    $product = wc_get_product( ${fx.id} );
    if ( $product ) { $product->delete( true ); }
  `);
}

/** The product's own layout. */
const setLayout = (fx, layout) =>
  php(`update_post_meta( ${fx.id}, "_subscrpt_plan_selector_layout", "${layout}" ); ${REPO}::flush_cache( ${fx.id} );`);

/** The group's storefront intervals. */
const setIntervals = (fx, intervals) =>
  php(
    `${REPO}::update_group( ${fx.group}, array( "data" => array( "storefront" => array( "intervals" => "${intervals}" ) ) ) ); ${REPO}::flush_cache( ${fx.id} );`,
  );

const planId = (page) => page.locator("input[data-subscrpt-plan-id]").inputValue();
const shownBodies = (page) =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll("[data-subscrpt-body-for]"))
      .filter((b) => !b.hidden)
      .map((b) => b.getAttribute("data-subscrpt-body-for")),
  );
const shownPrice = (page) =>
  page.evaluate(() => {
    const card = Array.from(document.querySelectorAll("[data-subscrpt-card].is-selected"))[0];
    const ins = card ? card.querySelector("[data-subscrpt-card-price]") : null;
    return ins ? ins.textContent.trim() : "";
  });

/** The fields an add-to-cart request carried, as name => value. */
async function postedFields(page) {
  const req = page.waitForRequest((r) => r.method() === "POST" && /add-to-cart/.test(r.postData() || ""), {
    timeout: 15000,
  });
  await page.click("button.single_add_to_cart_button");
  const body = (await req).postData() || "";
  const fields = {};
  const parts = body.matchAll(/name="([^"]+)"\r?\n\r?\n([^\r\n]*)/g);
  let multipart = false;
  for (const m of parts) {
    multipart = true;
    fields[m[1]] = m[2];
  }
  if (!multipart) {
    for (const [k, v] of new URLSearchParams(body)) fields[k] = v;
  }
  return fields;
}

/** The `subscrpt_plan_id` an add-to-cart request carried. */
async function postedPlanId(page) {
  const fields = await postedFields(page);
  return "subscrpt_plan_id" in fields ? fields.subscrpt_plan_id : null;
}

/** Body field names (`e2e_body_<group>`) that are enabled right now. */
const enabledBodyFields = (page) =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll('[data-subscrpt-buybox] input[name^="e2e_body_"]'))
      .filter((i) => !i.disabled)
      .map((i) => i.name),
  );

/** Screenshots of the buybox at both widths. */
async function shoot(browser, url, name, prepare) {
  for (const width of [1440, 390]) {
    const page = await browser.newPage({ viewport: { width, height: 1000 }, ignoreHTTPSErrors: true });
    await page.goto(url, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.waitForSelector("[data-subscrpt-buybox]");
    if (prepare) {
      await prepare(page);
    }
    await page.locator("form.cart").scrollIntoViewIfNeeded();
    // Let the chips' colour transition finish.
    await page.mouse.move(0, 0);
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${SHOT}/23-${name}-${width}.png` });
    await page.close();
  }
}

/** Pick the group's second term, the way the layout offers it. */
async function pickSecondTerm(page, fx, layout) {
  if (layout === "dropdown") {
    await page.selectOption('select[name="subscrpt_plan_group"]', `grp_${fx.group}`);
    await page.selectOption(`select[name="subscrpt_plan_term[grp_${fx.group}]"]`, String(fx.terms[1]));
  } else {
    await page.click(`label[for="subscrpt-grp-grp_${fx.group}"]`);
    await page.click(`label[data-term-id="${fx.terms[1]}"]`);
  }
}

/** Pick One-Time, the way the layout offers it. */
async function pickOneTime(page, layout) {
  if (layout === "dropdown") {
    await page.selectOption('select[name="subscrpt_plan_group"]', "one_time");
  } else {
    await page.click('label[for="subscrpt-grp-one_time"]');
  }
}

(async () => {
  const fx = createFixture();
  if (!fx || !fx.id) {
    console.log("could not create the fixture");
    process.exit(1);
  }
  fs.mkdirSync(SHOT, { recursive: true });
  fs.writeFileSync(MU, MU_CODE);
  const browser = await chromium.launch();

  try {
    for (const layout of ["stacked", "classic", "dropdown"]) {
      console.log(`${layout}`);
      setLayout(fx, layout);
      const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, ignoreHTTPSErrors: true });
      await page.goto(fx.url, { waitUntil: "domcontentloaded", timeout: 90000 });
      await page.waitForSelector("[data-subscrpt-buybox]", { timeout: 15000 });

      const box = page.locator("[data-subscrpt-buybox]");
      check((await box.getAttribute("data-subscrpt-layout")) === layout, `renders as ${layout}`);
      check((await planId(page)) === String(fx.terms[0]), "starts on the first term");
      if (layout !== "stacked") {
        check(
          JSON.stringify(await shownBodies(page)) === JSON.stringify([`grp_${fx.group}`]),
          "only the group's body shows",
        );
      }

      await pickSecondTerm(page, fx, layout);
      check((await planId(page)) === String(fx.terms[1]), "the second term is posted");
      check((await shownPrice(page)).includes("16"), "its price shows", await shownPrice(page));
      if (layout !== "dropdown") {
        const active = await page.evaluate(() =>
          Array.from(document.querySelectorAll(".is-selected [data-subscrpt-term-btn].is-active")).map((l) =>
            l.getAttribute("data-term-id"),
          ),
        );
        check(
          JSON.stringify(active) === JSON.stringify([String(fx.terms[1])]),
          "its chip is the active one",
          JSON.stringify(active),
        );
      }

      await pickOneTime(page, layout);
      check((await planId(page)) === "", "One-Time posts no plan");
      if (layout !== "stacked") {
        check(JSON.stringify(await shownBodies(page)) === '["one_time"]', "One-Time's body shows instead");
      }
      if (layout === "dropdown") {
        const shown = await page.evaluate(() =>
          Array.from(document.querySelectorAll("[data-subscrpt-card]"))
            .filter((c) => !c.hidden)
            .map((c) => c.getAttribute("data-subscrpt-group")),
        );
        check(JSON.stringify(shown) === '["one_time"]', "only One-Time's details show");
      }

      check(
        JSON.stringify(await enabledBodyFields(page)) === '["e2e_body_one_time"]',
        "only One-Time's body controls are enabled",
        JSON.stringify(await enabledBodyFields(page)),
      );

      await pickSecondTerm(page, fx, layout);
      check((await planId(page)) === String(fx.terms[1]), "back on the second term");
      const fields = await postedFields(page);
      check(
        fields.subscrpt_plan_id === String(fx.terms[1]),
        "add to cart sends the second term",
        fields.subscrpt_plan_id,
      );
      const bodies = Object.keys(fields).filter((k) => k.startsWith("e2e_body_"));
      check(
        JSON.stringify(bodies) === JSON.stringify([`e2e_body_grp_${fx.group}`]),
        "only the chosen option's body posts",
        JSON.stringify(bodies),
      );
      await page.close();

      await shoot(browser, fx.url, `layout-${layout}`, (p) => pickSecondTerm(p, fx, layout));
    }

    console.log("stacked, intervals as a dropdown");
    setLayout(fx, "stacked");
    setIntervals(fx, "dropdown");
    const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, ignoreHTTPSErrors: true });
    await page.goto(fx.url, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.waitForSelector("[data-subscrpt-buybox]", { timeout: 15000 });
    const select = page.locator(`select[data-subscrpt-term-select][name="subscrpt_plan_term[grp_${fx.group}]"]`);
    check((await select.count()) === 1, "the terms are a select");
    check((await page.locator(`input[name="subscrpt_plan_term[grp_${fx.group}]"]`).count()) === 0, "and no chips");
    await select.selectOption(String(fx.terms[1]));
    check((await planId(page)) === String(fx.terms[1]), "the chosen interval is posted");
    check((await shownPrice(page)).includes("16"), "its price shows");
    await pickOneTime(page, "stacked");
    check(await select.isDisabled(), "the select is disabled while One-Time is chosen");
    await page.click(`label[for="subscrpt-grp-grp_${fx.group}"]`);
    check((await planId(page)) === String(fx.terms[1]), "re-selecting the group keeps the interval");
    const posted = await postedPlanId(page);
    check(posted === String(fx.terms[1]), "add to cart sends it", String(posted));
    await page.close();

    await shoot(browser, fx.url, "intervals-dropdown", (p) =>
      p.selectOption(`select[name="subscrpt_plan_term[grp_${fx.group}]"]`, String(fx.terms[1])),
    );
  } catch (e) {
    fail++;
    console.log(`  ✗ ${e.message}`);
  } finally {
    await browser.close();
    fs.rmSync(MU, { force: true });
    cleanup(fx);
  }

  console.log(`PASS ${pass}   FAIL ${fail}`);
  process.exit(fail ? 1 : 0);
})();
