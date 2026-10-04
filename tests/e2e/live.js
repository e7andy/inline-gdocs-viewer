// Live test resources, read from environment variables and validated.
// Each value is either a safe URL (no spaces or shell characters) or null.
const env = ( name, pattern ) => {
    const match = ( process.env[ name ] || '' ).trim().match( pattern );
    return match ? match[ 0 ] : null;
};

const sheet = env( 'IGSV_LIVE_SHEET', /^https:\/\/docs\.google\.com\/spreadsheets\/d\/[\w-]+/ );
const privateSheet = env( 'IGSV_LIVE_PRIVATE_SHEET', /^https:\/\/docs\.google\.com\/spreadsheets\/d\/[\w-]+/ );
const webapp = env( 'IGSV_LIVE_WEBAPP', /^https:\/\/script\.google\.com\/macros\/s\/[\w-]+\/exec$/ );
const files = env( 'IGSV_LIVE_FILES', /^https:\/\/[\w.-]+\/[\w./-]+$/ )
    || 'https://raw.githubusercontent.com/e7andy/inline-gdocs-viewer/master/tests/e2e/fixtures';

// Finds the gid of the tab whose CSV starts with the given header row, by
// trying the gids listed on the sheet's public HTML view.
async function findTabGid( sheetUrl, header ) {
    const html = await ( await fetch( `${ sheetUrl }/htmlview` ) ).text();
    const gids = [ ...new Set( [ ...html.matchAll( /gid=(\d+)/g ) ].map( ( m ) => m[ 1 ] ) ) ];
    for ( const gid of gids ) {
        const csv = await ( await fetch( `${ sheetUrl }/export?format=csv&gid=${ gid }` ) ).text();
        if ( csv.startsWith( header ) ) {
            return gid;
        }
    }
    return null;
}

module.exports = {
    sheet,
    privateSheet,
    webapp,
    files,
    enabled: Boolean( sheet || privateSheet || webapp || process.env.IGSV_LIVE_FILES ),
    findTabGid,
};
