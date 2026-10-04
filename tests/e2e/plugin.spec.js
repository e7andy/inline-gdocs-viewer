// Browser tests: tables, buttons, charts, and asset loading on the wp-env site.
const { test, expect } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );
const { wp } = require( './wp' );

const ids = () => JSON.parse( fs.readFileSync( path.join( __dirname, '.posts.json' ), 'utf8' ) );
const url = ( name ) => `/?p=${ ids()[ name ] }`;

// Fail a test on any JavaScript error or console error.
test.beforeEach( async ( { page }, testInfo ) => {
    testInfo.errors_seen = [];
    page.on( 'pageerror', ( err ) => testInfo.errors_seen.push( err.message ) );
    page.on( 'console', ( msg ) => {
        // Only the site's own page counts, not embedded third-party frames
        // such as the Google Docs Viewer.
        if ( msg.type() === 'error' && msg.page() === page && ( ! msg.location().url || msg.location().url.startsWith( new URL( page.url() ).origin ) ) ) {
            testInfo.errors_seen.push( msg.text() );
        }
    } );
} );
test.afterEach( async ( {}, testInfo ) => {
    expect( testInfo.errors_seen, 'JavaScript errors' ).toEqual( [] );
} );

test( 'table is enhanced by DataTables with buttons, search and sorting', async ( { page } ) => {
    await page.goto( url( 'table' ) );
    const container = page.locator( '.dt-container' );
    await expect( container ).toBeVisible();
    await expect( container.locator( '.dt-buttons button' ) ).toHaveCount( 6 );
    await expect( page.locator( 'table.igsv-table tbody tr' ) ).toHaveCount( 4 );

    await container.locator( 'input[type="search"]' ).fill( 'Ninjas' );
    await expect( page.locator( 'table.igsv-table tbody tr' ) ).toHaveCount( 1 );
    await container.locator( 'input[type="search"]' ).fill( '' );

    await page.locator( 'table.igsv-table thead th' ).nth( 1 ).click(); // sort by Goals ascending
    await expect( page.locator( 'table.igsv-table tbody tr td' ).first() ).toHaveText( 'Aliens' );
    await page.locator( 'table.igsv-table thead th' ).nth( 1 ).click(); // descending
    await expect( page.locator( 'table.igsv-table tbody tr td' ).first() ).toHaveText( 'Ninjas' );
} );

for ( const [ label, file ] of [ [ 'CSV', /\.csv$/ ], [ 'Excel', /\.xlsx$/ ], [ 'PDF', /\.pdf$/ ] ] ) {
    test( `${ label } export button downloads a file`, async ( { page } ) => {
        await page.goto( url( 'table' ) );
        const download = page.waitForEvent( 'download' );
        await page.locator( '.dt-buttons button', { hasText: label } ).click();
        const file_name = ( await download ).suggestedFilename();
        expect( file_name ).toMatch( file );
    } );
}

test( 'copy and column visibility buttons work', async ( { page, context } ) => {
    await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
    await page.goto( url( 'table' ) );
    await page.locator( '.dt-buttons button', { hasText: 'Copy' } ).click();
    await expect( page.locator( '.dt-button-info' ) ).toBeVisible();

    await page.locator( '.dt-buttons button', { hasText: 'Column visibility' } ).click();
    await page.locator( '.dt-button-collection button', { hasText: 'Goals' } ).click();
    await page.keyboard.press( 'Escape' );
    await expect( page.locator( 'table.igsv-table thead th' ) ).toHaveCount( 1 );
} );

test( 'print button opens a print view', async ( { page } ) => {
    await page.goto( url( 'table' ) );
    const popup = page.waitForEvent( 'popup' );
    await page.locator( '.dt-buttons button', { hasText: 'Print' } ).click();
    const print_page = await popup;
    await expect( print_page.locator( 'table' ) ).toContainText( 'Ninjas' );
    await print_page.close();
} );

test( 'fixed columns, fixed header and translations load', async ( { page } ) => {
    const lang_request = page.waitForRequest( /languages\/datatables-nl-NL\.json/ );
    await page.goto( url( 'fixed' ) );
    await lang_request;
    await expect( page.locator( '.dt-container' ) ).toBeVisible();
    await expect( page.locator( 'table.igsv-table td.dtfc-fixed-start' ).first() ).toBeVisible();
    await expect( page.locator( '.dt-search label' ) ).toContainText( /Zoek/i );
} );

test( 'no-datatables tables are left alone', async ( { page } ) => {
    await page.goto( url( 'plain' ) );
    await expect( page.locator( 'table.igsv-table' ) ).toBeVisible();
    await expect( page.locator( '.dt-container' ) ).toHaveCount( 0 );
} );

test( 'query filters and orders a CSV table', async ( { page } ) => {
    await page.goto( url( 'query' ) );
    const cells = page.locator( 'table.igsv-table tbody tr td:first-child' );
    await expect( cells ).toHaveText( [ 'Ninjas', 'Robots', 'Pirates' ] );
} );

test( 'DataTables API documented in the FAQ works', async ( { page } ) => {
    await page.goto( url( 'table' ) );
    await expect( page.locator( '.dt-container' ) ).toBeVisible();
    const rows = await page.evaluate( () => {
        const id = document.querySelector( 'table.igsv-table' ).id;
        jQuery( '#' + id ).DataTable().order( [ 1, 'desc' ] ).draw();
        return jQuery( '#' + id + ' tbody tr td:first-child' ).map( ( i, el ) => el.textContent ).get();
    } );
    expect( rows[ 0 ] ).toBe( 'Ninjas' );
} );

for ( const name of [ 'pie', 'bar' ] ) {
    test( `${ name } chart draws from the signed data source`, async ( { page } ) => {
        const datasource = page.waitForResponse( ( r ) => r.url().includes( 'igsv_datasource=1' ) );
        await page.goto( url( name ) );
        const response = await datasource;
        expect( response.status() ).toBe( 200 );
        expect( response.headers()[ 'x-content-type-options' ] ).toBe( 'nosniff' );
        await expect( page.locator( '.igsv-chart svg' ).first() ).toBeVisible( { timeout: 30000 } );
        await expect( page.locator( '.igsv-chart' ) ).toContainText( 'Ninjas' );
    } );
}

test( 'tampered data source request is refused', async ( { page, request } ) => {
    await page.goto( url( 'pie' ) );
    const href = await page.locator( '.igsv-chart' ).getAttribute( 'data-datasource-href' );
    const tampered = href.replace( /igsv_sig=[^&]+/, 'igsv_sig=0000' );
    const response = await request.get( tampered );
    expect( response.status() ).toBe( 403 );
    const unsigned = await request.get( '/?igsv_datasource=1&url=http://169.254.169.254/' );
    expect( unsigned.status() ).toBe( 403 );
} );

test( 'old proxy endpoint no longer fetches URLs', async ( { request } ) => {
    const response = await request.get( '/?gdoc_get_datasource_nonce=x&url=http%3A%2F%2F169.254.169.254%2F&tqx=out:html' );
    expect( await response.text() ).not.toContain( 'google.visualization' );
} );

test( 'docs viewer iframe is rendered', async ( { page } ) => {
    await page.goto( url( 'viewer' ) );
    await expect( page.locator( 'iframe[src^="https://docs.google.com/viewer?url="]' ) ).toHaveCount( 1 );
} );

test( 'CSV from an address without .csv becomes a DataTables table', async ( { page } ) => {
    await page.goto( url( 'export' ) );
    await expect( page.locator( '.dt-container' ) ).toBeVisible();
    await expect( page.locator( 'table.igsv-table tbody tr' ) ).toHaveCount( 4 );
    await expect( page.locator( 'iframe' ) ).toHaveCount( 0 );
} );

test( 'Apps Script web app HTML is shown as-is for an administrator\'s post', async ( { page } ) => {
    await page.goto( url( 'webapp' ) );
    const app = page.locator( '.igsv-e2e-webapp' );
    await expect( app ).toBeVisible();
    await expect( app.locator( 'strong' ) ).toHaveText( '12' );
    expect( await page.evaluate( () => window.igsvE2eWebappScript === true ) ).toBe( true );
} );

test( 'Apps Script web app HTML is filtered for a contributor\'s post', async ( { page } ) => {
    await page.goto( url( 'webapp_contributor' ) );
    const app = page.locator( '.igsv-e2e-webapp' );
    await expect( app ).toBeVisible();
    await expect( app.locator( 'strong' ) ).toHaveText( '12' );
    await expect( app.locator( 'script' ) ).toHaveCount( 0 );
    await expect( app.locator( '.hover-me' ) ).toBeVisible();
    await expect( app.locator( '[onmouseover]' ) ).toHaveCount( 0 );
    expect( await page.evaluate( () => typeof window.igsvE2eWebappScript ) ).toBe( 'undefined' );
} );

test( 'Apps Script web app returning CSV becomes a DataTables table', async ( { page } ) => {
    await page.goto( url( 'webapp_csv' ) );
    await expect( page.locator( '.dt-container' ) ).toBeVisible();
    await expect( page.locator( 'table.igsv-table tbody tr' ) ).toHaveCount( 4 );
} );

test( 'SQL query on the WordPress database becomes a DataTables table', async ( { page } ) => {
    await page.goto( url( 'sql' ) );
    await expect( page.locator( '.igsv-error' ) ).toHaveCount( 0 );
    await expect( page.locator( '.dt-container' ) ).toBeVisible();
    const headers = await page.locator( 'table.igsv-table thead th' ).allTextContents();
    expect( headers.map( ( h ) => h.trim() ) ).toEqual( [ 'Title', 'Slug' ] );
    const table_id = await page.locator( 'table.igsv-table' ).getAttribute( 'id' );
    const slugs = await page.evaluate( ( id ) => jQuery( '#' + id ).DataTable().column( 1 ).data().toArray(), table_id );
    expect( slugs ).toContain( 'igsv-e2e-sql' );
    expect( slugs.every( ( s ) => s.startsWith( 'igsv-e2e-' ) ) ).toBe( true );
} );

test( 'pages without the shortcode load no plugin scripts', async ( { page } ) => {
    await page.goto( url( 'none' ) );
    const scripts = await page.locator( 'script[src]' ).evaluateAll( ( els ) => els.map( ( e ) => e.src ) );
    expect( scripts.filter( ( s ) => /dataTables|gstatic\.com\/charts|igsv-/.test( s ) ) ).toEqual( [] );
} );

test( 'no third-party CDN requests except the Google Charts loader', async ( { page } ) => {
    const hosts = new Set();
    page.on( 'request', ( r ) => {
        const u = new URL( r.url() );
        if ( u.protocol.startsWith( 'http' ) ) {
            hosts.add( u.host );
        }
    } );
    await page.goto( url( 'table' ) );
    await expect( page.locator( '.dt-container' ) ).toBeVisible();
    for ( const host of hosts ) {
        expect( [ 'localhost:8888', 'localhost' ] ).toContain( host );
    }
} );

test( 'no PHP warnings were logged', async () => {
    const log = wp( 'eval "echo file_exists( WP_CONTENT_DIR . \'/debug.log\' ) ? file_get_contents( WP_CONTENT_DIR . \'/debug.log\' ) : \'\';"' );
    expect( log.split( '\n' ).filter( ( l ) => /PHP (Warning|Notice|Deprecated|Fatal)/.test( l ) ) ).toEqual( [] );
} );
