<?php
/**
 * Creates the posts that the browser tests visit and prints their IDs as JSON.
 *
 * Run with: wp eval-file wp-content/plugins/inline-gdocs-viewer/tests/e2e/setup.php
 *
 * The data source URLs are served by tests/e2e/mu-plugin.php.
 */

global $wpdb;

$csv   = 'https://example.test/goals.csv';
$posts = array(
    'table'       => '[gdoc key="' . $csv . '" title="Goals"]',
    'fixed'       => '[gdoc key="' . $csv . '" class="FixedHeader FixedColumns-left-1" lang="nl-NL"]',
    'plain'       => '[gdoc key="' . $csv . '" class="no-datatables"]',
    'query'       => '[gdoc key="' . $csv . '" class="no-datatables" query="select A, B where B %3E 6 order by B desc"]',
    'pie'         => '[gdoc key="' . $csv . '" chart="Pie" title="Goals pie" chart_colors="red green blue orange"]',
    'bar'         => '[gdoc key="' . $csv . '" chart="Bar" query="select A, B where B %3E 6"]',
    'viewer'      => '[gdoc key="https://example.test/report" height="400"]',
    'export'      => '[gdoc key="https://example.test/export?format=csv"]',
    'webapp'      => '[gdoc key="https://script.google.com/macros/s/IGSV-E2E-HTML/exec"]',
    'webapp_csv'  => '[gdoc key="https://script.google.com/macros/s/IGSV-E2E-CSV/exec"]',
    'sql'         => '[gdoc key="wordpress" query="SELECT post_title AS Title, post_name AS Slug FROM ' . $wpdb->posts . ' WHERE post_name LIKE \'igsv-e2e-%\' AND post_status = \'publish\' ORDER BY post_name"]',
    'none'        => 'This page has no shortcode.',
);

// Posts written by a Contributor, who may not publish unfiltered HTML.
$by_contributor = array(
    'webapp_contributor' => $posts['webapp'],
);

// Optional live tests against real resources, passed as name=value
// arguments by tests/e2e/global-setup.js:
//   sheet=<public sheet URL> gid=<second tab> private=<unshared sheet URL>
//   webapp=<Apps Script /exec URL> files=<base URL of tests/e2e/fixtures>
$live = array();
foreach ( isset( $args ) ? $args : array() as $arg ) {
    $pair = explode( '=', $arg, 2 );
    if ( 2 === count( $pair ) ) {
        $live[ $pair[0] ] = $pair[1];
    }
}
if ( ! empty( $live['sheet'] ) && preg_match( '!^https://docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]+)!', $live['sheet'], $m ) ) {
    $base = $m[0];
    $posts['live_table']  = '[gdoc key="' . $base . '/edit" use_cache="no"]';
    $posts['live_bare']   = '[gdoc key="' . $m[1] . '" class="no-datatables" use_cache="no"]';
    $posts['live_query']  = '[gdoc key="' . $base . '/edit" class="no-datatables" csv_headers="1" use_cache="no" query="select A, B where B %3E 6 order by B desc"]';
    $posts['live_chart']  = '[gdoc key="' . $base . '/edit" chart="Bar" title="Goals per team" csv_headers="1" query="select A, B order by B desc"]';
    if ( ! empty( $live['gid'] ) && ctype_digit( $live['gid'] ) ) {
        $posts['live_tab'] = '[gdoc key="' . $base . '/edit#gid=' . $live['gid'] . '" class="no-datatables" use_cache="no"]';
    }
}
if ( ! empty( $live['private'] ) && preg_match( '!^https://docs\.google\.com/spreadsheets/d/[A-Za-z0-9_-]+!', $live['private'], $m ) ) {
    $posts['live_private'] = '[gdoc key="' . $m[0] . '/edit" use_cache="no"]';
}
if ( ! empty( $live['webapp'] ) && preg_match( '!^https://script\.google\.com/macros/s/[A-Za-z0-9_-]+/exec$!', $live['webapp'] ) ) {
    // These use the normal cache (cleared below), so the web app is asked once per run.
    $posts['live_webapp']                    = '[gdoc key="' . $live['webapp'] . '"]';
    $by_contributor['live_webapp_contributor'] = $posts['live_webapp'];
    $posts['live_webapp_csv']                = '[gdoc key="' . $live['webapp'] . '?format=csv"]';
    $posts['live_webapp_chart']              = '[gdoc key="' . $live['webapp'] . '" chart="Pie" title="Web app goals"]';
}
if ( ! empty( $live['files'] ) && preg_match( '!^https://[A-Za-z0-9.-]+/[A-Za-z0-9_./-]+$!', $live['files'] ) ) {
    $posts['live_file_csv']   = '[gdoc key="' . $live['files'] . '/goals.csv" use_cache="no"]';
    $posts['live_file_chart'] = '[gdoc key="' . $live['files'] . '/goals.csv" chart="Column" title="Goals from a file"]';
    $posts['live_file_pdf']   = '[gdoc key="' . $live['files'] . '/report.pdf" height="400"]';
}

// SQL shortcodes only run when the setting is on and an administrator
// saved the post, which is what happens below.
$options = get_option( 'gdoc_settings', array() );
$options['allow_sql_db_queries'] = 1;
update_option( 'gdoc_settings', $options );

$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admin[0]->ID );

$contributor = get_user_by( 'login', 'igsv-e2e-contributor' );
$contributor_id = $contributor ? $contributor->ID : wp_insert_user( array(
    'user_login' => 'igsv-e2e-contributor',
    'user_pass'  => wp_generate_password(),
    'role'       => 'contributor',
) );

$ids = array();
foreach ( array_merge( $posts, $by_contributor ) as $name => $content ) {
    $existing = get_page_by_path( 'igsv-e2e-' . str_replace( '_', '-', $name ), OBJECT, 'post' );
    $data     = array(
        'post_title'   => 'IGSV e2e ' . $name,
        'post_name'    => 'igsv-e2e-' . str_replace( '_', '-', $name ),
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_author'  => isset( $by_contributor[ $name ] ) ? $contributor_id : $admin[0]->ID,
    );
    if ( $existing ) {
        $data['ID'] = $existing->ID;
    }
    $ids[ $name ] = wp_insert_post( wp_slash( $data ) );
}

// Start from fresh data: clear the plugin's cached responses.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_gdoc%' OR option_name LIKE '\\_transient\\_timeout\\_gdoc%'" );
wp_cache_flush();

delete_transient( 'doing_cron' );
@unlink( WP_CONTENT_DIR . '/debug.log' );
echo wp_json_encode( $ids );
