/**
 * Laptop only: lets admin.js run against a wp-env store from Windows. Loaded by
 * lap-run-wpenv.sh with `node -r`. Not part of the suite; run.sh does not use it.
 *
 * - admin.js runs `wp --path=<WP_PATH> eval-file <file> ...` with execFileSync. Here that
 *   becomes `docker exec -e SOBP_STATE=<state in the container> <cli container> wp eval-file
 *   <the same file inside the container> ...`.
 * - admin.js launches Playwright's bundled Chromium; the laptop has no matching download, so
 *   the installed Google Chrome is used instead (as PW_CHANNEL=chrome does in other suites).
 *
 * Needs LAP_CLI (container name), LAP_PLUGINS_HOST (host plugins folder, forward slashes) and
 * LAP_STATE_CONTAINER (SOBP_STATE as the container sees it).
 *
 * @package WC_Search_Orders_By_Product
 */

'use strict';

const cp = require( 'child_process' );
const playwright = require( 'playwright' );

const original = cp.execFileSync;
const hostPlugins = ( process.env.LAP_PLUGINS_HOST || '' ).replace( /\\/g, '/' ).replace( /\/$/, '' ).toLowerCase();

function toContainer( arg ) {
	const a = String( arg ).replace( /\\/g, '/' );
	if ( hostPlugins && a.toLowerCase().startsWith( hostPlugins + '/' ) ) {
		return '/var/www/html/wp-content/plugins/' + a.slice( hostPlugins.length + 1 );
	}
	return arg;
}

cp.execFileSync = function ( file, args, options ) {
	if ( 'wp' !== file ) {
		return original.apply( this, arguments );
	}
	const wpArgs = ( args || [] ).filter( ( a ) => ! String( a ).startsWith( '--path=' ) ).map( toContainer );
	const opts = Object.assign( {}, options || {} );
	delete opts.env;
	return original.call( this, 'docker', [ 'exec', '-e', `SOBP_STATE=${ process.env.LAP_STATE_CONTAINER }`, '-w', '/var/www/html', process.env.LAP_CLI, 'wp', ...wpArgs ], opts );
};

const launch = playwright.chromium.launch.bind( playwright.chromium );
playwright.chromium.launch = ( opts ) => launch( Object.assign( { channel: 'chrome' }, opts || {} ) );
