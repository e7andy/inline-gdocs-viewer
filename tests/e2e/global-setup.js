// Creates the posts that the browser tests visit.
const fs = require( 'fs' );
const path = require( 'path' );
const { wp } = require( './wp' );

module.exports = async () => {
    // IGSV_LIVE_SHEET: optional URL of a public Google Sheet for live-google.spec.js.
    // Only the sheet's base URL is passed on, so nothing else reaches the shell.
    const match = ( process.env.IGSV_LIVE_SHEET || '' ).match( /^https:\/\/docs\.google\.com\/spreadsheets\/d\/[\w-]+/ );
    const sheet = match ? ` ${ match[ 0 ] }/edit` : '';
    const out = wp( 'eval-file wp-content/plugins/inline-gdocs-viewer/tests/e2e/setup.php' + sheet );
    const json = out.slice( out.indexOf( '{' ), out.lastIndexOf( '}' ) + 1 );
    fs.writeFileSync( path.join( __dirname, '.posts.json' ), json );
};
