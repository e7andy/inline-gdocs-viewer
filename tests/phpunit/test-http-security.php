<?php
/**
 * HTTP fetching: the http_opts attribute, internal-network protection, and caching.
 */

class Test_Http_Security extends IGSV_TestCase {

    public function test_http_opts_cannot_write_files() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $target = get_temp_dir() . 'igsv-should-not-exist.php';
        $this->render( '[gdoc key="https://example.com/data.csv" http_opts=\'{"stream":true,"filename":"' . $target . '"}\']' );

        $this->assertNotEmpty( $this->http_requests );
        $args = $this->http_requests[0]['args'];
        $this->assertFalse( $args['stream'] );
        $this->assertNull( $args['filename'] );
        $this->assertFileDoesNotExist( $target );
    }

    public function test_http_opts_allows_safe_keys_only() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv" http_opts=\'{"method":"POST","timeout":500,"user-agent":"Agent","headers":{"X-Test":"1"},"sslverify":false,"reject_unsafe_urls":false}\']' );

        $args = $this->http_requests[0]['args'];
        $this->assertSame( 'POST', $args['method'] );
        $this->assertSame( 30, $args['timeout'], 'Timeout is capped.' );
        $this->assertSame( 'Agent', $args['user-agent'] );
        $this->assertSame( array( 'X-Test' => '1' ), $args['headers'] );
        $this->assertTrue( $args['sslverify'], 'sslverify cannot be turned off.' );
        $this->assertTrue( $args['reject_unsafe_urls'], 'Unsafe URLs are always rejected.' );
    }

    public function test_http_opts_rejects_bad_method_and_bad_json() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv" http_opts=\'{"method":"DELETE"}\']' );
        $this->assertSame( 'GET', $this->http_requests[0]['args']['method'] );

        $html = do_shortcode( '[gdoc key="https://example.com/data.csv" use_cache="no" http_opts="not json"]' );
        $this->assertStringContainsString( 'igsv-table', $html, 'Invalid JSON is ignored, not fatal.' );
    }

    public function test_every_request_rejects_unsafe_urls() {
        $this->mock_http( 'https://example.com/*', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv"]' );
        foreach ( $this->http_requests as $request ) {
            $this->assertTrue( $request['args']['reject_unsafe_urls'], $request['url'] );
        }
    }

    /**
     * @dataProvider provide_internal_urls
     */
    public function test_internal_addresses_are_blocked( $url ) {
        $this->mock_http( $url, self::CSV );
        $html = $this->render( '[gdoc key="' . $url . '"]' );
        $this->assertStringContainsString( 'Error', $html );
        $this->assertStringNotContainsString( 'igsv-table', $html );
        $this->assertCount( 0, $this->http_requests, 'The request is refused before it is sent.' );
    }

    public function test_redirects_to_internal_addresses_are_refused() {
        $this->allow_real_http = true;
        $ref = new ReflectionMethod( \WP_IGSV\InlineGoogleSpreadsheetViewerPlugin::class, 'validateRedirect' );
        $ref->setAccessible( true );
        $this->expectException( \WpOrg\Requests\Exception::class );
        $location = 'http://169.254.169.254/latest/';
        $ref->invokeArgs( null, array( &$location ) );
    }

    public function provide_internal_urls() {
        return array(
            'loopback'       => array( 'http://127.0.0.1/data.csv' ),
            'cloud metadata' => array( 'http://169.254.169.254/latest/meta-data/x.csv' ),
            'private range'  => array( 'http://10.0.0.1/data.csv' ),
            'localhost'      => array( 'http://localhost/data.csv' ),
        );
    }

    public function test_non_http_schemes_are_refused() {
        $html = $this->render( '[gdoc key="file:///etc/passwd.csv"]' );
        $this->assertCount( 0, $this->http_requests );
        $this->assertStringContainsString( 'Error', $html );
    }

    public function test_responses_are_cached_as_plain_data() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv"]' );
        do_shortcode( '[gdoc key="https://example.com/data.csv"]' );
        $this->assertCount( 1, $this->http_requests, 'Second render is served from the cache.' );

        global $wpdb;
        $stored = $wpdb->get_col( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_gdoc%'" );
        $this->assertNotEmpty( $stored );
        foreach ( $stored as $value ) {
            $this->assertStringNotContainsString( 'O:', $value, 'No serialized objects in the cache.' );
            $this->assertStringNotContainsString( 'O:', (string) base64_decode( $value, true ) );
        }
    }

    public function test_use_cache_no_bypasses_cache() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv" use_cache="no"]' );
        do_shortcode( '[gdoc key="https://example.com/data.csv" use_cache="no"]' );
        $this->assertCount( 2, $this->http_requests );
    }

    public function test_cache_key_includes_options_that_change_the_request() {
        $this->mock_http( 'https://docs.google.com/*', self::CSV );
        $this->render( '[gdoc key="https://docs.google.com/spreadsheets/d/ABC/edit" query="select A"]' );
        do_shortcode( '[gdoc key="https://docs.google.com/spreadsheets/d/ABC/edit" query="select A" csv_headers="1"]' );
        $this->assertCount( 2, $this->http_requests );
    }

    public function test_error_responses_are_not_cached() {
        $this->mock_http( 'https://example.com/flaky.csv', 'oops', 'text/plain', 500 );
        $this->render( '[gdoc key="https://example.com/flaky.csv"]' );
        $this->mock_http( 'https://example.com/flaky.csv', self::CSV );
        $html = do_shortcode( '[gdoc key="https://example.com/flaky.csv"]' );
        $this->assertCount( 2, $this->http_requests );
        $this->assertStringContainsString( 'igsv-table', $html );
    }

    public function test_corrupt_cache_entry_is_ignored() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv"]' );
        global $wpdb;
        $wpdb->query( "UPDATE {$wpdb->options} SET option_value = 'TzoxMjoiRXZpbCI6MDp7fQ==' WHERE option_name LIKE '\\_transient\\_gdoc%'" );
        wp_cache_flush();
        $html = do_shortcode( '[gdoc key="https://example.com/data.csv"]' );
        $this->assertStringContainsString( 'igsv-table', $html );
    }
}
