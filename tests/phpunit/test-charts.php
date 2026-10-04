<?php
/**
 * Chart output and the signed chart data endpoint.
 */

use WP_IGSV\InlineGoogleSpreadsheetViewerPlugin as Plugin;

class Test_Charts extends IGSV_TestCase {

    /**
     * Renders a chart and returns its data source URL's query parameters.
     */
    private function chart_datasource_params( $shortcode ) {
        $xp   = $this->xpath( $this->render( $shortcode ) );
        $div  = $xp->query( '//div[contains(@class,"igsv-chart")]' )->item( 0 );
        $this->assertNotNull( $div, 'Chart container rendered.' );
        $href = $div->getAttribute( 'data-datasource-href' );
        $this->assertStringStartsWith( home_url( '/' ), $href );
        parse_str( (string) wp_parse_url( $href, PHP_URL_QUERY ), $params );
        return $params;
    }

    public function test_chart_markup() {
        $html = $this->render( '[gdoc key="https://example.com/data.csv" chart="Pie" title="Goals" chart_colors="red green" chart_background_color=\'{"fill":"yellow"}\']Fallback[/gdoc]' );
        $xp   = $this->xpath( $html );
        $div  = $xp->query( '//div[contains(@class,"igsv-chart")]' )->item( 0 );

        $this->assertSame( 'Pie', $div->getAttribute( 'data-chart-type' ) );
        $this->assertSame( 'Goals', $div->getAttribute( 'title' ) );
        $this->assertSame( 'red green', $div->getAttribute( 'data-chart-colors' ) );
        $this->assertSame( '{"fill":"yellow"}', $div->getAttribute( 'data-chart-background-color' ) );
        $this->assertSame( 'Fallback', trim( $div->textContent ) );
        $this->assertCount( 0, $this->http_requests, 'Rendering a chart makes no HTTP request.' );
    }

    public function test_chart_attribute_cannot_break_out() {
        $html = $this->render( '[gdoc key="https://example.com/data.csv" chart="Pie" chart_colors="red%27 onmouseover=%27alert(1)"]' );
        $xp   = $this->xpath( $html );
        $div  = $xp->query( '//div[contains(@class,"igsv-chart")]' )->item( 0 );
        $this->assertFalse( $div->hasAttribute( 'onmouseover' ) );
        $this->assertSame( "red' onmouseover='alert(1)", $div->getAttribute( 'data-chart-colors' ) );
    }

    public function test_chart_content_is_filtered() {
        $post = $this->make_post_by( 'contributor' );
        $html = $this->render( '[gdoc key="https://example.com/data.csv" chart="Pie"]<script>alert(1)</script><em>ok</em>[/gdoc]', $post );
        $this->assertStringNotContainsString( '<script>', $html );
        $this->assertStringContainsString( '<em>ok</em>', $html );
    }

    public function test_rendering_a_chart_does_not_write_options() {
        $this->make_post_by( 'administrator' );
        $before = get_option( 'gdoc_settings' );
        $writes = 0;
        $count  = function () use ( &$writes ) {
            $writes++;
        };
        add_action( 'update_option', $count );
        do_shortcode( '[gdoc key="https://example.com/data.csv" chart="Bar"]' );
        remove_action( 'update_option', $count );
        $this->assertSame( 0, $writes );
        $this->assertSame( $before, get_option( 'gdoc_settings' ) );
    }

    public function test_spreadsheet_chart_uses_google_directly() {
        $xp   = $this->xpath( $this->render( '[gdoc key="https://docs.google.com/spreadsheets/d/ABC/edit" chart="Line"]' ) );
        $href = $xp->query( '//div[contains(@class,"igsv-chart")]' )->item( 0 )->getAttribute( 'data-datasource-href' );
        $this->assertStringStartsWith( 'https://docs.google.com/spreadsheets/d/ABC/gviz/tq?', $href );
    }

    public function test_annotated_timeline_maps_to_annotation_chart() {
        $xp = $this->xpath( $this->render( '[gdoc key="https://docs.google.com/spreadsheets/d/ABC/edit" chart="AnnotatedTimeLine"]' ) );
        $this->assertSame( 'Annotation', $xp->query( '//div[contains(@class,"igsv-chart")]' )->item( 0 )->getAttribute( 'data-chart-type' ) );
    }

    public function test_datasource_serves_signed_request() {
        $params = $this->chart_datasource_params( '[gdoc key="https://example.com/data.csv" chart="Bar" query="select A, B where B %3E 6"]' );
        $this->mock_http( 'https://example.com/data.csv', self::CSV );

        $params['tqx'] = 'reqId:7';
        $response      = Plugin::handleDatasourceRequest( $params );

        $this->assertSame( 200, $response['status'] );
        $this->assertSame( 'nosniff', $response['headers']['X-Content-Type-Options'] );
        $this->assertStringStartsWith( 'application/javascript', $response['headers']['Content-Type'] );
        $this->assertStringStartsWith( 'google.visualization.Query.setResponse(', $response['body'] );
        $this->assertStringContainsString( 'reqId:"7"', $response['body'] );
        $this->assertStringContainsString( 'Ninjas', $response['body'] );
        $this->assertStringNotContainsString( 'Aliens', $response['body'] );
    }

    public function test_datasource_rejects_unsigned_or_tampered_requests() {
        $params = $this->chart_datasource_params( '[gdoc key="https://example.com/data.csv" chart="Bar"]' );
        $this->mock_http( 'https://example.com/*', self::CSV );

        $missing = $params;
        unset( $missing['igsv_sig'] );
        $this->assertSame( 403, Plugin::handleDatasourceRequest( $missing )['status'] );

        $tampered = $params;
        $tampered['igsv_src'] = rtrim( strtr( base64_encode( wp_json_encode( array( 'k' => 'https://example.com/other.csv', 'q' => '', 'h' => 0 ) ) ), '+/', '-_' ), '=' );
        $this->assertSame( 403, Plugin::handleDatasourceRequest( $tampered )['status'] );

        $this->assertSame( 403, Plugin::handleDatasourceRequest( array( 'igsv_datasource' => '1', 'url' => 'http://169.254.169.254/' ) )['status'] );
        $this->assertCount( 0, $this->http_requests );
    }

    public function test_datasource_ignores_client_query_and_unsafe_formats() {
        $params = $this->chart_datasource_params( '[gdoc key="https://example.com/data.csv" chart="Bar"]' );
        $this->mock_http( 'https://example.com/data.csv', self::CSV );

        $params['tq']  = "select A where '<img src=x onerror=alert(1)>'";
        $params['tqx'] = 'out:html;responseHandler:alert(document.cookie)//';
        $response      = Plugin::handleDatasourceRequest( $params );

        $this->assertSame( 200, $response['status'] );
        $this->assertStringStartsWith( 'application/javascript', $response['headers']['Content-Type'] );
        $this->assertStringStartsWith( 'google.visualization.Query.setResponse(', $response['body'] );
        $this->assertStringNotContainsString( '<img', $response['body'] );
    }

    public function test_datasource_accepts_google_response_handler() {
        $params = $this->chart_datasource_params( '[gdoc key="https://example.com/data.csv" chart="Bar"]' );
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        $params['tqx'] = 'reqId:0;responseHandler:google.visualization.Query.setResponse';
        $this->assertStringStartsWith( 'google.visualization.Query.setResponse(', Plugin::handleDatasourceRequest( $params )['body'] );
    }

    public function test_datasource_rejects_non_google_response_handler() {
        $params = $this->chart_datasource_params( '[gdoc key="https://example.com/data.csv" chart="Bar"]' );
        $this->mock_http( 'https://example.com/data.csv', self::CSV );
        // A handler outside google.visualization.* is ignored; the default is used.
        $params['tqx'] = 'reqId:0;responseHandler:alert';
        $this->assertStringStartsWith( 'google.visualization.Query.setResponse(', Plugin::handleDatasourceRequest( $params )['body'] );
    }

    public function test_datasource_types_numeric_columns() {
        $params = $this->chart_datasource_params( '[gdoc key="https://example.com/data.csv" chart="Bar"]' );
        $this->mock_http( 'https://example.com/data.csv', "Team,Goals\nAliens,5\nNinjas,12\n" );
        $body = Plugin::handleDatasourceRequest( $params )['body'];
        $this->assertMatchesRegularExpression( '/label:"Goals"[^}]*type:"number"|type:"number"[^}]*label:"Goals"/', $body );
    }

    public function test_datasource_handles_fetch_errors() {
        $params = $this->chart_datasource_params( '[gdoc key="https://example.com/data.csv" chart="Bar"]' );
        $this->mock_http( 'https://example.com/data.csv', 'gone', 'text/plain', 404 );
        $response = Plugin::handleDatasourceRequest( $params );
        $this->assertSame( 502, $response['status'] );
        $this->assertStringContainsString( 'status:"error"', $response['body'] );
    }
}
