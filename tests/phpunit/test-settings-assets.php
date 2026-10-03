<?php
/**
 * Settings, front-end assets, and uninstalling.
 */

use WP_IGSV\InlineGoogleSpreadsheetViewerPlugin as Plugin;

class Test_Settings_Assets extends IGSV_TestCase {

    public function set_up() {
        parent::set_up();
        $GLOBALS['wp_scripts'] = null;
        $GLOBALS['wp_styles']  = null;
        wp_scripts();
        wp_styles();
    }

    public function test_validate_settings() {
        $clean = Plugin::validateSettings( array(
            'allow_sql_db_queries'       => '1',
            'load_assets_everywhere'     => 'yes',
            'datatables_classes'         => 'one <b>two</b>',
            'datatables_defaults_object' => '{"paging": false}',
            'unknown'                    => 'dropped',
        ) );
        $this->assertSame( 1, $clean['allow_sql_db_queries'] );
        $this->assertSame( 1, $clean['load_assets_everywhere'] );
        $this->assertSame( 'one two', $clean['datatables_classes'] );
        $this->assertEquals( array( 'paging' => false ), (array) $clean['datatables_defaults_object'] );
        $this->assertArrayNotHasKey( 'unknown', $clean );
    }

    public function test_invalid_defaults_json_keeps_previous_value() {
        update_option( 'gdoc_settings', array( 'datatables_classes' => 'igsv-table', 'datatables_defaults_object' => array( 'paging' => true ) ) );
        $clean = Plugin::validateSettings( array( 'datatables_defaults_object' => '{not json' ) );
        $this->assertEquals( array( 'paging' => true ), (array) $clean['datatables_defaults_object'] );
    }

    public function test_options_page_escapes_textarea() {
        wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
        update_option( 'gdoc_settings', array(
            'datatables_classes'         => 'igsv-table',
            'datatables_defaults_object' => array( 'x' => '</textarea><script>alert(1)</script>' ),
        ) );
        ob_start();
        Plugin::renderOptionsPage();
        $html = ob_get_clean();
        $this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
    }

    public function test_no_assets_on_pages_without_the_shortcode() {
        do_action( 'wp_enqueue_scripts' );
        $this->assertTrue( wp_script_is( 'igsv-datatables', 'registered' ) );
        $this->assertFalse( wp_script_is( 'igsv-datatables', 'enqueued' ) );
        $this->assertFalse( wp_script_is( 'igsv-gvizcharts', 'enqueued' ) );
        $this->assertFalse( wp_style_is( 'jquery-datatables', 'enqueued' ) );
    }

    public function test_table_enqueues_table_assets_only() {
        do_action( 'wp_enqueue_scripts' );
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv"]' );
        $this->assertTrue( wp_script_is( 'igsv-datatables', 'enqueued' ) );
        $this->assertTrue( wp_script_is( 'jszip', 'enqueued' ) );
        $this->assertTrue( wp_style_is( 'jquery-datatables', 'enqueued' ) );
        $this->assertFalse( wp_script_is( 'igsv-gvizcharts', 'enqueued' ) );
    }

    public function test_chart_enqueues_current_google_loader() {
        do_action( 'wp_enqueue_scripts' );
        $this->render( '[gdoc key="https://example.com/data.csv" chart="Pie"]' );
        $this->assertTrue( wp_script_is( 'igsv-gvizcharts', 'enqueued' ) );
        $this->assertSame( 'https://www.gstatic.com/charts/loader.js', wp_scripts()->registered['google-ajax-api']->src );
    }

    public function test_assets_detected_in_queried_post_content() {
        $post_id = self::factory()->post->create( array( 'post_content' => '[gdoc key="x"]' ) );
        $this->go_to( get_permalink( $post_id ) );
        do_action( 'wp_enqueue_scripts' );
        $this->assertTrue( wp_script_is( 'igsv-datatables', 'enqueued' ) );
        $this->assertTrue( wp_style_is( 'jquery-datatables', 'enqueued' ), 'Styles load in the head.' );
    }

    public function test_shortcode_rendered_before_enqueue_hook_gets_assets() {
        // Block themes render content before wp_enqueue_scripts.
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv"]' );
        do_shortcode( '[gdoc key="https://example.com/data.csv" chart="Pie"]' );
        $this->assertFalse( wp_script_is( 'igsv-datatables', 'enqueued' ) );
        do_action( 'wp_enqueue_scripts' );
        $this->assertTrue( wp_script_is( 'igsv-datatables', 'enqueued' ) );
        $this->assertTrue( wp_script_is( 'igsv-gvizcharts', 'enqueued' ) );
    }

    public function test_load_everywhere_setting() {
        $options = get_option( 'gdoc_settings' );
        $options['load_assets_everywhere'] = 1;
        update_option( 'gdoc_settings', $options );
        do_action( 'wp_enqueue_scripts' );
        $this->assertTrue( wp_script_is( 'igsv-datatables', 'enqueued' ) );
    }

    public function test_assets_are_bundled_locally() {
        do_action( 'wp_enqueue_scripts' );
        $base = plugins_url( '/', dirname( __DIR__, 2 ) . '/inline-gdocs-viewer.php' );
        foreach ( array( wp_scripts(), wp_styles() ) as $deps ) {
            foreach ( $deps->registered as $handle => $dep ) {
                if ( 'google-ajax-api' === $handle || ! is_string( $dep->src ) || 0 !== strpos( $dep->src, $base ) ) {
                    continue;
                }
                $path = dirname( __DIR__, 2 ) . '/' . substr( strtok( $dep->src, '?' ), strlen( $base ) );
                $this->assertFileExists( $path, $handle );
            }
        }
        $this->assertStringStartsWith( $base, wp_scripts()->registered['jquery-datatables']->src );
        $this->assertStringStartsWith( $base, wp_styles()->registered['jquery-datatables']->src );
    }

    public function test_bundled_libraries_include_their_licenses() {
        $dir = dirname( __DIR__, 2 ) . '/assets/vendor';
        $this->assertDirectoryExists( $dir );
        foreach ( glob( $dir . '/*', GLOB_ONLYDIR ) as $lib ) {
            $this->assertNotEmpty( glob( $lib . '/LICENSE*' ), basename( $lib ) . ' ships its license.' );
        }
    }

    public function test_dequeue_filter_still_works() {
        add_filter( 'gdoc_enqueued_front_end_scripts', function ( $scripts ) {
            unset( $scripts['jszip'] );
            return $scripts;
        } );
        do_action( 'wp_enqueue_scripts' );
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv"]' );
        $this->assertFalse( wp_script_is( 'jszip', 'registered' ) );
        $this->assertTrue( wp_script_is( 'igsv-datatables', 'enqueued' ) );
    }

    public function test_localized_vars_list_available_languages() {
        do_action( 'wp_enqueue_scripts' );
        $data = wp_scripts()->get_data( 'igsv-datatables', 'data' );
        $this->assertStringContainsString( '"languages"', $data );
        $this->assertStringContainsString( '"nl-NL"', $data );
    }

    public function test_uninstall_removes_everything() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv"]' );
        $post_id = self::factory()->post->create();
        update_post_meta( $post_id, '_gdoc_sql_authorized', array( 'x' ) );

        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            define( 'WP_UNINSTALL_PLUGIN', 'inline-gdocs-viewer/inline-gdocs-viewer.php' );
        }
        include dirname( __DIR__, 2 ) . '/uninstall.php';

        global $wpdb;
        $this->assertFalse( get_option( 'gdoc_settings' ) );
        $this->assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%gdoc%'" ) );
        $this->assertSame( '', get_post_meta( $post_id, '_gdoc_sql_authorized', true ) );
        $this->assertFalse( get_role( 'administrator' )->has_cap( 'gdoc_query_sql_databases' ) );
    }
}
