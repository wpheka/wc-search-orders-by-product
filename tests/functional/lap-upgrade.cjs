/**
 * Laptop only (queue 2026-10-22 item 3.2): the upgrade from 4.0. Not part of the suite.
 *
 *   NODE_PATH=... node tests/functional/lap-upgrade.cjs set   <base-url> [shots-dir]
 *   NODE_PATH=... node tests/functional/lap-upgrade.cjs check <base-url> [shots-dir]
 *
 * set:   with 4.0 active, set every setting in WooCommerce > Settings > Advanced >
 *        Search orders by product to a non-default value (every filter and the Purchased
 *        column off, two categories chosen) and save, as a store owner would.
 * check: with 4.1.1 active, read what the new WPHEKA > Search Orders page shows, save it once
 *        unchanged, and check the old WooCommerce section links to the new page.
 * Logs in as admin / password. Prints what it finds; the database comparison is done outside.
 *
 * @package WC_Search_Orders_By_Product
 */

'use strict';

const { chromium } = require( 'playwright' );

const MODE = process.argv[ 2 ];
const BASE = ( process.argv[ 3 ] || 'http://localhost:8894' ).replace( /\/$/, '' );
const SHOTS = process.argv[ 4 ] ? process.argv[ 4 ].replace( /\/?$/, '/' ) : '';
const SECTION = `${ BASE }/wp-admin/admin.php?page=wc-settings&tab=advanced&section=wc_search_orders_by_product`;
const PAGE = `${ BASE }/wp-admin/admin.php?page=wc-search-orders-by-product-settings`;

async function shot( page, name ) {
	if ( SHOTS ) {
		await page.screenshot( { path: SHOTS + name, fullPage: true } );
	}
}

async function readForm( page, selector ) {
	return page.evaluate( ( sel ) => {
		const out = {};
		const form = document.querySelector( sel );
		if ( ! form ) {
			return { error: 'no form ' + sel };
		}
		form.querySelectorAll( 'input, select, textarea' ).forEach( ( el ) => {
			if ( ! el.name || ! /^sobp_settings/.test( el.name ) || 'hidden' === el.type ) {
				return;
			}
			if ( 'checkbox' === el.type ) {
				if ( el.name.endsWith( '[]' ) ) {
					out[ el.name ] = out[ el.name ] || [];
					if ( el.checked ) {
						out[ el.name ].push( el.value );
					}
				} else {
					out[ el.name ] = el.checked;
				}
			} else if ( el.multiple ) {
				out[ el.name ] = Array.from( el.selectedOptions ).map( ( o ) => o.value );
			} else {
				out[ el.name ] = el.value;
			}
		} );
		return out;
	}, selector );
}

( async () => {
	const browser = await chromium.launch( { channel: 'chrome', headless: true } );
	const page = await ( await browser.newContext( { viewport: { width: 1440, height: 1000 } } ) ).newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.goto( `${ BASE }/wp-login.php`, { waitUntil: 'domcontentloaded' } );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );

	if ( 'set' === MODE ) {
		await page.goto( SECTION, { waitUntil: 'load' } );
		console.log( 'BEFORE (4.0 defaults on screen)', JSON.stringify( await readForm( page, '#mainform' ) ) );
		// Every checkbox off.
		for ( const box of await page.locator( '#mainform input[type="checkbox"][name^="sobp_settings"]' ).all() ) {
			if ( await box.isChecked() ) {
				await box.click();
			}
		}
		// Two categories in the select2 multiselect: set the real select and tell select2.
		await page.evaluate( () => {
			const el = document.querySelector( 'select[name^="sobp_settings[category_include]"]' );
			const picks = Array.from( el.options ).filter( ( o ) => /ZZ Upgrade Cat (A|C)/.test( o.textContent ) ).map( ( o ) => o.value );
			Array.from( el.options ).forEach( ( o ) => {
				o.selected = picks.includes( o.value );
			} );
			if ( window.jQuery ) {
				window.jQuery( el ).trigger( 'change' );
			}
		} );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'load' } ), page.click( '#mainform button[name="save"]' ) ] );
		console.log( 'SAVED:', ( ( await page.locator( '#message, .updated' ).first().textContent().catch( () => '' ) ) || '' ).trim().slice( 0, 60 ) );
		console.log( 'SHOWN BY 4.0', JSON.stringify( await readForm( page, '#mainform' ) ) );
		await shot( page, 'upgrade-40-settings.png' );
	} else {
		await page.goto( PAGE, { waitUntil: 'networkidle' } );
		await shot( page, 'upgrade-411-settings.png' );
		const form = await page.locator( 'form[data-w-form]' ).count() ? 'form[data-w-form]' : 'form';
		console.log( 'SHOWN BY 4.1.1', JSON.stringify( await readForm( page, form ) ) );
		const cats = await page.evaluate( () => Array.from( document.querySelectorAll( '[name^="sobp_settings[category_include]"]' ) ).filter( ( e ) => e.checked || e.selected || ( e.selectedOptions && e.selectedOptions.length ) ).map( ( e ) => e.value || Array.from( e.selectedOptions || [] ).map( ( o ) => o.value ).join( ',' ) ) );
		console.log( 'CATEGORIES SHOWN', JSON.stringify( cats ) );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'load' } ), page.evaluate( ( sel ) => document.querySelector( sel ).requestSubmit(), form ) ] );
		console.log( 'SAVED WITHOUT CHANGES:', ( ( await page.locator( '.w-toast, .w-notice, .notice' ).first().textContent().catch( () => '' ) ) || '' ).trim().slice( 0, 60 ) );

		await page.goto( SECTION, { waitUntil: 'load' } );
		await shot( page, 'upgrade-411-old-section.png' );
		const old = await page.evaluate( () => {
			const f = document.querySelector( '#mainform' );
			const link = f ? f.querySelector( 'a[href*="page=wc-search-orders-by-product-settings"]' ) : null;
			return { fields: f ? f.querySelectorAll( '[name^="sobp_settings"]' ).length : -1, link: link ? link.href : '', save: f ? f.querySelectorAll( 'button[name="save"]' ).length : -1, text: f ? f.innerText.replace( /\s+/g, ' ' ).slice( 0, 300 ) : '' };
		} );
		console.log( 'OLD SECTION', JSON.stringify( old ) );
		if ( old.link ) {
			await page.goto( old.link, { waitUntil: 'networkidle' } );
			console.log( 'OLD SECTION LINK OPENS', page.url().replace( BASE, '' ), '| form:', await page.locator( 'form[data-w-form]' ).count() );
		}
	}
	console.log( 'SCRIPT ERRORS', JSON.stringify( errors ) );
	await browser.close();
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
