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

// Optional live tests against a real public Google Sheet, passed as the
// first argument: wp eval-file setup.php https://docs.google.com/spreadsheets/d/.../edit
$sheet = isset( $args[0] ) ? $args[0] : '';
if ( preg_match( '!^https://docs\.google\.com/spreadsheets/d/[A-Za-z0-9_-]+!', $sheet ) ) {
    $posts['live_table'] = '[gdoc key="' . $sheet . '" use_cache="no"]';
    $posts['live_query'] = '[gdoc key="' . $sheet . '" csv_headers="1" use_cache="no" query="select A, C, I where C contains \'2026\'"]';
    $posts['live_guess'] = '[gdoc key="' . $sheet . '" use_cache="no" query="select A, C"]';
    $posts['live_chart'] = '[gdoc key="' . $sheet . '" chart="Bar" title="Longest races" csv_headers="1" query="select A, I where I is not null order by I desc limit 8"]';
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
