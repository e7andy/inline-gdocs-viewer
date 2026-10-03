<?php
/**
 * PHPUnit bootstrap for the plugin, using the WordPress test suite.
 *
 * Run inside wp-env: npm run test:php
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = '/wordpress-phpunit';
}

// The WordPress test suite needs the PHPUnit Polyfills.
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter( 'muplugins_loaded', function () {
    require dirname( __DIR__, 2 ) . '/inline-gdocs-viewer.php';
} );

require $_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-igsv-testcase.php';
