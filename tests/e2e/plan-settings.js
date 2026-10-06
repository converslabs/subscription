/**
 * End to end, in a browser: Settings > Product page, and a product's own layout.
 *
 *     NODE_PATH=docs/node_modules node subscription/tests/e2e/plan-settings.js
 *
 * Run from the bench root. It creates a recurring plan group of two terms and a
 * simple product that sells it, and deletes both afterwards; the store's options
 * are put back as they were.
 *
 *   1. The preview opens on the store's layout, with the intervals as chips.
 *   2. Changing the layout, the intervals, a colour and the radius changes the
 *      preview at once, before anything is saved; an invalid colour is ignored.
 *   3. After saving, the options are stored and the product page shows the new
 *      layout and colours, as CSS variables on `.subscrpt-buybox`.
 *   4. A product's own layout, chosen in its Subscription tab, wins over the
 *      store's on that product only; "Store default" removes it.
 *   5. With Pro: custom CSS saved in the same section reaches the page.
 *
 * Screenshots go to research/box-storefront/proof/25-*.
 */
const { chromium } = require("playwright");
const { execSync } = require("child_process");
const fs = require("fs");

const SITE = process.env.WPS_SITE || "https://wpsubscription.local";
const SHOT = process.env.WPS_SHOT_DIR || "research/box-storefront/proof";
const wp = (cmd) => execSync(`./wps cli ${cmd} 2>/dev/null`).toString().trim();
const php = (code) => wp(`eval '${code.replace(/'/g, `'\\''`)}'`);
const REPO = "SpringDevs\\Subscription\\Illuminate\\Plans\\PlanRepository";
const OPTIONS = [
  "subscrpt_plan_selector_layout",
  "subscrpt_plan_intervals_display",
  "subscrpt_plan_radius",
  "subscrpt_plan_color_accent",
  "subscrpt_plan_color_accent_ink",
  "subscrpt_plan_color_ink",
  "subscrpt_plan_color_border",
  "subscrpt_plan_color_badge",
  "subscrpt_plan_color_ribbon",
  "subscrpt_plan_custom_css",
];
const SETTINGS = `${SITE}/wp-admin/admin.php?page=wp-subscription-settings&cat=product_page`;

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

/** Every option this test touches, as JSON, so it can be put back. */
const readOptions = () =>
  JSON.parse(
    php(
      `$out = array(); foreach ( array(${OPTIONS.map((o) => `"${o}"`).join(",")}) as $o ) { $out[ $o ] = get_option( $o, null ); } echo wp_json_encode( $out );`,
    ),
  );

/** Put the options back: a value that was not there is deleted. */
const restoreOptions = (saved) =>
  php(
    Object.keys(saved)
      .map((o) =>
        saved[o] === null ? `delete_option( "${o}" );` : `update_option( "${o}", ${JSON.stringify(saved[o])} );`,
      )
      .join(" "),
  );

/** A recurring group of two monthly terms, and a product that sells it. */
function createFixture() {
  return JSON.parse(
    php(`
      $gid = ${REPO}::insert_group( array( "type" => 2, "product_type" => 1, "title" => "Settings e2e", "status" => "active", "data" => array() ) );
      $ids = array();
      foreach ( array( array( "Monthly", 1 ), array( "Every 2 months", 2 ) ) as $i => $t ) {
        $plan_id = ${REPO}::insert_plan( array( "plan_group_id" => $gid, "title" => $t[0], "type" => 2, "billing_frequency" => $t[1], "billing_interval" => 3, "status" => "active", "price_mode" => "snapshot" ) );
        $ids[] = $plan_id;
      }
      $make = function ( $name ) use ( $ids ) {
        $product = new WC_Product_Simple();
        $product->set_name( $name );
        $product->set_status( "publish" );
        $product->set_regular_price( "20" );
        $pid = $product->save();
        foreach ( $ids as $plan_id ) {
          ${REPO}::insert_relation( array( "plan_id" => $plan_id, "oid" => $pid, "vid" => 0, "type" => ${REPO}::REL_PRODUCT, "status" => "active", "data" => array( "regular_price" => "20" ) ) );
        }
        ${REPO}::flush_cache( $pid );
        return $pid;
      };
      $a = $make( "Plan settings e2e A" );
      $b = $make( "Plan settings e2e B" );
      echo wp_json_encode( array( "group" => $gid, "a" => $a, "b" => $b, "urlA" => get_permalink( $a ), "urlB" => get_permalink( $b ) ) );
    `),
  );
}

/** Delete the products, their relations and the group. */
function cleanup(fx) {
  php(`
    global $wpdb;
    foreach ( array( ${fx.a}, ${fx.b} ) as $pid ) {
      $wpdb->delete( ${REPO}::relation_table(), array( "oid" => $pid ) );
      ${REPO}::flush_cache( $pid );
      $product = wc_get_product( $pid );
      if ( $product ) { $product->delete( true ); }
    }
    ${REPO}::delete_group( ${fx.group} );
  `);
}

/** Choose a value in an adv-select by the name of its hidden input. */
async function choose(page, name, value) {
  const root = page.locator(`.wpsubs-adv-select:has(input[name="${name}"])`);
  await root.locator(".wpsubs-adv-select__trigger").click();
  await root.locator(`.wpsubs-adv-select__item[data-value="${value}"]`).click();
}

/** The block of the preview that shows, as `layout/intervals`. */
const shownPreview = (page) =>
  page.evaluate(() =>
    Array.from(document.querySelectorAll("[data-subscrpt-preview-layout]"))
      .filter((b) => !b.hidden)
      .map((b) => `${b.dataset.subscrptPreviewLayout}/${b.dataset.subscrptPreviewIntervals}`),
  );

/** A custom property on the shown buybox. */
const previewVar = (page, prop) =>
  page.evaluate((p) => {
    const box = Array.from(document.querySelectorAll("[data-subscrpt-preview-layout]"))
      .filter((b) => !b.hidden)[0]
      .querySelector(".subscrpt-buybox");
    return getComputedStyle(box).getPropertyValue(p).trim();
  }, prop);

/** A custom property on the storefront's buybox. */
const pageVar = (page, prop) =>
  page.evaluate((p) => getComputedStyle(document.querySelector(".subscrpt-buybox")).getPropertyValue(p).trim(), prop);

const HIDE =
  "#wpadminbar, #toplevel_page_milo-subscriptions, #toplevel_page_sublium, .notice { display: none !important; }";

async function login(browser, width) {
  const ctx = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width, height: 1000 } });
  const page = await ctx.newPage();
  await page.goto(`${SITE}/wp-login.php`);
  await page.fill("#user_login", "admin");
  await page.fill("#user_pass", "password");
  await Promise.all([page.waitForNavigation(), page.click("#wp-submit")]);
  return page;
}

(async () => {
  const saved = readOptions();
  const fx = createFixture();
  if (!fx || !fx.a) {
    console.log("could not create the fixture");
    process.exit(1);
  }
  fs.mkdirSync(SHOT, { recursive: true });
  restoreOptions(Object.fromEntries(OPTIONS.map((o) => [o, null])));
  const browser = await chromium.launch();

  try {
    const page = await login(browser, 1440);
    await page.goto(SETTINGS, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.addStyleTag({ content: HIDE });
    await page.waitForSelector("[data-subscrpt-preview]");

    console.log("the preview");
    check(JSON.stringify(await shownPreview(page)) === '["stacked/chips"]', "opens on stacked, with chips");
    check(
      (await page.locator("[data-subscrpt-preview-layout]").count()) === 14,
      "holds all seven layouts in both interval styles",
    );
    check((await page.locator("[data-subscrpt-preview] input[name]").count()) === 0, "posts nothing into the form");
    check(
      (await page.locator(".wpsubs-settings-heading__title", { hasText: "Product page" }).count()) === 1,
      "the group has one heading",
    );

    console.log("live changes, before saving");
    await choose(page, "subscrpt_plan_selector_layout", "grid");
    check(JSON.stringify(await shownPreview(page)) === '["grid/chips"]', "the layout changes the preview");
    await choose(page, "subscrpt_plan_intervals_display", "dropdown");
    check(JSON.stringify(await shownPreview(page)) === '["grid/dropdown"]', "so do the intervals");
    check(
      (await page
        .locator("[data-subscrpt-preview-layout='grid'][data-subscrpt-preview-intervals='dropdown'] select")
        .count()) >= 1,
      "as a select",
    );
    await choose(page, "subscrpt_plan_intervals_display", "chips");

    await page.fill("#subscrpt_plan_color_accent", "#d63638");
    check((await previewVar(page, "--subscrpt-accent")) === "#d63638", "an accent colour reaches the preview");
    await page.fill("#subscrpt_plan_color_badge", "#00a32a");
    check((await previewVar(page, "--subscrpt-badge")) === "#00a32a", "so does the badge colour");
    await page.fill("#subscrpt_plan_radius", "22");
    check((await previewVar(page, "--subscrpt-radius")) === "22px", "and the radius");
    await page.fill("#subscrpt_plan_color_ribbon", "red");
    check((await previewVar(page, "--subscrpt-ribbon")) === "", "a value that is not a colour is ignored");
    await page.fill("#subscrpt_plan_color_ribbon", "#8c1d94");
    check((await previewVar(page, "--subscrpt-ribbon")) === "#8c1d94", "until it is one");
    check((await page.locator(".subscrpt-color-picker").count()) === 6, "each colour has a picker");
    check(php('echo get_option( "subscrpt_plan_selector_layout", "none" );') === "none", "nothing is saved yet");

    const panel = page.locator("[data-subscrpt-panel='product_page']");
    await panel.screenshot({ path: `${SHOT}/25-settings-product-page-1440.png` });
    await page.setViewportSize({ width: 390, height: 900 });
    await panel.screenshot({ path: `${SHOT}/25-settings-product-page-390.png` });
    await page.setViewportSize({ width: 1440, height: 1000 });

    console.log("saved");
    await Promise.all([
      page.waitForNavigation({ waitUntil: "domcontentloaded" }),
      page.click(".subscrpt-settings__save"),
    ]);
    const stored = JSON.parse(
      php(
        'echo wp_json_encode( array( get_option( "subscrpt_plan_selector_layout" ), get_option( "subscrpt_plan_color_accent" ), get_option( "subscrpt_plan_radius" ), get_option( "subscrpt_plan_color_ribbon" ), get_option( "subscrpt_plan_intervals_display" ) ) );',
      ),
    );
    check(
      JSON.stringify(stored) === JSON.stringify(["grid", "#d63638", "22", "#8c1d94", "chips"]),
      "the options are stored",
      JSON.stringify(stored),
    );
    await page.addStyleTag({ content: HIDE });
    check(JSON.stringify(await shownPreview(page)) === '["grid/chips"]', "the preview reopens on the saved layout");
    check((await page.inputValue("#subscrpt_plan_color_accent")) === "#d63638", "and the field keeps its colour");

    console.log("the product page");
    const shop = await browser.newPage({ viewport: { width: 1440, height: 1000 }, ignoreHTTPSErrors: true });
    await shop.goto(fx.urlB, { waitUntil: "domcontentloaded", timeout: 90000 });
    await shop.waitForSelector("[data-subscrpt-buybox]");
    check(
      (await shop.getAttribute("[data-subscrpt-buybox]", "data-subscrpt-layout")) === "grid",
      "shows the saved layout",
    );
    check((await pageVar(shop, "--subscrpt-accent")) === "#d63638", "and the accent colour");
    check((await pageVar(shop, "--subscrpt-radius")) === "22px", "and the radius");
    check((await pageVar(shop, "--subscrpt-ribbon")) === "#8c1d94", "and the ribbon colour");
    const outline = await shop.evaluate(
      () =>
        getComputedStyle(
          document.querySelector(
            "[data-subscrpt-card].is-selected, .subscrpt-buybox__tile.is-selected, .subscrpt-buybox__card",
          ),
        ).borderColor,
    );
    check(outline === "rgb(214, 54, 56)", "the selected option is drawn in it", outline);
    await shop.close();

    console.log("a product's own layout");
    await page.goto(`${SITE}/wp-admin/post.php?post=${fx.a}&action=edit`, {
      waitUntil: "domcontentloaded",
      timeout: 90000,
    });
    await page.addStyleTag({ content: HIDE });
    await page.click("li.sdevs_subscription_tab a");
    await page.waitForSelector("[data-subscrpt-layout-choice]");
    const showing = (await page.textContent("[data-subscrpt-layout-showing]")).trim();
    check(showing === "Showing: Grid of tiles", "its tab says which layout the page shows", showing);
    await choose(page, "subscrpt_plan_layout", "accordion");
    await Promise.all([page.waitForNavigation({ waitUntil: "domcontentloaded" }), page.click("#publish")]);
    const meta = (id) => php(`echo get_post_meta( ${id}, "_subscrpt_plan_selector_layout", true );`);
    check(meta(fx.a) === "accordion", "the choice is saved on the product");
    check(meta(fx.b) === "", "and not on the other");

    const own = await browser.newPage({ viewport: { width: 1440, height: 1000 }, ignoreHTTPSErrors: true });
    await own.goto(fx.urlA, { waitUntil: "domcontentloaded", timeout: 90000 });
    await own.waitForSelector("[data-subscrpt-buybox]");
    check(
      (await own.getAttribute("[data-subscrpt-buybox]", "data-subscrpt-layout")) === "accordion",
      "that product shows its own layout",
    );
    check((await pageVar(own, "--subscrpt-accent")) === "#d63638", "in the store's colours");
    await own.screenshot({ path: `${SHOT}/25-product-override-1440.png` });
    await own.setViewportSize({ width: 390, height: 900 });
    await own.screenshot({ path: `${SHOT}/25-product-override-390.png` });
    await own.goto(fx.urlB, { waitUntil: "domcontentloaded", timeout: 90000 });
    check(
      (await own.getAttribute("[data-subscrpt-buybox]", "data-subscrpt-layout")) === "grid",
      "the other product keeps the store's",
    );
    await own.close();

    await page.goto(`${SITE}/wp-admin/post.php?post=${fx.a}&action=edit`, {
      waitUntil: "domcontentloaded",
      timeout: 90000,
    });
    await page.click("li.sdevs_subscription_tab a");
    await choose(page, "subscrpt_plan_layout", "");
    await Promise.all([page.waitForNavigation({ waitUntil: "domcontentloaded" }), page.click("#publish")]);
    check(meta(fx.a) === "", "Store default removes the override");

    console.log("custom CSS (Pro)");
    await page.goto(SETTINGS, { waitUntil: "domcontentloaded", timeout: 90000 });
    await page.addStyleTag({ content: HIDE });
    if ((await page.locator("#subscrpt_plan_custom_css").count()) === 1) {
      await page.fill(
        "#subscrpt_plan_custom_css",
        ".subscrpt-buybox{outline:3px solid rgb(1, 2, 3)}</style><script>window.__x=1</script>",
      );
      await Promise.all([
        page.waitForNavigation({ waitUntil: "domcontentloaded" }),
        page.click(".subscrpt-settings__save"),
      ]);
      const css = php('echo get_option( "subscrpt_plan_custom_css" );');
      check(!/<|<\/style/i.test(css) && css.includes("outline:3px"), "saved without the markup", css);
      const styled = await browser.newPage({ ignoreHTTPSErrors: true });
      await styled.goto(fx.urlB, { waitUntil: "domcontentloaded", timeout: 90000 });
      await styled.waitForSelector("[data-subscrpt-buybox]");
      const ol = await styled.evaluate(() => getComputedStyle(document.querySelector(".subscrpt-buybox")).outlineColor);
      check(ol === "rgb(1, 2, 3)", "and reaches the page, after the store's colours", ol);
      check((await styled.evaluate(() => window.__x)) === undefined, "and cannot run a script");
      await styled.close();
    } else {
      console.log("  - Pro is not active; skipped");
    }

    await page.close();
  } catch (e) {
    fail++;
    console.log(`  ✗ ${e.message}`);
  } finally {
    await browser.close();
    cleanup(fx);
    restoreOptions(saved);
  }

  console.log(`PASS ${pass}   FAIL ${fail}`);
  process.exit(fail ? 1 : 0);
})();
