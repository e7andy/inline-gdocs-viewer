<?php
/**
 * Data source cases beyond the basic table tests: CSV from addresses without
 * a .csv extension, Google Sheets by bare ID and tab, sheets that aren't
 * shared, and Apps Script charts.
 */

class Test_Data_Sources extends IGSV_TestCase {

    const EXPORT = 'https://example.com/export?format=csv&id=7';

    private function methods() {
        return array_map( function ( $r ) {
            return $r['args']['method'];
        }, $this->http_requests );
    }

    public function test_csv_from_address_without_csv_extension_becomes_a_table() {
        $this->mock_http( self::EXPORT, self::CSV, 'text/csv; charset=utf-8' );
        $xp = $this->xpath( $this->render( '[gdoc key="' . self::EXPORT . '"]' ) );
        $this->assertSame( 4, $xp->query( '//table[contains(@class,"igsv-table")]/tbody/tr' )->length );
        $this->assertSame( 0, $xp->query( '//iframe' )->length );
        $this->assertSame( array( 'HEAD', 'GET' ), $this->methods() );
    }

    /**
     * @dataProvider provide_csv_content_types
     */
    public function test_csv_content_types_are_recognized( $content_type ) {
        $this->mock_http( self::EXPORT, self::CSV, $content_type );
        $this->assertStringContainsString( 'igsv-table', $this->render( '[gdoc key="' . self::EXPORT . '"]' ) );
    }

    public function provide_csv_content_types() {
        return array(
            'text/csv'                    => array( 'text/csv' ),
            'with charset'                => array( 'text/csv; charset=UTF-8' ),
            'upper case'                  => array( 'Text/CSV' ),
            'application/csv'             => array( 'application/csv' ),
            'text/comma-separated-values' => array( 'text/comma-separated-values' ),
        );
    }

    public function test_query_works_on_csv_from_address_without_csv_extension() {
        $this->mock_http( self::EXPORT, self::CSV, 'text/csv' );
        $xp    = $this->xpath( $this->render( '[gdoc key="' . self::EXPORT . '" query="select A where B %3E 6"]' ) );
        $teams = array();
        foreach ( $xp->query( '//tbody/tr/td[1]' ) as $td ) {
            $teams[] = trim( $td->textContent );
        }
        $this->assertSame( array( 'Ninjas', 'Pirates' ), $teams );
    }

    public function test_documents_still_use_the_viewer_without_being_downloaded() {
        $this->mock_http( 'https://example.com/report', '%PDF-1.7 ...', 'application/pdf' );
        $xp = $this->xpath( $this->render( '[gdoc key="https://example.com/report"]' ) );
        $this->assertSame( 1, $xp->query( '//iframe[starts-with(@src,"https://docs.google.com/viewer?url=")]' )->length );
        $this->assertSame( array( 'HEAD' ), $this->methods() );
    }

    public function test_failed_content_type_check_falls_back_to_the_viewer() {
        $this->mock_http( 'https://example.com/no-head', '', 'text/html', 405 );
        $html = $this->render( '[gdoc key="https://example.com/no-head"]' );
        $this->assertStringContainsString( '<iframe', $html );
        $this->assertStringNotContainsString( 'igsv-error', $html );
    }

    public function test_private_address_shows_the_viewer_without_any_request() {
        $html = $this->render( '[gdoc key="http://10.0.0.5/internal-report"]' );
        $this->assertStringContainsString( '<iframe', $html );
        $this->assertCount( 0, $this->http_requests );
    }

    public function test_content_type_check_is_cached() {
        $this->mock_http( 'https://example.com/report', '', 'application/pdf' );
        $this->render( '[gdoc key="https://example.com/report"]' );
        do_shortcode( '[gdoc key="https://example.com/report"]' );
        $this->assertCount( 1, $this->http_requests );

        do_shortcode( '[gdoc key="https://example.com/report" use_cache="no"]' );
        $this->assertCount( 2, $this->http_requests, 'use_cache="no" checks again.' );
    }

    public function test_chart_from_address_without_csv_extension_uses_the_data_endpoint() {
        $xp   = $this->xpath( $this->render( '[gdoc key="' . self::EXPORT . '" chart="Bar"]' ) );
        $href = $xp->query( '//div[contains(@class,"igsv-chart")]' )->item( 0 )->getAttribute( 'data-datasource-href' );
        $this->assertStringStartsWith( home_url( '/' ), $href );
        $this->assertCount( 0, $this->http_requests, 'Rendering the chart fetches nothing.' );

        parse_str( (string) wp_parse_url( $href, PHP_URL_QUERY ), $params );
        $this->mock_http( self::EXPORT, self::CSV, 'text/csv' );
        $response = \WP_IGSV\InlineGoogleSpreadsheetViewerPlugin::handleDatasourceRequest( $params );
        $this->assertSame( 200, $response['status'] );
        $this->assertStringContainsString( 'Ninjas', $response['body'] );
    }

    public function test_apps_script_chart_queries_the_web_app_directly() {
        $app  = 'https://script.google.com/macros/s/XYZ/exec';
        $xp   = $this->xpath( $this->render( '[gdoc key="' . $app . '" chart="Line" title="App"]' ) );
        $div  = $xp->query( '//div[contains(@class,"igsv-chart")]' )->item( 0 );
        $this->assertSame( $app, $div->getAttribute( 'data-datasource-href' ) );
        $this->assertSame( 'Line', $div->getAttribute( 'data-chart-type' ) );
        $this->assertCount( 0, $this->http_requests );
    }

    public function test_sheet_that_is_not_shared_shows_a_helpful_error() {
        $signin = '<html><head><script>steal()</script></head><body>Sign in - Google Accounts</body></html>';
        $this->mock_http( 'https://docs.google.com/spreadsheets/d/PRIVATE/export?format=csv', $signin, 'text/html; charset=utf-8' );
        $html = $this->render( '[gdoc key="https://docs.google.com/spreadsheets/d/PRIVATE/edit"]' );
        $this->assertStringContainsString( 'igsv-error', $html );
        $this->assertStringContainsString( 'Anyone with the link', $html );
        $this->assertStringNotContainsString( 'Sign in', $html );
        $this->assertStringNotContainsString( '<script', $html );
    }

    /**
     * Google answers 401 for sheets that aren't shared (seen live), and 403
     * or 404 for sheets that are restricted or don't exist.
     *
     * @dataProvider provide_sheet_error_codes
     */
    public function test_sheet_error_status_shows_the_sharing_hint( $code ) {
        $this->mock_http( 'https://docs.google.com/spreadsheets/d/PRIVATE/export?format=csv', '<html>Sign in</html>', 'text/html', $code );
        $html = $this->render( '[gdoc key="https://docs.google.com/spreadsheets/d/PRIVATE/edit"]' );
        $this->assertStringContainsString( 'Anyone with the link', $html );
        $this->assertStringNotContainsString( 'Sign in', $html );
    }

    public function provide_sheet_error_codes() {
        return array( '401' => array( 401 ), '403' => array( 403 ), '404' => array( 404 ) );
    }

    public function test_other_sources_keep_the_http_status_error() {
        $this->mock_http( 'https://example.com/data.csv', 'Not found', 'text/plain', 404 );
        $html = $this->render( '[gdoc key="https://example.com/data.csv"]' );
        $this->assertStringContainsString( 'HTTP status 404', $html );
        $this->assertStringNotContainsString( 'Anyone with the link', $html );
    }

    public function test_sheet_by_bare_id_renders_a_table() {
        $this->mock_http( 'https://docs.google.com/spreadsheets/d/1AbC-dEf_123/export?format=csv', self::CSV );
        $xp = $this->xpath( $this->render( '[gdoc key="1AbC-dEf_123"]' ) );
        $this->assertSame( 4, $xp->query( '//tbody/tr' )->length );
    }

    public function test_sheet_tab_is_chosen_by_gid() {
        $this->mock_http( 'https://docs.google.com/spreadsheets/d/ABC/export?format=csv', "Tab\nFirst\n" );
        $this->mock_http( 'https://docs.google.com/spreadsheets/d/ABC/export?format=csv&gid=123', "Tab\nSecond\n" );

        $from_url = $this->xpath( $this->render( '[gdoc key="https://docs.google.com/spreadsheets/d/ABC/edit#gid=123"]' ) );
        $this->assertSame( 'Second', trim( $from_url->query( '//tbody//td' )->item( 0 )->textContent ) );

        $from_attribute = $this->xpath( do_shortcode( '[gdoc key="ABC" gid="123" use_cache="no"]' ) );
        $this->assertSame( 'Second', trim( $from_attribute->query( '//tbody//td' )->item( 0 )->textContent ) );

        $first = $this->xpath( do_shortcode( '[gdoc key="ABC" use_cache="no"]' ) );
        $this->assertSame( 'First', trim( $first->query( '//tbody//td' )->item( 0 )->textContent ) );
    }

    public function test_sheet_query_and_chart_by_bare_id() {
        $gviz = 'https://docs.google.com/spreadsheets/d/ABC/gviz/tq?tqx=out:csv&tq=select%20A&headers=1';
        $this->mock_http( $gviz, "Team\nAliens\n" );
        $xp = $this->xpath( $this->render( '[gdoc key="ABC" query="select A" csv_headers="1"]' ) );
        $this->assertSame( 'Aliens', trim( $xp->query( '//tbody//td' )->item( 0 )->textContent ) );

        $chart = $this->xpath( do_shortcode( '[gdoc key="ABC" chart="Pie"]' ) );
        $this->assertStringStartsWith(
            'https://docs.google.com/spreadsheets/d/ABC/gviz/tq?',
            $chart->query( '//div[contains(@class,"igsv-chart")]' )->item( 0 )->getAttribute( 'data-datasource-href' )
        );
    }
}
