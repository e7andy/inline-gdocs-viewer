// Live tests against real resources on Google and GitHub. They need the
// internet, so they run by hand before a release and weekly in GitHub Actions
// (.github/workflows/live.yml):
//
//   IGSV_LIVE_SHEET=... IGSV_LIVE_PRIVATE_SHEET=... IGSV_LIVE_WEBAPP=... npm run test:e2e:live
//
// Each group skips itself when its variable isn't set. The resources must
// have the contents described in README.md ("Live tests"):
//
// - IGSV_LIVE_SHEET: public sheet. First tab: Team, Goals, Founded, Website
//   with Aliens 5, Ninjas 12, Pirates 7, Robots 9, Älgar 3. A second tab with
//   Name, Value / Second, 42.
// - IGSV_LIVE_PRIVATE_SHEET: any sheet that is not shared.
// - IGSV_LIVE_WEBAPP: the Apps Script web app from README.md.
// - Files: tests/e2e/fixtures in this repo on GitHub (IGSV_LIVE_FILES to override).
const { test, expect } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );
const { wp } = require( './wp' );
const live = require( './live' );

const ids = () => JSON.parse( fs.readFileSync( path.join( __dirname, '.posts.json' ), 'utf8' ) );
const url = ( name ) => `/?p=${ ids()[ name ] }`;
const cells = async ( page, column ) =>
    ( await page.locator( `table.igsv-table tbody tr td:nth-child(${ column })` ).allTextContents() ).map( ( t ) => t.trim() );
const headers = async ( page ) =>
    ( await page.locator( 'table.igsv-table thead th' ).allTextContents() ).map( ( t ) => t.trim() );

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

test.describe( 'live Google Sheet', () => {
    test.skip( ! live.sheet, 'Set IGSV_LIVE_SHEET to run.' );

    test( 'table shows every row, with text, dates, and links intact', async ( { page } ) => {
        await page.goto( url( 'live_table' ) );
        await expect( page.locator( '.dt-container' ) ).toBeVisible();
        expect( await headers( page ) ).toEqual( [ 'Team', 'Goals', 'Founded', 'Website' ] );

        const id = await page.locator( 'table.igsv-table' ).getAttribute( 'id' );
        const rows = await page.evaluate( ( tid ) => jQuery( '#' + tid ).DataTable().rows( { order: 'index' } ).data().toArray()
            .map( ( r ) => r.map( ( c ) => jQuery( '<div>' ).html( c ).text() ) ), id );
        expect( rows ).toEqual( [
            [ 'Aliens', '5', '2020-01-15', 'https://example.com/aliens' ],
            [ 'Ninjas', '12', '2019-06-01', 'https://example.com/ninjas' ],
            [ 'Pirates', '7', '2021-03-20', 'https://example.com/pirates' ],
            [ 'Robots', '9', '2018-11-05', 'https://example.com/robots' ],
            [ 'Älgar', '3', '2022-08-30', '' ],
        ] );

        await page.locator( '.dt-search input' ).fill( 'Älgar' );
        await expect( page.locator( 'table.igsv-table tbody tr' ) ).toHaveCount( 1 );
        await page.locator( '.dt-search input' ).fill( '' );

        const link = page.locator( 'table.igsv-table a[href="https://example.com/aliens"]' );
        await expect( link ).toHaveAttribute( 'target', '_blank' );
        await expect( link ).toHaveAttribute( 'rel', /noopener/ );
    } );

    test( 'a bare sheet ID works like the full URL', async ( { page } ) => {
        await page.goto( url( 'live_bare' ) );
        expect( await cells( page, 1 ) ).toEqual( [ 'Aliens', 'Ninjas', 'Pirates', 'Robots', 'Älgar' ] );
    } );

    test( 'the second tab is chosen by gid', async ( { page } ) => {
        test.skip( ! ids().live_tab, 'Second tab (Name, Value) not found in the sheet.' );
        await page.goto( url( 'live_tab' ) );
        expect( await headers( page ) ).toEqual( [ 'Name', 'Value' ] );
        expect( await cells( page, 1 ) ).toEqual( [ 'Second' ] );
        expect( await cells( page, 2 ) ).toEqual( [ '42' ] );
    } );

    test( 'query filters and orders on Google\'s side', async ( { page } ) => {
        await page.goto( url( 'live_query' ) );
        expect( await headers( page ) ).toEqual( [ 'Team', 'Goals' ] );
        expect( await cells( page, 1 ) ).toEqual( [ 'Ninjas', 'Robots', 'Pirates' ] );
        expect( await cells( page, 2 ) ).toEqual( [ '12', '9', '7' ] );
    } );

    test( 'chart draws straight from Google', async ( { page } ) => {
        const google = page.waitForRequest( ( r ) => r.url().startsWith( live.sheet + '/gviz/tq' ) );
        await page.goto( url( 'live_chart' ) );
        await google;
        const chart = page.locator( '.igsv-chart' );
        await expect( chart.locator( 'svg' ).first() ).toBeVisible( { timeout: 30000 } );
        await expect( chart ).toContainText( 'Goals per team' );
        await expect( chart ).toContainText( 'Ninjas' );
    } );
} );

test.describe( 'live private Google Sheet', () => {
    test.skip( ! live.privateSheet, 'Set IGSV_LIVE_PRIVATE_SHEET to run.' );

    test( 'a sheet that is not shared shows the sharing hint', async ( { page } ) => {
        await page.goto( url( 'live_private' ) );
        await expect( page.locator( '.igsv-error' ) ).toContainText( 'Anyone with the link' );
        await expect( page.locator( 'table.igsv-table' ) ).toHaveCount( 0 );
    } );
} );

test.describe( 'live Apps Script web app', () => {
    test.skip( ! live.webapp, 'Set IGSV_LIVE_WEBAPP to run.' );

    test( 'HTML is shown as-is in an administrator\'s post', async ( { page } ) => {
        await page.goto( url( 'live_webapp' ) );
        const app = page.locator( '.igsv-live-webapp' );
        await expect( app ).toBeVisible();
        await expect( app.locator( 'strong' ) ).toHaveText( '33' );
        expect( await page.evaluate( () => window.igsvLiveWebappScript === true ) ).toBe( true );
    } );

    test( 'HTML is filtered in a contributor\'s post', async ( { page } ) => {
        await page.goto( url( 'live_webapp_contributor' ) );
        const app = page.locator( '.igsv-live-webapp' );
        await expect( app.locator( 'strong' ) ).toHaveText( '33' );
        await expect( app.locator( 'script' ) ).toHaveCount( 0 );
        expect( await page.evaluate( () => typeof window.igsvLiveWebappScript ) ).toBe( 'undefined' );
    } );

    test( 'CSV output becomes a table', async ( { page } ) => {
        await page.goto( url( 'live_webapp_csv' ) );
        await expect( page.locator( '.dt-container' ) ).toBeVisible();
        expect( await headers( page ) ).toEqual( [ 'Team', 'Goals' ] );
        await expect( page.locator( 'table.igsv-table tbody tr' ) ).toHaveCount( 4 );
    } );

    test( 'chart draws from the web app', async ( { page } ) => {
        const app = page.waitForRequest( ( r ) => r.url().startsWith( live.webapp ) && r.url().includes( 'tqx=' ) );
        await page.goto( url( 'live_webapp_chart' ) );
        await app;
        const chart = page.locator( '.igsv-chart' );
        await expect( chart.locator( 'svg' ).first() ).toBeVisible( { timeout: 30000 } );
        await expect( chart ).toContainText( 'Web app goals' );
        await expect( chart ).toContainText( 'Ninjas' );
    } );
} );

test.describe( 'live files on GitHub', () => {
    test.skip( ! live.enabled, 'Set any IGSV_LIVE_* variable to run.' );

    test( 'a CSV file over HTTPS becomes a table', async ( { page } ) => {
        await page.goto( url( 'live_file_csv' ) );
        await expect( page.locator( '.dt-container' ) ).toBeVisible();
        expect( await cells( page, 1 ) ).toEqual( [ 'Aliens', 'Ninjas', 'Pirates', 'Robots' ] );
    } );

    test( 'a chart from a CSV file uses the signed data endpoint', async ( { page } ) => {
        const endpoint = page.waitForResponse( ( r ) => r.url().includes( 'igsv_datasource=1' ) );
        await page.goto( url( 'live_file_chart' ) );
        expect( ( await endpoint ).status() ).toBe( 200 );
        await expect( page.locator( '.igsv-chart svg' ).first() ).toBeVisible( { timeout: 30000 } );
        await expect( page.locator( '.igsv-chart' ) ).toContainText( 'Ninjas' );
    } );

    test( 'a PDF opens in the Google Docs Viewer', async ( { page } ) => {
        await page.goto( url( 'live_file_pdf' ) );
        const frame = page.locator( 'iframe[src^="https://docs.google.com/viewer?url="]' );
        await expect( frame ).toHaveCount( 1 );
        expect( await frame.getAttribute( 'src' ) ).toContain( encodeURIComponent( live.files + '/report.pdf' ) );
    } );
} );

test( 'live: no PHP warnings were logged', async () => {
    test.skip( ! live.enabled, 'No live resources configured.' );
    const log = wp( 'eval "echo file_exists( WP_CONTENT_DIR . \'/debug.log\' ) ? file_get_contents( WP_CONTENT_DIR . \'/debug.log\' ) : \'\';"' );
    expect( log.split( '\n' ).filter( ( l ) => /PHP (Warning|Notice|Deprecated|Fatal)/.test( l ) ) ).toEqual( [] );
} );
