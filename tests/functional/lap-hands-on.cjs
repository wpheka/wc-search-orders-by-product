/**
 * Laptop only (queue 2026-10-22 item 3.5): the order filters used the way a store owner uses
 * them, through the controls (product search box, SKU box, category and type dropdowns, Filter
 * button) instead of URL parameters, on the screen of the storage in use. Also the Purchased
 * column and a settings save round trip. Not part of the suite.
 *
 *   NODE_PATH=... node tests/functional/lap-hands-on.cjs <base-url> <ids-json> <hpos|legacy> [shots-dir]
 *
 * <ids-json> is what lap-hands-on-fixture.php create printed. Logs in as admin / password.
 * Prints PASS or FAIL per case and exits non-zero on any failure.
 *
 * @package WC_Search_Orders_By_Product
 */

'use strict';

const { chromium } = require( 'playwright' );

const BASE = process.argv[ 2 ].replace( /\/$/, '' );
const ids = JSON.parse( process.argv[ 3 ] );
const STORE = process.argv[ 4 ];
const SHOTS = process.argv[ 5 ] ? process.argv[ 5 ].replace( /\/?$/, '/' ) : '';
const LIST = 'hpos' === STORE ? `${ BASE }/wp-admin/admin.php?page=wc-orders` : `${ BASE }/wp-admin/edit.php?post_type=shop_order`;
const SETTINGS = `${ BASE }/wp-admin/admin.php?page=wc-search-orders-by-product-settings&tab=settings`;
const MINE = [ ids.o1, ids.o2, ids.o3 ];
let failed = 0;

function check( name, ok, detail = '' ) {
	if ( ! ok ) {
		failed++;
	}
	console.log( `${ ok ? 'PASS' : 'FAIL' }|${ STORE }: ${ name }${ ok ? '' : ' -- ' + detail }` );
}
const same = ( a, b ) => JSON.stringify( [ ...a ].sort() ) === JSON.stringify( [ ...b ].sort() );

async function listed( page ) {
	return page.evaluate( () => Array.from( document.querySelectorAll( 'table.wp-list-table tbody tr[id]' ) ).map( ( tr ) => parseInt( tr.id.replace( /\D/g, '' ), 10 ) ).filter( Boolean ) );
}
async function filter( page ) {
	await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.locator( '#post-query-submit, #order-query-submit, input[name="filter_action"]' ).first().click() ] );
}
// A selectWoo control: open it, type, pick the first result containing `pick`.
async function choose( page, select, typed, pick ) {
	const box = page.locator( `${ select } + .select2, ${ select } ~ .select2` ).first();
	await box.locator( '.select2-selection' ).click();
	// Some dropdowns have no search box (selectWoo hides it for short lists): type only if there is one.
	const search = page.locator( '.select2-container--open .select2-search__field' );
	if ( typed && await search.count() && await search.first().isVisible() ) {
		await search.first().fill( typed );
	}
	const option = page.locator( '.select2-results__option', { hasText: pick } ).first();
	await option.waitFor( { timeout: 30000 } );
	await option.click();
}

( async () => {
	const browser = await chromium.launch( { channel: 'chrome', headless: true } );
	const page = await ( await browser.newContext( { viewport: { width: 1440, height: 1000 } } ) ).newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await Promise.all( [ page.waitForURL( /wp-admin/, { waitUntil: 'domcontentloaded', timeout: 90000 } ), page.click( '#wp-submit' ) ] );

	const cases = [
		[ 'product search box: "ZZH Mug" finds the two orders with the mug', async () => choose( page, 'select.wc-product-search', 'ZZH Mug', 'ZZH Mug' ), [ ids.o1, ids.o3 ], true ],
		[ 'product search box: the variation "Small" finds only its order', async () => choose( page, 'select.wc-product-search', 'ZZH Shirt', 'Small' ), [ ids.o2 ], true ],
		[ 'product search box: the variable parent finds the orders of both variations', async () => choose( page, 'select.wc-product-search', 'ZZH Shirt', 'ZZH Shirt' ), [ ids.o2, ids.o3 ], true ],
		[ 'SKU box: ZZH-SHIRT-L finds only that order', async () => page.fill( '#search_sku', 'ZZH-SHIRT-L' ), [ ids.o3 ], true ],
		[ 'SKU box: the prefix ZZH-SHIRT finds both shirt orders', async () => page.fill( '#search_sku', 'ZZH-SHIRT' ), [ ids.o2, ids.o3 ], true ],
		[ 'category dropdown: ZZH Mugs includes its sub-category', async () => choose( page, 'select[name="search_product_cat"]', 'ZZH', 'ZZH Mugs' ), [ ids.o1, ids.o2, ids.o3 ], false ],
		[ 'category dropdown: the sub-category ZZH Travel alone', async () => choose( page, 'select[name="search_product_cat"]', 'ZZH', 'ZZH Travel' ), [ ids.o2, ids.o3 ], false ],
		[ 'product type dropdown: Variable product', async () => choose( page, 'select[name="search_product_type"]', '', 'Variable' ), [ ids.o2, ids.o3 ], false ],
		[ 'product type dropdown: Simple product', async () => choose( page, 'select[name="search_product_type"]', '', 'Simple' ), [ ids.o1, ids.o3 ], false ],
	];
	let n = 0;
	for ( const [ name, act, want, onlyMine ] of cases ) {
		try {
			await page.goto( LIST, { waitUntil: 'domcontentloaded' } );
			await act();
			await filter( page );
			const all = await listed( page );
			const mine = all.filter( ( id ) => MINE.includes( id ) );
			const others = all.filter( ( id ) => ! MINE.includes( id ) );
			check( name, same( mine, want ) && ( ! onlyMine || 0 === others.length ), `listed ${ JSON.stringify( all ) }, want ${ JSON.stringify( want ) }` );
			if ( SHOTS ) {
				await page.screenshot( { path: `${ SHOTS }hands-on-${ STORE }-${ ++n }.png` } );
			}
		} catch ( e ) {
			check( name, false, e.message.split( '\n' )[ 0 ] );
		}
	}

	// The Purchased column.
	await page.goto( LIST, { waitUntil: 'domcontentloaded' } );
	const col = await page.evaluate( ( id ) => {
		const head = Array.from( document.querySelectorAll( 'table.wp-list-table thead th' ) ).map( ( th ) => th.textContent.trim() );
		const row = document.querySelector( `tr#order-${ id }, tr#post-${ id }` );
		const cell = row ? row.querySelector( 'td.column-sobp_items, td[class*="sobp_items"]' ) : null;
		return { head, cell: cell ? cell.innerText.replace( /\s+/g, ' ' ).trim() : '(no cell)' };
	}, ids.o3 );
	check( 'Purchased column lists both items of the two-item order', col.head.some( ( h ) => /Purchased/i.test( h ) ) && /ZZH Mug/.test( col.cell ) && /ZZH Shirt/.test( col.cell ), JSON.stringify( col ) );

	// Settings round trip: SKU filter off, then on again.
	const skuSwitch = 'input[name="sobp_settings[search_orders_by_sku]"][type="checkbox"]';
	const save = async () => Promise.all( [ page.waitForNavigation( { waitUntil: 'load' } ), page.locator( '.w-savebar button[type="submit"]' ).click() ] );
	await page.goto( SETTINGS, { waitUntil: 'networkidle' } );
	const was = await page.locator( skuSwitch ).isChecked();
	if ( was ) {
		await page.locator( skuSwitch ).click( { force: true } );
		await save();
		await page.goto( LIST, { waitUntil: 'domcontentloaded' } );
		const gone = 0 === ( await page.locator( '#search_sku' ).count() );
		await page.goto( SETTINGS, { waitUntil: 'networkidle' } );
		await page.locator( skuSwitch ).click( { force: true } );
		await save();
		await page.goto( LIST, { waitUntil: 'domcontentloaded' } );
		const back = 1 === ( await page.locator( '#search_sku' ).count() );
		check( 'settings round trip: SKU filter off hides the SKU box, on shows it again', gone && back, `hidden after off: ${ gone }, shown after on: ${ back }` );
	} else {
		check( 'settings round trip: SKU filter was on to start with', false, 'SKU switch was off' );
	}

	check( 'no JavaScript errors on these screens', 0 === errors.length, JSON.stringify( errors ).slice( 0, 300 ) );
	await browser.close();
	process.exit( failed ? 1 : 0 );
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
