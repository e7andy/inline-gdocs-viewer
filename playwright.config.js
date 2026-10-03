// Browser tests. Run with: npm run test:e2e (needs `npm run env:start`).
const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
    testDir: './tests/e2e',
    globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
    timeout: 60000,
    workers: 1,
    use: {
        baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
        acceptDownloads: true,
    },
    reporter: [ [ 'list' ] ],
} );
