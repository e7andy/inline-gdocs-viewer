// Live tests against a real public Google Sheet. Run by hand before a release:
//
//   IGSV_LIVE_SHEET="https://docs.google.com/spreadsheets/d/<id>/edit" npm run test:e2e:live
//
// The expected values are read from Google at test time, so the tests keep
// working when the sheet's contents change. The sheet needs a text column A,
// a date-like text column C, and a number column I, with headers in row 1.
const { test, expect } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );

const SHEET = ( process.env.IGSV_LIVE_SHEET || '' ).match( /^https:\/\/docs\.google\.com\/spreadsheets\/d\/[\w-]+/ );
test.skip( ! SHEET, 'Set IGSV_LIVE_SHEET to a public Google Sheet URL to run live tests.' );

const ids = () => JSON.parse( fs.readFileSync( path.join( __dirname, '.posts.json' ), 'utf8' ) );
const url = ( name ) => `/?p=${ ids()[ name ] }`;

// Minimal RFC 4180 CSV parser (quoted fields, "" escapes, newlines in fields).
function parseCsv( text ) {
    const rows = [];
    let row = [], field = '', quoted = false;
    for ( let i = 0; i < text.length; i++ ) {
        const c = text[ i ];
        if ( quoted ) {
            if ( c === '"' && text[ i + 1 ] === '"' ) {
                field += '"';
                i++;
            } else if ( c === '"' ) {
                quoted = false;
            } else {
                field += c;
            }
        } else if ( c === '"' ) {
            quoted = true;
        } else if ( c === ',' ) {
            row.push( field );
            field = '';
        } else if ( c === '\n' || c === '\r' ) {
            if ( c === '\r' && text[ i + 1 ] === '\n' ) {
                i++;
            }
            row.push( field );
            rows.push( row );
            row = [];
            field = '';
        } else {
            field += c;
        }
    }
    if ( field !== '' || row.length ) {
        row.push( field );
        rows.push( row );
    }
    return rows;
}

// Asks Google directly, for comparison with what the plugin shows.
async function fromGoogle( request, query ) {
    const target = query
        ? `${ SHEET[ 0 ] }/gviz/tq?tqx=out:csv&headers=1&tq=${ encodeURIComponent( query ) }`
        : `${ SHEET[ 0 ] }/export?format=csv`;
    const response = await request.get( target );
    expect( response.ok() ).toBeTruthy();
    return parseCsv( await response.text() );
}

test.beforeEach( async ( { page }, testInfo ) => {
    testInfo.errors_seen = [];
    page.on( 'pageerror', ( err ) => testInfo.errors_seen.push( err.message ) );
    page.on( 'console', ( msg ) => {
        if ( msg.type() === 'error' ) {
            testInfo.errors_seen.push( msg.text() );
        }
    } );
} );
test.afterEach( async ( {}, testInfo ) => {
    expect( testInfo.errors_seen, 'JavaScript errors' ).toEqual( [] );
} );

test( 'live sheet: every row is shown, with text, line breaks and links intact', async ( { page, request } ) => {
    const rows = await fromGoogle( request );
    await page.goto( url( 'live_table' ) );
    await expect( page.locator( '.dt-container' ) ).toBeVisible();
    await expect( page.locator( '.igsv-error' ) ).toHaveCount( 0 );

    const table_id = await page.locator( 'table.igsv-table' ).getAttribute( 'id' );
    const shown = await page.evaluate( ( id ) => jQuery( '#' + id ).DataTable().rows().count(), table_id );
    expect( shown ).toBe( rows.length - 1 );

    const headers = await page.locator( 'table.igsv-table thead th' ).allTextContents();
    expect( headers.map( ( h ) => h.trim() ) ).toEqual( rows[ 0 ].map( ( h ) => h.trim() ) );

    // A cell with non-ASCII text, found through the search box.
    const sample = rows.slice( 1 ).find( ( r ) => /[^\x00-\x7F]/.test( r[ 0 ] ) && ! r[ 0 ].includes( '\n' ) );
    if ( sample ) {
        await page.locator( '.dt-search input' ).fill( sample[ 0 ] );
        await expect( page.locator( 'table.igsv-table tbody tr' ).first() ).toContainText( sample[ 0 ] );
        await page.locator( '.dt-search input' ).fill( '' );
    }

    // Multi-line cells keep their line breaks, and URLs become links.
    if ( rows.some( ( r ) => r.some( ( c ) => c.includes( '\n' ) ) ) ) {
        expect( await page.locator( 'table.igsv-table tbody td br' ).count() ).toBeGreaterThan( 0 );
    }
    const link = rows.slice( 1 ).flat().find( ( c ) => /^https?:\/\/\S+$/.test( c.trim() ) );
    if ( link ) {
        await page.evaluate( ( id ) => jQuery( '#' + id ).DataTable().page.len( -1 ).draw(), table_id );
        expect( await page.locator( `table.igsv-table a[href="${ link.trim() }"]` ).count() ).toBeGreaterThan( 0 );
    }
} );

test( 'live sheet: query with csv_headers matches Google\'s own result', async ( { page, request } ) => {
    const rows = await fromGoogle( request, "select A, C, I where C contains '2026'" );
    await page.goto( url( 'live_query' ) );
    await expect( page.locator( '.igsv-error' ) ).toHaveCount( 0 );
    const headers = await page.locator( 'table.igsv-table thead th' ).allTextContents();
    expect( headers.map( ( h ) => h.trim() ) ).toEqual( rows[ 0 ] );

    const table_id = await page.locator( 'table.igsv-table' ).getAttribute( 'id' );
    const shown = await page.evaluate( ( id ) => {
        const dt = jQuery( '#' + id ).DataTable();
        return dt.rows( { order: 'index' } ).data().toArray().map( ( r ) => r.map( ( c ) => jQuery( '<div>' ).html( c ).text() ) );
    }, table_id );
    expect( shown ).toEqual( rows.slice( 1 ) );
} );

test( 'live sheet: query without csv_headers still renders', async ( { page } ) => {
    // Google guesses how many header rows there are. With multi-line cells in
    // the first data row it can merge that row into the header; csv_headers="1"
    // (previous test) avoids that. Either way the plugin must render a table.
    await page.goto( url( 'live_guess' ) );
    await expect( page.locator( '.igsv-error' ) ).toHaveCount( 0 );
    await expect( page.locator( 'table.igsv-table thead th' ).first() ).toContainText( /\S/ );
} );

test( 'live sheet: chart draws straight from Google', async ( { page, request } ) => {
    const rows = await fromGoogle( request, 'select A, I where I is not null order by I desc limit 8' );
    const google_request = page.waitForRequest( ( r ) => r.url().startsWith( SHEET[ 0 ] + '/gviz/tq' ) );
    await page.goto( url( 'live_chart' ) );
    await google_request;
    await expect( page.locator( '.igsv-chart svg' ).first() ).toBeVisible( { timeout: 30000 } );
    await expect( page.locator( '.igsv-chart' ) ).not.toContainText( /error/i );
    await expect( page.locator( '.igsv-chart' ) ).toContainText( 'Longest races' );
    // Google shortens long labels, so compare the start of the first one.
    await expect( page.locator( '.igsv-chart' ) ).toContainText( rows[ 1 ][ 0 ].slice( 0, 10 ) );
} );
