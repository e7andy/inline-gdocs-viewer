// Creates the posts that the browser tests visit.
//
// Live tests (live-google.spec.js) use real resources from these optional
// environment variables. Only validated URLs are passed on to WP-CLI.
//   IGSV_LIVE_SHEET          public test sheet (see live-google.spec.js)
//   IGSV_LIVE_PRIVATE_SHEET  sheet that is not shared
//   IGSV_LIVE_WEBAPP         Apps Script web app (/exec URL)
//   IGSV_LIVE_FILES          base URL of tests/e2e/fixtures (default: this repo on GitHub)
const fs = require( 'fs' );
const path = require( 'path' );
const { wp } = require( './wp' );
const live = require( './live' );

module.exports = async () => {
    const args = [];
    if ( live.sheet ) {
        args.push( `sheet=${ live.sheet }` );
        const gid = await live.findTabGid( live.sheet, 'Name,Value' );
        if ( gid ) {
            args.push( `gid=${ gid }` );
        }
    }
    if ( live.privateSheet ) {
        args.push( `private=${ live.privateSheet }` );
    }
    if ( live.webapp ) {
        args.push( `webapp=${ live.webapp }` );
        // Wake the web app up, so its first answer in the tests isn't a slow start.
        await Promise.all( [ live.webapp, `${ live.webapp }?format=csv` ].map( ( u ) =>
            fetch( u, { signal: AbortSignal.timeout( 60000 ) } ).catch( () => null ) ) );
    }
    if ( live.enabled ) {
        args.push( `files=${ live.files }` );
    }
    const out = wp( 'eval-file wp-content/plugins/inline-gdocs-viewer/tests/e2e/setup.php ' + args.join( ' ' ) );
    const json = out.slice( out.indexOf( '{' ), out.lastIndexOf( '}' ) + 1 );
    fs.writeFileSync( path.join( __dirname, '.posts.json' ), json );
};
