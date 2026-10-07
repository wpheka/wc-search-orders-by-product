// Time full orders-screen loads (server response to DOM ready) on the seeded
// 100,000-order store. node browser-timing.js hpos|legacy  (after seed-large.php)
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const STORE = process.argv[2] || 'hpos';
const wp = (...a) => execFileSync('wp', ['--path=' + process.env.WP_PATH, ...a], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim().split('\n').filter(l => !l.startsWith('Deprecated')).pop();
const u = JSON.parse(wp('eval-file', path.join(__dirname, 'browser-user.php'), 'create'));
const LIST = STORE === 'hpos' ? `${u.admin_url}admin.php?page=wc-orders` : `${u.admin_url}edit.php?post_type=shop_order`;
const clothing = wp('eval', 'echo get_term_by("slug","clothing","product_cat")->term_id;');
const cases = {
  'no filter (WooCommerce alone)': '',
  'simple product': 'product_id=10000123',
  'variable parent': 'product_id=10004600',
  'category with children': `search_product_cat=${clothing}`,
  'type variable': 'search_product_type=variable',
  'SKU prefix': 'search_sku=PERF-12',
  'payment cod': 'search_payment_method=cod',
  'country CA': 'search_billing_country=CA',
  'product + category + payment': `product_id=10000123&search_product_cat=${clothing}&search_payment_method=bacs`,
};
(async () => {
  const b = await chromium.launch(); const ctx = await b.newContext(); await ctx.addCookies(u.cookies);
  const p = await ctx.newPage(); p.setDefaultTimeout(180000);
  let worst = 0;
  console.log(`${STORE}: median of 3 page loads, ms`);
  for (const [label, q] of Object.entries(cases)) {
    const runs = [];
    let rows = 0;
    for (let i = 0; i < 3; i++) {
      const t = Date.now();
      await p.goto(`${LIST}${q ? '&' + q : ''}`, { waitUntil: 'domcontentloaded' });
      runs.push(Date.now() - t);
      rows = await p.locator('#the-list tr[id]').count();
      if (/Fatal error|critical error/i.test(await p.content())) throw new Error('PHP error on ' + label);
    }
    runs.sort((a, c) => a - c);
    if (label !== 'no filter (WooCommerce alone)') worst = Math.max(worst, runs[1]);
    console.log(`  ${label.padEnd(32)} ${String(runs[1]).padStart(6)}   rows on page ${rows}`);
  }
  console.log(`  worst filtered page: ${worst} ms (target < 2000 ms)`);
  await b.close();
  wp('eval-file', path.join(__dirname, 'browser-user.php'), 'delete');
})();
