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

    public function test_http_opts_allows_safe_keys_only_for_trusted_authors() {
        if ( is_multisite() ) {
            $this->markTestSkipped( 'Administrators lack unfiltered_html on multisite.' );
        }
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->make_post_by( 'administrator' );
        do_shortcode( '[gdoc key="https://example.com/data.csv" http_opts=\'{"method":"POST","timeout":500,"user-agent":"Agent","headers":{"X-Test":"1","Host":"evil.example"},"body":"x=1","sslverify":false,"reject_unsafe_urls":false}\']' );

        $args = $this->http_requests[0]['args'];
        $this->assertSame( 'POST', $args['method'] );
        $this->assertSame( 30, $args['timeout'], 'Timeout is capped.' );
        $this->assertSame( 'Agent', $args['user-agent'] );
        $this->assertSame( array( 'X-Test' => '1' ), $args['headers'], 'Host is never allowed.' );
        $this->assertSame( 'x=1', $args['body'] );
        $this->assertTrue( $args['sslverify'], 'sslverify cannot be turned off.' );
        $this->assertTrue( $args['reject_unsafe_urls'], 'Unsafe URLs are always rejected.' );
    }

    public function test_http_opts_post_body_and_headers_dropped_for_contributors() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $post = $this->make_post_by( 'contributor' );
        $this->render( '[gdoc key="https://example.com/data.csv" http_opts=\'{"method":"POST","user-agent":"Agent","timeout":9,"headers":{"X-Test":"1"},"body":"x=1"}\']', $post );

        $args = $this->http_requests[0]['args'];
        $this->assertSame( 'GET', $args['method'], 'A contributor cannot force POST.' );
        $this->assertSame( 'Agent', $args['user-agent'], 'Harmless options still work.' );
        $this->assertSame( 9, $args['timeout'] );
        // WordPress adds its own defaults, so check the author's values didn't get in.
        $this->assertArrayNotHasKey( 'X-Test', (array) $args['headers'] );
        $this->assertEmpty( $args['body'] );
    }

    public function test_default_timeout_allows_slow_web_apps() {
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $this->render( '[gdoc key="https://example.com/data.csv"]' );
        $this->assertSame( 15, $this->http_requests[0]['args']['timeout'] );

        do_shortcode( '[gdoc key="https://example.com/data.csv" use_cache="no" http_opts=\'{"timeout":3}\']' );
        $this->assertSame( 3, $this->http_requests[1]['args']['timeout'], 'http_opts can still set the timeout.' );
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
            'loopback'        => array( 'http://127.0.0.1/data.csv' ),
            'cloud metadata'  => array( 'http://169.254.169.254/latest/meta-data/x.csv' ),
            'private range'   => array( 'http://10.0.0.1/data.csv' ),
            'localhost'       => array( 'http://localhost/data.csv' ),
            'carrier-grade'   => array( 'http://100.64.0.1/data.csv' ),
            'alibaba meta'    => array( 'http://100.100.100.200/latest.csv' ),
            'benchmarking'    => array( 'http://198.18.0.1/data.csv' ),
            'all zeros'       => array( 'http://0.0.0.0/data.csv' ),
        );
    }

    /**
     * IPv6 URLs can't go through a shortcode (their brackets break shortcode
     * parsing), so check the address policy directly.
     *
     * @dataProvider provide_address_policy
     */
    public function test_url_address_policy( $url, $allowed ) {
        $ref = new ReflectionMethod( \WP_IGSV\InlineGoogleSpreadsheetViewerPlugin::class, 'isUrlAllowed' );
        $ref->setAccessible( true );
        $this->assertSame( $allowed, $ref->invoke( null, $url ), $url );
    }

    public function provide_address_policy() {
        return array(
            'public ipv4'     => array( 'http://8.8.8.8/data.csv', true ),
            'public ipv6'     => array( 'http://[2606:4700:4700::1111]/x.csv', true ),
            'ipv6 loopback'   => array( 'http://[::1]/data.csv', false ),
            'ipv6 link local' => array( 'http://[fe80::1]/data.csv', false ),
            'ipv6 unique'     => array( 'http://[fd00:ec2::254]/data.csv', false ),
            'ipv4-in-ipv6'    => array( 'http://[::ffff:127.0.0.1]/x.csv', false ),
            'metadata ipv4'   => array( 'http://169.254.169.254/x.csv', false ),
            'ftp scheme'      => array( 'ftp://example.com/x.csv', false ),
            'with credentials'=> array( 'http://user:pass@example.com/x.csv', false ),
        );
    }

    public function test_public_ipv4_literal_is_fetched() {
        $this->mock_http( 'http://8.8.8.8/data.csv', self::CSV );
        $this->assertStringContainsString( 'igsv-table', $this->render( '[gdoc key="http://8.8.8.8/data.csv"]' ) );
    }

    public function test_gdoc_url_allowed_filter_can_permit_a_private_address() {
        $this->mock_http( 'http://10.1.2.3/intranet.csv', self::CSV );
        add_filter( 'gdoc_url_allowed', function ( $allowed, $url ) {
            return 0 === strpos( $url, 'http://10.1.2.3/' ) ? true : $allowed;
        }, 10, 2 );
        $this->assertStringContainsString( 'igsv-table', $this->render( '[gdoc key="http://10.1.2.3/intranet.csv"]' ) );
    }

    public function test_oversized_responses_are_refused() {
        $big = str_repeat( 'A,B', 1 ) . "\n" . str_repeat( 'x,y\n', 100 );
        add_filter( 'gdoc_max_response_bytes', function () {
            return 10;
        } );
        $this->mock_http( 'https://example.com/big.csv', "Team,Goals\nAliens,5\nNinjas,12\n" );
        $html = $this->render( '[gdoc key="https://example.com/big.csv"]' );
        $this->assertStringContainsString( 'too large', $html );
        $this->assertStringNotContainsString( 'igsv-table', $html );
    }

    public function test_declared_content_length_is_refused_before_download() {
        add_filter( 'gdoc_max_response_bytes', function () {
            return 100;
        } );
        add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
            if ( 'https://example.com/huge.csv' === $url ) {
                return array(
                    'headers'  => array( 'content-type' => 'text/csv', 'content-length' => '999999999' ),
                    'body'     => 'small body',
                    'response' => array( 'code' => 200, 'message' => 'OK' ),
                    'cookies'  => array(), 'filename' => null,
                );
            }
            return $pre;
        }, 20, 3 );
        $html = $this->render( '[gdoc key="https://example.com/huge.csv"]' );
        $this->assertStringContainsString( 'too large', $html );
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
