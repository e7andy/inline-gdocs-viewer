<?php
/**
 * Creates the posts that the browser tests visit and prints their IDs as JSON.
 *
 * Run with: wp eval-file wp-content/plugins/inline-gdocs-viewer/tests/e2e/setup.php
 */

$csv   = 'https://example.test/goals.csv';
$posts = array(
    'table'  => '[gdoc key="' . $csv . '" title="Goals"]',
    'fixed'  => '[gdoc key="' . $csv . '" class="FixedHeader FixedColumns-left-1" lang="nl-NL"]',
    'plain'  => '[gdoc key="' . $csv . '" class="no-datatables"]',
    'query'  => '[gdoc key="' . $csv . '" class="no-datatables" query="select A, B where B %3E 6 order by B desc"]',
    'pie'    => '[gdoc key="' . $csv . '" chart="Pie" title="Goals pie" chart_colors="red green blue orange"]',
    'bar'    => '[gdoc key="' . $csv . '" chart="Bar" query="select A, B where B %3E 6"]',
    'viewer' => '[gdoc key="https://example.com/paper.pdf" height="400"]',
    'none'   => 'This page has no shortcode.',
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

$admin = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admin[0]->ID );

$ids = array();
foreach ( $posts as $name => $content ) {
    $existing = get_page_by_path( 'igsv-e2e-' . $name, OBJECT, 'post' );
    $data     = array(
        'post_title'   => 'IGSV e2e ' . $name,
        'post_name'    => 'igsv-e2e-' . $name,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_author'  => $admin[0]->ID,
    );
    if ( $existing ) {
        $data['ID'] = $existing->ID;
    }
    $ids[ $name ] = wp_insert_post( wp_slash( $data ) );
}

delete_transient( 'doing_cron' );
@unlink( WP_CONTENT_DIR . '/debug.log' );
echo wp_json_encode( $ids );
