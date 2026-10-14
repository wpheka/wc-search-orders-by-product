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
const MINE = ['o1', 'o2', 'o3', 'o4', 'o5', 'o6'].map(k => ids[k]);
const name = s => `${STORE}: ${s}`;
// A crash must show as a failure, never as cases that silently did not run.
const crashed = e => { record(false, name('browser cases ran to the end'), (e && e.message || String(e)).split('\n')[0]); process.exit(1); };
process.on('unhandledRejection', crashed);
process.on('uncaughtException', crashed);

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
    const added = ['#search_sku', 'select#search_payment_method', 'select#search_shipping_method', 'select#search_billing_country'];
    const addedCounts = await Promise.all(added.map(sel => page.locator(sel).count()));
    record(want(['o1', 'o2', 'o3', 'o4', 'o6']).every(id => r.listed.includes(id)) && !r.listed.includes(ids.o5) && dropdowns.join() === '1,1,1' && addedCounts.join() === '1,1,1,1',
      name('no filter: every order listed, all seven filter controls shown'), `mine ${JSON.stringify(r.mine)}, dropdowns ${dropdowns}, new controls ${addedCounts}`);
  } catch (e) { record(false, name('no filter: orders screen loads'), e.message.split('\n')[0]); }

  // Purchased items column: o3 lists both of its products.
  try {
    await rows('');
    const rowId = STORE === 'hpos' ? `#order-${ids.o3}` : `#post-${ids.o3}`;
    const cell = (await page.locator(`${rowId} td.column-sobp_items`).innerText()).replace(/\s+/g, ' ');
    record(/1 × ZZ SOBP Product A/.test(cell) && /1 × ZZ SOBP Variable V/.test(cell), name('purchased items column lists the order\'s items'), `cell "${cell}"`);
  } catch (e) { record(false, name('purchased items column'), e.message.split('\n')[0]); }

  await exact('product A', `product_id=${ids.A}`, ['o1', 'o3']);
  await exact('variable product V (parent) finds orders for its variations', `product_id=${ids.V}`, ['o2', 'o3']);
  // A product type is not unique to the test catalogue, so only the test orders are judged.
  await mineOnly('product type "variable"', 'search_product_type=variable', ['o2', 'o3']);
  await mineOnly('product type "simple"', 'search_product_type=simple', ['o1', 'o3', 'o4', 'o6']);
  await exact('category B includes its sub-category', `search_product_cat=${ids.cat_b}`, ['o2', 'o3', 'o6']);
  await exact('sub-category alone', `search_product_cat=${ids.cat_bc}`, ['o6']);
  await exact('category A', `search_product_cat=${ids.cat_a}`, ['o1', 'o3', 'o4']);
  await exact('product A + category B (both must match)', `product_id=${ids.A}&search_product_cat=${ids.cat_b}`, ['o3']);
  await exact('type "variable" + product A', `search_product_type=variable&product_id=${ids.A}`, ['o3']);
  await exact('Processing tab + product A', `${ST}=wc-processing&product_id=${ids.A}`, ['o1', 'o3']);
  await mineOnly('Completed tab + type "variable"', `${ST}=wc-completed&search_product_type=variable`, ['o2']);
  await exact('a product no order contains lists nothing', `product_id=${ids.C}`, []);
  await exact('SKU of product A', 'search_sku=ZZSOBP-A', ['o1', 'o3']);
  await exact('SKU prefix of the variations', 'search_sku=ZZSOBP-V', ['o2', 'o3']);
  await exact('SKU of one variation', 'search_sku=ZZSOBP-V-S', ['o2']);
  await exact('an unknown SKU lists nothing', 'search_sku=ZZSOBP-NOPE', []);
  // Payment, country and shipping are not unique to the test orders.
  await mineOnly('payment method "cod"', 'search_payment_method=cod', ['o1', 'o3']);
  await mineOnly('billing country GB', 'search_billing_country=GB', ['o4']);
  await mineOnly('billing country CA', 'search_billing_country=CA', ['o2', 'o6']);
  await mineOnly('shipping method flat_rate', 'search_shipping_method=flat_rate', ['o1']);
  await mineOnly('shipping method free_shipping', 'search_shipping_method=free_shipping', ['o3']);
  await exact('product A + payment "cod"', `product_id=${ids.A}&search_payment_method=cod`, ['o1', 'o3']);
  await exact('category A + billing country US', `search_product_cat=${ids.cat_a}&search_billing_country=US`, ['o1', 'o3']);
  await exact('Processing tab + SKU prefix of the variations', `${ST}=wc-processing&search_sku=ZZSOBP-V`, ['o3']);
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

    // The review counter now increases on HPOS (setup reset it to 0).
    try {
      const count = parseInt(JSON.parse(php('option.php', 'sobp_filtered_search_count')), 10);
      record(count >= 3, 'hpos: filtering on the HPOS screen counts towards the review prompt', `counter ${count}`);
    } catch (e) { record(false, 'hpos: review counter', e.message.split('\n')[0]); }

    // Settings page (WPHEKA > Search Orders, 4.1.0). Fields are found by name: the kit gives
    // controls generated ids. Saving is a plain form post that redirects back with w-saved=1.
    const SETTINGS = `${A}admin.php?page=wc-search-orders-by-product-settings`;
    const sw = key => `input[type="checkbox"][name="sobp_settings[${key}]"]`;
    const openSettings = async p => {
      const resp = await p.goto(SETTINGS, { waitUntil: 'domcontentloaded' });
      await p.waitForSelector('form#sobp-settings');
      return resp;
    };
    // Returns the URL the save redirected to: the page's script removes w-saved=1 from the
    // address bar once the toast shows, so p.url() after load no longer has it.
    const saveSettings = async p => {
      const [resp] = await Promise.all([p.waitForNavigation({ waitUntil: 'load' }), p.evaluate(() => document.getElementById('sobp-settings').requestSubmit())]);
      return resp ? resp.url() : p.url();
    };
    const stored = () => JSON.parse(php('settings.php'));

    // The old WooCommerce > Settings > Advanced section is now only a link to the page.
    try {
      const resp = await page.goto(`${A}admin.php?page=wc-settings&tab=advanced&section=wc_search_orders_by_product`, { waitUntil: 'domcontentloaded' });
      const link = await page.locator('a.button-primary[href*="page=wc-search-orders-by-product-settings"]').count();
      const fields = await page.locator('[name^="sobp_settings"]').count();
      const text = await page.locator('#mainform').innerText();
      record(resp.status() === 200 && link === 1 && fields === 0 && /settings have moved/.test(text), 'hpos: the old WooCommerce settings section links to the new page', `status ${resp.status()}, link ${link}, fields ${fields}`);
    } catch (e) { record(false, 'hpos: old WooCommerce settings section', e.message.split('\n')[0]); }

    // The page shows every field: Product (always on), the six filter switches, the
    // category picker, the items column and the fast lookup row.
    try {
      const resp = await openSettings(page);
      const keys = ['search_orders_by_product_type', 'search_orders_by_product_category', 'search_orders_by_sku', 'search_orders_by_payment_method', 'search_orders_by_shipping_method', 'search_orders_by_billing_country', 'purchased_items_column'];
      const counts = [];
      for (const k of keys) counts.push(await page.locator(sw(k)).count());
      const always = await page.locator('#filters .w-row', { hasText: 'Always on' }).count();
      const cats = await page.locator('input[name="sobp_settings[category_include][]"][type="checkbox"]').count();
      const catA = await page.locator(`input[name="sobp_settings[category_include][]"][value="${ids.cat_a}"]`).count();
      const child = await page.locator('.w-chip', { hasText: 'ZZ SOBP Cat B › ZZ SOBP Cat B Child' }).count();
      const fast = await page.locator('#performance .w-row', { hasText: 'Fast lookup' }).count();
      const html = await page.content();
      record(resp.status() === 200 && counts.every(c => c === 1) && always === 1 && cats >= 3 && catA === 1 && child === 1 && fast === 1 && !/Fatal error|Warning<\/b>|Notice<\/b>|critical error/i.test(html),
        'hpos: the settings page shows every field', `status ${resp.status()}, switches ${JSON.stringify(counts)}, always ${always}, categories ${cats} (A ${catA}, child label ${child}), fast ${fast}`);
    } catch (e) { record(false, 'hpos: settings page fields', e.message.split('\n')[0]); }

    // Switch the SKU filter off and on (the case the WooCommerce section covered).
    try {
      await openSettings(page);
      await page.locator(sw('search_orders_by_sku')).setChecked(false, { force: true });
      const url = await saveSettings(page);
      const toast = await page.locator('.w-toast.is-shown').count();
      const storedOff = stored().search_orders_by_sku;
      await page.goto(LIST, { waitUntil: 'domcontentloaded' });
      const off = await page.locator('#search_sku').count();
      await openSettings(page);
      const shownOff = await page.locator(sw('search_orders_by_sku')).isChecked();
      await page.locator(sw('search_orders_by_sku')).setChecked(true, { force: true });
      await saveSettings(page);
      const storedOn = stored().search_orders_by_sku;
      await page.goto(LIST, { waitUntil: 'domcontentloaded' });
      const on = await page.locator('#search_sku').count();
      record(/w-saved=1/.test(url) && toast === 1 && storedOff === 'no' && off === 0 && shownOff === false && storedOn === 'yes' && on === 1,
        'hpos: Settings switch the SKU filter off and on', `redirect ${url.replace(A, '')}, toast ${toast}, stored ${storedOff}/${storedOn}, box ${off}/${on}, shown off ${shownOff}`);
    } catch (e) { record(false, 'hpos: Settings SKU switch', e.message.split('\n')[0]); }

    // Switch the category filter off, then back on (the case the old WPHEKA page covered).
    try {
      await openSettings(page);
      await page.locator(sw('search_orders_by_product_category')).setChecked(false, { force: true });
      const hidden = await page.locator('#sobp-category-rows.is-off').count();
      await saveSettings(page);
      const storedOff = stored().search_orders_by_product_category;
      await page.goto(LIST, { waitUntil: 'domcontentloaded' });
      const off = await page.locator('select.dropdown_product_cat').count();
      await openSettings(page);
      await page.locator(sw('search_orders_by_product_category')).setChecked(true, { force: true });
      await saveSettings(page);
      await page.goto(LIST, { waitUntil: 'domcontentloaded' });
      const on = await page.locator('select.dropdown_product_cat').count();
      record(hidden === 1 && storedOff === 'no' && off === 0 && on === 1, 'hpos: Settings switch the category filter off and on again', `picker hidden ${hidden}, stored ${storedOff}, dropdown off ${off} on ${on}`);
    } catch (e) { record(false, 'hpos: Settings category switch', e.message.split('\n')[0]); }

    // The category picker: list only category A, then every category again. An add-on's
    // key in the option survives both saves.
    try {
      php('settings-set.php', 'zz_addon_key', '"kept"');
      await openSettings(page);
      await page.locator('[data-w-chipfind]').fill('ZZ SOBP Cat A');
      const visible = await page.locator('#category-filter .w-chip:visible').count();
      const bar = await page.locator('.w-savebar.is-shown').count();
      await page.locator(`input[name="sobp_settings[category_include][]"][value="${ids.cat_a}"]`).setChecked(true, { force: true });
      await saveSettings(page);
      const one = stored();
      await page.goto(LIST, { waitUntil: 'domcontentloaded' });
      const listed = await page.$$eval('select.dropdown_product_cat option', os => os.map(o => o.value).filter(Boolean));
      const statCats = await (async () => { await openSettings(page); return (await page.locator('.w-stat', { hasText: 'Categories listed' }).locator('.w-stat-value').innerText()).trim(); })();
      await page.locator(`input[name="sobp_settings[category_include][]"][value="${ids.cat_a}"]`).setChecked(false, { force: true });
      await saveSettings(page);
      const all = stored();
      record(visible === 1 && bar === 0 && JSON.stringify(one.category_include) === JSON.stringify([ids.cat_a]) && listed.length === 1 && statCats === '1'
        && Array.isArray(all.category_include) && all.category_include.length === 0 && one.zz_addon_key === 'kept' && all.zz_addon_key === 'kept',
        'hpos: Settings category picker lists one category, then all, and keeps add-on keys',
        `search shows ${visible}, save bar on search ${bar}, stored ${JSON.stringify(one.category_include)} then ${JSON.stringify(all.category_include)}, dropdown ${JSON.stringify(listed)}, stat ${statCats}, add-on ${one.zz_addon_key}/${all.zz_addon_key}`);
    } catch (e) { record(false, 'hpos: Settings category picker', e.message.split('\n')[0]); }
    php('settings-set.php', 'zz_addon_key', 'unset');
    php('settings-set.php', 'category_include', '[]');
    // Whatever happened above, the later cases need every filter on.
    php('enable-all.php');

    // Stats and the Help tab.
    try {
      await openSettings(page);
      const filtersOn = (await page.locator('.w-stat', { hasText: 'Filters on' }).locator('.w-stat-value').innerText()).replace(/\s+/g, ' ').trim();
      const source = await page.locator('.w-stat', { hasText: 'Data source' }).locator('.w-stat-value').innerText();
      const orders = (await page.locator('.w-stat', { hasText: 'Orders on the store' }).locator('.w-stat-value').innerText()).replace(/\D/g, '');
      const help = await page.goto(`${SETTINGS}&tab=help`, { waitUntil: 'domcontentloaded' });
      const cards = await page.locator('.w-flow .w-flow-step').count();
      record(filtersOn === '7 of 7' && /Fast|Direct/.test(source) && parseInt(orders, 10) >= 6 && help.status() === 200 && cards === 4,
        'hpos: stats show real numbers and the Help tab opens', `filters ${filtersOn}, source ${source}, orders ${orders}, help ${help.status()} with ${cards} cards`);
    } catch (e) { record(false, 'hpos: stats and Help', e.message.split('\n')[0]); }

    // A save without a valid nonce is refused and changes nothing.
    try {
      const before = php('settings.php');
      const r = await page.request.post(`${A}admin-post.php`, { form: { action: 'wc_search_orders_by_product_save_settings', _wpnonce: 'nope', 'sobp_settings[search_orders_by_sku]': 'no' }, maxRedirects: 0 });
      const after = php('settings.php');
      record(r.status() === 403 && before === after, 'hpos: a settings save without a valid nonce is refused', `status ${r.status()}, changed ${before !== after}`);
    } catch (e) { record(false, 'hpos: settings nonce', e.message.split('\n')[0]); }

    // The plugins screen Settings link opens the page.
    try {
      await page.goto(`${A}plugins.php`, { waitUntil: 'domcontentloaded' });
      const href = await page.locator('tr[data-plugin="wc-search-orders-by-product/wc-search-orders-by-product.php"] .settings a').getAttribute('href');
      record(/page=wc-search-orders-by-product-settings/.test(href || ''), 'hpos: the plugins screen Settings link opens the settings page', `href ${href}`);
    } catch (e) { record(false, 'hpos: Settings link', e.message.split('\n')[0]); }

    // A shop manager can filter orders, sees WPHEKA > Search Orders and can save settings.
    try {
      const mctx = await browser.newContext({ viewport: { width: 1400, height: 1200 } });
      await mctx.addCookies(ids.manager_cookies);
      const mp = await mctx.newPage(); mp.setDefaultTimeout(120000); track(mp);
      await mp.goto(`${LIST}&product_id=${ids.A}`, { waitUntil: 'domcontentloaded' });
      const listed = await mp.$$eval('#the-list tr[id]', trs => trs.map(t => parseInt(t.id.replace(/\D/g, ''), 10)));
      const filtered = JSON.stringify(listed.sort((a, b) => a - b)) === JSON.stringify(want(['o1', 'o3']));
      const menu = await mp.locator('#adminmenu a[href*="page=wc-search-orders-by-product-settings"]').count();
      const resp = await openSettings(mp);
      await mp.locator(sw('search_orders_by_billing_country')).setChecked(false, { force: true });
      await saveSettings(mp);
      const saved = stored().search_orders_by_billing_country;
      php('enable-all.php');
      record(filtered && menu >= 1 && resp.status() === 200 && saved === 'no', 'hpos: a shop manager can filter orders, find the page in the menu and save settings', `filtered ${JSON.stringify(listed)}, menu links ${menu}, page ${resp.status()}, saved ${saved}`);
      await mctx.close();
    } catch (e) { record(false, 'hpos: shop manager', e.message.split('\n')[0]); php('enable-all.php'); }

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
  record(true, name('browser cases ran to the end'), '');
  // WooCommerce PayPal Payments throws React error #299 from its own settings
  // script on every WooCommerce settings page of this site, General included
  // (checked 2026-10-07), so it says nothing about this plugin.
  const real = errors.filter(e => !/Transition was skipped/.test(e) && !/woocommerce-paypal-payments\/assets\/ppcp-settings/.test(e));
  record(real.length === 0, name('no JavaScript errors on these screens'), JSON.stringify(real));
  await browser.close();
})();
