// Helpers that run WP-CLI in the wp-env development container.
const { execSync } = require( 'child_process' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..', '..' );

function wp( args ) {
    return execSync( `npx wp-env run cli wp ${ args }`, {
        cwd: root,
        encoding: 'utf8',
        env: { ...process.env, MSYS_NO_PATHCONV: '1' },
        stdio: [ 'ignore', 'pipe', 'ignore' ],
    } ).trim();
}

module.exports = { wp, root };
