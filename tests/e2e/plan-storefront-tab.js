/**
 * End to end, in a browser: the "On the product page" tab of a plan group.
 *
 *     NODE_PATH=docs/node_modules node subscription/tests/e2e/plan-storefront-tab.js
 *
 * Run from the bench root. It creates a recurring plan group whose `data`
 * already holds another key, edits and saves the tab, reloads, and checks the
 * fields persisted and the other key survived. It deletes the group afterwards.
 */
const { chromium } = require("playwright");
const { execSync } = require("child_process");
const fs = require("fs");

const SITE = process.env.WPS_SITE || "https://wpsubscription.local";
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

const BENEFITS = ["Cancel anytime", "Free shipping", "Skip a month", "Pause whenever", "Gift it"];

(async () => {
  const id = parseInt(
    php(
      `echo ${REPO}::insert_group( array( "type" => 2, "product_type" => 1, "title" => "Storefront tab e2e", "status" => "active", "data" => array( "x" => "keep me" ) ) );`,
    ),
    10,
  );
  if (!id) {
    console.log("could not create the group");
    process.exit(1);
  }
  fs.mkdirSync(SHOT, { recursive: true });
  const browser = await chromium.launch();
  try {
    const url = `${SITE}/wp-admin/admin.php?page=wp-subscription-plans&view=detail&plan=${id}`;
    const open = async (page) => {
      await page.goto(url, { waitUntil: "domcontentloaded" });
      await page.click("#subscrpt-tab-storefront");
      await page.waitForSelector("[data-subscrpt-storefront]", { state: "visible" });
    };

    const ctx = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 1000 } });
    const page = await ctx.newPage();
    await page.goto(`${SITE}/wp-login.php`);
    await page.fill("#user_login", "admin");
    await page.fill("#user_pass", "password");
    await Promise.all([page.waitForNavigation(), page.click("#wp-submit")]);

    await open(page);
    check((await page.inputValue("[data-subscrpt-sf=tag]")) === "", "the tab starts empty");

    await page.fill("[data-subscrpt-sf=tag]", "Cancel anytime");
    await page.fill("[data-subscrpt-sf=benefits_heading]", "How it works");
    const lines = page.locator("[data-subscrpt-sf-benefit]");
    check((await lines.count()) === 5, "five benefit inputs");
    for (let i = 0; i < 5; i++) await lines.nth(i).fill(BENEFITS[i]);
    await page.fill("[data-subscrpt-sf=learn_label]", "Subscription details");
    await page.fill("[data-subscrpt-sf=learn_url]", "https://example.com/details");
    await page.fill("[data-subscrpt-sf=learn_panel]", "Pause or cancel from My Account.");

    const saved = page.waitForResponse((r) => r.url().includes("/plans/groups/") && r.request().method() === "PUT");
    await page.click("[data-subscrpt-sf-save]");
    check((await saved).ok(), "save answers 2xx");

    await open(page);
    check((await page.inputValue("[data-subscrpt-sf=tag]")) === "Cancel anytime", "tag persisted");
    check((await page.inputValue("[data-subscrpt-sf=benefits_heading]")) === "How it works", "heading persisted");
    const got = await lines.evaluateAll((els) => els.map((e) => e.value));
    check(JSON.stringify(got) === JSON.stringify(BENEFITS), "five benefits persisted in order", got.join("|"));
    check((await page.inputValue("[data-subscrpt-sf=learn_url]")) === "https://example.com/details", "link persisted");
    check((await page.inputValue("[data-subscrpt-sf=learn_label]")) === "Subscription details", "link label persisted");
    check(
      (await page.inputValue("[data-subscrpt-sf=learn_panel]")) === "Pause or cancel from My Account.",
      "panel text persisted",
    );

    const x = php(`$g = ${REPO}::get_group( ${id} ); echo $g["data"]["x"] ?? "MISSING";`);
    check(x === "keep me", "the group's other data key survived the save", x);

    await page.addStyleTag({
      content: "#wpadminbar,#toplevel_page_milo-subscriptions,#toplevel_page_sublium,.notice{display:none !important}",
    });
    await page.screenshot({ path: `${SHOT}/20-plan-storefront-tab-1440.png`, fullPage: true });
    await page.setViewportSize({ width: 390, height: 900 });
    await page.screenshot({ path: `${SHOT}/20-plan-storefront-tab-390.png`, fullPage: true });
  } finally {
    await browser.close();
    php(`${REPO}::delete_group( ${id} );`);
  }
  console.log(`PASS ${pass}   FAIL ${fail}`);
  process.exit(fail ? 1 : 0);
})();
