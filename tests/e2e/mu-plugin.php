<?php
/**
 * Test-only must-use plugin for the browser tests (development site only).
 *
 * Serves fixture files for fake data source URLs, so the browser tests
 * don't depend on outside servers. Each URL has its own file and content
 * type, and answers both GET and HEAD requests.
 */

function igsv_e2e_fixtures() {
    return array(
        'https://example.test/goals.csv'                        => array( 'goals.csv', 'text/csv; charset=utf-8' ),
        'https://example.test/export?format=csv'                => array( 'goals.csv', 'text/csv; charset=utf-8' ),
        'https://example.test/report'                           => array( 'report.pdf', 'application/pdf' ),
        'https://script.google.com/macros/s/IGSV-E2E-HTML/exec' => array( 'webapp.html', 'text/html; charset=utf-8' ),
        'https://script.google.com/macros/s/IGSV-E2E-CSV/exec'  => array( 'goals.csv', 'text/csv; charset=utf-8' ),
    );
}

add_filter( 'gdoc_url_allowed', function ( $allowed, $url ) {
    return isset( igsv_e2e_fixtures()[ $url ] ) ? true : $allowed;
}, 10, 2 );

add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
    $fixtures = igsv_e2e_fixtures();
    if ( ! isset( $fixtures[ $url ] ) ) {
        return $pre;
    }
    list( $file, $type ) = $fixtures[ $url ];
    return array(
        'headers'  => array( 'content-type' => $type ),
        'body'     => 'HEAD' === $args['method'] ? '' : file_get_contents( __DIR__ . '/igsv-e2e-fixtures/' . $file ),
        'response' => array( 'code' => 200, 'message' => 'OK' ),
        'cookies'  => array(),
        'filename' => null,
    );
}, 10, 3 );
