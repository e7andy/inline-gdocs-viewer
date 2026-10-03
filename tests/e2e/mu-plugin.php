<?php
/**
 * Test-only must-use plugin for the browser tests (development site only).
 *
 * Serves fixture CSV files for https://example.test/ URLs, so the browser
 * tests don't depend on outside servers.
 */

add_filter( 'gdoc_url_allowed', function ( $allowed, $url ) {
    return 0 === strpos( $url, 'https://example.test/' ) ? true : $allowed;
}, 10, 2 );

add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
    if ( 0 !== strpos( $url, 'https://example.test/' ) ) {
        return $pre;
    }
    $file = __DIR__ . '/igsv-e2e-fixtures/' . basename( wp_parse_url( $url, PHP_URL_PATH ) );
    if ( ! is_readable( $file ) ) {
        return array( 'headers' => array(), 'body' => 'Not found', 'response' => array( 'code' => 404, 'message' => 'Not Found' ), 'cookies' => array(), 'filename' => null );
    }
    return array(
        'headers'  => array( 'content-type' => 'text/csv; charset=utf-8' ),
        'body'     => file_get_contents( $file ),
        'response' => array( 'code' => 200, 'message' => 'OK' ),
        'cookies'  => array(),
        'filename' => null,
    );
}, 10, 3 );
