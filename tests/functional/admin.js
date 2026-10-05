// The orders screen as a store owner uses it. node admin.js hpos|legacy
// Filters by product, type and category, alone and combined, with status tabs;
// the Trash view; a product with no orders. On "hpos" also the Subscriptions
// screen, switching a filter off and on in Settings, and the review prompt.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const STORE = process.argv[2] || 'hpos';
const STATE = process.env.SOBP_STATE;
const ids = JSON.parse(fs.readFileSync(path.join(STATE, 'ids.json'), 'utf8'));
const php = (file, ...args) => execFileSync('wp', ['--path=' + process.env.WP_PATH, 'eval-file', path.join(__dirname, file), ...args], { encoding: 'utf8', env: process.env, stdio: ['ignore', 'pipe', 'ignore'] }).trim().split('\n').filter(l => !l.startsWith('Deprecated')).pop();
const record = (ok, name, detail) => { const line = `${ok ? 'PASS' : 'FAIL'}|${name}${ok ? '' : ' -- ' + detail}`; fs.appendFileSync(path.join(STATE, 'results'), line + '\n'); console.log('  ' + line); };
const A = ids.admin_url;
const LIST = STORE === 'hpos' ? `${A}admin.php?page=wc-orders` : `${A}edit.php?post_type=shop_order`;
const ST = STORE === 'hpos' ? 'status' : 'post_status';
const MINE = ['o1', 'o2', 'o3', 'o4', 'o5'].map(k => ids[k]);
const name = s => `${STORE}: ${s}`;

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 1200 } });
  await ctx.addCookies(ids.cookies);
  const page = await ctx.newPage();
  page.setDefaultTimeout(120000);
  const errors = [];
  const track = p => p.on('pageerror', e => errors.push(`${p.url()}: ${e && (e.stack || e.message) || JSON.stringify(e)}`));
  track(page);

  async function rows(query) {
    const resp = await page.goto(`${LIST}&${query}`, { waitUntil: 'domcontentloaded' });
    const html = await page.content();
    if (/Fatal error|Warning<\/b>|Notice<\/b>|critical error/i.test(html)) throw new Error('PHP error on the orders screen');
    const listed = await page.$$eval('#the-list tr[id]', trs => trs.map(t => parseInt(t.id.replace(/\D/g, ''), 10)));
    return { status: resp.status(), listed, mine: listed.filter(id => MINE.includes(id)).sort((a, b) => a - b) };
  }
  const want = keys => keys.map(k => ids[k]).sort((a, b) => a - b);
  async function mineOnly(label, query, keys) {
    try {
      const r = await rows(query);
      record(JSON.stringify(r.mine) === JSON.stringify(want(keys)), name(label), `test orders listed ${JSON.stringify(r.mine)}, want ${JSON.stringify(want(keys))}`);
    } catch (e) { record(false, name(label), e.message.split('\n')[0]); }
  }
  async function exact(label, query, keys) {
    try {
      const r = await rows(query);
      // Every row must be an expected test order: a filter on a test product
      // or category can only match test orders.
      const ok = JSON.stringify(r.listed.slice().sort((a, b) => a - b)) === JSON.stringify(want(keys));
      record(ok, name(label), `listed ${JSON.stringify(r.listed)}, want ${JSON.stringify(want(keys))}`);
    } catch (e) { record(false, name(label), e.message.split('\n')[0]); }
  }

  try {
    const r = await rows('');
    const dropdowns = [await page.locator('select#product_id').count(), await page.locator('select#dropdown_product_type').count(), await page.locator('select.dropdown_product_cat').count()];
    record(want(['o1', 'o2', 'o3', 'o4']).every(id => r.listed.includes(id)) && !r.listed.includes(ids.o5) && dropdowns.join() === '1,1,1',
      name('no filter: every order listed, product/type/category dropdowns shown'), `mine ${JSON.stringify(r.mine)}, dropdowns ${dropdowns}`);
  } catch (e) { record(false, name('no filter: orders screen loads'), e.message.split('\n')[0]); }

  await exact('product A', `product_id=${ids.A}`, ['o1', 'o3']);
  await exact('variable product V (parent) finds orders for its variations', `product_id=${ids.V}`, ['o2', 'o3']);
  // A product type is not unique to the test catalogue, so only the test orders are judged.
  await mineOnly('product type "variable"', 'search_product_type=variable', ['o2', 'o3']);
  await mineOnly('product type "simple"', 'search_product_type=simple', ['o1', 'o3', 'o4']);
  await exact('category B', `search_product_cat=${ids.cat_b}`, ['o2', 'o3']);
  await exact('category A', `search_product_cat=${ids.cat_a}`, ['o1', 'o3', 'o4']);
  await exact('product A + category B (both must match)', `product_id=${ids.A}&search_product_cat=${ids.cat_b}`, ['o3']);
  await exact('type "variable" + product A', `search_product_type=variable&product_id=${ids.A}`, ['o3']);
  await exact('Processing tab + product A', `${ST}=wc-processing&product_id=${ids.A}`, ['o1', 'o3']);
  await mineOnly('Completed tab + type "variable"', `${ST}=wc-completed&search_product_type=variable`, ['o2']);
  await exact('a product no order contains lists nothing', `product_id=${ids.C}`, []);
  try {
    const r = await rows(`${ST}=trash&product_id=${ids.A}`);
    record(r.listed.includes(ids.o5), name('Trash view still lists the trashed order'), `listed ${JSON.stringify(r.listed)}`);
  } catch (e) { record(false, name('Trash view loads'), e.message.split('\n')[0]); }

  if (STORE === 'hpos') {
    if (ids.wcs) {
      try {
        const resp = await page.goto(`${A}admin.php?page=wc-orders--shop_subscription&product_id=${ids.A}`, { waitUntil: 'domcontentloaded' });
        const html = await page.content();
        record(resp.status() === 200 && !/Fatal error|critical error/i.test(html) && (await page.locator('select#product_id').count()) === 0,
          'hpos: Subscriptions screen loads untouched, no product filter on it', `status ${resp.status()}`);
      } catch (e) { record(false, 'hpos: Subscriptions screen loads', e.message.split('\n')[0]); }
    }

    // Settings: switch the category filter off, then back on.
    try {
      const settings = `${A}admin.php?page=wc-search-orders-by-product-settings`;
      await page.goto(settings, { waitUntil: 'domcontentloaded' });
      const save = async () => {
        const resp = page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('save_sobp_plugin_data'));
        // A successful save reloads the page; wait for that reload to finish so
        // the next step does not race it.
        const reloaded = page.waitForNavigation({ waitUntil: 'load' });
        await page.locator('.wpheka-save-changes').click();
        const r = await resp;
        if (r.status() === 200) await reloaded;
        return String(r.status());
      };
      await page.waitForSelector('#search_orders_by_product_category');
      const before = await page.locator('#search_orders_by_product_category').isChecked();
      await page.locator('#search_orders_by_product_category').setChecked(false, { force: true });
      const checkedNow = await page.locator('#search_orders_by_product_category').isChecked();
      const reply = await save();
      const stored = php('settings.php');
      await page.goto(LIST, { waitUntil: 'domcontentloaded' });
      const off = await page.locator('select.dropdown_product_cat').count();
      const storedOff = JSON.parse(stored).search_orders_by_product_category;
      await page.goto(settings, { waitUntil: 'domcontentloaded' });
      await page.waitForSelector('#search_orders_by_product_category');
      await page.locator('#search_orders_by_product_category').setChecked(true, { force: true });
      await save();
      await page.goto(LIST, { waitUntil: 'domcontentloaded' });
      const on = await page.locator('select.dropdown_product_cat').count();
      record(reply.startsWith('200') && String(storedOff) === '0' && off === 0 && on === 1, 'hpos: Settings save, and switch the category filter off and on again', `save reply ${reply}, stored ${stored}, dropdown off ${off} on ${on}`);
    } catch (e) { record(false, 'hpos: Settings page saves', e.message.split('\n')[0]); }

    // Review prompt, on a fresh page so nothing from the Settings step is still loading.
    await page.close();
    const fresh = await ctx.newPage(); fresh.setDefaultTimeout(120000); track(fresh);
    const pageRef = fresh;
    php('usermeta.php', 'clear', 'sobp_review_snoozed_until'); php('usermeta.php', 'clear', 'sobp_review_dismissed');
    try {
      await pageRef.goto(`${A}index.php`, { waitUntil: 'load' });
      const shown = await pageRef.locator('#sobp-review-notice').count();
      const resp = pageRef.waitForResponse(r => r.url().includes('admin-ajax.php') && (r.request().postData() || '').includes('sobp_snooze_review'));
      await pageRef.locator('#sobp-review-notice .notice-dismiss').click();
      const r = await resp;
      await pageRef.goto(`${A}index.php`, { waitUntil: 'load' });
      const days = (parseInt(JSON.parse(php('usermeta.php', 'get', 'sobp_review_snoozed_until')), 10) - Date.now() / 1000) / 86400;
      record(shown === 1 && r.status() === 200 && (await pageRef.locator('#sobp-review-notice').count()) === 0 && days > 13.9 && days <= 14,
        'hpos: review prompt shows, and X keeps it closed for 14 days', `shown ${shown}, ajax ${r.status()}, snooze ${days.toFixed(2)}d`);
    } catch (e) { record(false, 'hpos: review prompt X', e.message.split('\n')[0]); }
    php('usermeta.php', 'clear', 'sobp_review_snoozed_until');
    try {
      await pageRef.goto(`${A}index.php`, { waitUntil: 'load' });
      await pageRef.locator('#sobp-review-notice a', { hasText: "Don't ask again" }).click();
      await pageRef.waitForLoadState('load');
      await pageRef.goto(`${A}index.php`, { waitUntil: 'load' });
      record((await pageRef.locator('#sobp-review-notice').count()) === 0 && JSON.parse(php('usermeta.php', 'get', 'sobp_review_dismissed')) == 1,
        'hpos: review prompt "Don\'t ask again" hides it for good', 'still shown');
    } catch (e) { record(false, 'hpos: review prompt Don\'t ask again', e.message.split('\n')[0]); }
  }
  const real = errors.filter(e => !/Transition was skipped/.test(e));
  record(real.length === 0, name('no JavaScript errors on these screens'), JSON.stringify(real));
  await browser.close();
})();
